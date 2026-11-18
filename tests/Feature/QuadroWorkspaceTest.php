<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\CursoFile;
use App\Models\Quadro;
use App\Models\QuadroCartao;
use App\Models\QuadroComentario;
use App\Models\QuadroLista;
use App\Models\QuadroMembro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuadroWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function board(User $owner): Quadro
    {
        return Quadro::factory()->for($owner)->create(['title' => 'Meu aprendizado']);
    }

    private function member(Quadro $board, string $role = 'editor', string $status = 'accepted'): User
    {
        $user = User::factory()->create();
        $board->membros()->create(['user_id' => $user->id, 'role' => $role, 'status' => $status]);

        return $user;
    }

    public function test_courses_page_shows_only_accessible_boards_and_creation_interface(): void
    {
        $owner = User::factory()->create();
        $board = $this->board($owner);
        Quadro::factory()->create(['title' => 'Quadro privado alheio']);
        $this->actingAs($owner)->get(route('cursos'))->assertOk()->assertSee('Meu aprendizado')->assertSee('Criar quadro')->assertSee('Por categoria')->assertDontSee('Quadro privado alheio')->assertDontSee('Horas estudadas');
    }

    public function test_creating_board_saves_category_cover_and_three_editable_lists(): void
    {
        $owner = User::factory()->create();
        $response = $this->actingAs($owner)->postJson(route('quadros.store'), ['title' => 'Estudar PHP', 'category' => 'Tecnologia', 'cover' => 'ocean'])->assertOk();
        $board = Quadro::firstOrFail();
        $this->assertSame($owner->id, $board->user_id);
        $this->assertSame('Tecnologia', $board->category);
        $this->assertSame(['A fazer', 'Em andamento', 'Concluído'], $board->listas->pluck('title')->all());
        $response->assertJsonPath('url', route('quadros.show', $board, absolute: false));
        $this->get($response->json('url'))->assertOk()->assertSee('Estudar PHP')->assertSee('Tabela')->assertSee('Compartilhar');
    }

    public function test_board_cover_is_validated_and_served_privately(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson(route('quadros.store'), ['title' => 'Estudos', 'cover' => 'navy', 'cover_image' => UploadedFile::fake()->image('capa.png')])->assertOk();
        $board = Quadro::firstOrFail();
        Storage::disk('local')->assertExists($board->cover_path);
        $this->get(route('quadros.cover', $board))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs(User::factory()->create())->get(route('quadros.cover', $board))->assertNotFound();
    }

    public function test_invalid_board_input_and_oversized_cover_are_rejected(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create())->postJson(route('quadros.store'), ['title' => '', 'cover' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors(['title', 'cover']);
        $this->postJson(route('quadros.store'), ['title' => 'Estudos', 'cover' => 'navy', 'cover_image' => UploadedFile::fake()->image('large.png')->size(8193)])->assertUnprocessable()->assertJsonValidationErrors('cover_image');
        $this->assertDatabaseCount('quadros', 0);
    }

    public function test_owner_can_change_cover_and_delete_board_without_deleting_legacy_courses(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $course = Curso::factory()->for($owner)->create();
        $board = $this->board($owner);
        $card = QuadroCartao::factory()->for($board)->create();
        $card->legacy_curso_id = $course->id;
        $card->save();
        $board->cover_path = 'quadro-covers/old.png';
        $board->save();
        Storage::disk('local')->put($board->cover_path, 'old');
        $this->actingAs($owner)->postJson(route('quadros.update', $board), ['title' => 'Novo título', 'cover' => 'forest', 'remove_cover' => true])->assertOk();
        $this->assertSame('Novo título', $board->fresh()->title);
        Storage::disk('local')->assertMissing('quadro-covers/old.png');
        $this->deleteJson(route('quadros.destroy', $board))->assertOk();
        $this->assertDatabaseMissing('quadro_cartoes', ['id' => $card->id]);
        $this->assertModelExists($course);
    }

    public function test_lists_can_be_created_renamed_recolored_and_reordered(): void
    {
        $owner = User::factory()->create();
        $board = $this->board($owner);
        $this->actingAs($owner)->postJson(route('quadros.listas.store', $board), ['title' => 'Ideias', 'color' => '#334155'])->assertOk();
        $first = $board->listas()->firstOrFail();
        $second = QuadroLista::factory()->for($board)->create(['position' => 1]);
        $this->patchJson(route('quadros.listas.update', [$board, $first]), ['title' => 'Novas ideias', 'color' => '#ff00ff'])->assertOk();
        $this->assertSame('#ff00ff', $first->fresh()->color);
        $this->get(route('quadros.show', $board))->assertOk()->assertSee('--quadro-list-color:#ff00ff', false);
        $this->putJson(route('quadros.listas.ordem', $board), ['ids' => [$second->id, $first->id]])->assertOk();
        $this->assertSame([$second->id, $first->id], $board->listas()->pluck('id')->all());
        $this->deleteJson(route('quadros.listas.destroy', [$board, $first]))->assertOk();
        $this->assertModelMissing($first);
    }

    public function test_nonempty_lists_cannot_be_deleted_and_foreign_lists_cannot_be_reordered(): void
    {
        $owner = User::factory()->create();
        $board = $this->board($owner);
        $card = QuadroCartao::factory()->for($board)->create();
        $foreign = QuadroLista::factory()->create();
        $this->actingAs($owner)->deleteJson(route('quadros.listas.destroy', [$board, $card->lista]))->assertUnprocessable();
        $this->putJson(route('quadros.listas.ordem', $board), ['ids' => [$foreign->id]])->assertUnprocessable();
        $this->patchJson(route('quadros.listas.update', [$board, $foreign]), ['title' => 'Errado', 'color' => '#ffffff'])->assertNotFound();
        $this->assertModelExists($card);
    }

    public function test_cards_save_description_tags_responsible_users_and_delivery_date(): void
    {
        $owner = User::factory()->create();
        $board = $this->board($owner);
        $list = QuadroLista::factory()->for($board)->create();
        $editor = $this->member($board);
        $response = $this->actingAs($editor)->postJson(route('quadros.cartoes.store', $board), ['title' => 'Módulo 1', 'description' => 'Estudar o básico', 'lista_id' => $list->id, 'status' => 'doing', 'labels_text' => 'Importante, Revisar', 'assignee_ids' => [$owner->id, $editor->id], 'due_at' => '2026-12-01T14:00'])->assertOk();
        $card = QuadroCartao::findOrFail($response->json('id'));
        $this->assertSame(['Importante', 'Revisar'], $card->labels);
        $this->assertSame([$owner->id, $editor->id], $card->assignee_ids);
        $this->assertSame('2026-12-01 14:00', $card->due_at->format('Y-m-d H:i'));
        $this->getJson(route('quadros.cartoes.show', [$board, $card]))->assertOk();
        $this->patchJson(route('quadros.cartoes.update', [$board, $card]), ['title' => 'Módulo atualizado', 'lista_id' => $list->id, 'status' => 'done', 'assignee_ids' => [], 'labels' => []])->assertOk();
        $this->assertSame('done', $card->fresh()->status);
        $this->assertSame([], $card->fresh()->assignee_ids);
    }

    public function test_cards_can_move_between_lists_and_keep_manual_order(): void
    {
        $owner = User::factory()->create();
        $board = $this->board($owner);
        $first = QuadroCartao::factory()->for($board)->create();
        $destination = QuadroLista::factory()->for($board)->create();
        $second = QuadroCartao::factory()->for($board)->create(['lista_id' => $destination->id, 'position' => 0]);
        $third = QuadroCartao::factory()->for($board)->create(['lista_id' => $destination->id, 'position' => 1]);
        $this->actingAs($owner)->patchJson(route('quadros.cartoes.move', [$board, $first]), ['lista_id' => $destination->id, 'before_id' => $third->id])->assertOk();
        $this->assertSame([$second->id, $first->id, $third->id], $destination->cartoes()->pluck('id')->all());
    }

    public function test_card_validation_rejects_foreign_lists_users_and_nested_paths(): void
    {
        $owner = User::factory()->create();
        $board = $this->board($owner);
        $card = QuadroCartao::factory()->for($board)->create();
        $foreign = QuadroCartao::factory()->create();
        $outsider = User::factory()->create();
        $this->actingAs($owner)->postJson(route('quadros.cartoes.store', $board), ['title' => 'Inválido', 'lista_id' => $foreign->lista_id, 'assignee_ids' => [$outsider->id]])->assertUnprocessable()->assertJsonValidationErrors(['lista_id', 'assignee_ids.0']);
        $this->getJson(route('quadros.cartoes.show', [$board, $foreign]))->assertNotFound();
        $this->patchJson(route('quadros.cartoes.move', [$board, $card]), ['lista_id' => $foreign->lista_id])->assertUnprocessable();
        $this->patchJson(route('quadros.cartoes.update', [$board, $foreign]), ['title' => 'Errado', 'lista_id' => $card->lista_id])->assertNotFound();
    }

    public function test_viewers_can_read_board_and_comments_but_cannot_mutate_content(): void
    {
        $owner = User::factory()->create();
        $board = $this->board($owner);
        $card = QuadroCartao::factory()->for($board)->create();
        $viewer = $this->member($board, 'viewer');
        $this->actingAs($viewer)->get(route('quadros.show', $board))->assertOk();
        $this->getJson(route('quadros.cartoes.show', [$board, $card]))->assertOk();
        $this->postJson(route('quadros.listas.store', $board), ['title' => 'No', 'color' => '#ffffff'])->assertForbidden();
        $this->deleteJson(route('quadros.cartoes.destroy', [$board, $card]))->assertForbidden();
        $this->postJson(route('quadros.comentarios.store', [$board, $card]), ['body' => 'No'])->assertForbidden();
        $this->postJson(route('quadros.update', $board), ['title' => 'No', 'cover' => 'blue'])->assertForbidden();
    }

    public function test_invitation_must_be_accepted_before_shared_board_can_be_accessed(): void
    {
        $owner = User::factory()->create();
        $board = $this->board($owner);
        $guest = User::factory()->create(['apelido' => 'colega']);
        $this->actingAs($owner)->postJson(route('quadros.membros.store', $board), ['handle' => '@colega', 'role' => 'editor'])->assertOk();
        $invite = QuadroMembro::firstOrFail();
        $this->actingAs($guest)->get(route('cursos'))->assertOk()->assertSee('Convites para quadros')->assertSee('Meu aprendizado');
        $this->get(route('quadros.show', $board))->assertNotFound();
        $this->postJson(route('quadros.convites.respond', $invite), ['accept' => true])->assertOk();
        $this->get(route('quadros.show', $board))->assertOk();
        $this->assertSame('accepted', $invite->fresh()->status);
    }

    public function test_membership_changes_and_revocation_are_enforced(): void
    {
        $owner = User::factory()->create();
        $board = $this->board($owner);
        $editor = $this->member($board);
        $member = $board->membros()->firstOrFail();
        $this->actingAs($editor)->postJson(route('quadros.membros.store', $board), ['handle' => 'another', 'role' => 'viewer'])->assertForbidden();
        $this->actingAs($owner)->patchJson(route('quadros.membros.update', [$board, $member]), ['role' => 'viewer'])->assertOk();
        $this->assertSame('viewer', $member->fresh()->role);
        $this->deleteJson(route('quadros.membros.destroy', [$board, $member]))->assertOk();
        $this->actingAs($editor)->get(route('quadros.show', $board))->assertNotFound();
    }

    public function test_only_invited_user_can_accept_and_only_owner_can_delete_board(): void
    {
        $owner = User::factory()->create();
        $board = $this->board($owner);
        $invited = $this->member($board, 'admin', 'pending');
        $invite = $board->membros()->firstOrFail();
        $this->actingAs($owner)->postJson(route('quadros.convites.respond', $invite), ['accept' => true])->assertNotFound();
        $this->actingAs($invited)->postJson(route('quadros.convites.respond', $invite), ['accept' => true])->assertOk();
        $this->deleteJson(route('quadros.destroy', $board))->assertForbidden();
    }

    public function test_card_comments_have_escaped_text_edit_dates_and_incremental_sync(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(16, 0));
        $owner = User::factory()->create();
        $board = $this->board($owner);
        $card = QuadroCartao::factory()->for($board)->create();
        $editor = $this->member($board);
        $this->actingAs($editor)->postJson(route('quadros.comentarios.store', [$board, $card]), ['body' => '<script>Texto</script>'])->assertOk();
        $comment = $card->comentarios()->firstOrFail();
        $this->travel(2)->minutes();
        $edited = $this->patchJson(route('quadros.comentarios.update', [$board, $card, $comment]), ['body' => 'Nova versão'])->assertOk();
        $this->assertStringContainsString('Editado em 17/11/2026 16:02:00', $edited->json('html'));
        $revision = $edited->json('revision');
        $this->getJson(route('quadros.comentarios.index', [$board, $card]).'?revision='.$revision)->assertExactJson(['revision' => $revision]);
        $this->actingAs($owner)->patchJson(route('quadros.comentarios.update', [$board, $card, $comment]), ['body' => 'Não é meu'])->assertForbidden();
        $this->deleteJson(route('quadros.comentarios.destroy', [$board, $card, $comment]))->assertOk();
        $this->assertModelMissing($comment);
    }

    public function test_courses_are_imported_without_losing_notes_progress_or_materials(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $course = Curso::factory()->for($owner)->create(['title' => 'Curso antigo', 'notes' => 'Minhas anotações', 'total_lessons' => 10, 'completed_lessons' => 2]);
        $file = CursoFile::factory()->for($course)->create();
        Storage::disk('public')->put($file->path, 'Material original');
        $path = glob(database_path('migrations/*_import_existing_cursos_into_quadros.php'))[0];
        $migration = require $path;
        $migration->up();
        $migration->up();
        $card = QuadroCartao::where('legacy_curso_id', $course->id)->firstOrFail();
        $this->assertSame('Curso antigo', $card->title);
        $this->assertStringContainsString('Minhas anotações', $card->description);
        $this->assertSame(2, $card->legacy_data['completed_lessons']);
        $this->assertDatabaseCount('quadro_cartoes', 1);
        $this->assertModelExists($course);
        $this->actingAs($owner)->getJson(route('quadros.cartoes.show', [$card->quadro, $card]))->assertOk();
        $this->get(route('quadros.legacy-file', [$card->quadro, $card, $file]))->assertDownload($file->original_name);
    }

    public function test_guests_and_outsiders_cannot_read_or_mutate_boards(): void
    {
        $owner = User::factory()->create();
        $board = $this->board($owner);
        $this->get(route('quadros.show', $board))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->getJson(route('quadros.show', $board))->assertNotFound();
        $this->postJson(route('quadros.listas.store', $board), ['title' => 'Errado', 'color' => '#ffffff'])->assertNotFound();
    }

    public function test_dashboard_opens_boards_from_the_courses_widget(): void
    {
        $owner = User::factory()->create();
        $board = $this->board($owner);
        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertSee('Meu aprendizado')->assertSee(route('quadros.show', $board, absolute: false), false);
    }

    public function test_creation_can_invite_a_user_without_giving_access_before_acceptance(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['apelido' => 'amigadeestudos']);
        $this->actingAs($owner)->postJson(route('quadros.store'), ['title' => 'Juntos', 'cover' => 'violet', 'invite_handle' => '@AMIGADEESTUDOS'])->assertOk();
        $board = Quadro::firstOrFail();
        $this->assertDatabaseHas('quadro_membros', ['quadro_id' => $board->id, 'user_id' => $invitee->id, 'role' => 'editor', 'status' => 'pending']);
        $this->actingAs($invitee)->get(route('quadros.show', $board))->assertNotFound();
    }

    public function test_new_card_form_uses_current_lists_and_is_unavailable_to_viewers(): void
    {
        $owner = User::factory()->create();
        $board = $this->board($owner);
        $list = QuadroLista::factory()->for($board)->create(['title' => 'Minha lista nova']);
        $response = $this->actingAs($owner)->getJson(route('quadros.cartoes.create', ['quadro' => $board, 'lista_id' => $list->id]))->assertOk();
        $this->assertStringContainsString('Minha lista nova', $response->json('html'));
        $this->assertStringContainsString('Criar cartão', $response->json('html'));
        $viewer = $this->member($board, 'viewer');
        $this->actingAs($viewer)->getJson(route('quadros.cartoes.create', ['quadro' => $board, 'lista_id' => $list->id]))->assertForbidden();
    }

    public function test_incomplete_list_order_and_unsafe_colors_are_rejected(): void
    {
        $owner = User::factory()->create();
        $board = $this->board($owner);
        $first = QuadroLista::factory()->for($board)->create(['position' => 0]);
        QuadroLista::factory()->for($board)->create(['position' => 1]);
        $this->actingAs($owner)->putJson(route('quadros.listas.ordem', $board), ['ids' => [$first->id]])->assertConflict();
        $this->patchJson(route('quadros.listas.update', [$board, $first]), ['title' => 'Lista', 'color' => 'url(javascript:alert(1))'])->assertUnprocessable()->assertJsonValidationErrors('color');
        $this->assertSame('#334155', $first->fresh()->color);
    }

    public function test_deleting_card_removes_comments_and_cannot_delete_card_in_another_board(): void
    {
        $owner = User::factory()->create();
        $board = $this->board($owner);
        $card = QuadroCartao::factory()->for($board)->create();
        $comment = QuadroComentario::factory()->for($card, 'cartao')->for($owner)->create();
        $other = $this->board($owner);
        $this->actingAs($owner)->deleteJson(route('quadros.cartoes.destroy', [$other, $card]))->assertNotFound();
        $this->deleteJson(route('quadros.cartoes.destroy', [$board, $card]))->assertOk();
        $this->assertModelMissing($card);
        $this->assertModelMissing($comment);
    }

    public function test_card_content_and_comments_are_escaped_and_foreign_comment_paths_rejected(): void
    {
        $owner = User::factory()->create();
        $board = $this->board($owner);
        $card = QuadroCartao::factory()->for($board)->create(['title' => '<script>title</script>', 'description' => '<img src=x onerror=alert(1)>', 'labels' => ['<script>tag</script>']]);
        $this->actingAs($owner);
        $html = $this->getJson(route('quadros.show', $board))->assertOk()->json('html');
        $this->assertStringContainsString('&lt;script&gt;title&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $commentResponse = $this->postJson(route('quadros.comentarios.store', [$board, $card]), ['body' => '<script>comment</script>'])->assertOk();
        $this->assertStringContainsString('&lt;script&gt;', $commentResponse->json('html'));
        $this->assertStringNotContainsString('<script>', $commentResponse->json('html'));
        $comment = $card->comentarios()->firstOrFail();
        $otherCard = QuadroCartao::factory()->for($board)->create();
        $this->patchJson(route('quadros.comentarios.update', [$board, $otherCard, $comment]), ['body' => 'Wrong'])->assertNotFound();
        $this->deleteJson(route('quadros.comentarios.destroy', [$board, $otherCard, $comment]))->assertNotFound();
        $this->postJson(route('quadros.comentarios.store', [$board, $card]), ['body' => str_repeat('x', 5001)])->assertUnprocessable()->assertJsonValidationErrors('body');
    }

    public function test_owner_can_open_edit_form_directly_from_board_tile(): void
    {
        $owner = User::factory()->create();
        $board = $this->board($owner);
        $this->actingAs($owner)->get(route('cursos'))->assertOk()->assertSee('data-quadro-tile-edit', false)->assertSee('data-quadro-tile-delete', false);
        $response = $this->getJson(route('quadros.edit', $board))->assertOk();
        $this->assertStringContainsString('Configurar quadro', $response->json('html'));
        $this->assertStringContainsString('value="Meu aprendizado"', $response->json('html'));
        $this->assertStringContainsString('id="quadro-edit-dialog"', $response->json('html'));
        $this->assertStringContainsString(route('quadros.update', $board, absolute: false), $response->json('html'));
    }

    public function test_board_tile_actions_and_edit_form_follow_membership_permissions(): void
    {
        $owner = User::factory()->create();
        $board = $this->board($owner);
        $admin = $this->member($board, 'admin');
        $editor = $this->member($board, 'editor');
        $viewer = $this->member($board, 'viewer');
        $this->actingAs($admin)->get(route('cursos'))->assertSee('data-quadro-tile-edit', false)->assertDontSee('data-quadro-tile-delete', false);
        $this->getJson(route('quadros.edit', $board))->assertOk();
        foreach ([$editor, $viewer] as $user) {
            $this->actingAs($user)->get(route('cursos'))->assertDontSee('data-quadro-tile-edit', false)->assertDontSee('data-quadro-tile-delete', false);
            $this->getJson(route('quadros.edit', $board))->assertForbidden();
        }
        $this->actingAs(User::factory()->create())->getJson(route('quadros.edit', $board))->assertNotFound();
    }

    public function test_new_list_can_be_inserted_between_existing_lists(): void
    {
        $owner = User::factory()->create();
        $board = $this->board($owner);
        $first = QuadroLista::factory()->for($board)->create(['title' => 'Primeira', 'position' => 0]);
        $last = QuadroLista::factory()->for($board)->create(['title' => 'Última', 'position' => 1]);
        $this->actingAs($owner)->postJson(route('quadros.listas.store', $board), ['title' => 'No meio', 'color' => '#334155', 'before_id' => $last->id])->assertOk();
        $this->assertSame(['Primeira', 'No meio', 'Última'], $board->listas()->pluck('title')->all());
        $this->assertSame([0, 1, 2], $board->listas()->pluck('position')->all());
        $this->assertSame(0, $first->fresh()->position);
        $this->get(route('quadros.show', $board))->assertSee('data-quadro-insert-list', false);
    }

    public function test_inserting_a_column_rejects_a_destination_in_another_board(): void
    {
        $owner = User::factory()->create();
        $board = $this->board($owner);
        $other = QuadroLista::factory()->create();
        $this->actingAs($owner)->postJson(route('quadros.listas.store', $board), ['title' => 'Inválida', 'color' => '#334155', 'before_id' => $other->id])->assertUnprocessable()->assertJsonValidationErrors('before_id');
        $this->assertSame(0, $board->listas()->count());
    }
}
