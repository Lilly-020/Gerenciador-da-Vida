<?php

namespace App\Http\Requests;

use App\Models\CustoFixo;
use Illuminate\Validation\Rule;

class UpdateCustoFixoRequest extends StoreCustoFixoRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['required', Rule::in([CustoFixo::STATUS_ATIVO, CustoFixo::STATUS_INATIVO])],
        ];
    }
}
