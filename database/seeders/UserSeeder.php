<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // إنشاء مستخدم إداري (إذا لم يكن موجوداً)
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'is_active' => true,
                'about' => 'System Administrator',
            ]
        );

        // إنشاء مستخدمين عاديين (إذا لم يكونوا موجودين)
        User::firstOrCreate(
            ['email' => 'ahmed@example.com'],
            [
                'name' => 'Ahmed Ali',
                'password' => Hash::make('password'),
                'is_admin' => false,
                'is_active' => true,
                'about' => 'Software Developer',
            ]
        );

        User::firstOrCreate(
            ['email' => 'sara@example.com'],
            [
                'name' => 'Sara Mohammed',
                'password' => Hash::make('password'),
                'is_admin' => false,
                'is_active' => true,
                'about' => 'Designer',
            ]
        );

        User::firstOrCreate(
            ['email' => 'khalid@example.com'],
            [
                'name' => 'Khalid Hassan',
                'password' => Hash::make('password'),
                'is_admin' => false,
                'is_active' => true,
                'about' => 'Project Manager',
            ]
        );

        // إنشاء مستخدمين إضافيين باستخدام Factory (10 مستخدمين)
        User::factory(10)->create();
    }
}

