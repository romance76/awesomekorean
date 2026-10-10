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
  { path: '/auth/social-callback', name: 'social-callback', component: p('auth/SocialCallback') },
  // 이메일 인증 완료 착지 — beforeEach 가 항상 원래 페이지로 보내므로 컴포넌트는 렌더되지 않음
  { path: '/email-verified', name: 'email-verified', component: p('Home') },

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
  { path: '/jobs/:id/applicants', name: 'job-applicants', component: p('jobs/JobApplicants'), meta: { auth: true } },

  // Market
  { path: '/market', name: 'market', component: p('market/MarketList') },
  { path: '/stocks', name: 'stocks', component: p('Stocks') },
  { path: '/market/write', name: 'market-write', component: p('market/MarketWrite'), meta: { auth: true } },
  { path: '/market/:id', name: 'market-detail', component: p('market/MarketDetail') },

  // Real Estate
  { path: '/realestate', name: 'realestate', component: p('realestate/RealEstateList') },
  { path: '/realestate/write', name: 'realestate-write', component: p('realestate/RealEstateWrite'), meta: { auth: true } },
  { path: '/realestate/:id', name: 'realestate-detail', component: p('realestate/RealEstateDetail') },

  // Clubs
  { path: '/clubs', name: 'clubs', component: p('community/ClubList') },
  { path: '/clubs/sample', name: 'club-sample', component: p('community/ClubSample') },
  { path: '/clubs/create', name: 'club-create', component: p('community/ClubWrite'), meta: { auth: true } },
  { path: '/clubs/:id', name: 'club-detail', component: p('community/ClubDetail') },
  { path: '/clubs/:id/edit', name: 'club-edit', component: p('community/ClubWrite'), meta: { auth: true } },

  // News
  { path: '/news', name: 'news', component: p('news/NewsList') },
  { path: '/news/:id', name: 'news-detail', component: p('news/NewsList') },

  // Shopping (Amazon Associates 제휴 상품)
  { path: '/shopping', name: 'shopping', component: p('shopping/ShoppingHome') },
  { path: '/shopping/guide', name: 'shopping-guide', component: p('shopping/AssociatesGuide') },
  { path: '/shopping/write', name: 'shopping-write', component: p('shopping/ShoppingWrite'), meta: { auth: true } },
  { path: '/shopping/:id', name: 'shopping-detail', component: p('shopping/ShoppingDetail') },

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
  // NEW 전단 광고 — 지역별 · 시간대별 상단 노출 게시판
  { path: '/new', name: 'new-board', component: p('new/NewBoard') },
  { path: '/new/apply', redirect: '/dashboard?tab=flyer' },  // 신청은 마이페이지 탭에서
  { path: '/new/:id(\\d+)', name: 'flyer-detail', component: p('new/FlyerDetail') },
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
  { path: '/games/casino', component: p('games/CasinoLobby') },
  { path: '/games/animals', component: p('games/GameAnimals') },
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
  // 기존 스탠드얼론 쪽지함은 대시보드 탭으로 통합 — 북마크/공유 링크 대응용 리다이렉트
  { path: '/messages', redirect: '/dashboard?tab=messages' },
  { path: '/notifications', name: 'notifications', component: p('Notifications'), meta: { auth: true } },
  { path: '/points', name: 'points', component: p('points/PointDashboard'), meta: { auth: true } },
  { path: '/points/rules', component: p('PointRules') },
  { path: '/grades', name: 'grades', component: p('points/MemberGrades') },  // 회원 등급 15단계 안내
  { path: '/entries', redirect: '/dashboard?tab=entries' },  // 마이페이지 대시보드 탭으로 통합
  { path: '/sweepstakes', redirect: '/events?type=sweepstakes' },  // 이벤트 목록으로 통합
  { path: '/sweepstakes/rules', name: 'sweepstakes-rules', component: p('events/SweepstakesRules') },  // 경품 추첨 공식 규정 (공개)
  { path: '/sweepstakes/:id', redirect: '/events?type=sweepstakes' },
  { path: '/search', name: 'search', component: p('Search') },
  { path: '/easy-test', name: 'easy-test', component: p('EasyViewSample') },   // 큰 글씨 보기 샘플(테스트 중)
  { path: '/about', name: 'about', component: p('static/About') },
  { path: '/contact', name: 'contact', component: p('static/Contact') },
  { path: '/terms', name: 'terms', component: p('static/Terms') },
  { path: '/privacy', name: 'privacy', component: p('static/Privacy') },
  { path: '/profile/edit', component: p('profile/ProfileEdit'), meta: { auth: true } },
  { path: '/profile/:id', name: 'profile', component: p('profile/UserProfile') },
  { path: '/dashboard', name: 'dashboard', component: p('profile/UserDashboard'), meta: { auth: true } },
  { path: '/ad-apply', name: 'ad-apply', component: p('ads/AdApply'), meta: { auth: true } },
  { path: '/ad-apply/text', name: 'ad-apply-text', component: p('ads/AdApplyText'), meta: { auth: true } },

  // Admin
  {
    path: '/admin',
    component: p('admin/AdminLayout'),
    meta: { auth: true, admin: true },
    children: [
      { path: '', name: 'admin', component: p('admin/Overview') },
      { path: 'overview', component: p('admin/Overview') },
      { path: 'menu/:group', component: p('admin/AdminMobileMenu') },
      { path: 'members', component: p('admin/Members') },
      { path: 'friends', component: p('admin/AdminFriends') },
      { path: 'content', component: p('admin/Content') },
      { path: 'community', component: p('admin/AdminCommunity') },
      { path: 'boards', component: p('admin/BoardManager') },
      { path: 'news', component: p('admin/AdminNews') },
      { path: 'info', component: p('admin/AdminInfo') },
      { path: 'jobs', component: p('admin/AdminJobs') },
      { path: 'market', component: p('admin/AdminMarket') },
      { path: 'realestate', component: p('admin/AdminRealestate') },
      { path: 'clubs', component: p('admin/AdminClubs') },
      { path: 'qa', component: p('admin/AdminQa') },
      { path: 'recipes', component: p('admin/AdminRecipes') },
      { path: 'events', component: p('admin/AdminEvents') },
      { path: 'hero-banners', component: p('admin/AdminHeroBanners') },
      { path: 'popup-banners', component: p('admin/AdminPopupBanners') },
      { path: 'shopping', component: p('admin/AdminShopping') },
      { path: 'shorts', component: p('admin/AdminShorts') },
      { path: 'directory', component: p('admin/Business') },
      { path: 'claims', component: p('admin/AdminClaims') },
      { path: 'rewards', component: p('admin/AdminRewardRequests') },
      { path: 'games', component: p('admin/AdminGames') },
      { path: 'games/settings/:slug', component: p('admin/AdminGameSettings') },
      { path: 'games/questions/:slug', component: p('admin/AdminQuizQuestions') },
      { path: 'poker', component: p('admin/AdminPoker') },
      { path: 'music', component: p('admin/AdminMusic') },
      { path: 'groupbuy', component: p('admin/GroupBuy') },
      { path: 'elder', component: p('admin/AdminElder') },
      { path: 'chats', component: p('admin/AdminChats') },
      { path: 'calls', component: p('admin/AdminCalls') },
      { path: 'communication', component: p('admin/AdminCommunication') },
      { path: 'ad-center', component: p('admin/AdminAdCenter') },
      { path: 'banners', component: p('admin/Banners') },
      { path: 'flyers', component: p('admin/AdminFlyers') },
      { path: 'ad-settings', redirect: '/admin/pricing' },  // 가격/할인 센터로 통합
      { path: 'pricing', component: p('admin/AdminPricingCenter') },
      { path: 'payments', component: p('admin/Payments') },
      { path: 'revenue', component: p('admin/Revenue') },
      { path: 'security', component: p('admin/AdminSecurity') },
      { path: 'accounts', component: p('admin/AdminAccounts') },
      { path: 'todos', component: p('admin/AdminTodos') },
      { path: 'analytics', component: p('admin/AdminAnalytics') },
      { path: 'open-event', component: p('admin/AdminOpenEvent') },
      { path: 'settings', component: p('admin/SiteSettings') },
      { path: 'point-settings', redirect: '/admin/pricing' },  // 가격/할인 센터로 통합
      { path: 'entry-settings', component: p('admin/AdminEntrySettings') },
      { path: 'sweepstakes', component: p('admin/AdminSweepstakes') },
      { path: 'sweepstakes-rules', component: p('admin/AdminSweepstakesRules') },
      { path: 'system', component: p('admin/System') },
    ]
  },

  // 세븐포커
  { path: '/poker7',          component: p('games/SevenPokerLobby'), meta: { auth: true } },
  { path: '/poker7/room/:id', component: p('games/SevenPokerRoom'),  meta: { auth: true } },

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

// 서버가 이메일 인증을 요구하는(verified.email) 새 글 작성 화면들 — 미인증
// 회원이 폼을 끝까지 채운 뒤 제출 단계에서야 막히지 않도록 진입부터 차단
const VERIFIED_WRITE_ROUTES = new Set([
  'post-write', 'qa-write', 'job-write', 'market-write', 'realestate-write',
  'club-create', 'recipe-create', 'groupbuy-create', 'event-create',
])

router.beforeEach(async (to, from, next) => {
  const auth = useAuthStore()
  await auth.initPromise
  if (to.meta.auth && !auth.isLoggedIn) return next({ name: 'login', query: { redirect: to.fullPath } })
  if (to.meta.admin) {
    if (!auth.isAdmin) return next('/')
    // 관리자 화면은 들어갈 때마다 서버에서 "지금도 로그인돼 있고 관리자인지" 직접 확인한다 (저장된 정보만 믿지 않음)
    if (!from.path.startsWith('/admin') || Date.now() - (window.__adminVerifiedAt || 0) > 5 * 60 * 1000) {
      const ok = await auth.verifyAdminNow()
      if (!ok) return auth.isLoggedIn ? next('/') : next({ name: 'login', query: { redirect: to.fullPath } })
      window.__adminVerifiedAt = Date.now()
    }
  }
  if (to.meta.guest && auth.isLoggedIn) return next('/')
  // 메일의 인증 링크(서버 /api/verify-email)가 여기로 보냄 → 재발송을 눌렀던(또는 막혔던 글쓰기) 페이지로 복귀
  if (to.path === '/email-verified') {
    if (!auth.isLoggedIn) {
      auth.announceVerified('이메일 인증이 완료되었습니다. 로그인해 주세요.')
      return next({ name: 'login' })
    }
    auth.announceVerified()
    return next(auth.takeVerifyReturn() || '/dashboard')
  }
  if (VERIFIED_WRITE_ROUTES.has(to.name) && auth.needsVerification) {
    auth.rememberVerifyReturn(to.fullPath)
    const { useSiteStore } = await import('../stores/site')
    useSiteStore().toast('이메일 인증 후 글을 쓸 수 있어요. 마이페이지에서 인증 메일을 다시 받을 수 있습니다.', 'warning', 5000)
    return next('/dashboard')
  }
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

// 세션이 끝나면(토큰 만료·서버 거부·비활동·다른 창 로그아웃) 안내하고, 로그인이 필요한 화면(특히 관리자)에서는 로그인 화면으로 보낸다
if (typeof window !== 'undefined') {
  window.addEventListener('ak:session-expired', async (e) => {
    window.__adminVerifiedAt = 0
    const cur = router.currentRoute.value
    try {
      const { useSiteStore } = await import('../stores/site')
      useSiteStore().toast(e.detail?.message || '로그인 시간이 지나 로그아웃됐어요.', 'warning', 6000)
    } catch {}
    if (cur.meta?.auth || cur.meta?.admin || cur.path.startsWith('/admin')) {
      router.replace({ name: 'login', query: { redirect: cur.fullPath } })
    }
  })
}
