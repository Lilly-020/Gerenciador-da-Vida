<?php

namespace Tests\Feature;

use App\Models\Amizade;
use App\Models\Lancamento;
use App\Models\Participacao;
use App\Models\Projeto;
use App\Models\Quadro;
use App\Models\Sonho;
use App\Models\Tarefa;
use App\Models\User;
use App\Notifications\SocialNotification;
use App\Support\Carteira;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class FriendshipCollaborationTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_handle_requests_are_private_idempotent_and_only_the_recipient_can_accept(): void
    {
        $sender = User::factory()->create(['apelido' => 'sender']);
        $recipient = User::factory()->create(['apelido' => 'Friend.Unique']);
        $this->actingAs($sender)->postJson(route('amigos.store'), ['apelido' => '@fRiEnD.uNiQuE'])->assertOk();
        $friendship = Amizade::firstOrFail();
        $this->assertFalse($sender->isFriendsWith($recipient->id));
        $this->postJson(route('amigos.store'), ['apelido' => 'Friend.Unique'])->assertOk();
        $this->assertSame(1, $recipient->notifications()->count());
        $this->get('/usuarios/buscar?q=Fr')->assertNotFound();
        $this->postJson(route('amigos.store'), ['apelido' => 'Friend'])->assertUnprocessable();
        $this->post(route('amigos.respond', $friendship), ['accept' => true])->assertNotFound();
        $this->actingAs(User::factory()->create())->post(route('amigos.respond', $friendship), ['accept' => true])->assertNotFound();
        $notification = $recipient->notifications()->firstOrFail();
        $this->actingAs($recipient)->post(route('notificacoes.open', $notification))->assertRedirect(route('perfil', ['aba' => 'amigos']));
        $this->assertSame('pending', $friendship->fresh()->status);
        $this->get(route('perfil', ['aba' => 'amigos']))->assertOk()->assertSee('Aceitar amizade');
        $this->post(route('amigos.respond', $friendship), ['accept' => true])->assertRedirect();
        $this->assertTrue($sender->isFriendsWith($recipient->id));
        $this->assertTrue($recipient->isFriendsWith($sender->id));
        $this->assertSame(SocialNotification::class, $sender->notifications()->firstOrFail()->type);
    }

    #[TestWith(['projeto'])]
    #[TestWith(['sonho'])]
    #[TestWith(['tarefa'])]
    public function test_friendship_alone_grants_no_access_and_sharing_grants_only_one_item(string $kind): void
    {
        $owner = User::factory()->create();
        $friend = User::factory()->create();
        Amizade::factory()->create(['user_low_id' => min($owner->id, $friend->id), 'user_high_id' => max($owner->id, $friend->id), 'requester_id' => $owner->id]);
        $class = ['projeto' => Projeto::class, 'sonho' => Sonho::class, 'tarefa' => Tarefa::class][$kind];
        $item = $class::factory()->for($owner)->create();
        $other = $class::factory()->for($owner)->create();
        $this->actingAs($friend);
        $this->assertNull($class::find($item->id));
        $this->actingAs($owner)->post(route('participacoes.store'), ['tipo' => $kind, 'item_id' => $item->id, 'friend_id' => $friend->id])->assertRedirect();
        $this->actingAs($friend);
        $this->assertNotNull($class::find($item->id));
        $this->assertNull($class::find($other->id));
        $this->deleteJson(route(['projeto' => 'projetos.destroy', 'sonho' => 'sonhos.destroy', 'tarefa' => 'tarefas.destroy'][$kind], $item))->assertForbidden();
        if ($kind === 'projeto') {
            $this->postJson(route('projetos.tarefas.store', $item), ['title' => 'Tarefa do amigo'])->assertOk();
        } elseif ($kind === 'tarefa') {
            $this->patchJson(route('tarefas.update', $item), ['completed' => true])->assertOk();
            $this->assertTrue($item->fresh()->completed);
        } else {
            $this->putJson(route('sonhos.update', $item), ['title' => 'Sonho em conjunto'])->assertOk();
        }
        $this->assertSame(1, $friend->notifications()->count());
    }

    public function test_planner_friends_can_create_cards_but_cannot_delete_the_board_or_manage_other_members(): void
    {
        $owner = User::factory()->create();
        $friend = User::factory()->create();
        $stranger = User::factory()->create();
        Amizade::factory()->create(['user_low_id' => $owner->id, 'user_high_id' => $friend->id, 'requester_id' => $owner->id]);
        $board = Quadro::factory()->for($owner)->create();
        $other = Quadro::factory()->for($owner)->create();
        $list = $board->listas()->create(['title' => 'Lista']);
        $this->actingAs($owner)->postJson(route('quadros.membros.store', $board), ['friend_id' => $stranger->id])->assertUnprocessable();
        $this->postJson(route('quadros.membros.store', $board), ['friend_id' => $friend->id, 'role' => 'admin'])->assertOk();
        $this->assertSame('editor', $board->membros()->firstOrFail()->role);
        $this->actingAs($friend)->get(route('quadros.show', $board))->assertOk();
        $this->get(route('quadros.show', $other))->assertNotFound();
        $this->postJson(route('quadros.cartoes.store', $board), ['lista_id' => $list->id, 'title' => 'Criado pelo amigo'])->assertOk();
        $this->deleteJson(route('quadros.destroy', $board))->assertForbidden();
        $this->postJson(route('quadros.membros.store', $board), ['friend_id' => $stranger->id])->assertForbidden();
        $this->assertSame(1, $friend->notifications()->count());
    }

    public function test_removing_friendship_revokes_item_access_and_financial_sharing_is_disabled(): void
    {
        $owner = User::factory()->create();
        $friend = User::factory()->create();
        $friendship = Amizade::factory()->create(['user_low_id' => $owner->id, 'user_high_id' => $friend->id, 'requester_id' => $owner->id]);
        $project = Projeto::factory()->for($owner)->create();
        $this->actingAs($owner)->post(route('participacoes.store'), ['tipo' => 'projeto', 'item_id' => $project->id, 'friend_id' => $friend->id])->assertRedirect();
        Participacao::create(['owner_id' => $owner->id, 'participant_id' => $friend->id, 'tipo' => 'financas', 'status' => 'aceito', 'permission' => 'admin']);
        Lancamento::factory()->for($owner)->create(['description' => 'Segredo financeiro']);
        $this->actingAs($friend)->withSession(['carteira_owner_id' => $owner->id])->get(route('financeiro'))->assertOk()->assertDontSee('Segredo financeiro');
        $this->assertSame($friend->id, Carteira::ownerId());
        $this->post(route('participacoes.store'), ['tipo' => 'financas', 'friend_id' => $owner->id, 'item_id' => 1])->assertSessionHasErrors('tipo');
        $this->actingAs($owner)->delete(route('amigos.destroy', $friendship))->assertRedirect();
        $this->actingAs($friend);
        $this->assertNull(Projeto::find($project->id));
    }
}
