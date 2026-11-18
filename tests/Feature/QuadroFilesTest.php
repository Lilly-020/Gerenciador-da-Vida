<?php

namespace Tests\Feature;

use App\Models\Quadro;
use App\Models\QuadroCartao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class QuadroFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_comment_attachments_appear_in_files_without_pinning_and_stay_out_of_pinned_comments(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create();
        $url = route('quadros.comentarios.index', [$board, $card]);
        $revision = $this->actingAs($owner)->getJson($url)->json('revision');
        $file = $this->postJson(route('quadros.arquivos.store', [$board, $card]), ['file' => UploadedFile::fake()->image('Chat.png'), 'source' => 'comment'])->assertOk()->json('file');
        $response = $this->getJson($url.'?revision='.$revision)->assertOk();
        $this->assertStringContainsString('Chat.png', $response->json('files'));
        $this->assertStringContainsString('data-quadro-file-menu', $response->json('files'));
        $this->assertStringContainsString('Visualizar arquivo', $response->json('files'));
        $this->assertStringContainsString('Baixar arquivo', $response->json('files'));
        $this->postJson(route('quadros.comentarios.store', [$board, $card]), ['rich_body' => '<p>Referência</p><img data-file-id="'.$file['id'].'">'])->assertOk();
        $comment = $card->comentarios()->firstOrFail();
        $pinned = $this->patchJson(route('quadros.comentarios.pin', [$board, $card, $comment]), ['pinned' => true])->assertOk()->json('pinned');
        $this->assertStringContainsString('Referência', $pinned);
        $this->assertStringNotContainsString('Chat.png', $pinned);
        $this->assertStringNotContainsString('<img', $pinned);
    }

    #[TestWith(['Movie.mp4', 'video/mp4', true])]
    #[TestWith(['Movie.webm', 'video/webm', true])]
    #[TestWith(['Movie.mov', 'video/quicktime', true])]
    #[TestWith(['Document.pdf', 'application/pdf', true])]
    #[TestWith(['Document.doc', 'application/msword', false])]
    public function test_file_actions_and_inline_responses_follow_the_file_type(string $name, string $mime, bool $preview): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create();
        $file = $this->actingAs($owner)->postJson(route('quadros.arquivos.store', [$board, $card]), ['file' => UploadedFile::fake()->create($name, 10, $mime)])->assertOk()->json('file');
        $response = $this->get($file['url'].'?preview=1')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith($preview ? 'inline;' : 'attachment;', $response->headers->get('Content-Disposition'));
        if ($preview) {
            $response->assertHeader('Content-Type', $mime);
        }
        $this->get($file['url'])->assertDownload($name);
        $html = $this->getJson(route('quadros.cartoes.show', [$board, $card]))->assertOk()->json('html');
        if ($preview) {
            $this->assertStringContainsString('Visualizar arquivo', $html);
        } else {
            $this->assertStringNotContainsString('Visualizar arquivo', $html);
        }
        if ($mime === 'application/pdf') {
            $this->assertStringContainsString('target="_blank"', $html);
        }
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get($file['url'].'?preview=1')->assertNotFound();
    }

    public function test_video_preview_supports_partial_requests_for_playback_and_seeking(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create();
        $file = $this->actingAs($owner)->postJson(route('quadros.arquivos.store', [$board, $card]), ['file' => UploadedFile::fake()->createWithContent('Clip.mp4', str_repeat('video', 100))])->assertOk()->json('file');
        $response = $this->withHeader('Range', 'bytes=0-15')->get($file['url'].'?preview=1');
        $response->assertStatus(206);
        $response->assertHeader('Content-Range', 'bytes 0-15/500')->assertHeader('Content-Length', '16');
    }

    public function test_svg_content_and_svg_filename_disguised_as_an_image_are_rejected(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $board = Quadro::factory()->for($owner)->create();
        $card = QuadroCartao::factory()->for($board)->create();
        $url = route('quadros.arquivos.store', [$board, $card]);
        $svg = UploadedFile::fake()->createWithContent('unsafe.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->actingAs($owner)->postJson($url, ['file' => new UploadedFile($svg->getRealPath(), 'unsafe.png', 'image/png', null, true)])->assertUnprocessable()->assertJsonValidationErrors('file');
        $image = UploadedFile::fake()->image('safe.png');
        $this->postJson($url, ['file' => new UploadedFile($image->getRealPath(), 'disguised.svg', 'image/png', null, true)])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertEmpty($card->fresh()->attachments);
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }
}
