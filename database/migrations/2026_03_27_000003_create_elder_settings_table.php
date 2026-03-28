<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elder_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('elder_mode')->default(false);
            $table->string('guardian_phone')->nullable();
            $table->string('guardian_name')->nullable();
            $table->integer('checkin_interval')->default(24); // hours
            $table->timestamp('last_checkin_at')->nullable();
            $table->timestamp('last_sos_at')->nullable();
            $table->boolean('alert_sent')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elder_settings');
    }
};
