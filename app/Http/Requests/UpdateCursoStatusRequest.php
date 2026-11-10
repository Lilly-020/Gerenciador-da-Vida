<?php

namespace App\Http\Requests;

use App\Models\Curso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCursoStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(array_keys(Curso::STATUSES))],
        ];
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $curso = $this->route('curso');
            if ($this->input('status') === 'not_started' && ($curso->completed_lessons > 0 || $curso->studied_hours > 0)) {
                $validator->errors()->add('status', 'Este curso já tem progresso. Edite o acompanhamento para zerá-lo ou escolha Pausado.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Informe a nova coluna do curso.',
            'status.in' => 'Coluna inválida.',
        ];
    }
}
