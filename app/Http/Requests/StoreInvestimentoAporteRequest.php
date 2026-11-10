<?php

namespace App\Http\Requests;

use App\Support\Carteira;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestimentoAporteRequest extends FormRequest
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
            'kind' => ['sometimes', 'required', 'in:aporte,resgate'],
            'account_id' => ['nullable', 'integer', Rule::exists('contas_financeiras', 'id')->where('user_id', Carteira::ownerId())],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'rate_override' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Informe o valor do aporte.',
            'amount.min' => 'O valor precisa ser maior que zero.',
            'date.required' => 'Informe a data do aporte.',
        ];
    }
}
