<?php

namespace Tests\Feature;

use App\Models\ContaFinanceira;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContaFinanceiraTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_account_shows_guidance_instead_of_a_transfer_form(): void
    {
        $user = User::factory()->create();
        ContaFinanceira::factory()->for($user)->create();

        $this->actingAs($user)->get('/financeiro/contas')
            ->assertSee('Você precisa de pelo menos duas contas')
            ->assertDontSee('data-account-transfer', false)
            ->assertSee('Adicionar outra conta');
    }

    public function test_two_accounts_show_a_transfer_form_with_unselected_accounts(): void
    {
        $user = User::factory()->create();
        ContaFinanceira::factory()->for($user)->count(2)->create();

        $this->actingAs($user)->get('/financeiro/contas')
            ->assertSee('data-account-transfer', false)
            ->assertSee('Selecione uma conta')
            ->assertSee('De qual conta?')
            ->assertSee('Para qual conta?');
    }

    public function test_same_account_error_is_in_portuguese_and_preserves_the_form(): void
    {
        $user = User::factory()->create();
        $account = ContaFinanceira::factory()->for($user)->create();

        $this->actingAs($user)->from('/financeiro/contas')->post('/financeiro/transferencias', [
            'source_id' => $account->id, 'destination_id' => $account->id,
            'amount' => '25.00', 'date' => today()->toDateString(),
        ])->assertRedirect('/financeiro/contas')
            ->assertSessionHasErrors(['destination_id' => 'Escolha uma conta de destino diferente da origem.'])
            ->assertSessionHasInput('amount', '25.00');

        $this->assertDatabaseCount('transferencias_financeiras', 0);
    }

    public function test_date_before_opening_balance_returns_a_form_error(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 10));
        $user = User::factory()->create();
        $source = ContaFinanceira::factory()->for($user)->create(['opening_date' => '2026-11-01']);
        $destination = ContaFinanceira::factory()->for($user)->create(['opening_date' => '2026-11-05']);

        $this->actingAs($user)->from('/financeiro/contas')->post('/financeiro/transferencias', [
            'source_id' => $source->id, 'destination_id' => $destination->id,
            'amount' => 25, 'date' => '2026-11-02',
        ])->assertRedirect('/financeiro/contas')->assertSessionHasErrors([
            'date' => 'Escolha uma data igual ou posterior ao saldo inicial das duas contas.',
        ]);

        $this->assertDatabaseCount('transferencias_financeiras', 0);
    }
}
