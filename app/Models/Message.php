<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $fillable = [
        'user_id',
        'chat_id',
        'message',
        'status',
        'is_edited',
        'is_deleted',
        'deleted_at',
    ];

    /**
     * Get the attachments for the message.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    /**
     * Check if the message has attachments.
     */
    public function getHasAttachmentsAttribute(): bool
    {
        return $this->attachments()->exists();
    }

    /**
     * Get the reactions for the message.
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    /**
     * Get the chat that owns the message.
     */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    /**
     * Get the user that created the message.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
