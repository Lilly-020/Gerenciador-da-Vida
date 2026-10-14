<?php

namespace App\Http\Controllers;

use App\Models\Participacao;
use App\Models\Projeto;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ParticipacaoController extends Controller
{
    /**
     * Invite another user, found by apelido, to one of the user's own
     * projects or to their Financeiro. Access only starts once the invited
     * user accepts.
     */
    public function store(Request $request): RedirectResponse
    {
        $owner = $request->user();

        $data = $request->validate([
            'apelido' => ['required', 'string', 'max:31'],
            'tipo' => ['required', Rule::in([Participacao::TIPO_PROJETO, Participacao::TIPO_FINANCAS])],
            'projeto_id' => ['nullable', 'required_if:tipo,'.Participacao::TIPO_PROJETO, 'integer'],
            'permission' => ['required', Rule::in(array_keys(Participacao::PERMISSIONS))],
            'sections' => ['nullable', 'required_if:tipo,'.Participacao::TIPO_FINANCAS, 'array', 'min:1'],
            'sections.*' => [Rule::in(array_keys(Participacao::SECOES))],
        ], [
            'apelido.required' => 'Informe o apelido de quem você quer convidar.',
            'sections.required_if' => 'Escolha pelo menos uma seção para compartilhar.',
            'sections.min' => 'Escolha pelo menos uma seção para compartilhar.',
        ]);

        $participant = User::findByApelido($data['apelido']);

        if (! $participant) {
            throw ValidationException::withMessages(['apelido' => 'Nenhum usuário com esse apelido.']);
        }

        if ($participant->is($owner)) {
            throw ValidationException::withMessages(['apelido' => 'Você não pode convidar a si mesmo.']);
        }

        $isProjeto = $data['tipo'] === Participacao::TIPO_PROJETO;
        $projetoId = null;

        if ($isProjeto) {
            // Only the project's creator invites — not other participants.
            $projetoId = Projeto::where('user_id', $owner->id)->findOrFail($data['projeto_id'])->id;
        }

        $existing = $owner->participacoesEnviadas()
            ->where('participant_id', $participant->id)
            ->where('tipo', $data['tipo'])
            ->where('projeto_id', $projetoId)
            ->first();

        if ($existing && $existing->status !== Participacao::STATUS_RECUSADO) {
            throw ValidationException::withMessages(['apelido' => $participant->handle().' já foi convidado.']);
        }

        // A refused invitation can be sent again.
        ($existing ?? new Participacao)->fill([
            'owner_id' => $owner->id,
            'participant_id' => $participant->id,
            'tipo' => $data['tipo'],
            'projeto_id' => $projetoId,
            'permission' => $data['permission'],
            'sections' => $isProjeto ? null : array_values($data['sections']),
            'status' => Participacao::STATUS_PENDENTE,
        ])->save();

        return back()->with('status', 'Convite enviado para '.$participant->handle().'.');
    }

    public function aceitar(Request $request, Participacao $participacao): RedirectResponse
    {
        $this->ensurePendingInviteFor($request, $participacao);

        $participacao->update(['status' => Participacao::STATUS_ACEITO]);

        return back()->with('status', 'Convite aceito.');
    }

    public function recusar(Request $request, Participacao $participacao): RedirectResponse
    {
        $this->ensurePendingInviteFor($request, $participacao);

        $participacao->update(['status' => Participacao::STATUS_RECUSADO]);

        return back()->with('status', 'Convite recusado.');
    }

    /**
     * The owner removes a participant (or cancels an invitation), or a
     * participant leaves.
     */
    public function destroy(Request $request, Participacao $participacao): RedirectResponse
    {
        $userId = (int) $request->user()->id;

        abort_unless(in_array($userId, [(int) $participacao->owner_id, (int) $participacao->participant_id], true), 404);

        $participacao->delete();

        return back()->with('status', 'Participação removida.');
    }

    private function ensurePendingInviteFor(Request $request, Participacao $participacao): void
    {
        abort_unless((int) $participacao->participant_id === (int) $request->user()->id, 404);
        abort_unless($participacao->status === Participacao::STATUS_PENDENTE, 422, 'Este convite já foi respondido.');
    }
}
