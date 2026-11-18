<?php

namespace App\Http\Controllers;

use App\Models\Quadro;
use App\Models\QuadroLista;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuadroListaController extends Controller
{
    public function store(Request $request, Quadro $quadro): JsonResponse
    {
        abort_unless($quadro->canEdit($request->user()), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'before_id' => ['nullable', 'integer', Rule::exists('quadro_listas', 'id')->where('quadro_id', $quadro->id)],
        ]);
        DB::transaction(function () use ($quadro, $data): void {
            Quadro::whereKey($quadro->id)->lockForUpdate()->firstOrFail();
            $ids = $quadro->listas()->pluck('id')->all();
            $index = isset($data['before_id']) ? array_search((int) $data['before_id'], $ids, true) : count($ids);
            abort_if($index === false, 409, 'A lista mudou. Atualize o quadro e tente novamente.');
            $list = $quadro->listas()->create(['title' => $data['title'], 'color' => $data['color'], 'position' => count($ids)]);
            array_splice($ids, $index, 0, [$list->id]);
            foreach ($ids as $position => $id) {
                $quadro->listas()->whereKey($id)->update(['position' => $position]);
            }
        });

        return response()->json(['saved' => true]);
    }

    public function update(Request $request, Quadro $quadro, QuadroLista $lista): JsonResponse
    {
        abort_unless($lista->quadro_id === $quadro->id, 404);
        abort_unless($quadro->canEdit($request->user()), 403);
        $lista->update($request->validate(['title' => ['required', 'string', 'max:100'], 'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/']]));

        return response()->json(['saved' => true]);
    }

    public function reorder(Request $request, Quadro $quadro): JsonResponse
    {
        abort_unless($quadro->canEdit($request->user()), 403);
        $data = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['required', 'integer', 'distinct', Rule::exists('quadro_listas', 'id')->where('quadro_id', $quadro->id)]]);
        DB::transaction(function () use ($quadro, $data): void {
            Quadro::whereKey($quadro->id)->lockForUpdate()->firstOrFail();
            abort_unless($quadro->listas()->count() === count($data['ids']), 409, 'O quadro mudou. Atualize a página.');
            foreach ($data['ids'] as $position => $id) {
                $quadro->listas()->whereKey($id)->update(['position' => $position]);
            }
        });

        return response()->json(['saved' => true]);
    }

    public function destroy(Request $request, Quadro $quadro, QuadroLista $lista): JsonResponse
    {
        abort_unless($lista->quadro_id === $quadro->id, 404);
        abort_unless($quadro->canEdit($request->user()), 403);
        DB::transaction(function () use ($quadro, $lista): void {
            Quadro::whereKey($quadro->id)->lockForUpdate()->firstOrFail();
            abort_if($lista->cartoes()->exists(), 422, 'Mova os cartões antes de excluir a lista.');
            $lista->delete();
        });

        return response()->json(['saved' => true]);
    }
}
