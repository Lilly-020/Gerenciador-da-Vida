<?php

namespace Tests\Feature;

use App\Models\Lancamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LancamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_entradas_page_lists_only_entradas(): void
    {
        $user = User::factory()->create();
        Lancamento::factory()->for($user)->entrada()->create(['description' => 'Salário']);
        Lancamento::factory()->for($user)->saida()->create(['description' => 'Supermercado']);

        $response = $this->actingAs($user)->get('/financeiro/entradas');

        $response->assertOk();
        $response->assertSee('Salário');
        $response->assertDontSee('Supermercado');
    }

    public function test_the_financeiro_nav_tab_stays_highlighted_on_sub_pages(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/financeiro/entradas');

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/href="[^"]*\/financeiro"[^>]*aria-current="page"/',
            $response->getContent(),
        );
    }

    public function test_saidas_page_lists_only_saidas(): void
    {
        $user = User::factory()->create();
        Lancamento::factory()->for($user)->entrada()->create(['description' => 'Salário']);
        Lancamento::factory()->for($user)->saida()->create(['description' => 'Supermercado']);

        $response = $this->actingAs($user)->get('/financeiro/saidas');

        $response->assertOk();
        $response->assertSee('Supermercado');
        $response->assertDontSee('Salário');
    }

    public function test_user_can_create_an_entrada(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/financeiro/lancamentos', [
            'type' => 'entrada',
            'category' => 'Salário',
            'description' => 'Salário de setembro',
            'amount' => 5000,
            'date' => '2026-09-05',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('lancamentos', [
            'type' => 'entrada',
            'description' => 'Salário de setembro',
            'status' => 'realizado',
            'user_id' => $user->id,
        ]);
    }

    public function test_creating_a_lancamento_requires_core_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/financeiro/lancamentos', ['type' => 'entrada']);

        $response->assertSessionHasErrors(['category', 'description', 'amount', 'date']);
    }

    public function test_recurring_template_generates_twelve_upcoming_occurrences(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/financeiro/lancamentos', [
            'type' => 'entrada',
            'category' => 'Salário',
            'description' => 'Salário mensal',
            'amount' => 5000,
            'date' => '2026-09-05',
            'is_recurring_template' => true,
            'recurrence_period' => 'mensal',
            'recurrence_day' => 5,
        ]);

        $response->assertOk();

        $template = Lancamento::where('description', 'Salário mensal')->firstOrFail();
        $this->assertTrue($template->is_recurring_template);
        $this->assertSame(12, $template->occurrences()->count());
        $this->assertSame(12, $template->occurrences()->where('status', 'previsto')->count());
    }

    public function test_a_non_recurring_lancamento_generates_no_occurrences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/financeiro/lancamentos', [
            'type' => 'saida',
            'category' => 'Alimentação',
            'description' => 'Supermercado',
            'amount' => 350,
            'date' => '2026-09-10',
        ]);

        $lancamento = Lancamento::firstOrFail();
        $this->assertSame(0, $lancamento->occurrences()->count());
    }

    public function test_user_can_toggle_a_lancamento_status(): void
    {
        $user = User::factory()->create();
        $lancamento = Lancamento::factory()->for($user)->entrada()->previsto()->create();

        $response = $this->actingAs($user)
            ->patchJson("/financeiro/lancamentos/{$lancamento->id}", ['status' => 'realizado']);

        $response->assertOk();
        $this->assertDatabaseHas('lancamentos', ['id' => $lancamento->id, 'status' => 'realizado']);
    }

    public function test_user_can_delete_a_lancamento(): void
    {
        $user = User::factory()->create();
        $lancamento = Lancamento::factory()->for($user)->create();

        $response = $this->actingAs($user)->deleteJson("/financeiro/lancamentos/{$lancamento->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('lancamentos', ['id' => $lancamento->id]);
    }

    public function test_a_user_cannot_see_another_users_lancamentos(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        Lancamento::factory()->for($owner)->entrada()->create(['description' => 'Salário privado']);

        $response = $this->actingAs($intruder)->get('/financeiro/entradas');

        $response->assertOk();
        $response->assertDontSee('Salário privado');
    }

    public function test_a_user_cannot_toggle_another_users_lancamento(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $lancamento = Lancamento::factory()->for($owner)->previsto()->create();

        $response = $this->actingAs($intruder)
            ->patchJson("/financeiro/lancamentos/{$lancamento->id}", ['status' => 'realizado']);

        $response->assertNotFound();
        $this->assertDatabaseHas('lancamentos', ['id' => $lancamento->id, 'status' => 'previsto']);
    }

    public function test_a_user_cannot_delete_another_users_lancamento(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $lancamento = Lancamento::factory()->for($owner)->create();

        $response = $this->actingAs($intruder)->deleteJson("/financeiro/lancamentos/{$lancamento->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('lancamentos', ['id' => $lancamento->id]);
    }
}
