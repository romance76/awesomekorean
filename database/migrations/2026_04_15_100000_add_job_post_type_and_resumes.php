<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('job_posts', function (Blueprint $table) {
            $table->string('post_type', 10)->default('hiring')->after('user_id'); // hiring or seeking
            $table->index(['post_type', 'category']);
        });

        Schema::create('resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('summary')->nullable();
            $table->string('category', 30)->nullable();
            $table->string('desired_type', 20)->nullable();
            $table->integer('desired_salary')->nullable();
            $table->string('desired_salary_type', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 10)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->text('experience')->nullable();
            $table->text('education')->nullable();
            $table->text('skills')->nullable();
            $table->text('certifications')->nullable();
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('view_count')->default(0);
            $table->timestamps();
            $table->index(['user_id']);
            $table->index(['category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('job_posts', function (Blueprint $table) {
            $table->dropIndex(['post_type', 'category']);
            $table->dropColumn('post_type');
        });
        Schema::dropIfExists('resumes');
    }
};
