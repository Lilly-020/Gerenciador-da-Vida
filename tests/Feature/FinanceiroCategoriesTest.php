<?php

namespace Tests\Feature;

use App\Models\CategoriaFinanceira;
use App\Models\Lancamento;
use App\Models\Participacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceiroCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_picker_returns_all_time_link_counts(): void
    {
        $owner = User::factory()->create();
        Lancamento::factory()->for($owner)->saida()->create(['category' => 'Moradia', 'date' => '2020-01-01']);
        Lancamento::factory()->for($owner)->saida()->create(['category' => 'Moradia']);
        Lancamento::factory()->saida()->create(['category' => 'Moradia']);

        $response = $this->actingAs($owner)->getJson('/financeiro/categorias?type=saida');

        $response->assertOk()->assertJsonFragment(['name' => 'Moradia', 'links' => 2]);
    }

    public function test_linked_category_cannot_be_deleted_even_if_entry_is_cancelled(): void
    {
        $owner = User::factory()->create();
        Lancamento::factory()->for($owner)->saida()->create(['category' => 'Moradia', 'status' => 'cancelado']);

        $this->actingAs($owner)->deleteJson('/financeiro/categorias', ['type' => 'saida', 'name' => 'Moradia'])
            ->assertUnprocessable()->assertJsonPath('links', 1)->assertJsonPath('sharingLinks', 0);

        $this->assertContains('Moradia', CategoriaFinanceira::namesFor('saida'));
    }

    public function test_unused_default_category_stays_deleted_after_reload(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->deleteJson('/financeiro/categorias', ['type' => 'entrada', 'name' => 'Freelance'])->assertOk();

        $this->getJson('/financeiro/categorias?type=entrada')->assertDontSee('Freelance');
        $this->get('/financeiro/entradas')->assertDontSee('value="Freelance"', false);
        $this->assertDatabaseHas('categorias_financeiras', ['user_id' => $owner->id, 'name' => 'Freelance', 'is_deleted' => true]);
    }

    public function test_categories_can_be_created_and_restored_inline(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->postJson('/financeiro/categorias', ['type' => 'entrada', 'name' => 'Consultoria'])->assertOk()->assertJsonFragment(['name' => 'Consultoria', 'links' => 0]);
        $this->deleteJson('/financeiro/categorias', ['type' => 'entrada', 'name' => 'Consultoria'])->assertOk();
        $this->postJson('/financeiro/categorias', ['type' => 'entrada', 'name' => 'Consultoria'])->assertOk()->assertJsonFragment(['name' => 'Consultoria', 'links' => 0]);

        $this->assertDatabaseCount('categorias_financeiras', 1);
        $this->assertDatabaseHas('categorias_financeiras', ['name' => 'Consultoria', 'is_deleted' => false]);
    }

    public function test_a_deleted_category_cannot_be_used_by_an_outdated_form(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->deleteJson('/financeiro/categorias', ['type' => 'entrada', 'name' => 'Freelance'])->assertOk();

        $this->postJson('/financeiro/lancamentos', ['type' => 'entrada', 'category' => 'Freelance', 'description' => 'Old form', 'amount' => 100, 'date' => today()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('category');

        $this->assertDatabaseMissing('lancamentos', ['description' => 'Old form']);
    }

    public function test_category_used_in_sharing_cannot_be_deleted(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        Participacao::create(['owner_id' => $owner->id, 'participant_id' => $guest->id, 'tipo' => 'financas', 'permission' => 'ver', 'sections' => ['saidas'], 'allowed_categories' => ['Moradia'], 'status' => 'pendente']);

        $this->actingAs($owner)->deleteJson('/financeiro/categorias', ['type' => 'saida', 'name' => 'Moradia'])->assertUnprocessable()->assertJsonPath('sharingLinks', 1);
    }

    public function test_viewers_cannot_create_or_delete_categories(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        Participacao::create(['owner_id' => $owner->id, 'participant_id' => $guest->id, 'tipo' => 'financas', 'permission' => 'ver', 'sections' => ['entradas', 'saidas'], 'status' => 'aceito']);

        $this->actingAs($guest)->withSession(['carteira_owner_id' => $owner->id])->getJson('/financeiro/categorias?type=saida')->assertOk()->assertJsonPath('canManage', false);
        $this->postJson('/financeiro/categorias', ['type' => 'saida', 'name' => 'Other'])->assertForbidden();
        $this->deleteJson('/financeiro/categorias', ['type' => 'saida', 'name' => 'Moradia'])->assertForbidden();
    }

    public function test_custom_dates_filter_monthly_indicators_and_include_the_end_date(): void
    {
        $owner = User::factory()->create();
        Lancamento::factory()->for($owner)->entrada()->create(['amount' => 100, 'date' => '2026-03-01']);
        Lancamento::factory()->for($owner)->entrada()->create(['amount' => 200, 'date' => '2026-03-15']);
        Lancamento::factory()->for($owner)->entrada()->create(['amount' => 999, 'date' => '2026-03-16']);

        $this->actingAs($owner)->get('/financeiro?start=2026-03-01&end=2026-03-15')->assertViewHas('period', 'personalizado')
            ->assertViewHas('cards', fn (array $cards): bool => $cards['entradas'] === 300.0);
    }

    public function test_invalid_date_range_returns_a_readable_error(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->getJson('/financeiro?start=2026-03-20&end=2026-03-01')
            ->assertUnprocessable()->assertJsonValidationErrors('end');
    }

    public function test_explicit_dates_matching_a_preset_keep_the_selected_preset(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 10));
        $owner = User::factory()->create();

        $this->actingAs($owner)->get('/financeiro?period=mes_atual&start=2026-11-01&end=2026-11-30')
            ->assertViewHas('period', 'mes_atual')->assertSee('Data de início')->assertSee('Data de fim');
    }
}
