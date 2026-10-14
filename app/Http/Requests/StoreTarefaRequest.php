<?php

namespace App\Http\Requests;

use App\Models\Tarefa;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTarefaRequest extends FormRequest
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
            'date' => ['required', 'date'],
            // Repetition: the weekdays (Carbon's dayOfWeek, 0 = Sunday) the
            // task repeats on, from `date` through `repeat_until`.
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:0,6'],
            'repeat_until' => ['nullable', 'required_with:weekdays', 'date', 'after_or_equal:date'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || empty($this->input('weekdays'))) {
                    return;
                }

                $days = Carbon::parse($this->input('date'))->diffInDays(Carbon::parse($this->input('repeat_until')));

                if ($days > Tarefa::MAX_REPEAT_DAYS) {
                    $validator->errors()->add('repeat_until', 'A repetição pode ir até no máximo um ano à frente.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Dê um nome para a tarefa.',
            'title.max' => 'O nome pode ter no máximo 255 caracteres.',
            'date.required' => 'Informe o dia da tarefa.',
            'date.date' => 'Informe uma data válida.',
            'repeat_until.required_with' => 'Informe até quando a tarefa se repete.',
            'repeat_until.after_or_equal' => 'O fim da repetição não pode ser antes do dia da tarefa.',
        ];
    }
}
