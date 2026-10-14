<?php

namespace App\Http\Requests;

use App\Models\CustoFixo;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class UpdateCustoFixoRequest extends StoreCustoFixoRequest
{
    public const SCOPE_MES = 'mes';

    public const SCOPE_TODOS = 'todos';

    /**
     * @var array<int, string>
     */
    public const SCOPES = [self::SCOPE_MES, self::SCOPE_TODOS];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $scope = ['scope' => ['nullable', Rule::in(self::SCOPES)]];

        // Changing a single month only touches the fields a month can
        // have of its own; the schedule fields aren't even submitted.
        if ($this->input('scope') === self::SCOPE_MES) {
            return [...Arr::only(parent::rules(), [...CustoFixo::MONTHLY_FIELDS, 'partes.*']), ...$scope];
        }

        return [
            ...parent::rules(),
            ...$scope,
            'status' => ['required', Rule::in([CustoFixo::STATUS_ATIVO, CustoFixo::STATUS_INATIVO])],
        ];
    }
}
