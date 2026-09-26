<?php

namespace Tests\Feature;

use App\Models\Investimento;
use App\Models\InvestimentoAporte;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestimentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_the_users_investments(): void
    {
        $user = User::factory()->create();
        Investimento::factory()->for($user)->create(['name' => 'Tesouro Selic']);

        $response = $this->actingAs($user)->get('/financeiro/investimentos');

        $response->assertOk();
        $response->assertSee('Tesouro Selic');
    }

    public function test_user_can_create_an_investment(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/financeiro/investimentos', [
            'name' => 'CDB XP',
            'type' => 'cdb',
            'rate' => 12,
            'rate_period' => 'anual',
            'rate_reference' => 'CDI',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('investimentos', [
            'name' => 'CDB XP',
            'user_id' => $user->id,
            'status' => 'ativo',
        ]);
    }

    public function test_creating_an_investment_requires_core_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/financeiro/investimentos', []);

        $response->assertSessionHasErrors(['name', 'type', 'rate', 'rate_period']);
    }

    public function test_user_can_add_a_contribution(): void
    {
        $user = User::factory()->create();
        $investimento = Investimento::factory()->for($user)->create();

        $response = $this->actingAs($user)->post("/financeiro/investimentos/{$investimento->id}/aportes", [
            'amount' => 1000,
            'date' => '2026-01-01',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('investimento_aportes', [
            'investimento_id' => $investimento->id,
            'amount' => 1000,
        ]);
    }

    public function test_each_contribution_is_stored_individually_not_collapsed(): void
    {
        $user = User::factory()->create();
        $investimento = Investimento::factory()->for($user)->create();

        InvestimentoAporte::factory()->for($investimento)->onDate('2026-01-01')->create(['amount' => 1000]);
        InvestimentoAporte::factory()->for($investimento)->onDate('2026-02-01')->create(['amount' => 200]);
        InvestimentoAporte::factory()->for($investimento)->onDate('2026-03-01')->create(['amount' => 2000]);

        $investimento->load('aportes');

        $this->assertSame(3, $investimento->aportes->count());
        $this->assertSame(3200.0, $investimento->totalAportado());
    }

    public function test_compound_interest_is_estimated_per_contribution_based_on_elapsed_time(): void
    {
        $user = User::factory()->create();
        $investimento = Investimento::factory()->for($user)->create(['rate' => 12, 'rate_period' => 'anual']);

        // Invested exactly one year ago: should have earned ~12% (compounded daily).
        InvestimentoAporte::factory()->for($investimento)->onDate(today()->subYear()->toDateString())->create(['amount' => 1000]);

        $investimento->load('aportes');

        $this->assertEqualsWithDelta(120.0, $investimento->rendimentoEstimado(), 1.0);
        $this->assertEqualsWithDelta(1120.0, $investimento->patrimonioEstimado(), 1.0);
    }

    public function test_a_future_dated_contribution_has_no_yield_yet(): void
    {
        $user = User::factory()->create();
        $investimento = Investimento::factory()->for($user)->create(['rate' => 12, 'rate_period' => 'anual']);
        InvestimentoAporte::factory()->for($investimento)->onDate(today()->addMonth()->toDateString())->create(['amount' => 1000]);

        $investimento->load('aportes');

        $this->assertSame(0.0, $investimento->rendimentoEstimado());
    }

    public function test_user_can_edit_an_investments_details(): void
    {
        $user = User::factory()->create();
        $investimento = Investimento::factory()->for($user)->create(['name' => 'CDB XP', 'rate' => 12]);

        $response = $this->actingAs($user)->put("/financeiro/investimentos/{$investimento->id}", [
            'name' => 'CDB XP Investimentos',
            'type' => 'cdb',
            'rate' => 13.5,
            'rate_period' => 'anual',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('investimentos', [
            'id' => $investimento->id,
            'name' => 'CDB XP Investimentos',
            'rate' => 13.5,
        ]);
    }

    public function test_editing_an_investment_requires_core_fields(): void
    {
        $user = User::factory()->create();
        $investimento = Investimento::factory()->for($user)->create();

        $response = $this->actingAs($user)->put("/financeiro/investimentos/{$investimento->id}", []);

        $response->assertSessionHasErrors(['name', 'type', 'rate', 'rate_period']);
    }

    public function test_a_user_cannot_edit_another_users_investment(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $investimento = Investimento::factory()->for($owner)->create(['name' => 'Original']);

        $response = $this->actingAs($intruder)->put("/financeiro/investimentos/{$investimento->id}", [
            'name' => 'Hackeado',
            'type' => 'cdb',
            'rate' => 1,
            'rate_period' => 'anual',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('investimentos', ['id' => $investimento->id, 'name' => 'Original']);
    }

    public function test_user_can_delete_an_investment(): void
    {
        $user = User::factory()->create();
        $investimento = Investimento::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete("/financeiro/investimentos/{$investimento->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('investimentos', ['id' => $investimento->id]);
    }

    public function test_user_can_delete_a_contribution(): void
    {
        $user = User::factory()->create();
        $investimento = Investimento::factory()->for($user)->create();
        $aporte = InvestimentoAporte::factory()->for($investimento)->create();

        $response = $this->actingAs($user)->delete("/financeiro/investimentos/{$investimento->id}/aportes/{$aporte->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('investimento_aportes', ['id' => $aporte->id]);
    }

    public function test_index_explains_why_a_brand_new_contribution_shows_zero_yield(): void
    {
        $user = User::factory()->create();
        $investimento = Investimento::factory()->for($user)->create(['name' => 'CDB Recente']);
        InvestimentoAporte::factory()->for($investimento)->onDate(today()->toDateString())->create();

        $response = $this->actingAs($user)->get('/financeiro/investimentos');

        $response->assertOk();
        $response->assertSee('só começa a contar a partir do dia seguinte');
    }

    public function test_index_does_not_show_the_zero_yield_note_once_a_contribution_has_accrued_something(): void
    {
        $user = User::factory()->create();
        $investimento = Investimento::factory()->for($user)->create(['name' => 'CDB Antigo']);
        InvestimentoAporte::factory()->for($investimento)->onDate(today()->subYear()->toDateString())->create();

        $response = $this->actingAs($user)->get('/financeiro/investimentos');

        $response->assertOk();
        $response->assertDontSee('só começa a contar a partir do dia seguinte');
    }

    public function test_index_shows_a_one_year_projection_for_each_investment(): void
    {
        $user = User::factory()->create();
        $investimento = Investimento::factory()->for($user)->create(['rate' => 12, 'rate_period' => 'anual']);
        InvestimentoAporte::factory()->for($investimento)->onDate(today()->subYear()->toDateString())->create(['amount' => 1000]);

        $response = $this->actingAs($user)->get('/financeiro/investimentos');

        $response->assertOk();
        $response->assertSee('em 1 ano');

        $expected = $investimento->fresh('aportes')->patrimonioEstimado(today()->addYear());
        $this->assertGreaterThan(1000.0, $expected);
    }

    public function test_a_user_cannot_see_another_users_investments(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        Investimento::factory()->for($owner)->create(['name' => 'Investimento privado']);

        $response = $this->actingAs($intruder)->get('/financeiro/investimentos');

        $response->assertOk();
        $response->assertDontSee('Investimento privado');
    }

    public function test_a_user_cannot_add_a_contribution_to_another_users_investment(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $investimento = Investimento::factory()->for($owner)->create();

        $response = $this->actingAs($intruder)->post("/financeiro/investimentos/{$investimento->id}/aportes", [
            'amount' => 1000,
            'date' => '2026-01-01',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseMissing('investimento_aportes', ['investimento_id' => $investimento->id]);
    }

    public function test_a_user_cannot_delete_another_users_investment(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $investimento = Investimento::factory()->for($owner)->create();

        $response = $this->actingAs($intruder)->delete("/financeiro/investimentos/{$investimento->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('investimentos', ['id' => $investimento->id]);
    }
}
