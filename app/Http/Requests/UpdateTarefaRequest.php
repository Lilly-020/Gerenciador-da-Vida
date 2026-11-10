<?php

namespace App\Http\Requests;

use App\Models\Tarefa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'category_id' => ['nullable', 'integer', Rule::exists('tarefa_categorias', 'id')->where('user_id', $this->user()->id)],
            'start_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'end_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'kind' => ['sometimes', 'required', Rule::in(array_keys(Tarefa::KINDS))],
            'completed' => ['sometimes', 'required', 'boolean'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $task = $this->route('tarefa');
            $date = $this->input('date', $task->date->toDateString());
            $endDate = $this->input('end_date', $task->end_date?->toDateString());
            if ($endDate && $endDate < $date) {
                $validator->errors()->add('end_date', 'A data de fim deve ser igual ou posterior ao início.');
            }
            $start = $this->input('start_time', $task->start_time ? substr($task->start_time, 0, 5) : null);
            $end = $this->input('end_time', $task->end_time ? substr($task->end_time, 0, 5) : null);
            if ($end && ! $start) {
                $validator->errors()->add('start_time', 'Informe o horário de início.');
            } elseif ($start && $end && (! $endDate || $endDate === $date) && $end <= $start) {
                $validator->errors()->add('end_time', 'O término deve ser depois do início, no mesmo dia.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'Selecione uma categoria sua ou crie uma nova.',
            'date.required' => 'Informe a data de início.',
            'date.date_format' => 'Informe uma data de início válida.',
            'end_date.date_format' => 'Informe uma data de fim válida.',
            'notes.max' => 'As observações podem ter até 10.000 caracteres.',
            'completed.required' => 'Informe se a tarefa está concluída.',
            'completed.boolean' => 'Valor inválido para o estado da tarefa.',
            'title.required' => 'Informe o nome da tarefa.',
        ];
    }
}
