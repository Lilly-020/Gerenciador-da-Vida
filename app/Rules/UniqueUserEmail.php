<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class UniqueUserEmail implements ValidationRule
{
    public function __construct(private readonly ?User $except = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $query = User::query()->whereRaw('LOWER(email) = ?', [Str::lower(trim($value))]);
        if ($this->except !== null) {
            $query->whereKeyNot($this->except->getKey());
        }

        if ($query->exists()) {
            $fail('Esse e-mail já está em uso.');
        }
    }
}
