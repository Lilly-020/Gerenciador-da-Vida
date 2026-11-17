<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjetoEtiquetaRequest;
use App\Http\Requests\UpdateProjetoEtiquetaRequest;
use App\Models\Projeto;
use App\Models\ProjetoEtiqueta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjetoEtiquetaController extends Controller
{
    public function index(Request $request, Projeto $projeto): JsonResponse
    {
        $projeto->load('etiquetas');
        $canEdit = $projeto->canBeEditedBy($request->user());

        return response()->json([
            'list' => view('partials.projeto-etiqueta-list', compact('projeto', 'canEdit'))->render(),
            'badges' => view('partials.projeto-etiqueta-badges', compact('projeto'))->render(),
            'canEdit' => $canEdit,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(StoreProjetoEtiquetaRequest $request, Projeto $projeto): JsonResponse
    {
        $projeto->etiquetas()->create($request->validated());
        $projeto->recordActivity('Criou uma etiqueta.');

        return $this->index($request, $projeto);
    }

    public function update(UpdateProjetoEtiquetaRequest $request, Projeto $projeto, ProjetoEtiqueta $etiqueta): JsonResponse
    {
        $etiqueta->update($request->validated());
        $projeto->recordActivity('Atualizou uma etiqueta.');

        return $this->index($request, $projeto);
    }

    public function destroy(Request $request, Projeto $projeto, ProjetoEtiqueta $etiqueta): JsonResponse
    {
        abort_unless($etiqueta->projeto_id === $projeto->id, 404);
        abort_unless($projeto->canBeEditedBy($request->user()), 403);
        $etiqueta->delete();
        $projeto->recordActivity('Excluiu uma etiqueta.');

        return $this->index($request, $projeto);
    }
}
