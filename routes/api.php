<?php

use App\Http\Controllers\API\AuthController;

use App\Http\Controllers\API\PostController;

use App\Http\Controllers\API\CommentController;

use App\Http\Controllers\API\JobController;

use App\Http\Controllers\API\MarketController;
use App\Http\Controllers\API\RealEstateController;

use App\Http\Controllers\API\BusinessController;

use App\Http\Controllers\API\BusinessClaimController;

use App\Http\Controllers\API\OwnerDashboardController;

use App\Http\Controllers\API\AdminBusinessController;

use App\Http\Controllers\API\PointController;

use App\Http\Controllers\API\MessageController;

use App\Http\Controllers\API\AdminController;

use App\Http\Controllers\API\AdminSettingsController;

use App\Http\Controllers\API\BoardController;

use App\Http\Controllers\API\ProfileController;

use App\Http\Controllers\API\SearchController;

use App\Http\Controllers\API\ReportController;

use App\Http\Controllers\API\ChatController;

use App\Http\Controllers\API\ElderController;

use App\Http\Controllers\API\QuizController;

use App\Http\Controllers\API\RideController;

use App\Http\Controllers\API\DriverController;

use App\Http\Controllers\API\MatchController;

use App\Http\Controllers\API\ClubController;

use App\Http\Controllers\API\EventController;

use App\Http\Controllers\API\GameController;

use App\Http\Controllers\API\PokerController;

use App\Http\Controllers\API\NotificationController;

use App\Http\Controllers\API\FriendController;

use App\Http\Controllers\API\NewsController;

use App\Http\Controllers\API\ShortController;

use App\Http\Controllers\API\ShoppingController;

use App\Http\Controllers\API\AIController;

use App\Http\Controllers\API\GroupBuyController;

use App\Http\Controllers\API\MentorController;

use App\Http\Controllers\API\CallController;

use App\Http\Controllers\API\QaController;

use App\Http\Controllers\API\RecipeController;

use App\Http\Controllers\API\MusicController;

use App\Http\Controllers\API\AdminQaController;

use App\Http\Controllers\API\AdminRecipeController;

use App\Http\Controllers\API\GameScoreController;

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\CommunityController;
use App\Http\Controllers\API\QAController as QAV2Controller;



// 공개 인증

Route::prefix('auth')->group(function () {

    Route::post('register', [AuthController::class, 'register']);

    Route::post('login',    [AuthController::class, 'login']);

});



// 공개 사이트 설정 (프론트엔드 메뉴/사이트명 등)

Route::get('settings/public', [AdminSettingsController::class, 'getPublicSettings']);



// 공개 조회

Route::get('boards',                [BoardController::class,   'index']);

Route::get('boards/{board}',        [BoardController::class,   'show']);

Route::get('posts',                 [PostController::class,    'index']);

Route::get('posts/{post}',          [PostController::class,    'show']);

Route::get('jobs',                  [JobController::class,     'index']);

Route::get('jobs/{job}',            [JobController::class,     'show']);

Route::get('market',                [MarketController::class,  'index']);

Route::get('market/{item}',         [MarketController::class,  'show']);
Route::get('realestate',             [RealEstateController::class, 'index']);
Route::get('realestate/{id}',         [RealEstateController::class, 'show']);
Route::get("realestate/{id}/comments", [RealEstateController::class, "getComments"]);

Route::get('businesses',            [BusinessController::class,'index']);

Route::get('businesses/{business}', [BusinessController::class,'show']);

Route::get('profile/{username}',    [ProfileController::class, 'show']);

Route::get('search',                [SearchController::class,  'search']);



// 음악듣기 (공개)

Route::get('music/categories', [MusicController::class, 'categories']);

Route::get('music/categories/{id}/tracks', [MusicController::class, 'tracks']);

Route::post('music/tracks/{id}/play', [MusicController::class, 'playTrack']);


// 숏츠 (공개)

Route::get('shorts',               [ShortController::class,   'index']);

Route::get('shorts/feed',           [ShortController::class,   'feed']);
Route::get('shorts/{id}',              [ShortController::class,   'show']);



// 쇼핑정보 (공개)

Route::get('shopping/nearby-stores', [ShoppingController::class, 'nearbyStores']);
Route::get('shopping/stores',       [ShoppingController::class, 'stores']);
Route::get('shopping/stores/{id}',  [ShoppingController::class, 'showStore']);
Route::get('shopping/deals',        [ShoppingController::class, 'deals']);
Route::get('shopping/deals/{id}',   [ShoppingController::class, 'showDeal']);
Route::get('shopping/categories',   [ShoppingController::class, 'categories']);



// Chat (공개 방 목록)

Route::get('chat/rooms',        [ChatController::class, 'rooms']);
    Route::post('chat/rooms',       [ChatController::class, 'createRoom']);
    Route::patch('chat/rooms/{slug}',       [ChatController::class, 'updateRoom']);
    Route::delete('chat/rooms/{slug}',      [ChatController::class, 'deleteRoom']);

Route::get('chat/rooms/{slug}', [ChatController::class, 'room']);



// News (공개)

Route::get('news',                    [NewsController::class, 'index']);

Route::get('news/yesterday-summary',  [NewsController::class, 'yesterdaySummary']);

Route::get('news/{id}',               [NewsController::class, 'show']);



// AI (공개)

Route::get('ai/search',    [AIController::class, 'search']);

Route::post('ai/translate',[AIController::class, 'translate']);

Route::get('ai/feed',      [AIController::class, 'feed']);



// 공동구매 (공개 조회)

Route::get('groupbuy',      [GroupBuyController::class, 'index']);

Route::get('groupbuy/{id}', [GroupBuyController::class, 'show']);



// 멘토링 (공개 조회)

Route::get('mentors',       [MentorController::class, 'index']);



// Events (공개)

Route::get('events',       [EventController::class, 'index']);

Route::get('events/{id}',  [EventController::class, 'show']);



// 동호회 (공개)

Route::get('clubs',      [ClubController::class, 'index']);

Route::get('clubs/{id}', [ClubController::class, 'show']);
Route::get('clubs/{id}/posts', [ClubController::class, 'posts']);



// 드라이버 위치 (공개 조회)

Route::get('drivers/nearby', [DriverController::class, 'nearbyDrivers']);



// 게임 (공개)

Route::get('games/rooms',      [GameController::class, 'index']);

Route::get('games/leaderboard',[GameController::class, 'leaderboard']);

Route::get('games/shop',       [GameController::class, 'shopItems']);

Route::get('poker/rooms',      [PokerController::class, 'index']);



// 게임 카테고리 (공개)

Route::get('game-categories', [App\Http\Controllers\API\GameCategoryController::class, 'index']);

Route::get('game-leaderboard', [App\Http\Controllers\API\GameCategoryController::class, 'leaderboard']);

Route::get('games/{id}/leaderboard', [GameScoreController::class, 'gameLeaderboard']);



// Q&A (공개)

Route::get('qa/categories',    [QaController::class, 'categories']);

Route::get('qa',               [QaController::class, 'index']);

Route::get('qa/{id}',          [QaController::class, 'show']);

// Recipes (공개)

Route::get('recipes/categories', [RecipeController::class, 'categories']);

Route::get('recipes/popular',    [RecipeController::class, 'popular']);

Route::get('recipes',            [RecipeController::class, 'index']);

Route::get('recipes/{id}',       [RecipeController::class, 'show']);



// 업소 이메일 인증 (공개)

Route::get('claims/email/verify/{token}', [BusinessClaimController::class, 'verifyEmail']);



// 인증 필요

Route::middleware('auth:api')->group(function () {



    // Auth

    Route::post('auth/logout',  [AuthController::class, 'logout']);

    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    Route::get('auth/me',       [AuthController::class, 'me']);
    Route::delete('auth/account',  [AuthController::class, 'deleteAccount']);



    // 지갑

    Route::get('wallet/balance',          [App\Http\Controllers\API\WalletController::class, 'balance']);

    Route::get('wallet/transactions',     [App\Http\Controllers\API\WalletController::class, 'transactions']);

    Route::post('wallet/daily-bonus',     [App\Http\Controllers\API\WalletController::class, 'dailyBonus']);

    Route::post('wallet/convert',         [App\Http\Controllers\API\WalletController::class, 'convert']);



    // Profile

    Route::put('profile',               [ProfileController::class, 'update']);

    Route::post('profile/avatar',       [ProfileController::class, 'uploadAvatar']);

    Route::post('profile/password',     [ProfileController::class, 'changePassword']);

    Route::get('profile/me/posts',      [ProfileController::class, 'myPosts']);

    Route::get('profile/me/comments',   [ProfileController::class, 'myComments']);

    Route::get('bookmarks',             [ProfileController::class, 'bookmarks']);

    Route::post('bookmarks/toggle',     [ProfileController::class, 'toggleBookmark']);



    // Posts

    Route::post('posts',              [PostController::class, 'store']);

    Route::put('posts/{post}',        [PostController::class, 'update']);

    Route::delete('posts/{post}',     [PostController::class, 'destroy']);

    Route::post('posts/{post}/like',  [PostController::class, 'like']);



    // Comments

    Route::post('posts/{post}/comments', [CommentController::class, 'store']);

    Route::put('comments/{comment}',     [CommentController::class, 'update']);

    Route::delete('comments/{comment}',  [CommentController::class, 'destroy']);



    // Jobs

    Route::post('jobs',                [JobController::class, 'store']);

    Route::put('jobs/{job}',           [JobController::class, 'update']);

    Route::delete('jobs/{job}',        [JobController::class, 'destroy']);

    Route::post('jobs/{id}/like',      [JobController::class, 'like']);

    Route::post('jobs/{id}/bookmark',  [JobController::class, 'bookmark']);

    Route::post('jobs/{id}/comments',  [JobController::class, 'comment']);



    // Market

    Route::post('market',                [MarketController::class, 'store']);

    Route::put('market/{item}',          [MarketController::class, 'update']);

    Route::delete('market/{item}',       [MarketController::class, 'destroy']);

    Route::patch('market/{id}/sold',     [MarketController::class, 'sold']);
    Route::post('market/{id}/like',      [MarketController::class, 'like']);

    Route::post('market/{id}/bookmark',  [MarketController::class, 'bookmark']);

    Route::post('market/{id}/comments',  [MarketController::class, 'comment']);

    // Real Estate
    Route::post('realestate',                   [RealEstateController::class, 'store']);
    Route::put('realestate/{id}',               [RealEstateController::class, 'update']);
    Route::delete('realestate/{id}',            [RealEstateController::class, 'destroy']);
    Route::post('realestate/{id}/bookmark',     [RealEstateController::class, 'bookmark']);
    Route::post('realestate/{id}/comments',     [RealEstateController::class, 'comment']);



    // Businesses

    Route::post('businesses',                   [BusinessController::class, 'store']);

    Route::post('businesses/{business}/review', [BusinessController::class, 'review']);

    Route::post('businesses/{id}/stat/{type}',    [BusinessController::class, 'trackStat']);

    Route::post('businesses/{id}/bookmark',       [BusinessController::class, 'toggleBookmark']);



    // Points

    Route::get('points/balance',   [PointController::class, 'balance']);

    Route::get('points/history',   [PointController::class, 'history']);

    Route::post('points/checkin',  [PointController::class, 'checkin']);

    Route::post('points/convert',  [PointController::class, 'convert']);



    // Messages

    Route::get('messages/inbox',      [MessageController::class, 'inbox']);

    Route::get('messages/unread',     [MessageController::class, 'unreadCount']);

    Route::post('messages',           [MessageController::class, 'send']);

    Route::get('messages/{message}',  [MessageController::class, 'show']);



    // Reports

    Route::post('reports', [ReportController::class, 'store']);



    // Chat

    Route::get('chat/rooms/{slug}/messages',  [ChatController::class, 'messages']);

    Route::post('chat/rooms/{slug}/messages', [ChatController::class, 'send']);

    Route::get('chat/rooms/{slug}/search',    [ChatController::class, 'search']);

    Route::post('chat/block/{userId}',        [ChatController::class, 'blockUser']);

    Route::delete('chat/block/{userId}',      [ChatController::class, 'unblockUser']);

    Route::post('chat/report/{messageId}',    [ChatController::class, 'reportMessage']);



    // Elder 안심 서비스

    Route::get('elder/settings',          [ElderController::class, 'settings']);
    Route::put('elder/settings',          [ElderController::class, 'updateSettings']);
    Route::post('elder/checkin',          [ElderController::class, 'checkin']);
    Route::post('elder/sos',              [ElderController::class, 'sos']);
    Route::get('elder/checkin-history',   [ElderController::class, 'checkinHistory']);
    Route::get('elder/sos-history',       [ElderController::class, 'sosHistory']);
    Route::post('elder/sos/{id}/resolve', [ElderController::class, 'resolveSos']);
    Route::get('elder/guardian/{userId}', [ElderController::class, 'guardianView']);
    Route::get('elder/guardian-search',   [ElderController::class, 'guardianSearch']);
    Route::post('elder/link-guardian',    [ElderController::class, 'linkGuardian']);



    // Quiz

    Route::get('quiz/today',       [QuizController::class, 'today']);

    Route::post('quiz/answer',     [QuizController::class, 'answer']);

    Route::get('quiz/leaderboard', [QuizController::class, 'leaderboard']);



    // Ride (알바 라이드)

    Route::post('ride/request',        [RideController::class, 'request']);

    Route::get('ride/history',         [RideController::class, 'history']);

    Route::get('ride/nearby',          [RideController::class, 'nearbyRequests']);

    Route::get('ride/{id}',            [RideController::class, 'show']);

    Route::post('ride/{id}/cancel',    [RideController::class, 'cancel']);

    Route::post('ride/{id}/accept',    [RideController::class, 'accept']);

    Route::post('ride/{id}/start',     [RideController::class, 'start']);

    Route::post('ride/{id}/complete',  [RideController::class, 'complete']);

    Route::post('ride/{id}/review',    [RideController::class, 'review']);



    // Driver

    Route::get('driver/profile',    [DriverController::class, 'profile']);

    Route::post('driver/register',  [DriverController::class, 'register']);

    Route::post('driver/online',    [DriverController::class, 'toggleOnline']);

    Route::post('driver/location',  [DriverController::class, 'updateLocation']);

    Route::get('driver/earnings',   [DriverController::class, 'earnings']);



    // Match (나이별 매칭)

    Route::get('match/profile',         [MatchController::class, 'myProfile']);

    Route::post('match/profile',        [MatchController::class, 'saveProfile']);

    Route::get('match/browse',          [MatchController::class, 'browse']);

    Route::post('match/like/{userId}',  [MatchController::class, 'like']);

    Route::get('match/likes',           [MatchController::class, 'likes']);

    Route::get('match/matches',         [MatchController::class, 'matches']);

    Route::post('match/photos',         [MatchController::class, 'uploadPhoto']);



    // 동호회 (인증 필요)

    Route::post('clubs',                          [ClubController::class, 'store']);

    Route::put('clubs/{id}',                      [ClubController::class, 'update']);

    Route::delete('clubs/{id}',                   [ClubController::class, 'destroy']);

    Route::post('clubs/{id}/join',                [ClubController::class, 'join']);

    Route::post('clubs/{id}/leave',               [ClubController::class, 'leave']);

    Route::post('clubs/{id}/posts',               [ClubController::class, 'createPost']);

    Route::get('clubs/my',                        [ClubController::class, 'myClubs']);

    Route::get('clubs/{id}/members',                   [ClubController::class, 'getMembers']);
    Route::get('clubs/{id}/members/pending',            [ClubController::class, 'getPendingMembers']);
    Route::delete('clubs/{id}/members/{userId}',        [ClubController::class, 'kickMember']);
    Route::post('clubs/{id}/members/{userId}/approve',  [ClubController::class, 'approveMember']);
    Route::post('clubs/{id}/members/{userId}/reject',   [ClubController::class, 'rejectMember']);
    Route::post('clubs/{id}/transfer/{userId}',         [ClubController::class, 'transferOwner']);



    // Events

    Route::post('events/{id}/attend',   [EventController::class, 'attend']);

    Route::post('events/{id}/like',     [EventController::class, 'like']);

    Route::post('events/{id}/bookmark', [EventController::class, 'bookmark']);

    Route::post('events/{id}/comments', [EventController::class, 'comment']);

    Route::post('events',               [EventController::class, 'store']);

    Route::put('events/{id}',           [EventController::class, 'update']);

    Route::delete('events/{id}',        [EventController::class, 'destroy']);



    // News (인증 필요 액션)

    Route::post('news/{id}/like',     [NewsController::class, 'like']);

    Route::post('news/{id}/comments', [NewsController::class, 'comment']);



    // 게임 (인증 필요)

    Route::post('games/rooms',                    [GameController::class, 'create']);

    Route::post('games/rooms/{id}/join',          [GameController::class, 'join']);

    Route::post('games/rooms/{id}/ready',         [GameController::class, 'ready']);

    Route::get('games/rooms/{id}/state',          [GameController::class, 'state']);

    Route::post('games/rooms/{id}/play',          [GameController::class, 'play']);

    Route::post('games/rooms/{id}/go',            [GameController::class, 'go']);

    Route::post('games/rooms/{id}/stop',          [GameController::class, 'stop']);

        // 교육 게임 점수

        Route::post('games/{id}/score',              [GameScoreController::class, 'saveScore']);

        Route::get('games/my-scores',                [GameScoreController::class, 'myScores']);

    Route::post('games/shop/redeem',              [GameController::class, 'redeem']);



    // 포커 (인증 필요)

    Route::post('poker/rooms',                    [PokerController::class, 'create']);

    Route::post('poker/rooms/{id}/join',          [PokerController::class, 'join']);

    Route::post('poker/rooms/{id}/ready',         [PokerController::class, 'ready']);

    Route::get('poker/rooms/{id}/state',          [PokerController::class, 'state']);

    Route::post('poker/rooms/{id}/action',        [PokerController::class, 'action']);



    // 친구

    Route::get('friends',                  [FriendController::class, 'myFriends']);

    Route::get('friends/pending',          [FriendController::class, 'pendingRequests']);

    Route::get('friends/search',           [FriendController::class, 'search']);

    Route::post('friends/request/{userId}',[FriendController::class, 'sendRequest']);

    Route::post('friends/accept/{userId}', [FriendController::class, 'acceptRequest']);

    Route::post('friends/reject/{userId}', [FriendController::class, 'rejectRequest']);

    Route::delete('friends/{userId}',      [FriendController::class, 'removeFriend']);



    // 알림

    Route::get('notifications',                   [NotificationController::class, 'index']);

    Route::get('notifications/unread',            [NotificationController::class, 'unreadCount']);

    Route::post('notifications/{id}/read',        [NotificationController::class, 'markRead']);

    Route::post('notifications/read-all',         [NotificationController::class, 'markAllRead']);



    // 음악 플레이리스트 (인증 필요)
    Route::get('music/playlists', [MusicController::class, 'myPlaylists']);
    Route::post('music/playlists', [MusicController::class, 'createPlaylist']);
    Route::get('music/playlists/{id}', [MusicController::class, 'getPlaylist']);
    Route::put('music/playlists/{id}', [MusicController::class, 'updatePlaylist']);
    Route::delete('music/playlists/{id}', [MusicController::class, 'deletePlaylist']);
    Route::post('music/playlists/{id}/tracks', [MusicController::class, 'addTrackToPlaylist']);
    Route::delete('music/playlists/{playlistId}/tracks/{trackId}', [MusicController::class, 'removeTrackFromPlaylist']);

    // 숏츠 (인증 필요 작업)

    Route::post('shorts',               [ShortController::class, 'store']);

    Route::post('shorts/{id}/like',     [ShortController::class, 'like']);

    Route::post('shorts/{id}/view',     [ShortController::class, 'view']);

    Route::get('shorts/my',             [ShortController::class, 'myShorts']);

    Route::delete('shorts/{id}',        [ShortController::class, 'destroy']);

    Route::post('shorts/interests',     [ShortController::class, 'saveInterests']);

    Route::get('shorts/interests',      [ShortController::class, 'getInterests']);



    // WebRTC 통화 시그널링

    Route::post('call/signal', [CallController::class, 'signal']);



    // 공동구매 (인증 필요)

    Route::post('groupbuy',           [GroupBuyController::class, 'store']);

    Route::post('groupbuy/{id}/join', [GroupBuyController::class, 'join']);

    Route::post('groupbuy/{id}/leave',[GroupBuyController::class, 'leave']);

    Route::delete('groupbuy/{id}',    [GroupBuyController::class, 'destroy']);



    // 멘토링 (인증 필요)

    Route::get('mentors/my',                     [MentorController::class, 'myProfile']);

    Route::post('mentors/profile',               [MentorController::class, 'saveProfile']);

    Route::post('mentors/{id}/request',          [MentorController::class, 'request']);

    Route::get('mentors/requests',               [MentorController::class, 'myRequests']);

    Route::post('mentors/requests/{id}/respond', [MentorController::class, 'respond']);

    Route::post('mentors/toggle-available',      [MentorController::class, 'toggleAvailable']);



    // Q&A (인증 필요)

    Route::post('qa',                       [QaController::class, 'store']);

    Route::post('qa/{id}/answers',          [QaController::class, 'answer']);

    Route::post('qa/{id}/best-answer',      [QaController::class, 'setBestAnswer']);

    Route::post('qa/answers/{id}/like',     [QaController::class, 'likeAnswer']);

    // Recipes (인증 필요)

    Route::post('recipes/image',            [RecipeController::class, 'uploadImage']);

    Route::post('recipes',                  [RecipeController::class, 'store']);

    Route::get('recipes/my',                [RecipeController::class, 'myRecipes']);

    Route::put('recipes/{id}',              [RecipeController::class, 'update']);

    Route::delete('recipes/{id}',           [RecipeController::class, 'destroy']);

    Route::post('recipes/{id}/like',        [RecipeController::class, 'like']);

    Route::post('recipes/{id}/bookmark',    [RecipeController::class, 'bookmark']);

    Route::post('recipes/{id}/comments',    [RecipeController::class, 'comment']);



    // Business Claims

    Route::post('businesses/{id}/claim', [BusinessClaimController::class, 'initiate']);

    Route::post('claims/{id}/email', [BusinessClaimController::class, 'sendEmailVerification']);

    Route::post('claims/{id}/documents', [BusinessClaimController::class, 'uploadDocuments']);

    Route::get('claims/{id}/status', [BusinessClaimController::class, 'status']);

    Route::get('my-claims', [BusinessClaimController::class, 'myClaims']);

    // Owner Dashboard

    Route::get('owner/business', [OwnerDashboardController::class, 'myBusiness']);

    Route::put('owner/business', [OwnerDashboardController::class, 'update']);

    Route::post('owner/business/photos', [OwnerDashboardController::class, 'uploadPhotos']);

    Route::put('owner/business/photos/reorder', [OwnerDashboardController::class, 'reorderPhotos']);

    Route::delete('owner/business/photos/{index}', [OwnerDashboardController::class, 'deletePhoto']);

    Route::post('owner/business/menu-item', [OwnerDashboardController::class, 'upsertMenuItem']);

    Route::delete('owner/business/menu-item/{itemId}', [OwnerDashboardController::class, 'deleteMenuItem']);

    Route::get('owner/reviews', [OwnerDashboardController::class, 'myReviews']);

    Route::post('owner/reviews/{id}/reply', [OwnerDashboardController::class, 'replyReview']);

    Route::post('owner/reviews/{id}/report', [OwnerDashboardController::class, 'reportReview']);

    Route::get('owner/events', [OwnerDashboardController::class, 'myEvents']);

    Route::post('owner/events', [OwnerDashboardController::class, 'createEvent']);

    Route::delete('owner/events/{id}', [OwnerDashboardController::class, 'deleteEvent']);

    Route::get('owner/stats', [OwnerDashboardController::class, 'stats']);



    // Admin

    Route::middleware('admin')->prefix('admin')->group(function () {

        // 게임 관리

        Route::get('games',                          [GameScoreController::class, 'adminList']);

        Route::put('games/{id}/toggle',              [GameScoreController::class, 'toggleActive']);

        Route::get('games/{id}/questions',           [GameScoreController::class, 'getQuestions']);

        Route::post('games/{id}/questions',          [GameScoreController::class, 'addQuestion']);

        Route::delete('questions/{id}',              [GameScoreController::class, 'deleteQuestion']);



        // 개요

        Route::get('stats',                          [AdminController::class, 'stats']);

        Route::get('activity',                       [AdminController::class, 'activity']);



        // 회원 관리

        Route::get('users',                          [AdminController::class, 'users']);

        Route::get('users/{user}',                   [AdminController::class, 'userDetail']);

        Route::post('users/{user}/ban',              [AdminController::class, 'banUser']);

        Route::post('users/{user}/unban',            [AdminController::class, 'unbanUser']);

        Route::delete('users/{user}',                [AdminController::class, 'deleteUser']);



        // 신고 / 콘텐츠

        Route::get('reports',                        [AdminController::class, 'reports']);

        Route::post('reports/{report}/dismiss',      [AdminController::class, 'dismissReport']);

        Route::get('posts',                          [AdminController::class, 'posts']);

        Route::delete('posts/{id}',                  [AdminController::class, 'deletePost']);

        Route::get('jobs',                           [AdminController::class, 'jobs']);

        Route::delete('jobs/{id}',                   [AdminController::class, 'deleteJob']);

        Route::get('market',                         [AdminController::class, 'market']);

        Route::delete('market/{id}',                 [AdminController::class, 'deleteMarket']);
        Route::get('realestate',                     [RealEstateController::class, 'adminIndex']);
        Route::delete('realestate/{id}',             [RealEstateController::class, 'adminDestroy']);
        Route::patch('realestate/{id}/toggle',       [RealEstateController::class, 'adminToggle']);



        // 업소록

        Route::get('businesses',                     [AdminController::class, 'businesses']);

        Route::post('businesses/{id}/approve',       [AdminController::class, 'approveBusiness']);

        Route::put('businesses/{id}',                [AdminController::class, 'updateBusiness']);

        Route::post('businesses/{id}/reject',        [AdminController::class, 'rejectBusiness']);

        Route::delete('businesses/{id}',             [AdminController::class, 'deleteBusiness']);



        // 라이드

        Route::get('rides',                          [AdminController::class, 'rides']);

        Route::post('rides/{id}/approve',            [AdminController::class, 'approveRide']);

        Route::post('rides/{id}/reject',             [AdminController::class, 'rejectRide']);

        Route::post('rides/{id}/complete',           [AdminController::class, 'completeRide']);

        Route::post('rides/{id}/cancel',             [AdminController::class, 'rejectRide']);

        Route::get('rides/payouts',                  [AdminController::class, 'ridePayouts']);

        Route::post('rides/payouts/{driverId}',      [AdminController::class, 'payoutDriver']);



        // 공동구매 / 멘토링

        Route::get('groupbuys',                      [AdminController::class, 'groupbuys']);

        Route::post('groupbuys',                     [AdminController::class, 'createGroupBuy']);

        Route::put('groupbuys/{id}',                 [AdminController::class, 'updateGroupBuy']);

        Route::post('groupbuys/{id}/approve',        [AdminController::class, 'approveGroupBuy']);

        Route::delete('groupbuys/{id}',              [AdminController::class, 'deleteGroupBuy']);

        Route::get('mentors',                        [AdminController::class, 'mentors']);

        Route::post('mentors/{id}/toggle',           [AdminController::class, 'toggleMentor']);

        Route::get('mentor/requests',                [AdminController::class, 'mentorRequests']);

        Route::patch('mentor/requests/{id}/approve', [AdminController::class, 'approveMentorRequest']);

        Route::patch('mentor/requests/{id}/reject',  [AdminController::class, 'rejectMentorRequest']);

        Route::post('mentor/settings',               [AdminController::class, 'saveMentorSettings']);



        // 채팅

        Route::get('chats',                          [AdminController::class, 'chatRooms']);

        Route::get('chats/{roomId}/messages',        [AdminController::class, 'chatMessages']);

        Route::post('chats',                         [AdminController::class, 'createChatRoom']);

        Route::put('chats/{id}',                     [AdminController::class, 'updateChatRoom']);

        Route::delete('chats/{id}',                  [AdminController::class, 'deleteChatRoom']);

        Route::delete('chat-messages/{id}',          [AdminController::class, 'deleteChatMessage']);



        // Admin Chat Management

        Route::get('chat/rooms',                     [AdminController::class, 'adminChatRooms']);

        Route::get('chat/rooms/{id}/messages',       [AdminController::class, 'adminChatMessages']);

        Route::post('chat/rooms',                    [AdminController::class, 'adminCreateChatRoom']);

        Route::patch('chat/rooms/{id}/toggle',       [AdminController::class, 'adminToggleChatRoom']);

        Route::delete('chat/rooms/{id}',             [AdminController::class, 'adminDeleteChatRoom']);

        Route::delete('chat/messages/{id}',          [AdminController::class, 'adminDeleteChatMessage']);



        // Admin Shopping Management
        Route::get('shopping/stores',                    [ShoppingController::class, 'adminStores']);
        Route::post('shopping/stores',                   [ShoppingController::class, 'adminStoreStore']);
        Route::put('shopping/stores/{id}',               [ShoppingController::class, 'adminUpdateStore']);
        Route::delete('shopping/stores/{id}',            [ShoppingController::class, 'adminDeleteStore']);
        Route::get('shopping/stores/{id}/locations',     [ShoppingController::class, 'adminLocations']);
        Route::post('shopping/stores/{id}/locations',    [ShoppingController::class, 'adminStoreLocation']);
        Route::put('shopping/locations/{id}',            [ShoppingController::class, 'adminUpdateLocation']);
        Route::delete('shopping/locations/{id}',         [ShoppingController::class, 'adminDeleteLocation']);
        Route::get('shopping/deals',                     [ShoppingController::class, 'adminDeals']);
        Route::post('shopping/deals',                    [ShoppingController::class, 'adminStoreDeal']);
        Route::put('shopping/deals/{id}',                [ShoppingController::class, 'adminUpdateDeal']);
        Route::delete('shopping/deals/{id}',             [ShoppingController::class, 'adminDeleteDeal']);
        Route::delete('shopping/deals-bulk-errors',      [ShoppingController::class, 'adminBulkDeleteErrors']);
        Route::get('shopping/flyers',                    [ShoppingController::class, 'adminFlyers']);
        Route::post('shopping/flyers',                   [ShoppingController::class, 'adminStoreFlyer']);
        Route::delete('shopping/flyers/{id}',            [ShoppingController::class, 'adminDeleteFlyer']);
        Route::get('shopping/scrape-logs',               [ShoppingController::class, 'adminScrapeLogs']);



        // 노인 안심

        Route::get('elder/service-settings',         [ElderController::class, 'adminSettings']);
        Route::post('elder/service-settings',        [ElderController::class, 'adminSaveSettings']);
        Route::get('elder',                          [ElderController::class, 'adminMonitor']);
        Route::get('elder/{id}',                     [ElderController::class, 'adminDetail']);
        Route::post('elder/{id}/reset-checkin',      [ElderController::class, 'adminResetCheckin']);
        Route::post('elder/{id}/send-alert',         [ElderController::class, 'adminSendAlert']);



        // ── 사이트 설정 ──────────────────────────────────────────────

        Route::get('settings/all',                   [AdminSettingsController::class, 'getAll']);

        Route::post('settings/company',              [AdminSettingsController::class, 'saveCompany']);

        Route::post('settings/site',                 [AdminSettingsController::class, 'saveSite']);

        Route::post('settings/footer',               [AdminSettingsController::class, 'saveFooter']);

        Route::post('settings/terms/{type}',         [AdminSettingsController::class, 'saveTerms']);

        Route::post('settings/payment-gateway',      [AdminSettingsController::class, 'savePaymentGateway']);

        Route::post('settings/notifications',        [AdminSettingsController::class, 'saveNotifications']);

        Route::get('settings/menus',                 [AdminSettingsController::class, 'getMenus']);

        Route::post('settings/menus/batch',          [AdminSettingsController::class, 'saveMenusBatch']);

        Route::post('settings/menus/{key}',          [AdminSettingsController::class, 'saveMenusBatch']);

        Route::post('settings/boards/{key}',         [AdminSettingsController::class, 'saveBoard']);



        // ── 결제 관리 ─────────────────────────────────────────────────

        Route::get('payments',                       [AdminSettingsController::class, 'getPayments']);

        Route::post('payments/{id}/refund',          [AdminSettingsController::class, 'refundPayment']);



        // ── 배너 관리 ─────────────────────────────────────────────────

        Route::get('banners',                        [AdminSettingsController::class, 'getBanners']);

        Route::post('banners',                       [AdminSettingsController::class, 'createBanner']);

        Route::put('banners/{id}',                   [AdminSettingsController::class, 'updateBanner']);

        Route::delete('banners/{id}',                [AdminSettingsController::class, 'deleteBanner']);



        // ── 회원 고급 관리 ────────────────────────────────────────────

        Route::get('members',                        [AdminController::class, 'users']);

        Route::post('users/{id}/adjust-points',      [AdminSettingsController::class, 'adjustPoints']);

        Route::post('users/bulk-action',             [AdminSettingsController::class, 'bulkAction']);



        // ── 동호회 / 이벤트 / 숏츠 ────────────────────────────────────

        Route::get('clubs',                          [AdminController::class, 'getClubs']);

        Route::delete('clubs/{id}',                  [AdminController::class, 'deleteClub']);

        Route::get('events',                         [AdminController::class, 'getEvents']);

        Route::delete('events/{id}',                 [AdminController::class, 'deleteEvent']);

        Route::get('shorts',                         [AdminController::class, 'getShorts']);

        Route::patch('shorts/{id}/blind',             [AdminController::class, 'blindShort']);

        Route::delete('shorts/{id}',                 [AdminController::class, 'deleteShort']);



        // ── 드라이버 관리 ─────────────────────────────────────────────

        Route::get('drivers',                        [AdminController::class, 'getDrivers']);

        Route::post('drivers/{id}/approve',          [AdminController::class, 'approveDriver']);

        Route::post('drivers/{id}/reject',           [AdminController::class, 'rejectDriver']);



        // ── 업체/회사 관리 ────────────────────────────────────────────

        Route::get('companies',                      [AdminController::class, 'getCompanies']);

        Route::post('companies/{id}/approve',        [AdminController::class, 'approveCompany']);

        Route::post('companies/{id}/reject',         [AdminController::class, 'rejectCompany']);



        // ── 채팅 킥 / 신고 처리 ───────────────────────────────────────

        Route::post('chats/{id}/kick/{userId}',      [AdminController::class, 'kickFromChat']);

        Route::post('reports/{id}/resolve',          [AdminController::class, 'resolveReport']);



        // Q&A 관리

        Route::get('qa/stats',                  [AdminQaController::class, 'stats']);

        Route::get('qa/categories',             [AdminQaController::class, 'categories']);

        Route::post('qa/categories',            [AdminQaController::class, 'storeCategory']);

        Route::put('qa/categories/{id}',        [AdminQaController::class, 'updateCategory']);

        Route::delete('qa/categories/{id}',     [AdminQaController::class, 'destroyCategory']);

        Route::get('qa/posts',                  [AdminQaController::class, 'posts']);

        Route::put('qa/posts/{id}',             [AdminQaController::class, 'updatePost']);

        Route::delete('qa/posts/{id}',          [AdminQaController::class, 'destroyPost']);

        Route::get('qa/posts/{id}/answers',     [AdminQaController::class, 'answers']);

        Route::post('qa/answers/{id}/best',     [AdminQaController::class, 'setBest']);

        Route::delete('qa/answers/{id}',        [AdminQaController::class, 'destroyAnswer']);

        // 레시피 관리

        Route::get('recipes/stats',             [AdminRecipeController::class, 'stats']);

        Route::get('recipes/categories',        [AdminRecipeController::class, 'categories']);

        Route::post('recipes/categories',       [AdminRecipeController::class, 'storeCategory']);

        Route::put('recipes/categories/{id}',   [AdminRecipeController::class, 'updateCategory']);

        Route::delete('recipes/categories/{id}',[AdminRecipeController::class, 'destroyCategory']);

        Route::get('recipes/list',              [AdminRecipeController::class, 'recipes']);

        Route::get('recipes/comments',          [AdminRecipeController::class, 'comments']);

        Route::delete('recipes/comments/{id}',  [AdminRecipeController::class, 'destroyComment']);

        Route::get('recipes/{id}',              [AdminRecipeController::class, 'show']);

        Route::put('recipes/{id}',              [AdminRecipeController::class, 'update']);

        Route::delete('recipes/{id}',           [AdminRecipeController::class, 'destroy']);


        // 음악 관리 (어드민)
        Route::get('music/categories', [MusicController::class, 'adminCategories']);
        Route::post('music/categories', [MusicController::class, 'adminCreateCategory']);
        Route::put('music/categories/{id}', [MusicController::class, 'adminUpdateCategory']);
        Route::delete('music/categories/{id}', [MusicController::class, 'adminDeleteCategory']);
        Route::post('music/tracks', [MusicController::class, 'adminCreateTrack']);
        Route::put('music/tracks/{id}', [MusicController::class, 'adminUpdateTrack']);
        Route::delete('music/tracks/{id}', [MusicController::class, 'adminDeleteTrack']);

        // 지갑 관리 (어드민)

        // 뉴스 관리
        Route::get('news/stats',            [AdminController::class, 'newsStats']);
        Route::get('news',                  [AdminController::class, 'newsList']);
        Route::delete('news/{id}',          [AdminController::class, 'deleteNews']);
        // 매칭 관리
        Route::get('matching/stats',        [AdminController::class, 'matchingStats']);
        Route::get('matching/profiles',     [AdminController::class, 'matchingProfiles']);
        Route::delete('matching/{id}',      [AdminController::class, 'deleteMatchProfile']);
        // 친구 관리
        Route::get('friends/stats',         [AdminController::class, 'friendsStats']);
        Route::get('wallets', [AdminSettingsController::class, 'getWallets']);

        Route::get('wallet-transactions', [AdminSettingsController::class, 'getWalletTransactions']);

        Route::get('businesses-list', [AdminBusinessController::class, 'index']);

        Route::get('businesses-list/{id}', [AdminBusinessController::class, 'show']);

        Route::put('businesses-list/{id}', [AdminBusinessController::class, 'update']);

        Route::delete('businesses-list/{id}', [AdminBusinessController::class, 'destroy']);

        Route::get('business-claims-list', [AdminBusinessController::class, 'claims']);

        Route::post('business-claims-list/{id}/approve', [AdminBusinessController::class, 'approveClaim']);

        Route::post('business-claims-list/{id}/reject', [AdminBusinessController::class, 'rejectClaim']);

        Route::get('business-reviews-list', [AdminBusinessController::class, 'reviews']);

        Route::post('business-reviews-list/{id}/hide', [AdminBusinessController::class, 'hideReview']);

        Route::post('business-reviews-list/{id}/restore', [AdminBusinessController::class, 'restoreReview']);

        Route::delete('business-reviews-list/{id}', [AdminBusinessController::class, 'deleteReview']);

        Route::post('businesses/import', [AdminBusinessController::class, 'bulkImport']);

        Route::get('businesses/crawl-status', [AdminBusinessController::class, 'crawlStatus']);

    });

});


// ============================================================
// Community v2 (새 커뮤니티 게시판)
// ============================================================
Route::prefix('community-v2')->group(function () {
    Route::get('/categories', [CommunityController::class, 'categories']);
    Route::get('/{slug}/posts', [CommunityController::class, 'posts']);
    Route::get('/{slug}/posts/{id}', [CommunityController::class, 'show']);
    Route::post('/posts', [CommunityController::class, 'store'])->middleware('auth:api');
    Route::post('/{slug}/posts', [CommunityController::class, 'store'])->middleware('auth:api');
    Route::put('/posts/{id}', [CommunityController::class, 'update'])->middleware('auth:api');
    Route::put('/{slug}/posts/{id}', [CommunityController::class, 'update'])->middleware('auth:api');
    Route::delete('/posts/{id}', [CommunityController::class, 'destroy'])->middleware('auth:api');
    Route::delete('/{slug}/posts/{id}', [CommunityController::class, 'destroy'])->middleware('auth:api');
    Route::post('/{slug}/posts/{id}/like', [CommunityController::class, 'like'])->middleware('auth:api');
    Route::get('/{slug}/posts/{id}/comments', [CommunityController::class, 'comments']);
    Route::post('/{slug}/posts/{id}/comments', [CommunityController::class, 'storeComment'])->middleware('auth:api');
    Route::delete('/comments/{id}', [CommunityController::class, 'destroyComment'])->middleware('auth:api');
});

// ============================================================
// Q&A v2 (새 Q&A 게시판)
// ============================================================
Route::prefix('qa-v2')->group(function () {
    Route::get('/categories', [QAV2Controller::class, 'categories']);
    Route::get('/questions', [QAV2Controller::class, 'allQuestions']);
    Route::get('/{slug}/questions', [QAV2Controller::class, 'questions']);
    Route::get('/questions/{id}', [QAV2Controller::class, 'show']);
    Route::get('/questions/{id}/answers', [QAV2Controller::class, 'answers']);
    Route::get('/leaderboard', [QAV2Controller::class, 'leaderboard']);
    Route::middleware('auth:api')->group(function () {
        Route::post('/questions', [QAV2Controller::class, 'store']);
        Route::put('/questions/{id}', [QAV2Controller::class, 'update']);
        Route::delete('/questions/{id}', [QAV2Controller::class, 'destroy']);
        Route::post('/questions/{id}/recommend', [QAV2Controller::class, 'recommend']);
        Route::post('/questions/{id}/answers', [QAV2Controller::class, 'storeAnswer']);
        Route::post('/answers/{id}/accept', [QAV2Controller::class, 'acceptAnswer']);
        Route::post('/answers/{id}/like', [QAV2Controller::class, 'likeAnswer']);
    });
});

// 유저 해결 내역
Route::get('/user/{userId}/resolved-answers', [QAV2Controller::class, 'userResolved']);
