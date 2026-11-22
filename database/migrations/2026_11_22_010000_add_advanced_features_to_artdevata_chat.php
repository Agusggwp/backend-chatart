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
        // 1. Message editing & E2EE fields on messages table
        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('is_edited')->default(false)->after('body');
            $table->timestamp('edited_at')->nullable()->after('is_edited');
            $table->text('original_body')->nullable()->after('edited_at');
            $table->text('ciphertext')->nullable()->after('original_body');
            $table->string('iv', 64)->nullable()->after('ciphertext');
            $table->string('key_id', 64)->nullable()->after('iv');
        });

        // 2. Mute and Clear Chat on conversation_participants table
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->boolean('is_muted')->default(false)->after('is_archived');
            $table->timestamp('muted_until')->nullable()->after('is_muted');
            $table->timestamp('cleared_at')->nullable()->after('muted_until');
        });

        // 3. Group invite links & settings on conversations table
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('invite_token', 64)->nullable()->unique()->after('description');
            $table->timestamp('invite_token_created_at')->nullable()->after('invite_token');
            $table->json('settings')->nullable()->after('invite_token_created_at');
        });

        // 4. User reports and moderation table
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reported_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('reason', 50);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending'); // pending, reviewed, resolved, dismissed
            $table->timestamps();

            $table->index(['reporter_id', 'created_at']);
            $table->index(['reported_user_id', 'status']);
        });

        // 5. Multi-device and sessions management table
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_id', 100);
            $table->string('device_name', 100);
            $table->string('platform', 30)->default('web'); // web, android, ios
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->string('push_token')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id']);
            $table->index(['user_id', 'last_active_at']);
        });

        // 6. E2EE Public Keys storage table
        Schema::create('user_e2ee_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->text('identity_key');
            $table->text('signed_prekey');
            $table->text('prekey_signature');
            $table->json('one_time_prekeys')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_e2ee_keys');
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('reports');

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['invite_token', 'invite_token_created_at', 'settings']);
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropColumn(['is_muted', 'muted_until', 'cleared_at']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['is_edited', 'edited_at', 'original_body', 'ciphertext', 'iv', 'key_id']);
        });
    }
};
