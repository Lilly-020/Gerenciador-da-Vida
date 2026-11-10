<?php

namespace Tests\Feature;

use App\Models\CategoriaFinanceira;
use App\Models\ContaFinanceira;
use App\Models\Investimento;
use App\Models\Lancamento;
use App\Models\Participacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinanceiroPlanningTest extends TestCase
{
    use RefreshDatabase;

    private function invite(User $owner, User $guest, array $extra = []): Participacao
    {
        return Participacao::create([
            'owner_id' => $owner->id, 'participant_id' => $guest->id, 'tipo' => 'financas', 'permission' => 'editar',
            'sections' => ['dashboard', 'entradas', 'saidas'], 'status' => 'aceito', ...$extra,
        ]);
    }

    public function test_recurring_bills_default_to_pending_and_obey_end_and_count(): void
    {
        $this->travelTo(now()->setDate(2026, 1, 1));
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/financeiro/lancamentos', [
            'type' => 'saida', 'category' => 'Moradia', 'description' => 'Aluguel', 'amount' => 1000, 'date' => '2026-01-31',
            'is_recurring_template' => true, 'recurrence_period' => 'mensal', 'recurrence_day' => 31,
            'recurrence_end' => '2026-04-30', 'recurrence_count' => 4,
        ])->assertOk();

        $template = Lancamento::where('is_recurring_template', true)->firstOrFail();
        $this->assertSame('previsto', $template->status);
        $this->assertSame(['2026-02-28', '2026-03-31', '2026-04-30'], $template->occurrences()->orderBy('date')->get()->map(fn ($row): string => $row->date->toDateString())->all());
        $template->generateUpcomingOccurrences();
        $this->assertSame(3, $template->occurrences()->count());
    }

    public function test_recurring_occurrences_keep_account_and_participant_shares(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $account = ContaFinanceira::factory()->for($owner)->create();
        $this->invite($owner, $guest);

        $this->actingAs($owner)->postJson('/financeiro/lancamentos', [
            'type' => 'saida', 'category' => 'Moradia', 'description' => 'Internet', 'amount' => 100, 'date' => today()->toDateString(),
            'account_id' => $account->id, 'partes' => [$guest->id => 40], 'is_recurring_template' => true,
            'recurrence_period' => 'mensal', 'recurrence_day' => 10, 'recurrence_count' => 2,
        ])->assertOk();

        $child = Lancamento::whereNotNull('parent_recurring_id')->with('partes')->firstOrFail();
        $this->assertSame($account->id, $child->account_id);
        $this->assertSame(40.0, $child->saidaFor($guest));
        $this->assertSame('previsto', $child->status);
    }

    public function test_installments_preserve_cents_and_month_end_anchor(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/financeiro/lancamentos', [
            'type' => 'saida', 'category' => 'Compras', 'description' => 'Compra', 'amount' => 100,
            'date' => '2026-01-31', 'installments' => 3, 'status' => 'realizado',
        ])->assertOk();

        $rows = Lancamento::orderBy('date')->get();
        $this->assertSame(['33.34', '33.33', '33.33'], $rows->pluck('amount')->all());
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], $rows->map(fn ($row): string => $row->date->toDateString())->all());
        $this->assertSame(['realizado', 'previsto', 'previsto'], $rows->pluck('status')->all());
        $this->assertSame(1, $rows->pluck('installment_group')->unique()->count());
    }

    public function test_shared_installment_rounding_never_makes_owner_share_negative(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $other = User::factory()->create();
        $this->invite($owner, $guest);
        $this->invite($owner, $other);

        $this->actingAs($owner)->postJson('/financeiro/lancamentos', [
            'type' => 'saida', 'category' => 'Compras', 'description' => 'Centavos', 'amount' => 0.02,
            'date' => today()->toDateString(), 'installments' => 2, 'partes' => [$guest->id => 0.01, $other->id => 0.01],
        ])->assertOk();

        $rows = Lancamento::with('partes')->get();
        $this->assertSame([0.0, 0.0], $rows->map(fn ($row): float => $row->parteOf($owner))->all());
        $this->assertSame(0.01, $rows->sum(fn ($row): float => $row->parteOf($guest)));
        $this->assertSame(0.01, $rows->sum(fn ($row): float => $row->parteOf($other)));
    }

    public function test_accounts_transfers_and_investments_affect_cash_once(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $bank = ContaFinanceira::factory()->for($user)->create(['opening_balance' => 1000]);
        $cash = ContaFinanceira::factory()->for($user)->create();
        Lancamento::factory()->for($user)->entrada()->create(['account_id' => $bank->id, 'amount' => 500]);
        Lancamento::factory()->for($user)->saida()->create(['account_id' => $bank->id, 'amount' => 100]);
        Lancamento::factory()->for($user)->saida()->previsto()->create(['account_id' => $bank->id, 'amount' => 80]);
        $investment = Investimento::factory()->for($user)->create(['rate' => 0]);
        $investment->aportes()->create(['amount' => 200, 'date' => today(), 'account_id' => $bank->id]);
        $investment->aportes()->create(['amount' => 50, 'date' => today(), 'kind' => 'resgate', 'account_id' => $bank->id]);

        $this->actingAs($user)->post('/financeiro/transferencias', ['source_id' => $bank->id, 'destination_id' => $cash->id, 'amount' => 300, 'date' => today()->toDateString()])->assertRedirect();

        $this->assertSame(950.0, $bank->balanceAsOf());
        $this->assertSame(300.0, $cash->balanceAsOf());
        $this->get('/financeiro')->assertViewHas('cards', fn (array $cards): bool => $cards['saldo_disponivel'] === 1250.0 && $cards['a_pagar'] === 80.0 && $cards['saldo_projetado'] === 1170.0 && $cards['investido'] === 150.0);
        $this->get('/financeiro/contas')->assertSee('Transferir entre contas');
        $this->assertDatabaseCount('lancamentos', 3);
    }

    public function test_opening_balance_does_not_count_older_account_transactions_twice(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 8));
        $user = User::factory()->create();
        $account = ContaFinanceira::factory()->for($user)->create(['opening_balance' => 1000, 'opening_date' => '2026-11-01']);
        Lancamento::factory()->for($user)->entrada()->create(['amount' => 999, 'date' => '2026-10-31', 'account_id' => $account->id]);
        Lancamento::factory()->for($user)->entrada()->create(['amount' => 100, 'date' => '2026-11-02', 'account_id' => $account->id]);

        $this->actingAs($user)->get('/financeiro')->assertViewHas('cards', fn (array $cards): bool => $cards['saldo_disponivel'] === 1100.0);
    }

    public function test_pending_overdue_and_cancelled_statuses(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 8));
        $user = User::factory()->create();
        $bill = Lancamento::factory()->for($user)->saida()->previsto()->create(['date' => '2026-11-01', 'amount' => 100]);

        $this->actingAs($user)->get('/financeiro/saidas')->assertSee('Atrasado');
        $this->patchJson(route('financeiro.lancamentos.update', $bill), ['status' => 'cancelado'])->assertOk();

        $this->assertSame('Cancelado', $bill->fresh()->situation);
        $this->get('/financeiro')->assertViewHas('cards', fn (array $cards): bool => $cards['a_pagar'] === 0.0);
    }

    public function test_private_receipt_access_respects_the_owner(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $row = Lancamento::factory()->for($owner)->create();

        $this->actingAs($owner)->post(route('financeiro.comprovantes.store', $row), ['file' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf')])->assertRedirect();

        $file = $row->files()->firstOrFail();
        Storage::disk('local')->assertExists($file->path);
        $this->get(route('financeiro.comprovantes.download', [$row, $file]))->assertDownload('receipt.pdf');
        $this->actingAs(User::factory()->create())->get(route('financeiro.comprovantes.download', [$row, $file]))->assertNotFound();
    }

    public function test_account_category_and_entry_sharing_scopes_are_applied_to_reads_and_writes(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $account = ContaFinanceira::factory()->for($owner)->create();
        $shown = Lancamento::factory()->for($owner)->saida()->create(['description' => 'Casa compartilhada', 'category' => 'Moradia', 'account_id' => $account->id]);
        $hidden = Lancamento::factory()->for($owner)->saida()->create(['description' => 'Particular', 'category' => 'Lazer']);
        $this->invite($owner, $guest, ['allowed_accounts' => [$account->id], 'allowed_categories' => ['Moradia'], 'allowed_lancamentos' => [$shown->id]]);

        $this->actingAs($guest)->withSession(['carteira_owner_id' => $owner->id])->get('/financeiro/saidas')->assertSee('Casa compartilhada')->assertDontSee('Particular');
        $this->patchJson(route('financeiro.lancamentos.update', $hidden), ['status' => 'previsto'])->assertNotFound();
        $this->patchJson(route('financeiro.lancamentos.update', $shown), ['category' => 'Lazer'])->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->get('/financeiro/relatorio')->assertDontSee('Particular');
    }

    public function test_viewers_cannot_transfer_and_unaccepted_invites_have_no_access(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $account = ContaFinanceira::factory()->for($owner)->create();
        $invite = $this->invite($owner, $guest, ['permission' => 'ver']);

        $this->actingAs($guest)->withSession(['carteira_owner_id' => $owner->id])->post('/financeiro/transferencias', ['source_id' => $account->id])->assertForbidden();
        $invite->update(['status' => 'pendente']);
        $this->get('/financeiro/contas')->assertDontSee($account->name);
    }

    public function test_investment_estimates_support_cdi_ipca_and_explicit_deductions(): void
    {
        $user = User::factory()->create();
        $cdi = Investimento::factory()->for($user)->create(['rate_mode' => 'cdi', 'rate' => 100, 'reference_rate' => 10]);
        $ipca = Investimento::factory()->for($user)->create(['rate_mode' => 'ipca', 'rate' => 6, 'reference_rate' => 4]);
        $fixed = Investimento::factory()->for($user)->create(['rate' => 10, 'rate_period' => 'anual', 'ir_rate' => 20, 'iof_rate' => 0, 'estimated_fees' => 5]);
        $fixed->aportes()->create(['amount' => 1000, 'date' => today()->subDays(365)]);

        $this->actingAs($user)->get('/financeiro/investimentos')->assertSee('Valor líquido estimado');

        $this->assertEqualsWithDelta(10, $cdi->effectiveRate(), 0.000001);
        $this->assertEqualsWithDelta(10.24, $ipca->effectiveRate(), 0.000001);
        $this->assertSame(1075.0, $fixed->fresh('aportes')->estimativaLiquida());
    }

    public function test_withdrawals_preserve_original_contribution_and_reject_excess(): void
    {
        $user = User::factory()->create();
        $investment = Investimento::factory()->for($user)->create(['rate' => 0]);
        $investment->aportes()->create(['amount' => 1000, 'date' => today()]);

        $this->actingAs($user)->post(route('financeiro.investimentos.aportes.store', $investment), ['amount' => 300, 'date' => today()->toDateString(), 'kind' => 'resgate'])->assertRedirect();

        $this->assertSame(1000.0, $investment->fresh('aportes')->totalAportado());
        $this->assertSame(700.0, $investment->fresh('aportes')->patrimonioEstimado());
        $this->postJson(route('financeiro.investimentos.aportes.store', $investment), ['amount' => 701, 'date' => today()->toDateString(), 'kind' => 'resgate'])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->assertDatabaseCount('investimento_aportes', 2);
    }

    public function test_categories_can_be_renamed_without_detaching_existing_records(): void
    {
        $user = User::factory()->create();
        $category = CategoriaFinanceira::factory()->for($user)->create(['name' => 'Casa']);
        $row = Lancamento::factory()->for($user)->saida()->create(['category' => 'Casa']);

        $this->actingAs($user)->put(route('financeiro.categorias.update', $category), ['name' => 'Moradia da família'])->assertRedirect();

        $this->assertSame('Moradia da família', $row->fresh()->category);
        $this->get('/financeiro/saidas')->assertSee('Moradia da família');
    }

    public function test_investment_section_is_not_exposed_by_a_dashboard_only_invite(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        Investimento::factory()->for($owner)->create(['name' => 'Investimento privado']);
        $this->invite($owner, $guest, ['sections' => ['dashboard']]);

        $this->actingAs($guest)->withSession(['carteira_owner_id' => $owner->id])->get('/financeiro')->assertDontSee('Investimentos acumulados')->assertViewHas('cards', fn (array $cards): bool => (float) $cards['patrimonio_investido'] === 0.0);
    }

    public function test_scheduled_generation_is_idempotent_and_can_be_paused(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 8));
        $user = User::factory()->create();
        $template = Lancamento::factory()->for($user)->create([
            'is_recurring_template' => true, 'recurrence_period' => 'mensal', 'recurrence_day' => 15,
            'date' => '2026-11-15', 'recurrence_count' => 4,
        ]);

        $this->artisan('financeiro:recorrencias')->assertSuccessful();
        $this->artisan('financeiro:recorrencias')->assertSuccessful();

        $this->assertSame(3, $template->occurrences()->count());
        $template->update(['recurrence_active' => false, 'recurrence_count' => 10]);
        $this->artisan('financeiro:recorrencias')->assertSuccessful();
        $this->assertSame(3, $template->occurrences()->count());
    }

    public function test_withdrawals_cannot_overdraw_later_movements(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 8));
        $owner = User::factory()->create();
        $investment = Investimento::factory()->for($owner)->create(['rate' => 0]);
        $investment->aportes()->create(['amount' => 1000, 'date' => '2026-01-01']);
        $investment->aportes()->create(['amount' => 600, 'date' => '2026-02-01', 'kind' => 'resgate']);

        $this->actingAs($owner)->postJson(route('financeiro.investimentos.aportes.store', $investment), ['amount' => 500, 'date' => '2026-01-15', 'kind' => 'resgate'])->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->assertDatabaseCount('investimento_aportes', 2);
    }

    public function test_initial_investment_and_reported_value_are_stored_separately(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('financeiro.investimentos.store'), [
            'name' => 'Reserva', 'type' => 'cdb', 'rate' => 0, 'rate_period' => 'anual',
            'initial_amount' => 1000, 'initial_date' => today()->toDateString(),
            'reported_value' => 1020, 'reported_at' => today()->toDateString(),
        ])->assertRedirect();

        $investment = Investimento::firstOrFail();
        $this->assertSame(1000.0, $investment->totalAportado());
        $this->assertSame(1000.0, $investment->patrimonioEstimado());
        $this->assertSame('1020.00', $investment->reported_value);
        $this->get('/financeiro/investimentos')->assertSee('Extrato em');
    }

    public function test_an_administrator_can_manage_accounts_and_owner_can_revoke_access(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $invite = $this->invite($owner, $admin, ['permission' => 'admin']);

        $this->actingAs($admin)->withSession(['carteira_owner_id' => $owner->id])->post('/financeiro/contas', [
            'name' => 'Conta da casa', 'kind' => 'banco', 'opening_balance' => 100, 'opening_date' => today()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('contas_financeiras', ['name' => 'Conta da casa', 'user_id' => $owner->id]);
        $this->actingAs($owner)->withSession(['carteira_owner_id' => null])->put(route('participacoes.financeiro.update', $invite), ['permission' => 'ver', 'sections' => ['saidas']])->assertRedirect();
        $this->assertSame('ver', $invite->fresh()->permission);
        $this->actingAs($admin)->withSession(['carteira_owner_id' => $owner->id])->post('/financeiro/contas', ['name' => 'Blocked'])->assertForbidden();
    }

    public function test_editors_cannot_escalate_their_financial_access(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $invite = $this->invite($owner, $editor);

        $this->actingAs($editor)->withSession(['carteira_owner_id' => $owner->id])->putJson(route('participacoes.financeiro.update', $invite), ['permission' => 'admin', 'sections' => ['investimentos']])->assertNotFound();

        $this->assertSame('editar', $invite->fresh()->permission);
    }

    public function test_comparative_report_uses_previous_month_realized_amounts(): void
    {
        $owner = User::factory()->create();
        Lancamento::factory()->for($owner)->entrada()->create(['amount' => 1000, 'date' => '2026-10-01']);
        Lancamento::factory()->for($owner)->entrada()->create(['amount' => 1500, 'date' => '2026-11-01']);

        $this->actingAs($owner)->get('/financeiro/relatorio?mes=2026-11&comparar=1')->assertSee('Comparativo de valores realizados')->assertViewHas('comparison', fn (array $comparison): bool => $comparison['entrada'] === 1000.0);
    }

    public function test_transfers_reject_foreign_or_identical_accounts(): void
    {
        $owner = User::factory()->create();
        $ownAccount = ContaFinanceira::factory()->for($owner)->create();
        $foreignAccount = ContaFinanceira::factory()->create();

        $this->actingAs($owner)->postJson('/financeiro/transferencias', ['source_id' => $ownAccount->id, 'destination_id' => $foreignAccount->id, 'amount' => 100, 'date' => today()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('destination_id');
        $this->postJson('/financeiro/transferencias', ['source_id' => $ownAccount->id, 'destination_id' => $ownAccount->id, 'amount' => 100, 'date' => today()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('destination_id');
        $this->assertDatabaseCount('transferencias_financeiras', 0);
    }

    public function test_investment_history_is_independent_from_dashboard_period(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 8));
        $owner = User::factory()->create();
        $investment = Investimento::factory()->for($owner)->create(['rate' => 0]);
        $investment->aportes()->create(['amount' => 1000, 'date' => '2026-01-01']);

        $this->actingAs($owner)->get('/financeiro?period=mes_atual')->assertViewHas('cards', fn (array $cards): bool => $cards['investido'] === 0.0 && $cards['patrimonio_investido'] === 1000.0);
    }

    public function test_user_can_invite_a_financial_administrator_with_account_limits(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create(['apelido' => 'financeguest']);
        $account = ContaFinanceira::factory()->for($owner)->create();

        $this->actingAs($owner)->post(route('participacoes.store'), [
            'tipo' => 'financas', 'apelido' => 'financeguest', 'permission' => 'admin',
            'sections' => ['entradas', 'saidas'], 'allowed_accounts' => [$account->id],
        ])->assertRedirect();

        $invite = Participacao::where('participant_id', $guest->id)->firstOrFail();
        $this->assertSame('pendente', $invite->status);
        $this->assertSame('admin', $invite->permission);
        $this->assertSame([$account->id], $invite->allowed_accounts);
        $this->get('/financeiro/participantes')->assertSee('Editar acesso');
    }

    public function test_removing_contributions_cannot_leave_withdrawals_without_balance(): void
    {
        $owner = User::factory()->create();
        $investment = Investimento::factory()->for($owner)->create(['rate' => 0]);
        $contribution = $investment->aportes()->create(['amount' => 1000, 'date' => today()]);
        $investment->aportes()->create(['amount' => 100, 'date' => today(), 'kind' => 'resgate']);

        $this->actingAs($owner)->deleteJson(route('financeiro.investimentos.aportes.destroy', [$investment, $contribution]))->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->assertModelExists($contribution);
    }

    public function test_estimated_yield_stops_at_maturity(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 8));
        $owner = User::factory()->create();
        $investment = Investimento::factory()->for($owner)->create(['rate' => 10, 'rate_period' => 'anual', 'maturity_date' => '2026-01-01']);
        $investment->aportes()->create(['amount' => 1000, 'date' => '2025-01-01']);

        $this->actingAs($owner)->get('/financeiro/investimentos')->assertOk();

        $this->assertSame(100.0, $investment->fresh('aportes')->rendimentoEstimado());
    }
}
