<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('license_number')->nullable();
            $table->string('license_img')->nullable();
            $table->string('car_make')->nullable();
            $table->string('car_model')->nullable();
            $table->string('car_year', 4)->nullable();
            $table->string('car_color')->nullable();
            $table->string('car_plate')->nullable();
            $table->boolean('verified')->default(false);
            $table->boolean('is_online')->default(false);
            $table->decimal('current_lat', 10, 7)->nullable();
            $table->decimal('current_lng', 10, 7)->nullable();
            $table->timestamp('last_location_at')->nullable();
            $table->decimal('rating_avg', 3, 2)->default(5.00);
            $table->integer('total_rides')->default(0);
            $table->decimal('total_earnings', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('rides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passenger_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('requesting');
            // requesting / matched / ongoing / completed / cancelled
            $table->decimal('pickup_lat', 10, 7)->nullable();
            $table->decimal('pickup_lng', 10, 7)->nullable();
            $table->string('pickup_address')->nullable();
            $table->decimal('dropoff_lat', 10, 7)->nullable();
            $table->decimal('dropoff_lng', 10, 7)->nullable();
            $table->string('dropoff_address')->nullable();
            $table->decimal('estimated_fare', 8, 2)->nullable();
            $table->decimal('final_fare', 8, 2)->nullable();
            $table->decimal('platform_fee', 8, 2)->nullable();
            $table->string('payment_method')->default('cash'); // cash, card, points
            $table->tinyInteger('rating_driver')->nullable();
            $table->tinyInteger('rating_passenger')->nullable();
            $table->decimal('distance_miles', 8, 2)->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ride_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_id')->constrained('users')->cascadeOnDelete();
            $table->tinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_reviews');
        Schema::dropIfExists('rides');
        Schema::dropIfExists('driver_profiles');
    }
};
