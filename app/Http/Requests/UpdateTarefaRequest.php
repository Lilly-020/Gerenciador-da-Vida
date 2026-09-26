<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTarefaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'completed' => ['sometimes', 'required', 'boolean'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'completed.required' => 'Informe se a tarefa está concluída.',
            'completed.boolean' => 'Valor inválido para o estado da tarefa.',
            'title.required' => 'Informe o nome da tarefa.',
        ];
    }
}
