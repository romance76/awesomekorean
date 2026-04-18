import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

// Glob-based lazy loading (이전 실패 원인: template literal import 사용 → 하위 디렉토리 누락)
const pages = import.meta.glob('../pages/**/*.vue')

const p = (path) => {
  const key = `../pages/${path}.vue`
  if (!pages[key]) {
    console.warn(`[Router] Page not found: ${key}`)
    return () => import('../pages/NotFound.vue')
  }
  return pages[key]
}

const routes = [
  { path: '/', name: 'home', component: p('Home') },
  { path: '/login', name: 'login', component: p('auth/Login'), meta: { guest: true } },
  { path: '/register', name: 'register', component: p('auth/Register'), meta: { guest: true } },
  { path: '/forgot-password', name: 'forgot-password', component: p('auth/ForgotPassword'), meta: { guest: true } },

  // Community
  { path: '/community', name: 'community', component: p('community/BoardList') },
  { path: '/community/write/:board?', name: 'post-write', component: p('community/PostWrite'), meta: { auth: true } },
  { path: '/community/:board', name: 'board', component: p('community/BoardList') },
  { path: '/community/:board/:id', name: 'post-detail', component: p('community/BoardList') },

  // Q&A
  { path: '/qa', name: 'qa', component: p('qa/QaList') },
  { path: '/qa/write', name: 'qa-write', component: p('qa/QAWrite'), meta: { auth: true } },
  { path: '/qa/:id', name: 'qa-detail', component: p('qa/QaList') },

  // Jobs
  { path: '/jobs', name: 'jobs', component: p('jobs/JobList') },
  { path: '/jobs/write', name: 'job-write', component: p('jobs/JobWrite'), meta: { auth: true } },
  { path: '/jobs/:id', name: 'job-detail', component: p('jobs/JobDetail') },

  // Market
  { path: '/market', name: 'market', component: p('market/MarketList') },
  { path: '/market/write', name: 'market-write', component: p('market/MarketWrite'), meta: { auth: true } },
  { path: '/market/:id', name: 'market-detail', component: p('market/MarketDetail') },

  // Real Estate
  { path: '/realestate', name: 'realestate', component: p('realestate/RealEstateList') },
  { path: '/realestate/write', name: 'realestate-write', component: p('realestate/RealEstateWrite'), meta: { auth: true } },
  { path: '/realestate/:id', name: 'realestate-detail', component: p('realestate/RealEstateDetail') },

  // Clubs
  { path: '/clubs', name: 'clubs', component: p('community/ClubList') },
  { path: '/clubs/create', name: 'club-create', component: p('community/ClubWrite'), meta: { auth: true } },
  { path: '/clubs/:id', name: 'club-detail', component: p('community/ClubDetail') },
  { path: '/clubs/:id/edit', name: 'club-edit', component: p('community/ClubWrite'), meta: { auth: true } },

  // News
  { path: '/news', name: 'news', component: p('news/NewsList') },
  { path: '/news/:id', name: 'news-detail', component: p('news/NewsList') },

  // Recipes (식품안전나라 API 기반 + 유저 레시피)
  { path: '/recipes', name: 'recipes', component: p('recipes/RecipeList') },
  { path: '/recipes/write', name: 'recipe-create', component: p('recipes/RecipeCreate'), meta: { auth: true } },
  { path: '/recipes/:id/edit', name: 'recipe-edit', component: p('recipes/RecipeCreate'), meta: { auth: true } },
  { path: '/recipes/:id', name: 'recipe-detail', component: p('recipes/RecipeDetail') },

  // GroupBuy
  { path: '/groupbuy', name: 'groupbuy', component: p('groupbuy/GroupBuyHome') },
  { path: '/groupbuy/create', name: 'groupbuy-create', component: p('groupbuy/GroupBuyCreate'), meta: { auth: true } },
  { path: '/groupbuy/:id', name: 'groupbuy-detail', component: p('groupbuy/GroupBuyDetail') },

  // Events
  { path: '/events', name: 'events', component: p('events/EventList') },
  { path: '/events/create', name: 'event-create', component: p('events/EventCreate'), meta: { auth: true } },
  { path: '/events/:id', name: 'event-detail', component: p('events/EventDetail') },
  { path: '/events/:id/edit', name: 'event-edit', component: p('events/EventCreate'), meta: { auth: true } },

  // Directory
  { path: '/directory', name: 'directory', component: p('directory/BusinessList') },
  { path: '/directory/register', name: 'business-register', component: p('directory/BusinessRegister'), meta: { auth: true } },
  { path: '/directory/:id', name: 'business-detail', component: p('directory/BusinessList') },

  // Chat — /chat/:id 는 동일 ChatRooms 로 라우팅 (내부에서 activeRoom 복원)
  { path: '/chat', name: 'chat', component: p('chat/ChatRooms'), meta: { auth: true } },
  { path: '/chat/:id', name: 'chat-room', component: p('chat/ChatRooms'), meta: { auth: true } },

  // Games (인기 게임만 라우트, 나머지는 GameLobby에서 접근)
  { path: '/games', name: 'games', component: p('games/GameLobby') },
  { path: '/games/quiz', component: p('games/QuizGame') },
  { path: '/games/memory', component: p('games/MemoryGame') },
  { path: '/games/bingo', component: p('games/BingoGame') },
  { path: '/games/2048', component: p('games/Game2048') },
  { path: '/games/omok', component: p('games/OmokGame') },
  { path: '/games/gostop', component: p('games/GoStop') },           // 허브(모드 선택) — Issue #25
  { path: '/games/gostop/solo', component: p('games/GoStopSolo') },   // 솔로 AI
  { path: '/games/holdem', component: p('games/HoldemSolo') },
  { path: '/games/poker', component: p('games/PokerLobby'), meta: { auth: true } },
  { path: '/games/poker/play', component: p('games/PokerPlay'), meta: { auth: true } },
  { path: '/games/poker/multi', component: p('games/PokerMulti'), meta: { auth: true } },
  { path: '/games/poker/tournament/:id', component: p('games/PokerTournamentWait'), meta: { auth: true } },
  { path: '/games/poker/tutorial', component: p('games/PokerTutorial') },
  { path: '/games/blackjack', component: p('games/Blackjack') },
  { path: '/games/wordle', component: p('games/GameWordle') },
  { path: '/games/hangul', component: p('games/GameHangul') },
  { path: '/games/counting', component: p('games/GameCounting') },
  { path: '/games/colors', component: p('games/GameColors') },
  { path: '/games/snake', component: p('games/GameSnake') },
  { path: '/games/typing', component: p('games/GameTyping') },
  { path: '/games/spelling', component: p('games/GameSpelling') },
  { path: '/games/proverb', component: p('games/GameProverb') },
  { path: '/games/wordchain', component: p('games/GameWordChain') },
  { path: '/games/wordblank', component: p('games/GameWordBlank') },
  { path: '/games/wordcard', component: p('games/GameWordCard') },
  { path: '/games/satwords', component: p('games/GameSATWords') },
  { path: '/games/stroop', component: p('games/GameStroop') },
  { path: '/games/puzzle', component: p('games/GamePuzzle') },
  { path: '/games/shapes', component: p('games/GameShapes') },
  { path: '/games/speedcalc', component: p('games/GameSpeedCalc') },
  { path: '/games/stocksim', component: p('games/GameStockSim') },
  { path: '/games/uslife', component: p('games/GameUSLife') },
  { path: '/games/seniormemory', component: p('games/GameSeniorMemory') },
  { path: '/games/towerdefense', component: p('games/GameTowerDefense') },
  { path: '/games/slots', component: p('games/GameSlots') },
  { path: '/games/idiom', component: p('games/GameIdiom') },
  { path: '/games/flag', component: p('games/GameFlag') },
  { path: '/games/leaderboard', component: p('games/Leaderboard') },
  { path: '/games/shop', component: p('games/PointShop'), meta: { auth: true } },

  // Shorts
  { path: '/shorts', name: 'shorts', component: p('shorts/ShortsHome') },
  { path: '/shorts/upload', component: p('shorts/ShortsUpload'), meta: { auth: true } },

  // Music
  { path: '/music', name: 'music', component: p('music/MusicHome') },

  // 안심 커뮤니케이션
  { path: '/comms', name: 'comms', component: p('comms/CommsHome'), meta: { auth: true } },

  // Elder
  { path: '/elder', name: 'elder', component: p('elder/ElderHome') },
  { path: '/elder/checkin', component: p('elder/ElderCheckin'), meta: { auth: true } },
  { path: '/elder/guardian', component: p('elder/GuardianDashboard'), meta: { auth: true } },

  // User
  { path: '/friends', name: 'friends', component: p('friends/FriendList'), meta: { auth: true } },
  { path: '/messages', name: 'messages', component: p('messages/MessageInbox'), meta: { auth: true } },
  { path: '/notifications', name: 'notifications', component: p('Notifications'), meta: { auth: true } },
  { path: '/points', name: 'points', component: p('points/PointDashboard'), meta: { auth: true } },
  { path: '/points/rules', component: p('PointRules') },
  { path: '/search', name: 'search', component: p('Search') },
  { path: '/about', name: 'about', component: p('static/About') },
  { path: '/terms', name: 'terms', component: p('static/Terms') },
  { path: '/privacy', name: 'privacy', component: p('static/Privacy') },
  { path: '/faq', name: 'faq', component: p('static/FAQ') },
  { path: '/profile/edit', component: p('profile/ProfileEdit'), meta: { auth: true } },
  { path: '/profile/:id', name: 'profile', component: p('profile/UserProfile') },
  { path: '/dashboard', name: 'dashboard', component: p('profile/UserDashboard'), meta: { auth: true } },
  { path: '/ad-apply', name: 'ad-apply', component: p('ads/AdApply'), meta: { auth: true } },

  // MyPage v2 (Phase 2-C 묶음 3 스캐폴드 — 개별 페이지는 Placeholder, 실구현은 차후)
  {
    path: '/mypage',
    component: p('mypage/MyPageLayout'),
    meta: { auth: true },
    children: [
      { path: '', redirect: '/mypage/profile' },
      { path: 'posts',                 component: p('mypage/MyPagePlaceholder') },
      { path: 'comments',              component: p('mypage/MyPagePlaceholder') },
      { path: 'market',                component: p('mypage/MyPagePlaceholder') },
      { path: 'realestate',            component: p('mypage/MyPagePlaceholder') },
      { path: 'jobs',                  component: p('mypage/MyPagePlaceholder') },
      { path: 'groupbuy',              component: p('mypage/MyPagePlaceholder') },
      { path: 'clubs',                 component: p('mypage/MyPagePlaceholder') },
      { path: 'events',                component: p('mypage/MyPagePlaceholder') },
      { path: 'business',              component: p('mypage/MyPagePlaceholder') },
      { path: 'resume',                component: p('mypage/MyPagePlaceholder') },
      { path: 'bookmarks',             component: p('mypage/MyPagePlaceholder') },
      { path: 'friends',               component: p('mypage/MyPagePlaceholder') },
      { path: 'messages',              component: p('mypage/MyPagePlaceholder') },
      { path: 'chats',                 component: p('mypage/MyPagePlaceholder') },
      { path: 'calls',                 component: p('mypage/MyPagePlaceholder') },
      { path: 'notifications',         component: p('mypage/MyPagePlaceholder') },
      { path: 'points',                component: p('mypage/MyPagePlaceholder') },
      { path: 'payments',              component: p('mypage/MyPagePlaceholder') },
      { path: 'ads',                   component: p('mypage/MyPagePlaceholder') },
      { path: 'profile',               component: p('mypage/MyPagePlaceholder') },
      { path: 'security',              component: p('mypage/MyPagePlaceholder') },
      { path: 'notification-settings', component: p('mypage/MyPagePlaceholder') },
      { path: 'privacy',               component: p('mypage/MyPagePlaceholder') },
      { path: 'elder',                 component: p('mypage/MyPagePlaceholder') },
    ],
  },

  // Admin v2 (Phase 2-C 묶음 4 레이아웃 + 묶음 6·8·9 구현 페이지)
  {
    path: '/admin/v2',
    component: p('admin/v2/AdminLayoutV2'),
    meta: { auth: true, admin: true },
    children: [
      { path: '', redirect: '/admin/v2/dashboard' },
      // 묶음 9
      { path: 'dashboard', component: p('admin/v2/AnalyticsDashboard') },
      { path: 'analytics/users',   component: p('admin/v2/AnalyticsDashboard') },
      { path: 'analytics/content', component: p('admin/v2/AnalyticsDashboard') },
      { path: 'analytics/revenue', component: p('admin/v2/AnalyticsDashboard') },
      { path: 'analytics/custom',  component: p('admin/v2/AnalyticsDashboard') },
      // 묶음 6
      { path: 'integrations/api-keys',     component: p('admin/v2/ApiKeysPage') },
      { path: 'integrations/stripe',       component: p('admin/v2/ScaffoldPage'), props: { bundle: 6, icon: '💳', title: 'Stripe 연동', description: 'Stripe 키 관리 (묶음 6)' } },
      { path: 'integrations/sentry',       component: p('admin/v2/ScaffoldPage'), props: { bundle: 10, icon: '🚨', title: 'Sentry DSN', description: 'DSN 입력 시 자동 활성화 (kay_final_inputs.md 참조)' } },
      { path: 'integrations/digitalocean', component: p('admin/v2/ScaffoldPage'), props: { bundle: 8, icon: '🌊', title: 'DigitalOcean Token', description: 'Token 입력 시 Mock → 실데이터 전환' } },
      { path: 'integrations/firebase',     component: p('admin/v2/ScaffoldPage'), props: { bundle: 6, icon: '🔥', title: 'Firebase FCM', description: '푸시 알림용 7개 필드 (kay_final_inputs.md 참조)' } },
      // 묶음 8
      { path: 'server/overview',   component: p('admin/v2/ServerOverview') },
      { path: 'server/metrics',    component: p('admin/v2/ScaffoldPage'), props: { bundle: 8, icon: '📈', title: '서버 메트릭 차트', description: 'DO Monitoring API 연동 (Mock 지원)' } },
      { path: 'server/plan',       component: p('admin/v2/ScaffoldPage'), props: { bundle: 8, icon: '💳', title: '플랜 업그레이드', description: 'Droplet resize (DO Token 필요)' } },
      { path: 'server/snapshots',  component: p('admin/v2/ScaffoldPage'), props: { bundle: 8, icon: '📸', title: 'Snapshots', description: 'DO Snapshot 생성·복원 (Mock 리스트 제공)' } },
      { path: 'server/automation', component: p('admin/v2/ScaffoldPage'), props: { bundle: 8, icon: '⚙️', title: '자동화', description: '자동 백업·알림 임계치' } },
      { path: 'server/backup',     component: p('admin/v2/ScaffoldPage'), props: { bundle: 8, icon: '💾', title: '백업 이력', description: 'server_backups 테이블 UI' } },
      // 범용 fallback
      { path: ':pathMatch(.*)*', component: p('admin/v2/ScaffoldPage') },
    ],
  },

  // Admin
  {
    path: '/admin',
    component: p('admin/AdminLayout'),
    meta: { auth: true, admin: true },
    children: [
      { path: '', name: 'admin', component: p('admin/Overview') },
      { path: 'members', component: p('admin/Members') },
      { path: 'friends', component: p('admin/AdminFriends') },
      { path: 'content', component: p('admin/Content') },
      { path: 'boards', component: p('admin/BoardManager') },
      { path: 'news', component: p('admin/AdminNews') },
      { path: 'jobs', component: p('admin/AdminJobs') },
      { path: 'market', component: p('admin/AdminMarket') },
      { path: 'realestate', component: p('admin/AdminRealestate') },
      { path: 'clubs', component: p('admin/AdminClubs') },
      { path: 'qa', component: p('admin/AdminQa') },
      { path: 'recipes', component: p('admin/AdminRecipes') },
      { path: 'events', component: p('admin/AdminEvents') },
      { path: 'hero-banners', component: p('admin/AdminHeroBanners') },
      { path: 'shopping', component: p('admin/AdminShopping') },
      { path: 'shorts', component: p('admin/AdminShorts') },
      { path: 'directory', component: p('admin/Business') },
      { path: 'claims', component: p('admin/AdminClaims') },
      { path: 'games', component: p('admin/AdminGames') },
      { path: 'poker', component: p('admin/AdminPoker') },
      { path: 'music', component: p('admin/AdminMusic') },
      { path: 'groupbuy', component: p('admin/GroupBuy') },
      { path: 'elder', component: p('admin/AdminElder') },
      { path: 'chats', component: p('admin/AdminChats') },
      { path: 'calls', component: p('admin/AdminCalls') },
      { path: 'banners', component: p('admin/Banners') },
      { path: 'ad-settings', component: p('admin/AdSettings') },
      { path: 'payments', component: p('admin/Payments') },
      { path: 'security', component: p('admin/AdminSecurity') },
      { path: 'settings', component: p('admin/SiteSettings') },
      { path: 'point-settings', component: p('admin/AdminPointSettings') },
      { path: 'system', component: p('admin/System') },
    ]
  },

  // 404
  { path: '/404', name: 'not-found', component: p('NotFound') },
  { path: '/:pathMatch(.*)*', redirect: '/404' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    return savedPosition || { top: 0 }
  },
})

router.beforeEach(async (to, from, next) => {
  const auth = useAuthStore()
  await auth.initPromise
  if (to.meta.auth && !auth.isLoggedIn) return next({ name: 'login', query: { redirect: to.fullPath } })
  if (to.meta.admin && !auth.isAdmin) return next('/')
  if (to.meta.guest && auth.isLoggedIn) return next('/')
  next()
})

// 섹션(/jobs, /market 등) 간 이동 시 이전 섹션의 위치 필터 상태 리셋
router.afterEach((to, from) => {
  try {
    // 동적 import 로 순환 참조 방지
    import('../stores/locationFilter').then(({ useLocationFilterStore }) => {
      const store = useLocationFilterStore()
      store.onRouteChange(to.path, from.path)
    })
  } catch {}
})

export default router
