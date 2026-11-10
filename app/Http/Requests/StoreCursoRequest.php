<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'category' => ['nullable', 'string', 'max:255'],
            'progress_unit' => ['sometimes', 'required', 'in:lessons,modules'],
            'total_lessons' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'completed_lessons' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000'],
            'duration_hours' => ['nullable', 'numeric', 'min:0.01', 'max:999999.99'],
            'studied_hours' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999.99'],
            'notes' => ['nullable', 'string', 'max:50000'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'next_review_at' => ['nullable', 'date_format:Y-m-d'],
            'platform' => ['nullable', 'string', 'max:255'],
            'link' => ['nullable', 'url:http,https', 'max:2048'],
            'objective' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['total_lessons', 'completed_lessons', 'rating'])) {
                return;
            }
            $curso = $this->route('curso');
            $total = $this->input('total_lessons', $curso?->total_lessons);
            $completed = $this->input('completed_lessons', $curso?->completed_lessons ?? 0);
            if ($completed > 0 && (! $total || $completed > $total)) {
                $validator->errors()->add('completed_lessons', 'Informe um total igual ou maior que a quantidade concluída.');
            }
            if ($this->filled('rating') && ! $curso?->completed_at && (! $total || $completed < $total)) {
                $validator->errors()->add('rating', 'Finalize o curso antes de avaliá-lo.');
            }
        }];
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
