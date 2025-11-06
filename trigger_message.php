<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Events\MessageSent;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

$al = User::firstOrCreate([
    'email' => 'al@example.com',
], [
    'name' => 'al',
    'password' => '5400',
    'is_active' => true,
]);

$an = User::firstOrCreate([
    'email' => 'an@example.com',
], [
    'name' => 'an',
    'password' => '5400',
    'is_active' => true,
]);

// Find or create chat between al and an
$chat = Chat::query()
    ->where(function ($q) use ($al, $an) {
        $q->where('user_one', $al->id)->where('user_two', $an->id);
    })
    ->orWhere(function ($q) use ($al, $an) {
        $q->where('user_one', $an->id)->where('user_two', $al->id);
    })
    ->first();

if (!$chat) {
    $chat = Chat::create([
        'user_one' => $al->id,
        'user_two' => $an->id,
    ]);
}

$message = Message::create([
    'user_id' => $al->id,
    'message' => 'Test broadcast at '.now()->toDateTimeString(),
    'chat_id' => $chat->id,
    'status' => 'delivered',
]);

$messageData = [
    'id' => $message->id,
    'message' => $message->message,
    'created_at' => $message->created_at->toISOString(),
    'status' => $message->status,
    'user_id' => $message->user_id,
    'chat_id' => $message->chat_id,
];

broadcast(new MessageSent($messageData))->toOthers();

echo "Broadcasted MessageSent on chat.{$chat->id}\n";


