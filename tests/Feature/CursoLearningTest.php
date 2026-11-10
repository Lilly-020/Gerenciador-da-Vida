<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\CursoFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CursoLearningTest extends TestCase
{
    use RefreshDatabase;

    public function test_learning_fields_are_saved_and_progress_is_automatic(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/cursos', [
            'title' => 'Laravel', 'platform' => 'Udemy', 'category' => 'Programação',
            'progress_unit' => 'lessons', 'total_lessons' => 40, 'completed_lessons' => 24,
            'duration_hours' => 20, 'studied_hours' => 12, 'notes' => "Módulo 1\nAprendi rotas.",
            'next_review_at' => '2027-01-15',
        ]);

        $response->assertOk()->assertJsonStructure(['card', 'stats']);
        $curso = $user->cursos()->firstOrFail();
        $this->assertSame(60, $curso->progress);
        $this->assertSame('in_progress', $curso->status);
        $this->assertDatabaseHas('cursos', ['id' => $curso->id, 'category' => 'Programação', 'studied_hours' => 12, 'completed_lessons' => 24]);
        $this->get('/cursos')->assertSee('24 de 40 aulas')->assertSee('60%')->assertSee('Horas estudadas');
    }

    public function test_finishing_lessons_records_completion_and_review_preserves_it_and_stats(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $curso = Curso::factory()->for($user)->create(['total_lessons' => 10, 'completed_lessons' => 5]);

        $this->actingAs($user)->putJson(route('cursos.update', $curso), ['title' => $curso->title, 'completed_lessons' => 10])->assertOk();

        $finishedAt = $curso->fresh()->completed_at;
        $this->assertNotNull($finishedAt);
        $this->assertSame('completed', $curso->fresh()->status);
        $response = $this->patchJson(route('cursos.move', $curso), ['status' => 'in_review'])->assertOk();
        $this->assertSame($finishedAt->toDateTimeString(), $curso->fresh()->completed_at->toDateTimeString());
        $this->assertSame(1, Curso::summarize($user->cursos()->get())['finalizados']);
        $this->assertStringContainsString('Finalizados', $response->json('stats'));
    }

    public function test_dragging_to_completed_updates_lessons_and_pausing_does_not_erase_progress(): void
    {
        $user = User::factory()->create();
        $curso = Curso::factory()->for($user)->create(['total_lessons' => 40, 'completed_lessons' => 24]);

        $this->actingAs($user)->patchJson(route('cursos.move', $curso), ['status' => 'paused'])->assertOk();

        $this->assertSame(24, $curso->fresh()->completed_lessons);
        $this->assertSame('paused', $curso->fresh()->status);
        $this->putJson(route('cursos.update', $curso), ['title' => $curso->title, 'studied_hours' => 15])->assertOk();
        $this->assertSame('paused', $curso->fresh()->status);
        $this->patchJson(route('cursos.move', $curso), ['status' => 'completed'])->assertOk();
        $this->assertSame(40, $curso->fresh()->completed_lessons);
        $this->assertSame(100, $curso->fresh()->progress);
    }

    public function test_status_change_cannot_silently_erase_learning_progress(): void
    {
        $user = User::factory()->create();
        $curso = Curso::factory()->for($user)->create(['total_lessons' => 10, 'completed_lessons' => 3]);

        $this->actingAs($user)->patchJson(route('cursos.move', $curso), ['status' => 'not_started'])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertSame(3, $curso->fresh()->completed_lessons);
        $this->assertSame('in_progress', $curso->fresh()->status);
    }

    public function test_total_cannot_be_reduced_below_existing_completed_lessons(): void
    {
        $user = User::factory()->create();
        $curso = Curso::factory()->for($user)->create(['total_lessons' => 40, 'completed_lessons' => 24]);

        $this->actingAs($user)->putJson(route('cursos.update', $curso), ['title' => $curso->title, 'total_lessons' => 10])->assertUnprocessable()->assertJsonValidationErrors('completed_lessons');

        $this->assertSame(40, $curso->fresh()->total_lessons);
    }

    public function test_course_can_be_rated_after_finishing_and_notes_are_escaped(): void
    {
        $user = User::factory()->create();
        $curso = Curso::factory()->for($user)->status('completed')->create();
        $note = '<script>alert("notes")</script>';

        $this->actingAs($user)->putJson(route('cursos.update', $curso), ['title' => $curso->title, 'rating' => 5, 'notes' => $note])->assertOk();

        $this->assertDatabaseHas('cursos', ['id' => $curso->id, 'rating' => 5, 'notes' => $note]);
        $this->get('/cursos')->assertSee($note)->assertDontSee($note, false)->assertSee('5/5');
    }

    public function test_certificate_upload_and_material_reclassification(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $curso = Curso::factory()->for($user)->create();

        $this->actingAs($user)->postJson(route('cursos.arquivos.store', $curso), [
            'file' => UploadedFile::fake()->create('certificate.pdf', 10, 'application/pdf'), 'kind' => 'certificate',
        ])->assertOk();

        $file = $curso->files()->firstOrFail();
        $this->assertSame('certificate', $file->kind);
        Storage::disk('public')->assertExists($file->path);
        $this->patchJson(route('cursos.arquivos.update', [$curso, $file]), ['kind' => 'material'])->assertOk();
        $this->assertSame('material', $file->fresh()->kind);
    }

    public function test_file_reclassification_is_scoped_to_the_course_and_user(): void
    {
        $user = User::factory()->create();
        $curso = Curso::factory()->for($user)->create();
        $file = CursoFile::factory()->create();

        $this->actingAs($user)->patchJson(route('cursos.arquivos.update', [$curso, $file]), ['kind' => 'certificate'])->assertNotFound();
        $this->patchJson(route('cursos.arquivos.update', [$file->curso_id, $file]), ['kind' => 'certificate'])->assertNotFound();

        $this->assertSame('material', $file->fresh()->kind);
    }

    public function test_stats_sum_study_hours_and_exclude_other_users(): void
    {
        $user = User::factory()->create();
        Curso::factory()->for($user)->create(['studied_hours' => 12.5]);
        Curso::factory()->for($user)->status('in_review')->create(['studied_hours' => 2]);
        Curso::factory()->create(['studied_hours' => 999]);

        $this->actingAs($user)->get('/cursos')->assertSee('14,5h')->assertDontSee('999,0h');

        $this->assertSame(['total' => 2, 'em_andamento' => 1, 'finalizados' => 1, 'horas' => 14.5], Curso::summarize($user->cursos()->get()));
    }

    #[DataProvider('invalidLearningFields')]
    public function test_learning_validation_rejects_invalid_values(array $fields, string $error): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/cursos', ['title' => 'Invalid', ...$fields])->assertUnprocessable()->assertJsonValidationErrors($error);

        $this->assertDatabaseMissing('cursos', ['title' => 'Invalid']);
    }

    public static function invalidLearningFields(): array
    {
        return [
            'too many completed' => [['total_lessons' => 10, 'completed_lessons' => 11], 'completed_lessons'],
            'missing total' => [['completed_lessons' => 1], 'completed_lessons'],
            'negative hours' => [['studied_hours' => -1], 'studied_hours'],
            'zero total' => [['total_lessons' => 0], 'total_lessons'],
            'invalid rating' => [['rating' => 6], 'rating'],
            'premature rating' => [['rating' => 5], 'rating'],
            'invalid review date' => [['next_review_at' => 'invalid'], 'next_review_at'],
            'invalid unit' => [['progress_unit' => 'unknown'], 'progress_unit'],
            'unsafe link' => [['link' => 'javascript:alert(1)'], 'link'],
        ];
    }
}
