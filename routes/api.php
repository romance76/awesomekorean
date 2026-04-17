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
use App\Http\Controllers\API\AdminRecipeController;
use App\Http\Controllers\API\ThumbnailController;
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
use App\Http\Controllers\API\PokerController;
use App\Http\Controllers\API\PokerTournamentController;
use App\Http\Controllers\API\ConversationController;
use App\Http\Controllers\API\CallController;
use App\Http\Controllers\API\UserBlockController;
use App\Http\Controllers\API\PlacesController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;

// ─── Broadcasting Auth ───
Broadcast::routes(['middleware' => ['auth:api']]);

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
Route::get('/jobs/featured', [JobController::class, 'featured']);
Route::get('/jobs/promotion-slots', [JobController::class, 'promotionSlots']);
Route::get('/promotion/settings', function (\Illuminate\Http\Request $r) {
    if ($r->resource && in_array($r->resource, \App\Support\PromotionSettings::RESOURCES)) {
        return response()->json(['success' => true, 'data' => \App\Support\PromotionSettings::forResource($r->resource)]);
    }
    return response()->json(['success' => true, 'data' => \App\Support\PromotionSettings::all()]);
});
Route::get('/jobs/{id}', [JobController::class, 'show']);
Route::get('/resumes', [\App\Http\Controllers\API\ResumeController::class, 'index']);
Route::get('/resumes/{id}', [\App\Http\Controllers\API\ResumeController::class, 'show']);
Route::get('/market', [MarketController::class, 'index']);
Route::get('/market/promotion-slots', [MarketController::class, 'promotionSlots']);
Route::get('/market/{id}', [MarketController::class, 'show']);
Route::get('/businesses', [BusinessController::class, 'index']);
Route::get('/businesses/page-data', [BusinessController::class, 'pageData']);
Route::get('/businesses/promotion-slots', [BusinessController::class, 'promotionSlots']);
Route::get('/businesses/{id}', [BusinessController::class, 'show']);
Route::get('/businesses/{id}/reviews', [BusinessController::class, 'reviews']);
Route::get('/businesses/{id}/menus', [BusinessController::class, 'menus']);
Route::get('/realestate', [RealEstateController::class, 'index']);
Route::get('/realestate/promotion-slots', [RealEstateController::class, 'promotionSlots']);
Route::get('/realestate/{id}', [RealEstateController::class, 'show']);
Route::get('/clubs', [ClubController::class, 'index']);
Route::get('/clubs/{id}', [ClubController::class, 'show']);
Route::get('/clubs/{id}/members', [ClubController::class, 'members']);
Route::get('/clubs/{id}/boards', [ClubController::class, 'boards']);
Route::get('/clubs/{id}/boards/{boardId}/posts', [ClubController::class, 'boardPosts']);
Route::get('/clubs/{id}/posts', [ClubController::class, 'posts']);
Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/categories', [NewsController::class, 'categories']);
Route::get('/news/{id}', [NewsController::class, 'show']);
// 썸네일 프록시/캐시 (모든 리스트 페이지가 공유)
Route::get('/thumb', [ThumbnailController::class, 'show']);
Route::get('/banners/active', [\App\Http\Controllers\API\BannerController::class, 'show']);
Route::get('/banners/mobile', [\App\Http\Controllers\API\BannerController::class, 'mobileAd']);
Route::get('/banners/all', [\App\Http\Controllers\API\BannerController::class, 'all']);
Route::get('/hero-banners', function () {
    return response()->json(['success' => true, 'data' => \App\Models\HeroBanner::active()->orderBy('sort_order')->get()]);
})->middleware('cache.api:1800');
Route::post('/banners/{id}/click', [\App\Http\Controllers\API\BannerController::class, 'click']);
Route::get('/ad-settings/public', [\App\Http\Controllers\API\AdminSettingsController::class, 'getAdPageSettingsPublic']);

Route::get('/recipes', [RecipeController::class, 'index']);
Route::get('/recipes/categories', [RecipeController::class, 'categories']);
Route::get('/recipes/{id}', [RecipeController::class, 'show']);
Route::get('/recipes/{id}/comments', [RecipeController::class, 'comments']);
Route::get('/groupbuys', [GroupBuyController::class, 'index']);
Route::get('/groupbuys/my-joined', [GroupBuyController::class, 'myJoined'])->middleware('auth:api');
Route::get('/groupbuys/{id}', [GroupBuyController::class, 'show']);
Route::get('/groupbuys/{id}/participants', [GroupBuyController::class, 'participants']);
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{id}', [EventController::class, 'show']);
Route::get('/events/{id}/attendees', [EventController::class, 'attendees']);
Route::get('/qa', [QaController::class, 'index']);
Route::get('/qa/categories', [QaController::class, 'categories']);
Route::get('/qa/{id}', [QaController::class, 'show']);
Route::get('/shorts', [ShortController::class, 'index']);
Route::get('/shopping', [ShoppingController::class, 'index']);
Route::get('/music/categories', [MusicController::class, 'categories']);
Route::get('/music/tracks/{categoryId}', [MusicController::class, 'tracks']);
Route::get('/search', [SearchController::class, 'search']);
Route::get('/comments/{type}/{id}', [CommentController::class, 'index']);
Route::get('/settings/public', [AdminSettingsController::class, 'getPublic'])->middleware('cache.api:1800'); // 30분 캐시
Route::get('/settings/points', function () {
    $settings = \DB::table('point_settings')->pluck('value', 'key');
    return response()->json(['success' => true, 'data' => $settings]);
});
Route::get('/games/leaderboard/{gameType}', [GameScoreController::class, 'leaderboard']);

// ─── Public Poker ───
Route::get('/poker/tournaments', [PokerTournamentController::class, 'index']);
Route::get('/poker/tournaments/{id}', [PokerTournamentController::class, 'show']);
Route::get('/poker/tournaments/{id}/results', [PokerTournamentController::class, 'results']);
Route::get('/poker/leaderboard', [PokerController::class, 'leaderboard']);

// 게임 호환 API (old_site GameLobby용)
Route::get('/game-categories', function () {
    $settings = \App\Models\GameSetting::all()->groupBy('game_type');
    return response()->json($settings);
});
Route::get('/game-leaderboard', function () {
    return response()->json(['data' => []]);
});

// ─── Public API (no auth) ───
Route::get('/places/nearby-schools', [PlacesController::class, 'nearbySchools']);

// ─── Authenticated ───
Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // 온라인 heartbeat
    Route::post('/heartbeat', function () {
        auth()->user()->update(['last_active_at' => now()]);
        return response()->json(['success' => true]);
    });
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
    Route::post('/jobs/{id}/apply', [JobController::class, 'apply']);
    Route::get('/my-jobs', [JobController::class, 'myPosts']);
    Route::get('/my-applications', [JobController::class, 'myApplications']);
    Route::get('/jobs/{id}/applicants', [JobController::class, 'applicants']);
    Route::post('/jobs/{id}/promote', [JobController::class, 'promote']);

    // 이력서
    Route::get('/my-resume', [\App\Http\Controllers\API\ResumeController::class, 'myResume']);
    Route::post('/resumes', [\App\Http\Controllers\API\ResumeController::class, 'store']);
    Route::put('/resumes/{id}', [\App\Http\Controllers\API\ResumeController::class, 'update']);
    Route::delete('/resumes/{id}', [\App\Http\Controllers\API\ResumeController::class, 'destroy']);

    Route::post('/market', [MarketController::class, 'store']);
    Route::put('/market/{id}', [MarketController::class, 'update']);
    Route::delete('/market/{id}', [MarketController::class, 'destroy']);

    // Market Reservations (에스크로)
    Route::post('/market/{id}/reserve', [MarketReservationController::class, 'reserve']);
    Route::post('/market/reservations/{id}/complete', [MarketReservationController::class, 'complete']);
    Route::post('/market/reservations/{id}/cancel', [MarketReservationController::class, 'cancel']);

    // 배너 광고 신청
    Route::get('/banners/my', [\App\Http\Controllers\API\BannerController::class, 'myBanners']);
    Route::post('/banners/apply', [\App\Http\Controllers\API\BannerController::class, 'store']);

    // 홀드 (구매자가 포인트로 물건 예약)
    Route::post('/market/{id}/hold', [MarketController::class, 'hold']);
    Route::post('/market/{id}/hold/cancel', [MarketController::class, 'cancelHold']);
    // 상위노출 (판매자가 포인트로 부스트)
    Route::post('/market/{id}/boost', [MarketController::class, 'boost']);
    Route::post('/market/{id}/bump', [MarketController::class, 'bump']);
    Route::post('/market/{id}/promote', [MarketController::class, 'promote']);
    Route::post('/realestate/{id}/promote', [RealEstateController::class, 'promote']);
    Route::post('/businesses/{id}/promote', [BusinessController::class, 'promote']);

    Route::post('/businesses', [BusinessController::class, 'store']);
    Route::post('/businesses/{id}/reviews', [BusinessController::class, 'storeReview']);
    Route::post('/businesses/{id}/claim', [BusinessController::class, 'claim']);
    Route::get('/my-businesses', [BusinessController::class, 'myBusinesses']);
    Route::put('/my-businesses/{id}', [BusinessController::class, 'updateMyBusiness']);
    Route::post('/my-businesses/{id}/photos', [BusinessController::class, 'uploadMyBusinessPhotos']);
    Route::post('/my-businesses/{id}/menus', [BusinessController::class, 'storeMenu']);
    Route::put('/my-businesses/{bizId}/menus/{menuId}', [BusinessController::class, 'updateMenu']);
    Route::delete('/my-businesses/{bizId}/menus/{menuId}', [BusinessController::class, 'deleteMenu']);

    Route::post('/realestate', [RealEstateController::class, 'store']);
    Route::put('/realestate/{id}', [RealEstateController::class, 'update']);
    Route::delete('/realestate/{id}', [RealEstateController::class, 'destroy']);

    Route::get('/my-clubs', [ClubController::class, 'myClubs']);
    Route::post('/clubs', [ClubController::class, 'store']);
    Route::put('/clubs/{id}', [ClubController::class, 'update']);
    Route::delete('/clubs/{id}', [ClubController::class, 'destroy']);
    Route::post('/clubs/{id}/join', [ClubController::class, 'join']);
    Route::post('/clubs/{id}/leave', [ClubController::class, 'leave']);
    Route::get('/clubs/{id}/pending-members', [ClubController::class, 'pendingMembers']);
    Route::post('/clubs/{id}/members/{userId}/approve', [ClubController::class, 'approveMember']);
    Route::post('/clubs/{id}/members/{userId}/reject', [ClubController::class, 'rejectMember']);
    Route::put('/clubs/{id}/members/{userId}', [ClubController::class, 'updateMember']);
    Route::delete('/clubs/{id}/members/{userId}', [ClubController::class, 'removeMember']);
    Route::post('/clubs/{id}/boards', [ClubController::class, 'createBoard']);
    Route::put('/clubs/{id}/boards/{boardId}', [ClubController::class, 'updateBoard']);
    Route::delete('/clubs/{id}/boards/{boardId}', [ClubController::class, 'deleteBoard']);
    Route::post('/clubs/{id}/posts', [ClubController::class, 'createPost']);
    Route::put('/clubs/posts/{postId}', [ClubController::class, 'updatePost']);
    Route::delete('/clubs/posts/{postId}', [ClubController::class, 'deletePost']);
    Route::post('/clubs/{id}/chatroom', [ClubController::class, 'createChatRoom']);

    // Recipes (유저 작성/평점/찜)
    Route::get('/recipes/my/favorites', [RecipeController::class, 'myFavorites']);
    Route::post('/recipes', [RecipeController::class, 'store']);
    Route::put('/recipes/{id}', [RecipeController::class, 'update']);
    Route::delete('/recipes/{id}', [RecipeController::class, 'destroy']);
    Route::post('/recipes/{id}/rate', [RecipeController::class, 'rate']);
    Route::delete('/recipes/{id}/comments/{commentId}', [RecipeController::class, 'deleteComment']);
    Route::post('/recipes/{id}/favorite', [RecipeController::class, 'toggleFavorite']);

    Route::post('/groupbuys', [GroupBuyController::class, 'store']);
    Route::put('/groupbuys/{id}', [GroupBuyController::class, 'update']);
    Route::delete('/groupbuys/{id}', [GroupBuyController::class, 'destroy']);
    Route::post('/groupbuys/{id}/join', [GroupBuyController::class, 'join']);
    Route::post('/groupbuys/{id}/cancel', [GroupBuyController::class, 'cancelParticipation']);

    Route::post('/events', [EventController::class, 'store']);
    Route::put('/events/{id}', [EventController::class, 'update']);
    Route::delete('/events/{id}', [EventController::class, 'destroy']);
    Route::post('/events/{id}/attend', [EventController::class, 'toggleAttend']);

    Route::post('/qa', [QaController::class, 'store']);
    Route::post('/qa/{id}/answer', [QaController::class, 'answer']);
    Route::post('/qa/{id}/accept/{answerId}', [QaController::class, 'acceptAnswer']);
    Route::delete('/qa/{id}/answer/{answerId}', [QaController::class, 'deleteAnswer']);
    Route::post('/qa/{id}/answer/{answerId}/like', [QaController::class, 'likeAnswer']);

    Route::post('/shorts', [ShortController::class, 'store']);
    Route::post('/shorts/{id}/like', [ShortController::class, 'toggleLike']);
    Route::post('/shorts/{id}/viewed', [ShortController::class, 'markViewed']);

    Route::post('/comments', [CommentController::class, 'store']);
    Route::put('/comments/{id}', [CommentController::class, 'update']);
    Route::delete('/comments/{id}', [CommentController::class, 'destroy']);
    Route::post('/comments/{id}/vote', [CommentController::class, 'vote']);

    Route::post('/bookmarks', [BookmarkController::class, 'toggle']);
    Route::get('/bookmarks', [BookmarkController::class, 'index']);
    Route::get('/bookmarks/check', [BookmarkController::class, 'check']);

    Route::get('/chat/rooms', [ChatController::class, 'rooms']);
    Route::post('/chat/rooms', [ChatController::class, 'createRoom']);
    Route::get('/chat/rooms/{id}', [ChatController::class, 'showRoom']);
    Route::get('/chat/rooms/{id}/messages', [ChatController::class, 'messages']);
    Route::get('/chat/rooms/{id}/messages/search', [ChatController::class, 'searchMessages']);
    Route::get('/chat/rooms/{id}/participants', [ChatController::class, 'participants']);
    Route::post('/chat/rooms/{id}/messages', [ChatController::class, 'sendMessage']);
    Route::post('/chat/rooms/{id}/read', [ChatController::class, 'markRead']);

    Route::get('/friends', [FriendController::class, 'index']);
    Route::post('/friends/request/{userId}', [FriendController::class, 'sendRequest']);
    Route::post('/friends/accept/{id}', [FriendController::class, 'accept']);
    Route::post('/friends/block/{userId}', [FriendController::class, 'block']);
    Route::delete('/friends/cancel/{id}', [FriendController::class, 'cancelRequest']);
    Route::delete('/friends/{id}', [FriendController::class, 'remove']);
    Route::post('/friends/private-chat', [FriendController::class, 'createPrivateChat']);
    Route::post('/friends/group-chat', [FriendController::class, 'createGroupChat']);
    Route::get('/friends/chat-rooms', [FriendController::class, 'privateChatRooms']);

    Route::get('/messages', [MessageController::class, 'index']);
    Route::post('/messages', [MessageController::class, 'store']);
    Route::post('/messages/{id}/read', [MessageController::class, 'markRead']);
    Route::delete('/messages/{id}', [MessageController::class, 'destroy']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markOneRead']);

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
    Route::post('/music/import-youtube', [MusicController::class, 'importYoutube']);
    Route::post('/music/favorites', [MusicController::class, 'toggleFavorite']);
    Route::get('/music/favorites', [MusicController::class, 'favorites']);

    // Payments (Stripe)
    Route::get('/payments/packages', [PaymentController::class, 'packages']);
    Route::post('/payments/create-intent', [PaymentController::class, 'createIntent']);
    Route::post('/payments/confirm', [PaymentController::class, 'confirm']);
    Route::get('/payments/history', [PaymentController::class, 'history']);

    Route::get('/elder/settings', [ElderController::class, 'settings']);
    Route::put('/elder/settings', [ElderController::class, 'updateSettings']);
    Route::post('/elder/search-ward', [ElderController::class, 'searchWard']);
    Route::post('/elder/register-ward', [ElderController::class, 'registerWard']);
    Route::get('/elder/my-wards', [ElderController::class, 'myWards']);
    Route::post('/elder/save-schedule', [ElderController::class, 'saveSchedule']);
    Route::post('/elder/checkin', [ElderController::class, 'checkin']);
    Route::post('/elder/sos', [ElderController::class, 'sos']);
    Route::get('/elder/checkin-history', [ElderController::class, 'checkinHistory']);
    Route::get('/elder/guardian/wards', [ElderController::class, 'guardianWards']);

    Route::get('/users/{id}', [ProfileController::class, 'show']);
    Route::get('/users/{id}/posts', [ProfileController::class, 'posts']);

    // ─── 안심 커뮤니케이션 ───
    Route::prefix('comms')->group(function () {
        // 채팅
        Route::get('/conversations', [ConversationController::class, 'index']);
        Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
        Route::post('/conversations/{partnerId}/send', [ConversationController::class, 'send']);

        // 통화
        Route::post('/calls/initiate', [CallController::class, 'initiate']);
        Route::post('/calls/signal', [CallController::class, 'signal']);
        Route::post('/calls/{call}/answer', [CallController::class, 'answer']);
        Route::post('/calls/{call}/end', [CallController::class, 'end']);
        Route::get('/calls/{call}/status', [CallController::class, 'status']);
        Route::get('/calls/history', [CallController::class, 'history']);
        Route::post('/calls/client-log', [CallController::class, 'clientLog']);

        // 차단
        Route::post('/users/{user}/block', [UserBlockController::class, 'block']);
        Route::delete('/users/{user}/block', [UserBlockController::class, 'unblock']);

        // FCM 토큰 등록
        Route::post('/push/register', function (\Illuminate\Http\Request $request) {
            $request->validate(['fcm_token' => 'required|string', 'platform' => 'required|in:web,ios,android']);
            $request->user()->update(['fcm_token' => $request->fcm_token, 'push_platform' => $request->platform]);
            return response()->json(['success' => true]);
        });

        // 온라인 heartbeat
        Route::post('/presence/ping', function (\Illuminate\Http\Request $request) {
            Cache::put('user-online-' . $request->user()->id, true, now()->addSeconds(30));
            return response()->json(['success' => true]);
        });

        // PeerJS peer ID 등록/조회
        Route::post('/presence/peer-id', function (\Illuminate\Http\Request $request) {
            $request->validate(['peer_id' => 'required|string']);
            Cache::put('peer-id-' . $request->user()->id, $request->peer_id, now()->addHours(2));
            return response()->json(['success' => true]);
        });
        Route::get('/presence/peer-id/{userId}', function ($userId) {
            return response()->json(['peer_id' => Cache::get('peer-id-' . $userId)]);
        });
    });

    // ─── Poker ───
    Route::prefix('poker')->group(function () {
        Route::get('/wallet', [PokerController::class, 'wallet']);
        Route::post('/wallet/deposit', [PokerController::class, 'deposit']);
        Route::post('/wallet/withdraw', [PokerController::class, 'withdraw']);
        Route::get('/wallet/transactions', [PokerController::class, 'transactions']);
        Route::post('/games', [PokerController::class, 'storeGame']);
        Route::get('/stats', [PokerController::class, 'stats']);
        Route::get('/leaderboard', [PokerController::class, 'leaderboard']);
        Route::get('/history', [PokerController::class, 'history']);

        // 토너먼트
        Route::get('/my-registrations', [PokerTournamentController::class, 'myRegistrations']);
        Route::post('/tournaments/{id}/register', [PokerTournamentController::class, 'register']);
        Route::delete('/tournaments/{id}/register', [PokerTournamentController::class, 'unregister']);
        Route::post('/tournaments/{id}/heartbeat', [PokerTournamentController::class, 'heartbeat']);

        // 멀티플레이어
        Route::post('/multi/quick-match', [\App\Http\Controllers\API\PokerMultiController::class, 'quickMatch']);
        Route::get('/multi/game/{gameId}', [\App\Http\Controllers\API\PokerMultiController::class, 'getState']);
        Route::post('/multi/game/{gameId}/action', [\App\Http\Controllers\API\PokerMultiController::class, 'action']);
        Route::post('/multi/game/{gameId}/chat', [\App\Http\Controllers\API\PokerMultiController::class, 'chat']);
        Route::get('/multi/game/{gameId}/timeout', [\App\Http\Controllers\API\PokerMultiController::class, 'checkTimeout']);
        Route::post('/tournaments/{id}/join', [\App\Http\Controllers\API\PokerMultiController::class, 'joinTournament']);
        Route::get('/tournaments/{id}/waiting', [\App\Http\Controllers\API\PokerMultiController::class, 'waitingRoom']);
        Route::get('/tournaments/{id}/game', [\App\Http\Controllers\API\PokerMultiController::class, 'tournamentGameState']);
    });
});

// ─── Admin ───
Route::middleware(['auth:api', 'admin'])->prefix('admin')->group(function () {
    Route::get('/overview', [AdminController::class, 'overview']);
    Route::get('/users', [AdminController::class, 'users']);
    Route::post('/users/{id}/ban', [AdminController::class, 'banUser']);
    Route::post('/users/{id}/unban', [AdminController::class, 'unbanUser']);
    Route::get('/users/{id}/detail', [AdminController::class, 'userDetail']);
    Route::put('/users/{id}', [AdminController::class, 'updateUser']);
    Route::get('/posts/{id}/detail', [AdminController::class, 'postDetail']);
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
    Route::get('/banners', [AdminController::class, 'bannerList']);
    Route::get('/ad-settings', [\App\Http\Controllers\API\AdminSettingsController::class, 'getAdPageSettings']);
    Route::post('/ad-settings', [\App\Http\Controllers\API\AdminSettingsController::class, 'saveAdPageSettings']);
    Route::post('/ad-slot-prices', [\App\Http\Controllers\API\AdminSettingsController::class, 'saveSlotMinPrices']);
    Route::post('/banners', [AdminController::class, 'createBanner']);
    Route::post('/banners/{id}/approve', [AdminController::class, 'approveBanner']);
    Route::post('/banners/{id}/reject', [AdminController::class, 'rejectBanner']);
    Route::post('/banners/{id}/pause', [AdminController::class, 'pauseBanner']);
    Route::delete('/banners/{id}', [AdminController::class, 'deleteBanner']);

    // 히어로 배너 관리
    Route::get('/hero-banners', fn() => response()->json(['success'=>true, 'data'=> \App\Models\HeroBanner::orderBy('sort_order')->get()]));
    Route::post('/hero-banners', function (\Illuminate\Http\Request $r) { return response()->json(['success'=>true, 'data'=> \App\Models\HeroBanner::create($r->all())]); });
    Route::put('/hero-banners/{id}', function (\Illuminate\Http\Request $r, $id) { \App\Models\HeroBanner::findOrFail($id)->update($r->all()); return response()->json(['success'=>true]); });
    Route::delete('/hero-banners/{id}', function ($id) { \App\Models\HeroBanner::findOrFail($id)->delete(); return response()->json(['success'=>true]); });

    Route::get('/ip-bans', [AdminController::class, 'ipBans']);
    Route::post('/ip-bans', [AdminController::class, 'createIpBan']);
    Route::delete('/ip-bans/{id}', [AdminController::class, 'deleteIpBan']);
    Route::get('/payments', [AdminController::class, 'payments']);
    Route::post('/payments/{id}/refund', [AdminController::class, 'refundPayment']);
    Route::get('/claims', [AdminController::class, 'claims']);
    Route::post('/claims/{id}/approve', [AdminController::class, 'approveClaim']);
    Route::post('/claims/{id}/reject', [AdminController::class, 'rejectClaim']);
    Route::get('/settings', [AdminSettingsController::class, 'index']);
    Route::get('/settings/all', [AdminSettingsController::class, 'getAll']);
    Route::put('/settings', [AdminSettingsController::class, 'update']);
    Route::post('/settings/company', [AdminSettingsController::class, 'saveCompany']);
    Route::post('/settings/site', [AdminSettingsController::class, 'saveSite']);
    Route::post('/settings/footer', [AdminSettingsController::class, 'saveFooter']);
    Route::post('/settings/terms/{type}', [AdminSettingsController::class, 'saveTerms']);
    Route::post('/settings/notifications', [AdminSettingsController::class, 'saveNotifications']);
    Route::post('/settings/stripe', [AdminSettingsController::class, 'saveStripe']);
    Route::post('/settings/payment-gateway', [AdminSettingsController::class, 'savePaymentGateway']);
    Route::post('/settings/seo', [AdminSettingsController::class, 'saveSeo']);
    Route::post('/settings/generate-vapid', [AdminSettingsController::class, 'generateVapid']);
    Route::get('/settings/menus', [AdminSettingsController::class, 'getMenus']);
    Route::post('/settings/menus/batch', [AdminSettingsController::class, 'saveMenus']);
    Route::post('/settings/logo', [AdminSettingsController::class, 'uploadLogo']);
    Route::get('/api-keys', [AdminSettingsController::class, 'getApiKeys']);
    Route::post('/api-keys', [AdminSettingsController::class, 'storeApiKey']);
    Route::put('/api-keys/{id}', [AdminSettingsController::class, 'updateApiKey']);
    Route::delete('/api-keys/{id}', [AdminSettingsController::class, 'deleteApiKey']);
    Route::get('/api-keys/{id}/reveal', [AdminSettingsController::class, 'revealApiKey']);

    // Firebase 설정
    Route::get('/firebase', [AdminSettingsController::class, 'getFirebase']);
    Route::post('/firebase', [AdminSettingsController::class, 'saveFirebase']);

    // 포인트 설정
    Route::get('/point-settings', [AdminSettingsController::class, 'getPointSettings']);
    Route::post('/point-settings', [AdminSettingsController::class, 'savePointSettings']);

    // 수동 수집
    Route::post('/fetch-music', function () {
        try {
            $before = \App\Models\MusicTrack::count();
            \Artisan::call('music:fetch', ['--daily' => 100, '--korean-ratio' => 75]);
            $output = \Artisan::output();
            $after = \App\Models\MusicTrack::count();
            $added = $after - $before;

            // API 할당량 초과 감지
            if (str_contains($output, 'API 오류: 403')) {
                return response()->json([
                    'success' => false,
                    'message' => "YouTube API 할당량 초과! 추가된 곡: {$added}곡. 내일 다시 시도하거나 YouTube 데이터 API 키의 할당량을 확인하세요.",
                    'added' => $added,
                    'total' => $after,
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => $added > 0 ? "음악 {$added}곡 추가 완료 (전체 {$after}곡)" : "추가된 곡 없음 (전체 {$after}곡, 중복/필터로 제외)",
                'added' => $added,
                'total' => $after,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    });
    Route::post('/fetch-news', function () {
        try { \Artisan::call('news:fetch'); return response()->json(['success' => true, 'message' => '뉴스 수집 완료']); }
        catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()], 500); }
    });
    Route::post('/fetch-shorts', function () {
        try {
            $before = \App\Models\Short::count();
            \Artisan::call('shorts:fetch', ['--limit' => 100, '--korean-ratio' => 75]);
            $output = \Artisan::output();
            $after = \App\Models\Short::count();
            $added = $after - $before;

            if (str_contains($output, 'API 할당량 초과') || str_contains($output, '403')) {
                return response()->json([
                    'success' => false,
                    'message' => "YouTube API 할당량 초과! 추가된 쇼츠: {$added}개. 내일 다시 시도하세요.",
                    'added' => $added,
                    'total' => $after,
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => $added > 0 ? "쇼츠 {$added}개 추가 완료 (전체 {$after}개)" : "추가된 쇼츠 없음 (전체 {$after}개)",
                'added' => $added,
                'total' => $after,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    });

    // Admin Music
    Route::post('/music/categories', [MusicController::class, 'storeCategory']);
    Route::put('/music/categories/{id}', [MusicController::class, 'updateCategory']);
    Route::delete('/music/categories/{id}', [MusicController::class, 'destroyCategory']);
    Route::post('/music/bulk-import', [MusicController::class, 'bulkImport']);
    Route::post('/music/tracks', [MusicController::class, 'storeTrack']);
    Route::delete('/music/tracks/{id}', [MusicController::class, 'destroyTrack']);

    // ─── Admin Poker ───
    Route::prefix('poker')->group(function () {
        Route::get('/overview', [PokerController::class, 'adminOverview']);
        Route::get('/wallets', [PokerController::class, 'adminWallets']);
        Route::put('/wallets/{id}', [PokerController::class, 'adminUpdateWallet']);
        Route::get('/settings', [PokerController::class, 'adminSettings']);
        Route::put('/settings', [PokerController::class, 'adminUpdateSettings']);

        // 토너먼트 관리
        Route::post('/tournaments', [PokerTournamentController::class, 'adminCreate']);
        Route::get('/tournaments', [PokerTournamentController::class, 'adminList']);
        Route::delete('/tournaments/{id}', [PokerTournamentController::class, 'adminCancel']);
    });

    // ─── Admin 안심서비스 ───
    Route::get('/elder/overview', [AdminController::class, 'elderOverview']);
    Route::get('/elder/guardians', [AdminController::class, 'elderGuardians']);
    Route::get('/elder/guardians/{id}/detail', [AdminController::class, 'elderGuardianDetail']);
    Route::delete('/elder/guardians/{id}', [AdminController::class, 'elderDeleteGuardian']);
    Route::get('/elder/calls', [AdminController::class, 'elderCallLogs']);
    Route::get('/elder/checkins', [AdminController::class, 'elderCheckins']);
    Route::get('/elder/sos', [AdminController::class, 'elderSosLogs']);

    // ─── Admin 통화 내역 ───
    Route::get('/calls', [AdminController::class, 'callLogs']);
    Route::get('/calls/stats', [AdminController::class, 'callStats']);

    // ─── Admin 채팅 ───
    Route::get('/chat/rooms', [AdminController::class, 'chatRooms']);
    Route::post('/chat/rooms', [AdminController::class, 'chatCreateRoom']);
    Route::delete('/chat/rooms/{id}', [AdminController::class, 'chatDeleteRoom']);
    Route::get('/chat/rooms/{id}', [AdminController::class, 'chatRoomDetail']);
    Route::get('/chat/rooms/{id}/messages', [AdminController::class, 'chatRoomMessages']);
    Route::post('/chat/rooms/{id}/announce', [AdminController::class, 'chatAnnounce']);
    Route::delete('/chat/rooms/{id}/members/{userId}', [AdminController::class, 'chatKickMember']);
    Route::post('/chat/rooms/{id}/ban/{userId}', [AdminController::class, 'chatBanMember']);
    Route::delete('/chat/rooms/{id}/ban/{userId}', [AdminController::class, 'chatUnbanMember']);
    Route::delete('/chat/rooms/{id}/messages/{messageId}', [AdminController::class, 'chatDeleteMessage']);
    Route::post('/chat/rooms/{id}/reports/{reportId}/resolve', [AdminController::class, 'chatResolveReport']);
    Route::post('/chat/users/{userId}/permaban', [AdminController::class, 'chatPermaBan']);
    Route::get('/chat/permaban-list', [AdminController::class, 'chatPermaBanList']);

    // Admin 공동구매
    Route::post('/groupbuys/{id}/approve', [GroupBuyController::class, 'adminApprove']);
    Route::post('/groupbuys/{id}/reject', [GroupBuyController::class, 'adminReject']);
    Route::post('/groupbuys/{id}/complete', [GroupBuyController::class, 'adminComplete']);

    // Admin Shorts
    Route::get('/shorts', [AdminController::class, 'shortsList']);
    Route::delete('/shorts/{id}', [AdminController::class, 'shortsDelete']);
    Route::get('/shorts/stats', [AdminController::class, 'shortsStats']);

    // Admin 콘텐츠 관리 (AdminListView 에서 호출) — /api/admin/{resource}/{id}
    Route::delete('/qa/{id}', [AdminController::class, 'deleteQa']);
    Route::patch('/qa/{id}/toggle', [AdminController::class, 'toggleQa']);
    Route::delete('/news/{id}', [AdminController::class, 'deleteNews']);
    Route::patch('/news/{id}/toggle', [AdminController::class, 'toggleNews']);
    Route::delete('/jobs/{id}', [AdminController::class, 'deleteJob']);
    Route::delete('/realestate/{id}', [AdminController::class, 'deleteRealEstate']);
    Route::delete('/events/{id}', [AdminController::class, 'deleteEvent']);
    Route::delete('/market/{id}', [AdminController::class, 'deleteMarket']);
    Route::delete('/groupbuys/{id}', [AdminController::class, 'deleteGroupBuy']);
    Route::delete('/clubs/{id}', [AdminController::class, 'deleteClub']);

    // ─── Admin Recipes (식품안전나라 API) ───
    Route::get('/recipes/stats', [AdminRecipeController::class, 'stats']);
    Route::get('/recipes/test-connection', [AdminRecipeController::class, 'testConnection']);
    Route::post('/recipes/sync', [AdminRecipeController::class, 'sync']);
    Route::post('/recipes/sync-all', [AdminRecipeController::class, 'syncAll']);
    Route::post('/recipes/clear-all', [AdminRecipeController::class, 'clearAll']);
    Route::delete('/recipes/bulk-delete', [AdminRecipeController::class, 'bulkDelete']);
    Route::get('/recipes', [AdminRecipeController::class, 'index']);
    Route::put('/recipes/{id}', [AdminRecipeController::class, 'update']);
    Route::patch('/recipes/{id}/toggle', [AdminRecipeController::class, 'toggle']);
    Route::delete('/recipes/{id}', [AdminRecipeController::class, 'destroy']);

});
