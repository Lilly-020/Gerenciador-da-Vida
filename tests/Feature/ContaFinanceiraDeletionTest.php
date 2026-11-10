<?php

namespace Tests\Feature;

use App\Models\ContaFinanceira;
use App\Models\Investimento;
use App\Models\InvestimentoAporte;
use App\Models\Lancamento;
use App\Models\Participacao;
use App\Models\TransferenciaFinanceira;
use App\Models\User;
use App\Support\Carteira;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContaFinanceiraDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_account_can_be_deleted_and_disappears_from_accounts(): void
    {
        $user = User::factory()->create();
        $account = ContaFinanceira::factory()->for($user)->create(['name' => 'Conta para excluir']);

        $this->actingAs($user)->deleteJson('/financeiro/contas/'.$account->id)->assertOk();

        $this->assertSoftDeleted($account);
        $this->get('/financeiro/contas')->assertViewHas('accounts', fn ($accounts): bool => $accounts->doesntContain('id', $account->id));
    }

    public function test_money_must_leave_before_deletion(): void
    {
        $user = User::factory()->create();
        $account = ContaFinanceira::factory()->for($user)->create(['opening_balance' => 1790.67]);

        $this->actingAs($user)->deleteJson('/financeiro/contas/'.$account->id)
            ->assertUnprocessable()->assertJsonValidationErrors(['conta'])
            ->assertJsonPath('errors.conta.0', 'Esta conta só pode ser excluída quando todo o dinheiro tiver saído. Transfira ou retire o saldo antes de excluir.');

        $this->assertNotSoftDeleted($account);
    }

    public function test_balance_includes_realized_entries(): void
    {
        $user = User::factory()->create();
        $account = ContaFinanceira::factory()->for($user)->create();
        Lancamento::factory()->for($user)->create([
            'account_id' => $account->id, 'type' => 'entrada', 'status' => 'realizado',
            'amount' => 0.01, 'date' => today(),
        ]);

        $this->actingAs($user)->deleteJson('/financeiro/contas/'.$account->id)
            ->assertUnprocessable()->assertJsonValidationErrors(['conta']);
        $this->assertNotSoftDeleted($account);
    }

    public function test_negative_balance_also_blocks_deletion(): void
    {
        $user = User::factory()->create();
        $account = ContaFinanceira::factory()->for($user)->create(['opening_balance' => -0.01]);

        $this->actingAs($user)->deleteJson('/financeiro/contas/'.$account->id)
            ->assertUnprocessable()->assertJsonValidationErrors(['conta']);
        $this->assertNotSoftDeleted($account);
    }

    public function test_zero_balance_after_transfer_can_be_deleted_without_losing_history(): void
    {
        $user = User::factory()->create();
        $source = ContaFinanceira::factory()->for($user)->create(['name' => 'Banco antigo', 'opening_balance' => 100]);
        $destination = ContaFinanceira::factory()->for($user)->create();
        $transfer = TransferenciaFinanceira::factory()->for($user)->create([
            'source_id' => $source->id, 'destination_id' => $destination->id,
            'amount' => 100, 'date' => today(),
        ]);

        $this->actingAs($user)->deleteJson('/financeiro/contas/'.$source->id)->assertOk();

        $this->assertSoftDeleted($source);
        $this->assertModelExists($transfer);
        $this->assertSame('Banco antigo', $transfer->fresh()->source->name);
        $this->assertSame(100.0, $destination->balanceAsOf());
        $this->get('/financeiro')->assertOk()->assertViewHas('cards', fn (array $cards): bool => $cards['saldo_disponivel'] === 100.0);
    }

    public function test_other_users_cannot_delete_an_account(): void
    {
        $account = ContaFinanceira::factory()->create();
        $this->actingAs(User::factory()->create())->deleteJson('/financeiro/contas/'.$account->id)->assertNotFound();
        $this->assertNotSoftDeleted($account);
    }

    public function test_deleted_accounts_cannot_receive_transfers(): void
    {
        $user = User::factory()->create();
        $source = ContaFinanceira::factory()->for($user)->create();
        $destination = ContaFinanceira::factory()->for($user)->create();
        $this->actingAs($user)->deleteJson('/financeiro/contas/'.$destination->id)->assertOk();

        $this->postJson('/financeiro/transferencias', [
            'source_id' => $source->id, 'destination_id' => $destination->id,
            'amount' => 10, 'date' => today()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['destination_id']);
        $this->assertDatabaseCount('transferencias_financeiras', 0);
    }

    public function test_pending_entries_require_an_active_account_before_confirmation(): void
    {
        $user = User::factory()->create();
        $account = ContaFinanceira::factory()->for($user)->create();
        $entry = Lancamento::factory()->for($user)->create([
            'account_id' => $account->id, 'type' => 'entrada', 'status' => 'previsto', 'amount' => 50,
        ]);
        $this->actingAs($user)->deleteJson('/financeiro/contas/'.$account->id)->assertOk();

        $this->patchJson('/financeiro/lancamentos/'.$entry->id, ['status' => 'realizado'])
            ->assertUnprocessable()->assertJsonValidationErrors(['account_id']);
        $this->assertSame('previsto', $entry->fresh()->status);
    }

    public function test_mistaken_account_and_all_linked_movements_are_deleted_after_confirmation(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $account = ContaFinanceira::factory()->for($user)->create(['opening_balance' => 500]);
        $other = ContaFinanceira::factory()->for($user)->create();
        $entry = Lancamento::factory()->for($user)->create([
            'account_id' => $account->id, 'amount' => 50, 'type' => 'entrada', 'status' => 'realizado', 'date' => today(),
        ]);
        $entry->syncPartes([]);
        Storage::disk('local')->put('test/receipt.pdf', 'receipt');
        $file = $entry->files()->create(['path' => 'test/receipt.pdf', 'original_name' => 'receipt.pdf', 'size' => 7]);
        $unrelated = Lancamento::factory()->for($user)->create(['account_id' => $other->id]);
        $transfer = TransferenciaFinanceira::factory()->for($user)->create([
            'source_id' => $account->id, 'destination_id' => $other->id, 'amount' => 100, 'date' => today(),
        ]);
        $investment = Investimento::factory()->for($user)->create();
        $contribution = InvestimentoAporte::factory()->for($investment, 'investimento')->create([
            'account_id' => $account->id, 'amount' => 20,
        ]);
        $this->actingAs($user);
        $impact = $this->getJson('/financeiro/contas/'.$account->id.'/exclusao')->assertOk()
            ->assertJsonPath('entries', 1)->assertJsonPath('transfers', 1)
            ->assertJsonPath('investments', 1)->assertJsonPath('attachments', 1)->json();

        $this->deleteJson('/financeiro/contas/'.$account->id, [
            'delete_movements' => true, 'confirm_mistake' => true, 'impact_token' => $impact['token'],
        ])->assertOk();

        $this->assertDatabaseMissing('contas_financeiras', ['id' => $account->id]);
        $this->assertModelMissing($entry);
        $this->assertModelMissing($file);
        $this->assertModelMissing($transfer);
        $this->assertModelMissing($contribution);
        $this->assertModelExists($investment);
        $this->assertModelExists($other);
        $this->assertModelExists($unrelated);
        Storage::disk('local')->assertMissing('test/receipt.pdf');
    }

    public function test_mistaken_account_deletion_requires_explicit_confirmation(): void
    {
        $user = User::factory()->create();
        $account = ContaFinanceira::factory()->for($user)->create(['opening_balance' => 500]);
        $this->actingAs($user)->deleteJson('/financeiro/contas/'.$account->id, ['delete_movements' => true])
            ->assertUnprocessable()->assertJsonValidationErrors(['confirm_mistake', 'impact_token']);
        $this->assertNotSoftDeleted($account);
    }

    public function test_changed_movements_require_a_new_confirmation(): void
    {
        $user = User::factory()->create();
        $account = ContaFinanceira::factory()->for($user)->create();
        $this->actingAs($user);
        $token = $this->getJson('/financeiro/contas/'.$account->id.'/exclusao')->assertOk()->json('token');
        $entry = Lancamento::factory()->for($user)->create(['account_id' => $account->id]);

        $this->deleteJson('/financeiro/contas/'.$account->id, [
            'delete_movements' => true, 'confirm_mistake' => true, 'impact_token' => $token,
        ])->assertUnprocessable()->assertJsonValidationErrors(['conta']);
        $this->assertModelExists($entry);
        $this->assertNotSoftDeleted($account);
    }

    public function test_deletion_rolls_back_if_it_would_leave_an_investment_with_unfunded_withdrawals(): void
    {
        $user = User::factory()->create();
        $account = ContaFinanceira::factory()->for($user)->create();
        $investment = Investimento::factory()->for($user)->create(['rate' => 0]);
        $contribution = InvestimentoAporte::factory()->for($investment, 'investimento')->create([
            'account_id' => $account->id, 'amount' => 100,
        ]);
        InvestimentoAporte::factory()->for($investment, 'investimento')->create([
            'kind' => 'resgate', 'amount' => 50, 'account_id' => null,
        ]);
        $this->actingAs($user);
        $token = $this->getJson('/financeiro/contas/'.$account->id.'/exclusao')->assertOk()->json('token');

        $this->deleteJson('/financeiro/contas/'.$account->id, [
            'delete_movements' => true, 'confirm_mistake' => true, 'impact_token' => $token,
        ])->assertUnprocessable()->assertJsonValidationErrors(['conta']);
        $this->assertNotSoftDeleted($account);
        $this->assertModelExists($contribution);
    }

    public function test_other_users_cannot_inspect_or_purge_account_movements(): void
    {
        $account = ContaFinanceira::factory()->create();
        $this->actingAs(User::factory()->create());
        $this->getJson('/financeiro/contas/'.$account->id.'/exclusao')->assertNotFound();
        $this->deleteJson('/financeiro/contas/'.$account->id, [
            'delete_movements' => true, 'confirm_mistake' => true, 'impact_token' => str_repeat('a', 64),
        ])->assertNotFound();
        $this->assertNotSoftDeleted($account);
    }

    public function test_editors_cannot_preview_or_purge_an_account(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $account = ContaFinanceira::factory()->for($owner)->create();
        Participacao::create([
            'owner_id' => $owner->id, 'participant_id' => $editor->id, 'tipo' => 'financas',
            'permission' => 'editar', 'status' => 'aceito', 'sections' => array_keys(Participacao::SECOES),
        ]);
        $this->actingAs($editor)->withSession([Carteira::SESSION_KEY => $owner->id]);

        $this->getJson('/financeiro/contas/'.$account->id.'/exclusao')->assertForbidden();
        $this->deleteJson('/financeiro/contas/'.$account->id, [
            'delete_movements' => true, 'confirm_mistake' => true, 'impact_token' => str_repeat('a', 64),
        ])->assertForbidden();
        $this->assertNotSoftDeleted($account);
    }
}
