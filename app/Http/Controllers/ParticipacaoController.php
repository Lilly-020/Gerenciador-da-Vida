<?php

namespace App\Http\Controllers;

use App\Models\FinanceiroActivity;
use App\Models\Participacao;
use App\Models\Projeto;
use App\Models\User;
use App\Support\Carteira;
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
        if ($request->input('tipo') === Participacao::TIPO_FINANCAS && ! Carteira::isOwn()) {
            abort_unless(Carteira::canManage() && ! Carteira::hasRestrictions(), 403);
            $owner = Carteira::owner();
        }

        $data = $request->validate([
            'apelido' => ['required', 'string', 'max:31'],
            'tipo' => ['required', Rule::in([Participacao::TIPO_PROJETO, Participacao::TIPO_FINANCAS])],
            'projeto_id' => ['nullable', 'required_if:tipo,'.Participacao::TIPO_PROJETO, 'integer'],
            'permission' => ['required', Rule::in(array_keys($request->input('tipo') === Participacao::TIPO_PROJETO ? Participacao::PROJECT_PERMISSIONS : Participacao::PERMISSIONS))],
            'sections' => ['nullable', 'required_if:tipo,'.Participacao::TIPO_FINANCAS, 'array', 'min:1'],
            'allowed_accounts' => ['nullable', 'array'],
            'allowed_accounts.*' => ['integer', Rule::exists('contas_financeiras', 'id')->whereNull('deleted_at')->where('user_id', $owner->id)],
            'allowed_categories' => ['nullable', 'array'],
            'allowed_categories.*' => ['string', 'max:255'],
            'allowed_lancamentos' => ['nullable', 'array'],
            'allowed_lancamentos.*' => ['integer', Rule::exists('lancamentos', 'id')->where('user_id', $owner->id)],
            'sections.*' => [Rule::in(array_keys(Participacao::SECOES))],
        ], [
            'apelido.required' => 'Informe o apelido de quem você quer convidar.',
            'sections.required_if' => 'Escolha pelo menos uma seção para compartilhar.',
            'sections.min' => 'Escolha pelo menos uma seção para compartilhar.',
        ]);

        if ($data['tipo'] === Participacao::TIPO_FINANCAS && ! Carteira::isOwn()) {
            foreach ($data['sections'] ?? [] as $section) {
                if (! Carteira::canSee($section)) {
                    throw ValidationException::withMessages(['sections' => 'Você não pode compartilhar uma seção à qual não tem acesso.']);
                }
            }
        }

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
            // Project administrators manage invitations.
            $project = Projeto::findOrFail($data['projeto_id']);
            abort_unless($project->canBeManagedBy($owner), 404);
            $projetoId = $project->id;
            if ($participant->id === $project->user_id) {
                throw ValidationException::withMessages(['apelido' => 'O criador já é administrador do projeto.']);
            }
        }

        $existing = Participacao::where('owner_id', $isProjeto ? $project->user_id : $owner->id)
            ->where('participant_id', $participant->id)
            ->where('tipo', $data['tipo'])
            ->where('projeto_id', $projetoId)
            ->first();

        if ($existing && $existing->status !== Participacao::STATUS_RECUSADO) {
            throw ValidationException::withMessages(['apelido' => $participant->handle().' já foi convidado.']);
        }

        // A refused invitation can be sent again.
        ($existing ?? new Participacao)->fill([
            'owner_id' => $isProjeto ? $project->user_id : $owner->id,
            'participant_id' => $participant->id,
            'tipo' => $data['tipo'],
            'projeto_id' => $projetoId,
            'permission' => $data['permission'],
            'sections' => $isProjeto ? null : array_values($data['sections']),
            'allowed_accounts' => $isProjeto ? null : ($data['allowed_accounts'] ?? null),
            'allowed_categories' => $isProjeto ? null : ($data['allowed_categories'] ?? null),
            'allowed_lancamentos' => $isProjeto ? null : ($data['allowed_lancamentos'] ?? null),
            'status' => Participacao::STATUS_PENDENTE,
        ])->save();

        if (! $isProjeto) {
            FinanceiroActivity::record('Convidou '.$participant->handle().' para o Financeiro.');
        }
        if ($isProjeto) {
            $project->recordActivity('Convidou '.$participant->handle().' como '.Participacao::PROJECT_PERMISSIONS[$data['permission']].'.');
        }

        return back()->with('status', 'Convite enviado para '.$participant->handle().'.');
    }

    public function aceitar(Request $request, Participacao $participacao): RedirectResponse
    {
        $this->ensurePendingInviteFor($request, $participacao);

        $participacao->update(['status' => Participacao::STATUS_ACEITO]);
        if ($participacao->tipo === Participacao::TIPO_PROJETO) {
            $participacao->projeto?->recordActivity('Aceitou o convite para participar.');
        }

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

        abort_unless(in_array($userId, [(int) $participacao->owner_id, (int) $participacao->participant_id], true) || ($participacao->tipo === Participacao::TIPO_FINANCAS && (int) $participacao->owner_id === (int) Carteira::ownerId() && Carteira::canManage() && ! Carteira::hasRestrictions()) || ($participacao->tipo === Participacao::TIPO_PROJETO && $participacao->projeto?->canBeManagedBy($request->user())), 404);

        if ($participacao->tipo === Participacao::TIPO_PROJETO && $participacao->projeto) {
            $participacao->projeto->tasks()->where('assignee_id', $participacao->participant_id)->update(['assignee_id' => null]);
            $participacao->projeto->recordActivity('Removeu a participação de '.$participacao->participant->handle().'.');
        }
        $participacao->delete();

        return back()->with('status', 'Participação removida.');
    }

    public function updateFinancialAccess(Request $request, Participacao $participacao): RedirectResponse
    {
        abort_unless($participacao->tipo === Participacao::TIPO_FINANCAS, 404);
        $isOwner = (int) $participacao->owner_id === (int) $request->user()->id;
        abort_unless($isOwner || ((int) $participacao->owner_id === (int) Carteira::ownerId() && Carteira::canManage() && ! Carteira::hasRestrictions()), 404);
        $data = $request->validate([
            'permission' => ['required', Rule::in(array_keys(Participacao::PERMISSIONS))],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => [Rule::in(array_keys(Participacao::SECOES))],
            'allowed_accounts' => ['nullable', 'array'],
            'allowed_accounts.*' => ['integer', Rule::exists('contas_financeiras', 'id')->whereNull('deleted_at')->where('user_id', $participacao->owner_id)],
            'allowed_categories' => ['nullable', 'array'], 'allowed_categories.*' => ['string', 'max:255'],
            'allowed_lancamentos' => ['nullable', 'array'],
            'allowed_lancamentos.*' => ['integer', Rule::exists('lancamentos', 'id')->where('user_id', $participacao->owner_id)],
        ]);
        if (! $isOwner) {
            foreach ($data['sections'] as $section) {
                abort_unless(Carteira::canSee($section), 403);
            }
        }
        $participacao->update([...$data, 'allowed_accounts' => $data['allowed_accounts'] ?? null,
            'allowed_categories' => $data['allowed_categories'] ?? null, 'allowed_lancamentos' => $data['allowed_lancamentos'] ?? null]);
        $activity = new FinanceiroActivity(['actor_id' => $request->user()->id, 'description' => 'Alterou o acesso de '.$participacao->participant->handle().'.']);
        $activity->user_id = $participacao->owner_id;
        $activity->save();

        return back()->with('status', 'Permissões atualizadas.');
    }

    public function reenviar(Request $request, Participacao $participacao): RedirectResponse
    {
        abort_unless($participacao->tipo === Participacao::TIPO_PROJETO && $participacao->projeto?->canBeManagedBy($request->user()), 404);
        abort_unless($participacao->status === Participacao::STATUS_PENDENTE, 422);
        $participacao->touch();
        $participacao->projeto->recordActivity('Reenviou o convite para '.$participacao->participant->handle().'.');

        return back()->with('status', 'Convite renovado. Ele continua disponível para o participante no sistema.');
    }

    private function ensurePendingInviteFor(Request $request, Participacao $participacao): void
    {
        abort_unless((int) $participacao->participant_id === (int) $request->user()->id, 404);
        abort_unless($participacao->status === Participacao::STATUS_PENDENTE, 422, 'Este convite já foi respondido.');
    }
}
