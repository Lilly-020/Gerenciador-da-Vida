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
}
