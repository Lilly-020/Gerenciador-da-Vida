<?php

namespace Tests\Feature;

use App\Models\Sonho;
use App\Models\SonhoTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SonhoTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_the_users_sonhos_with_progress_stats(): void
    {
        $user = User::factory()->create();
        $sonho = Sonho::factory()->for($user)->create(['title' => 'Comprar uma casa']);
        SonhoTask::factory()->for($sonho)->completed()->create(['title' => 'Escolher cidade']);
        SonhoTask::factory()->for($sonho)->create(['title' => 'Escolher terreno']);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('Comprar uma casa');
        $response->assertSee('Escolher cidade');
        $response->assertSee('Escolher terreno');
        $response->assertSee('50%');
        $response->assertSee('Em andamento');
    }

    public function test_a_sonho_with_no_tasks_is_not_started(): void
    {
        $user = User::factory()->create();
        Sonho::factory()->for($user)->create(['title' => 'Aprender violão']);

        $response = $this->actingAs($user)->get('/');

        $response->assertSee('0%');
        $response->assertSee('Não iniciado');
    }

    public function test_a_sonho_with_all_tasks_completed_is_completed(): void
    {
        $user = User::factory()->create();
        $sonho = Sonho::factory()->for($user)->create();
        SonhoTask::factory()->for($sonho)->completed()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertSee('100%');
        $response->assertSee('Concluído');
    }

    public function test_user_can_create_a_sonho(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/sonhos', ['title' => 'Aprender inglês']);

        $response->assertRedirect();
        $this->assertDatabaseHas('sonhos', ['title' => 'Aprender inglês', 'user_id' => $user->id]);
    }

    public function test_creating_a_sonho_requires_a_title(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/sonhos', ['title' => '']);

        $response->assertSessionHasErrors('title');
    }

    public function test_creating_a_sonho_via_fetch_returns_rendered_card_and_stats(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/sonhos', ['title' => 'Aprender inglês']);

        $response->assertOk();
        $response->assertJsonStructure(['card', 'stats']);
        $this->assertStringContainsString('Aprender inglês', $response->json('card'));
        $this->assertStringContainsString('Total de sonhos', $response->json('stats'));
    }

    public function test_user_can_rename_their_sonho(): void
    {
        $user = User::factory()->create();
        $sonho = Sonho::factory()->for($user)->create(['title' => 'Título antigo']);

        $response = $this->actingAs($user)
            ->putJson("/sonhos/{$sonho->id}", ['title' => 'Título novo']);

        $response->assertOk();
        $this->assertDatabaseHas('sonhos', ['id' => $sonho->id, 'title' => 'Título novo']);
    }

    public function test_updating_a_sonho_requires_a_title(): void
    {
        $user = User::factory()->create();
        $sonho = Sonho::factory()->for($user)->create();

        $response = $this->actingAs($user)->put("/sonhos/{$sonho->id}", ['title' => '']);

        $response->assertSessionHasErrors('title');
    }

    public function test_a_user_cannot_rename_another_users_sonho(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $sonho = Sonho::factory()->for($owner)->create(['title' => 'Título original']);

        $response = $this->actingAs($intruder)
            ->putJson("/sonhos/{$sonho->id}", ['title' => 'Título malicioso']);

        $response->assertNotFound();
        $this->assertDatabaseHas('sonhos', ['id' => $sonho->id, 'title' => 'Título original']);
    }

    public function test_user_can_add_a_task_to_their_sonho(): void
    {
        $user = User::factory()->create();
        $sonho = Sonho::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->postJson("/sonhos/{$sonho->id}/tarefas", ['title' => 'Juntar entrada']);

        $response->assertOk();
        $this->assertDatabaseHas('sonho_tasks', ['sonho_id' => $sonho->id, 'title' => 'Juntar entrada']);
    }

    public function test_user_can_toggle_a_task_and_progress_updates(): void
    {
        $user = User::factory()->create();
        $sonho = Sonho::factory()->for($user)->create();
        $task = SonhoTask::factory()->for($sonho)->create();

        $response = $this->actingAs($user)
            ->patchJson("/sonhos/{$sonho->id}/tarefas/{$task->id}", ['completed' => true]);

        $response->assertOk();
        $this->assertDatabaseHas('sonho_tasks', ['id' => $task->id, 'completed' => true]);
        $this->assertStringContainsString('100%', $response->json('card'));
    }

    public function test_a_user_cannot_see_another_users_sonhos(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        Sonho::factory()->for($owner)->create(['title' => 'Segredo do outro usuário']);

        $response = $this->actingAs($intruder)->get('/');

        $response->assertOk();
        $response->assertDontSee('Segredo do outro usuário');
    }

    public function test_a_user_cannot_add_a_task_to_another_users_sonho(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $sonho = Sonho::factory()->for($owner)->create();

        $response = $this->actingAs($intruder)
            ->postJson("/sonhos/{$sonho->id}/tarefas", ['title' => 'Tarefa maliciosa']);

        $response->assertNotFound();
        $this->assertDatabaseMissing('sonho_tasks', ['title' => 'Tarefa maliciosa']);
    }

    public function test_a_user_cannot_toggle_another_users_task(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $sonho = Sonho::factory()->for($owner)->create();
        $task = SonhoTask::factory()->for($sonho)->create(['completed' => false]);

        $response = $this->actingAs($intruder)
            ->patchJson("/sonhos/{$sonho->id}/tarefas/{$task->id}", ['completed' => true]);

        $response->assertNotFound();
        $this->assertDatabaseHas('sonho_tasks', ['id' => $task->id, 'completed' => false]);
    }

    public function test_user_can_rename_a_task(): void
    {
        $user = User::factory()->create();
        $sonho = Sonho::factory()->for($user)->create();
        $task = SonhoTask::factory()->for($sonho)->create(['title' => 'Título original']);

        $response = $this->actingAs($user)
            ->patchJson("/sonhos/{$sonho->id}/tarefas/{$task->id}", ['title' => 'Título novo']);

        $response->assertOk();
        $this->assertDatabaseHas('sonho_tasks', ['id' => $task->id, 'title' => 'Título novo']);
    }

    public function test_user_can_delete_a_task_from_their_sonho(): void
    {
        $user = User::factory()->create();
        $sonho = Sonho::factory()->for($user)->create();
        $task = SonhoTask::factory()->for($sonho)->create();

        $response = $this->actingAs($user)
            ->deleteJson("/sonhos/{$sonho->id}/tarefas/{$task->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('sonho_tasks', ['id' => $task->id]);
    }

    public function test_a_user_cannot_delete_another_users_task(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $sonho = Sonho::factory()->for($owner)->create();
        $task = SonhoTask::factory()->for($sonho)->create();

        $response = $this->actingAs($intruder)
            ->deleteJson("/sonhos/{$sonho->id}/tarefas/{$task->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('sonho_tasks', ['id' => $task->id]);
    }

    public function test_user_can_delete_their_sonho_and_its_tasks(): void
    {
        $user = User::factory()->create();
        $sonho = Sonho::factory()->for($user)->create();
        $task = SonhoTask::factory()->for($sonho)->create();

        $response = $this->actingAs($user)->deleteJson("/sonhos/{$sonho->id}");

        $response->assertOk();
        $response->assertJsonStructure(['removeCardId', 'stats']);
        $this->assertDatabaseMissing('sonhos', ['id' => $sonho->id]);
        $this->assertDatabaseMissing('sonho_tasks', ['id' => $task->id]);
    }

    public function test_a_user_cannot_delete_another_users_sonho(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $sonho = Sonho::factory()->for($owner)->create();

        $response = $this->actingAs($intruder)->deleteJson("/sonhos/{$sonho->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('sonhos', ['id' => $sonho->id]);
    }

    public function test_planning_details_are_saved_and_finances_stay_separate_from_tasks(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/sonhos', [
            'title' => 'Comprar carro', 'financial_goal' => 45000, 'saved_amount' => 12000,
            'target_date' => '2027-12-31', 'priority' => 'high', 'notes' => 'Pesquisar modelos',
            'image_url' => 'https://example.com/car.jpg',
        ])->assertOk();
        $sonho = $user->sonhos()->firstOrFail();
        SonhoTask::factory()->for($sonho)->completed()->create();

        $this->actingAs($user)->get('/')->assertSee('27%')->assertSee('33.000,00');

        $this->assertSame(100, $sonho->progress);
        $this->assertSame('in_progress', $sonho->status);
        $this->assertDatabaseHas('sonhos', ['id' => $sonho->id, 'priority' => 'high', 'saved_amount' => 12000, 'notes' => 'Pesquisar modelos']);
    }

    public function test_archived_dreams_can_be_restored_and_are_excluded_from_active_list(): void
    {
        $user = User::factory()->create();
        $sonho = Sonho::factory()->for($user)->create(['title' => 'Viagem antiga']);

        $this->actingAs($user)->putJson('/sonhos/'.$sonho->id, ['title' => $sonho->title, 'archived' => true])->assertOk();

        $this->assertDatabaseHas('sonhos', ['id' => $sonho->id, 'archived' => true]);
        $this->get('/')->assertDontSee('Viagem antiga');
        $this->get('/?status=archived')->assertSee('Viagem antiga');
        $this->putJson('/sonhos/'.$sonho->id, ['title' => $sonho->title, 'archived' => false])->assertOk();
        $this->get('/')->assertSee('Viagem antiga');
    }

    public function test_planning_rejects_invalid_values_without_creating_a_dream(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/sonhos', [
            'title' => 'Inválido', 'financial_goal' => -1, 'saved_amount' => -10,
            'priority' => 'urgent', 'target_date' => 'invalid', 'image_url' => 'javascript:alert(1)',
        ])->assertUnprocessable()->assertJsonValidationErrors(['financial_goal', 'saved_amount', 'priority', 'target_date', 'image_url']);

        $this->assertDatabaseMissing('sonhos', ['title' => 'Inválido']);
    }

    public function test_status_filters_and_priority_ordering(): void
    {
        $user = User::factory()->create();
        $low = Sonho::factory()->for($user)->create(['title' => 'Baixa prioridade', 'priority' => 'low']);
        Sonho::factory()->for($user)->create(['title' => 'Alta prioridade', 'priority' => 'high']);
        SonhoTask::factory()->for($low)->completed()->create();

        $this->actingAs($user)->get('/?status=completed')->assertSee('Baixa prioridade')->assertDontSee('Alta prioridade');
        $this->get('/?sort=priority')->assertSeeInOrder(['Alta prioridade', 'Baixa prioridade']);
    }

    public function test_rounding_financial_progress_does_not_complete_an_unfunded_dream(): void
    {
        $user = User::factory()->create();
        $sonho = Sonho::factory()->for($user)->create(['financial_goal' => 45000, 'saved_amount' => 44999]);

        $this->actingAs($user)->get('/?status=in_progress')->assertSee($sonho->title);

        $this->assertSame('in_progress', $sonho->status);
        $this->putJson('/sonhos/'.$sonho->id, ['title' => $sonho->title, 'saved_amount' => 45000])->assertOk();
        $this->assertSame('completed', $sonho->fresh()->status);
    }
}
