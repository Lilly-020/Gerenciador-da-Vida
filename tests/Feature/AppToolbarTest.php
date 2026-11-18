<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SocialNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppToolbarTest extends TestCase
{
    use RefreshDatabase;

    public function test_toolbar_contains_theme_profile_and_bell_and_notifications_are_no_longer_in_profile(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('perfil'))->assertOk()->assertSee('data-toggle-theme', false)->assertSee('data-notifications-toggle', false)->assertSee($user->name)->assertDontSee('Lembretes e menções');
        $this->get(route('perfil', ['aba' => 'amigos']))->assertOk()->assertSee('Adicionar pelo apelido');
    }

    public function test_bell_lists_only_the_current_users_notifications_and_opening_is_scoped(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $user->notify(new SocialNotification(['kind' => 'friend_request', 'actor' => 'Amigo', 'title' => 'Pedido de amizade', 'friendship_id' => 123]));
        $other->notify(new SocialNotification(['kind' => 'friend_request', 'actor' => 'Privado', 'title' => 'Não pode aparecer', 'friendship_id' => 456]));
        $response = $this->actingAs($user)->getJson(route('notificacoes.index'))->assertOk()->assertJsonPath('count', 1);
        $this->assertStringContainsString('Amigo', $response->json('html'));
        $this->assertStringNotContainsString('Não pode aparecer', $response->json('html'));
        $this->post(route('notificacoes.open', $other->notifications()->firstOrFail()))->assertNotFound();
    }
}
