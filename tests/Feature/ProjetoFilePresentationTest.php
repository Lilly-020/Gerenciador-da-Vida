<?php

namespace Tests\Feature;

use App\Models\Participacao;
use App\Models\Projeto;
use App\Models\ProjetoFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjetoFilePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_project_shows_attachment_button_without_an_empty_files_section(): void
    {
        $owner = User::factory()->create();
        Projeto::factory()->for($owner)->create();

        $this->actingAs($owner)->get(route('projetos'))->assertOk()
            ->assertSee('data-project-attach', false)->assertSee('data-project-file-input', false)
            ->assertDontSee('data-project-section="files"', false)->assertDontSee('Anexos (0)');
    }

    public function test_image_upload_returns_list_with_thumbnail_name_and_date(): void
    {
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(15, 30));
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();

        $response = $this->actingAs($owner)->postJson(route('projetos.arquivos.store', $project), ['file' => UploadedFile::fake()->image('imagem.png')])->assertOk();

        $file = $project->files()->firstOrFail();
        Storage::disk('local')->assertExists($file->path);
        $this->assertSame($owner->id, $file->user_id);
        $html = $response->json('html');
        $this->assertStringContainsString('imagem.png', $html);
        $this->assertStringContainsString('17/11/2026 15:30', $html);
        $this->assertStringContainsString('<img ', $html);
        $this->assertStringContainsString(route('projetos.arquivos.preview', [$project, $file], absolute: false), $html);
        $this->assertStringContainsString('Baixar arquivo', $html);
        $this->assertStringContainsString('Remover anexo', $html);
    }

    public function test_image_preview_is_inline_and_private(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $this->actingAs($owner)->postJson(route('projetos.arquivos.store', $project), ['file' => UploadedFile::fake()->image('imagem.png')])->assertOk();
        $file = $project->files()->firstOrFail();

        $response = $this->get(route('projetos.arquivos.preview', [$project, $file]))->assertOk()
            ->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
    }

    public function test_documents_show_file_type_instead_of_an_image_and_can_be_removed_without_reload(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $response = $this->actingAs($owner)->postJson(route('projetos.arquivos.store', $project), ['file' => UploadedFile::fake()->create('brief.pdf', 50, 'application/pdf')])->assertOk();
        $this->assertStringNotContainsString('<img ', $response->json('html'));
        $this->assertStringContainsString('pdf', $response->json('html'));
        $file = $project->files()->firstOrFail();

        $deleted = $this->deleteJson(route('projetos.arquivos.destroy', [$project, $file]))->assertOk();

        $this->assertStringNotContainsString('data-project-section="files"', $deleted->json('html'));
        $this->assertModelMissing($file);
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_preview_rejects_non_images_even_when_named_like_an_image(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $file = ProjetoFile::factory()->for($project)->create(['path' => 'projeto-files/fake.png']);
        Storage::disk('local')->put($file->path, '<html>Not an image</html>');

        $this->actingAs($owner)->get(route('projetos.arquivos.preview', [$project, $file]))->assertStatus(415);
    }

    public function test_preview_rejects_outsiders_mismatched_projects_and_missing_files(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $other = Projeto::factory()->for($owner)->create();
        $file = ProjetoFile::factory()->for($project)->create(['path' => 'projeto-files/missing.png']);

        $this->actingAs(User::factory()->create())->get(route('projetos.arquivos.preview', [$project, $file]))->assertNotFound();
        $this->actingAs($owner)->get(route('projetos.arquivos.preview', [$other, $file]))->assertNotFound();
        $this->get(route('projetos.arquivos.preview', [$project, $file]))->assertNotFound();
    }

    public function test_read_only_participants_can_preview_images_but_cannot_remove_them(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        Participacao::create(['owner_id' => $owner->id, 'participant_id' => $viewer->id, 'projeto_id' => $project->id, 'tipo' => 'projeto', 'status' => 'aceito', 'permission' => 'ver']);
        $this->actingAs($owner)->postJson(route('projetos.arquivos.store', $project), ['file' => UploadedFile::fake()->image('foto.png')])->assertOk();
        $file = $project->files()->firstOrFail();

        $this->actingAs($viewer)->get(route('projetos.arquivos.preview', [$project, $file]))->assertOk();
        $this->deleteJson(route('projetos.arquivos.destroy', [$project, $file]))->assertForbidden();
        $this->postJson(route('projetos.arquivos.store', $project), ['file' => UploadedFile::fake()->image('outra.png')])->assertForbidden();
        $this->assertModelExists($file);
    }

    public function test_invalid_or_oversized_files_are_rejected_and_file_names_are_escaped(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $project = Projeto::factory()->for($owner)->create();
        $this->actingAs($owner)->postJson(route('projetos.arquivos.store', $project), ['file' => UploadedFile::fake()->create('too-big.pdf', 10241, 'application/pdf')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson(route('projetos.arquivos.store', $project), ['file' => UploadedFile::fake()->create('script.html', 1, 'text/html')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $file = ProjetoFile::factory()->for($project)->create(['original_name' => '<script>alert(1)</script>.pdf']);
        $html = view('partials.projeto-files', ['projeto' => $project->load('files'), 'canEdit' => true])->render();
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString($file->original_name, $html);
    }
}
