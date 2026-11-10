<?php

namespace Tests\Feature;

use App\Models\Tarefa;
use App\Models\User;
use App\Support\CalendarioFeriados;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TarefaCategoriesAndHolidaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_week_has_only_a_holiday_filter_and_holidays_do_not_count_as_tasks(): void
    {
        $this->actingAs(User::factory()->create())->get('/tarefas?view=week&date=2026-04-03')
            ->assertOk()->assertSee('Sexta-feira Santa (Paixão de Cristo)')
            ->assertDontSee('data-week-kind-filter="reuniao"', false)
            ->assertDontSee('data-week-kind-filter="encontro"', false)
            ->assertDontSee('data-week-kind-filter="evento"', false)
            ->assertDontSee('data-week-kind-filter="atividade"', false)
            ->assertSee('data-week-kind-filter="feriado"', false);
        $this->getJson('/tarefas?date=2026-04-03')->assertJsonPath('summary.total', 0);
        $this->assertDatabaseCount('tarefas', 0);
    }

    public function test_holidays_appear_in_month_and_minimalist_calendars(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/tarefas?view=month&date=2026-11-01')->assertOk()->assertSee('Finados')->assertSee('Dia Nacional de Zumbi e da Consciência Negra');
        $this->get('/tarefas?view=calendar&date=2026-11-02')->assertOk()->assertSee('Feriados nacionais neste mês')->assertSee('Finados');
        $this->assertSame('Sexta-feira Santa (Paixão de Cristo)', CalendarioFeriados::on(Carbon::parse('2027-03-26')));
        $this->assertSame('Sexta-feira Santa (Paixão de Cristo)', CalendarioFeriados::on(Carbon::parse('2028-04-14')));
        $this->assertNull(CalendarioFeriados::on(Carbon::parse('2026-02-17')));
    }

    public function test_custom_categories_can_be_created_and_selected_without_duplicates(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = $this->postJson(route('tarefas.categorias.store'), ['name' => 'Pessoal'])->assertOk()->json();
        $this->postJson(route('tarefas.categorias.store'), ['name' => 'Pessoal'])->assertOk()->assertJsonPath('id', $category['id']);
        $this->assertDatabaseCount('tarefa_categorias', 1);
        $result = $this->postJson('/tarefas', [
            'title' => 'Organizar viagem', 'date' => '2026-11-10', 'end_date' => '2026-11-12',
            'notes' => 'Separar documentos', 'category_id' => $category['id'], 'start_time' => '18:00', 'end_time' => '08:00',
        ])->assertOk();
        $this->assertDatabaseHas('tarefas', ['user_id' => $user->id, 'category_id' => $category['id'], 'notes' => 'Separar documentos']);
        $this->assertNotNull($result->json('created_task_url'));
        $this->getJson($result->json('created_task_url'))->assertOk()->assertSee('Pessoal');
    }

    public function test_categories_are_private_and_cannot_be_assigned_across_users(): void
    {
        $this->actingAs(User::factory()->create());
        $category = $this->postJson(route('tarefas.categorias.store'), ['name' => 'Categoria privada'])->assertOk()->json();
        $other = User::factory()->create();
        $task = Tarefa::factory()->for($other)->create();
        $this->actingAs($other)->get('/tarefas')->assertDontSee('Categoria privada');
        $this->postJson('/tarefas', ['title' => 'Inválida', 'date' => '2026-11-10', 'category_id' => $category['id']])
            ->assertUnprocessable()->assertJsonValidationErrors('category_id');
        $this->patchJson(route('tarefas.update', $task), ['category_id' => $category['id']])
            ->assertUnprocessable()->assertJsonValidationErrors('category_id');
        $this->postJson(route('tarefas.categorias.store'), ['name' => '   '])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_new_task_form_exposes_options_and_repetition_preserves_custom_details(): void
    {
        $this->actingAs(User::factory()->create())->get('/tarefas?date=2026-11-10')->assertOk()
            ->assertSee('Título da tarefa')->assertSee('Data de início')->assertSee('Data de fim (opcional)')
            ->assertSee('Observações (opcional)')->assertSee('Criar categoria')->assertSee('Adicionar com anexos')
            ->assertDontSee('Tipo e horário')->assertDontSee('Editar tipo e horário');
        $category = $this->postJson(route('tarefas.categorias.store'), ['name' => 'Leitura'])->assertOk()->json();
        $this->postJson('/tarefas', [
            'title' => 'Ler', 'date' => '2026-11-09', 'end_date' => '2026-11-10', 'notes' => 'Capítulo 1',
            'weekdays' => [1, 2], 'repeat_until' => '2026-11-10', 'category_id' => $category['id'],
        ])->assertOk();
        $tasks = Tarefa::orderBy('date')->get();
        $this->assertCount(2, $tasks);
        $this->assertSame(['2026-11-10', '2026-11-11'], $tasks->map(fn (Tarefa $task): string => $task->end_date->toDateString())->all());
        $this->assertSame(['Capítulo 1', 'Capítulo 1'], $tasks->pluck('notes')->all());
        $this->assertSame([$category['id'], $category['id']], $tasks->pluck('category_id')->all());
    }
}
