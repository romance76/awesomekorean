import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { installLateralReplace } from './lateralReplace'

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

  // 한인 유튜버
  { path: '/youtubers', name: 'youtubers', component: p('youtubers/YoutubeList') },
  { path: '/youtubers/apply', name: 'youtubers-apply', component: p('youtubers/YoutubeApply'), meta: { auth: true } },
  { path: '/youtubers/:id', name: 'youtuber-detail', component: p('youtubers/YoutubeChannel') },

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
  // 정보(/info) — 서버가 그리는 페이지를 사이트 안에서 틀로 보여 줌 (음악이 끊기지 않게). 직접 주소 접속은 서버가 그대로 처리
  { path: '/info', name: 'info', component: p('InfoFrame') },
  { path: '/info/:slug(.*)', name: 'info-show', component: p('InfoFrame') },
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
  // MONEY 허브 (qa/money_hub_plan.md)
  { path: '/money', name: 'money', component: p('money/MoneyHome') },
  { path: '/money/cafes', redirect: '/money' },   // 카페 전체 안내는 허브(/money)로 합침
  { path: '/money/cafe/:slug', name: 'money-cafe', component: p('money/MoneyCafe'), meta: { stableKey: true } },   // 주제·쪽 선택을 주소에 적어도 화면을 새로 그리지 않음(App.vue viewKey)
  { path: '/money/post/:id', name: 'money-post', component: p('money/MoneyPost') },
  { path: '/money/write', name: 'money-write', component: p('money/MoneyWrite'), meta: { auth: true } },
  { path: '/money/guide/:slug', name: 'money-guide', component: p('money/MoneyGuide') },

  // Recipes (식품안전나라 API 기반 + 유저 레시피)
  { path: '/recipes', name: 'recipes', component: p('recipes/RecipeList') },
  { path: '/recipes/write', name: 'recipe-create', component: p('recipes/RecipeCreate'), meta: { auth: true } },
  { path: '/recipes/:id/edit', name: 'recipe-edit', component: p('recipes/RecipeCreate'), meta: { auth: true } },
  { path: '/recipes/:id', name: 'recipe-detail', component: p('recipes/RecipeDetail') },

  // GroupBuy
  { path: '/groupbuy', name: 'groupbuy', component: p('groupbuy/GroupBuyHome') },
  { path: '/groupbuy/create', name: 'groupbuy-create', component: p('groupbuy/GroupBuyCreate'), meta: { auth: true } },
  { path: '/groupbuy/manage', redirect: '/dashboard?tab=groupbuy' },   // 내 공동구매 관리는 마이페이지 탭에서
  { path: '/groupbuy/coupon/:code', name: 'groupbuy-coupon-check', component: p('groupbuy/CouponCheck') },   // 가게가 손님 QR 을 찍으면 열리는 쿠폰 확인 페이지(로그인 없이 PIN)
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
  // 운영하지 않는 서비스(게임·안심서비스·세븐포커): 주소로 직접 들어와도 열리지 않고 홈으로 보낸다 (서버도 같은 주소를 301 로 홈에 보냄 — routes/web.php)
  { path: '/games/:rest(.*)*', redirect: '/' },
  { path: '/elder/:rest(.*)*', redirect: '/' },
  { path: '/poker7/:rest(.*)*', redirect: '/' },

  // Shorts
  { path: '/shorts', name: 'shorts', component: p('shorts/ShortsHome') },
  { path: '/shorts/upload', component: p('shorts/ShortsUpload'), meta: { auth: true } },

  // Music
  { path: '/music', name: 'music', component: p('music/MusicHome') },

  // 안심 커뮤니케이션
  { path: '/comms', name: 'comms', component: p('comms/CommsHome'), meta: { auth: true } },

  // Elder

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
      // 할 일 #84 전(옛 4그룹) 주소가 북마크돼 있을 경우를 위한 호환 리다이렉트 — 동적 :group 라우트보다 먼저 와야 매칭됨
      { path: 'menu/board', redirect: '/admin/menu/content' },
      { path: 'menu/ad', redirect: '/admin/menu/money' },
      { path: 'menu/:group', component: p('admin/AdminMobileMenu') },
      { path: 'members', component: p('admin/Members') },
      { path: 'friends', component: p('admin/AdminFriends') },
      { path: 'content', component: p('admin/Content') },
      { path: 'community', component: p('admin/AdminCommunity') },
      { path: 'boards', component: p('admin/BoardManager') },
      { path: 'news', component: p('admin/AdminNews') },
      { path: 'info', component: p('admin/AdminInfo') },
      { path: 'money', component: p('admin/AdminMoney') },
      { path: 'social', component: p('admin/AdminSocial') },
      { path: 'jobs', component: p('admin/AdminJobs') },
      { path: 'market', component: p('admin/AdminMarket') },
      { path: 'realestate', component: p('admin/AdminRealestate') },
      { path: 'clubs', component: p('admin/AdminClubs') },
      { path: 'youtubers', component: p('admin/AdminYoutubers') },
      { path: 'qa', component: p('admin/AdminQa') },
      { path: 'recipes', component: p('admin/AdminRecipes') },
      { path: 'events', component: p('admin/AdminEvents') },
      { path: 'hero-banners', component: p('admin/AdminHeroBanners') },
      { path: 'popup-banners', component: p('admin/AdminPopupBanners') },
      { path: 'shopping', component: p('admin/AdminShopping') },
      { path: 'shorts', component: p('admin/AdminShorts') },
      { path: 'directory', component: p('admin/Business') },
      { path: 'claims', component: p('admin/AdminClaims') },
      // 보상 승인은 지급·보상 센터의 한 탭으로 합침 — 알림·"오늘 할 일"의 옛 주소(?tab=events|recipes)는 그대로 이동
      { path: 'rewards', redirect: to => ({ path: '/admin/grants', query: { ...to.query, section: 'rewards' } }) },
      { path: 'grants', component: p('admin/AdminGrants') },
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
      // 매출·결제 통합(할 일 #90) — 독립 화면 대신 결제/오더 화면의 '매출 현황' 탭으로. 옛 주소 북마크 대비 리다이렉트.
      { path: 'revenue', redirect: to => ({ path: '/admin/payments', query: { ...to.query, tab: 'revenue' } }) },
      { path: 'security', component: p('admin/AdminSecurity') },
      { path: 'accounts', component: p('admin/AdminAccounts') },
      { path: 'two-factor', component: p('admin/AdminTwoFactor') },
      { path: 'todos', component: p('admin/AdminTodos') },
      { path: 'audit-log', component: p('admin/AdminAuditLog') },
      { path: 'analytics', component: p('admin/AdminAnalytics') },
      { path: 'open-event', component: p('admin/AdminOpenEvent') },
      { path: 'settings', component: p('admin/SiteSettings') },
      { path: 'point-settings', redirect: '/admin/pricing' },  // 가격/할인 센터로 통합
      { path: 'entry-settings', component: p('admin/AdminEntrySettings') },
      { path: 'sweepstakes', component: p('admin/AdminSweepstakes') },
      { path: 'sweepstakes-rules', component: p('admin/AdminSweepstakesRules') },
      { path: 'event-import', component: p('admin/AdminEventImport') },
      { path: 'event-feeds', component: p('admin/AdminEventFeeds') },
      { path: 'system', component: p('admin/System') },
    ]
  },

  // 세븐포커

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

// 같은 메뉴 묶음의 탭끼리 옮길 때는 이동 기록을 쌓지 않고 바꿔 끼움 → 뒤로가기가 그 묶음에 들어오기 전 화면으로 간다
installLateralReplace(router)

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
    const { useSiteStore } = await import('../stores/site')
    // 관리자 사이트 설정의 "이메일 인증 필수"가 꺼져 있는지 알려면 공개 설정이 와 있어야 한다 (첫 화면에서 아직 못 불러왔을 수 있음)
    await useSiteStore().load()
    if (auth.needsVerification) {
      auth.rememberVerifyReturn(to.fullPath)
      useSiteStore().toast('이메일 인증 후 글을 쓸 수 있어요. 마이페이지에서 인증 메일을 다시 받을 수 있습니다.', 'warning', 5000)
      return next('/dashboard')
    }
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
