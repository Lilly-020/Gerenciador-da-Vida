<?php

namespace Tests\Feature;

use App\Models\Tarefa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TarefaDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_view_shows_tasks_in_their_days_and_keeps_one_editable_day_panel(): void
    {
        $user = User::factory()->create();
        Tarefa::factory()->for($user)->onDate('2026-11-10')->create(['title' => 'Estudar Laravel']);
        Tarefa::factory()->for($user)->onDate('2026-11-12')->completed()->create(['title' => 'Ler um capítulo']);

        $response = $this->actingAs($user)->get('/tarefas?date=2026-11-10&view=month');

        $response->assertSee('Calendário amplo')->assertSee('Estudar Laravel')->assertSee('Ler um capítulo')
            ->assertSee('data-calendar-open="2026-11-12"', false);
        $this->assertSame(1, substr_count($response->getContent(), 'id="tarefas-day"'));
    }

    public function test_month_view_includes_adjacent_days_and_hides_other_users_tasks(): void
    {
        $user = User::factory()->create();
        Tarefa::factory()->for($user)->onDate('2026-10-31')->create(['title' => 'Tarefa no limite do mês']);
        Tarefa::factory()->onDate('2026-11-10')->create(['title' => 'Tarefa privada']);

        $this->actingAs($user)->get('/tarefas?date=2026-11-10&view=month')
            ->assertSee('Tarefa no limite do mês')->assertDontSee('Tarefa privada');
    }

    public function test_dashboard_separates_today_overdue_and_upcoming_pending_tasks(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 10));
        $user = User::factory()->create();
        Tarefa::factory()->for($user)->onDate('2026-11-09')->create(['title' => 'Atrasada']);
        Tarefa::factory()->for($user)->onDate('2026-11-08')->completed()->create(['title' => 'Passado concluído']);
        Tarefa::factory()->for($user)->onDate('2026-11-10')->create(['title' => 'Hoje pendente']);
        Tarefa::factory()->for($user)->onDate('2026-11-11')->create(['title' => 'Planejada']);
        Tarefa::factory()->for($user)->onDate('2026-11-18')->create(['title' => 'Fora da semana']);

        $this->actingAs($user)->get('/tarefas?view=dashboard')->assertSee('Atrasada')->assertSee('Hoje pendente')->assertSee('Planejada')
            ->assertDontSee('Passado concluído')->assertDontSee('Fora da semana')
            ->assertViewHas('overview', fn (array $overview): bool => $overview['overdueCount'] === 1 && $overview['upcomingCount'] === 1);
    }

    public function test_task_mutations_refresh_large_calendar_and_dashboard(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 10));
        $user = User::factory()->create();
        $task = Tarefa::factory()->for($user)->onDate('2026-11-10')->create(['title' => 'Concluir atividade']);

        $response = $this->actingAs($user)->patchJson(route('tarefas.update', $task), ['completed' => true]);

        $response->assertOk()->assertJsonStructure(['day', 'stats', 'charts', 'calendar', 'overview', 'summary']);
        $this->assertStringContainsString('aria-label="Marcar como pendente: Concluir atividade"', $response->json('calendar'));
        $this->assertStringContainsString('checked', $response->json('calendar'));
        $this->assertStringContainsString('100%', $response->json('overview'));
        $this->assertDatabaseHas('tarefas', ['id' => $task->id, 'completed' => true]);
    }

    public function test_empty_dashboard_has_clear_empty_states_and_invalid_view_falls_back(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/tarefas?view=dashboard')->assertSee('Nenhuma tarefa para hoje.')->assertSee('Nenhuma pendência atrasada.');
        $this->get('/tarefas?view=invalid')->assertViewHas('view', 'calendar');
    }
}
