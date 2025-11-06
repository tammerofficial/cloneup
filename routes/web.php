<?php

use App\Http\Controllers\HomeController;
use App\Http\Middleware\UpdateUserLastSeen;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', UpdateUserLastSeen::class])->group(function () {
    Route::get('/', [HomeController::class, 'show'])->name('home');

    Route::post('/contact/store', [HomeController::class, 'storeContact'])->name('contact.store');

    Route::post('/chat/start', [HomeController::class, 'startChat'])->name('chat.start');
    Route::get('/chat/{id}/messages', [HomeController::class, 'getMessages'])->name('chat.messages.get');
    Route::post('/chat/message', [HomeController::class, 'sendMessage'])->name('chat.message.send');
    Route::get('/attachment/{attachmentId}', [HomeController::class, 'getAttachment'])->name('attachment.get');

    Route::patch('/user-status', [HomeController::class, 'updateUserStatus'])->name('user.status.update');
    Route::patch('/message-status', [HomeController::class, 'updateMessageStatus'])->name('message.status.update');

    // Search routes
    Route::get('/search/global', [HomeController::class, 'globalSearch'])->name('search.global');
    Route::get('/search/conversation/{chatId}', [HomeController::class, 'searchInConversation'])->name('search.conversation');

    // Message reactions routes
    Route::post('/messages/{messageId}/reactions', [HomeController::class, 'addReaction'])->name('messages.reactions.add');
    Route::delete('/messages/{messageId}/reactions/{reactionId}', [HomeController::class, 'removeReaction'])->name('messages.reactions.remove');
    Route::get('/messages/{messageId}/reactions', [HomeController::class, 'getReactions'])->name('messages.reactions.get');

    // Message edit/delete routes
    Route::put('/messages/{messageId}', [HomeController::class, 'updateMessage'])->name('messages.update');
    Route::delete('/messages/{messageId}', [HomeController::class, 'deleteMessage'])->name('messages.delete');
});

require __DIR__ . '/auth.php';
