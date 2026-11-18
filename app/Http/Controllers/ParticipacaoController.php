<?php

namespace App\Http\Controllers;

use App\Models\Participacao;
use App\Models\Projeto;
use App\Models\Sonho;
use App\Models\Tarefa;
use App\Models\User;
use App\Notifications\SocialNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParticipacaoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['tipo' => ['required', Rule::in(['projeto', 'sonho', 'tarefa'])], 'item_id' => ['required', 'integer'], 'friend_id' => ['required', 'integer', Rule::in($request->user()->friendIds())]]);
        $model = ['projeto' => Projeto::class, 'sonho' => Sonho::class, 'tarefa' => Tarefa::class][$data['tipo']];
        $item = $model::findOrFail($data['item_id']);
        abort_unless($item->user_id === $request->user()->id, 403);
        $entry = Participacao::firstOrCreate(['owner_id' => $item->user_id, 'participant_id' => $data['friend_id'], 'tipo' => $data['tipo'], $data['tipo'].'_id' => $item->id], ['status' => 'aceito', 'permission' => 'editar']);
        if ($entry->status !== 'aceito') {
            $entry->update(['status' => 'aceito', 'permission' => 'editar']);
        }
        if ($entry->wasRecentlyCreated || $entry->wasChanged('status')) {
            User::findOrFail($data['friend_id'])->notify(new SocialNotification(['kind' => $data['tipo'], 'item_id' => $item->id, 'title' => $item->title, 'actor' => $request->user()->name]));
        }

        return back()->with('status', 'Amigo adicionado. Ele pode colaborar somente neste item.');
    }

    public function destroy(Request $request, Participacao $participacao): RedirectResponse
    {
        abort_unless(in_array($participacao->tipo, ['projeto', 'sonho', 'tarefa'], true) && in_array($request->user()->id, [$participacao->owner_id, $participacao->participant_id], true), 404);
        if ($participacao->tipo === 'projeto') {
            $participacao->projeto?->tasks()->where('assignee_id', $participacao->participant_id)->update(['assignee_id' => null]);
        }
        $participacao->delete();

        return back()->with('status', 'Participação encerrada.');
    }
}
