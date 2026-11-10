<?php

namespace Tests\Feature;

use App\Models\Tarefa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TarefaWeekTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_day_tasks_do_not_shift_the_week_or_hide_todays_tasks(): void
    {
        $user = User::factory()->create();
        Tarefa::factory()->for($user)->onDate('2026-11-09')->create(['title' => 'Tarefa de segunda']);
        $task = Tarefa::factory()->for($user)->onDate('2026-11-10')->create(['title' => 'Ler a Bíblia']);

        $response = $this->actingAs($user)->get('/tarefas?view=week&date=2026-11-10');

        $response->assertSee('Ler a Bíblia');
        $days = $response->viewData('weekDays');
        $this->assertSame('2026-11-09', $days[0]['date']->toDateString());
        $this->assertSame('2026-11-15', $days[6]['date']->toDateString());
        $this->assertSame([$task->id], $days[1]['allDay']->pluck('id')->all());
    }

    public function test_week_view_has_seven_days_hourly_agenda_and_all_day_tasks(): void
    {
        $user = User::factory()->create();
        Tarefa::factory()->for($user)->onDate('2026-11-10')->create(['title' => 'Reunião da equipe', 'kind' => 'reuniao', 'start_time' => '09:00', 'end_time' => '10:00']);
        Tarefa::factory()->for($user)->onDate('2026-11-15')->create(['title' => 'Compromisso do domingo']);
        Tarefa::factory()->for($user)->onDate('2026-11-16')->create(['title' => 'Fora desta semana']);

        $response = $this->actingAs($user)->get('/tarefas?view=week&date=2026-11-10');

        $response->assertSee('Reunião da equipe')->assertSee('Compromisso do domingo')->assertSee('Dia todo')
            ->assertSee('07:00')->assertSee('23:00')->assertSee('Semana anterior')->assertSee('Próxima semana')->assertDontSee('Fora desta semana');
        $response->assertViewHas('weekDays', fn (array $days): bool => count($days) === 7 && $days[0]['date']->toDateString() === '2026-11-09' && $days[6]['date']->toDateString() === '2026-11-15');
        $this->assertSame(0, substr_count($response->getContent(), 'id="tarefas-day"'));
    }

    public function test_overlapping_appointments_are_assigned_separate_lanes(): void
    {
        $user = User::factory()->create();
        Tarefa::factory()->for($user)->onDate('2026-11-10')->create(['start_time' => '09:00', 'end_time' => '10:00']);
        Tarefa::factory()->for($user)->onDate('2026-11-10')->create(['start_time' => '09:30', 'end_time' => '10:30']);

        $this->actingAs($user)->get('/tarefas?view=week&date=2026-11-10')->assertViewHas('weekDays', function (array $days): bool {
            $events = $days[1]['events'];

            return $events[0]['lane'] === 0 && $events[1]['lane'] === 1 && $events[0]['lanes'] === 2 && $events[1]['lanes'] === 2;
        });
    }

    public function test_creating_a_scheduled_task_updates_the_weekly_agenda(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/tarefas', ['title' => 'Consulta', 'date' => '2026-11-10', 'kind' => 'encontro', 'start_time' => '14:00', 'end_time' => '14:30']);

        $response->assertOk()->assertJsonStructure(['week', 'day', 'overview']);
        $this->assertDatabaseHas('tarefas', ['user_id' => $user->id, 'title' => 'Consulta', 'kind' => 'encontro', 'start_time' => '14:00', 'end_time' => '14:30']);
        $this->assertStringContainsString('14:00 — 14:30', $response->json('week'));
    }

    public function test_task_repetitions_preserve_the_kind_and_times(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/tarefas', ['title' => 'Estudar', 'date' => '2026-11-09', 'weekdays' => [1, 2], 'repeat_until' => '2026-11-10', 'kind' => 'atividade', 'start_time' => '08:00', 'end_time' => '09:00'])->assertOk();

        $this->assertDatabaseCount('tarefas', 2);
        $this->assertSame(['08:00', '08:00'], Tarefa::pluck('start_time')->all());
        $this->assertSame(['09:00', '09:00'], Tarefa::pluck('end_time')->all());
    }

    public function test_end_time_must_be_later_and_have_a_start(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/tarefas', ['title' => 'Invalid', 'date' => '2026-11-10', 'start_time' => '10:00', 'end_time' => '09:00'])->assertUnprocessable()->assertJsonValidationErrors('end_time');
        $this->postJson('/tarefas', ['title' => 'Invalid', 'date' => '2026-11-10', 'end_time' => '09:00'])->assertUnprocessable()->assertJsonValidationErrors('start_time');
        $this->assertDatabaseCount('tarefas', 0);
    }

    public function test_schedule_can_be_changed_or_cleared_without_changing_completion(): void
    {
        $user = User::factory()->create();
        $task = Tarefa::factory()->for($user)->completed()->onDate('2026-11-10')->create(['start_time' => '09:00', 'end_time' => '10:00']);

        $this->actingAs($user)->patchJson(route('tarefas.update', $task), ['start_time' => '10:30'])->assertUnprocessable()->assertJsonValidationErrors('end_time');
        $this->patchJson(route('tarefas.update', $task), ['start_time' => null, 'end_time' => null, 'kind' => 'evento'])->assertOk();

        $this->assertDatabaseHas('tarefas', ['id' => $task->id, 'start_time' => null, 'end_time' => null, 'kind' => 'evento', 'completed' => true]);
    }

    public function test_week_does_not_expose_another_users_appointments(): void
    {
        $user = User::factory()->create();
        $private = Tarefa::factory()->onDate('2026-11-10')->create(['title' => 'Agenda privada', 'start_time' => '12:00']);

        $this->actingAs($user)->get('/tarefas?view=week&date=2026-11-10')->assertDontSee('Agenda privada');
        $this->patchJson(route('tarefas.update', $private), ['start_time' => '13:00'])->assertNotFound();
    }
}
