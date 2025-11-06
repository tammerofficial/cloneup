<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    protected $fillable = [
        'message_id',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'mime_type',
        'thumbnail_path',
        'duration',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'duration' => 'integer',
    ];

    // Ensure URLs are included when serializing to JSON
    protected $appends = ['file_url', 'thumbnail_url'];

    /**
     * Get the message that owns the attachment.
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get the full URL for the file.
     */
    public function getFileUrlAttribute(): string
    {
        $path = ltrim($this->file_path, '/');
        return url('/storage/' . $path);
    }

    /**
     * Get the full URL for the thumbnail.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail_path) {
            return null;
        }

        $path = ltrim($this->thumbnail_path, '/');
        return url('/storage/' . $path);
    }
}
