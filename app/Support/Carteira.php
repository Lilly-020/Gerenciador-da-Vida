<?php

namespace App\Support;

use App\Models\Participacao;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Whose Financeiro the current request is looking at.
 *
 * Normally the logged-in user's own. A user who accepted an invitation to
 * someone else's Financeiro can switch to that "carteira" (the choice is
 * kept in the session); from then on every Financeiro query, and every
 * lançamento/custo fixo/investimento created, belongs to that owner — as
 * far as the invitation's permission and sections allow.
 */
class Carteira
{
    public const SESSION_KEY = 'carteira_owner_id';

    private const REQUEST_KEY = 'carteira.participacao';

    /**
     * The accepted invitation behind the carteira being viewed, or null
     * when the user is in their own. Re-checked on every request, so
     * access ends the moment the owner removes the participant.
     */
    public static function participacao(): ?Participacao
    {
        $user = Auth::user();
        $request = request();

        if (! $user || ! $request->hasSession()) {
            return null;
        }

        if (! $request->attributes->has(self::REQUEST_KEY)) {
            $ownerId = $request->session()->get(self::SESSION_KEY);

            $request->attributes->set(self::REQUEST_KEY, $ownerId ? Participacao::with('owner')
                ->where('tipo', Participacao::TIPO_FINANCAS)
                ->where('owner_id', $ownerId)
                ->where('participant_id', $user->id)
                ->where('status', Participacao::STATUS_ACEITO)
                ->first() : null);
        }

        return $request->attributes->get(self::REQUEST_KEY);
    }

    public static function owner(): ?User
    {
        return self::participacao()?->owner ?? Auth::user();
    }

    public static function ownerId(): ?int
    {
        return self::participacao()?->owner_id ?? Auth::id();
    }

    public static function isOwn(): bool
    {
        return self::participacao() === null;
    }

    public static function canEdit(): bool
    {
        return self::participacao()?->canEdit() ?? true;
    }

    public static function canSee(string $section): bool
    {
        $participacao = self::participacao();

        return $participacao === null || in_array($section, $participacao->sections ?? [], true);
    }

    /**
     * Everyone in the carteira being viewed: its owner first, then the
     * participants who accepted.
     *
     * @return Collection<int, User>
     */
    public static function members(): Collection
    {
        $owner = self::owner();

        if (! $owner) {
            return collect();
        }

        return collect([$owner])->concat(
            Participacao::with('participant')
                ->where('tipo', Participacao::TIPO_FINANCAS)
                ->where('owner_id', $owner->id)
                ->where('status', Participacao::STATUS_ACEITO)
                ->get()
                ->pluck('participant'),
        );
    }

    /**
     * The other people's carteiras the logged-in user can switch to.
     *
     * @return Collection<int, Participacao>
     */
    public static function available(): Collection
    {
        return Auth::user()?->participacoesRecebidas()
            ->with('owner')
            ->where('tipo', Participacao::TIPO_FINANCAS)
            ->where('status', Participacao::STATUS_ACEITO)
            ->get() ?? collect();
    }
}
