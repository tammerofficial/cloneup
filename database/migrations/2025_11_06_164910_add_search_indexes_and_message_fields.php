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
        Schema::table('messages', function (Blueprint $table) {
            // Add fields for edit/delete if they don't exist
            if (!Schema::hasColumn('messages', 'is_edited')) {
                $table->boolean('is_edited')->default(false)->after('status');
            }
            if (!Schema::hasColumn('messages', 'is_deleted')) {
                $table->boolean('is_deleted')->default(false)->after('is_edited');
            }
            if (!Schema::hasColumn('messages', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('is_deleted');
            }
            
            // Add indexes for search if they don't exist
            if (!$this->indexExists('messages', 'messages_chat_id_created_at_index')) {
                $table->index(['chat_id', 'created_at']);
            }
            // Note: Cannot index TEXT column directly, will use LIKE search instead
        });
    }

    /**
     * Check if an index exists.
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        $result = $connection->select(
            "SELECT COUNT(*) as count FROM information_schema.statistics 
             WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$databaseName, $table, $index]
        );
        return $result[0]->count > 0;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_message_index');
            $table->dropIndex(['chat_id', 'created_at']);
            $table->dropColumn(['is_edited', 'is_deleted', 'deleted_at']);
        });
    }
};
