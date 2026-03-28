<?php
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\PostController;
use App\Http\Controllers\API\CommentController;
use App\Http\Controllers\API\JobController;
use App\Http\Controllers\API\MarketController;
use App\Http\Controllers\API\BusinessController;
use App\Http\Controllers\API\PointController;
use App\Http\Controllers\API\MessageController;
use App\Http\Controllers\API\AdminController;
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
use Illuminate\Support\Facades\Route;

// 공개 인증
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
});

// 공개 조회
Route::get('boards',                [BoardController::class,   'index']);
Route::get('boards/{board}',        [BoardController::class,   'show']);
Route::get('posts',                 [PostController::class,    'index']);
Route::get('posts/{post}',          [PostController::class,    'show']);
Route::get('jobs',                  [JobController::class,     'index']);
Route::get('jobs/{job}',            [JobController::class,     'show']);
Route::get('market',                [MarketController::class,  'index']);
Route::get('market/{item}',         [MarketController::class,  'show']);
Route::get('businesses',            [BusinessController::class,'index']);
Route::get('businesses/{business}', [BusinessController::class,'show']);
Route::get('profile/{username}',    [ProfileController::class, 'show']);
Route::get('search',                [SearchController::class,  'search']);

// 숏츠 (공개)
Route::get('shorts/feed',           [ShortController::class,   'feed']);

// 쇼핑정보 (공개)
Route::get('shopping/stores',       [ShoppingController::class, 'stores']);
Route::get('shopping/deals',        [ShoppingController::class, 'deals']);
Route::get('shopping/deals/{id}',   [ShoppingController::class, 'showDeal']);
Route::get('shopping/categories',   [ShoppingController::class, 'categories']);

// Chat (공개 방 목록)
Route::get('chat/rooms',        [ChatController::class, 'rooms']);
Route::get('chat/rooms/{slug}', [ChatController::class, 'room']);

// News (공개)
Route::get('news',         [NewsController::class, 'index']);
Route::get('news/{id}',    [NewsController::class, 'show']);

// Events (공개)
Route::get('events',       [EventController::class, 'index']);
Route::get('events/{id}',  [EventController::class, 'show']);

// 동호회 (공개)
Route::get('clubs',      [ClubController::class, 'index']);
Route::get('clubs/{id}', [ClubController::class, 'show']);

// 드라이버 위치 (공개 조회)
Route::get('drivers/nearby', [DriverController::class, 'nearbyDrivers']);

// 게임 (공개)
Route::get('games/rooms',      [GameController::class, 'index']);
Route::get('games/leaderboard',[GameController::class, 'leaderboard']);
Route::get('games/shop',       [GameController::class, 'shopItems']);
Route::get('poker/rooms',      [PokerController::class, 'index']);

// 인증 필요
Route::middleware('auth:api')->group(function () {

    // Auth
    Route::post('auth/logout',  [AuthController::class, 'logout']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::get('auth/me',       [AuthController::class, 'me']);

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
    Route::delete('comments/{comment}',  [CommentController::class, 'destroy']);

    // Jobs
    Route::post('jobs',          [JobController::class, 'store']);
    Route::delete('jobs/{job}',  [JobController::class, 'destroy']);

    // Market
    Route::post('market',          [MarketController::class, 'store']);
    Route::put('market/{item}',    [MarketController::class, 'update']);
    Route::delete('market/{item}', [MarketController::class, 'destroy']);

    // Businesses
    Route::post('businesses',                   [BusinessController::class, 'store']);
    Route::post('businesses/{business}/review', [BusinessController::class, 'review']);

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

    // Elder 안심 서비스
    Route::get('elder/settings',          [ElderController::class, 'settings']);
    Route::put('elder/settings',          [ElderController::class, 'updateSettings']);
    Route::post('elder/checkin',          [ElderController::class, 'checkin']);
    Route::post('elder/sos',              [ElderController::class, 'sos']);
    Route::get('elder/guardian/{userId}', [ElderController::class, 'guardianView']);

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
    Route::post('clubs',              [ClubController::class, 'store']);
    Route::post('clubs/{id}/join',    [ClubController::class, 'join']);
    Route::post('clubs/{id}/leave',   [ClubController::class, 'leave']);
    Route::get('clubs/my',            [ClubController::class, 'myClubs']);

    // Events
    Route::post('events/{id}/attend', [EventController::class, 'attend']);
    Route::post('events',       [EventController::class, 'store']);
    Route::put('events/{id}',   [EventController::class, 'update']);
    Route::delete('events/{id}',[EventController::class, 'destroy']);

    // 게임 (인증 필요)
    Route::post('games/rooms',                    [GameController::class, 'create']);
    Route::post('games/rooms/{id}/join',          [GameController::class, 'join']);
    Route::post('games/rooms/{id}/ready',         [GameController::class, 'ready']);
    Route::get('games/rooms/{id}/state',          [GameController::class, 'state']);
    Route::post('games/rooms/{id}/play',          [GameController::class, 'play']);
    Route::post('games/rooms/{id}/go',            [GameController::class, 'go']);
    Route::post('games/rooms/{id}/stop',          [GameController::class, 'stop']);
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

    // 숏츠 (인증 필요 작업)
    Route::post('shorts',               [ShortController::class, 'store']);
    Route::post('shorts/{id}/like',     [ShortController::class, 'like']);
    Route::post('shorts/{id}/view',     [ShortController::class, 'view']);
    Route::get('shorts/my',             [ShortController::class, 'myShorts']);
    Route::delete('shorts/{id}',        [ShortController::class, 'destroy']);
    Route::post('shorts/interests',     [ShortController::class, 'saveInterests']);
    Route::get('shorts/interests',      [ShortController::class, 'getInterests']);

    // Admin
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('stats',                     [AdminController::class, 'stats']);
        Route::get('users',                     [AdminController::class, 'users']);
        Route::post('users/{user}/ban',         [AdminController::class, 'banUser']);
        Route::post('users/{user}/unban',       [AdminController::class, 'unbanUser']);
        Route::get('reports',                   [AdminController::class, 'reports']);
        Route::post('reports/{report}/dismiss', [AdminController::class, 'dismissReport']);
    });
});
