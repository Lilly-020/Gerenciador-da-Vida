<?php

namespace App\Http\Controllers;

use App\Models\Quadro;
use App\Models\QuadroCartao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class QuadroCartaoActionController extends Controller
{
    public function store(Request $request, Quadro $quadro, QuadroCartao $cartao): JsonResponse
    {
        abort_unless($cartao->quadro_id === $quadro->id, 404);
        abort_unless($quadro->canEdit($request->user()), 403);
        $data = $request->validate(['lista_id' => ['required', 'integer', Rule::exists('quadro_listas', 'id')->where('quadro_id', $quadro->id)], 'title' => ['sometimes', 'required', 'string', 'max:255']]);
        $paths = [];
        try {
            $copy = DB::transaction(function () use ($request, $quadro, $cartao, $data, &$paths): QuadroCartao {
                Quadro::whereKey($quadro->id)->lockForUpdate()->firstOrFail();
                $cartao->refresh();
                $copy = $quadro->cartoes()->create([
                    ...$cartao->only(['title', 'description', 'status', 'labels', 'assignee_ids', 'due_at', 'starts_on', 'reminder_minutes', 'checklist', 'checklists', 'location', 'cover_color']),
                    'title' => $data['title'] ?? mb_substr($cartao->title.' (cópia)', 0, 255),
                    'lista_id' => $data['lista_id'],
                    'position' => ($quadro->cartoes()->where('lista_id', $data['lista_id'])->max('position') ?? -1) + 1,
                ]);
                $copy->created_by = $request->user()->id;
                $files = [];
                foreach ($cartao->attachments ?? [] as $file) {
                    if (($file['source'] ?? 'card') === 'comment' && $cartao->cover_file_id !== $file['id']) {
                        continue;
                    }
                    $id = (string) Str::uuid();
                    $path = 'quadro-cartoes/'.$copy->id.'/'.$id.'.'.pathinfo($file['path'], PATHINFO_EXTENSION);
                    abort_unless(Storage::disk('local')->copy($file['path'], $path), 500, 'Não foi possível copiar o anexo.');
                    $paths[] = $path;
                    $files[] = [...$file, 'id' => $id, 'path' => $path, 'source' => 'card', 'at' => now()->toIso8601String()];
                    if ($cartao->cover_file_id === $file['id']) {
                        $copy->cover_file_id = $id;
                    }
                }
                $copy->attachments = $files;
                $copy->recordActivity($request->user(), 'Criou uma cópia de '.$cartao->title.'.');
                $copy->save();

                return $copy;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }

        return response()->json(['id' => $copy->id, 'url' => route('quadros.cartoes.show', [$quadro, $copy], absolute: false)]);
    }
}
