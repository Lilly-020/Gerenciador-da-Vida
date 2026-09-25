<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjetoRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Dê um título para o projeto.',
            'title.max' => 'O título pode ter no máximo 255 caracteres.',
            'description.max' => 'As observações podem ter no máximo 2000 caracteres.',
            'starts_at.date' => 'Informe uma data de início válida.',
            'due_at.date' => 'Informe uma data de prazo válida.',
            'due_at.after_or_equal' => 'O prazo não pode ser antes do início.',
        ];
    }
}
