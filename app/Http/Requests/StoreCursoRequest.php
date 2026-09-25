<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCursoRequest extends FormRequest
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
            'platform' => ['nullable', 'string', 'max:255'],
            'link' => ['nullable', 'url', 'max:2048'],
            'objective' => ['nullable', 'string', 'max:2000'],
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
            'title.required' => 'Dê um nome para o curso.',
            'title.max' => 'O nome pode ter no máximo 255 caracteres.',
            'platform.max' => 'Esse campo pode ter no máximo 255 caracteres.',
            'link.url' => 'Informe um link válido (com http:// ou https://).',
            'objective.max' => 'O objetivo pode ter no máximo 2000 caracteres.',
            'starts_at.date' => 'Informe uma data de início válida.',
            'due_at.date' => 'Informe uma previsão de término válida.',
            'due_at.after_or_equal' => 'A previsão de término não pode ser antes do início.',
        ];
    }
}
