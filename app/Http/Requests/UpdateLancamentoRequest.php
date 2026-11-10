<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPartes;
use App\Models\Lancamento;
use App\Support\Carteira;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLancamentoRequest extends FormRequest
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
            'recurrence_active' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'required', Rule::in([Lancamento::STATUS_PREVISTO, Lancamento::STATUS_REALIZADO, Lancamento::STATUS_CANCELADO])],
            'account_id' => ['nullable', 'integer', Rule::exists('contas_financeiras', 'id')->whereNull('deleted_at')->where('user_id', Carteira::ownerId())],
            'expense_kind' => ['sometimes', 'required', 'in:fixo,variavel'],
            'category' => ['sometimes', 'required', 'string', 'max:255'],
            'subcategory' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'date' => ['sometimes', 'required', 'date'],
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            ...$this->partesRules(),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->validatePartes($validator, $this->input('amount', $this->route('lancamento')->amount));
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
            'status.required' => 'Informe o novo status.',
            'category.required' => 'Escolha uma categoria.',
            'description.required' => 'Descreva o lançamento.',
            'amount.required' => 'Informe o valor.',
            'amount.min' => 'O valor precisa ser maior que zero.',
            'date.required' => 'Informe a data.',
        ];
    }
}
