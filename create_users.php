<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

// Create users
$user1 = User::firstOrCreate(
    ['email' => 'al@example.com'],
    [
        'name' => 'al',
        'password' => '5400',
        'is_active' => true,
    ]
);

$user2 = User::firstOrCreate(
    ['email' => 'an@example.com'],
    [
        'name' => 'an',
        'password' => '5400',
        'is_active' => true,
    ]
);

echo "Users created successfully!\n";
echo "User 1: ID={$user1->id}, Name={$user1->name}, Email={$user1->email}\n";
echo "User 2: ID={$user2->id}, Name={$user2->name}, Email={$user2->email}\n";

