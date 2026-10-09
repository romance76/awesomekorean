<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('sweepstakes_prize_claims')) {
            return;
        }
        Schema::create('sweepstakes_prize_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sweepstakes_id')->constrained('sweepstakes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rank')->default(1);
            $table->string('prize_label')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('popup_dismissed_at')->nullable();
            $table->timestamp('contact_confirmed_at')->nullable();
            $table->json('contact_snapshot')->nullable(); // 확인된 이메일/전화/주소 (비공개)
            $table->timestamp('fulfilled_at')->nullable();
            $table->string('fulfilled_note')->nullable();
            $table->timestamps();
            $table->unique(['sweepstakes_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sweepstakes_prize_claims');
    }
};
