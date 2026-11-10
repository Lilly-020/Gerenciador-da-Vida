<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSonhoRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'target_date' => ['nullable', 'date_format:Y-m-d'],
            'financial_goal' => ['nullable', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'saved_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'priority' => ['sometimes', 'required', 'in:low,medium,high'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'image_url' => ['nullable', 'url:https', 'max:2048'],
            'archived' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Dê um título para o sonho.',
            'title.max' => 'O título pode ter no máximo 255 caracteres.',
        ];
    }
}
