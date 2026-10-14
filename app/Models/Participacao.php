<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An invitation for another user to take part in a project or in the
 * owner's Financeiro. Deliberately has no "owner" global scope: both sides
 * need to read it, so the controllers check who is asking.
 */
#[Table('participacoes')]
#[Fillable(['owner_id', 'participant_id', 'tipo', 'projeto_id', 'permission', 'sections', 'status'])]
class Participacao extends Model
{
    public const TIPO_PROJETO = 'projeto';

    public const TIPO_FINANCAS = 'financas';

    public const PERMISSION_VER = 'ver';

    public const PERMISSION_EDITAR = 'editar';

    /**
     * @var array<string, string>
     */
    public const PERMISSIONS = [
        self::PERMISSION_EDITAR => 'Pode editar',
        self::PERMISSION_VER => 'Só ver',
    ];

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_ACEITO = 'aceito';

    public const STATUS_RECUSADO = 'recusado';

    /**
     * The Financeiro sections an owner can choose to show a participant.
     *
     * @var array<string, string>
     */
    public const SECOES = [
        'dashboard' => 'Dashboard',
        'entradas' => 'Entradas',
        'saidas' => 'Saídas',
        'custos_fixos' => 'Custos Fixos',
        'investimentos' => 'Investimentos',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sections' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_id');
    }

    /**
     * Unscoped: an invited user must be able to see which project the
     * invitation is for before having access to the project itself.
     *
     * @return BelongsTo<Projeto, $this>
     */
    public function projeto(): BelongsTo
    {
        return $this->belongsTo(Projeto::class)->withoutGlobalScope('owner');
    }

    public function canEdit(): bool
    {
        return $this->permission === self::PERMISSION_EDITAR;
    }

    /**
     * What is being shared, in words — e.g. `o projeto "MVP"`.
     */
    public function subject(): string
    {
        if ($this->tipo === self::TIPO_FINANCAS) {
            return 'o Financeiro';
        }

        return 'o projeto "'.($this->projeto?->title ?? 'removido').'"';
    }

    /**
     * @return array<int, string>
     */
    public function sectionLabels(): array
    {
        return array_values(array_intersect_key(self::SECOES, array_flip($this->sections ?? [])));
    }
}
