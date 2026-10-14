<?php

namespace Tests\Feature;

use App\Models\CustoFixo;
use App\Models\Lancamento;
use App\Models\Participacao;
use App\Models\User;
use App\Support\Carteira;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarteiraCompartilhadaTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $guest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['apelido' => 'lilly']);
        $this->guest = User::factory()->create(['apelido' => 'joao']);
    }

    /**
     * @param  array<int, string>  $sections
     */
    private function share(string $permission = 'editar', ?array $sections = null, bool $accept = true): Participacao
    {
        $this->actingAs($this->owner)->post('/participacoes', [
            'apelido' => 'joao',
            'tipo' => 'financas',
            'permission' => $permission,
            'sections' => $sections ?? array_keys(Participacao::SECOES),
        ])->assertSessionHasNoErrors();

        $participacao = Participacao::latest('id')->firstOrFail();

        if ($accept) {
            $this->actingAs($this->guest)->post("/participacoes/{$participacao->id}/aceitar");
        }

        return $participacao->fresh();
    }

    /**
     * The guest, already switched to the owner's carteira.
     */
    private function asGuestInOwnersCarteira(): static
    {
        return $this->actingAs($this->guest)->withSession([Carteira::SESSION_KEY => $this->owner->id]);
    }

    public function test_sharing_the_financeiro_needs_at_least_one_section(): void
    {
        $this->actingAs($this->owner)->post('/participacoes', [
            'apelido' => 'joao', 'tipo' => 'financas', 'permission' => 'ver', 'sections' => [],
        ])->assertSessionHasErrors('sections');
    }

    public function test_the_owner_manages_participants_on_their_own_page(): void
    {
        $this->share(accept: false);

        $this->actingAs($this->owner)->get('/financeiro/participantes')
            ->assertOk()
            ->assertSee('@joao')
            ->assertSee('Aguardando aceitar');
    }

    public function test_a_participant_keeps_their_own_carteira_by_default(): void
    {
        $this->share();
        Lancamento::factory()->for($this->owner)->saida()->create(['description' => 'Mercado da Lilly']);

        $this->actingAs($this->guest)->get('/financeiro/saidas')
            ->assertOk()
            ->assertDontSee('Mercado da Lilly')
            ->assertSee('Carteira de @lilly');
    }

    public function test_a_participant_can_switch_to_the_shared_carteira_and_back(): void
    {
        $this->share();
        Lancamento::factory()->for($this->owner)->saida()->create(['description' => 'Mercado da Lilly']);

        $this->actingAs($this->guest)->post('/financeiro/carteira', ['owner_id' => $this->owner->id])
            ->assertRedirect(route('financeiro'))
            ->assertSessionHas(Carteira::SESSION_KEY, $this->owner->id);

        $this->asGuestInOwnersCarteira()->get('/financeiro/saidas')->assertSee('Mercado da Lilly');

        $this->asGuestInOwnersCarteira()->post('/financeiro/carteira', ['owner_id' => ''])
            ->assertSessionMissing(Carteira::SESSION_KEY);
    }

    public function test_nobody_can_switch_to_a_carteira_that_was_not_shared_and_accepted(): void
    {
        $this->share(accept: false);
        Lancamento::factory()->for($this->owner)->saida()->create(['description' => 'Mercado da Lilly']);

        $this->actingAs($this->guest)->post('/financeiro/carteira', ['owner_id' => $this->owner->id])
            ->assertSessionMissing(Carteira::SESSION_KEY);

        // Even with the session forged, a pending invitation gives nothing.
        $this->asGuestInOwnersCarteira()->get('/financeiro/saidas')->assertDontSee('Mercado da Lilly');
    }

    public function test_what_a_participant_adds_goes_into_the_shared_carteira(): void
    {
        $this->share('editar');

        $this->asGuestInOwnersCarteira()->postJson('/financeiro/lancamentos', [
            'type' => 'saida', 'category' => 'Lazer', 'description' => 'Cinema', 'amount' => 80, 'date' => today()->toDateString(),
        ])->assertOk();

        $this->assertDatabaseHas('lancamentos', ['description' => 'Cinema', 'user_id' => $this->owner->id]);
        $this->actingAs($this->owner)->get('/financeiro/saidas')->assertSee('Cinema');
        // Back in their own carteira, the guest doesn't have it.
        $this->actingAs($this->guest)->withSession([Carteira::SESSION_KEY => null])
            ->get('/financeiro/saidas')->assertDontSee('Cinema');
    }

    public function test_a_view_only_participant_cannot_change_the_carteira(): void
    {
        $this->share('ver');
        $lancamento = Lancamento::factory()->for($this->owner)->saida()->create();
        $custoFixo = CustoFixo::factory()->for($this->owner)->create();

        $this->asGuestInOwnersCarteira()->get('/financeiro/saidas')->assertOk()->assertSee('Somente leitura');

        $this->asGuestInOwnersCarteira()->postJson('/financeiro/lancamentos', [
            'type' => 'saida', 'category' => 'Lazer', 'description' => 'Cinema', 'amount' => 80, 'date' => today()->toDateString(),
        ])->assertForbidden();
        $this->asGuestInOwnersCarteira()->deleteJson("/financeiro/lancamentos/{$lancamento->id}")->assertForbidden();
        $this->asGuestInOwnersCarteira()->postJson("/financeiro/custos-fixos/{$custoFixo->id}/pagar")->assertForbidden();

        $this->assertModelExists($lancamento);
        $this->assertSame(1, Lancamento::withoutGlobalScopes()->count());
    }

    public function test_a_participant_only_reaches_the_sections_that_were_shared(): void
    {
        $this->share('editar', ['saidas']);
        $entrada = Lancamento::factory()->for($this->owner)->entrada()->create(['description' => 'Salário da Lilly']);

        $this->asGuestInOwnersCarteira()->get('/financeiro/saidas')->assertOk()->assertDontSee('Custos Fixos');
        $this->asGuestInOwnersCarteira()->get('/financeiro/entradas')->assertRedirect(route('financeiro.saidas'));
        $this->asGuestInOwnersCarteira()->get('/financeiro')->assertRedirect(route('financeiro.saidas'));
        $this->asGuestInOwnersCarteira()->getJson('/financeiro/investimentos')->assertForbidden();
        $this->asGuestInOwnersCarteira()->getJson('/financeiro/custos-fixos')->assertForbidden();

        // The shared write routes still respect the lançamento's own type.
        $this->asGuestInOwnersCarteira()->deleteJson("/financeiro/lancamentos/{$entrada->id}")->assertForbidden();
        $this->asGuestInOwnersCarteira()->postJson('/financeiro/lancamentos', [
            'type' => 'entrada', 'category' => 'Outros', 'description' => 'x', 'amount' => 1, 'date' => today()->toDateString(),
        ])->assertForbidden();

        $this->assertModelExists($entrada);
    }

    public function test_access_ends_as_soon_as_the_owner_removes_the_participant(): void
    {
        $participacao = $this->share();
        Lancamento::factory()->for($this->owner)->saida()->create(['description' => 'Mercado da Lilly']);

        $this->actingAs($this->owner)->delete("/participacoes/{$participacao->id}");

        $this->asGuestInOwnersCarteira()->get('/financeiro/saidas')->assertOk()->assertDontSee('Mercado da Lilly');
    }

    public function test_a_lancamento_can_be_divided_between_the_participants(): void
    {
        $this->share();

        $response = $this->actingAs($this->owner)->postJson('/financeiro/lancamentos?mes='.today()->format('Y-m'), [
            'type' => 'saida', 'category' => 'Alimentação', 'description' => 'Jantar', 'amount' => 100,
            'date' => today()->toDateString(),
            "parte_u{$this->owner->id}" => '60.00',
            "parte_u{$this->guest->id}" => '40.00',
        ]);

        $response->assertOk();
        // On an expense the participant's share comes in; the rest is the owner's.
        $this->assertStringContainsString('de @joao', $response->json('list'));
        $this->assertStringContainsString('Fica para @lilly', $response->json('list'));

        $lancamento = Lancamento::withoutGlobalScopes()->where('description', 'Jantar')->firstOrFail();
        $this->assertSame(60.0, $lancamento->shareOf($this->owner));
        $this->assertSame(40.0, $lancamento->shareOf($this->guest));
    }

    public function test_a_division_cannot_exceed_the_total_or_leave_the_carteira(): void
    {
        $this->share();
        $stranger = User::factory()->create();
        $base = [
            'type' => 'saida', 'category' => 'Alimentação', 'description' => 'Jantar', 'amount' => 100,
            'date' => today()->toDateString(),
        ];

        $this->actingAs($this->owner)->postJson('/financeiro/lancamentos', [
            ...$base, "parte_u{$this->owner->id}" => 60, "parte_u{$this->guest->id}" => 50,
        ])->assertJsonValidationErrors('partes');
        $this->actingAs($this->owner)->postJson('/financeiro/lancamentos', [
            ...$base, "parte_u{$this->owner->id}" => 60, "parte_u{$stranger->id}" => 40,
        ])->assertJsonValidationErrors('partes');

        $this->assertSame(0, Lancamento::withoutGlobalScopes()->count());
    }

    public function test_when_the_owner_sets_their_own_part_the_parts_must_add_up_to_the_total(): void
    {
        $this->share();
        $payload = [
            'name' => 'Vivo', 'category' => 'Telefone', 'amount' => 60, 'due_day' => 10, 'periodicity' => 'mensal',
            'starts_on' => today()->startOfMonth()->toDateString(),
        ];

        $this->actingAs($this->owner)->postJson('/financeiro/custos-fixos', [
            ...$payload, "parte_u{$this->owner->id}" => 60, "parte_u{$this->guest->id}" => 60,
        ])->assertJsonValidationErrors('partes');
        $this->actingAs($this->owner)->postJson('/financeiro/custos-fixos', [
            ...$payload, "parte_u{$this->owner->id}" => 30, "parte_u{$this->guest->id}" => 20,
        ])->assertJsonValidationErrors('partes');

        $response = $this->actingAs($this->owner)->postJson('/financeiro/custos-fixos', [
            ...$payload, "parte_u{$this->owner->id}" => 30, "parte_u{$this->guest->id}" => 30,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Fica para @lilly', $response->json('list'));
        // The edit form gets both parts back, the owner's included.
        $this->assertStringContainsString("data-parte-u{$this->owner->id}=\"30\"", $response->json('list'));
        $this->assertStringContainsString("data-parte-u{$this->guest->id}=\"30\"", $response->json('list'));
    }

    public function test_what_is_not_given_to_a_participant_stays_with_the_owner(): void
    {
        $this->share();

        $this->actingAs($this->owner)->postJson('/financeiro/lancamentos', [
            'type' => 'entrada', 'category' => 'Freelance', 'description' => 'Projeto em dupla', 'amount' => 1000,
            'date' => today()->toDateString(), "parte_u{$this->guest->id}" => 400,
        ])->assertOk();

        $lancamento = Lancamento::withoutGlobalScopes()->where('description', 'Projeto em dupla')->firstOrFail();
        $this->assertSame(600.0, $lancamento->shareOf($this->owner));
        $this->assertSame(400.0, $lancamento->shareOf($this->guest));

        // On an income the participant's share goes out.
        $this->actingAs($this->owner)->get('/financeiro/entradas')
            ->assertSee('para @joao')
            ->assertSee('Fica para @lilly');
    }

    public function test_a_fixed_cost_can_have_a_participant_share_which_its_payment_inherits(): void
    {
        $this->share();

        $this->actingAs($this->owner)->postJson('/financeiro/custos-fixos', [
            'name' => 'Vivo', 'category' => 'Telefone', 'amount' => 60, 'due_day' => 10, 'periodicity' => 'mensal',
            'starts_on' => today()->startOfMonth()->toDateString(), "parte_u{$this->guest->id}" => 30,
        ])->assertOk();

        $custoFixo = CustoFixo::withoutGlobalScopes()->where('name', 'Vivo')->firstOrFail();
        $this->assertEquals([$this->guest->id => 30], $custoFixo->partes);

        $this->actingAs($this->owner)->get('/financeiro/custos-fixos')
            ->assertSee('de @joao')
            ->assertSee('Fica para @lilly');

        $this->actingAs($this->owner)->postJson("/financeiro/custos-fixos/{$custoFixo->id}/pagar")->assertOk();

        $pagamento = Lancamento::withoutGlobalScopes()->where('custo_fixo_id', $custoFixo->id)->firstOrFail();
        $this->assertSame(30.0, $pagamento->shareOf($this->guest));
        $this->assertSame(30.0, $pagamento->shareOf($this->owner));
    }

    public function test_a_fixed_costs_share_can_differ_in_a_single_month(): void
    {
        $this->share();
        $custoFixo = CustoFixo::factory()->for($this->owner)->create([
            'name' => 'Vivo', 'category' => 'Telefone', 'amount' => 60, 'due_day' => 10, 'partes' => [$this->guest->id => 30],
        ]);
        $nextMonth = today()->addMonthNoOverflow()->startOfMonth();

        $this->actingAs($this->owner)->putJson("/financeiro/custos-fixos/{$custoFixo->id}?mes=".$nextMonth->format('Y-m'), [
            'scope' => 'mes', 'name' => 'Vivo', 'category' => 'Telefone', 'amount' => 60, 'due_day' => 10,
            "parte_u{$this->guest->id}" => 45,
        ])->assertOk();

        $custoFixo = CustoFixo::withoutGlobalScopes()->with('ajustes')->findOrFail($custoFixo->id);
        $this->assertEquals([$this->guest->id => 45], $custoFixo->valuesFor($nextMonth)['partes']);
        $this->assertEquals([$this->guest->id => 30], $custoFixo->valuesFor(today())['partes']);
    }

    public function test_a_fixed_costs_share_cannot_exceed_its_amount(): void
    {
        $this->share();

        $this->actingAs($this->owner)->postJson('/financeiro/custos-fixos', [
            'name' => 'Vivo', 'category' => 'Telefone', 'amount' => 60, 'due_day' => 10, 'periodicity' => 'mensal',
            'starts_on' => today()->toDateString(), "parte_u{$this->guest->id}" => 61,
        ])->assertJsonValidationErrors('partes');
    }

    public function test_editing_can_change_or_clear_a_division_and_a_status_toggle_leaves_it_alone(): void
    {
        $this->share();
        $lancamento = Lancamento::factory()->for($this->owner)->saida()->create(['amount' => 100]);
        $lancamento->syncPartes([$this->owner->id => 60, $this->guest->id => 40]);
        $url = "/financeiro/lancamentos/{$lancamento->id}";

        $this->actingAs($this->owner)->patchJson($url, ['status' => 'previsto'])->assertOk();
        $this->assertSame(2, $lancamento->partes()->count());

        $this->actingAs($this->owner)->patchJson($url, [
            'amount' => 100, "parte_u{$this->owner->id}" => 10, "parte_u{$this->guest->id}" => 90,
        ])->assertOk();
        $this->assertSame(90.0, $lancamento->fresh()->shareOf($this->guest));

        $this->actingAs($this->owner)->patchJson($url, [
            'amount' => 100, "parte_u{$this->owner->id}" => '', "parte_u{$this->guest->id}" => '',
        ])->assertOk();
        $this->assertSame(0, $lancamento->partes()->count());
    }

    public function test_the_dashboard_separates_individual_and_joint_spending(): void
    {
        $this->share();
        $jantar = Lancamento::factory()->for($this->owner)->saida()->create(['category' => 'Alimentação', 'amount' => 100]);
        $jantar->syncPartes([$this->owner->id => 60, $this->guest->id => 40]);
        Lancamento::factory()->for($this->owner)->saida()->create(['category' => 'Moradia', 'amount' => 900]);

        $joint = [['category' => 'Alimentação', 'type' => 'saida', 'amount' => 100.0]];

        $this->actingAs($this->owner)->get('/financeiro')
            ->assertSee('gastos individuais')
            ->assertSee('gastos conjuntos')
            ->assertViewHas('totaisPorCategoria', [['category' => 'Moradia', 'type' => 'saida', 'amount' => 900.0]])
            ->assertViewHas('gastosConjuntos', $joint);

        // The participant has no spending of their own in this carteira.
        $this->asGuestInOwnersCarteira()->get('/financeiro')
            ->assertViewHas('totaisPorCategoria', [])
            ->assertViewHas('gastosConjuntos', $joint);
    }

    public function test_a_participants_part_counts_in_their_own_account_not_the_owners(): void
    {
        $this->share('ver');
        $vivo = CustoFixo::factory()->for($this->owner)->create([
            'name' => 'Vivo', 'category' => 'Telefone', 'amount' => 60, 'partes' => [$this->guest->id => 20],
        ]);
        $this->actingAs($this->owner)->postJson("/financeiro/custos-fixos/{$vivo->id}/pagar")->assertOk();
        Lancamento::factory()->for($this->guest)->saida()->create(['category' => 'Lazer', 'amount' => 15]);

        // The owner's account is charged only their R$ 40.
        $this->actingAs($this->owner)->get('/financeiro')
            ->assertViewHas('cards', fn (array $cards): bool => $cards['saidas'] === 40.0);

        // The participant — in their own carteira — is charged their R$ 20,
        // next to what they spent on their own.
        $own = $this->actingAs($this->guest)->withSession([Carteira::SESSION_KEY => null]);

        $own->get('/financeiro')
            ->assertViewHas('cards', fn (array $cards): bool => $cards['saidas'] === 35.0)
            ->assertViewHas('totaisPorCategoria', [['category' => 'Lazer', 'type' => 'saida', 'amount' => 15.0]])
            ->assertViewHas('gastosConjuntos', [['category' => 'Telefone', 'type' => 'saida', 'amount' => 60.0]])
            ->assertViewHas('despesasPorCategoria', fn (array $categorias): bool => collect($categorias)
                ->pluck('amount', 'category')->all() === ['Telefone' => 20.0, 'Lazer' => 15.0]);

        $own->get('/financeiro/saidas')
            ->assertSee('Vivo')
            ->assertSee('carteira de @lilly')
            ->assertViewHas('totalRealizado', 35.0);

        // It is read-only there: the lançamento still belongs to the owner.
        $pagamento = Lancamento::withoutGlobalScopes()->where('custo_fixo_id', $vivo->id)->firstOrFail();
        $own->deleteJson("/financeiro/lancamentos/{$pagamento->id}")->assertNotFound();
        $this->assertModelExists($pagamento);
    }

    public function test_someone_elses_lancamentos_without_a_part_for_the_user_never_show_up(): void
    {
        $this->share();
        Lancamento::factory()->for($this->owner)->saida()->create(['description' => 'Só da Lilly', 'amount' => 500]);

        $own = $this->actingAs($this->guest)->withSession([Carteira::SESSION_KEY => null]);

        $own->get('/financeiro/saidas')->assertDontSee('Só da Lilly')->assertViewHas('totalRealizado', 0.0);
        $own->get('/financeiro')->assertViewHas('cards', fn (array $cards): bool => $cards['saidas'] === 0.0);
    }

    public function test_the_dashboard_cards_count_only_the_logged_in_users_share(): void
    {
        $this->share();
        $jantar = Lancamento::factory()->for($this->owner)->saida()->create(['amount' => 100]);
        $jantar->syncPartes([$this->owner->id => 60, $this->guest->id => 40]);
        Lancamento::factory()->for($this->owner)->saida()->create(['amount' => 900]);
        $freela = Lancamento::factory()->for($this->owner)->entrada()->create(['amount' => 1000]);
        $freela->syncPartes([$this->guest->id => 250]);

        $this->actingAs($this->owner)->get('/financeiro')
            ->assertSee('Parte dos outros participantes')
            ->assertViewHas('cards', fn (array $cards): bool => $cards['entradas'] === 750.0
                && $cards['saidas'] === 960.0
                && $cards['saldo'] === -210.0)
            ->assertViewHas('participantes', fn ($participantes): bool => $participantes->count() === 1
                && $participantes[0]['user']->is($this->guest)
                && $participantes[0]['entradas'] === 250.0
                && $participantes[0]['saidas'] === 40.0);

        $this->asGuestInOwnersCarteira()->get('/financeiro')
            ->assertViewHas('cards', fn (array $cards): bool => $cards['entradas'] === 250.0 && $cards['saidas'] === 40.0)
            ->assertViewHas('participantes', fn ($participantes): bool => $participantes[0]['user']->is($this->owner)
                && $participantes[0]['saidas'] === 960.0);
    }

    public function test_entradas_and_saidas_totals_count_only_the_logged_in_users_share(): void
    {
        $this->share();
        $jantar = Lancamento::factory()->for($this->owner)->saida()->create(['amount' => 100]);
        $jantar->syncPartes([$this->owner->id => 60, $this->guest->id => 40]);
        Lancamento::factory()->for($this->owner)->saida()->previsto()->create(['amount' => 900]);

        $this->actingAs($this->owner)->get('/financeiro/saidas')
            ->assertSee('Parte dos outros participantes')
            ->assertViewHas('totalRealizado', 60.0)
            ->assertViewHas('totalPrevisto', 900.0)
            ->assertViewHas('participantes', fn ($participantes): bool => $participantes[0]['user']->is($this->guest)
                && $participantes[0]['realizado'] === 40.0
                && $participantes[0]['previsto'] === 0.0);

        $this->asGuestInOwnersCarteira()->get('/financeiro/saidas')
            ->assertViewHas('totalRealizado', 40.0)
            ->assertViewHas('totalPrevisto', 0.0);

        // The fetch responses re-render the same totals.
        $response = $this->actingAs($this->owner)->withSession([Carteira::SESSION_KEY => null])
            ->patchJson("/financeiro/lancamentos/{$jantar->id}", ['status' => 'previsto']);
        $this->assertStringContainsString('Parte dos outros participantes', $response->json('totals'));
    }

    public function test_custos_fixos_totals_count_only_the_logged_in_users_share(): void
    {
        $this->share();
        $vivo = CustoFixo::factory()->for($this->owner)->create(['amount' => 60, 'partes' => [$this->guest->id => 20]]);
        CustoFixo::factory()->for($this->owner)->create(['amount' => 1000]);
        $vivo->markPaid(today());

        $this->actingAs($this->owner)->withSession([Carteira::SESSION_KEY => null])->get('/financeiro/custos-fixos')
            ->assertSee('Parte dos outros participantes')
            ->assertViewHas('total', 1040.0)
            ->assertViewHas('totalPago', 40.0)
            ->assertViewHas('participantes', fn ($participantes): bool => $participantes[0]['total'] === 20.0
                && $participantes[0]['totalPago'] === 20.0);

        $this->asGuestInOwnersCarteira()->get('/financeiro/custos-fixos')
            ->assertViewHas('total', 20.0)
            ->assertViewHas('totalPago', 20.0);
    }

    public function test_a_carteira_without_participants_keeps_its_full_totals(): void
    {
        Lancamento::factory()->for($this->owner)->saida()->create(['amount' => 900]);

        $this->actingAs($this->owner)->get('/financeiro')
            ->assertDontSee('Parte dos outros participantes')
            ->assertViewHas('cards', fn (array $cards): bool => $cards['saidas'] === 900.0);
    }

    public function test_a_carteira_without_participants_shows_a_single_chart(): void
    {
        $this->actingAs($this->owner)->get('/financeiro')
            ->assertViewHas('gastosConjuntos', null)
            ->assertDontSee('gastos conjuntos');
    }
}
