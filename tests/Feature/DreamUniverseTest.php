<?php

namespace Tests\Feature;

use App\Models\Participacao;
use App\Models\Sonho;
use App\Models\SonhoTask;
use App\Models\User;
use App\Support\DreamUniverse;
use Database\Seeders\DreamverseDemoSeeder;
use Database\Seeders\DreamverseLargeDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DreamUniverseTest extends TestCase
{
    use RefreshDatabase;

    public function test_universe_uses_existing_dreams_and_excludes_unauthorized_data(): void
    {
        $user = User::factory()->create();
        $owned = Sonho::factory()->for($user)->create(['notes' => 'Meu objetivo', 'category' => 'travel']);
        $archived = Sonho::factory()->for($user)->create(['archived' => true]);
        $private = Sonho::factory()->create(['title' => 'Um sonho privado']);
        SonhoTask::factory()->for($owned)->completed()->create();

        $this->actingAs($user)->getJson('/')->assertOk()
            ->assertJsonCount(2, 'dreams')
            ->assertJsonPath('categories', DreamUniverse::CATEGORIES)
            ->assertJsonMissing(['title' => $private->title]);
        $this->get('/')->assertOk()->assertSee('dreamverse')->assertSee($owned->title)->assertDontSee($archived->title);
        $this->getJson('/')->assertJsonFragment(['id' => $owned->id, 'category' => 'travel', 'progress' => 100]);
    }

    public function test_legacy_dreams_receive_a_neutral_category_and_keep_planning_and_tasks(): void
    {
        $user = User::factory()->create();
        $dream = Sonho::factory()->for($user)->create(['financial_goal' => 500, 'saved_amount' => 250, 'notes' => 'Preservar']);
        SonhoTask::factory()->for($dream)->create(['title' => 'Primeiro passo']);

        $this->actingAs($user)->getJson('/')->assertJsonPath('dreams.0.category', 'other')
            ->assertJsonPath('dreams.0.notes', 'Preservar')
            ->assertJsonPath('dreams.0.financial_progress', 50)
            ->assertJsonPath('dreams.0.tasks.0.title', 'Primeiro passo');
    }

    public function test_create_and_edit_return_the_dream_without_changing_completion_rules(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/sonhos', [
            'title' => 'Fazer uma viagem', 'category' => 'travel', 'priority' => 'high', 'financial_goal' => 1000, 'saved_amount' => 100,
        ])->assertOk()->assertJsonPath('dream.category', 'travel')->assertJsonPath('dream.status', 'in_progress');
        $id = $response->json('dream.id');
        $this->postJson("/sonhos/{$id}/tarefas", ['title' => 'Organizar roteiro'])->assertOk()->assertJsonPath('dream.tasks.0.completed', false);
        $task = SonhoTask::firstOrFail();
        $this->patchJson("/sonhos/{$id}/tarefas/{$task->id}", ['completed' => true])->assertOk()
            ->assertJsonPath('dream.progress', 100)->assertJsonPath('dream.status', 'in_progress');
        $this->putJson("/sonhos/{$id}", ['title' => 'Viagem dos sonhos', 'category' => 'personal', 'saved_amount' => 1000])->assertOk()
            ->assertJsonPath('dream.category', 'personal')->assertJsonPath('dream.status', 'completed');
        $this->putJson("/sonhos/{$id}", ['title' => 'Viagem dos sonhos', 'archived' => true])->assertOk()->assertJsonPath('dream.archived', true);
        $this->deleteJson("/sonhos/{$id}")->assertOk();
        $this->assertDatabaseMissing('sonho_tasks', ['id' => $task->id]);
    }

    public function test_categories_are_validated_and_client_fields_cannot_change_ownership_or_completion(): void
    {
        $user = User::factory()->create();
        $dream = Sonho::factory()->for($user)->create();
        $this->actingAs($user)->postJson('/sonhos', ['title' => 'Teste', 'category' => 'invented'])->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->putJson("/sonhos/{$dream->id}", ['title' => 'Teste', 'category' => 'invented'])->assertUnprocessable();
        $this->putJson("/sonhos/{$dream->id}", ['title' => 'Meu sonho', 'user_id' => 999, 'status' => 'completed', 'progress' => 100])->assertOk()
            ->assertJsonPath('dream.status', 'not_started')->assertJsonPath('dream.progress', 0);
        $this->assertSame($user->id, $dream->fresh()->user_id);
    }

    public function test_collaborators_can_see_and_edit_only_the_shared_dream(): void
    {
        $owner = User::factory()->create();
        $friend = User::factory()->create();
        $shared = Sonho::factory()->for($owner)->create();
        $private = Sonho::factory()->for($owner)->create();
        Participacao::create(['owner_id' => $owner->id, 'participant_id' => $friend->id, 'tipo' => 'sonho', 'sonho_id' => $shared->id, 'status' => 'aceito']);

        $this->actingAs($friend)->getJson('/')->assertJsonCount(1, 'dreams')->assertJsonPath('dreams.0.can_manage', false);
        $this->putJson("/sonhos/{$private->id}", ['title' => 'Não permitido'])->assertNotFound();
        $this->putJson("/sonhos/{$shared->id}", ['title' => 'Compartilhado', 'category' => 'family'])->assertOk()->assertJsonPath('dream.category', 'family');
        $this->deleteJson("/sonhos/{$shared->id}")->assertForbidden();
        $this->putJson("/sonhos/{$shared->id}", ['title' => 'Compartilhado', 'archived' => true])->assertForbidden();
    }

    public function test_shared_task_updates_keep_the_same_summary_scope_as_the_universe(): void
    {
        $owner = User::factory()->create();
        $friend = User::factory()->create();
        $shared = Sonho::factory()->for($owner)->create();
        Sonho::factory()->for($friend)->create();
        Participacao::create(['owner_id' => $owner->id, 'participant_id' => $friend->id, 'tipo' => 'sonho', 'sonho_id' => $shared->id, 'status' => 'aceito']);
        $task = SonhoTask::factory()->for($shared)->create();
        $this->actingAs($friend)->patchJson("/sonhos/{$shared->id}/tarefas/{$task->id}", ['completed' => true])->assertOk()
            ->assertJsonPath('dream.status', 'completed')
            ->assertJsonPath('dream.can_manage', false);
        $this->assertSame(2, Sonho::summarize(Sonho::with('tasks')->get())['total']);
    }

    public function test_requested_demo_dreams_are_idempotent_and_belong_only_to_the_selected_account(): void
    {
        $target = User::factory()->create(['email' => 'lillycardoso02@gmail.com']);
        $other = User::factory()->create();
        Sonho::factory()->for($other)->create(['title' => 'Manter meu sonho']);

        $this->seed(DreamverseDemoSeeder::class);
        $this->seed(DreamverseDemoSeeder::class);

        $this->assertSame(25, $target->sonhos()->count());
        $this->assertSame(75, SonhoTask::whereIn('sonho_id', $target->sonhos()->select('id'))->count());
        $this->assertSame(1, $other->sonhos()->count());
        $this->assertSame(8, $target->sonhos()->distinct()->count('category'));
        $this->assertSame(3, $target->sonhos()->with('tasks')->get()->pluck('status')->unique()->count());
    }

    public function test_large_demo_fills_only_the_requested_account_to_two_hundred_and_preserves_existing_dreams(): void
    {
        $target = User::factory()->create(['email' => 'lillycardoso02@gmail.com']);
        $other = User::factory()->create();
        $existing = Sonho::factory()->for($target)->create(['title' => 'Meu sonho original', 'notes' => 'Manter estas anotações']);
        Sonho::factory()->for($other)->create();

        $this->seed(DreamverseLargeDemoSeeder::class);
        $this->seed(DreamverseLargeDemoSeeder::class);

        $this->assertSame(200, $target->sonhos()->count());
        $this->assertSame(1, $other->sonhos()->count());
        $this->assertSame('Manter estas anotações', $existing->fresh()->notes);
        $this->assertSame(597, SonhoTask::whereIn('sonho_id', $target->sonhos()->select('id'))->count());
        $this->assertSame(8, $target->sonhos()->distinct()->count('category'));
    }
}
