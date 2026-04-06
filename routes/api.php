<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\PostController;
use App\Http\Controllers\API\BoardController;
use App\Http\Controllers\API\JobController;
use App\Http\Controllers\API\MarketController;
use App\Http\Controllers\API\BusinessController;
use App\Http\Controllers\API\RealEstateController;
use App\Http\Controllers\API\ClubController;
use App\Http\Controllers\API\NewsController;
use App\Http\Controllers\API\RecipeController;
use App\Http\Controllers\API\GroupBuyController;
use App\Http\Controllers\API\EventController;
use App\Http\Controllers\API\QaController;
use App\Http\Controllers\API\ShortController;
use App\Http\Controllers\API\ShoppingController;
use App\Http\Controllers\API\MusicController;
use App\Http\Controllers\API\SearchController;
use App\Http\Controllers\API\CommentController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\PointController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\ReportController;
use App\Http\Controllers\API\BookmarkController;
use App\Http\Controllers\API\ChatController;
use App\Http\Controllers\API\FriendController;
use App\Http\Controllers\API\MessageController;
use App\Http\Controllers\API\ElderController;
use App\Http\Controllers\API\MarketReservationController;
use App\Http\Controllers\API\GameScoreController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\AdminSettingsController;

// ─── Public Auth ───
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// ─── Public Read ───
Route::get('/boards', [BoardController::class, 'index']);
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{id}', [PostController::class, 'show']);
Route::get('/jobs', [JobController::class, 'index']);
Route::get('/jobs/{id}', [JobController::class, 'show']);
Route::get('/market', [MarketController::class, 'index']);
Route::get('/market/{id}', [MarketController::class, 'show']);
Route::get('/businesses', [BusinessController::class, 'index']);
Route::get('/businesses/{id}', [BusinessController::class, 'show']);
Route::get('/businesses/{id}/reviews', [BusinessController::class, 'reviews']);
Route::get('/realestate', [RealEstateController::class, 'index']);
Route::get('/realestate/{id}', [RealEstateController::class, 'show']);
Route::get('/clubs', [ClubController::class, 'index']);
Route::get('/clubs/{id}', [ClubController::class, 'show']);
Route::get('/clubs/{id}/posts', [ClubController::class, 'posts']);
Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/categories', [NewsController::class, 'categories']);
Route::get('/news/{id}', [NewsController::class, 'show']);
Route::get('/recipes', [RecipeController::class, 'index']);
Route::get('/recipes/categories', [RecipeController::class, 'categories']);
Route::get('/recipes/{id}', [RecipeController::class, 'show']);
Route::get('/groupbuys', [GroupBuyController::class, 'index']);
Route::get('/groupbuys/{id}', [GroupBuyController::class, 'show']);
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{id}', [EventController::class, 'show']);
Route::get('/qa', [QaController::class, 'index']);
Route::get('/qa/categories', [QaController::class, 'categories']);
Route::get('/qa/{id}', [QaController::class, 'show']);
Route::get('/shorts', [ShortController::class, 'index']);
Route::get('/shopping', [ShoppingController::class, 'index']);
Route::get('/music/categories', [MusicController::class, 'categories']);
Route::get('/music/tracks/{categoryId}', [MusicController::class, 'tracks']);
Route::get('/search', [SearchController::class, 'search']);
Route::get('/comments/{type}/{id}', [CommentController::class, 'index']);
Route::get('/settings/public', [AdminSettingsController::class, 'getPublic']);
Route::get('/games/leaderboard/{gameType}', [GameScoreController::class, 'leaderboard']);

// 게임 호환 API (old_site GameLobby용)
Route::get('/game-categories', function () {
    $settings = \App\Models\GameSetting::all()->groupBy('game_type');
    return response()->json($settings);
});
Route::get('/game-leaderboard', function () {
    return response()->json(['data' => []]);
});

// ─── Authenticated ───
Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user/profile', [ProfileController::class, 'update']);
    Route::post('/user/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/user/delete', [ProfileController::class, 'deleteAccount']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    Route::post('/posts', [PostController::class, 'store']);
    Route::put('/posts/{id}', [PostController::class, 'update']);
    Route::delete('/posts/{id}', [PostController::class, 'destroy']);
    Route::post('/posts/{id}/like', [PostController::class, 'toggleLike']);

    Route::post('/jobs', [JobController::class, 'store']);
    Route::put('/jobs/{id}', [JobController::class, 'update']);
    Route::delete('/jobs/{id}', [JobController::class, 'destroy']);

    Route::post('/market', [MarketController::class, 'store']);
    Route::put('/market/{id}', [MarketController::class, 'update']);
    Route::delete('/market/{id}', [MarketController::class, 'destroy']);

    // Market Reservations (에스크로)
    Route::post('/market/{id}/reserve', [MarketReservationController::class, 'reserve']);
    Route::post('/market/reservations/{id}/complete', [MarketReservationController::class, 'complete']);
    Route::post('/market/reservations/{id}/cancel', [MarketReservationController::class, 'cancel']);

    Route::post('/businesses', [BusinessController::class, 'store']);
    Route::post('/businesses/{id}/reviews', [BusinessController::class, 'storeReview']);

    Route::post('/realestate', [RealEstateController::class, 'store']);
    Route::put('/realestate/{id}', [RealEstateController::class, 'update']);
    Route::delete('/realestate/{id}', [RealEstateController::class, 'destroy']);

    Route::post('/clubs', [ClubController::class, 'store']);
    Route::post('/clubs/{id}/join', [ClubController::class, 'join']);
    Route::post('/clubs/{id}/leave', [ClubController::class, 'leave']);

    Route::post('/recipes', [RecipeController::class, 'store']);
    Route::delete('/recipes/{id}', [RecipeController::class, 'destroy']);

    Route::post('/groupbuys', [GroupBuyController::class, 'store']);
    Route::post('/groupbuys/{id}/join', [GroupBuyController::class, 'join']);

    Route::post('/events', [EventController::class, 'store']);
    Route::post('/events/{id}/attend', [EventController::class, 'toggleAttend']);

    Route::post('/qa', [QaController::class, 'store']);
    Route::post('/qa/{id}/answer', [QaController::class, 'answer']);
    Route::post('/qa/{id}/accept/{answerId}', [QaController::class, 'acceptAnswer']);

    Route::post('/shorts', [ShortController::class, 'store']);
    Route::post('/shorts/{id}/like', [ShortController::class, 'toggleLike']);

    Route::post('/comments', [CommentController::class, 'store']);
    Route::put('/comments/{id}', [CommentController::class, 'update']);
    Route::delete('/comments/{id}', [CommentController::class, 'destroy']);

    Route::post('/bookmarks', [BookmarkController::class, 'toggle']);
    Route::get('/bookmarks', [BookmarkController::class, 'index']);

    Route::get('/chat/rooms', [ChatController::class, 'rooms']);
    Route::post('/chat/rooms', [ChatController::class, 'createRoom']);
    Route::get('/chat/rooms/{id}/messages', [ChatController::class, 'messages']);
    Route::post('/chat/rooms/{id}/messages', [ChatController::class, 'sendMessage']);

    Route::get('/friends', [FriendController::class, 'index']);
    Route::post('/friends/request/{userId}', [FriendController::class, 'sendRequest']);
    Route::post('/friends/accept/{id}', [FriendController::class, 'accept']);
    Route::post('/friends/block/{userId}', [FriendController::class, 'block']);
    Route::delete('/friends/{id}', [FriendController::class, 'remove']);

    Route::get('/messages', [MessageController::class, 'index']);
    Route::post('/messages', [MessageController::class, 'store']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read', [NotificationController::class, 'markRead']);

    Route::get('/points/history', [PointController::class, 'history']);
    Route::get('/points/balance', [PointController::class, 'balance']);
    Route::post('/points/daily-spin', [PointController::class, 'dailySpin']);

    Route::post('/reports', [ReportController::class, 'store']);

    // Wallet (게임 호환)
    Route::get('/wallet/balance', function () {
        $u = auth()->user();
        return response()->json(['coin' => $u->points, 'chip' => $u->game_points, 'gem' => 0, 'star' => 0]);
    });
    Route::post('/wallet/daily-bonus', [PointController::class, 'dailySpin']);

    // Games
    Route::post('/games/scores', [GameScoreController::class, 'store']);
    Route::get('/games/my-scores', [GameScoreController::class, 'myScores']);

    // Music playlists
    Route::get('/music/playlists', [MusicController::class, 'playlists']);
    Route::post('/music/playlists', [MusicController::class, 'createPlaylist']);
    Route::get('/music/playlists/{id}', [MusicController::class, 'getPlaylist']);
    Route::post('/music/playlists/{id}/tracks', [MusicController::class, 'addTrack']);
    Route::delete('/music/playlists/{id}/tracks/{trackId}', [MusicController::class, 'removeTrack']);
    Route::delete('/music/playlists/{id}', [MusicController::class, 'deletePlaylist']);
    Route::get('/music/search', [MusicController::class, 'searchTracks']);

    // Payments (Stripe)
    Route::get('/payments/packages', [PaymentController::class, 'packages']);
    Route::post('/payments/create-intent', [PaymentController::class, 'createIntent']);
    Route::post('/payments/confirm', [PaymentController::class, 'confirm']);
    Route::get('/payments/history', [PaymentController::class, 'history']);

    Route::get('/elder/settings', [ElderController::class, 'settings']);
    Route::put('/elder/settings', [ElderController::class, 'updateSettings']);
    Route::post('/elder/checkin', [ElderController::class, 'checkin']);
    Route::post('/elder/sos', [ElderController::class, 'sos']);
    Route::get('/elder/checkin-history', [ElderController::class, 'checkinHistory']);
    Route::get('/elder/guardian/wards', [ElderController::class, 'guardianWards']);

    Route::get('/users/{id}', [ProfileController::class, 'show']);
    Route::get('/users/{id}/posts', [ProfileController::class, 'posts']);
});

// ─── Admin ───
Route::middleware(['auth:api', 'admin'])->prefix('admin')->group(function () {
    Route::get('/overview', [AdminController::class, 'overview']);
    Route::get('/users', [AdminController::class, 'users']);
    Route::post('/users/{id}/ban', [AdminController::class, 'banUser']);
    Route::post('/users/{id}/unban', [AdminController::class, 'unbanUser']);
    Route::get('/posts', [AdminController::class, 'posts']);
    Route::post('/posts/{id}/hide', [AdminController::class, 'hidePost']);
    Route::post('/posts/{id}/pin', [AdminController::class, 'pinPost']);
    Route::delete('/posts/{id}', [AdminController::class, 'deletePost']);
    Route::get('/boards', [AdminController::class, 'boards']);
    Route::post('/boards', [AdminController::class, 'createBoard']);
    Route::put('/boards/{id}', [AdminController::class, 'updateBoard']);
    Route::delete('/boards/{id}', [AdminController::class, 'deleteBoard']);
    Route::get('/reports', [AdminController::class, 'reports']);
    Route::put('/reports/{id}', [AdminController::class, 'updateReport']);
    Route::get('/banners', [AdminController::class, 'banners']);
    Route::post('/banners', [AdminController::class, 'createBanner']);
    Route::delete('/banners/{id}', [AdminController::class, 'deleteBanner']);
    Route::get('/ip-bans', [AdminController::class, 'ipBans']);
    Route::post('/ip-bans', [AdminController::class, 'createIpBan']);
    Route::delete('/ip-bans/{id}', [AdminController::class, 'deleteIpBan']);
    Route::get('/payments', [AdminController::class, 'payments']);
    Route::get('/settings', [AdminSettingsController::class, 'index']);
    Route::put('/settings', [AdminSettingsController::class, 'update']);
    Route::post('/settings/logo', [AdminSettingsController::class, 'uploadLogo']);

    // 수동 수집
    Route::post('/fetch-news', function () {
        try { \Artisan::call('news:fetch'); return response()->json(['success' => true, 'message' => '뉴스 수집 완료']); }
        catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()], 500); }
    });
    Route::post('/fetch-shorts', function () {
        try { \Artisan::call('shorts:fetch'); return response()->json(['success' => true, 'message' => '숏츠 수집 완료']); }
        catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()], 500); }
    });

    // Admin Music
    Route::post('/music/categories', [MusicController::class, 'storeCategory']);
    Route::post('/music/tracks', [MusicController::class, 'storeTrack']);
    Route::delete('/music/tracks/{id}', [MusicController::class, 'destroyTrack']);
});
