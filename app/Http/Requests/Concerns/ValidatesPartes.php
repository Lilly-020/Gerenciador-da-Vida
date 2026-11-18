<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

/** Rejects fields from the retired financial sharing interface. */
trait ValidatesPartes
{
    protected function prepareForValidation(): void
    {
        $fields = collect($this->all())->filter(fn ($value, $key): bool => preg_match('/^parte_u\d+$/', (string) $key) === 1);
        if ($fields->isNotEmpty()) {
            $this->merge(['partes' => $fields->all()]);
        }
    }

    /** @return array<string, list<string>> */
    protected function partesRules(): array
    {
        return ['partes' => ['prohibited'], 'partes_conta' => ['prohibited']];
    }

    protected function validatePartes(Validator $validator, mixed $amount): void {}
}
