<?php

namespace Tests\Feature;

use App\Models\Participacao;
use App\Models\Projeto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipacaoTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $guest;

    private Projeto $projeto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['apelido' => 'lilly']);
        $this->guest = User::factory()->create(['apelido' => 'Joao']);
        $this->projeto = Projeto::factory()->for($this->owner)->create(['title' => 'Projeto secreto']);
    }

    private function invite(string $permission = 'editar', string $apelido = 'joao'): Participacao
    {
        $this->actingAs($this->owner)->post('/participacoes', [
            'apelido' => $apelido, 'tipo' => 'projeto', 'projeto_id' => $this->projeto->id, 'permission' => $permission,
        ])->assertSessionHasNoErrors();

        return Participacao::latest('id')->firstOrFail();
    }

    private function accepted(string $permission = 'editar'): Participacao
    {
        $participacao = $this->invite($permission);
        $this->actingAs($this->guest)->post("/participacoes/{$participacao->id}/aceitar");

        return $participacao->fresh();
    }

    public function test_an_owner_invites_another_user_by_apelido_in_any_case(): void
    {
        $participacao = $this->invite('ver', '@JOAO');

        $this->assertSame($this->guest->id, $participacao->participant_id);
        $this->assertSame('pendente', $participacao->status);
        $this->assertSame('ver', $participacao->permission);
    }

    public function test_invalid_invitations_are_rejected(): void
    {
        $payload = ['tipo' => 'projeto', 'projeto_id' => $this->projeto->id, 'permission' => 'ver'];

        $this->actingAs($this->owner)->post('/participacoes', [...$payload, 'apelido' => 'ninguem'])
            ->assertSessionHasErrors('apelido');
        $this->actingAs($this->owner)->post('/participacoes', [...$payload, 'apelido' => 'lilly'])
            ->assertSessionHasErrors('apelido');

        $this->invite();
        $this->actingAs($this->owner)->post('/participacoes', [...$payload, 'apelido' => 'joao'])
            ->assertSessionHasErrors('apelido');

        $this->assertSame(1, Participacao::count());
    }

    public function test_a_user_cannot_invite_people_to_a_project_that_is_not_theirs(): void
    {
        $third = User::factory()->create(['apelido' => 'maria']);
        $this->accepted('editar');

        // Not even a participant who can edit.
        $this->actingAs($this->guest)->post('/participacoes', [
            'apelido' => 'maria', 'tipo' => 'projeto', 'projeto_id' => $this->projeto->id, 'permission' => 'ver',
        ])->assertNotFound();

        $this->assertSame(0, $third->participacoesRecebidas()->count());
    }

    public function test_a_project_is_only_visible_after_the_invitation_is_accepted(): void
    {
        $participacao = $this->invite();

        $this->actingAs($this->guest)->get('/projetos')->assertDontSee('Projeto secreto');
        $this->actingAs($this->guest)->get('/perfil?aba=notificacoes')
            ->assertSee('Projeto secreto')
            ->assertSee('@lilly');

        $this->actingAs($this->guest)->post("/participacoes/{$participacao->id}/aceitar");

        $this->actingAs($this->guest)->get('/projetos')
            ->assertSee('Projeto secreto')
            ->assertSee('Projeto de')
            ->assertDontSee('Excluir projeto');
    }

    public function test_the_owner_sees_the_invitations_they_sent_and_their_status(): void
    {
        $this->invite();

        $this->actingAs($this->owner)->get('/perfil?aba=notificacoes')
            ->assertOk()
            ->assertSee('@Joao')
            ->assertSee('Aguardando resposta');
        $this->actingAs($this->owner)->get('/projetos')
            ->assertSee('aguardando aceitar')
            ->assertSee('Convidar');
    }

    public function test_only_the_invited_user_can_answer_an_invitation(): void
    {
        $participacao = $this->invite();
        $third = User::factory()->create();

        $this->actingAs($third)->post("/participacoes/{$participacao->id}/aceitar")->assertNotFound();
        $this->actingAs($this->owner)->post("/participacoes/{$participacao->id}/aceitar")->assertNotFound();

        $this->assertSame('pendente', $participacao->fresh()->status);
    }

    public function test_a_refused_invitation_gives_no_access_and_can_be_sent_again(): void
    {
        $participacao = $this->invite();
        $this->actingAs($this->guest)->post("/participacoes/{$participacao->id}/recusar");

        $this->actingAs($this->guest)->get('/projetos')->assertDontSee('Projeto secreto');

        $again = $this->invite('ver');
        $this->assertSame($participacao->id, $again->id);
        $this->assertSame('pendente', $again->status);
    }

    public function test_a_participant_who_can_edit_manages_tasks_but_not_the_project_itself(): void
    {
        $this->accepted('editar');

        $this->actingAs($this->guest)->postJson("/projetos/{$this->projeto->id}/tarefas", ['title' => 'Minha parte'])
            ->assertOk();
        $this->assertDatabaseHas('projeto_tasks', ['projeto_id' => $this->projeto->id, 'title' => 'Minha parte']);

        $this->actingAs($this->guest)->deleteJson("/projetos/{$this->projeto->id}")->assertForbidden();
        $this->assertModelExists($this->projeto);
    }

    public function test_a_view_only_participant_cannot_change_anything(): void
    {
        $this->accepted('ver');
        $task = $this->projeto->tasks()->create(['title' => 'Tarefa do dono']);

        $this->actingAs($this->guest)->postJson("/projetos/{$this->projeto->id}/tarefas", ['title' => 'Invasão'])
            ->assertForbidden();
        $this->actingAs($this->guest)->patchJson("/projetos/{$this->projeto->id}/tarefas/{$task->id}", ['completed' => true])
            ->assertForbidden();
        $this->actingAs($this->guest)->deleteJson("/projetos/{$this->projeto->id}/tarefas/{$task->id}")
            ->assertForbidden();
        $this->actingAs($this->guest)->putJson("/projetos/{$this->projeto->id}", ['title' => 'Outro nome'])
            ->assertForbidden();

        $this->assertSame(1, $this->projeto->tasks()->count());
        $this->assertFalse($task->fresh()->completed);
        $this->actingAs($this->guest)->get('/projetos')->assertDontSee('Adicionar nova tarefa');
    }

    public function test_removing_a_participant_ends_their_access(): void
    {
        $participacao = $this->accepted();

        $this->actingAs($this->owner)->delete("/participacoes/{$participacao->id}");

        $this->assertModelMissing($participacao);
        $this->actingAs($this->guest)->get('/projetos')->assertDontSee('Projeto secreto');
        $this->actingAs($this->guest)->postJson("/projetos/{$this->projeto->id}/tarefas", ['title' => 'x'])
            ->assertNotFound();
    }

    public function test_a_stranger_cannot_remove_someone_elses_participation(): void
    {
        $participacao = $this->accepted();
        $third = User::factory()->create();

        $this->actingAs($third)->delete("/participacoes/{$participacao->id}")->assertNotFound();

        $this->assertModelExists($participacao);
    }

    public function test_pending_invitations_show_up_as_a_badge_on_every_page(): void
    {
        $this->invite();

        $this->actingAs($this->guest)->get('/tarefas')->assertSee('Convites pendentes');
        $this->actingAs($this->owner)->get('/tarefas')->assertDontSee('Convites pendentes');
    }
}
