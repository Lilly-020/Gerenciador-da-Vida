<?php

namespace Tests\Feature;

use App\Models\Nota;
use App\Models\NotaItem;
use App\Models\Tarefa;
use App\Models\User;
use App\Support\DashboardLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_notes_require_authentication(): void
    {
        $this->get('/notas')->assertRedirect(route('login'));
        $this->postJson('/notas', ['title' => 'Privada'])->assertUnauthorized();
    }

    public function test_notes_page_offers_text_checklists_and_a_private_editor(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/notas');

        $response->assertOk()->assertSee('Nova nota')->assertSee('Nova lista')->assertSee('Arquivadas')
            ->assertSee('id="note-editor-modal"', false)->assertSee('id="note-link-modal"', false);
    }

    public function test_a_note_can_mix_text_and_checklist_without_a_task_or_date(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/notas', [
            'title' => 'Lista de compras', 'content' => 'Comprar no mercado do bairro.', 'color' => 'amber', 'pinned' => true,
            'items' => [['text' => 'Arroz', 'completed' => false], ['text' => 'Leite', 'completed' => true]],
            'user_id' => 999, 'tarefa_id' => null,
        ]);

        $response->assertOk()->assertJsonPath('nota.title', 'Lista de compras')->assertJsonPath('nota.tarefa', null)
            ->assertJsonPath('nota.items.0.text', 'Arroz')->assertJsonPath('nota.items.1.completed', true);
        $this->assertDatabaseHas('notas', ['user_id' => $user->id, 'title' => 'Lista de compras', 'color' => 'amber', 'pinned' => true, 'tarefa_id' => null]);
        $this->assertDatabaseCount('nota_items', 2);
        $this->assertDatabaseCount('tarefas', 0);
        $this->assertSame([0, 1], NotaItem::orderBy('id')->pluck('position')->all());
    }

    public function test_a_text_only_note_can_be_created_directly_from_a_task(): void
    {
        $user = User::factory()->create();
        $task = Tarefa::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson('/notas', ['title' => 'Lembrar', 'content' => 'Levar sacolas.', 'tarefa_id' => $task->id]);

        $response->assertOk()->assertJsonPath('nota.tarefa.id', $task->id)->assertJsonCount(0, 'nota.items');
        $this->assertDatabaseHas('notas', ['title' => 'Lembrar', 'tarefa_id' => $task->id]);
    }

    public function test_editing_a_note_preserves_item_identity_and_supports_reordering_adding_and_removing(): void
    {
        $user = User::factory()->create();
        $note = Nota::factory()->for($user)->create();
        $first = NotaItem::factory()->for($note, 'nota')->create(['text' => 'Primeiro']);
        $second = NotaItem::factory()->for($note, 'nota')->create(['text' => 'Segundo', 'position' => 1]);
        $removed = NotaItem::factory()->for($note, 'nota')->create(['text' => 'Remover', 'position' => 2]);

        $response = $this->actingAs($user)->patchJson(route('notas.update', $note), ['title' => 'Atualizada', 'items' => [
            ['id' => $second->id, 'text' => 'Segundo editado', 'completed' => true],
            ['id' => $first->id, 'text' => 'Primeiro', 'completed' => false],
            ['text' => 'Novo item', 'completed' => false],
        ]]);

        $response->assertOk()->assertJsonPath('nota.items.0.id', $second->id)->assertJsonPath('nota.items.1.id', $first->id);
        $this->assertDatabaseHas('nota_items', ['id' => $second->id, 'text' => 'Segundo editado', 'position' => 0, 'completed' => true]);
        $this->assertModelMissing($removed);
        $this->assertDatabaseCount('nota_items', 3);
    }

    public function test_pin_archive_and_text_only_updates_do_not_clear_checklists(): void
    {
        $user = User::factory()->create();
        $note = Nota::factory()->for($user)->create(['title' => 'Organizar notas']);
        $item = NotaItem::factory()->for($note, 'nota')->create();

        $this->actingAs($user)->patchJson(route('notas.update', $note), ['pinned' => true, 'archived' => true])->assertOk();

        $this->assertModelExists($item);
        $this->get('/notas')->assertDontSee('Organizar notas');
        $this->get('/notas?filter=archived')->assertSee('Organizar notas');
        $this->patchJson(route('notas.update', $note), ['archived' => false])->assertOk();
        $this->get('/notas')->assertSee('Organizar notas');
    }

    public function test_search_matches_text_and_list_items_and_filters_lists_and_annotations(): void
    {
        $user = User::factory()->create();
        $list = Nota::factory()->for($user)->create(['title' => 'Compras do mês', 'content' => 'Mercado']);
        NotaItem::factory()->for($list, 'nota')->create(['text' => 'Café especial']);
        Nota::factory()->for($user)->create(['title' => 'Ideias de viagem', 'content' => 'Visitar montanhas']);

        $this->actingAs($user)->get('/notas?q=Caf%C3%A9')->assertSee('Compras do mês')->assertDontSee('Ideias de viagem');
        $this->get('/notas?filter=lists')->assertSee('Compras do mês')->assertDontSee('Ideias de viagem');
        $this->get('/notas?filter=text')->assertSee('Ideias de viagem')->assertDontSee('Compras do mês');
        $this->get('/notas?q=montanhas')->assertSee('Ideias de viagem');
    }

    public function test_private_notes_are_not_visible_or_mutable_by_another_user(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $note = Nota::factory()->for($owner)->create(['title' => 'Segredo privado']);
        $item = NotaItem::factory()->for($note, 'nota')->create(['text' => 'Item secreto']);
        $task = Tarefa::factory()->for($viewer)->create();

        $this->actingAs($viewer)->get('/notas')->assertDontSee('Segredo privado')->assertDontSee('Item secreto');
        $this->getJson(route('notas.show', $note))->assertNotFound();
        $this->patchJson(route('notas.update', $note), ['title' => 'Invadida'])->assertNotFound();
        $this->deleteJson(route('notas.destroy', $note))->assertNotFound();
        $this->putJson(route('notas.tarefa', $note), ['tarefa_id' => $task->id])->assertNotFound();
        $this->postJson(route('notas.agendar', $note), ['title' => 'Invadida', 'date' => '2026-11-12'])->assertNotFound();
        $this->patchJson(route('notas.itens.update', [$note, $item]), ['completed' => true])->assertNotFound();

        $this->assertSame('Segredo privado', $note->fresh()->title);
        $this->assertFalse($item->fresh()->completed);
    }

    public function test_items_from_another_note_cannot_be_injected_or_toggled(): void
    {
        $user = User::factory()->create();
        $first = Nota::factory()->for($user)->create(['title' => 'Primeira']);
        $second = Nota::factory()->for($user)->create();
        $item = NotaItem::factory()->for($second, 'nota')->create(['text' => 'Fica na segunda']);

        $this->actingAs($user)->patchJson(route('notas.update', $first), ['title' => 'Não salvar', 'items' => [['id' => $item->id, 'text' => 'Roubar', 'completed' => true]]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.id');
        $this->patchJson(route('notas.itens.update', [$first, $item]), ['completed' => true])->assertNotFound();

        $this->assertSame('Primeira', $first->fresh()->title);
        $this->assertSame('Fica na segunda', $item->fresh()->text);
        $this->assertFalse($item->fresh()->completed);
    }

    public function test_link_and_unlink_keep_the_original_note_items_and_task(): void
    {
        $user = User::factory()->create();
        $note = Nota::factory()->for($user)->create();
        $item = NotaItem::factory()->for($note, 'nota')->create();
        $task = Tarefa::factory()->for($user)->create();

        $this->actingAs($user)->putJson(route('notas.tarefa', $note), ['tarefa_id' => $task->id])->assertOk()->assertJsonPath('nota.tarefa.id', $task->id);
        $this->putJson(route('notas.tarefa', $note), ['tarefa_id' => null])->assertOk()->assertJsonPath('nota.tarefa', null);

        $this->assertModelExists($task);
        $this->assertModelExists($item);
        $this->assertDatabaseCount('notas', 1);
        $this->assertDatabaseCount('tarefas', 1);
    }

    public function test_tasks_from_another_user_are_not_linkable_or_offered(): void
    {
        $user = User::factory()->create();
        $note = Nota::factory()->for($user)->create();
        $private = Tarefa::factory()->create(['title' => 'Tarefa confidencial']);

        $this->actingAs($user)->putJson(route('notas.tarefa', $note), ['tarefa_id' => $private->id])->assertNotFound();
        $this->postJson('/notas', ['title' => 'Inválida', 'tarefa_id' => $private->id])->assertUnprocessable()->assertJsonValidationErrors('tarefa_id');
        $this->getJson('/notas/tarefas')->assertDontSee('Tarefa confidencial');
        $this->getJson('/notas/opcoes?tarefa_id='.$private->id)->assertNotFound();

        $this->assertNull($note->fresh()->tarefa_id);
    }

    public function test_scheduling_creates_a_calendar_task_with_the_same_note_and_checklist(): void
    {
        $user = User::factory()->create();
        $note = Nota::factory()->for($user)->create(['title' => 'Compras']);
        $item = NotaItem::factory()->for($note, 'nota')->create(['text' => 'Arroz integral']);

        $response = $this->actingAs($user)->postJson(route('notas.agendar', $note), ['title' => 'Fazer compras', 'date' => '2026-11-12', 'start_time' => '15:00', 'end_time' => '16:00']);

        $response->assertOk()->assertJsonPath('nota.items.0.id', $item->id);
        $task = $note->fresh()->tarefa;
        $response->assertJsonPath('calendar_url', route('dashboard', ['date' => '2026-11-12', 'view' => 'week', 'focus_task' => $task->id], absolute: false));
        $this->assertDatabaseHas('tarefas', ['id' => $task->id, 'title' => 'Fazer compras', 'date' => '2026-11-12 00:00:00', 'start_time' => '15:00', 'completed' => false]);
        $this->get('/tarefas?view=week&date=2026-11-12')->assertSee('Fazer compras');
        $details = $this->getJson(route('tarefas.show', $task))->assertOk();
        $this->assertStringContainsString('Arroz integral', $details->json('html'));
        $this->assertStringContainsString('data-note-item-toggle', $details->json('note_section'));
        $this->assertDatabaseCount('notas', 1);
        $this->assertDatabaseCount('nota_items', 1);
    }

    public function test_scheduled_note_opens_the_dashboard_in_its_week_with_the_linked_event(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 12)->setTime(10, 0));
        $user = User::factory()->create();
        $note = Nota::factory()->for($user)->create(['title' => 'Nota para dezembro']);
        NotaItem::factory()->for($note, 'nota')->create(['text' => 'Levar documentos']);

        $scheduled = $this->actingAs($user)->postJson(route('notas.agendar', $note), [
            'title' => 'Reunião de dezembro', 'date' => '2026-12-02', 'start_time' => '15:00', 'end_time' => '16:00',
        ])->assertOk();

        $taskId = $scheduled->json('nota.tarefa.id');
        $this->get($scheduled->json('calendar_url'))->assertOk()->assertSee('Nota vinculada')
            ->assertSee('data-calendar-task-id="'.$taskId.'"', false)
            ->assertViewHas('taskData', fn (array $data): bool => $data['view'] === 'week'
                && $data['selected']->toDateString() === '2026-12-02'
                && $data['weekDays'][2]['events'][0]['task']->id === $taskId);
    }

    public function test_rescheduling_reuses_the_linked_task_and_can_clear_its_times(): void
    {
        $user = User::factory()->create();
        $task = Tarefa::factory()->for($user)->completed()->create(['start_time' => '09:00', 'end_time' => '10:00']);
        $note = Nota::factory()->for($user)->for($task, 'tarefa')->create();

        $this->actingAs($user)->postJson(route('notas.agendar', $note), ['title' => 'Reagendada', 'date' => '2026-11-14'])->assertOk()->assertJsonPath('nota.tarefa.id', $task->id);
        $this->postJson(route('notas.agendar', $note), ['title' => 'Reagendada', 'date' => '2026-11-15'])->assertOk()->assertJsonPath('nota.tarefa.id', $task->id);

        $this->assertDatabaseCount('tarefas', 1);
        $this->assertDatabaseHas('tarefas', ['id' => $task->id, 'title' => 'Reagendada', 'date' => '2026-11-15 00:00:00', 'start_time' => null, 'end_time' => null, 'completed' => true]);
    }

    public function test_scheduling_a_note_without_a_time_appears_in_the_dashboard_and_refreshed_all_day_calendar(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 12)->setTime(10, 0));
        $user = User::factory()->create();
        $user->dashboard_preferences = [...DashboardLayout::defaults(), 'task_view' => 'week'];
        $user->save();
        $note = Nota::factory()->for($user)->create(['title' => 'Lista agendada']);
        $this->actingAs($user)->get('/dashboard')->assertViewHas('todayTotal', 0);

        $this->postJson(route('notas.agendar', $note), ['title' => 'Fazer compras hoje', 'date' => '2026-11-12'])->assertOk();

        $taskId = $note->fresh()->tarefa_id;
        $this->get('/dashboard')->assertViewHas('todayTotal', 1)
            ->assertViewHas('taskData', fn (array $data): bool => $data['weekDays'][3]['date']->toDateString() === '2026-11-12'
                && $data['weekDays'][3]['allDay']->pluck('id')->all() === [$taskId]);
        $refresh = $this->getJson('/tarefas?date=2026-11-12')->assertOk();
        $this->assertStringContainsString('Fazer compras hoje', $refresh->json('week'));
        $this->assertStringContainsString('week-all-event', $refresh->json('week'));
    }

    public function test_completing_checklist_items_and_tasks_are_independent(): void
    {
        $user = User::factory()->create();
        $task = Tarefa::factory()->for($user)->create();
        $note = Nota::factory()->for($user)->for($task, 'tarefa')->create();
        $item = NotaItem::factory()->for($note, 'nota')->create();

        $this->actingAs($user)->patchJson(route('notas.itens.update', [$note, $item]), ['completed' => true])->assertOk()->assertJsonPath('nota.items.0.completed', true);

        $this->assertFalse($task->fresh()->completed);
        $this->patchJson(route('tarefas.update', $task), ['completed' => true])->assertOk();
        $this->patchJson(route('notas.itens.update', [$note, $item]), ['completed' => false])->assertOk();
        $this->assertTrue($task->fresh()->completed);
        $this->assertFalse($item->fresh()->completed);
    }

    public function test_task_summary_shows_an_interactive_checklist_and_updated_progress(): void
    {
        $user = User::factory()->create();
        $task = Tarefa::factory()->for($user)->create();
        $note = Nota::factory()->for($user)->for($task, 'tarefa')->create(['title' => 'Compras']);
        $item = NotaItem::factory()->for($note, 'nota')->create(['text' => 'Comprar arroz']);
        NotaItem::factory()->for($note, 'nota')->create(['text' => 'Comprar leite', 'position' => 1]);

        $summary = $this->actingAs($user)->getJson(route('tarefas.show', $task))->assertOk()->json('summary');

        $this->assertStringContainsString('Comprar arroz', $summary);
        $this->assertStringContainsString('Comprar leite', $summary);
        $this->assertStringContainsString('data-note-item-toggle="'.route('notas.itens.update', [$note, $item], absolute: false).'"', $summary);
        $this->assertStringContainsString('0/2 itens', $summary);
        $this->patchJson(route('notas.itens.update', [$note, $item]), ['completed' => true])->assertOk();
        $updated = $this->getJson(route('tarefas.show', $task))->json('summary');
        $this->assertStringContainsString('1/2 itens', $updated);
        $this->assertStringContainsString('line-through text-slate-500', $updated);
        $this->assertFalse($task->fresh()->completed);
    }

    public function test_month_calendar_identifies_attached_notes_and_exposes_their_content_on_the_first_summary(): void
    {
        $user = User::factory()->create();
        $task = Tarefa::factory()->for($user)->onDate('2026-11-12')->create();
        $note = Nota::factory()->for($user)->for($task, 'tarefa')->create(['content' => 'Levar as sacolas reutilizáveis.']);
        NotaItem::factory()->for($note, 'nota')->create(['text' => 'Comprar pão']);

        $this->actingAs($user)->get('/tarefas?view=month&date=2026-11-12')
            ->assertSee('data-task-note-icon', false)->assertSee('data-task-card-url', false);

        $summary = $this->getJson(route('tarefas.show', $task))->json('summary');
        $this->assertStringContainsString('Levar as sacolas reutilizáveis.', $summary);
        $this->assertStringContainsString('Comprar pão', $summary);
        $this->assertStringContainsString('data-note-item-toggle', $summary);
    }

    public function test_calendar_does_not_show_the_note_icon_on_tasks_without_attached_notes(): void
    {
        $user = User::factory()->create();
        Tarefa::factory()->for($user)->onDate('2026-11-12')->create();

        $this->actingAs($user)->get('/tarefas?view=month&date=2026-11-12')->assertDontSee('data-task-note-icon', false);
    }

    public function test_deleting_a_task_preserves_linked_notes_and_items_as_independent_notes(): void
    {
        $user = User::factory()->create();
        $task = Tarefa::factory()->for($user)->create();
        $note = Nota::factory()->for($user)->for($task, 'tarefa')->create();
        $item = NotaItem::factory()->for($note, 'nota')->create();

        $this->actingAs($user)->deleteJson(route('tarefas.destroy', $task))->assertOk();

        $this->assertModelExists($note);
        $this->assertModelExists($item);
        $this->assertNull($note->fresh()->tarefa_id);
        $this->getJson(route('notas.show', $note))->assertOk()->assertJsonPath('nota.tarefa', null);
    }

    public function test_deleting_a_note_removes_its_items_but_preserves_the_task(): void
    {
        $user = User::factory()->create();
        $task = Tarefa::factory()->for($user)->create();
        $note = Nota::factory()->for($user)->for($task, 'tarefa')->create();
        $item = NotaItem::factory()->for($note, 'nota')->create();

        $this->actingAs($user)->deleteJson(route('notas.destroy', $note))->assertOk();

        $this->assertModelMissing($note);
        $this->assertModelMissing($item);
        $this->assertModelExists($task);
    }

    public function test_note_picker_only_offers_available_owned_notes(): void
    {
        $user = User::factory()->create();
        $task = Tarefa::factory()->for($user)->create();
        $otherTask = Tarefa::factory()->for($user)->create();
        Nota::factory()->for($user)->create(['title' => 'Disponível']);
        Nota::factory()->for($user)->for($task, 'tarefa')->create(['title' => 'Já vinculada aqui']);
        Nota::factory()->for($user)->for($otherTask, 'tarefa')->create(['title' => 'Outra tarefa']);
        Nota::factory()->for($user)->create(['title' => 'Arquivada', 'archived' => true]);
        Nota::factory()->create(['title' => 'Privada']);

        $response = $this->actingAs($user)->getJson('/notas/opcoes?tarefa_id='.$task->id);

        $response->assertOk()->assertJsonCount(2, 'options')->assertJsonFragment(['label' => 'Disponível'])->assertJsonFragment(['label' => 'Já vinculada aqui'])
            ->assertDontSee('Outra tarefa')->assertDontSee('Arquivada')->assertDontSee('Privada');
    }

    public function test_notes_and_items_are_escaped_in_cards_and_task_details(): void
    {
        $user = User::factory()->create();
        $task = Tarefa::factory()->for($user)->create();
        $note = Nota::factory()->for($user)->for($task, 'tarefa')->create(['title' => '<script>alert(1)</script>', 'content' => '<img src=x onerror=alert(2)>']);
        NotaItem::factory()->for($note, 'nota')->create(['text' => '<svg onload=alert(3)>']);

        $response = $this->actingAs($user)->get('/notas');

        $response->assertSee('<script>alert(1)</script>')->assertDontSee('<img src=x onerror=alert(2)>', false)->assertDontSee('<svg onload=alert(3)>', false);
        $details = $this->getJson(route('tarefas.show', $task));
        $this->assertStringContainsString('&lt;svg onload=alert(3)&gt;', $details->json('note_section'));
    }

    #[DataProvider('invalidNotes')]
    public function test_invalid_notes_return_422_without_creating_records(array $data, string $field): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/notas', $data)->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('notas', 0);
        $this->assertDatabaseCount('nota_items', 0);
    }

    public static function invalidNotes(): array
    {
        return [
            'missing title' => [[], 'title'],
            'invalid color' => [['title' => 'Nota', 'color' => 'red'], 'color'],
            'blank item' => [['title' => 'Nota', 'items' => [['text' => ' ', 'completed' => false]]], 'items.0.text'],
            'unvalidated nested field' => [['title' => 'Nota', 'items' => [['text' => 'Item', 'completed' => false, 'nota_id' => 123]]], 'items.0'],
            'missing item state' => [['title' => 'Nota', 'items' => [['text' => 'Item']]], 'items.0.completed'],
        ];
    }

    #[DataProvider('invalidSchedules')]
    public function test_invalid_schedules_do_not_create_tasks(array $fields, string $field): void
    {
        $user = User::factory()->create();
        $note = Nota::factory()->for($user)->create();

        $this->actingAs($user)->postJson(route('notas.agendar', $note), ['title' => 'Compras', ...$fields])->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('tarefas', 0);
        $this->assertNull($note->fresh()->tarefa_id);
    }

    public static function invalidSchedules(): array
    {
        return [
            'missing date' => [[], 'date'],
            'invalid date' => [['date' => 'not-a-date'], 'date'],
            'end before start' => [['date' => '2026-11-12', 'start_time' => '15:00', 'end_time' => '14:00'], 'end_time'],
            'end without start' => [['date' => '2026-11-12', 'end_time' => '14:00'], 'start_time'],
            'invalid end date' => [['date' => '2026-11-12', 'end_date' => '2026-11-11'], 'end_date'],
        ];
    }
}
