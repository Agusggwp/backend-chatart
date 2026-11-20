<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add is_forwarded to messages table
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'is_forwarded')) {
                $table->boolean('is_forwarded')->default(false)->after('is_deleted_for_everyone');
            }
        });

        // 2. Create blocked_users table
        if (!Schema::hasTable('blocked_users')) {
            Schema::create('blocked_users', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('blocked_user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['user_id', 'blocked_user_id']);
                $table->index('user_id');
                $table->index('blocked_user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_users');

        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'is_forwarded')) {
                $table->dropColumn('is_forwarded');
            }
        });
    }
};
