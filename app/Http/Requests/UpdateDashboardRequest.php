<?php

namespace App\Http\Requests;

use App\Support\DashboardLayout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'widgets' => ['required', 'array', 'list', 'size:'.count(DashboardLayout::WIDGETS)],
            'widgets.*' => ['required', 'array:id,visible,width'],
            'widgets.*.id' => ['required', 'string', 'distinct:strict', Rule::in(array_keys(DashboardLayout::WIDGETS))],
            'widgets.*.visible' => ['required', 'boolean'],
            'widgets.*.width' => ['required', Rule::in(['normal', 'wide'])],
            'task_view' => ['required', Rule::in(array_keys(DashboardLayout::TASK_VIEWS))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'widgets.size' => 'A configuração deve incluir todos os blocos da dashboard.',
            'widgets.*.id.distinct' => 'Cada bloco deve aparecer uma única vez.',
            'widgets.*.id.in' => 'Escolha um bloco válido.',
            'widgets.*.width.in' => 'Escolha um tamanho válido para o bloco.',
            'task_view.in' => 'Escolha um calendário de tarefas válido.',
        ];
    }
}
