<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPartes;
use App\Models\Lancamento;
use App\Support\Carteira;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLancamentoRequest extends FormRequest
{
    use ValidatesPartes;

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
            'status' => ['sometimes', 'required', Rule::in(['previsto', 'realizado'])],
            'installments' => ['sometimes', 'required', 'integer', 'between:1,120'],
            'recurrence_end' => ['nullable', 'date', 'after_or_equal:date'],
            'recurrence_count' => ['nullable', 'integer', 'between:1,1000'],
            'recurrence_status' => ['sometimes', 'required', 'in:previsto,realizado'],
            'type' => ['required', Rule::in([Lancamento::TYPE_ENTRADA, Lancamento::TYPE_SAIDA])],
            'account_id' => ['nullable', 'integer', Rule::exists('contas_financeiras', 'id')->whereNull('deleted_at')->where('user_id', Carteira::ownerId())],
            'expense_kind' => ['sometimes', 'required', 'in:fixo,variavel'],
            'category' => ['required', 'string', 'max:255'],
            'subcategory' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_recurring_template' => ['nullable', 'boolean'],
            'recurrence_period' => ['required_if:is_recurring_template,1', 'nullable', Rule::in(Lancamento::RECURRENCE_PERIODS)],
            'recurrence_day' => ['required_if:is_recurring_template,1', 'nullable', 'integer', 'min:1', 'max:31'],
            ...$this->partesRules(),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->validatePartes($validator, $this->input('amount'));
            if ($this->boolean('is_recurring_template') && $this->integer('installments', 1) > 1) {
                $validator->errors()->add('installments', 'Escolha recorrência ou parcelamento para este lançamento.');
            }
            if (is_numeric($this->input('amount')) && $this->integer('installments', 1) > round((float) $this->input('amount') * 100)) {
                $validator->errors()->add('installments', 'Cada parcela precisa ter pelo menos um centavo.');
            }
            $this->validateVisibility($validator);
        }];
    }

    private function validateVisibility(Validator $validator): void
    {
        $invite = Carteira::participacao();
        if (! $invite) {
            return;
        }
        $row = $this->route('lancamento');
        foreach (['allowed_accounts' => 'account_id', 'allowed_categories' => 'category'] as $scope => $field) {
            $value = $this->input($field, $row?->$field);
            if ($invite->$scope !== null && ! in_array((string) $value, array_map('strval', $invite->$scope), true)) {
                $validator->errors()->add($field, 'Este valor não faz parte do compartilhamento.');
            }
        }
        if (! $row && $invite->allowed_lancamentos !== null) {
            $validator->errors()->add('description', 'Este acesso permite editar apenas os lançamentos selecionados.');
        }
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
