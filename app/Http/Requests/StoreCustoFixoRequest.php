<?php

namespace App\Http\Requests;

use App\Models\CustoFixo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustoFixoRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_day' => ['required', 'integer', 'min:1', 'max:31'],
            'periodicity' => ['required', Rule::in(CustoFixo::PERIODICITIES)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Dê um nome para o custo fixo.',
            'category.required' => 'Escolha uma categoria.',
            'amount.required' => 'Informe o valor.',
            'amount.min' => 'O valor precisa ser maior que zero.',
            'due_day.required' => 'Informe o dia de vencimento.',
            'periodicity.required' => 'Escolha a periodicidade.',
            'starts_on.required' => 'Informe a data inicial.',
            'ends_on.after_or_equal' => 'A data final não pode ser antes da inicial.',
        ];
    }
}
