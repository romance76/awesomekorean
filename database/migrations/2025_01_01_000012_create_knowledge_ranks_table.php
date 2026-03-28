<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_ranks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->integer('answers_count')->default(0);
            $table->integer('accepted_count')->default(0); // 채택된 답변
            $table->integer('points')->default(0);
            $table->string('rank_title')->default('초보'); // 초보, 시민, 전문가, 지식왕
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_ranks');
    }
};
