<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class PrimaryUserSeeder extends Seeder
{
    /**
     * Seed the application's primary login account.
     */
    public function run(): void
    {
        User::updateOrCreate(
            [
                'email' => 'business@naeva.id',
            ],
            [
                'name' => 'Naeva Business',
                'email_verified_at' => now(),
                'password' => '@Gelasputih00',
            ],
        );
    }
}
