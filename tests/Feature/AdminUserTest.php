<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_user_management(): void
    {
        $this->get('/admin/usuarios')->assertRedirect(route('login'));
        $this->post('/admin/usuarios', [])->assertRedirect(route('login'));
    }

    public function test_regular_users_cannot_view_or_create_users_even_with_the_admin_email(): void
    {
        $user = User::factory()->create(['email' => 'lillycardoso02@gmail.com']);

        $this->actingAs($user)->get('/admin/usuarios')->assertForbidden();
        $this->post('/admin/usuarios', ['name' => 'Intruso', 'email' => 'intruso@example.com', 'password' => 'password123', 'password_confirmation' => 'password123', 'is_admin' => true])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'intruso@example.com']);
        $this->get('/')->assertDontSee('href="/admin/usuarios"', false);
    }

    public function test_administrator_can_create_a_regular_user_without_changing_their_own_session(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/usuarios')->assertOk()->assertSee('Criar usuário');

        $response = $this->post('/admin/usuarios', [
            'name' => 'Novo usuário', 'email' => ' NOVO@example.com ',
            'password' => 'password123', 'password_confirmation' => 'password123', 'is_admin' => true,
        ]);

        $response->assertRedirect(route('admin.usuarios.index'))->assertSessionHas('status', 'Usuário criado com sucesso.');
        $created = User::where('email', 'novo@example.com')->firstOrFail();
        $this->assertSame('Novo usuário', $created->name);
        $this->assertFalse($created->is_admin);
        $this->assertTrue(Hash::check('password123', $created->password));
        $this->assertAuthenticatedAs($admin);
        $this->get('/admin/usuarios')->assertSee('novo@example.com')->assertDontSee('password123');
    }

    public function test_invalid_user_data_is_rejected_without_creating_an_account(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/usuarios', [])->assertSessionHasErrors(['name', 'email', 'password']);
        $this->post('/admin/usuarios', ['name' => 'Teste', 'email' => $admin->email, 'password' => 'short', 'password_confirmation' => 'different'])
            ->assertSessionHasErrors(['email', 'password']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_profile_update_cannot_grant_administrator_permissions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/perfil', [
            'name' => 'Usuário', 'email' => 'lillycardoso02@gmail.com', 'apelido' => 'comum', 'is_admin' => true,
        ])->assertSessionHasNoErrors();

        $this->assertFalse($user->fresh()->is_admin);
        $this->get('/admin/usuarios')->assertForbidden();
    }
}
