<?php

namespace Database\Seeders;

use App\Models\Nota;
use App\Models\NotaItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotaSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->each(function (User $user): void {
            Nota::factory()->for($user)->has(NotaItem::factory()->count(3), 'items')->create(['title' => 'Minha lista de compras']);
        });
    }
}
