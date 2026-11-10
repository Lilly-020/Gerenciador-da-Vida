<?php

namespace Tests\Feature;

use App\Models\Tarefa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TarefaDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_tasks_open_a_private_details_dialog(): void
    {
        $user = User::factory()->create();
        $task = Tarefa::factory()->for($user)->create(['title' => 'Estudar', 'date' => '2026-11-10', 'notes' => '<script>unsafe</script>']);
        $this->actingAs($user)->get('/tarefas?date=2026-11-10&view=month')->assertOk()
            ->assertSee('data-task-details-url', false)->assertSee('task-details-modal', false);
        $response = $this->getJson(route('tarefas.show', $task))->assertOk();
        $html = $response->json('html');
        $summary = $response->json('summary');
        $this->assertStringContainsString('Estudar', $summary);
        $this->assertStringContainsString('10/11/2026', $summary);
        $this->assertStringContainsString('data-task-info-edit', $summary);
        $this->assertStringContainsString('data-task-info-delete', $summary);
        $this->assertStringContainsString('data-task-info-toggle', $summary);
        $this->assertStringContainsString('&lt;script&gt;unsafe&lt;/script&gt;', $summary);
        $this->assertStringNotContainsString('<form', $summary);
        $this->assertStringNotContainsString('<textarea', $summary);
        $this->assertStringNotContainsString('name="title"', $summary);
        $this->assertStringContainsString('Data de início', $html);
        $this->assertStringContainsString('Data de fim', $html);
        $this->assertStringContainsString('data-task-detail-delete', $html);
        $this->assertStringContainsString('Excluir tarefa', $html);
        $this->assertStringContainsString('&lt;script&gt;unsafe&lt;/script&gt;', $html);
    }

    public function test_details_can_be_saved_and_multi_day_tasks_appear_on_each_day(): void
    {
        $user = User::factory()->create();
        $task = Tarefa::factory()->for($user)->create(['date' => '2026-11-10']);
        $this->actingAs($user)->patchJson(route('tarefas.update', $task), [
            'title' => 'Viagem de estudo', 'date' => '2026-11-11', 'end_date' => '2026-11-13',
            'start_time' => '18:00', 'end_time' => '08:00', 'notes' => 'Levar material',
        ])->assertOk();
        $this->assertDatabaseHas('tarefas', ['id' => $task->id, 'title' => 'Viagem de estudo', 'notes' => 'Levar material']);
        $this->assertSame('2026-11-13', $task->fresh()->end_date->toDateString());
        foreach (['2026-11-11', '2026-11-12', '2026-11-13'] as $date) {
            $this->getJson('/tarefas?date='.$date)->assertOk()->assertJsonPath('summary.total', 1);
        }
        $this->getJson('/tarefas?date=2026-11-14')->assertJsonPath('summary.total', 0);
    }

    public function test_end_date_cannot_be_before_start_even_in_partial_updates(): void
    {
        $user = User::factory()->create();
        $task = Tarefa::factory()->for($user)->create(['date' => '2026-11-10', 'end_date' => '2026-11-12']);
        $this->actingAs($user)->patchJson(route('tarefas.update', $task), ['date' => '2026-11-13'])
            ->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->patchJson(route('tarefas.update', $task), ['end_date' => '2026-11-09'])
            ->assertUnprocessable()->assertJsonValidationErrors('end_date');
    }

    public function test_files_can_be_attached_downloaded_and_removed_privately(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $task = Tarefa::factory()->for($user)->create();
        $this->actingAs($user)->postJson(route('tarefas.arquivos.store', $task), [
            'file' => UploadedFile::fake()->create('material.pdf', 20, 'application/pdf'),
        ])->assertOk();
        $file = $task->files()->firstOrFail();
        Storage::disk('local')->assertExists($file->path);
        $this->get(route('tarefas.arquivos.show', [$task, $file]))->assertDownload('material.pdf');
        $this->actingAs(User::factory()->create())->getJson(route('tarefas.show', $task))->assertNotFound();
        $this->get(route('tarefas.arquivos.show', [$task, $file]))->assertNotFound();
        $this->deleteJson(route('tarefas.arquivos.destroy', [$task, $file]))->assertNotFound();
        $this->actingAs($user)->deleteJson(route('tarefas.arquivos.destroy', [$task, $file]))->assertOk();
        Storage::disk('local')->assertMissing($file->path);
        $this->assertModelMissing($file);
    }

    public function test_file_must_belong_to_task_and_uploads_are_validated(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $task = Tarefa::factory()->for($user)->create();
        $other = Tarefa::factory()->for($user)->create();
        $this->actingAs($user)->postJson(route('tarefas.arquivos.store', $other), [
            'file' => UploadedFile::fake()->image('foto.png'),
        ])->assertOk();
        $file = $other->files()->firstOrFail();
        $this->get(route('tarefas.arquivos.show', [$task, $file]))->assertNotFound();
        $this->deleteJson(route('tarefas.arquivos.destroy', [$task, $file]))->assertNotFound();
        $this->get(route('tarefas.arquivos.show', [$other, $file, 'preview' => 1]))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->postJson(route('tarefas.arquivos.store', $task), ['file' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson(route('tarefas.arquivos.store', $task), ['file' => UploadedFile::fake()->create('script.html', 1, 'text/html')])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_deleting_task_also_removes_its_attachments(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $task = Tarefa::factory()->for($user)->create();
        $this->actingAs($user)->postJson(route('tarefas.arquivos.store', $task), [
            'file' => UploadedFile::fake()->create('material.pdf', 20, 'application/pdf'),
        ])->assertOk();
        $file = $task->files()->firstOrFail();
        $this->deleteJson(route('tarefas.destroy', $task))->assertOk();
        Storage::disk('local')->assertMissing($file->path);
        $this->assertModelMissing($file);
    }

    public function test_week_and_month_cards_have_completion_controls_separate_from_details(): void
    {
        $user = User::factory()->create();
        Tarefa::factory()->for($user)->create(['title' => 'Sem horário', 'date' => '2026-11-10']);
        Tarefa::factory()->for($user)->create(['title' => 'Com horário', 'date' => '2026-11-10', 'start_time' => '09:00', 'end_time' => '10:00']);
        $this->actingAs($user);

        foreach (['week' => 'tarefas-week-calendar', 'month' => 'tarefas-month-calendar'] as $view => $calendarId) {
            $response = $this->get('/tarefas?date=2026-11-10&view='.$view)->assertOk();
            $document = new \DOMDocument;
            $previous = libxml_use_internal_errors(true);
            $document->loadHTML($response->getContent());
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xpath = new \DOMXPath($document);
            $cards = '//*[@id="'.$calendarId.'"]//*[@data-calendar-task]';
            $this->assertCount(2, $xpath->query($cards.'//input[@data-task-toggle-url]'));
            $this->assertCount(2, $xpath->query($cards.'//button[@data-task-details-url]'));
            $this->assertCount(0, $xpath->query($cards.'//button//input'));
        }
    }
}
