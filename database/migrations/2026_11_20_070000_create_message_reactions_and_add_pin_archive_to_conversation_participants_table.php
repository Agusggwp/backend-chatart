<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add is_pinned and is_archived to conversation_participants
        Schema::table('conversation_participants', function (Blueprint $table) {
            if (!Schema::hasColumn('conversation_participants', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('role');
            }
            if (!Schema::hasColumn('conversation_participants', 'pinned_at')) {
                $table->timestamp('pinned_at')->nullable()->after('is_pinned');
            }
            if (!Schema::hasColumn('conversation_participants', 'is_archived')) {
                $table->boolean('is_archived')->default(false)->after('pinned_at');
            }
            if (!Schema::hasColumn('conversation_participants', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('is_archived');
            }
        });

        // 2. Create message_reactions table
        if (!Schema::hasTable('message_reactions')) {
            Schema::create('message_reactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('emoji', 16);
                $table->timestamps();

                $table->unique(['message_id', 'user_id']);
                $table->index('message_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');

        Schema::table('conversation_participants', function (Blueprint $table) {
            if (Schema::hasColumn('conversation_participants', 'archived_at')) {
                $table->dropColumn('archived_at');
            }
            if (Schema::hasColumn('conversation_participants', 'is_archived')) {
                $table->dropColumn('is_archived');
            }
            if (Schema::hasColumn('conversation_participants', 'pinned_at')) {
                $table->dropColumn('pinned_at');
            }
            if (Schema::hasColumn('conversation_participants', 'is_pinned')) {
                $table->dropColumn('is_pinned');
            }
        });
    }
};
