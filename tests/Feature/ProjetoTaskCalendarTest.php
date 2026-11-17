<?php

namespace Tests\Feature;

use App\Models\Participacao;
use App\Models\Projeto;
use App\Models\ProjetoTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjetoTaskCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_task_can_be_scheduled_in_the_users_calendar_with_a_required_realization_date(): void
    {
        $user = User::factory()->create();
        $project = Projeto::factory()->for($user)->create(['title' => 'Meu aplicativo']);
        $source = ProjetoTask::factory()->for($project)->create(['title' => 'Deixar responsivo', 'due_at' => '2027-02-01']);

        $response = $this->actingAs($user)->postJson(route('projetos.tarefas.agendar', [$project, $source]), ['date' => '2026-11-20', 'start_time' => '09:00', 'end_time' => '10:00']);

        $response->assertOk()->assertJsonStructure(['card', 'stats', 'calendar_url']);
        $calendarTask = $user->tarefas()->firstOrFail();
        $this->assertDatabaseHas('tarefas', ['id' => $calendarTask->id, 'user_id' => $user->id, 'projeto_task_id' => $source->id, 'title' => 'Deixar responsivo', 'date' => '2026-11-20 00:00:00', 'start_time' => '09:00', 'end_time' => '10:00']);
        $this->assertSame('2027-02-01', $source->fresh()->due_at->toDateString());
        $this->assertModelExists($source);
        $this->get('/tarefas?view=week&date=2026-11-20')->assertSee('Deixar responsivo');
        $summary = $this->getJson(route('tarefas.show', $calendarTask))->json('summary');
        $this->assertStringContainsString('Meu aplicativo', $summary);
        $this->assertStringContainsString('No meu calendário', $response->json('card'));
    }

    public function test_a_task_with_a_deadline_still_requires_an_explicit_realization_date(): void
    {
        $user = User::factory()->create();
        $project = Projeto::factory()->for($user)->create();
        $source = ProjetoTask::factory()->for($project)->create(['due_at' => '2027-02-01']);

        $this->actingAs($user)->postJson(route('projetos.tarefas.agendar', [$project, $source]), [])->assertUnprocessable()
            ->assertJsonValidationErrors('date')->assertJsonPath('errors.date.0', 'Informe a data em que esta tarefa será realizada.');

        $this->assertDatabaseCount('tarefas', 0);
    }

    public function test_rescheduling_updates_the_same_calendar_task_and_can_clear_times(): void
    {
        $user = User::factory()->create();
        $project = Projeto::factory()->for($user)->create();
        $source = ProjetoTask::factory()->for($project)->create();
        $url = route('projetos.tarefas.agendar', [$project, $source]);
        $this->actingAs($user)->postJson($url, ['date' => '2026-11-20', 'start_time' => '09:00', 'end_time' => '10:00'])->assertOk();
        $calendarTask = $user->tarefas()->firstOrFail();

        $this->postJson($url, ['date' => '2026-11-23', 'user_id' => 999, 'projeto_task_id' => 999])->assertOk();

        $this->assertDatabaseCount('tarefas', 1);
        $this->assertDatabaseHas('tarefas', ['id' => $calendarTask->id, 'user_id' => $user->id, 'projeto_task_id' => $source->id, 'date' => '2026-11-23 00:00:00', 'start_time' => null, 'end_time' => null]);
        $this->get('/tarefas?view=week&date=2026-11-23')->assertViewHas('weekDays', fn (array $days): bool => $days[0]['allDay']->pluck('id')->all() === [$calendarTask->id]);
    }

    public function test_scheduling_copies_the_projects_current_completion_state(): void
    {
        $user = User::factory()->create();
        $project = Projeto::factory()->for($user)->create();
        $source = ProjetoTask::factory()->for($project)->completed()->create();

        $this->actingAs($user)->postJson(route('projetos.tarefas.agendar', [$project, $source]), ['date' => '2026-11-20'])->assertOk();

        $this->assertDatabaseHas('tarefas', ['projeto_task_id' => $source->id, 'completed' => true]);
    }

    public function test_read_only_participants_cannot_schedule_project_tasks(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $source = ProjetoTask::factory()->for($project)->create();
        Participacao::create(['owner_id' => $owner->id, 'participant_id' => $viewer->id, 'projeto_id' => $project->id, 'tipo' => 'projeto', 'permission' => 'ver', 'status' => 'aceito']);

        $this->actingAs($viewer)->postJson(route('projetos.tarefas.agendar', [$project, $source]), ['date' => '2026-11-20'])->assertForbidden();

        $this->assertDatabaseCount('tarefas', 0);
        $this->get('/projetos')->assertDontSee('data-project-task-calendar', false);
    }

    public function test_shared_project_editors_have_independent_personal_calendar_entries(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $source = ProjetoTask::factory()->for($project)->create();
        Participacao::create(['owner_id' => $owner->id, 'participant_id' => $editor->id, 'projeto_id' => $project->id, 'tipo' => 'projeto', 'permission' => 'editar', 'status' => 'aceito']);
        $url = route('projetos.tarefas.agendar', [$project, $source]);

        $this->actingAs($owner)->postJson($url, ['date' => '2026-11-20'])->assertOk();
        $this->actingAs($editor)->postJson($url, ['date' => '2026-11-21'])->assertOk();
        $this->postJson($url, ['date' => '2026-11-22'])->assertOk();

        $this->assertDatabaseCount('tarefas', 2);
        $this->assertDatabaseHas('tarefas', ['user_id' => $owner->id, 'projeto_task_id' => $source->id, 'date' => '2026-11-20 00:00:00']);
        $this->assertDatabaseHas('tarefas', ['user_id' => $editor->id, 'projeto_task_id' => $source->id, 'date' => '2026-11-22 00:00:00']);
        $this->assertSame('2026-11-22', $source->fresh()->calendarTask->date->toDateString());
    }

    public function test_nested_tasks_from_another_project_or_private_projects_are_rejected(): void
    {
        $user = User::factory()->create();
        $project = Projeto::factory()->for($user)->create();
        $otherProject = Projeto::factory()->for($user)->create();
        $otherTask = ProjetoTask::factory()->for($otherProject)->create();
        $private = ProjetoTask::factory()->create();

        $this->actingAs($user)->postJson(route('projetos.tarefas.agendar', [$project, $otherTask]), ['date' => '2026-11-20'])->assertNotFound();
        $this->postJson(route('projetos.tarefas.agendar', [$private->projeto_id, $private]), ['date' => '2026-11-20'])->assertNotFound();

        $this->assertDatabaseCount('tarefas', 0);
    }

    public function test_deleting_the_calendar_task_keeps_the_project_item_and_allows_rescheduling(): void
    {
        $user = User::factory()->create();
        $project = Projeto::factory()->for($user)->create();
        $source = ProjetoTask::factory()->for($project)->create();
        $url = route('projetos.tarefas.agendar', [$project, $source]);
        $this->actingAs($user)->postJson($url, ['date' => '2026-11-20'])->assertOk();
        $calendarTask = $user->tarefas()->firstOrFail();

        $this->deleteJson(route('tarefas.destroy', $calendarTask))->assertOk();
        $this->postJson($url, ['date' => '2026-11-21'])->assertOk();

        $this->assertModelExists($source);
        $this->assertDatabaseCount('tarefas', 1);
        $this->assertDatabaseHas('tarefas', ['projeto_task_id' => $source->id, 'date' => '2026-11-21 00:00:00']);
    }

    public function test_deleting_the_project_task_preserves_the_calendar_entry(): void
    {
        $user = User::factory()->create();
        $project = Projeto::factory()->for($user)->create();
        $source = ProjetoTask::factory()->for($project)->create();
        $this->actingAs($user)->postJson(route('projetos.tarefas.agendar', [$project, $source]), ['date' => '2026-11-20'])->assertOk();
        $calendarTask = $user->tarefas()->firstOrFail();

        $this->deleteJson(route('projetos.tarefas.destroy', [$project, $source]))->assertOk();

        $this->assertModelExists($calendarTask);
        $this->assertNull($calendarTask->fresh()->projeto_task_id);
    }

    #[DataProvider('invalidSchedules')]
    public function test_invalid_dates_and_times_do_not_create_calendar_tasks(array $data, string $field): void
    {
        $user = User::factory()->create();
        $project = Projeto::factory()->for($user)->create();
        $source = ProjetoTask::factory()->for($project)->create();

        $this->actingAs($user)->postJson(route('projetos.tarefas.agendar', [$project, $source]), $data)->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('tarefas', 0);
    }

    public static function invalidSchedules(): array
    {
        return [
            'invalid date' => [['date' => 'invalid'], 'date'],
            'invalid start' => [['date' => '2026-11-20', 'start_time' => '25:00'], 'start_time'],
            'invalid end' => [['date' => '2026-11-20', 'end_time' => '25:00'], 'end_time'],
            'end without start' => [['date' => '2026-11-20', 'end_time' => '15:00'], 'start_time'],
            'end before start' => [['date' => '2026-11-20', 'start_time' => '15:00', 'end_time' => '14:00'], 'end_time'],
            'unvalidated end date cannot bypass time order' => [['date' => '2026-11-20', 'start_time' => '15:00', 'end_time' => '14:00', 'end_date' => '2026-11-21'], 'end_time'],
        ];
    }
}
