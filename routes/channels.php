<?php

use App\Models\Chat;
use App\Models\User;
use App\Models\UserHasContact;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.{chatId}', function (User $user, int $chatId) {
    $chat = Chat::query()->findOrFail($chatId);
    return $chat->user_one === $user->id || $chat->user_two === $user->id;
});

Broadcast::channel('chat.start.user.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});

Broadcast::channel('status.user.{partnerId}', function (User $user, int $partnerId) {
    return Chat::query()
        ->where(function ($query) use ($partnerId, $user) {
            $query->where('user_one', $user->id)
                ->where('user_two', $partnerId);
        })
        ->orWhere(function ($query) use ($partnerId, $user) {
            $query->where('user_one', $partnerId)
                ->where('user_two', $user->id);
        })
        ->exists();
});
