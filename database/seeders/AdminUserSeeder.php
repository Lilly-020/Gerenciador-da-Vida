<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the application's admin user.
     *
     * Uses updateOrCreate so the seeder is safe to run more than once.
     * The plain password is hashed automatically by the User model's
     * `password => hashed` cast before it touches the database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'lillycardoso02@gmail.com'],
            [
                'name' => 'Lilly Cardoso',
                'password' => '1892Ly@09',
                'email_verified_at' => now(),
            ]
        );
    }
}
