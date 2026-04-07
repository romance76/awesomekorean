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
  { path: '/community/:board/:id', name: 'post-detail', component: p('community/PostDetail') },

  // Q&A
  { path: '/qa', name: 'qa', component: p('qa/QaList') },
  { path: '/qa/write', name: 'qa-write', component: p('qa/QAWrite'), meta: { auth: true } },
  { path: '/qa/:id', name: 'qa-detail', component: p('qa/QaDetail') },

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
  { path: '/clubs/:id', name: 'club-detail', component: p('community/ClubDetail') },

  // News
  { path: '/news', name: 'news', component: p('news/NewsList') },
  { path: '/news/:id', name: 'news-detail', component: p('news/NewsDetail') },

  // Recipes
  { path: '/recipes', name: 'recipes', component: p('recipes/RecipeList') },
  { path: '/recipes/write', name: 'recipe-write', component: p('recipes/RecipeCreate'), meta: { auth: true } },
  { path: '/recipes/:id', name: 'recipe-detail', component: p('recipes/RecipeDetail') },

  // GroupBuy
  { path: '/groupbuy', name: 'groupbuy', component: p('groupbuy/GroupBuyHome') },

  // Events
  { path: '/events', name: 'events', component: p('events/EventList') },
  { path: '/events/create', name: 'event-create', component: p('events/EventCreate'), meta: { auth: true } },
  { path: '/events/:id', name: 'event-detail', component: p('events/EventDetail') },

  // Directory
  { path: '/directory', name: 'directory', component: p('directory/BusinessList') },
  { path: '/directory/register', name: 'business-register', component: p('directory/BusinessRegister'), meta: { auth: true } },
  { path: '/directory/:id', name: 'business-detail', component: p('directory/BusinessDetail') },

  // Chat
  { path: '/chat', name: 'chat', component: p('chat/ChatRooms'), meta: { auth: true } },
  { path: '/chat/:id', name: 'chat-room', component: p('chat/ChatRoom'), meta: { auth: true } },

  // Games (인기 게임만 라우트, 나머지는 GameLobby에서 접근)
  { path: '/games', name: 'games', component: p('games/GameLobby') },
  { path: '/games/quiz', component: p('games/QuizGame') },
  { path: '/games/memory', component: p('games/MemoryGame') },
  { path: '/games/bingo', component: p('games/BingoGame') },
  { path: '/games/2048', component: p('games/Game2048') },
  { path: '/games/omok', component: p('games/OmokGame') },
  { path: '/games/gostop', component: p('games/GoStopSolo') },
  { path: '/games/holdem', component: p('games/HoldemSolo') },
  { path: '/games/poker', component: p('games/Poker'), meta: { auth: true } },
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

  // Elder
  { path: '/elder', name: 'elder', component: p('elder/ElderHome'), meta: { auth: true } },
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
  { path: '/profile/edit', component: p('profile/ProfileEdit'), meta: { auth: true } },
  { path: '/profile/:id', name: 'profile', component: p('profile/UserProfile') },
  { path: '/dashboard', name: 'dashboard', component: p('profile/UserDashboard'), meta: { auth: true } },

  // Admin
  {
    path: '/admin',
    component: p('admin/AdminLayout'),
    meta: { auth: true, admin: true },
    children: [
      { path: '', name: 'admin', component: p('admin/Overview') },
      { path: 'members', component: p('admin/Members') },
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
      { path: 'shopping', component: p('admin/AdminShopping') },
      { path: 'shorts', component: p('admin/AdminShorts') },
      { path: 'directory', component: p('admin/Business') },
      { path: 'games', component: p('admin/AdminGames') },
      { path: 'music', component: p('admin/AdminMusic') },
      { path: 'groupbuy', component: p('admin/GroupBuy') },
      { path: 'elder', component: p('admin/Elder') },
      { path: 'chats', component: p('admin/Chats') },
      { path: 'banners', component: p('admin/Banners') },
      { path: 'payments', component: p('admin/Payments') },
      { path: 'security', component: p('admin/AdminSecurity') },
      { path: 'settings', component: p('admin/SiteSettings') },
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

export default router
