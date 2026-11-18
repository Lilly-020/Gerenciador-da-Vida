<?php

namespace Tests\Feature;

use App\Models\Amizade;
use App\Models\Participacao;
use App\Models\Projeto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipacaoTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $guest;

    private Projeto $projeto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['apelido' => 'lilly']);
        $this->guest = User::factory()->create(['apelido' => 'Joao']);
        $this->projeto = Projeto::factory()->for($this->owner)->create(['title' => 'Projeto secreto']);
    }

    private function invite(string $permission = 'editar', string $apelido = 'joao'): Participacao
    {
        Amizade::firstOrCreate(['user_low_id' => $this->owner->id, 'user_high_id' => $this->guest->id], ['requester_id' => $this->owner->id, 'status' => 'accepted']);
        $this->actingAs($this->owner)->post('/participacoes', ['friend_id' => $this->guest->id, 'tipo' => 'projeto', 'item_id' => $this->projeto->id])->assertSessionHasNoErrors();
        $entry = Participacao::latest('id')->firstOrFail();
        if ($permission === 'ver') {
            $entry->update(['permission' => 'ver']);
        }

        return $entry;
    }

    private function accepted(string $permission = 'editar'): Participacao
    {
        return $this->invite($permission);
    }

    public function test_an_owner_adds_an_accepted_friend_by_id(): void
    {
        $entry = $this->invite();
        $this->assertSame('aceito', $entry->status);
        $this->assertSame('editar', $entry->permission);
    }

    public function test_sharing_requires_an_accepted_friend_and_known_item(): void
    {
        $this->actingAs($this->owner)->post('/participacoes', ['tipo' => 'projeto', 'item_id' => $this->projeto->id, 'friend_id' => $this->guest->id])->assertSessionHasErrors('friend_id');
        $this->post('/participacoes', ['apelido' => 'Joao', 'tipo' => 'projeto'])->assertSessionHasErrors(['friend_id', 'item_id']);
    }

    public function test_a_friend_cannot_add_people_to_another_users_project(): void
    {
        $this->invite();
        $third = User::factory()->create();
        Amizade::create(['user_low_id' => $this->guest->id, 'user_high_id' => $third->id, 'requester_id' => $this->guest->id, 'status' => 'accepted']);
        $this->actingAs($this->guest)->post('/participacoes', ['tipo' => 'projeto', 'item_id' => $this->projeto->id, 'friend_id' => $third->id])->assertForbidden();
    }

    public function test_a_project_is_visible_only_after_an_accepted_friend_is_added_to_it(): void
    {
        $this->actingAs($this->guest)->get('/projetos')->assertDontSee('Projeto secreto');
        $this->invite();
        $this->actingAs($this->guest)->get('/projetos')->assertSee('Projeto secreto');
    }

    public function test_the_owner_sees_friends_added_to_the_project(): void
    {
        $this->invite();
        $this->actingAs($this->owner)->get('/projetos')->assertOk()->assertSee($this->guest->handle())->assertSee('Selecionar amigo');
    }

    public function test_legacy_item_acceptance_endpoints_are_removed(): void
    {
        $entry = $this->invite();
        $this->actingAs($this->guest)->post('/participacoes/'.$entry->id.'/aceitar')->assertNotFound();
        $this->post('/participacoes/'.$entry->id.'/recusar')->assertNotFound();
    }

    public function test_pending_or_declined_friendships_do_not_grant_project_access(): void
    {
        $friendship = Amizade::create(['user_low_id' => $this->owner->id, 'user_high_id' => $this->guest->id, 'requester_id' => $this->owner->id, 'status' => 'declined']);
        $this->actingAs($this->guest)->get('/projetos')->assertDontSee('Projeto secreto');
        $this->actingAs($this->owner)->post('/participacoes', ['tipo' => 'projeto', 'item_id' => $this->projeto->id, 'friend_id' => $this->guest->id])->assertSessionHasErrors('friend_id');
    }

    public function test_a_participant_who_can_edit_manages_tasks_but_not_the_project_itself(): void
    {
        $this->accepted('editar');

        $this->actingAs($this->guest)->postJson("/projetos/{$this->projeto->id}/tarefas", ['title' => 'Minha parte'])
            ->assertOk();
        $this->assertDatabaseHas('projeto_tasks', ['projeto_id' => $this->projeto->id, 'title' => 'Minha parte']);

        $this->actingAs($this->guest)->deleteJson("/projetos/{$this->projeto->id}")->assertForbidden();
        $this->assertModelExists($this->projeto);
    }

    public function test_a_view_only_participant_cannot_change_anything(): void
    {
        $this->accepted('ver');
        $task = $this->projeto->tasks()->create(['title' => 'Tarefa do dono']);

        $this->actingAs($this->guest)->postJson("/projetos/{$this->projeto->id}/tarefas", ['title' => 'Invasão'])
            ->assertForbidden();
        $this->actingAs($this->guest)->patchJson("/projetos/{$this->projeto->id}/tarefas/{$task->id}", ['completed' => true])
            ->assertForbidden();
        $this->actingAs($this->guest)->deleteJson("/projetos/{$this->projeto->id}/tarefas/{$task->id}")
            ->assertForbidden();
        $this->actingAs($this->guest)->putJson("/projetos/{$this->projeto->id}", ['title' => 'Outro nome'])
            ->assertForbidden();

        $this->assertSame(1, $this->projeto->tasks()->count());
        $this->assertFalse($task->fresh()->completed);
        $this->actingAs($this->guest)->get('/projetos')->assertDontSee('Adicionar nova tarefa');
    }

    public function test_removing_a_participant_ends_their_access(): void
    {
        $participacao = $this->accepted();

        $this->actingAs($this->owner)->delete("/participacoes/{$participacao->id}");

        $this->assertModelMissing($participacao);
        $this->actingAs($this->guest)->get('/projetos')->assertDontSee('Projeto secreto');
        $this->actingAs($this->guest)->postJson("/projetos/{$this->projeto->id}/tarefas", ['title' => 'x'])
            ->assertNotFound();
    }

    public function test_a_stranger_cannot_remove_someone_elses_participation(): void
    {
        $participacao = $this->accepted();
        $third = User::factory()->create();

        $this->actingAs($third)->delete("/participacoes/{$participacao->id}")->assertNotFound();

        $this->assertModelExists($participacao);
    }

    public function test_friend_requests_appear_in_the_global_notification_bell(): void
    {
        $this->actingAs($this->owner)->postJson(route('amigos.store'), ['apelido' => 'Joao'])->assertOk();
        $this->actingAs($this->guest)->get('/tarefas')->assertSee('data-notifications-toggle', false);
        $response = $this->getJson(route('notificacoes.index'))->assertOk()->assertJsonPath('count', 1);
        $this->assertStringContainsString('Pedido de amizade', $response->json('html'));
    }
}
