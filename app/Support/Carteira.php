<?php

namespace App\Support;

use App\Models\Participacao;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/** The Financeiro always belongs exclusively to the authenticated user. */
class Carteira
{
    public const SESSION_KEY = 'carteira_owner_id';

    public static function participacao(): ?Participacao
    {
        return null;
    }

    public static function owner(): ?User
    {
        return Auth::user();
    }

    public static function ownerId(): ?int
    {
        return Auth::id();
    }

    public static function isOwn(): bool
    {
        return true;
    }

    public static function canEdit(): bool
    {
        return true;
    }

    public static function canManage(): bool
    {
        return true;
    }

    public static function hasRestrictions(): bool
    {
        return false;
    }

    public static function scopeLancamentos(Builder $query): void {}

    public static function canSee(string $section): bool
    {
        return true;
    }

    /** @return Collection<int, User> */
    public static function members(): Collection
    {
        return Auth::user() ? collect([Auth::user()]) : collect();
    }

    /** @return Collection<int, Participacao> */
    public static function available(): Collection
    {
        return collect();
    }
}
