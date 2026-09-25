<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\CursoFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CursoTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_the_users_courses_grouped_by_column(): void
    {
        $user = User::factory()->create();
        Curso::factory()->for($user)->status('not_started')->create(['title' => 'Laravel do zero']);
        Curso::factory()->for($user)->status('in_progress')->create(['title' => 'React avançado']);

        $response = $this->actingAs($user)->get('/cursos');

        $response->assertOk();
        $response->assertSee('Laravel do zero');
        $response->assertSee('React avançado');
        $response->assertSee('Não iniciado');
        $response->assertSee('Em andamento');
        $response->assertSee('Finalizado');
        $response->assertSee('Em revisão');
        $response->assertSee('Cancelado');
    }

    public function test_new_courses_default_to_the_not_started_column(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/cursos', [
            'title' => 'Laravel do zero',
            'platform' => 'Udemy',
            'link' => 'https://example.com/curso',
            'objective' => 'Aprender o framework para os projetos pessoais.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cursos', [
            'title' => 'Laravel do zero',
            'platform' => 'Udemy',
            'link' => 'https://example.com/curso',
            'status' => 'not_started',
            'user_id' => $user->id,
        ]);
    }

    public function test_creating_a_curso_requires_a_title(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/cursos', ['title' => '']);

        $response->assertSessionHasErrors('title');
    }

    public function test_creating_a_curso_validates_the_link_is_a_url(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/cursos', [
            'title' => 'Laravel do zero',
            'link' => 'not-a-url',
        ]);

        $response->assertSessionHasErrors('link');
    }

    public function test_user_can_create_a_curso_with_start_and_due_dates(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/cursos', [
            'title' => 'Laravel do zero',
            'starts_at' => '2026-09-20',
            'due_at' => '2026-12-20',
        ]);

        $response->assertRedirect();

        $curso = Curso::firstWhere('title', 'Laravel do zero');
        $this->assertNotNull($curso);
        $this->assertSame('2026-09-20', $curso->starts_at->format('Y-m-d'));
        $this->assertSame('2026-12-20', $curso->due_at->format('Y-m-d'));
    }

    public function test_due_at_cannot_be_before_starts_at_for_a_curso(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/cursos', [
            'title' => 'Laravel do zero',
            'starts_at' => '2026-06-01',
            'due_at' => '2026-01-01',
        ]);

        $response->assertSessionHasErrors('due_at');
    }

    public function test_a_curso_with_dates_shows_them_in_the_card(): void
    {
        $this->actingAs(User::factory()->create());

        $curso = Curso::factory()->create([
            'starts_at' => '2026-09-20',
            'due_at' => '2026-12-20',
        ]);

        $html = view('partials.curso-card', ['curso' => $curso])->render();

        $this->assertStringContainsString('20-09-2026', $html);
        $this->assertStringContainsString('20-12-2026', $html);
    }

    public function test_a_curso_without_dates_hides_the_dates_row(): void
    {
        $this->actingAs(User::factory()->create());

        $curso = Curso::factory()->create(['starts_at' => null, 'due_at' => null]);

        $html = view('partials.curso-card', ['curso' => $curso])->render();

        $this->assertStringNotContainsString('Início', $html);
        $this->assertStringNotContainsString('Previsão', $html);
    }

    public function test_user_can_move_a_curso_between_columns(): void
    {
        $user = User::factory()->create();
        $curso = Curso::factory()->for($user)->status('not_started')->create();

        $response = $this->actingAs($user)
            ->patchJson("/cursos/{$curso->id}", ['status' => 'in_review']);

        $response->assertOk();
        $this->assertDatabaseHas('cursos', ['id' => $curso->id, 'status' => 'in_review']);
        $this->assertStringContainsString('Em revisão', $response->json('card'));
    }

    public function test_moving_a_curso_rejects_an_invalid_status(): void
    {
        $user = User::factory()->create();
        $curso = Curso::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->patchJson("/cursos/{$curso->id}", ['status' => 'not-a-real-status']);

        $response->assertJsonValidationErrors('status');
    }

    public function test_a_user_cannot_see_another_users_courses(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        Curso::factory()->for($owner)->create(['title' => 'Curso privado']);

        $response = $this->actingAs($intruder)->get('/cursos');

        $response->assertOk();
        $response->assertDontSee('Curso privado');
    }

    public function test_a_user_cannot_move_another_users_curso(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $curso = Curso::factory()->for($owner)->status('not_started')->create();

        $response = $this->actingAs($intruder)
            ->patchJson("/cursos/{$curso->id}", ['status' => 'completed']);

        $response->assertNotFound();
        $this->assertDatabaseHas('cursos', ['id' => $curso->id, 'status' => 'not_started']);
    }

    public function test_user_can_edit_a_curso(): void
    {
        $user = User::factory()->create();
        $curso = Curso::factory()->for($user)->create(['title' => 'Título antigo']);

        $response = $this->actingAs($user)->putJson("/cursos/{$curso->id}", [
            'title' => 'Título novo',
            'platform' => 'Alura',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('cursos', ['id' => $curso->id, 'title' => 'Título novo', 'platform' => 'Alura']);
        $this->assertStringContainsString('Título novo', $response->json('card'));
    }

    public function test_editing_a_curso_requires_a_title(): void
    {
        $user = User::factory()->create();
        $curso = Curso::factory()->for($user)->create();

        $response = $this->actingAs($user)->put("/cursos/{$curso->id}", ['title' => '']);

        $response->assertSessionHasErrors('title');
    }

    public function test_a_user_cannot_edit_another_users_curso(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $curso = Curso::factory()->for($owner)->create(['title' => 'Título original']);

        $response = $this->actingAs($intruder)->putJson("/cursos/{$curso->id}", ['title' => 'Hackeado']);

        $response->assertNotFound();
        $this->assertDatabaseHas('cursos', ['id' => $curso->id, 'title' => 'Título original']);
    }

    public function test_user_can_delete_their_curso(): void
    {
        $user = User::factory()->create();
        $curso = Curso::factory()->for($user)->create();

        $response = $this->actingAs($user)->deleteJson("/cursos/{$curso->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('cursos', ['id' => $curso->id]);
    }

    public function test_a_user_cannot_delete_another_users_curso(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $curso = Curso::factory()->for($owner)->create();

        $response = $this->actingAs($intruder)->deleteJson("/cursos/{$curso->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('cursos', ['id' => $curso->id]);
    }

    public function test_user_can_attach_a_file_to_their_curso(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $curso = Curso::factory()->for($user)->create();
        $file = UploadedFile::fake()->create('diploma.pdf', 500, 'application/pdf');

        $response = $this->actingAs($user)
            ->postJson("/cursos/{$curso->id}/arquivos", ['file' => $file]);

        $response->assertOk();
        $this->assertDatabaseHas('curso_files', ['curso_id' => $curso->id, 'original_name' => 'diploma.pdf']);
        $this->assertStringContainsString('diploma.pdf', $response->json('card'));

        $stored = CursoFile::firstWhere('curso_id', $curso->id);
        Storage::disk('public')->assertExists($stored->path);
    }

    public function test_attaching_a_file_rejects_unsupported_types(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $curso = Curso::factory()->for($user)->create();
        $file = UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream');

        $response = $this->actingAs($user)
            ->postJson("/cursos/{$curso->id}/arquivos", ['file' => $file]);

        $response->assertJsonValidationErrors('file');
    }

    public function test_user_can_remove_an_attached_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $curso = Curso::factory()->for($user)->create();
        $file = CursoFile::factory()->for($curso)->create(['path' => 'curso-files/existing.pdf']);
        Storage::disk('public')->put($file->path, 'conteúdo falso');

        $response = $this->actingAs($user)
            ->deleteJson("/cursos/{$curso->id}/arquivos/{$file->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('curso_files', ['id' => $file->id]);
        Storage::disk('public')->assertMissing($file->path);
    }

    public function test_a_user_cannot_attach_a_file_to_another_users_curso(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $curso = Curso::factory()->for($owner)->create();
        $file = UploadedFile::fake()->create('diploma.pdf', 500, 'application/pdf');

        $response = $this->actingAs($intruder)
            ->postJson("/cursos/{$curso->id}/arquivos", ['file' => $file]);

        $response->assertNotFound();
        $this->assertDatabaseMissing('curso_files', ['curso_id' => $curso->id]);
    }

    public function test_a_user_cannot_remove_another_users_attached_file(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $curso = Curso::factory()->for($owner)->create();
        $file = CursoFile::factory()->for($curso)->create();

        $response = $this->actingAs($intruder)
            ->deleteJson("/cursos/{$curso->id}/arquivos/{$file->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('curso_files', ['id' => $file->id]);
    }
}
