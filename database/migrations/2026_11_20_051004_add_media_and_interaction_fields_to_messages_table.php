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
            $table->string('attachment_url')->nullable()->after('type');
            $table->string('attachment_type', 30)->nullable()->after('attachment_url');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_type');
            $table->foreignId('reply_to_id')
                ->nullable()
                ->after('attachment_size')
                ->constrained('messages')
                ->nullOnDelete();
            $table->boolean('is_deleted_for_everyone')->default(false)->after('reply_to_id');
            $table->json('deleted_for_user_ids')->nullable()->after('is_deleted_for_everyone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['reply_to_id']);
            $table->dropColumn([
                'attachment_url',
                'attachment_type',
                'attachment_size',
                'reply_to_id',
                'is_deleted_for_everyone',
                'deleted_for_user_ids',
            ]);
        });
    }
};
