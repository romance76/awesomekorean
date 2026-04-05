<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Users Enhancement ───
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname', 50)->nullable()->after('name');
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 10)->nullable();
            $table->string('zipcode', 10)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('avatar')->nullable();
            $table->text('bio')->nullable();
            $table->string('language', 5)->default('ko');
            $table->integer('points')->default(0);
            $table->integer('game_points')->default(0);
            $table->string('role', 20)->default('user'); // user, admin, guardian
            $table->boolean('is_banned')->default(false);
            $table->string('ban_reason')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->integer('login_count')->default(0);
            $table->string('provider', 30)->nullable();
            $table->string('provider_id')->nullable();
            $table->index(['latitude', 'longitude']);
        });

        // ─── Boards ───
        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 50)->unique();
            $table->string('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Posts ───
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->string('category', 50)->nullable();
            $table->json('images')->nullable();
            $table->integer('view_count')->default(0);
            $table->integer('like_count')->default(0);
            $table->integer('comment_count')->default(0);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 10)->nullable();
            $table->string('zipcode', 10)->nullable();
            $table->timestamps();
            $table->index(['board_id', 'created_at']);
            $table->index(['lat', 'lng']);
        });

        // ─── Comments (polymorphic) ───
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->string('commentable_type', 50);
            $table->unsignedBigInteger('commentable_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->text('content');
            $table->integer('like_count')->default(0);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
            $table->index(['commentable_type', 'commentable_id']);
        });

        // ─── Post Likes ───
        Schema::create('post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'post_id']);
        });

        // ─── Bookmarks (polymorphic) ───
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bookmarkable_type', 50);
            $table->unsignedBigInteger('bookmarkable_id');
            $table->timestamps();
            $table->index(['bookmarkable_type', 'bookmarkable_id']);
        });

        // ─── Job Posts ───
        Schema::create('job_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('company');
            $table->text('content');
            $table->string('category', 30); // it, restaurant, construction, office, education, medical, retail, beauty, driving, etc
            $table->string('type', 20); // full, part, contract
            $table->integer('salary_min')->nullable();
            $table->integer('salary_max')->nullable();
            $table->string('salary_type', 20)->nullable(); // hourly, monthly, yearly
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 10)->nullable();
            $table->string('zipcode', 10)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->integer('view_count')->default(0);
            $table->timestamps();
            $table->index(['category', 'type']);
            $table->index(['lat', 'lng']);
        });

        // ─── Market Items ───
        Schema::create('market_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->decimal('price', 10, 2)->default(0);
            $table->json('images')->nullable();
            $table->string('category', 30);
            $table->string('condition', 20); // new, like_new, good, fair
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 10)->nullable();
            $table->string('status', 20)->default('active'); // active, reserved, sold
            $table->integer('view_count')->default(0);
            $table->boolean('is_negotiable')->default(false);
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['lat', 'lng']);
        });

        // ─── Market Reservations ───
        Schema::create('market_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('buyer_id');
            $table->unsignedBigInteger('seller_id');
            $table->integer('points_held')->default(0);
            $table->string('status', 20)->default('pending'); // pending, completed, cancelled, no_show
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->foreign('buyer_id')->references('id')->on('users');
            $table->foreign('seller_id')->references('id')->on('users');
        });

        // ─── Businesses ───
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 50);
            $table->string('subcategory', 50)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 10)->nullable();
            $table->string('zipcode', 10)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->json('images')->nullable();
            $table->string('logo')->nullable();
            $table->json('hours')->nullable();
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('review_count')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_claimed')->default(false);
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->integer('view_count')->default(0);
            $table->timestamps();
            $table->index(['category', 'city']);
            $table->index(['lat', 'lng']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
        });

        // ─── Business Reviews ───
        Schema::create('business_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('rating'); // 1-5
            $table->text('content')->nullable();
            $table->json('images')->nullable();
            $table->timestamps();
        });

        // ─── Business Claims ───
        Schema::create('business_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('document_url')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ─── Real Estate Listings ───
        Schema::create('real_estate_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->string('type', 20); // rent, sale, roommate
            $table->string('property_type', 20); // apt, house, condo, studio, office
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('deposit', 12, 2)->nullable();
            $table->json('images')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 10)->nullable();
            $table->string('zipcode', 10)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->tinyInteger('bedrooms')->nullable();
            $table->tinyInteger('bathrooms')->nullable();
            $table->integer('sqft')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('view_count')->default(0);
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email')->nullable();
            $table->timestamps();
            $table->index(['type', 'property_type']);
            $table->index(['lat', 'lng']);
        });

        // ─── Clubs ───
        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 30);
            $table->string('image')->nullable();
            $table->string('type', 20)->default('online'); // online, local
            $table->string('zipcode', 10)->nullable();
            $table->integer('member_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Club Members ───
        Schema::create('club_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member'); // admin, member
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['club_id', 'user_id']);
        });

        // ─── Club Posts ───
        Schema::create('club_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->json('images')->nullable();
            $table->integer('like_count')->default(0);
            $table->integer('comment_count')->default(0);
            $table->timestamps();
        });

        // ─── News Categories ───
        Schema::create('news_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 80)->unique();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
            $table->foreign('parent_id')->references('id')->on('news_categories')->nullOnDelete();
        });

        // ─── News ───
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content')->nullable();
            $table->text('summary')->nullable();
            $table->string('source', 100)->nullable();
            $table->string('source_url')->nullable();
            $table->string('image_url')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('subcategory', 50)->nullable();
            $table->integer('view_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->foreign('category_id')->references('id')->on('news_categories')->nullOnDelete();
            $table->index('published_at');
        });

        // ─── Recipe Categories ───
        Schema::create('recipe_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 50)->unique();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ─── Recipe Posts ───
        Schema::create('recipe_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('title_ko')->nullable();
            $table->text('content');
            $table->text('content_ko')->nullable();
            $table->json('ingredients')->nullable();
            $table->json('ingredients_ko')->nullable();
            $table->json('steps')->nullable();
            $table->json('steps_ko')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->json('images')->nullable();
            $table->tinyInteger('servings')->nullable();
            $table->integer('prep_time')->nullable();
            $table->integer('cook_time')->nullable();
            $table->string('difficulty', 20)->default('medium'); // easy, medium, hard
            $table->integer('view_count')->default(0);
            $table->integer('like_count')->default(0);
            $table->integer('comment_count')->default(0);
            $table->timestamps();
            $table->foreign('category_id')->references('id')->on('recipe_categories')->nullOnDelete();
        });

        // ─── Group Buys ───
        Schema::create('group_buys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->json('images')->nullable();
            $table->string('product_url')->nullable();
            $table->decimal('original_price', 10, 2)->nullable();
            $table->decimal('group_price', 10, 2)->nullable();
            $table->integer('min_participants')->default(2);
            $table->integer('max_participants')->nullable();
            $table->integer('current_participants')->default(0);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 10)->nullable();
            $table->string('status', 20)->default('recruiting'); // recruiting, confirmed, completed, cancelled
            $table->timestamp('deadline')->nullable();
            $table->timestamps();
            $table->index(['lat', 'lng']);
        });

        // ─── Events ───
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('content')->nullable();
            $table->string('image_url')->nullable();
            $table->string('category', 30)->nullable();
            $table->string('organizer')->nullable();
            $table->string('venue')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 10)->nullable();
            $table->string('zipcode', 10)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('url')->nullable();
            $table->string('source', 50)->nullable();
            $table->string('source_id', 100)->nullable();
            $table->integer('view_count')->default(0);
            $table->integer('attendee_count')->default(0);
            $table->timestamps();
            $table->index(['start_date', 'end_date']);
            $table->index(['lat', 'lng']);
        });

        // ─── Event Attendees ───
        Schema::create('event_attendees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('going'); // going, interested
            $table->timestamps();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->unique(['event_id', 'user_id']);
        });

        // ─── QA Categories ───
        Schema::create('qa_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 50)->unique();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ─── QA Posts ───
        Schema::create('qa_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('title');
            $table->text('content');
            $table->integer('bounty_points')->default(0);
            $table->integer('view_count')->default(0);
            $table->integer('answer_count')->default(0);
            $table->boolean('is_resolved')->default(false);
            $table->unsignedBigInteger('best_answer_id')->nullable();
            $table->timestamps();
            $table->foreign('category_id')->references('id')->on('qa_categories')->nullOnDelete();
        });

        // ─── QA Answers ───
        Schema::create('qa_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qa_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->integer('like_count')->default(0);
            $table->boolean('is_best')->default(false);
            $table->timestamps();
        });

        // ─── Shorts ───
        Schema::create('shorts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('title');
            $table->string('video_url');
            $table->string('thumbnail_url')->nullable();
            $table->string('youtube_id', 20)->nullable();
            $table->integer('duration')->nullable();
            $table->integer('view_count')->default(0);
            $table->integer('like_count')->default(0);
            $table->integer('comment_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        // ─── Short Likes ───
        Schema::create('short_likes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('short_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->foreign('short_id')->references('id')->on('shorts')->cascadeOnDelete();
            $table->unique(['short_id', 'user_id']);
        });

        // ─── Shopping Stores ───
        Schema::create('shopping_stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->string('website')->nullable();
            $table->string('category', 50)->nullable();
            $table->timestamps();
        });

        // ─── Shopping Deals ───
        Schema::create('shopping_deals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->decimal('original_price', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->integer('discount_percent')->nullable();
            $table->string('url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->foreign('store_id')->references('id')->on('shopping_stores')->nullOnDelete();
        });

        // ─── Chat Rooms ───
        Schema::create('chat_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('type', 20)->default('dm'); // dm, group
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        // ─── Chat Room Users ───
        Schema::create('chat_room_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();
            $table->unique(['chat_room_id', 'user_id']);
        });

        // ─── Chat Messages ───
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content')->nullable();
            $table->string('type', 20)->default('text'); // text, image, file
            $table->string('file_url')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        // ─── Music Categories ───
        Schema::create('music_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 50)->unique();
            $table->string('image')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ─── Music Tracks ───
        Schema::create('music_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('music_categories')->cascadeOnDelete();
            $table->string('title');
            $table->string('artist')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('youtube_id', 20)->nullable();
            $table->integer('duration')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ─── User Playlists ───
        Schema::create('user_playlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        // ─── User Playlist Tracks ───
        Schema::create('user_playlist_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained('user_playlists')->cascadeOnDelete();
            $table->foreignId('track_id')->constrained('music_tracks')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ─── Point Logs ───
        Schema::create('point_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->string('type', 20); // earn, spend, purchase
            $table->string('reason');
            $table->string('related_type', 50)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->integer('balance_after')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        // ─── User Daily Spins ───
        Schema::create('user_daily_spins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('spun_at');
            $table->integer('points_won')->default(0);
            $table->timestamps();
        });

        // ─── Game Rooms ───
        Schema::create('game_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('game_type', 30);
            $table->unsignedBigInteger('host_id');
            $table->string('status', 20)->default('waiting'); // waiting, playing, finished
            $table->integer('max_players')->default(4);
            $table->integer('bet_amount')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->foreign('host_id')->references('id')->on('users');
        });

        // ─── Game Players ───
        Schema::create('game_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('score')->default(0);
            $table->boolean('is_winner')->default(false);
            $table->integer('bet_amount')->default(0);
            $table->timestamps();
        });

        // ─── Game Settings ───
        Schema::create('game_settings', function (Blueprint $table) {
            $table->id();
            $table->string('game_type', 30);
            $table->string('key', 50);
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['game_type', 'key']);
        });

        // ─── Elder Settings ───
        Schema::create('elder_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('guardian_id')->nullable();
            $table->integer('checkin_interval')->default(24); // hours
            $table->json('sos_contacts')->nullable();
            $table->json('medications')->nullable();
            $table->text('health_notes')->nullable();
            $table->timestamps();
            $table->foreign('guardian_id')->references('id')->on('users')->nullOnDelete();
        });

        // ─── Elder Checkin Logs ───
        Schema::create('elder_checkin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_in_at');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('status', 20)->default('ok'); // ok, missed, sos
            $table->timestamps();
        });

        // ─── Elder SOS Logs ───
        Schema::create('elder_sos_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->text('message')->nullable();
            $table->json('contacts_notified')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // ─── Friends ───
        Schema::create('friends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('friend_id');
            $table->string('status', 20)->default('pending'); // pending, accepted, blocked
            $table->timestamps();
            $table->foreign('friend_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'friend_id']);
        });

        // ─── Notifications ───
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('title');
            $table->text('content')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });

        // ─── Reports ───
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reporter_id');
            $table->string('reportable_type', 50);
            $table->unsignedBigInteger('reportable_id');
            $table->string('reason');
            $table->text('content')->nullable();
            $table->string('status', 20)->default('pending'); // pending, reviewed, resolved
            $table->text('admin_note')->nullable();
            $table->timestamps();
            $table->foreign('reporter_id')->references('id')->on('users');
            $table->index(['reportable_type', 'reportable_id']);
        });

        // ─── IP Bans ───
        Schema::create('ip_bans', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('banned_by')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->foreign('banned_by')->references('id')->on('users')->nullOnDelete();
            $table->index('ip_address');
        });

        // ─── Site Settings ───
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('group', 50)->default('general');
            $table->timestamps();
        });

        // ─── Banners ───
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('image_url');
            $table->string('link_url')->nullable();
            $table->string('position', 30)->default('home');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        // ─── Payments ───
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_payment_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 5)->default('usd');
            $table->integer('points_purchased')->default(0);
            $table->string('status', 20)->default('pending'); // pending, completed, failed
            $table->timestamps();
        });

        // ─── Messages (DM) ───
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_id');
            $table->unsignedBigInteger('receiver_id');
            $table->text('content');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
            $table->foreign('sender_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('receiver_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['sender_id', 'receiver_id']);
        });
    }

    public function down(): void
    {
        $tables = [
            'messages', 'payments', 'banners', 'site_settings', 'ip_bans',
            'reports', 'notifications', 'friends',
            'elder_sos_logs', 'elder_checkin_logs', 'elder_settings',
            'game_settings', 'game_players', 'game_rooms',
            'user_daily_spins', 'point_logs',
            'user_playlist_tracks', 'user_playlists', 'music_tracks', 'music_categories',
            'chat_messages', 'chat_room_users', 'chat_rooms',
            'shopping_deals', 'shopping_stores',
            'short_likes', 'shorts',
            'qa_answers', 'qa_posts', 'qa_categories',
            'event_attendees', 'events',
            'group_buys',
            'recipe_posts', 'recipe_categories',
            'news', 'news_categories',
            'club_posts', 'club_members', 'clubs',
            'real_estate_listings',
            'business_claims', 'business_reviews', 'businesses',
            'market_reservations', 'market_items',
            'job_posts',
            'bookmarks', 'post_likes', 'comments', 'posts', 'boards',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('users', function (Blueprint $table) {
            $cols = [
                'nickname', 'phone', 'address', 'city', 'state', 'zipcode',
                'latitude', 'longitude', 'avatar', 'bio', 'language',
                'points', 'game_points', 'role', 'is_banned', 'ban_reason',
                'last_login_at', 'login_count', 'provider', 'provider_id',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
