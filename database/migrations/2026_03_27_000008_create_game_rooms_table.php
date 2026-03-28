<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('game_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('type', 20)->default('go_stop');
            $table->enum('status', ['waiting','playing','finished'])->default('waiting');
            $table->unsignedTinyInteger('min_players')->default(2);
            $table->unsignedTinyInteger('max_players')->default(3);
            $table->unsignedInteger('bet_points')->default(100);
            $table->json('state')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('game_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_room_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('seat')->default(1);
            $table->boolean('is_ready')->default(false);
            $table->integer('points_result')->default(0);
            $table->timestamps();
            $table->unique(['game_room_id', 'user_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('game_players');
        Schema::dropIfExists('game_rooms');
    }
};
