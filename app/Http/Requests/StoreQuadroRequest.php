<?php

namespace App\Http\Requests;

use App\Models\Quadro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuadroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'cover' => ['required', Rule::in(array_keys(Quadro::COVERS))],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
            'remove_cover' => ['sometimes', 'boolean'],
            'friend_id' => ['nullable', 'integer', Rule::in($this->user()->friendIds())],
            'invite_handle' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('invite_handle')) {
            $this->merge(['invite_handle' => mb_strtolower(ltrim(trim($this->input('invite_handle')), '@'))]);
        }
    }
}
