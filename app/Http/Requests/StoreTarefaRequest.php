<?php

namespace App\Http\Requests;

use App\Models\Tarefa;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'category_id' => ['nullable', 'integer', Rule::exists('tarefa_categorias', 'id')->where('user_id', $this->user()->id)],
            'start_time' => ['nullable', 'required_with:end_time', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'kind' => ['sometimes', 'required', Rule::in(array_keys(Tarefa::KINDS))],
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
                if ($validator->errors()->isEmpty() && $this->filled('start_time') && $this->filled('end_time') && (! $this->filled('end_date') || $this->input('end_date') === $this->input('date')) && $this->input('end_time') <= $this->input('start_time')) {
                    $validator->errors()->add('end_time', 'O término deve ser depois do início, no mesmo dia.');
                }
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
            'start_time.required_with' => 'Informe o horário de início.',
            'start_time.date_format' => 'Informe um horário de início válido.',
            'end_time.date_format' => 'Informe um horário de término válido.',
            'end_time.after' => 'O término deve ser depois do início, no mesmo dia.',
            'end_date.after_or_equal' => 'A data de fim deve ser igual ou posterior ao início.',
            'end_date.date_format' => 'Informe uma data de fim válida.',
            'notes.max' => 'As observações podem ter até 10.000 caracteres.',
            'category_id.exists' => 'Selecione uma categoria sua ou crie uma nova.',
            'title.required' => 'Dê um nome para a tarefa.',
            'title.max' => 'O nome pode ter no máximo 255 caracteres.',
            'date.required' => 'Informe o dia da tarefa.',
            'date.date' => 'Informe uma data válida.',
            'repeat_until.required_with' => 'Informe até quando a tarefa se repete.',
            'repeat_until.after_or_equal' => 'O fim da repetição não pode ser antes do dia da tarefa.',
        ];
    }
}
