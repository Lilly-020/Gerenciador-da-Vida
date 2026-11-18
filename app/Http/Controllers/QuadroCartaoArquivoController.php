<?php

namespace App\Http\Controllers;

use App\Models\Quadro;
use App\Models\QuadroCartao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class QuadroCartaoArquivoController extends Controller
{
    public function store(Request $request, Quadro $quadro, QuadroCartao $cartao): JsonResponse
    {
        abort_unless($cartao->quadro_id === $quadro->id, 404);
        abort_unless($quadro->canEdit($request->user()), 403);
        $request->validate(['file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif,pdf,txt,csv,doc,docx,xls,xlsx,zip', 'max:10240'], 'source' => ['sometimes', 'in:card,comment']]);
        $file = $request->file('file');
        $path = $file->store('quadro-cartoes/'.$cartao->id, 'local');
        abort_unless($path, 500, 'Não foi possível armazenar o arquivo.');
        try {
            $attachment = DB::transaction(function () use ($request, $quadro, $cartao, $file, $path): array {
                Quadro::whereKey($quadro->id)->lockForUpdate()->firstOrFail();
                $cartao->refresh();
                $files = $cartao->attachments ?? [];
                abort_if(count($files) >= 50, 422, 'O cartão pode ter até 50 arquivos.');
                $attachment = ['id' => (string) Str::uuid(), 'path' => $path, 'name' => mb_substr(basename($file->getClientOriginalName()), 0, 255), 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'at' => now()->toIso8601String(), 'source' => $request->input('source', 'card')];
                $files[] = $attachment;
                $cartao->attachments = $files;
                $cartao->recordActivity($request->user(), 'Anexou um arquivo: '.end($files)['name'].'.');
                $cartao->save();

                return $attachment;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return response()->json(['saved' => true, 'file' => ['id' => $attachment['id'], 'name' => $attachment['name'], 'mime' => $attachment['mime'], 'url' => route('quadros.arquivos.show', [$quadro, $cartao, $attachment['id']], absolute: false)]]);
    }

    public function show(Request $request, Quadro $quadro, QuadroCartao $cartao, string $arquivo): StreamedResponse
    {
        abort_unless($cartao->quadro_id === $quadro->id, 404);
        $file = collect($cartao->attachments ?? [])->firstWhere('id', $arquivo);
        abort_unless($file && Storage::disk('local')->exists($file['path']), 404);
        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'];
        if ($request->boolean('preview') && in_array($file['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return Storage::disk('local')->response($file['path'], $file['name'], [...$headers, 'Content-Type' => $file['mime']], 'inline')->setPrivate();
        }

        return Storage::disk('local')->download($file['path'], $file['name'], $headers)->setPrivate();
    }

    public function destroy(Request $request, Quadro $quadro, QuadroCartao $cartao, string $arquivo): JsonResponse
    {
        abort_unless($cartao->quadro_id === $quadro->id, 404);
        abort_unless($quadro->canEdit($request->user()), 403);
        $path = DB::transaction(function () use ($request, $quadro, $cartao, $arquivo): string {
            Quadro::whereKey($quadro->id)->lockForUpdate()->firstOrFail();
            $cartao->refresh();
            $files = collect($cartao->attachments ?? []);
            $file = $files->firstWhere('id', $arquivo);
            abort_unless($file, 404);
            $cartao->attachments = $files->reject(fn (array $item): bool => $item['id'] === $arquivo)->values()->all();
            if ($cartao->cover_file_id === $arquivo) {
                $cartao->cover_file_id = null;
            }
            $cartao->recordActivity($request->user(), 'Removeu um arquivo: '.$file['name'].'.');
            $cartao->save();

            return $file['path'];
        });
        Storage::disk('local')->delete($path);

        return response()->json(['saved' => true]);
    }
}
