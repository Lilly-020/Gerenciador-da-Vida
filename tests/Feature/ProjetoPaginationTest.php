<?php

namespace Tests\Feature;

use App\Models\Participacao;
use App\Models\Projeto;
use App\Models\ProjetoTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class ProjetoPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_are_paginated_ten_at_a_time_with_totals_for_all_projects(): void
    {
        $user = User::factory()->create();
        Projeto::factory()->for($user)->count(21)->sequence(
            fn (Sequence $sequence): array => ['title' => sprintf('Projeto %02d', $sequence->index + 1)],
        )->create();

        $response = $this->actingAs($user)->get('/projetos');

        $response->assertSee('Projeto 21')->assertSee('Projeto 12')->assertDontSee('Projeto 11')->assertDontSee('Projeto 01')
            ->assertSee('Paginação dos projetos')->assertSee('Próxima')->assertViewHas('stats', fn (array $stats): bool => $stats['total'] === 21)
            ->assertViewHas('projetos', fn (LengthAwarePaginator $page): bool => $page->perPage() === 10 && $page->count() === 10 && $page->total() === 21 && $page->lastPage() === 3);
        $this->get('/projetos?page=2')->assertSee('Projeto 11')->assertSee('Projeto 02')->assertDontSee('Projeto 21')->assertDontSee('Projeto 01');
        $this->get('/projetos?page=3')->assertSee('Projeto 01')->assertDontSee('Projeto 02');
    }

    public function test_title_search_matches_projects_beyond_the_current_page_without_accents(): void
    {
        $user = User::factory()->create();
        Projeto::factory()->for($user)->create(['title' => 'Organização da viagem']);
        Projeto::factory()->for($user)->count(11)->create(['title' => 'Outro projeto']);

        $response = $this->actingAs($user)->get('/projetos?q=organizacao');

        $response->assertSee('Organização da viagem')->assertDontSee('Outro projeto')
            ->assertViewHas('projetos', fn (LengthAwarePaginator $page): bool => $page->total() === 1);
    }

    public function test_pagination_keeps_filters_and_sorting_in_its_links(): void
    {
        $user = User::factory()->create();
        Projeto::factory()->for($user)->count(11)->sequence(fn (Sequence $sequence): array => ['title' => sprintf('Viagem %02d', $sequence->index + 1)])->create(['due_at' => null]);

        $response = $this->actingAs($user)->get('/projetos?q=Viagem&prazo=none&sort=name');

        $response->assertSee('Viagem 01')->assertSee('Viagem 10')->assertDontSee('Viagem 11');
        $page = $response->viewData('projetos');
        $this->assertSame('/projetos?q=Viagem&prazo=none&sort=name&page=2', $page->nextPageUrl());
        $this->get('/projetos?q=Viagem&prazo=none&sort=name&page=2')->assertSee('Viagem 11')->assertDontSee('Viagem 01');
    }

    public function test_status_filters_apply_before_pagination_and_statistics_keep_all_active_projects(): void
    {
        $user = User::factory()->create();
        $completed = Projeto::factory()->for($user)->create(['title' => 'Projeto concluído']);
        ProjetoTask::factory()->for($completed)->completed()->create();
        Projeto::factory()->for($user)->count(3)->create(['title' => 'Projeto pendente']);
        Projeto::factory()->for($user)->create(['title' => 'Arquivo antigo', 'archived' => true]);

        $response = $this->actingAs($user)->get('/projetos?status=completed');

        $response->assertSee('Projeto concluído')->assertDontSee('Projeto pendente')->assertDontSee('Arquivo antigo')
            ->assertViewHas('stats', fn (array $stats): bool => $stats['total'] === 4 && $stats['concluidos'] === 1);
        $this->get('/projetos?status=archived')->assertSee('Arquivo antigo')->assertDontSee('Projeto concluído');
    }

    public function test_deadline_filters_and_date_ranges_search_all_pages(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 17)->startOfDay());
        $user = User::factory()->create();
        Projeto::factory()->for($user)->create(['title' => 'Vencido', 'due_at' => '2026-11-15']);
        Projeto::factory()->for($user)->create(['title' => 'Entrega nesta semana', 'due_at' => '2026-11-20']);
        Projeto::factory()->for($user)->create(['title' => 'Sem data', 'due_at' => null]);

        $this->actingAs($user)->get('/projetos?prazo=overdue')->assertSee('Vencido')->assertDontSee('Entrega nesta semana')->assertDontSee('Sem data');
        $this->get('/projetos?prazo=soon')->assertSee('Entrega nesta semana')->assertDontSee('Vencido')->assertDontSee('Sem data');
        $this->get('/projetos?de=2026-11-19&ate=2026-11-21')->assertSee('Entrega nesta semana')->assertDontSee('Vencido');
        $this->get('/projetos?ate=2026-11-16')->assertSee('Vencido')->assertDontSee('Entrega nesta semana');
    }

    public function test_only_owned_and_accepted_shared_projects_are_counted_and_paginated(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        Projeto::factory()->for($user)->count(10)->create();
        $shared = Projeto::factory()->for($owner)->create(['title' => 'Projeto compartilhado']);
        Projeto::factory()->for($owner)->create(['title' => 'Projeto privado']);
        Participacao::create(['owner_id' => $owner->id, 'participant_id' => $user->id, 'projeto_id' => $shared->id, 'tipo' => 'projeto', 'permission' => 'ver', 'status' => 'aceito']);

        $response = $this->actingAs($user)->get('/projetos');

        $response->assertSee('Projeto compartilhado')->assertDontSee('Projeto privado')
            ->assertViewHas('projetos', fn (LengthAwarePaginator $page): bool => $page->total() === 11 && $page->count() === 10);
    }

    public function test_ajax_pagination_returns_only_the_requested_cards_and_navigation(): void
    {
        $user = User::factory()->create();
        Projeto::factory()->for($user)->count(11)->sequence(fn (Sequence $sequence): array => ['title' => $sequence->index === 0 ? 'Primeiro projeto' : sprintf('Projeto novo %02d', $sequence->index + 1)])->create();

        $response = $this->actingAs($user)->getJson('/projetos?page=2');

        $response->assertOk()->assertJsonStructure(['cards', 'pagination', 'stats', 'page'])->assertJsonPath('page', 2);
        $this->assertStringContainsString('Primeiro projeto', $response->json('cards'));
        $this->assertStringNotContainsString('Projeto novo 11', $response->json('cards'));
        $this->assertStringContainsString('aria-current="page"', $response->json('pagination'));
    }

    public function test_deleting_the_last_project_on_the_last_page_returns_to_the_previous_page(): void
    {
        $user = User::factory()->create();
        $projects = Projeto::factory()->for($user)->count(11)->create();

        $this->actingAs($user)->deleteJson(route('projetos.destroy', $projects->first()))->assertOk();

        $response = $this->getJson('/projetos?page=2')->assertOk()->assertJsonPath('page', 1);
        $this->assertSame(10, substr_count($response->json('cards'), 'data-board-card'));
        $this->assertDatabaseCount('projetos', 10);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/projetos?status=invalid')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson('/projetos?de=2026-11-20&ate=2026-11-10')->assertUnprocessable()->assertJsonValidationErrors('ate');
        $this->getJson('/projetos?page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
    }
}
