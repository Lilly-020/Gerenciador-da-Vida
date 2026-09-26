<?php

namespace App\Http\Requests;

use App\Models\Lancamento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLancamentoRequest extends FormRequest
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
     * Every field is "sometimes" since this same endpoint serves two very
     * different callers: the previsto/realizado checkbox, which PATCHes
     * only `status`, and the edit modal, which PATCHes every field but
     * `status`. Only the keys actually present in the request get
     * validated (and later written via `$lancamento->update()`), so
     * neither caller touches fields it didn't send.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::in([Lancamento::STATUS_PREVISTO, Lancamento::STATUS_REALIZADO])],
            'category' => ['sometimes', 'required', 'string', 'max:255'],
            'subcategory' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'date' => ['sometimes', 'required', 'date'],
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Informe o novo status.',
            'category.required' => 'Escolha uma categoria.',
            'description.required' => 'Descreva o lançamento.',
            'amount.required' => 'Informe o valor.',
            'amount.min' => 'O valor precisa ser maior que zero.',
            'date.required' => 'Informe a data.',
        ];
    }
}
