<?php

namespace Tests\Feature;

use App\Models\ContaFinanceira;
use App\Models\Lancamento;
use App\Models\LancamentoFile;
use App\Models\TransferenciaFinanceira;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

    public function test_administrator_can_edit_account_details_without_changing_its_permissions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $other = User::factory()->create(['email' => 'Existing@Example.com']);

        $this->actingAs($admin)->get(route('admin.usuarios.edit', $user))->assertOk()->assertSee('Editar usuário');
        $this->from(route('admin.usuarios.edit', $user))->put(route('admin.usuarios.update', $user), [
            'name' => 'Novo nome', 'email' => strtolower($other->email),
        ])->assertSessionHasErrors(['email' => 'Esse e-mail já está em uso.']);
        $this->from(route('admin.usuarios.edit', $user))->followingRedirects()->put(route('admin.usuarios.update', $user), [
            'name' => 'Novo nome', 'email' => strtolower($other->email),
        ])->assertSee('id="usuario-email-error"', false);

        $this->put(route('admin.usuarios.update', $user), [
            'name' => 'Novo nome', 'email' => strtoupper($user->email), 'password' => '', 'is_admin' => true,
        ])->assertRedirect(route('admin.usuarios.index'));

        $this->assertSame('Novo nome', $user->fresh()->name);
        $this->assertSame($user->password, $user->fresh()->password);
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_profile_rejects_an_email_used_by_another_account_regardless_of_case(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['email' => 'Existing@Example.com']);

        $this->actingAs($user)->from('/perfil')->put('/perfil', [
            'name' => $user->name, 'apelido' => 'meu_apelido', 'email' => ' EXISTING@example.com ',
        ])->assertSessionHasErrors(['email' => 'Esse e-mail já está em uso.']);

        $this->from('/perfil')->followingRedirects()->put('/perfil', [
            'name' => $user->name, 'apelido' => 'meu_apelido', 'email' => ' EXISTING@example.com ',
        ])->assertSee('id="perfil-email-error"', false)->assertSee('Esse e-mail já está em uso.');
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_admin_can_delete_another_account_with_financial_records_and_attachments(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $account = ContaFinanceira::factory()->for($user)->create();
        $transfer = TransferenciaFinanceira::factory()->for($user)->create(['source_id' => $account->id]);
        $entry = Lancamento::factory()->for($user)->create(['account_id' => $account->id]);
        $file = LancamentoFile::factory()->for($entry)->create();
        Storage::disk('local')->put($file->path, 'Arquivo de exemplo');

        $this->actingAs($admin)->get(route('admin.usuarios.confirm-deletion', $user))
            ->assertOk()->assertSee('Excluir usuário')->assertSee($user->email);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->delete(route('admin.usuarios.destroy', $user))->assertRedirect(route('admin.usuarios.index'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('contas_financeiras', ['id' => $account->id]);
        $this->assertDatabaseMissing('transferencias_financeiras', ['id' => $transfer->id]);
        $this->assertDatabaseMissing('lancamentos', ['id' => $entry->id]);
        Storage::disk('local')->assertMissing($file->path);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_only_admins_can_edit_or_delete_accounts_and_cannot_delete_their_own(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.usuarios.edit', $admin))->assertForbidden();
        $this->put(route('admin.usuarios.update', $admin), [])->assertForbidden();
        $this->delete(route('admin.usuarios.destroy', $admin))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.usuarios.confirm-deletion', $admin))->assertForbidden();
        $this->delete(route('admin.usuarios.destroy', $admin))->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
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
