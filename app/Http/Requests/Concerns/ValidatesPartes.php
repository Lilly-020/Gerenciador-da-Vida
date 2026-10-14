<?php

namespace App\Http\Requests\Concerns;

use App\Support\Carteira;
use Illuminate\Validation\Validator;

/**
 * Shared by the lançamento and custo fixo form requests: reads the "dividir entre
 * participantes" fields. The forms post them flat, one `parte_u{userId}`
 * per carteira member, which is gathered here into `partes` (amount per
 * user id, blanks and zeros dropped). When no such field is posted at
 * all, `partes` stays absent and the existing division is left alone.
 */
trait ValidatesPartes
{
    protected function prepareForValidation(): void
    {
        $fields = collect($this->all())->filter(fn ($value, $key): bool => preg_match('/^parte_u\d+$/', (string) $key) === 1);

        if ($fields->isEmpty()) {
            return;
        }

        $this->merge([
            'partes' => $fields
                ->filter(fn ($value): bool => is_numeric($value) && (float) $value > 0)
                ->mapWithKeys(fn ($value, $key): array => [(int) substr((string) $key, strlen('parte_u')) => round((float) $value, 2)])
                ->all(),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function partesRules(): array
    {
        return [
            'partes' => ['sometimes', 'array'],
            'partes.*' => ['numeric', 'min:0.01'],
        ];
    }

    /**
     * A division, when given, must be among the carteira's members and
     * fit within the total; what is left over stays with the carteira's
     * owner.
     */
    protected function validatePartes(Validator $validator, mixed $amount): void
    {
        $partes = $this->input('partes');

        if ($validator->errors()->isNotEmpty() || empty($partes)) {
            return;
        }

        $memberIds = Carteira::members()->pluck('id')->all();

        if (array_diff(array_keys($partes), $memberIds) !== []) {
            $validator->errors()->add('partes', 'Só dá para dividir entre participantes desta carteira.');

            return;
        }

        if (array_sum($partes) - (float) $amount > 0.009) {
            $validator->errors()->add('partes', 'A soma das partes não pode passar do valor total.');
        }
    }
}
