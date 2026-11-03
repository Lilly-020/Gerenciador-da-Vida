<?php

namespace Tests\Feature;

use App\Models\Lancamento;
use App\Models\Participacao;
use App\Models\User;
use App\Support\Carteira;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatorioMensalTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_shows_month_details_and_separate_actual_and_projected_totals(): void
    {
        $user = User::factory()->create();
        Lancamento::factory()->for($user)->entrada()->create(['date' => '2026-11-01', 'amount' => 5000, 'description' => 'Salário novembro']);
        Lancamento::factory()->for($user)->saida()->create(['date' => '2026-11-30', 'amount' => 1200, 'notes' => 'Detalhes completos', 'payment_method' => 'Pix']);
        Lancamento::factory()->for($user)->saida()->create(['date' => '2026-11-15', 'amount' => 300, 'status' => 'previsto']);
        Lancamento::factory()->for($user)->entrada()->create(['date' => '2026-12-01', 'description' => 'Outro mês']);
        Lancamento::factory()->for(User::factory())->entrada()->create(['date' => '2026-11-01', 'description' => 'Privado']);

        $response = $this->actingAs($user)->get('/financeiro/relatorio?mes=2026-11');

        $response->assertOk()->assertSee('Salário novembro')->assertSee('Detalhes completos')->assertSee('Pix')
            ->assertDontSee('Outro mês')->assertDontSee('Privado')
            ->assertViewHas('totals', [
                'realizado' => ['entrada' => 5000.0, 'saida' => 1200.0, 'saldo' => 3800.0],
                'previsto' => ['entrada' => 0.0, 'saida' => 300.0, 'saldo' => -300.0],
            ]);
    }

    public function test_shared_entries_are_counted_once_using_the_users_account_impact(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $expense = Lancamento::factory()->for($owner)->saida()->create(['date' => '2026-11-10', 'amount' => 120]);
        $expense->syncPartes([$guest->id => 60]);
        $income = Lancamento::factory()->for($owner)->entrada()->create(['date' => '2026-11-11', 'amount' => 50]);
        $income->syncPartes([$guest->id => 20]);

        $this->actingAs($guest)->get('/financeiro/relatorio?mes=2026-11')
            ->assertOk()->assertViewHas('rows', fn ($rows): bool => $rows->count() === 2)
            ->assertViewHas('totals', fn (array $totals): bool => $totals['realizado'] === ['entrada' => 0.0, 'saida' => 80.0, 'saldo' => -80.0]);
    }

    public function test_report_does_not_disclose_unshared_sections(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        Participacao::create(['owner_id' => $owner->id, 'participant_id' => $guest->id, 'tipo' => 'financas', 'permission' => 'ver', 'sections' => ['saidas'], 'status' => 'aceito']);
        Lancamento::factory()->for($owner)->entrada()->create(['date' => '2026-11-01', 'description' => 'Salário secreto']);
        Lancamento::factory()->for($owner)->saida()->create(['date' => '2026-11-02', 'description' => 'Despesa compartilhada']);

        $this->actingAs($guest)->withSession([Carteira::SESSION_KEY => $owner->id])->get('/financeiro/relatorio?mes=2026-11')
            ->assertOk()->assertSee('Relatório parcial')->assertSee('Despesa compartilhada')->assertDontSee('Salário secreto');
    }

    public function test_report_only_renders_selected_information_and_keeps_final_totals(): void
    {
        $user = User::factory()->create();
        Lancamento::factory()->for($user)->entrada()->create([
            'date' => '2026-11-01', 'amount' => 500, 'description' => 'Recebimento mensal',
            'notes' => 'Observação confidencial', 'payment_method' => 'Forma confidencial',
        ]);
        $query = http_build_query(['mes' => '2026-11', 'personalizar' => 1, 'campos' => ['date', 'description', 'amount']]);

        $response = $this->actingAs($user)->get('/financeiro/relatorio?'.$query);

        $response->assertOk()->assertSee('Recebimento mensal')->assertDontSee('Observação confidencial')
            ->assertDontSee('Forma confidencial')->assertSee('Cálculo final do mês')
            ->assertViewHas('selectedColumns', ['date' => 'Data', 'description' => 'Descrição', 'amount' => 'Valor integral'])
            ->assertViewHas('totals', fn (array $totals): bool => $totals['realizado']['saldo'] === 500.0);

        $this->get('/financeiro/relatorio?'.http_build_query(['mes' => '2026-11', 'campos' => ['notes']]))
            ->assertOk()->assertSee('Observação confidencial')->assertDontSee('Recebimento mensal');
    }

    public function test_report_rejects_empty_or_unknown_column_selections(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/financeiro/relatorio?personalizar=1')->assertJsonValidationErrors('campos');
        $this->getJson('/financeiro/relatorio?campos[]=invalid')->assertJsonValidationErrors('campos.0');
    }

    public function test_report_requires_authentication_and_a_valid_month(): void
    {
        $this->get('/financeiro/relatorio')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->getJson('/financeiro/relatorio?mes=todos')->assertJsonValidationErrors('mes');
        $this->get('/financeiro/relatorio?mes=2026-11')->assertOk()->assertSee('Nenhum lançamento neste mês.');
    }
}
