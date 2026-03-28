<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shopping_stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['korean', 'american', 'asian', 'other'])->default('korean');
            $table->string('region')->nullable();   // Atlanta, NY, LA, National...
            $table->string('website')->nullable();
            $table->string('rss_url')->nullable();
            $table->string('logo')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shopping_deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('shopping_stores')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->decimal('original_price', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->string('category')->nullable();  // 채소, 육류, 생선, 과자...
            $table->string('unit')->nullable();      // lb, ea, pack
            $table->string('source_url')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->integer('view_count')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_deals');
        Schema::dropIfExists('shopping_stores');
    }
};
