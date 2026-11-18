<?php

namespace Tests\Feature;

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
        return Participacao::create(['owner_id' => $this->owner->id, 'participant_id' => $this->guest->id, 'tipo' => 'financas', 'permission' => $permission, 'status' => $accept ? 'aceito' : 'pendente', 'sections' => $sections ?? array_keys(Participacao::SECOES)]);
    }

    /**
     * The guest, already switched to the owner's carteira.
     */
    private function asGuestInOwnersCarteira(): static
    {
        return $this->actingAs($this->guest)->withSession([Carteira::SESSION_KEY => $this->owner->id]);
    }

    public function test_financial_invitations_are_rejected_even_with_sections(): void
    {
        $this->actingAs($this->owner)->post('/participacoes', ['tipo' => 'financas', 'apelido' => 'joao', 'sections' => ['dashboard']])->assertSessionHasErrors('tipo');
    }

    public function test_financial_participant_management_page_is_removed(): void
    {
        $this->actingAs($this->owner)->get('/financeiro/participantes')->assertNotFound();
    }

    public function test_legacy_financial_memberships_do_not_change_the_default_wallet(): void
    {
        $this->share();
        $this->actingAs($this->guest);
        $this->assertSame($this->guest->id, Carteira::ownerId());
    }

    public function test_switching_to_someone_elses_wallet_is_removed(): void
    {
        $this->share();
        $this->asGuestInOwnersCarteira()->post('/financeiro/carteira', ['owner_id' => $this->owner->id])->assertNotFound();
        $this->assertSame($this->guest->id, Carteira::ownerId());
    }

    public function test_legacy_wallet_session_values_never_grant_access(): void
    {
        $this->asGuestInOwnersCarteira()->get('/financeiro')->assertOk();
        $this->assertTrue(Carteira::isOwn());
        $this->assertCount(1, Carteira::members());
    }

    public function test_new_entries_always_belong_to_the_authenticated_user(): void
    {
        $this->share();
        $this->asGuestInOwnersCarteira()->post('/financeiro/lancamentos', ['type' => 'saida', 'description' => 'Minha compra', 'category' => 'Pessoal', 'amount' => 50, 'date' => '2026-11-18', 'status' => 'realizado'])->assertRedirect();
        $this->assertDatabaseHas('lancamentos', ['description' => 'Minha compra', 'user_id' => $this->guest->id]);
    }

    public function test_legacy_viewers_cannot_mutate_the_former_owners_entry(): void
    {
        $this->share('ver');
        $row = Lancamento::factory()->for($this->owner)->create();
        $this->asGuestInOwnersCarteira()->delete('/financeiro/lancamentos/'.$row->id)->assertNotFound();
    }

    public function test_legacy_section_limits_do_not_restrict_someones_own_wallet(): void
    {
        $this->share('ver', ['dashboard']);
        foreach (['/financeiro', '/financeiro/entradas', '/financeiro/saidas', '/financeiro/investimentos'] as $url) {
            $this->asGuestInOwnersCarteira()->get($url)->assertOk();
        }
    }

    public function test_legacy_memberships_never_make_other_wallets_available(): void
    {
        $this->share();
        $this->actingAs($this->guest);
        $this->assertTrue(Carteira::available()->isEmpty());
        $this->assertNull(Carteira::participacao());
    }

    public function test_new_financial_entries_reject_participant_splits(): void
    {
        $this->share();
        $this->actingAs($this->owner)->post('/financeiro/lancamentos', ['type' => 'saida', 'description' => 'Compra', 'amount' => 50, 'date' => '2026-11-18', 'partes' => [$this->guest->id => 10]])->assertSessionHasErrors('partes');
    }

    public function test_flat_legacy_split_fields_are_rejected(): void
    {
        $this->actingAs($this->owner)->post('/financeiro/lancamentos', ['type' => 'saida', 'description' => 'Compra', 'amount' => 50, 'date' => '2026-11-18', 'parte_u'.$this->guest->id => 100])->assertSessionHasErrors('partes');
    }

    public function test_legacy_splits_do_not_reduce_the_owners_full_amount(): void
    {
        $row = Lancamento::factory()->for($this->owner)->create(['amount' => 100]);
        $row->syncPartes([$this->guest->id => 30]);
        $this->assertSame(100.0, $row->parteOf($this->owner));
        $this->assertSame(0.0, $row->parteOf($this->guest));
    }

    public function test_historical_shared_income_does_not_leave_the_former_participants_account(): void
    {
        $row = Lancamento::factory()->for($this->owner)->create(['type' => 'entrada', 'amount' => 100]);
        $row->syncPartes([$this->guest->id => 30]);
        $this->assertSame(100.0, $row->entradaFor($this->owner));
        $this->assertSame(0.0, $row->saidaFor($this->guest));
    }

    public function test_an_owners_own_parte_row_from_older_data_is_not_charged_twice(): void
    {
        $row = Lancamento::factory()->for($this->owner)->create(['amount' => 100]);
        $row->partes()->create(['user_id' => $this->owner->id, 'amount' => 40]);
        $this->assertSame(100.0, $row->parteOf($this->owner));
    }

    public function test_expenses_always_come_out_of_the_owners_account(): void
    {
        $row = Lancamento::factory()->for($this->owner)->saida()->create(['amount' => 100]);
        $row->syncPartes([$this->guest->id => 40]);
        $this->assertSame(100.0, $row->saidaFor($this->owner));
        $this->assertSame(0.0, $row->saidaFor($this->guest));
    }

    public function test_status_updates_preserve_historical_split_records_without_using_them(): void
    {
        $row = Lancamento::factory()->for($this->owner)->create(['amount' => 100]);
        $row->syncPartes([$this->guest->id => 40]);
        $this->actingAs($this->owner)->patch('/financeiro/lancamentos/'.$row->id, ['status' => 'realizado'])->assertRedirect();
        $this->assertSame(1, $row->partes()->count());
        $this->assertSame(100.0, $row->fresh()->parteOf($this->owner));
    }

    public function test_the_dashboard_no_longer_has_joint_spending(): void
    {
        $this->share();
        $this->actingAs($this->owner)->get('/financeiro')->assertOk()->assertViewHas('gastosConjuntos', null)->assertDontSee('Parte dos outros participantes');
    }

    public function test_historical_shares_do_not_expose_foreign_entries(): void
    {
        $row = Lancamento::factory()->for($this->owner)->create();
        $row->syncPartes([$this->guest->id => 10]);
        $this->actingAs($this->guest);
        $this->assertSame(0, Lancamento::sharedWith($this->guest)->count());
        $this->assertNull(Lancamento::find($row->id));
    }

    public function test_someone_elses_lancamentos_without_a_part_for_the_user_never_show_up(): void
    {
        $row = Lancamento::factory()->for($this->owner)->create(['description' => 'Privado']);
        $this->asGuestInOwnersCarteira()->get('/financeiro/saidas')->assertOk()->assertDontSee('Privado');
    }

    public function test_the_dashboard_cards_show_the_logged_in_users_own_account(): void
    {
        Lancamento::factory()->for($this->guest)->saida()->create(['amount' => 70]);
        $this->share();
        $this->asGuestInOwnersCarteira()->get('/financeiro')->assertViewHas('cards', fn (array $cards): bool => $cards['saidas'] === 70.0);
    }

    public function test_entradas_and_saidas_never_display_foreign_participant_totals(): void
    {
        $this->share();
        foreach (['/financeiro/entradas', '/financeiro/saidas'] as $url) {
            $this->asGuestInOwnersCarteira()->get($url)->assertOk()->assertDontSee('Parte dos outros participantes');
        }
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
