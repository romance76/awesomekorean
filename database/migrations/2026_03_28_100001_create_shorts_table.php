<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shorts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('url');
            $table->string('embed_url')->nullable();
            $table->enum('platform', ['youtube', 'tiktok', 'instagram', 'other'])->default('other');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->json('tags')->nullable();       // ['요리','여행','뷰티'...]
            $table->integer('view_count')->default(0);
            $table->integer('like_count')->default(0);
            $table->integer('share_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('short_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('short_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->unique(['user_id', 'short_id']);
        });

        // 사용자 관심 태그 (개인화 피드용)
        Schema::create('user_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->json('tags')->nullable();       // ['요리','여행','뷰티'...]
            $table->timestamps();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_interests');
        Schema::dropIfExists('short_likes');
        Schema::dropIfExists('shorts');
    }
};
