<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed only the required users: al, an
        User::firstOrCreate(
            ['email' => 'al@example.com'],
            [
                'name' => 'al',
                'password' => '5400', // Will be hashed by model cast
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'an@example.com'],
            [
                'name' => 'an',
                'password' => '5400', // Will be hashed by model cast
                'is_active' => true,
            ]
        );
    }
}

