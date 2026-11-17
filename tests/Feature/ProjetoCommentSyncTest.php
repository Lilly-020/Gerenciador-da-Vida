<?php

namespace Tests\Feature;

use App\Models\Participacao;
use App\Models\Projeto;
use App\Models\ProjetoComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjetoCommentSyncTest extends TestCase
{
    use RefreshDatabase;

    private function participant(Projeto $project, string $permission = 'editar', string $status = 'aceito'): User
    {
        $user = User::factory()->create();
        Participacao::create([
            'owner_id' => $project->user_id, 'participant_id' => $user->id,
            'projeto_id' => $project->id, 'tipo' => 'projeto', 'permission' => $permission, 'status' => $status,
        ]);

        return $user;
    }

    public function test_new_comments_are_returned_to_another_participant_without_reloading(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $viewer = $this->participant($project, 'visualizar');
        $initial = $this->actingAs($viewer)->getJson(route('projetos.comentarios.index', $project))->assertOk()->json('revision');

        $created = $this->actingAs($owner)->postJson(route('projetos.comentarios.store', $project), ['body' => 'Atualização compartilhada']);
        $created->assertOk()->assertJsonPath('count', 1);
        $this->assertStringContainsString('Atualização compartilhada', $created->json('feed'));
        $this->assertDatabaseHas('projeto_comments', ['body' => 'Atualização compartilhada', 'edited_at' => null]);

        $received = $this->actingAs($viewer)->getJson(route('projetos.comentarios.index', $project).'?revision='.$initial)->assertOk();
        $this->assertStringContainsString('Atualização compartilhada', $received->json('feed'));
        $this->assertNotSame($initial, $received->json('revision'));
        $this->assertStringNotContainsString('data-project-comment-edit', $received->json('feed'));
    }

    public function test_unchanged_revision_avoids_resending_the_comment_feed(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $revision = $this->actingAs($owner)->getJson(route('projetos.comentarios.index', $project))->assertOk()->json('revision');

        $this->getJson(route('projetos.comentarios.index', $project).'?revision='.$revision)
            ->assertOk()->assertExactJson(['revision' => $revision])->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_author_can_edit_with_original_date_preserved_and_edit_date_visible(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(10, 0));
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $comment = ProjetoComment::factory()->for($project)->for($owner, 'user')->create(['body' => 'Original']);
        $createdAt = $comment->created_at->copy();
        $this->travel(5)->minutes();

        $response = $this->actingAs($owner)->patchJson(route('projetos.comentarios.update', [$project, $comment]), ['body' => '<script>Atualizado</script>'])->assertOk();

        $comment->refresh();
        $this->assertSame('<script>Atualizado</script>', $comment->body);
        $this->assertTrue($comment->created_at->equalTo($createdAt));
        $this->assertTrue($comment->edited_at->equalTo(now()));
        $this->assertStringContainsString('Editado em', $response->json('feed'));
        $this->assertStringContainsString('17/11/2026 10:05:00', $response->json('feed'));
        $this->assertStringContainsString('&lt;script&gt;', $response->json('feed'));
        $this->assertStringNotContainsString('<script>', $response->json('feed'));
        $this->assertDatabaseHas('projeto_activities', ['description' => 'Editou um comentário.', 'user_id' => $owner->id]);
    }

    public function test_saving_identical_text_does_not_mark_the_comment_as_edited(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $comment = ProjetoComment::factory()->for($project)->for($owner, 'user')->create(['body' => 'Original']);

        $this->actingAs($owner)->patchJson(route('projetos.comentarios.update', [$project, $comment]), ['body' => 'Original'])->assertOk();

        $this->assertNull($comment->fresh()->edited_at);
        $this->assertDatabaseMissing('projeto_activities', ['description' => 'Editou um comentário.']);
    }

    public function test_authorized_editor_cannot_edit_someone_elses_comment(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $editor = $this->participant($project);
        $comment = ProjetoComment::factory()->for($project)->for($owner, 'user')->create(['body' => 'Original']);

        $this->actingAs($editor)->patchJson(route('projetos.comentarios.update', [$project, $comment]), ['body' => 'Alteração indevida'])->assertForbidden();
        $this->assertSame('Original', $comment->fresh()->body);
    }

    public function test_read_only_author_cannot_edit_after_permission_is_reduced(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $viewer = $this->participant($project, 'visualizar');
        $comment = ProjetoComment::factory()->for($project)->for($viewer, 'user')->create();

        $this->actingAs($viewer)->patchJson(route('projetos.comentarios.update', [$project, $comment]), ['body' => 'Nova versão'])->assertForbidden();
    }

    public function test_edit_rejects_comments_from_another_project_and_invalid_bodies(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $other = Projeto::factory()->for($owner)->create();
        $comment = ProjetoComment::factory()->for($other)->for($owner, 'user')->create();
        $this->actingAs($owner)->patchJson(route('projetos.comentarios.update', [$project, $comment]), ['body' => 'Errado'])->assertNotFound();
        $this->patchJson(route('projetos.comentarios.update', [$other, $comment]), ['body' => ''])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->patchJson(route('projetos.comentarios.update', [$other, $comment]), ['body' => str_repeat('x', 5001)])->assertUnprocessable()->assertJsonValidationErrors('body');
    }

    public function test_deletion_changes_the_feed_and_revision_for_other_users(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $viewer = $this->participant($project, 'visualizar');
        $comment = ProjetoComment::factory()->for($project)->for($owner, 'user')->create();
        $revision = $this->actingAs($viewer)->getJson(route('projetos.comentarios.index', $project))->json('revision');
        $this->actingAs($owner)->deleteJson(route('projetos.comentarios.destroy', [$project, $comment]))->assertOk()->assertJsonPath('count', 0);

        $response = $this->actingAs($viewer)->getJson(route('projetos.comentarios.index', $project).'?revision='.$revision)->assertOk()->assertJsonPath('count', 0);
        $this->assertNotSame($revision, $response->json('revision'));
        $this->assertDatabaseMissing('projeto_comments', ['id' => $comment->id]);
    }

    public function test_private_feed_rejects_guests_outsiders_and_pending_invitations(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $this->getJson(route('projetos.comentarios.index', $project))->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson(route('projetos.comentarios.index', $project))->assertNotFound();
        $pending = $this->participant($project, 'editar', 'pendente');
        $this->actingAs($pending)->getJson(route('projetos.comentarios.index', $project))->assertNotFound();
    }
}
