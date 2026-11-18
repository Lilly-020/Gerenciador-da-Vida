<?php

namespace App\Http\Controllers;

use App\Models\ProjetoEtiqueta;
use App\Models\Quadro;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuadroEtiquetaController extends Controller
{
    public function store(Request $request, Quadro $quadro): JsonResponse
    {
        return $this->save($request, $quadro);
    }

    public function update(Request $request, Quadro $quadro, string $etiqueta): JsonResponse
    {
        return $this->save($request, $quadro, $etiqueta);
    }

    private function save(Request $request, Quadro $quadro, ?string $id = null): JsonResponse
    {
        abort_unless($quadro->canEdit($request->user()), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'color' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || (! array_key_exists($value, ProjetoEtiqueta::COLORS) && ! preg_match('/^#[0-9a-fA-F]{6}$/', $value))) {
                    $fail('Selecione uma cor válida.');
                }
            }],
        ]);
        $data['color'] = strtolower($data['color']);
        DB::transaction(function () use ($quadro, $data, $id): void {
            $board = Quadro::whereKey($quadro->id)->lockForUpdate()->firstOrFail();
            $labels = collect($board->label_catalog ?? []);
            $old = $id ? $labels->firstWhere('id', $id) : null;
            abort_if($id && ! $old, 404);
            abort_if($labels->contains(fn (array $label): bool => $label['id'] !== $id && mb_strtolower($label['name']) === mb_strtolower(trim($data['name']))), 422, 'Já existe uma etiqueta com esse nome.');
            abort_if(! $id && $labels->count() >= 100, 422, 'O quadro pode ter até 100 etiquetas.');
            $label = [...$data, 'name' => trim($data['name']), 'id' => $id ?? (string) Str::uuid()];
            $board->label_catalog = $id ? $labels->map(fn (array $item): array => $item['id'] === $id ? $label : $item)->all() : $labels->push($label)->all();
            $board->save();
            if ($old && $old['name'] !== $label['name']) {
                foreach ($board->cartoes()->get() as $card) {
                    $card->labels = array_map(fn (string $name): string => $name === $old['name'] ? $label['name'] : $name, $card->labels ?? []);
                    $card->save();
                }
            }
        });

        return response()->json(['saved' => true]);
    }

    public function destroy(Request $request, Quadro $quadro, string $etiqueta): JsonResponse
    {
        abort_unless($quadro->canEdit($request->user()), 403);
        DB::transaction(function () use ($quadro, $etiqueta): void {
            $board = Quadro::whereKey($quadro->id)->lockForUpdate()->firstOrFail();
            $labels = collect($board->label_catalog ?? []);
            $old = $labels->firstWhere('id', $etiqueta);
            abort_unless($old, 404);
            $board->label_catalog = $labels->reject(fn (array $label): bool => $label['id'] === $etiqueta)->values()->all();
            $board->save();
            foreach ($board->cartoes()->get() as $card) {
                $card->labels = array_values(array_filter($card->labels ?? [], fn (string $name): bool => $name !== $old['name']));
                $card->save();
            }
        });

        return response()->json(['saved' => true]);
    }
}
