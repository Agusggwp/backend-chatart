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
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caller_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('receiver_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained('conversations')
                ->nullOnDelete();
            $table->string('type', 20)->default('audio'); // audio, video
            $table->string('status', 20)->default('ringing'); // ringing, accepted, rejected, missed, ended
            $table->unsignedInteger('duration')->default(0); // in seconds
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['caller_id', 'created_at']);
            $table->index(['receiver_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
