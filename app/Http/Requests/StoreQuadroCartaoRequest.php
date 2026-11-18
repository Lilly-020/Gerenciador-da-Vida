<?php

namespace App\Http\Requests;

use App\Models\QuadroCartao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'labels' => ['nullable', 'array', 'max:20'],
            'labels.*' => ['required', 'string', 'max:40', 'distinct'],
            'assignee_ids' => ['nullable', 'array', 'max:100'],
            'assignee_ids.*' => ['integer', 'distinct', Rule::in($assignees)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('labels_text')) {
            $this->merge(['labels' => array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $this->input('labels_text'))))))]);
        }
    }
}
