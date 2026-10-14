<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PerfilTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_the_profile(): void
    {
        $this->get('/perfil')->assertRedirect(route('login'));
    }

    public function test_the_profile_page_shows_the_users_data(): void
    {
        $user = User::factory()->create(['name' => 'Lilly Souza', 'apelido' => 'lilly']);

        $this->actingAs($user)->get('/perfil')
            ->assertOk()
            ->assertSee('Lilly Souza')
            ->assertSee('Alterar senha');
    }

    public function test_user_can_update_name_apelido_and_email(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/perfil', [
            'name' => 'Lilly Souza', 'apelido' => 'Lilly_020', 'email' => 'lilly@example.com',
        ]);

        $response->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertSame('Lilly Souza', $user->name);
        $this->assertSame('Lilly_020', $user->apelido);
        $this->assertSame('lilly_020', $user->apelido_key);
        $this->assertSame('lilly@example.com', $user->email);
    }

    public function test_an_apelido_is_unique_regardless_of_case(): void
    {
        User::factory()->create(['apelido' => 'Lilly']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/perfil', [
            'name' => $user->name, 'apelido' => 'lILLY', 'email' => $user->email,
        ]);

        $response->assertSessionHasErrors('apelido');
        $this->assertNull($user->fresh()->apelido);
    }

    public function test_a_user_can_change_the_case_of_their_own_apelido(): void
    {
        $user = User::factory()->create(['apelido' => 'lilly']);

        $this->actingAs($user)->put('/perfil', [
            'name' => $user->name, 'apelido' => 'Lilly', 'email' => $user->email,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Lilly', $user->fresh()->apelido);
    }

    public function test_an_apelido_only_accepts_letters_numbers_dot_and_underscore(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/perfil', [
            'name' => $user->name, 'apelido' => 'li lly!', 'email' => $user->email,
        ])->assertSessionHasErrors('apelido');
    }

    public function test_an_email_already_in_use_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $this->actingAs($user)->put('/perfil', [
            'name' => $user->name, 'apelido' => 'lilly', 'email' => 'taken@example.com',
        ])->assertSessionHasErrors('email');
    }

    public function test_changing_the_password_requires_the_current_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/perfil/senha', [
            'current_password' => 'wrong', 'password' => 'nova-senha-123', 'password_confirmation' => 'nova-senha-123',
        ])->assertSessionHasErrorsIn('senha', 'current_password');

        $this->actingAs($user)->put('/perfil/senha', [
            'current_password' => 'password', 'password' => 'nova-senha-123', 'password_confirmation' => 'nova-senha-123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('nova-senha-123', $user->fresh()->password));
    }

    public function test_users_are_found_by_the_start_of_their_apelido_ignoring_case(): void
    {
        $user = User::factory()->create(['apelido' => 'lilly']);
        User::factory()->create(['apelido' => 'Joao_Silva', 'name' => 'João']);
        User::factory()->create(['apelido' => 'maria']);

        $this->actingAs($user)->getJson('/usuarios/buscar?q=JOA')
            ->assertOk()
            ->assertExactJson([['apelido' => 'Joao_Silva', 'name' => 'João']]);

        // Never suggests the user themself, nor matches on a single letter.
        $this->actingAs($user)->getJson('/usuarios/buscar?q=lil')->assertExactJson([]);
        $this->actingAs($user)->getJson('/usuarios/buscar?q=m')->assertExactJson([]);
    }
}
