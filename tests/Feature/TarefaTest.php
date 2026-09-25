<?php

namespace Tests\Feature;

use App\Models\Tarefa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TarefaTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_todays_tasks_by_default(): void
    {
        $user = User::factory()->create();
        Tarefa::factory()->for($user)->onDate(today()->toDateString())->create(['title' => 'Tarefa de hoje']);
        Tarefa::factory()->for($user)->onDate(today()->addDay()->toDateString())->create(['title' => 'Tarefa de amanhã']);

        $response = $this->actingAs($user)->get('/tarefas');

        $response->assertOk();
        $response->assertSee('Tarefa de hoje');
        $response->assertDontSee('Tarefa de amanhã');
    }

    public function test_index_shows_tasks_for_the_requested_date(): void
    {
        $user = User::factory()->create();
        Tarefa::factory()->for($user)->onDate('2026-09-20')->create(['title' => 'Tarefa específica']);

        $response = $this->actingAs($user)->get('/tarefas?date=2026-09-20');

        $response->assertOk();
        $response->assertSee('Tarefa específica');
        $response->assertSee('Domingo, 20 de setembro');
    }

    public function test_the_jump_to_today_link_is_hidden_when_today_is_already_selected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/tarefas');

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/id="jump-to-today"[^>]*class="[^"]*\bhidden\b/',
            $response->getContent(),
        );
    }

    public function test_the_jump_to_today_link_is_visible_when_viewing_another_day(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/tarefas?date='.today()->subDays(3)->toDateString());

        $response->assertOk();
        $this->assertDoesNotMatchRegularExpression(
            '/id="jump-to-today"[^>]*class="[^"]*\bhidden\b/',
            $response->getContent(),
        );
    }

    public function test_todays_calendar_cell_gets_a_distinct_highlight(): void
    {
        $user = User::factory()->create();

        // Select a different day in the same month, so today isn't the
        // selected cell — its own highlight should still show through.
        $otherDay = today()->day === 1 ? today()->addDay() : today()->subDay();

        $response = $this->actingAs($user)->get('/tarefas?date='.$otherDay->toDateString());

        $response->assertOk();
        $html = $response->getContent();
        $pos = strpos($html, 'data-calendar-day="'.today()->toDateString().'"');
        $this->assertNotFalse($pos, "Today's calendar cell was not found in the response.");
        $this->assertStringContainsString('ring-sky-400/60', substr($html, $pos, 800));
    }

    public function test_index_shows_the_bar_chart_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/tarefas');

        $response->assertOk();
        $response->assertSee('Análises');
        $response->assertSee('Dia');
        $response->assertSee('Semana');
        $response->assertSee('Mês');
        $response->assertSee('Concluídas');
        $response->assertSee('Pendentes');
        $response->assertSee('data-chart-panel="daily"', false);
        $response->assertSee('data-chart-panel="weekly"', false);
        $response->assertSee('data-chart-panel="monthly"', false);
    }

    public function test_daily_chart_reflects_completed_and_pending_counts(): void
    {
        $user = User::factory()->create();
        Tarefa::factory()->for($user)->onDate(today()->toDateString())->completed()->create();
        Tarefa::factory()->for($user)->onDate(today()->toDateString())->completed()->create();
        Tarefa::factory()->for($user)->onDate(today()->toDateString())->create();

        $response = $this->actingAs($user)->get('/tarefas');

        $response->assertOk();
        $response->assertSee('2 de 3 concluídas', false);
    }

    public function test_the_daily_panel_shows_visible_completed_and_pending_totals(): void
    {
        $user = User::factory()->create();
        Tarefa::factory()->for($user)->onDate(today()->toDateString())->completed()->create();
        Tarefa::factory()->for($user)->onDate(today()->subDay()->toDateString())->completed()->create();
        Tarefa::factory()->for($user)->onDate(today()->subDay()->toDateString())->create();
        Tarefa::factory()->for($user)->onDate(today()->subDays(2)->toDateString())->create();

        $response = $this->actingAs($user)->get('/tarefas');

        $response->assertOk();
        // Two completed and two pending across the daily window (the panel
        // rendered first in the DOM — weekly/monthly come after it).
        $panel = $this->extractChartPanel($response->getContent(), 'daily');
        $this->assertStringContainsString('Concluídas', $panel);
        $this->assertStringContainsString('Pendentes', $panel);
        $this->assertMatchesRegularExpression('/Conclu.das\s*<span[^>]*>\s*2\s*</u', $panel);
        $this->assertMatchesRegularExpression('/Pendentes\s*<span[^>]*>\s*2\s*</u', $panel);
    }

    /**
     * Extracts just one chart panel's HTML from the full charts markup,
     * where each panel is a sibling `data-chart-panel="..."` block.
     */
    private function extractChartPanel(string $html, string $panel): string
    {
        $start = strpos($html, 'data-chart-panel="'.$panel.'"');
        $this->assertNotFalse($start, "Panel [{$panel}] not found in response.");

        $nextStart = strpos($html, 'data-chart-panel="', $start + 1);

        return $nextStart !== false
            ? substr($html, $start, $nextStart - $start)
            : substr($html, $start);
    }

    public function test_a_day_outside_every_chart_window_is_not_counted(): void
    {
        $user = User::factory()->create();
        // Beyond the daily (14d), weekly (8wk) and monthly (6mo) windows.
        Tarefa::factory()->for($user)->onDate(today()->subYear()->toDateString())->completed()->create();

        $response = $this->actingAs($user)->get('/tarefas');

        $response->assertOk();
        $response->assertDontSee('1 de 1 concluídas', false);
    }

    public function test_fetch_responses_include_the_updated_charts(): void
    {
        $user = User::factory()->create();
        $tarefa = Tarefa::factory()->for($user)->onDate(today()->toDateString())->create();

        $response = $this->actingAs($user)
            ->patchJson("/tarefas/{$tarefa->id}", ['completed' => true]);

        $response->assertOk();
        $response->assertJsonStructure(['charts']);
        $this->assertStringContainsString('1 de 1 concluídas', $response->json('charts'));
    }

    public function test_a_user_only_sees_their_own_tasks_in_the_charts(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        Tarefa::factory()->for($owner)->onDate(today()->toDateString())->completed()->create();

        $response = $this->actingAs($intruder)->get('/tarefas');

        $response->assertOk();
        $response->assertDontSee('1 de 1 concluídas', false);
    }

    public function test_stats_reflect_completion_ratio(): void
    {
        $user = User::factory()->create();
        Tarefa::factory()->for($user)->onDate(today()->toDateString())->completed()->create();
        Tarefa::factory()->for($user)->onDate(today()->toDateString())->completed()->create();
        Tarefa::factory()->for($user)->onDate(today()->toDateString())->create();
        Tarefa::factory()->for($user)->onDate(today()->toDateString())->create();
        Tarefa::factory()->for($user)->onDate(today()->toDateString())->create();

        $response = $this->actingAs($user)->get('/tarefas');

        $response->assertOk();
        $response->assertSee('2/5');
    }

    public function test_user_can_create_a_task_for_a_given_day(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/tarefas', [
            'title' => 'Estudar Laravel',
            'date' => '2026-09-25',
        ]);

        $response->assertRedirect();

        $tarefa = Tarefa::firstWhere('title', 'Estudar Laravel');
        $this->assertNotNull($tarefa);
        $this->assertSame('2026-09-25', $tarefa->date->format('Y-m-d'));
        $this->assertSame($user->id, $tarefa->user_id);
        $this->assertFalse($tarefa->completed);
    }

    public function test_creating_a_task_requires_a_title_and_date(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/tarefas', ['title' => '', 'date' => '']);

        $response->assertSessionHasErrors(['title', 'date']);
    }

    public function test_creating_a_task_via_fetch_returns_day_and_stats_and_summary(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/tarefas', [
            'title' => 'Estudar Laravel',
            'date' => '2026-09-25',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['day', 'stats', 'date', 'summary' => ['completed', 'total']]);
        $this->assertSame('2026-09-25', $response->json('date'));
        $this->assertSame(1, $response->json('summary.total'));
        $this->assertSame(0, $response->json('summary.completed'));
    }

    public function test_user_can_toggle_a_task(): void
    {
        $user = User::factory()->create();
        $tarefa = Tarefa::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->patchJson("/tarefas/{$tarefa->id}", ['completed' => true]);

        $response->assertOk();
        $this->assertDatabaseHas('tarefas', ['id' => $tarefa->id, 'completed' => true]);
        $this->assertSame(1, $response->json('summary.completed'));
    }

    public function test_user_can_delete_a_task(): void
    {
        $user = User::factory()->create();
        $tarefa = Tarefa::factory()->for($user)->create();

        $response = $this->actingAs($user)->deleteJson("/tarefas/{$tarefa->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('tarefas', ['id' => $tarefa->id]);
    }

    public function test_a_user_cannot_see_another_users_tasks(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        Tarefa::factory()->for($owner)->onDate(today()->toDateString())->create(['title' => 'Tarefa privada']);

        $response = $this->actingAs($intruder)->get('/tarefas');

        $response->assertOk();
        $response->assertDontSee('Tarefa privada');
    }

    public function test_a_user_cannot_toggle_another_users_task(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $tarefa = Tarefa::factory()->for($owner)->create(['completed' => false]);

        $response = $this->actingAs($intruder)
            ->patchJson("/tarefas/{$tarefa->id}", ['completed' => true]);

        $response->assertNotFound();
        $this->assertDatabaseHas('tarefas', ['id' => $tarefa->id, 'completed' => false]);
    }

    public function test_a_user_cannot_delete_another_users_task(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $tarefa = Tarefa::factory()->for($owner)->create();

        $response = $this->actingAs($intruder)->deleteJson("/tarefas/{$tarefa->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('tarefas', ['id' => $tarefa->id]);
    }
}
