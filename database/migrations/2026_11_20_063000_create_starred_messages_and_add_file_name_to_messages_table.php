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
        if (!Schema::hasColumn('messages', 'file_name')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->string('file_name')->nullable()->after('attachment_url');
            });
        }

        if (!Schema::hasTable('starred_messages')) {
            Schema::create('starred_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['user_id', 'message_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('starred_messages');

        if (Schema::hasColumn('messages', 'file_name')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn('file_name');
            });
        }
    }
};
