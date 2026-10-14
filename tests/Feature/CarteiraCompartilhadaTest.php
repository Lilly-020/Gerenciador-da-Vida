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
        $this->assertStringContainsString('Dividido', $response->json('list'));
        $this->assertStringContainsString('@joao', $response->json('list'));

        $lancamento = Lancamento::withoutGlobalScopes()->where('description', 'Jantar')->firstOrFail();
        $this->assertSame(60.0, $lancamento->shareOf($this->owner));
        $this->assertSame(40.0, $lancamento->shareOf($this->guest));
    }

    public function test_a_division_must_add_up_and_stay_within_the_carteira(): void
    {
        $this->share();
        $stranger = User::factory()->create();
        $base = [
            'type' => 'saida', 'category' => 'Alimentação', 'description' => 'Jantar', 'amount' => 100,
            'date' => today()->toDateString(),
        ];

        $this->actingAs($this->owner)->postJson('/financeiro/lancamentos', [
            ...$base, "parte_u{$this->owner->id}" => 60, "parte_u{$this->guest->id}" => 30,
        ])->assertJsonValidationErrors('partes');
        $this->actingAs($this->owner)->postJson('/financeiro/lancamentos', [
            ...$base, "parte_u{$this->owner->id}" => 60, "parte_u{$stranger->id}" => 40,
        ])->assertJsonValidationErrors('partes');

        $this->assertSame(0, Lancamento::withoutGlobalScopes()->count());
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

    public function test_the_dashboard_charts_the_whole_carteira_and_the_logged_in_users_share(): void
    {
        $this->share();
        $jantar = Lancamento::factory()->for($this->owner)->saida()->create(['category' => 'Alimentação', 'amount' => 100]);
        $jantar->syncPartes([$this->owner->id => 60, $this->guest->id => 40]);
        // Not divided: counts entirely for the carteira's owner.
        Lancamento::factory()->for($this->owner)->saida()->create(['category' => 'Moradia', 'amount' => 900]);

        $everyone = [
            ['category' => 'Moradia', 'type' => 'saida', 'amount' => 900.0],
            ['category' => 'Alimentação', 'type' => 'saida', 'amount' => 100.0],
        ];

        $this->actingAs($this->owner)->get('/financeiro')
            ->assertSee('minha parte')
            ->assertViewHas('totaisPorCategoria', $everyone)
            ->assertViewHas('minhaPartePorCategoria', [
                ['category' => 'Moradia', 'type' => 'saida', 'amount' => 900.0],
                ['category' => 'Alimentação', 'type' => 'saida', 'amount' => 60.0],
            ]);

        $this->asGuestInOwnersCarteira()->get('/financeiro')
            ->assertViewHas('totaisPorCategoria', $everyone)
            ->assertViewHas('minhaPartePorCategoria', [
                ['category' => 'Alimentação', 'type' => 'saida', 'amount' => 40.0],
            ]);
    }

    public function test_a_carteira_without_participants_shows_a_single_chart(): void
    {
        $this->actingAs($this->owner)->get('/financeiro')
            ->assertViewHas('minhaPartePorCategoria', null)
            ->assertDontSee('minha parte');
    }
}
