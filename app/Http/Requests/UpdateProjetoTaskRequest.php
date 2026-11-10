<?php

namespace App\Http\Requests;

class UpdateProjetoTaskRequest extends StoreProjetoTaskRequest
{
    public function authorize(): bool
    {
        abort_unless($this->route('tarefa')->projeto_id === $this->route('projeto')->id, 404);

        return parent::authorize();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['title'] = ['sometimes', 'required', 'string', 'max:255'];
        $rules['completed'] = ['sometimes', 'required', 'boolean'];
        $rules['parent_id'] = ['prohibited'];

        return $rules;
    }
}
