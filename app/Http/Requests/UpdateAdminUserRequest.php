<?php

namespace App\Http\Requests;

use App\Rules\UniqueUserEmail;
use Illuminate\Validation\Rules\Password;

class UpdateAdminUserRequest extends StoreUserRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'email' => ['bail', 'required', 'string', 'email', 'max:255', new UniqueUserEmail($this->route('user'))],
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)],
        ];
    }
}
