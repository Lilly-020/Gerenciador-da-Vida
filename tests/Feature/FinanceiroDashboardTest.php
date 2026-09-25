<?php

namespace Tests\Feature;

use App\Models\Investimento;
use App\Models\InvestimentoAporte;
use App\Models\Lancamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceiroDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/financeiro');

        $response->assertOk();
        $response->assertSee('Financeiro');
        $response->assertSee('Entradas');
        $response->assertSee('Saídas');
        $response->assertSee('Saldo disponível');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/financeiro');

        $response->assertRedirect(route('login'));
    }

    public function test_cards_reflect_realizado_lancamentos_in_the_current_month(): void
    {
        $user = User::factory()->create();
        Lancamento::factory()->for($user)->entrada()->onDate(today()->toDateString())->create(['amount' => 5000]);
        Lancamento::factory()->for($user)->saida()->onDate(today()->toDateString())->create(['amount' => 2300]);
        // Previsto shouldn't count.
        Lancamento::factory()->for($user)->entrada()->previsto()->onDate(today()->toDateString())->create(['amount' => 9999]);

        $response = $this->actingAs($user)->get('/financeiro');

        $response->assertOk();
        $response->assertSee("R$\u{00A0}5.000,00", false);
        $response->assertSee("R$\u{00A0}2.300,00", false);
    }

    public function test_lancamentos_outside_the_selected_period_are_excluded(): void
    {
        $user = User::factory()->create();
        Lancamento::factory()->for($user)->entrada()->onDate(today()->subMonths(2)->toDateString())->create(['amount' => 4000]);

        $response = $this->actingAs($user)->get('/financeiro?period=mes_atual');

        $response->assertOk();
        $response->assertDontSee("R$\u{00A0}4.000,00", false);
    }

    public function test_previous_month_period_shows_that_months_data(): void
    {
        $user = User::factory()->create();
        Lancamento::factory()->for($user)->entrada()
            ->onDate(today()->subMonthNoOverflow()->startOfMonth()->toDateString())
            ->create(['amount' => 1234]);

        $response = $this->actingAs($user)->get('/financeiro?period=mes_anterior');

        $response->assertOk();
        $response->assertSee("R$\u{00A0}1.234,00", false);
    }

    public function test_investment_cards_reflect_estimated_yield(): void
    {
        $user = User::factory()->create();
        $investimento = Investimento::factory()->for($user)->create(['rate' => 12, 'rate_period' => 'anual']);
        InvestimentoAporte::factory()->for($investimento)->onDate(today()->subYear()->toDateString())->create(['amount' => 1000]);

        $response = $this->actingAs($user)->get('/financeiro');

        $response->assertOk();
        $response->assertSee('Patrimônio investido');
        $response->assertSee('Rendimento acumulado');
    }

    public function test_a_user_only_sees_their_own_data_on_the_dashboard(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        Lancamento::factory()->for($owner)->entrada()->onDate(today()->toDateString())->create(['amount' => 7777]);

        $response = $this->actingAs($intruder)->get('/financeiro');

        $response->assertOk();
        $response->assertDontSee("R$\u{00A0}7.777,00", false);
    }
}
