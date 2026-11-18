<?php

namespace Tests\Feature;

use App\Models\Quadro;
use App\Models\QuadroCartao;
use App\Models\QuadroComentario;
use App\Models\QuadroLista;
use App\Models\QuadroMembro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuadroCardToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_label_colors_are_saved_and_rendered_safely_on_cards(): void
    {
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create();
        $this->actingAs($owner)->postJson(route('quadros.etiquetas.store', $board), ['name' => 'Personalizada', 'color' => '#123ABC'])->assertOk();
        $label = collect($board->fresh()->label_catalog)->firstWhere('name', 'Personalizada');
        $this->assertSame('#123abc', $label['color']);
        $this->patchJson(route('quadros.cartoes.update', [$board, $card]), ['labels' => ['Personalizada']])->assertOk();
        $html = $this->getJson(route('quadros.show', $board))->assertOk()->json('html');
        $this->assertStringContainsString('background-color:#123abc', $html);
        $this->assertStringContainsString('Personalizada', $html);
        $this->patchJson(route('quadros.etiquetas.update', [$board, $label['id']]), ['name' => 'Personalizada', 'color' => 'red;background:url(bad)'])->assertUnprocessable()->assertJsonValidationErrors('color');
        $this->postJson(route('quadros.etiquetas.store', $board), ['name' => 'Inválida', 'color' => ['bad']])->assertUnprocessable()->assertJsonValidationErrors('color');
        $this->assertSame('#123abc', collect($board->fresh()->label_catalog)->firstWhere('name', 'Personalizada')['color']);
    }

    public function test_card_label_panel_shows_presets_without_selecting_them_automatically(): void
    {
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create(['labels' => []]);
        $html = $this->actingAs($owner)->getJson(route('quadros.cartoes.show', [$board, $card]))->assertOk()->json('html');
        foreach (['Realizado', 'Importante', 'Melhorias', 'Não urgente', 'Novo módulo', 'Urgente', 'Ideia'] as $name) {
            $this->assertStringContainsString('value="'.$name.'"', $html);
        }
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(7, $xpath->query('//*[@data-quadro-label-list]//input[@type="checkbox"]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-quadro-label-list]//input[@checked]')->length);
    }

    public function test_rich_comments_keep_formatting_and_private_images_and_strip_active_content(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create();
        $file = $this->actingAs($owner)->postJson(route('quadros.arquivos.store', [$board, $card]), ['file' => UploadedFile::fake()->image('Print.png'), 'source' => 'comment'])->assertOk()->json('file');
        $rich = '<p><b>Importante</b> <u>revisar</u></p><ul><li>Primeiro</li></ul><img data-file-id="'.$file['id'].'" src="https://tracker.test/x" onerror="alert(1)"><script>alert(2)</script><a href="jav&#x61;script:alert(3)">Link</a><img src="data:image/svg+xml,bad">';
        $response = $this->postJson(route('quadros.comentarios.store', [$board, $card]), ['rich_body' => $rich])->assertOk();
        $comment = $card->comentarios()->first();
        $this->assertStringContainsString('<b>Importante</b>', $comment->body_html);
        $this->assertStringContainsString('<ul><li>Primeiro</li></ul>', $comment->body_html);
        $this->assertStringContainsString($file['url'].'?preview=1', $comment->body_html);
        foreach (['onerror', '<script', 'javascript:', 'tracker.test', 'data:image'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $comment->body_html);
        }
        $this->assertSame([$file['id']], $comment->file_ids);
        $this->assertStringContainsString('data-quadro-comment-pin', $response->json('html'));
        $this->assertSame('comment', $card->fresh()->attachments[0]['source']);
    }

    public function test_images_cannot_be_embedded_from_other_cards_and_empty_rich_comments_are_rejected(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $first = QuadroCartao::factory()->for($board)->create();
        $second = QuadroCartao::factory()->for($board)->create();
        $file = $this->actingAs($owner)->postJson(route('quadros.arquivos.store', [$board, $first]), ['file' => UploadedFile::fake()->image('Private.png')])->json('file');
        $this->postJson(route('quadros.comentarios.store', [$board, $second]), ['rich_body' => '<img data-file-id="'.$file['id'].'">'])->assertUnprocessable();
        $this->postJson(route('quadros.comentarios.store', [$board, $second]), ['rich_body' => '<script>bad()</script><p><br></p>'])->assertUnprocessable();
        $this->assertSame(0, $second->comentarios()->count());
    }

    public function test_pinning_comment_populates_pinned_files_and_editing_preserves_it(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create();
        $file = $this->actingAs($owner)->postJson(route('quadros.arquivos.store', [$board, $card]), ['file' => UploadedFile::fake()->image('Reference.png'), 'source' => 'comment'])->json('file');
        $this->postJson(route('quadros.comentarios.store', [$board, $card]), ['rich_body' => '<b>Referência</b><img data-file-id="'.$file['id'].'">'])->assertOk();
        $comment = $card->comentarios()->first();
        $response = $this->patchJson(route('quadros.comentarios.pin', [$board, $card, $comment]), ['pinned' => true])->assertOk();
        $this->assertTrue($comment->fresh()->pinned);
        $this->assertSame('Fixou um comentário.', collect($card->fresh()->activity)->last()['message']);
        $this->assertStringContainsString('Comentários fixados', $response->json('pinned'));
        $this->assertStringNotContainsString('Reference.png', $response->json('pinned'));
        $this->assertStringContainsString('Reference.png', $response->json('files'));
        $this->patchJson(route('quadros.comentarios.update', [$board, $card, $comment]), ['rich_body' => '<b>Outra referência</b><img data-file-id="'.$file['id'].'">'])->assertOk();
        $this->assertTrue($comment->fresh()->pinned);
        $this->assertNotNull($comment->fresh()->edited_at);
        $this->patchJson(route('quadros.comentarios.pin', [$board, $card, $comment]), ['pinned' => false])->assertOk();
        $this->assertFalse($comment->fresh()->pinned);
    }

    public function test_comment_reactions_toggle_without_marking_comment_as_edited(): void
    {
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create();
        $comment = QuadroComentario::factory()->for($card, 'cartao')->for($owner)->create();
        $url = route('quadros.comentarios.react', [$board, $card, $comment]);
        $this->actingAs($owner)->postJson($url)->assertOk();
        $this->assertCount(1, $comment->fresh()->reactions);
        $this->assertNull($comment->fresh()->edited_at);
        $this->postJson($url)->assertOk();
        $this->assertSame([], $comment->fresh()->reactions);
    }

    public function test_viewers_and_foreign_paths_cannot_pin_react_or_duplicate(): void
    {
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create();
        $comment = QuadroComentario::factory()->for($card, 'cartao')->for($owner)->create();
        $viewer = User::factory()->create();
        QuadroMembro::factory()->for($board)->for($viewer)->create(['role' => 'viewer', 'status' => 'accepted']);
        $this->actingAs($viewer)->patchJson(route('quadros.comentarios.pin', [$board, $card, $comment]), ['pinned' => true])->assertForbidden();
        $this->postJson(route('quadros.comentarios.react', [$board, $card, $comment]))->assertForbidden();
        $this->postJson(route('quadros.cartoes.copy', [$board, $card]), ['lista_id' => $card->lista_id])->assertForbidden();
        $other = QuadroCartao::factory()->for($board)->create();
        $this->actingAs($owner)->patchJson(route('quadros.comentarios.pin', [$board, $other, $comment]), ['pinned' => true])->assertNotFound();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->postJson(route('quadros.cartoes.copy', [$board, $card]), ['lista_id' => $card->lista_id])->assertNotFound();
    }

    public function test_duplication_copies_details_and_creates_independent_attachment_and_cover(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create(['title' => 'Planejar', 'checklists' => [['id' => (string) Str::uuid(), 'title' => 'Revisão', 'items' => [['text' => 'Validar', 'done' => false]]]]]);
        $file = $this->actingAs($owner)->postJson(route('quadros.arquivos.store', [$board, $card]), ['file' => UploadedFile::fake()->image('Cover.png')])->json('file');
        $card->cover_file_id = $file['id'];
        $card->save();
        $destination = QuadroLista::factory()->for($board)->create();
        $response = $this->postJson(route('quadros.cartoes.copy', [$board, $card]), ['lista_id' => $destination->id])->assertOk();
        $copy = QuadroCartao::findOrFail($response->json('id'));
        $this->assertSame('Planejar (cópia)', $copy->title);
        $this->assertSame($card->checklists, $copy->checklists);
        $this->assertSame($destination->id, $copy->lista_id);
        $this->assertNotSame($card->cover_file_id, $copy->cover_file_id);
        $this->assertSame($copy->attachments[0]['id'], $copy->cover_file_id);
        Storage::disk('local')->assertExists($copy->attachments[0]['path']);
        $this->deleteJson(route('quadros.cartoes.destroy', [$board, $card]))->assertOk();
        Storage::disk('local')->assertExists($copy->attachments[0]['path']);
        $foreign = QuadroLista::factory()->create();
        $this->postJson(route('quadros.cartoes.copy', [$board, $copy]), ['lista_id' => $foreign->id])->assertUnprocessable();
    }

    public function test_label_catalog_has_editable_defaults_and_renames_selected_labels_on_cards(): void
    {
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create(['labels' => ['Importante']]);
        $this->assertCount(7, $board->label_catalog);
        $important = collect($board->label_catalog)->firstWhere('name', 'Importante');
        $this->actingAs($owner)->patchJson(route('quadros.etiquetas.update', [$board, $important['id']]), ['name' => 'Prioridade', 'color' => 'rose'])->assertOk();
        $this->assertSame(['Prioridade'], $card->fresh()->labels);
        $this->postJson(route('quadros.etiquetas.store', $board), ['name' => 'Personalizada', 'color' => 'blue'])->assertOk();
        $this->assertCount(8, $board->fresh()->label_catalog);
        $this->postJson(route('quadros.etiquetas.store', $board), ['name' => 'personalizada', 'color' => 'blue'])->assertUnprocessable();
        $this->deleteJson(route('quadros.etiquetas.destroy', [$board, $important['id']]))->assertOk();
        $this->assertSame([], $card->fresh()->labels);
    }

    public function test_named_checklists_allow_empty_lists_and_validate_items(): void
    {
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create();
        $lists = [['id' => (string) Str::uuid(), 'title' => 'Compras', 'items' => []], ['id' => (string) Str::uuid(), 'title' => 'Entrega', 'items' => [['text' => 'Confirmar', 'done' => true]]]];
        $this->actingAs($owner)->patchJson(route('quadros.cartoes.update', [$board, $card]), ['checklists' => $lists])->assertOk();
        $this->assertSame($lists, $card->fresh()->checklists);
        $lists[0]['items'] = [['text' => '', 'done' => false]];
        $this->patchJson(route('quadros.cartoes.update', [$board, $card]), ['checklists' => $lists])->assertUnprocessable()->assertJsonValidationErrors('checklists.0.items.0.text');
    }
}
