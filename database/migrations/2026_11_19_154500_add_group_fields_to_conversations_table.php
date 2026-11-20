<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->after('type');
            $table->string('description', 255)->nullable()->after('name');
            $table->string('avatar')->nullable()->after('description');
            $table->foreignId('created_by')
                ->nullable()
                ->after('avatar')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->string('role', 20)->default('member')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['name', 'description', 'avatar']);
        });
    }
};
