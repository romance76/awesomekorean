<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('nickname')->nullable();
            $table->string('gender')->nullable(); // male, female, other
            $table->integer('birth_year')->nullable();
            $table->integer('age_range_min')->default(20);
            $table->integer('age_range_max')->default(50);
            $table->string('region')->nullable();
            $table->text('bio')->nullable();
            $table->json('interests')->nullable();
            $table->json('photos')->nullable();
            $table->boolean('verified')->default(false);
            $table->string('visibility')->default('public'); // public, matches, hidden
            $table->timestamps();
        });

        Schema::create('match_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('id_front_img')->nullable();
            $table->string('selfie_img')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('match_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('liked_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_match')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'liked_user_id']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('region')->nullable();
            $table->string('category')->default('general'); // general, meetup, food, culture, sports
            $table->integer('max_attendees')->nullable();
            $table->integer('attendee_count')->default(0);
            $table->decimal('price', 8, 2)->default(0);
            $table->string('image')->nullable();
            $table->timestamp('event_date')->nullable();
            $table->boolean('is_online')->default(false);
            $table->string('status')->default('active'); // active, cancelled, completed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
        Schema::dropIfExists('match_likes');
        Schema::dropIfExists('match_verifications');
        Schema::dropIfExists('match_profiles');
    }
};
