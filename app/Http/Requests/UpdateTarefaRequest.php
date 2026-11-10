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
            $start = $this->input('start_time', $task->start_time ? substr($task->start_time, 0, 5) : null);
            $end = $this->input('end_time', $task->end_time ? substr($task->end_time, 0, 5) : null);
            if ($end && ! $start) {
                $validator->errors()->add('start_time', 'Informe o horário de início.');
            } elseif ($start && $end && $end <= $start) {
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
            'completed.required' => 'Informe se a tarefa está concluída.',
            'completed.boolean' => 'Valor inválido para o estado da tarefa.',
            'title.required' => 'Informe o nome da tarefa.',
        ];
    }
}
