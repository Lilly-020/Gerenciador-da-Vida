<?php

namespace Tests\Feature;

use App\Models\Quadro;
use App\Models\QuadroCartao;
use App\Models\QuadroMembro;
use App\Models\User;
use App\Notifications\QuadroCommentMention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuadroMentionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mentions_notify_only_accepted_participants_once_using_their_real_names(): void
    {
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create();
        $member = User::factory()->create(['name' => 'Pessoa marcada']);
        $pending = User::factory()->create();
        $outsider = User::factory()->create();
        QuadroMembro::factory()->for($board)->for($member)->create(['status' => 'accepted']);
        QuadroMembro::factory()->for($board)->for($pending)->create(['status' => 'pending']);
        $html = '<p>Olá ';
        foreach ([$member, $member, $owner, $pending, $outsider] as $person) {
            $html .= '<span data-mention-id="'.$person->id.'">@Nome falso</span> ';
        }
        $this->actingAs($owner)->postJson(route('quadros.comentarios.store', [$board, $card]), ['rich_body' => $html.'</p>'])->assertOk();
        $this->assertSame(1, $member->notifications()->count());
        $this->assertSame(0, $owner->notifications()->count());
        $this->assertSame(0, $pending->notifications()->count());
        $this->assertSame(0, $outsider->notifications()->count());
        $notification = $member->notifications()->firstOrFail();
        $this->assertSame(QuadroCommentMention::class, $notification->type);
        $this->assertSame($card->id, $notification->data['card_id']);
        $comment = $card->comentarios()->firstOrFail();
        $this->assertStringContainsString('@Pessoa marcada', $comment->body_html);
        $this->assertStringNotContainsString('data-mention-id="'.$outsider->id.'"', $comment->body_html);
    }

    public function test_opening_notifications_and_polling_preserve_unread_until_the_comment_is_viewed(): void
    {
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create();
        $member = User::factory()->create();
        $other = User::factory()->create();
        foreach ([$member, $other] as $person) {
            QuadroMembro::factory()->for($board)->for($person)->create(['status' => 'accepted', 'role' => 'viewer']);
        }
        $this->actingAs($owner)->postJson(route('quadros.comentarios.store', [$board, $card]), ['rich_body' => '<p><span data-mention-id="'.$member->id.'">Pessoa</span><span data-mention-id="'.$other->id.'">Outra</span></p>'])->assertOk();
        $comment = $card->comentarios()->firstOrFail();
        $notification = $member->notifications()->firstOrFail();
        $this->actingAs($member)->get(route('perfil', ['aba' => 'notificacoes']))->assertOk()->assertSee('mencionou você');
        $this->post(route('perfil.lembretes.read', $notification))->assertRedirect(route('quadros.show', $board, absolute: false).'#card='.$card->id.'&comment='.$comment->id);
        $this->assertNull($notification->fresh()->read_at);
        $html = $this->getJson(route('quadros.cartoes.show', [$board, $card]))->assertOk()->json('html');
        $this->assertStringContainsString('data-quadro-mention-unread', $html);
        $poll = $this->getJson(route('quadros.comentarios.index', [$board, $card]))->assertOk();
        $this->assertStringContainsString('data-quadro-mention-unread', $poll->json('html'));
        $this->assertNull($notification->fresh()->read_at);
        $this->getJson(route('perfil.notificacoes.count'))->assertJsonPath('count', 1);
        $response = $this->postJson(route('quadros.comentarios.read', [$board, $card, $comment]))->assertOk()->assertJsonPath('unread_count', 0);
        $this->assertStringNotContainsString('data-quadro-mention-unread', $response->json('html'));
        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertSame(1, $other->unreadNotifications()->count());
        $this->assertNotSame($poll->json('revision'), $response->json('revision'));
        $foreignCard = QuadroCartao::factory()->for($board)->create();
        $this->postJson(route('quadros.comentarios.read', [$board, $foreignCard, $comment]))->assertNotFound();
        $this->actingAs(User::factory()->create())->postJson(route('quadros.comentarios.read', [$board, $card, $comment]))->assertNotFound();
    }

    public function test_editing_a_comment_notifies_new_mentions_without_repeating_existing_notifications(): void
    {
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create();
        $member = User::factory()->create();
        QuadroMembro::factory()->for($board)->for($member)->create(['status' => 'accepted']);
        $this->actingAs($owner)->postJson(route('quadros.comentarios.store', [$board, $card]), ['body' => 'Mensagem inicial'])->assertOk();
        $comment = $card->comentarios()->firstOrFail();
        $rich = '<p><span data-mention-id="'.$member->id.'">Pessoa</span> resposta</p>';
        $this->patchJson(route('quadros.comentarios.update', [$board, $card, $comment]), ['rich_body' => $rich])->assertOk();
        $this->patchJson(route('quadros.comentarios.update', [$board, $card, $comment]), ['rich_body' => $rich.'<p>Editado</p>'])->assertOk();
        $this->assertSame(1, $member->notifications()->count());
        $this->assertNotNull($comment->fresh()->edited_at);
        $this->deleteJson(route('quadros.comentarios.destroy', [$board, $card, $comment]))->assertOk();
        $this->assertSame(0, $member->notifications()->count());
    }

    public function test_suggestions_expose_only_the_names_and_ids_of_people_with_access(): void
    {
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create();
        $member = User::factory()->create();
        $pending = User::factory()->create();
        QuadroMembro::factory()->for($board)->for($member)->create(['status' => 'accepted']);
        QuadroMembro::factory()->for($board)->for($pending)->create(['status' => 'pending']);
        $html = $this->actingAs($owner)->getJson(route('quadros.cartoes.show', [$board, $card]))->assertOk()->json('html');
        preg_match('/data-mention-people="([^"]*)"/', $html, $match);
        $people = json_decode(html_entity_decode($match[1], ENT_QUOTES), true);
        $this->assertSame([$owner->id, $member->id], array_column($people, 'id'));
        $this->assertSame(['id', 'name'], array_keys($people[0]));
    }
}
