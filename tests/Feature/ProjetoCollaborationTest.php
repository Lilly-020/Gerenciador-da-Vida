<?php

namespace Tests\Feature;

use App\Models\Participacao;
use App\Models\Projeto;
use App\Models\ProjetoFile;
use App\Models\ProjetoTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjetoCollaborationTest extends TestCase
{
    use RefreshDatabase;

    private function participate(Projeto $project, User $user, string $permission): Participacao
    {
        return Participacao::create([
            'owner_id' => $project->user_id, 'participant_id' => $user->id,
            'projeto_id' => $project->id, 'tipo' => 'projeto', 'permission' => $permission, 'status' => 'aceito',
        ]);
    }

    public function test_overdue_is_separate_from_status_and_excludes_completed_and_archived_projects(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 8)->startOfDay());
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create(['due_at' => '2026-09-30']);
        ProjetoTask::factory()->for($project)->completed()->create();
        $task = ProjetoTask::factory()->for($project)->create();

        $this->actingAs($owner)->get('/projetos')->assertSee('Atrasado')->assertSee('Em andamento');

        $this->patchJson(route('projetos.tarefas.update', [$project, $task]), ['completed' => true])->assertOk();
        $this->get('/projetos')->assertDontSee('>Atrasado<', false);
        $this->assertFalse($project->fresh()->overdue);
        $project->update(['archived' => true]);
        $this->assertSame(0, Projeto::summarize(collect([$project]))['total']);
    }

    public function test_deadline_today_is_not_overdue(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 8)->setTime(23, 59));
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create(['due_at' => '2026-11-08']);

        $this->actingAs($owner)->get('/projetos')->assertSee('data-soon="true"', false);

        $this->assertFalse($project->overdue);
    }

    public function test_editor_creates_assigned_subtasks_with_deadlines_and_numeric_goals(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $parent = ProjetoTask::factory()->for($project)->create();
        $this->participate($project, $editor, 'editar');

        $this->actingAs($editor)->postJson(route('projetos.tarefas.store', $project), [
            'title' => 'Revisar páginas', 'assignee_id' => $editor->id, 'parent_id' => $parent->id,
            'due_at' => '2027-01-01', 'goal_type' => 'numeric', 'target_value' => 200, 'current_value' => 50, 'unit' => 'páginas',
        ])->assertOk();

        $this->assertDatabaseHas('projeto_tasks', ['title' => 'Revisar páginas', 'assignee_id' => $editor->id, 'parent_id' => $parent->id, 'current_value' => 50]);
        $this->assertDatabaseHas('projeto_activities', ['projeto_id' => $project->id, 'user_id' => $editor->id]);
        $this->assertSame(12, $project->fresh('tasks')->progress);
    }

    public function test_task_validation_rejects_outsiders_foreign_parents_and_empty_numeric_targets(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $foreign = ProjetoTask::factory()->create();

        $this->actingAs($owner)->postJson(route('projetos.tarefas.store', $project), [
            'title' => 'Invalid task', 'assignee_id' => $outsider->id, 'parent_id' => $foreign->id,
            'goal_type' => 'numeric', 'current_value' => -1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['assignee_id', 'parent_id', 'target_value', 'current_value']);

        $this->assertDatabaseMissing('projeto_tasks', ['title' => 'Invalid task']);
    }

    public function test_financial_goals_are_separate_and_cannot_be_completed_by_checkbox(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        ProjetoTask::factory()->for($project)->completed()->create();
        $goal = ProjetoTask::factory()->for($project)->create(['goal_type' => 'financial', 'target_value' => 50000, 'current_value' => 12000]);

        $this->actingAs($owner)->patchJson(route('projetos.tarefas.update', [$project, $goal]), ['completed' => true])->assertOk();

        $project = $project->fresh('tasks');
        $this->assertSame(100, $project->progress);
        $this->assertSame(24, $project->financial_progress);
        $this->assertSame('in_progress', $project->status);
        $this->assertFalse($goal->fresh()->completed);
        $this->patchJson(route('projetos.tarefas.update', [$project, $goal]), ['current_value' => 50000])->assertOk();
        $this->assertSame('completed', $project->fresh('tasks')->status);
    }

    public function test_comments_can_reference_tasks_and_are_escaped_and_logged(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $task = ProjetoTask::factory()->for($project)->create();
        $body = '<script>alert("test")</script>';

        $this->actingAs($owner)->post(route('projetos.comentarios.store', $project), ['body' => $body, 'projeto_task_id' => $task->id])->assertRedirect();

        $this->assertDatabaseHas('projeto_comments', ['body' => $body, 'user_id' => $owner->id, 'projeto_task_id' => $task->id]);
        $this->assertDatabaseHas('projeto_activities', ['description' => 'Adicionou um comentário.', 'user_id' => $owner->id]);
        $this->get('/projetos')->assertSee($body)->assertDontSee($body, false);
    }

    public function test_comments_reject_tasks_from_another_project(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $otherTask = ProjetoTask::factory()->create();

        $this->actingAs($owner)->postJson(route('projetos.comentarios.store', $project), ['body' => 'Wrong project', 'projeto_task_id' => $otherTask->id])->assertUnprocessable()->assertJsonValidationErrors('projeto_task_id');

        $this->assertDatabaseCount('projeto_comments', 0);
    }

    public function test_private_upload_download_and_deletion(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();

        $this->actingAs($owner)->post(route('projetos.arquivos.store', $project), ['file' => UploadedFile::fake()->create('brief.pdf', 50, 'application/pdf')])->assertRedirect();

        $file = $project->files()->firstOrFail();
        Storage::disk('local')->assertExists($file->path);
        $this->get(route('projetos.arquivos.download', [$project, $file]))->assertDownload('brief.pdf');
        $this->delete(route('projetos.arquivos.destroy', [$project, $file]))->assertRedirect();
        Storage::disk('local')->assertMissing($file->path);
        $this->assertModelMissing($file);
    }

    public function test_an_outsider_cannot_download_a_project_attachment(): void
    {
        Storage::fake('local');
        $file = ProjetoFile::factory()->create();
        Storage::disk('local')->put($file->path, 'private document');

        $this->actingAs(User::factory()->create())->get(route('projetos.arquivos.download', [$file->projeto_id, $file]))->assertNotFound();
    }

    public function test_viewers_cannot_comment_upload_or_mutate_tasks_and_editors_cannot_manage_projects(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $editor = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $this->participate($project, $viewer, 'ver');
        $this->participate($project, $editor, 'editar');

        $this->actingAs($viewer)->postJson(route('projetos.comentarios.store', $project), ['body' => 'No'])->assertForbidden();
        $this->postJson(route('projetos.arquivos.store', $project))->assertForbidden();
        $this->postJson(route('projetos.tarefas.store', $project), ['title' => 'No'])->assertForbidden();
        $this->actingAs($editor)->putJson(route('projetos.update', $project), ['title' => 'No'])->assertForbidden();
        $this->assertDatabaseCount('projeto_comments', 0);
        $this->assertDatabaseCount('projeto_files', 0);
        $this->assertDatabaseCount('projeto_tasks', 0);
        $this->assertDatabaseHas('projetos', ['id' => $project->id, 'title' => $project->title]);
    }

    public function test_administrators_can_manage_project_and_invitations_and_cancel_pending_invites(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $invitee = User::factory()->create(['apelido' => 'newperson']);
        $project = Projeto::factory()->for($owner)->create();
        $this->participate($project, $admin, 'admin');

        $this->actingAs($admin)->putJson(route('projetos.update', $project), ['title' => 'Managed', 'details' => 'Complemento', 'priority' => 'high', 'archived' => true])->assertOk();
        $this->post(route('participacoes.store'), ['apelido' => 'newperson', 'tipo' => 'projeto', 'projeto_id' => $project->id, 'permission' => 'ver'])->assertRedirect();

        $invitation = Participacao::where('participant_id', $invitee->id)->firstOrFail();
        $this->assertSame($owner->id, $invitation->owner_id);
        $this->actingAs($owner)->get('/perfil?aba=notificacoes')->assertSee('Administrador');
        $this->actingAs($admin);
        $this->post(route('participacoes.reenviar', $invitation))->assertRedirect();
        $this->delete(route('participacoes.destroy', $invitation))->assertRedirect();
        $this->assertModelMissing($invitation);
        $this->assertDatabaseHas('projetos', ['id' => $project->id, 'details' => 'Complemento', 'priority' => 'high', 'archived' => true]);
    }

    public function test_removing_a_participant_clears_their_task_assignments(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $participation = $this->participate($project, $editor, 'editar');
        $task = ProjetoTask::factory()->for($project)->create(['assignee_id' => $editor->id]);

        $this->actingAs($owner)->delete(route('participacoes.destroy', $participation))->assertRedirect();

        $this->assertNull($task->fresh()->assignee_id);
    }

    public function test_deleting_a_task_removes_subtasks_and_preserves_comment_and_history(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $task = ProjetoTask::factory()->for($project)->create();
        $child = ProjetoTask::factory()->for($project)->create(['parent_id' => $task->id]);
        $comment = $project->comments()->create(['user_id' => $owner->id, 'projeto_task_id' => $child->id, 'body' => 'Preserve']);

        $this->actingAs($owner)->deleteJson(route('projetos.tarefas.destroy', [$project, $task]))->assertOk();

        $this->assertModelMissing($child);
        $this->assertNull($comment->fresh()->projeto_task_id);
        $this->assertDatabaseHas('projeto_activities', ['description' => 'Excluiu a tarefa “'.$task->title.'” e suas subtarefas.']);
    }

    #[DataProvider('invalidFiles')]
    public function test_invalid_attachments_are_rejected(string $name, int $size, string $mime): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();

        $this->actingAs($owner)->postJson(route('projetos.arquivos.store', $project), ['file' => UploadedFile::fake()->create($name, $size, $mime)])->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('projeto_files', 0);
        Storage::disk('local')->assertDirectoryEmpty('projeto-files');
    }

    public static function invalidFiles(): array
    {
        return ['executable' => ['test.php', 1, 'application/x-php'], 'too large' => ['test.pdf', 10241, 'application/pdf']];
    }

    public function test_overfunding_one_goal_does_not_complete_another_financial_goal(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        ProjetoTask::factory()->for($project)->create(['goal_type' => 'financial', 'target_value' => 100, 'current_value' => 200]);
        ProjetoTask::factory()->for($project)->create(['goal_type' => 'financial', 'target_value' => 100, 'current_value' => 0]);

        $this->actingAs($owner)->get('/projetos')->assertSee('Progresso financeiro');

        $this->assertSame(50, $project->fresh('tasks')->financial_progress);
        $this->assertSame('in_progress', $project->fresh('tasks')->status);
    }

    public function test_numeric_targets_cannot_be_removed_and_subtasks_cannot_be_reparented(): void
    {
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $task = ProjetoTask::factory()->for($project)->create(['goal_type' => 'numeric', 'target_value' => 100]);

        $this->actingAs($owner)->patchJson(route('projetos.tarefas.update', [$project, $task]), ['target_value' => null, 'parent_id' => $task->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['target_value', 'parent_id']);

        $this->assertDatabaseHas('projeto_tasks', ['id' => $task->id, 'target_value' => 100, 'parent_id' => null]);
    }

    public function test_deleted_projects_remove_private_attachments_and_collaboration_records(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $file = ProjetoFile::factory()->for($project)->for($owner)->create();
        Storage::disk('local')->put($file->path, 'private');
        $comment = $project->comments()->create(['user_id' => $owner->id, 'body' => 'Planning']);

        $this->actingAs($owner)->deleteJson(route('projetos.destroy', $project))->assertOk();

        Storage::disk('local')->assertMissing($file->path);
        $this->assertModelMissing($file);
        $this->assertModelMissing($comment);
    }

    public function test_administrator_can_delete_a_project_and_an_editor_cannot_renew_invites(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $editor = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $this->participate($project, $admin, 'admin');
        $invitation = $this->participate($project, $editor, 'editar');
        $invitation->update(['status' => 'pendente']);

        $this->actingAs($editor)->post(route('participacoes.reenviar', $invitation))->assertNotFound();
        $this->actingAs($admin)->deleteJson(route('projetos.destroy', $project))->assertOk();

        $this->assertModelMissing($project);
    }
}
