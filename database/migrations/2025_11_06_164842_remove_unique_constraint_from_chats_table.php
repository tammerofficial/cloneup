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
        Schema::table('chats', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['user_one']);
            $table->dropForeign(['user_two']);
        });

        // Drop unique constraint
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE `chats` DROP INDEX `chats_user_one_user_two_unique`');

        // Re-add foreign keys
        Schema::table('chats', function (Blueprint $table) {
            $table->foreign('user_one')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_two')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['user_one']);
            $table->dropForeign(['user_two']);
        });

        // Re-add unique constraint
        Schema::table('chats', function (Blueprint $table) {
            $table->unique(['user_one', 'user_two']);
        });

        // Re-add foreign keys
        Schema::table('chats', function (Blueprint $table) {
            $table->foreign('user_one')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_two')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
