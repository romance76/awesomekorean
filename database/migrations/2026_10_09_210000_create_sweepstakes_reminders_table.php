<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sweepstakes_reminders')) {
            return;
        }

        Schema::create('sweepstakes_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sweepstakes_id')->constrained('sweepstakes')->cascadeOnDelete();
            $table->timestamp('remind_at');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'sweepstakes_id']);
            $table->index(['remind_at', 'notified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sweepstakes_reminders');
    }
};
