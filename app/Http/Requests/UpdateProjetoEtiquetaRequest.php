<?php

namespace App\Http\Requests;

class UpdateProjetoEtiquetaRequest extends StoreProjetoEtiquetaRequest
{
    public function authorize(): bool
    {
        abort_unless($this->route('etiqueta')->projeto_id === $this->route('projeto')->id, 404);

        return parent::authorize();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        array_unshift($rules['name'], 'sometimes');
        array_unshift($rules['color'], 'sometimes');

        return $rules;
    }
}
