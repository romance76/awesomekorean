<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('creator_id');
            $table->string('name');
            $table->string('category')->default('기타'); // 스포츠, 음식/요리, 육아/교육, 취미/여가, 종교, 비즈니스, 기타
            $table->text('description')->nullable();
            $table->string('region')->nullable();
            $table->string('cover_image')->nullable();
            $table->boolean('is_approval')->default(false); // 승인제 여부
            $table->integer('member_count')->default(0);
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->foreign('creator_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('club_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('club_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('role', ['member', 'admin', 'owner'])->default('member');
            $table->enum('status', ['pending', 'approved'])->default('approved');
            $table->timestamps();

            $table->unique(['club_id', 'user_id']);
            $table->foreign('club_id')->references('id')->on('clubs')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_members');
        Schema::dropIfExists('clubs');
    }
};
