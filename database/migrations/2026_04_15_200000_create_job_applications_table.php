<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_post_id')->constrained('job_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resume_id')->nullable()->constrained('resumes')->nullOnDelete();
            $table->text('message')->nullable();
            $table->enum('status', ['pending', 'viewed', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();

            $table->unique(['job_post_id', 'user_id']); // 중복 지원 방지
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
