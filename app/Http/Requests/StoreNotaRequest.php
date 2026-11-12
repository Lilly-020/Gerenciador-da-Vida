<?php

namespace App\Http\Requests;

use App\Models\Nota;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:50000'],
            'color' => ['sometimes', 'required', Rule::in(array_keys(Nota::COLORS))],
            'pinned' => ['sometimes', 'required', 'boolean'],
            'archived' => ['sometimes', 'required', 'boolean'],
            'tarefa_id' => ['sometimes', 'nullable', 'integer', Rule::exists('tarefas', 'id')->where('user_id', $this->user()->id)],
            'items' => ['sometimes', 'array', 'list', 'max:200'],
            'items.*' => ['array:text,completed'],
            'items.*.text' => ['required', 'string', 'max:500'],
            'items.*.completed' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'Dê um título para a nota.',
            'title.max' => 'O título pode ter no máximo 255 caracteres.',
            'content.max' => 'A nota pode ter até 50.000 caracteres.',
            'color.in' => 'Escolha uma cor válida.',
            'tarefa_id.exists' => 'Escolha uma tarefa sua.',
            'items.max' => 'Uma lista pode ter até 200 itens.',
            'items.*.text.required' => 'Preencha o texto de cada item.',
            'items.*.text.max' => 'Cada item pode ter até 500 caracteres.',
            'items.*.id.exists' => 'Este item não pertence à nota.',
        ];
    }
}
