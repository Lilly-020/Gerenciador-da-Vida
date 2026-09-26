<?php

namespace Tests\Feature;

use App\Models\Projeto;
use App\Models\ProjetoTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjetoTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_the_users_projetos_with_progress_stats(): void
    {
        $user = User::factory()->create();
        $projeto = Projeto::factory()->for($user)->create([
            'title' => 'Comprar uma casa ou Apartamento',
            'description' => 'Juntar dinheiro e escolher a região.',
            'starts_at' => '2026-09-20',
            'due_at' => '2026-12-20',
        ]);
        ProjetoTask::factory()->for($projeto)->completed()->create(['title' => 'Juntar 50 mil para entrada']);
        ProjetoTask::factory()->for($projeto)->create(['title' => 'Escolher a cidade']);

        $response = $this->actingAs($user)->get('/projetos');

        $response->assertOk();
        $response->assertSee('Comprar uma casa ou Apartamento');
        $response->assertSee('Juntar dinheiro e escolher a região.');
        $response->assertSee('20-09-2026');
        $response->assertSee('20-12-2026');
        $response->assertSee('Juntar 50 mil para entrada');
        $response->assertSee('Escolher a cidade');
        $response->assertSee('50%');
        $response->assertSee('Em andamento');
    }

    public function test_a_projeto_without_dates_or_description_hides_those_sections(): void
    {
        $this->actingAs(User::factory()->create());

        $projeto = Projeto::factory()->create([
            'title' => 'Projeto simples',
            'description' => null,
            'starts_at' => null,
            'due_at' => null,
        ]);

        $html = view('partials.projeto-card', ['projeto' => $projeto->load('tasks')])->render();

        $this->assertStringContainsString('Projeto simples', $html);
        $this->assertStringNotContainsString('Objetivo', $html);
        $this->assertStringNotContainsString('Início', $html);
        $this->assertStringNotContainsString('Prazo', $html);
    }

    public function test_user_can_create_a_projeto_with_all_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/projetos', [
            'title' => 'Aprender inglês',
            'description' => 'Estudar todo dia.',
            'starts_at' => '2026-01-01',
            'due_at' => '2026-12-31',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('projetos', [
            'title' => 'Aprender inglês',
            'description' => 'Estudar todo dia.',
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_create_a_projeto_with_only_a_title(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/projetos', ['title' => 'Projeto mínimo']);

        $response->assertRedirect();
        $this->assertDatabaseHas('projetos', ['title' => 'Projeto mínimo', 'description' => null]);
    }

    public function test_creating_a_projeto_requires_a_title(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/projetos', ['title' => '']);

        $response->assertSessionHasErrors('title');
    }

    public function test_due_at_cannot_be_before_starts_at(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/projetos', [
            'title' => 'Projeto com datas erradas',
            'starts_at' => '2026-06-01',
            'due_at' => '2026-01-01',
        ]);

        $response->assertSessionHasErrors('due_at');
    }

    public function test_user_can_update_their_projeto(): void
    {
        $user = User::factory()->create();
        $projeto = Projeto::factory()->for($user)->create(['title' => 'Título antigo']);

        $response = $this->actingAs($user)->putJson("/projetos/{$projeto->id}", [
            'title' => 'Título novo',
            'description' => 'Novo objetivo',
            'starts_at' => '2026-10-01',
            'due_at' => '2026-11-01',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('projetos', [
            'id' => $projeto->id,
            'title' => 'Título novo',
            'description' => 'Novo objetivo',
        ]);
    }

    public function test_updating_a_projeto_requires_a_title(): void
    {
        $user = User::factory()->create();
        $projeto = Projeto::factory()->for($user)->create();

        $response = $this->actingAs($user)->put("/projetos/{$projeto->id}", ['title' => '']);

        $response->assertSessionHasErrors('title');
    }

    public function test_a_user_cannot_update_another_users_projeto(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $projeto = Projeto::factory()->for($owner)->create(['title' => 'Título original']);

        $response = $this->actingAs($intruder)
            ->putJson("/projetos/{$projeto->id}", ['title' => 'Título malicioso']);

        $response->assertNotFound();
        $this->assertDatabaseHas('projetos', ['id' => $projeto->id, 'title' => 'Título original']);
    }

    public function test_user_can_add_a_task_to_their_projeto(): void
    {
        $user = User::factory()->create();
        $projeto = Projeto::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->postJson("/projetos/{$projeto->id}/tarefas", ['title' => 'Nova tarefa']);

        $response->assertOk();
        $this->assertDatabaseHas('projeto_tasks', ['projeto_id' => $projeto->id, 'title' => 'Nova tarefa']);
    }

    public function test_user_can_toggle_a_task_and_progress_updates(): void
    {
        $user = User::factory()->create();
        $projeto = Projeto::factory()->for($user)->create();
        $task = ProjetoTask::factory()->for($projeto)->create();

        $response = $this->actingAs($user)
            ->patchJson("/projetos/{$projeto->id}/tarefas/{$task->id}", ['completed' => true]);

        $response->assertOk();
        $this->assertDatabaseHas('projeto_tasks', ['id' => $task->id, 'completed' => true]);
        $this->assertStringContainsString('100%', $response->json('card'));
    }

    public function test_a_user_cannot_see_another_users_projetos(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        Projeto::factory()->for($owner)->create(['title' => 'Segredo do outro usuário']);

        $response = $this->actingAs($intruder)->get('/projetos');

        $response->assertOk();
        $response->assertDontSee('Segredo do outro usuário');
    }

    public function test_a_user_cannot_add_a_task_to_another_users_projeto(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $projeto = Projeto::factory()->for($owner)->create();

        $response = $this->actingAs($intruder)
            ->postJson("/projetos/{$projeto->id}/tarefas", ['title' => 'Tarefa maliciosa']);

        $response->assertNotFound();
        $this->assertDatabaseMissing('projeto_tasks', ['title' => 'Tarefa maliciosa']);
    }

    public function test_a_user_cannot_toggle_another_users_task(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $projeto = Projeto::factory()->for($owner)->create();
        $task = ProjetoTask::factory()->for($projeto)->create(['completed' => false]);

        $response = $this->actingAs($intruder)
            ->patchJson("/projetos/{$projeto->id}/tarefas/{$task->id}", ['completed' => true]);

        $response->assertNotFound();
        $this->assertDatabaseHas('projeto_tasks', ['id' => $task->id, 'completed' => false]);
    }

    public function test_user_can_rename_a_task(): void
    {
        $user = User::factory()->create();
        $projeto = Projeto::factory()->for($user)->create();
        $task = ProjetoTask::factory()->for($projeto)->create(['title' => 'Título original']);

        $response = $this->actingAs($user)
            ->patchJson("/projetos/{$projeto->id}/tarefas/{$task->id}", ['title' => 'Título novo']);

        $response->assertOk();
        $this->assertDatabaseHas('projeto_tasks', ['id' => $task->id, 'title' => 'Título novo']);
    }

    public function test_user_can_delete_a_task_from_their_projeto(): void
    {
        $user = User::factory()->create();
        $projeto = Projeto::factory()->for($user)->create();
        $task = ProjetoTask::factory()->for($projeto)->create();

        $response = $this->actingAs($user)
            ->deleteJson("/projetos/{$projeto->id}/tarefas/{$task->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('projeto_tasks', ['id' => $task->id]);
    }

    public function test_a_user_cannot_delete_another_users_task(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $projeto = Projeto::factory()->for($owner)->create();
        $task = ProjetoTask::factory()->for($projeto)->create();

        $response = $this->actingAs($intruder)
            ->deleteJson("/projetos/{$projeto->id}/tarefas/{$task->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('projeto_tasks', ['id' => $task->id]);
    }

    public function test_user_can_delete_their_projeto_and_its_tasks(): void
    {
        $user = User::factory()->create();
        $projeto = Projeto::factory()->for($user)->create();
        $task = ProjetoTask::factory()->for($projeto)->create();

        $response = $this->actingAs($user)->deleteJson("/projetos/{$projeto->id}");

        $response->assertOk();
        $response->assertJsonStructure(['removeCardId', 'stats']);
        $this->assertDatabaseMissing('projetos', ['id' => $projeto->id]);
        $this->assertDatabaseMissing('projeto_tasks', ['id' => $task->id]);
    }

    public function test_a_user_cannot_delete_another_users_projeto(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $projeto = Projeto::factory()->for($owner)->create();

        $response = $this->actingAs($intruder)->deleteJson("/projetos/{$projeto->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('projetos', ['id' => $projeto->id]);
    }
}
