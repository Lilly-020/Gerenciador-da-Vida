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
