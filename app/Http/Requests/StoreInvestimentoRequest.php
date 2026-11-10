<?php

namespace App\Http\Requests;

use App\Models\Investimento;
use App\Support\Carteira;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestimentoRequest extends FormRequest
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
            'initial_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'initial_date' => ['nullable', 'date', 'before_or_equal:today'],
            'account_id' => ['nullable', 'integer', Rule::exists('contas_financeiras', 'id')->whereNull('deleted_at')->where('user_id', Carteira::ownerId())],
            'rate_mode' => ['sometimes', 'required', 'in:fixed,cdi,ipca'],
            'reference_rate' => ['nullable', 'required_if:rate_mode,cdi,ipca', 'numeric', 'between:0,100'],
            'ir_rate' => ['sometimes', 'required', 'numeric', 'between:0,100'],
            'iof_rate' => ['sometimes', 'required', 'numeric', 'between:0,100'],
            'estimated_fees' => ['sometimes', 'required', 'numeric', 'between:0,999999999999.99'],
            'reported_value' => ['nullable', 'numeric', 'between:0,999999999999.99'],
            'reported_at' => ['nullable', 'required_with:reported_value', 'date', 'before_or_equal:today'],
            'name' => ['required', 'string', 'max:255'],
            'institution' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Investimento::TYPES))],
            'rate' => ['required', 'numeric', 'min:0', 'max:1000'],
            'rate_reference' => ['nullable', 'string', 'max:255'],
            'rate_period' => ['required', Rule::in(Investimento::RATE_PERIODS)],
            'liquidity' => ['nullable', 'string', 'max:255'],
            'maturity_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Dê um nome para o investimento.',
            'type.required' => 'Escolha o tipo de investimento.',
            'rate.required' => 'Informe a taxa de rendimento.',
            'rate_period.required' => 'Escolha se a taxa é mensal ou anual.',
        ];
    }
}
