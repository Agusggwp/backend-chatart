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
        Schema::table('users', function (Blueprint $table) {
            $table->string('privacy_last_seen', 20)->default('everyone')->after('bio');
            $table->string('privacy_status', 20)->default('everyone')->after('privacy_last_seen');
            $table->boolean('privacy_read_receipts')->default(true)->after('privacy_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['privacy_last_seen', 'privacy_status', 'privacy_read_receipts']);
        });
    }
};
