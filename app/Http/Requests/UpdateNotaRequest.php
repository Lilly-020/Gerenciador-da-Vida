<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateNotaRequest extends StoreNotaRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['title'] = ['sometimes', 'required', 'string', 'max:255'];
        unset($rules['tarefa_id']);
        $rules['items.*'] = ['array:id,text,completed'];
        $rules['items.*.id'] = ['sometimes', 'nullable', 'integer', 'distinct', Rule::exists('nota_items', 'id')->where('nota_id', $this->route('nota')->id)];

        return $rules;
    }
}
