<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ScheduleProjetoTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless($this->route('tarefa')->projeto_id === $this->route('projeto')->id, 404);

        return $this->route('projeto')->canBeEditedBy($this->user());
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'required_with:end_time', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && $this->filled('start_time') && $this->filled('end_time') && $this->input('end_time') <= $this->input('start_time')) {
                $validator->errors()->add('end_time', 'O horário final deve ser depois do horário inicial.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'date.required' => 'Informe a data em que esta tarefa será realizada.',
            'date.date_format' => 'Informe uma data de realização válida.',
            'start_time.required_with' => 'Informe o horário inicial.',
            'start_time.date_format' => 'Informe um horário inicial válido.',
            'end_time.date_format' => 'Informe um horário final válido.',
        ];
    }
}
