<?php

namespace Tests\Feature;

use App\Models\CustoFixo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustoFixoTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_the_users_fixed_costs(): void
    {
        $user = User::factory()->create();
        CustoFixo::factory()->for($user)->create(['name' => 'Aluguel']);

        $response = $this->actingAs($user)->get('/financeiro/custos-fixos');

        $response->assertOk();
        $response->assertSee('Aluguel');
    }

    public function test_index_can_show_next_months_fixed_costs(): void
    {
        $user = User::factory()->create();
        $nextMonth = today()->addMonthNoOverflow()->startOfMonth();
        CustoFixo::factory()->for($user)->create(['name' => 'Aluguel', 'amount' => 1000]);
        CustoFixo::factory()->for($user)->create([
            'name' => 'Academia', 'amount' => 120, 'starts_on' => $nextMonth,
        ]);
        CustoFixo::factory()->for($user)->create([
            'name' => 'Curso', 'amount' => 300, 'ends_on' => today()->endOfMonth(),
        ]);

        $response = $this->actingAs($user)->get('/financeiro/custos-fixos?mes='.$nextMonth->format('Y-m'));

        $response->assertOk();
        $response->assertViewHas('total', fn ($total): bool => (float) $total === 1120.0);
        $response->assertSee('Encerrado em');
        $response->assertDontSee('Começa em');
        $response->assertDontSee('Marcar como pago');
        $response->assertSee('Voltar para o mês atual');
    }

    public function test_a_payment_this_month_does_not_count_as_paid_next_month(): void
    {
        $user = User::factory()->create();
        $custoFixo = CustoFixo::factory()->for($user)->create(['amount' => 1000]);
        $custoFixo->markPaid(today());

        $nextMonth = today()->addMonthNoOverflow()->format('Y-m');

        $this->actingAs($user)->get('/financeiro/custos-fixos')
            ->assertViewHas('totalPago', fn ($pago): bool => (float) $pago === 1000.0);
        $this->actingAs($user)->get("/financeiro/custos-fixos?mes={$nextMonth}")
            ->assertViewHas('totalPago', fn ($pago): bool => (float) $pago === 0.0);
    }

    public function test_editing_a_fixed_cost_re_renders_the_month_being_viewed(): void
    {
        $user = User::factory()->create();
        $nextMonth = today()->addMonthNoOverflow()->startOfMonth();
        $custoFixo = CustoFixo::factory()->for($user)->create(['starts_on' => $nextMonth]);

        $response = $this->actingAs($user)->putJson(
            "/financeiro/custos-fixos/{$custoFixo->id}?mes=".$nextMonth->format('Y-m'),
            [
                'name' => 'Academia', 'category' => 'Mensalidades', 'amount' => 150, 'due_day' => 10,
                'periodicity' => 'mensal', 'starts_on' => $nextMonth->toDateString(), 'status' => 'ativo',
            ],
        );

        $response->assertOk();
        $this->assertStringContainsString('150,00', $response->json('summary'));
        $this->assertStringContainsString('mes='.$nextMonth->format('Y-m'), $response->json('list'));
    }

    public function test_each_fixed_cost_card_shows_all_of_its_details(): void
    {
        $user = User::factory()->create();
        CustoFixo::factory()->for($user)->create([
            'name' => 'Vivo',
            'category' => 'Telefone',
            'starts_on' => '2026-01-15',
            'ends_on' => today()->addYear()->endOfYear(),
            'notes' => 'Plano controle, vence no cartão',
            'status' => CustoFixo::STATUS_INATIVO,
        ]);

        $response = $this->actingAs($user)->get('/financeiro/custos-fixos');

        $response->assertSee('Telefone');
        $response->assertSee('Desde 15/01/2026');
        $response->assertSee('Até '.today()->addYear()->endOfYear()->format('d/m/Y'));
        $response->assertSee('Inativo');
        $response->assertSee('Plano controle, vence no cartão');
    }

    public function test_user_can_create_a_fixed_cost(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/financeiro/custos-fixos', [
            'name' => 'Aluguel',
            'category' => 'Aluguel',
            'amount' => 1500,
            'due_day' => 10,
            'periodicity' => 'mensal',
            'starts_on' => '2026-09-01',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('custo_fixos', [
            'name' => 'Aluguel',
            'amount' => 1500,
            'user_id' => $user->id,
            'status' => 'ativo',
        ]);
    }

    public function test_creating_a_fixed_cost_requires_core_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/financeiro/custos-fixos', []);

        $response->assertSessionHasErrors(['name', 'category', 'amount', 'due_day', 'periodicity', 'starts_on']);
    }

    public function test_marking_a_fixed_cost_as_paid_creates_a_linked_saida(): void
    {
        $user = User::factory()->create();
        $custoFixo = CustoFixo::factory()->for($user)->create(['amount' => 1500]);

        $response = $this->actingAs($user)->postJson("/financeiro/custos-fixos/{$custoFixo->id}/pagar");

        $response->assertOk();
        $this->assertDatabaseHas('lancamentos', [
            'custo_fixo_id' => $custoFixo->id,
            'type' => 'saida',
            'status' => 'realizado',
            'amount' => 1500,
        ]);
        $this->assertTrue($custoFixo->isPaidFor(today()));
    }

    public function test_marking_as_paid_twice_in_the_same_month_does_not_duplicate(): void
    {
        $user = User::factory()->create();
        $custoFixo = CustoFixo::factory()->for($user)->create();

        $this->actingAs($user)->postJson("/financeiro/custos-fixos/{$custoFixo->id}/pagar");
        $this->actingAs($user)->postJson("/financeiro/custos-fixos/{$custoFixo->id}/pagar");

        $this->assertSame(1, $custoFixo->lancamentos()->count());
    }

    public function test_user_can_edit_a_fixed_costs_details(): void
    {
        $user = User::factory()->create();
        $custoFixo = CustoFixo::factory()->for($user)->create(['name' => 'Aluguel', 'amount' => 1500]);

        $response = $this->actingAs($user)->putJson("/financeiro/custos-fixos/{$custoFixo->id}", [
            'name' => 'Aluguel do apê',
            'category' => 'Aluguel',
            'amount' => 1650,
            'due_day' => 5,
            'periodicity' => 'mensal',
            'starts_on' => '2026-09-01',
            'status' => 'ativo',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('custo_fixos', [
            'id' => $custoFixo->id,
            'name' => 'Aluguel do apê',
            'amount' => 1650,
            'due_day' => 5,
        ]);
    }

    public function test_a_fixed_cost_can_be_edited_for_a_single_month(): void
    {
        $user = User::factory()->create();
        $custoFixo = CustoFixo::factory()->for($user)->create(['name' => 'Vivo', 'amount' => 60, 'due_day' => 10]);
        $thisMonth = today()->format('Y-m');
        $nextMonth = today()->addMonthNoOverflow()->format('Y-m');

        $response = $this->actingAs($user)->putJson("/financeiro/custos-fixos/{$custoFixo->id}?mes={$nextMonth}", [
            'scope' => 'mes', 'name' => 'Vivo', 'category' => 'Telefone', 'amount' => 75, 'due_day' => 12,
            'notes' => 'Veio com multa',
        ]);

        $response->assertOk();
        $this->assertSame('60.00', $custoFixo->fresh()->amount);
        $this->assertStringContainsString('Alterado só neste mês', $response->json('list'));
        $this->assertStringContainsString('Veio com multa', $response->json('list'));

        $this->actingAs($user)->get("/financeiro/custos-fixos?mes={$nextMonth}")
            ->assertViewHas('total', fn ($total): bool => (float) $total === 75.0)
            ->assertSee('Vence dia 12');
        $this->actingAs($user)->get("/financeiro/custos-fixos?mes={$thisMonth}")
            ->assertViewHas('total', fn ($total): bool => (float) $total === 60.0)
            ->assertDontSee('Alterado só neste mês');
    }

    public function test_editing_a_single_month_twice_keeps_one_ajuste_and_matching_values_remove_it(): void
    {
        $user = User::factory()->create();
        $custoFixo = CustoFixo::factory()->for($user)->create([
            'name' => 'Vivo', 'category' => 'Telefone', 'amount' => 60, 'due_day' => 10,
        ]);
        $url = "/financeiro/custos-fixos/{$custoFixo->id}?mes=".today()->format('Y-m');
        $payload = ['scope' => 'mes', 'name' => 'Vivo', 'category' => 'Telefone', 'due_day' => 10];

        $this->actingAs($user)->putJson($url, [...$payload, 'amount' => 75])->assertOk();
        $this->actingAs($user)->putJson($url, [...$payload, 'amount' => 80])->assertOk();

        $this->assertSame(1, $custoFixo->ajustes()->count());
        $this->assertSame('80.00', $custoFixo->ajustes()->first()->amount);

        $this->actingAs($user)->putJson($url, [...$payload, 'amount' => 60])->assertOk();

        $this->assertSame(0, $custoFixo->ajustes()->count());
    }

    public function test_editing_every_month_replaces_single_month_ajustes(): void
    {
        $user = User::factory()->create();
        $custoFixo = CustoFixo::factory()->for($user)->create(['amount' => 60]);
        $custoFixo->adjustFor(today(), ['amount' => 75]);

        $this->actingAs($user)->putJson("/financeiro/custos-fixos/{$custoFixo->id}", [
            'scope' => 'todos', 'name' => 'Vivo', 'category' => 'Telefone', 'amount' => 65, 'due_day' => 10,
            'periodicity' => 'mensal', 'starts_on' => today()->startOfMonth()->toDateString(), 'status' => 'ativo',
        ])->assertOk();

        $this->assertSame(0, $custoFixo->ajustes()->count());
        $this->actingAs($user)->get('/financeiro/custos-fixos')
            ->assertViewHas('total', fn ($total): bool => (float) $total === 65.0);
    }

    public function test_paying_a_fixed_cost_uses_the_months_own_amount(): void
    {
        $user = User::factory()->create();
        $custoFixo = CustoFixo::factory()->for($user)->create(['amount' => 60]);
        $custoFixo->adjustFor(today(), ['amount' => 75]);

        $this->actingAs($user)->postJson("/financeiro/custos-fixos/{$custoFixo->id}/pagar")->assertOk();

        $this->assertDatabaseHas('lancamentos', ['custo_fixo_id' => $custoFixo->id, 'amount' => 75]);
    }

    public function test_editing_a_fixed_cost_requires_core_fields(): void
    {
        $user = User::factory()->create();
        $custoFixo = CustoFixo::factory()->for($user)->create();

        $response = $this->actingAs($user)->putJson("/financeiro/custos-fixos/{$custoFixo->id}", []);

        $response->assertJsonValidationErrors(['name', 'category', 'amount', 'due_day', 'periodicity', 'starts_on', 'status']);
    }

    public function test_a_user_cannot_edit_another_users_fixed_cost(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $custoFixo = CustoFixo::factory()->for($owner)->create(['name' => 'Original']);

        $response = $this->actingAs($intruder)->putJson("/financeiro/custos-fixos/{$custoFixo->id}", [
            'name' => 'Hackeado',
            'category' => 'Outros',
            'amount' => 1,
            'due_day' => 1,
            'periodicity' => 'mensal',
            'starts_on' => '2026-09-01',
            'status' => 'ativo',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('custo_fixos', ['id' => $custoFixo->id, 'name' => 'Original']);
    }

    public function test_user_can_delete_a_fixed_cost(): void
    {
        $user = User::factory()->create();
        $custoFixo = CustoFixo::factory()->for($user)->create();

        $response = $this->actingAs($user)->deleteJson("/financeiro/custos-fixos/{$custoFixo->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('custo_fixos', ['id' => $custoFixo->id]);
    }

    public function test_a_user_cannot_see_another_users_fixed_costs(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        CustoFixo::factory()->for($owner)->create(['name' => 'Aluguel privado']);

        $response = $this->actingAs($intruder)->get('/financeiro/custos-fixos');

        $response->assertOk();
        $response->assertDontSee('Aluguel privado');
    }

    public function test_is_active_in_respects_the_start_and_end_window(): void
    {
        $user = User::factory()->create();
        $notStartedYet = CustoFixo::factory()->for($user)->create([
            'starts_on' => today()->addMonths(2)->startOfMonth(),
        ]);
        $alreadyEnded = CustoFixo::factory()->for($user)->create([
            'starts_on' => today()->subMonths(3)->startOfMonth(),
            'ends_on' => today()->subMonth()->endOfMonth(),
        ]);
        $currentlyActive = CustoFixo::factory()->for($user)->create([
            'starts_on' => today()->subMonth()->startOfMonth(),
            'ends_on' => today()->addMonth()->endOfMonth(),
        ]);

        $this->assertFalse($notStartedYet->isActiveIn(today()));
        $this->assertFalse($alreadyEnded->isActiveIn(today()));
        $this->assertTrue($currentlyActive->isActiveIn(today()));
    }

    public function test_is_due_in_only_matches_months_that_line_up_with_the_periodicity(): void
    {
        $user = User::factory()->create();
        $trimestral = CustoFixo::factory()->for($user)->create([
            'periodicity' => 'trimestral',
            'starts_on' => today()->startOfMonth(),
        ]);

        $this->assertTrue($trimestral->isDueIn(today()));
        $this->assertFalse($trimestral->isDueIn(today()->addMonth()));
        $this->assertFalse($trimestral->isDueIn(today()->addMonths(2)));
        $this->assertTrue($trimestral->isDueIn(today()->addMonths(3)));
        $this->assertTrue($trimestral->isDueIn(today()->addMonths(6)));
    }

    public function test_next_due_month_finds_the_upcoming_charge(): void
    {
        $user = User::factory()->create();
        $semestral = CustoFixo::factory()->for($user)->create([
            'periodicity' => 'semestral',
            'starts_on' => today()->startOfMonth(),
        ]);

        $next = $semestral->nextDueMonth(today()->addMonthNoOverflow());

        $this->assertNotNull($next);
        $this->assertTrue($next->isSameMonth(today()->addMonths(6)));
    }

    public function test_next_due_month_is_null_once_past_the_end_date(): void
    {
        $user = User::factory()->create();
        $custoFixo = CustoFixo::factory()->for($user)->create([
            'periodicity' => 'anual',
            'starts_on' => today()->subYear()->startOfMonth(),
            'ends_on' => today()->addMonth()->endOfMonth(),
        ]);

        $this->assertNull($custoFixo->nextDueMonth(today()->addMonths(2)));
    }

    public function test_a_periodic_cost_not_due_this_month_is_excluded_from_the_total_and_has_no_pay_button(): void
    {
        $user = User::factory()->create();
        CustoFixo::factory()->for($user)->create([
            'name' => 'IPVA',
            'amount' => 800,
            'periodicity' => 'anual',
            'starts_on' => today()->addMonth()->startOfMonth(),
        ]);

        $response = $this->actingAs($user)->get('/financeiro/custos-fixos');

        $response->assertOk();
        $response->assertViewHas('total', fn ($total): bool => (float) $total === 0.0);
    }

    public function test_user_cannot_pay_a_fixed_cost_outside_its_periodicity_window(): void
    {
        $user = User::factory()->create();
        $custoFixo = CustoFixo::factory()->for($user)->create([
            'periodicity' => 'trimestral',
            'starts_on' => today()->startOfMonth(),
        ]);

        // Due this month (0 months since start), but not next month.
        $response = $this->actingAs($user)
            ->postJson("/financeiro/custos-fixos/{$custoFixo->id}/pagar");
        $response->assertOk();

        $this->travelTo(today()->addMonthNoOverflow());

        $response = $this->actingAs($user)
            ->postJson("/financeiro/custos-fixos/{$custoFixo->id}/pagar");
        $response->assertStatus(422);
    }

    public function test_a_fixed_cost_that_has_not_started_yet_is_excluded_from_the_monthly_total(): void
    {
        $user = User::factory()->create();
        CustoFixo::factory()->for($user)->create(['amount' => 1000, 'starts_on' => today()->startOfMonth()]);
        CustoFixo::factory()->for($user)->create([
            'amount' => 500,
            'starts_on' => today()->addMonths(2)->startOfMonth(),
        ]);

        $response = $this->actingAs($user)->get('/financeiro/custos-fixos');

        $response->assertOk();
        // Still appears in the list (so it can be managed before it starts)...
        $response->assertSee('Começa em');
        // ...but only the started one counts toward "Total mensal".
        $response->assertViewHas('total', fn ($total): bool => (float) $total === 1000.0);
    }

    public function test_a_fixed_cost_that_has_already_ended_is_excluded_from_the_monthly_total(): void
    {
        $user = User::factory()->create();
        CustoFixo::factory()->for($user)->create([
            'amount' => 500,
            'starts_on' => today()->subMonths(3)->startOfMonth(),
            'ends_on' => today()->subMonth()->endOfMonth(),
        ]);

        $response = $this->actingAs($user)->get('/financeiro/custos-fixos');

        $response->assertOk();
        $response->assertSee('Encerrado em');
        $response->assertDontSee('Marcar como pago');
        $response->assertViewHas('total', fn ($total): bool => (float) $total === 0.0);
    }

    public function test_user_cannot_pay_a_fixed_cost_that_has_not_started_yet(): void
    {
        $user = User::factory()->create();
        $custoFixo = CustoFixo::factory()->for($user)->create([
            'starts_on' => today()->addMonth()->startOfMonth(),
        ]);

        $response = $this->actingAs($user)->postJson("/financeiro/custos-fixos/{$custoFixo->id}/pagar");

        $response->assertStatus(422);
        $this->assertSame(0, $custoFixo->lancamentos()->count());
    }

    public function test_a_user_cannot_pay_another_users_fixed_cost(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $custoFixo = CustoFixo::factory()->for($owner)->create();

        $response = $this->actingAs($intruder)->postJson("/financeiro/custos-fixos/{$custoFixo->id}/pagar");

        $response->assertNotFound();
        $this->assertSame(0, $custoFixo->lancamentos()->count());
    }
}
