<?php

namespace App\Http\Requests;

use App\Models\Participacao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjetoTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('projeto')->canBeEditedBy($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $projeto = $this->route('projeto');
        $assignees = $projeto->participacoes->where('status', Participacao::STATUS_ACEITO)->pluck('participant_id')->push($projeto->user_id)->all();

        return [
            'title' => ['required', 'string', 'max:255'],
            'assignee_id' => ['nullable', 'integer', Rule::in($assignees)],
            'due_at' => ['nullable', 'date_format:Y-m-d'],
            'parent_id' => ['nullable', 'integer', Rule::exists('projeto_tasks', 'id')->where('projeto_id', $projeto->id)->whereNull('parent_id')],
            'goal_type' => ['sometimes', 'required', 'in:checklist,numeric,financial'],
            'target_value' => ['nullable', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'current_value' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999999999.99'],
            'unit' => ['nullable', 'string', 'max:30'],
        ];
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $task = $this->route('tarefa');
            $type = $this->input('goal_type', $task?->goal_type ?? 'checklist');
            $target = $this->input('target_value', $task?->target_value);
            if ($type !== 'checklist' && ! $target) {
                $validator->errors()->add('target_value', 'Informe uma meta maior que zero.');
            }
        }];
    }
}
