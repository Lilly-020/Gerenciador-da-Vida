<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Lancamento;
use App\Models\Participacao;
use App\Models\Projeto;
use App\Models\Sonho;
use App\Models\Tarefa;
use App\Models\User;
use App\Support\Carteira;
use App\Support\DashboardLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_and_preferences_require_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->putJson('/dashboard/preferences', DashboardLayout::defaults())->assertUnauthorized();
    }

    public function test_dashboard_renders_all_five_widgets_with_empty_states(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/dashboard');

        $response->assertOk()->assertSee('Sua vida, do seu jeito.')->assertSee('Personalizar')
            ->assertSee('Qual é o seu próximo sonho?')->assertSee('O que você quer aprender?');
        foreach (array_keys(DashboardLayout::WIDGETS) as $id) {
            $response->assertSee('data-dashboard-widget="'.$id.'"', false);
        }
    }

    public function test_dashboard_summarizes_only_the_viewers_data_and_keeps_task_actions(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(10, 0));
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sonho::factory()->for($user)->create(['title' => 'Conhecer o Japão']);
        Projeto::factory()->for($user)->create(['title' => 'Meu projeto']);
        Curso::factory()->for($user)->create(['title' => 'Aprender Laravel']);
        Tarefa::factory()->for($user)->onDate('2026-11-11')->create(['title' => 'Ler a Bíblia']);
        Tarefa::factory()->for($user)->completed()->onDate('2026-11-10')->create(['end_date' => '2026-11-12']);
        Sonho::factory()->for($other)->create(['title' => 'Sonho privado']);
        Projeto::factory()->for($other)->create(['title' => 'Projeto privado']);
        Curso::factory()->for($other)->create(['title' => 'Curso privado']);
        Tarefa::factory()->for($other)->onDate('2026-11-11')->create(['title' => 'Tarefa privada']);
        Lancamento::factory()->for($user)->entrada()->create(['amount' => 3000]);
        Lancamento::factory()->for($user)->saida()->create(['amount' => 600]);
        Lancamento::factory()->for($other)->entrada()->create(['amount' => 99999]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSee('Conhecer o Japão')->assertSee('Meu projeto')->assertSee('Aprender Laravel')->assertSee('Ler a Bíblia')
            ->assertDontSee('Sonho privado')->assertDontSee('Projeto privado')->assertDontSee('Curso privado')->assertDontSee('Tarefa privada')
            ->assertSee('R$ 3.000,00')->assertSee('R$ 600,00')->assertDontSee('R$ 99.999,00')
            ->assertSee('data-dashboard-new-task', false)->assertSee('id="task-details-modal"', false)
            ->assertViewHas('todayTotal', 2)->assertViewHas('todayCompleted', 1);
        $this->assertSame(1, substr_count($response->getContent(), 'id="tarefas-page"'));
    }

    public function test_preferences_persist_order_visibility_width_and_calendar_only_for_the_viewer(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $preferences = DashboardLayout::defaults();
        $preferences['widgets'] = array_reverse($preferences['widgets']);
        $preferences['widgets'][0]['visible'] = false;
        $preferences['widgets'][1]['width'] = 'wide';
        $preferences['task_view'] = 'week';

        $response = $this->actingAs($user)->putJson('/dashboard/preferences', [...$preferences, 'user_id' => $other->id, 'is_admin' => true]);

        $response->assertOk()->assertJsonPath('preferences', $preferences);
        $this->assertSame($preferences, $user->fresh()->dashboard_preferences);
        $this->assertNull($other->fresh()->dashboard_preferences);
        $this->assertFalse($user->fresh()->is_admin);
        $this->get('/dashboard')->assertViewHas('preferences', $preferences)->assertViewHas('taskData', fn (array $data): bool => $data['view'] === 'week');
    }

    public function test_all_widgets_can_be_hidden_and_restored(): void
    {
        $preferences = DashboardLayout::defaults();
        $preferences['widgets'] = array_map(fn (array $widget): array => [...$widget, 'visible' => false], $preferences['widgets']);
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/dashboard/preferences', $preferences)->assertOk();

        $this->get('/dashboard')->assertSee('Adicionar blocos')->assertViewHas('preferences', $preferences);
        $this->putJson('/dashboard/preferences', DashboardLayout::defaults())->assertOk();
        $this->assertSame(DashboardLayout::defaults(), $user->fresh()->dashboard_preferences);
    }

    #[DataProvider('invalidPreferences')]
    public function test_invalid_preferences_return_422_without_overwriting_saved_settings(array $changes, string $error): void
    {
        $user = User::factory()->create();
        $user->dashboard_preferences = DashboardLayout::defaults();
        $user->save();
        $preferences = array_replace_recursive(DashboardLayout::defaults(), $changes);

        $this->actingAs($user)->putJson('/dashboard/preferences', $preferences)->assertUnprocessable()->assertJsonValidationErrors($error);

        $this->assertSame(DashboardLayout::defaults(), $user->fresh()->dashboard_preferences);
    }

    public static function invalidPreferences(): array
    {
        return [
            'unknown calendar' => [['task_view' => 'invalid'], 'task_view'],
            'unknown widget' => [['widgets' => [['id' => 'usuarios']]], 'widgets.0.id'],
            'duplicate widget' => [['widgets' => [1 => ['id' => 'tarefas']]], 'widgets.1.id'],
            'invalid visibility' => [['widgets' => [['visible' => 'yes']]], 'widgets.0.visible'],
            'invalid width' => [['widgets' => [['width' => 'huge']]], 'widgets.0.width'],
            'unvalidated nested property' => [['widgets' => [['user_id' => 123]]], 'widgets.0'],
        ];
    }

    #[DataProvider('calendarViews')]
    public function test_dashboard_renders_each_saved_calendar_with_the_selected_date(string $view, string $element): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(10, 0));
        $user = User::factory()->create();
        $user->dashboard_preferences = [...DashboardLayout::defaults(), 'task_view' => $view];
        $user->save();
        Tarefa::factory()->for($user)->onDate('2026-11-12')->create(['title' => 'Tarefa da quinta']);

        $response = $this->actingAs($user)->get('/dashboard?date=2026-11-12');

        $response->assertSee($element, false)->assertViewHas('taskData', fn (array $data): bool => $data['view'] === $view && $data['selected']->toDateString() === '2026-11-12');
    }

    public static function calendarViews(): array
    {
        return [
            'minimal' => ['calendar', 'id="tarefas-calendar"'],
            'week' => ['week', 'id="tarefas-week-calendar"'],
            'month' => ['month', 'id="tarefas-month-calendar"'],
            'progress' => ['dashboard', 'id="tarefas-overview"'],
        ];
    }

    public function test_task_creation_and_completion_update_the_dashboard_summary(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(10, 0));
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/tarefas', ['title' => 'Criada no painel', 'date' => '2026-11-11'])->assertOk();
        $task = $user->tarefas()->firstOrFail();
        $this->patchJson(route('tarefas.update', $task), ['completed' => true])->assertOk();

        $this->get('/dashboard')->assertViewHas('todayTotal', 1)->assertViewHas('todayCompleted', 1)->assertSee('Criada no painel');
        $this->getJson('/dashboard')->assertJsonStructure(['summary', 'widgets' => ['sonhos', 'projetos', 'cursos', 'financeiro']]);
        $this->assertDatabaseHas('tarefas', ['id' => $task->id, 'completed' => true, 'user_id' => $user->id]);
    }

    public function test_dashboard_preloads_all_calendars_for_switching_without_saving(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/dashboard');

        $response->assertSee('id="tarefas-calendar"', false)
            ->assertSee('id="tarefas-week-calendar"', false)
            ->assertSee('id="tarefas-month-calendar"', false)
            ->assertSee('id="tarefas-overview"', false)
            ->assertSee('id="tarefas-day"', false)
            ->assertViewHas('taskData', fn (array $data): bool => count($data['weekDays']) === 7 && isset($data['overview']['todayTasks']));
    }

    public function test_financial_summary_separates_actual_monthly_cashflow_from_pending_items(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(10, 0));
        $user = User::factory()->create();
        Lancamento::factory()->for($user)->entrada()->create(['amount' => 2000]);
        Lancamento::factory()->for($user)->saida()->create(['amount' => 500]);
        Lancamento::factory()->for($user)->saida()->onDate('2026-10-01')->create(['amount' => 9000]);
        Lancamento::factory()->for($user)->saida()->previsto()->onDate('2026-10-20')->create(['amount' => 300]);
        Lancamento::factory()->for($user)->entrada()->previsto()->onDate('2026-11-30')->create(['amount' => 100]);
        Lancamento::factory()->for($user)->saida()->previsto()->onDate('2026-12-01')->create(['amount' => 8000]);
        Lancamento::factory()->for($user)->saida()->create(['status' => 'cancelado', 'amount' => 7000]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertViewHas('financeiro', ['owner' => $user->name, 'income' => 2000.0, 'expense' => 500.0, 'to_pay' => 300.0, 'to_receive' => 100.0]);
    }

    public function test_financial_widget_respects_shared_wallet_section_permissions(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        Participacao::create(['owner_id' => $owner->id, 'participant_id' => $viewer->id, 'tipo' => 'financas', 'permission' => 'ver', 'status' => 'aceito', 'sections' => ['saidas']]);
        Lancamento::factory()->for($owner)->entrada()->create(['amount' => 987654]);

        $response = $this->actingAs($viewer)->withSession([Carteira::SESSION_KEY => $owner->id])->get('/dashboard');

        $response->assertViewHas('financeiro', null)->assertDontSee('987.654,00')->assertSee('Você não tem acesso ao resumo da carteira selecionada.');
    }

    public function test_invalid_calendar_navigation_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard?date=invalid')->assertSessionHasErrors('date');
        $this->get('/dashboard?view=invalid')->assertSessionHasErrors('view');
    }
}
