<?php

namespace App\Http\Requests;

use App\Models\QuadroCartao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQuadroCartaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('quadro')->canEdit($this->user());
    }

    public function rules(): array
    {
        $board = $this->route('quadro');
        $assignees = $board->membros->where('status', 'accepted')->pluck('user_id')->push($board->user_id)->all();

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'lista_id' => ['required', 'integer', Rule::exists('quadro_listas', 'id')->where('quadro_id', $board->id)],
            'status' => ['sometimes', Rule::in(array_keys(QuadroCartao::STATUSES))],
            'due_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'reminder_minutes' => ['nullable', 'integer', Rule::in(array_keys(QuadroCartao::REMINDERS))],
            'labels' => ['nullable', 'array', 'max:20'],
            'labels.*' => ['required', 'string', 'max:40', 'distinct'],
            'assignee_ids' => ['nullable', 'array', 'max:100'],
            'assignee_ids.*' => ['integer', 'distinct', Rule::in($assignees)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $card = $this->route('cartao');
            $due = $this->exists('due_at') ? $this->input('due_at') : $card?->due_at?->format('Y-m-d\TH:i');
            $start = $this->exists('starts_on') ? $this->input('starts_on') : $card?->starts_on?->format('Y-m-d');
            if (is_string($due) && is_string($start) && substr($due, 0, 10) < $start) {
                $validator->errors()->add('due_at', 'A entrega não pode ser anterior ao início.');
            }
            if (! $due && $this->input('reminder_minutes') !== null) {
                $validator->errors()->add('reminder_minutes', 'Defina uma data de entrega para adicionar um lembrete.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('labels_text')) {
            $this->merge(['labels' => array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $this->input('labels_text'))))))]);
        }
    }
}
