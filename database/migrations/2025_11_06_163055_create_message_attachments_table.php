<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->onDelete('cascade');
            $table->string('file_path');
            $table->string('file_name');
            $table->enum('file_type', ['image', 'video', 'audio', 'file'])->index();
            $table->unsignedBigInteger('file_size'); // in bytes
            $table->string('mime_type');
            $table->string('thumbnail_path')->nullable(); // for images and videos
            $table->unsignedInteger('duration')->nullable(); // in seconds for audio/video
            $table->timestamps();

            $table->index('message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
    }
};
