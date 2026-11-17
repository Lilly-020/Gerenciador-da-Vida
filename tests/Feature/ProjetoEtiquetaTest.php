<?php

namespace Tests\Feature;

use App\Models\Participacao;
use App\Models\Projeto;
use App\Models\ProjetoEtiqueta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjetoEtiquetaTest extends TestCase
{
    use RefreshDatabase;

    private function participant(Projeto $project, string $permission): User
    {
        $user = User::factory()->create();
        Participacao::create([
            'owner_id' => $project->user_id, 'participant_id' => $user->id, 'tipo' => 'projeto',
            'projeto_id' => $project->id, 'status' => 'aceito', 'permission' => $permission,
        ]);

        return $user;
    }

    public function test_owner_can_create_a_colored_selected_label_visible_on_the_project(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();

        $response = $this->actingAs($owner)->postJson(route('projetos.etiquetas.store', $project), ['name' => 'Importante', 'color' => 'amber'])->assertOk()->assertJsonPath('canEdit', true);

        $this->assertDatabaseHas('projeto_etiquetas', ['projeto_id' => $project->id, 'name' => 'Importante', 'color' => 'amber', 'selected' => true]);
        $this->assertStringContainsString('Importante', $response->json('badges'));
        $this->assertStringContainsString('bg-amber-300', $response->json('badges'));
        $this->get(route('projetos'))->assertSee('Importante')->assertDontSee('Status e etiquetas');
        $this->assertDatabaseHas('projeto_activities', ['projeto_id' => $project->id, 'description' => 'Criou uma etiqueta.']);
    }

    public function test_multiple_labels_can_be_selected_and_deselected_without_deleting_them(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $first = ProjetoEtiqueta::factory()->for($project)->create(['name' => 'Urgente']);
        ProjetoEtiqueta::factory()->for($project)->create(['name' => 'Novo módulo']);
        $this->actingAs($owner);
        $initial = $this->getJson(route('projetos.etiquetas.index', $project))->assertOk();
        $this->assertStringContainsString('Urgente', $initial->json('badges'));
        $this->assertStringContainsString('Novo módulo', $initial->json('badges'));

        $response = $this->patchJson(route('projetos.etiquetas.update', [$project, $first]), ['selected' => false])->assertOk();

        $this->assertStringNotContainsString('Urgente', $response->json('badges'));
        $this->assertStringContainsString('Urgente', $response->json('list'));
        $this->assertStringContainsString('Novo módulo', $response->json('badges'));
        $this->assertFalse($first->fresh()->selected);
        $this->patchJson(route('projetos.etiquetas.update', [$project, $first]), ['selected' => true])->assertOk();
        $this->assertTrue($first->fresh()->selected);
    }

    public function test_editor_can_rename_recolor_and_delete_labels(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $editor = $this->participant($project, 'editar');
        $label = ProjetoEtiqueta::factory()->for($project)->create(['name' => 'Original', 'selected' => false]);

        $this->actingAs($editor)->patchJson(route('projetos.etiquetas.update', [$project, $label]), ['name' => 'Melhorias', 'color' => 'cyan'])->assertOk();
        $this->assertDatabaseHas('projeto_etiquetas', ['id' => $label->id, 'name' => 'Melhorias', 'color' => 'cyan', 'selected' => false]);
        $response = $this->deleteJson(route('projetos.etiquetas.destroy', [$project, $label]))->assertOk();
        $this->assertDatabaseMissing('projeto_etiquetas', ['id' => $label->id]);
        $this->assertStringNotContainsString('Melhorias', $response->json('list'));
    }

    public function test_read_only_participant_can_view_but_cannot_change_labels(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $viewer = $this->participant($project, 'visualizar');
        $label = ProjetoEtiqueta::factory()->for($project)->create();

        $response = $this->actingAs($viewer)->getJson(route('projetos.etiquetas.index', $project))->assertOk()->assertJsonPath('canEdit', false);
        $this->assertStringNotContainsString('data-project-label-edit', $response->json('list'));
        $this->postJson(route('projetos.etiquetas.store', $project), ['name' => 'Proibido', 'color' => 'rose'])->assertForbidden();
        $this->patchJson(route('projetos.etiquetas.update', [$project, $label]), ['selected' => false])->assertForbidden();
        $this->deleteJson(route('projetos.etiquetas.destroy', [$project, $label]))->assertForbidden();
        $this->assertDatabaseCount('projeto_etiquetas', 1);
    }

    public function test_labels_cannot_be_accessed_or_changed_through_another_project(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $other = Projeto::factory()->for($owner)->create();
        $label = ProjetoEtiqueta::factory()->for($other)->create();
        $this->actingAs($owner)->patchJson(route('projetos.etiquetas.update', [$project, $label]), ['selected' => false])->assertNotFound();
        $this->deleteJson(route('projetos.etiquetas.destroy', [$project, $label]))->assertNotFound();
        $this->actingAs(User::factory()->create())->getJson(route('projetos.etiquetas.index', $project))->assertNotFound();
    }

    public function test_duplicate_names_are_rejected_within_a_project_but_allowed_in_another(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $other = Projeto::factory()->for($owner)->create();
        $label = ProjetoEtiqueta::factory()->for($project)->create(['name' => 'Importante']);
        $this->actingAs($owner)->postJson(route('projetos.etiquetas.store', $project), ['name' => 'Importante', 'color' => 'blue'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(route('projetos.etiquetas.store', $other), ['name' => 'Importante', 'color' => 'blue'])->assertOk();
        $this->patchJson(route('projetos.etiquetas.update', [$project, $label]), ['name' => 'Importante', 'color' => 'rose'])->assertOk();
    }

    public function test_label_input_is_validated_and_names_are_escaped(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $this->actingAs($owner)->postJson(route('projetos.etiquetas.store', $project), [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'color']);
        $this->postJson(route('projetos.etiquetas.store', $project), ['name' => str_repeat('x', 61), 'color' => 'invalid', 'selected' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'color', 'selected']);
        $response = $this->postJson(route('projetos.etiquetas.store', $project), ['name' => '<script>alert(1)</script>', 'color' => 'rose'])->assertOk();
        $this->assertStringContainsString('&lt;script&gt;', $response->json('badges'));
        $this->assertStringNotContainsString('<script>', $response->json('list'));
    }

    public function test_guests_cannot_access_labels_and_deleting_project_removes_them(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $label = ProjetoEtiqueta::factory()->for($project)->create();
        $this->getJson(route('projetos.etiquetas.index', $project))->assertUnauthorized();
        $this->actingAs($owner)->deleteJson(route('projetos.destroy', $project))->assertOk();
        $this->assertDatabaseMissing('projeto_etiquetas', ['id' => $label->id]);
    }

    public function test_new_projects_have_editable_presets_that_start_unselected(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson(route('projetos.store'), ['title' => 'Projeto com etiquetas prontas'])->assertOk();
        $project = $owner->projetos()->firstOrFail();
        $this->assertCount(7, $project->etiquetas);
        $this->assertSame(0, $project->etiquetas->where('selected', true)->count());
        $preset = $project->etiquetas->firstWhere('name', 'Importante');
        $this->assertSame('amber', $preset->color);

        $this->patchJson(route('projetos.etiquetas.update', [$project, $preset]), ['name' => 'Prioridade da equipe', 'color' => 'violet', 'selected' => true])->assertOk();
        $this->assertDatabaseHas('projeto_etiquetas', ['id' => $preset->id, 'name' => 'Prioridade da equipe', 'color' => 'violet', 'selected' => true]);
    }

    public function test_deleted_presets_do_not_reappear_when_opening_or_updating_the_project(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson(route('projetos.store'), ['title' => 'Projeto'])->assertOk();
        $project = $owner->projetos()->firstOrFail();
        $preset = $project->etiquetas()->where('name', 'Ideia')->firstOrFail();
        $this->deleteJson(route('projetos.etiquetas.destroy', [$project, $preset]))->assertOk();
        $this->getJson(route('projetos.etiquetas.index', $project))->assertOk();
        $this->putJson(route('projetos.update', $project), ['title' => 'Projeto atualizado'])->assertOk();

        $this->assertDatabaseMissing('projeto_etiquetas', ['projeto_id' => $project->id, 'name' => 'Ideia']);
        $this->assertSame(6, $project->etiquetas()->count());
    }

    public function test_existing_project_backfill_preserves_custom_labels_and_does_not_duplicate_names(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $custom = ProjetoEtiqueta::factory()->for($project)->create(['name' => 'Importante', 'color' => 'rose', 'selected' => true]);
        $extra = ProjetoEtiqueta::factory()->for($project)->create(['name' => 'Minha etiqueta']);
        $migration = require database_path('migrations/2026_11_17_203638_add_default_projeto_etiquetas.php');
        $migration->up();
        $migration->up();

        $this->assertSame(8, $project->etiquetas()->count());
        $this->assertDatabaseHas('projeto_etiquetas', ['id' => $custom->id, 'color' => 'rose', 'selected' => true]);
        $this->assertModelExists($extra);
        $this->assertDatabaseHas('projeto_etiquetas', ['projeto_id' => $project->id, 'name' => 'Urgente', 'color' => 'blue', 'selected' => false]);
    }
}
