<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateQuadroCartaoRequest extends StoreQuadroCartaoRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['title'] = ['sometimes', 'required', 'string', 'max:255'];
        $rules['lista_id'] = ['sometimes', ...$rules['lista_id']];

        return [...$rules,
            'checklist' => ['sometimes', 'array', 'max:200'],
            'checklist.*' => ['array:text,done'],
            'checklist.*.text' => ['required', 'string', 'max:500'],
            'checklist.*.done' => ['required', 'boolean'],
            'checklists' => ['sometimes', 'array', 'max:20'],
            'checklists.*' => ['array:id,title,items'],
            'checklists.*.id' => ['required', 'uuid', 'distinct'],
            'checklists.*.title' => ['required', 'string', 'max:100'],
            'checklists.*.items' => ['present', 'array', 'max:200'],
            'checklists.*.items.*' => ['array:text,done'],
            'checklists.*.items.*.text' => ['required', 'string', 'max:500'],
            'checklists.*.items.*.done' => ['required', 'boolean'],
            'location' => ['nullable', 'string', 'max:500'],
            'cover_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'cover_file_id' => ['nullable', 'uuid', Rule::in(collect($this->route('cartao')->attachments ?? [])->whereIn('mime', ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])->pluck('id')->all())],
        ];
    }

    public function authorize(): bool
    {
        abort_unless($this->route('cartao')->quadro_id === $this->route('quadro')->id, 404);

        return parent::authorize();
    }
}
