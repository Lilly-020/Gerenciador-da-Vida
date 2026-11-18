<?php

namespace Tests\Feature;

use App\Models\Quadro;
use App\Models\QuadroCartao;
use App\Models\QuadroMembro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuadroCartaoDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_details_save_checklist_location_and_history_without_changing_title(): void
    {
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create(['title' => 'Módulo de rotinas']);
        $this->actingAs($owner)->patchJson(route('quadros.cartoes.update', [$board, $card]), ['checklist' => [['text' => 'Revisar', 'done' => true]], 'location' => 'Escritório', 'cover_color' => '#2563eb'])->assertOk();
        $card->refresh();
        $this->assertSame('Módulo de rotinas', $card->title);
        $this->assertSame([['text' => 'Revisar', 'done' => true]], $card->checklist);
        $this->assertSame('Escritório', $card->location);
        $this->assertSame($owner->name, $card->activity[0]['author']);
        $this->assertStringContainsString('checklist', $card->activity[0]['message']);
        $response = $this->getJson(route('quadros.cartoes.show', [$board, $card]))->assertOk();
        $this->assertStringContainsString('Revisar', $response->json('html'));
        $this->assertStringContainsString('Histórico', $response->json('html'));
        $this->getJson(route('quadros.comentarios.index', [$board, $card]))->assertJsonPath('count', 0)->assertJsonStructure(['history']);
    }

    public function test_card_rejects_invalid_checklist_and_viewers_cannot_change_it(): void
    {
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create();
        $this->actingAs($owner)->patchJson(route('quadros.cartoes.update', [$board, $card]), ['checklist' => [['text' => '', 'done' => 'yes']]])->assertUnprocessable()->assertJsonValidationErrors(['checklist.0.text', 'checklist.0.done']);
        $viewer = User::factory()->create();
        QuadroMembro::factory()->for($board)->for($viewer)->create(['status' => 'accepted', 'role' => 'viewer']);
        $this->actingAs($viewer)->patchJson(route('quadros.cartoes.update', [$board, $card]), ['checklist' => []])->assertForbidden();
        $this->assertNull($card->fresh()->checklist);
    }

    public function test_attachments_are_private_previewable_removable_and_can_be_used_as_cover(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create();
        $this->actingAs($owner)->postJson(route('quadros.arquivos.store', [$board, $card]), ['file' => UploadedFile::fake()->image('Capa.png')])->assertOk();
        $file = $card->fresh()->attachments[0];
        Storage::disk('local')->assertExists($file['path']);
        $url = route('quadros.arquivos.show', [$board, $card, $file['id']]);
        $this->get($url.'?preview=1')->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->patchJson(route('quadros.cartoes.update', [$board, $card]), ['cover_file_id' => $file['id']])->assertOk();
        $this->assertSame($file['id'], $card->fresh()->cover_file_id);
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get($url)->assertNotFound();
        $this->actingAs($owner)->deleteJson(route('quadros.arquivos.destroy', [$board, $card, $file['id']]))->assertOk();
        Storage::disk('local')->assertMissing($file['path']);
        $this->assertNull($card->fresh()->cover_file_id);
        $this->assertSame([], $card->fresh()->attachments);
    }

    public function test_attachment_endpoints_reject_unsafe_files_and_cross_card_access(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create();
        $other = QuadroCartao::factory()->for($board)->create();
        $this->actingAs($owner)->postJson(route('quadros.arquivos.store', [$board, $card]), ['file' => UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])->assertUnprocessable();
        $this->postJson(route('quadros.arquivos.store', [$board, $card]), ['file' => UploadedFile::fake()->create('Large.pdf', 10241, 'application/pdf')])->assertUnprocessable();
        $this->postJson(route('quadros.arquivos.store', [$board, $card]), ['file' => UploadedFile::fake()->image('File.png')])->assertOk();
        $file = $card->fresh()->attachments[0];
        $this->get(route('quadros.arquivos.show', [$board, $other, $file['id']]))->assertNotFound();
        $this->patchJson(route('quadros.cartoes.update', [$board, $other]), ['cover_file_id' => $file['id']])->assertUnprocessable();
        $viewer = User::factory()->create();
        QuadroMembro::factory()->for($board)->for($viewer)->create(['status' => 'accepted', 'role' => 'viewer']);
        $this->actingAs($viewer)->postJson(route('quadros.arquivos.store', [$board, $card]), ['file' => UploadedFile::fake()->image('No.png')])->assertForbidden();
        $this->deleteJson(route('quadros.arquivos.destroy', [$board, $card, $file['id']]))->assertForbidden();
        Storage::disk('local')->assertExists($file['path']);
    }

    public function test_deleting_card_or_board_cleans_up_private_attachments(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create();
        $this->actingAs($owner)->postJson(route('quadros.arquivos.store', [$board, $card]), ['file' => UploadedFile::fake()->image('File.png')])->assertOk();
        $path = $card->fresh()->attachments[0]['path'];
        $this->deleteJson(route('quadros.cartoes.destroy', [$board, $card]))->assertOk();
        Storage::disk('local')->assertMissing($path);
        $other = QuadroCartao::factory()->for($board)->create();
        $this->postJson(route('quadros.arquivos.store', [$board, $other]), ['file' => UploadedFile::fake()->image('Other.png')])->assertOk();
        $path = $other->fresh()->attachments[0]['path'];
        $this->deleteJson(route('quadros.destroy', $board))->assertOk();
        Storage::disk('local')->assertMissing($path);
    }
}
