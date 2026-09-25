<?php

namespace App\Http\Requests;

use App\Models\Lancamento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLancamentoRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([Lancamento::TYPE_ENTRADA, Lancamento::TYPE_SAIDA])],
            'category' => ['required', 'string', 'max:255'],
            'subcategory' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_recurring_template' => ['nullable', 'boolean'],
            'recurrence_period' => ['required_if:is_recurring_template,1', 'nullable', Rule::in(Lancamento::RECURRENCE_PERIODS)],
            'recurrence_day' => ['required_if:is_recurring_template,1', 'nullable', 'integer', 'min:1', 'max:31'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.required' => 'Escolha uma categoria.',
            'description.required' => 'Descreva o lançamento.',
            'amount.required' => 'Informe o valor.',
            'amount.min' => 'O valor precisa ser maior que zero.',
            'date.required' => 'Informe a data.',
            'recurrence_period.required_if' => 'Escolha a periodicidade da recorrência.',
            'recurrence_day.required_if' => 'Informe o dia esperado da recorrência.',
        ];
    }
}
