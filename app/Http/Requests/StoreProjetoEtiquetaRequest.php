<?php

namespace App\Http\Requests;

use App\Models\ProjetoEtiqueta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjetoEtiquetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('projeto')->canBeEditedBy($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('projeto_etiquetas', 'name')->where('projeto_id', $this->route('projeto')->id)->ignore($this->route('etiqueta'))],
            'color' => ['required', Rule::in(array_keys(ProjetoEtiqueta::COLORS))],
            'selected' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
