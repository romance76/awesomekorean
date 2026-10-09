import './bootstrap'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import { useSiteStore } from './stores/site'

const pinia = createPinia()
import UserName from './components/UserName.vue'
import Pagination from './components/Pagination.vue'
import MobileFilter from './components/MobileFilter.vue'

const app = createApp(App)
app.component('UserName', UserName)
app.component('Pagination', Pagination)
app.component('MobileFilter', MobileFilter)

import MobileAdInline from './components/MobileAdInline.vue'
app.component('MobileAdInline', MobileAdInline)
app.use(pinia)
app.use(router)

const authStore = useAuthStore()
authStore.initialize()
if (authStore.isLoggedIn) authStore.fetchUser()
else authStore.resolveInit()

const siteStore = useSiteStore()
siteStore.load()

// 라우터의 첫 네비게이션(비동기 가드 포함)이 끝나기 전에 마운트하면,
// route.path가 아직 시작 위치('/')인 상태로 한 프레임 그려졌다가
// 실제 경로로 갱신되며 다시 그려짐 — 새로고침할 때마다 잠깐 홈
// 레이아웃(네비바/푸터)이 보였다 사라지는 깜빡임의 원인이었음.
// isReady()로 첫 네비게이션이 끝난 뒤에 마운트해서 깜빡임을 없앤다.
router.isReady().then(() => app.mount('#app'))

// 새 배포 후 옛 파일을 못 불러올 때(라우터 밖 지연 로딩 포함) 자동 새로고침 — 10초 안에 반복 새로고침은 방지
function reloadOnce() {
  const last = sessionStorage.getItem('_chunk_reload')
  if (!last || Date.now() - Number(last) > 10000) {
    sessionStorage.setItem('_chunk_reload', String(Date.now()))
    window.location.reload()
  }
}
router.onError((err) => {
  if (err?.message?.includes('Failed to fetch dynamically imported module') || err?.name === 'ChunkLoadError') reloadOnce()
})
window.addEventListener('vite:preloadError', (e) => { e.preventDefault(); reloadOnce() })

// 오래 열어 둔 앱(설치형/탭)이 배포 이전 버전으로 계속 동작해 버튼이 안 눌리는 문제 방지:
// 화면으로 돌아올 때 최신 빌드와 비교해서, 오래 비워 둔 뒤면 바로 새로고침하고
// 아니면(작성 중일 수 있어) 다음 페이지 이동 때 새로 불러온다.
const loadedEntry = [...document.scripts].map(s => s.src).find(s => /\/build\/assets\/app-[^/]+\.js/.test(s))
let newVersionReady = false
let hiddenAt = 0
async function checkNewVersion() {
  if (!loadedEntry) return
  try {
    const res = await fetch('/build/manifest.json', { cache: 'no-store' })
    if (!res.ok) return
    const file = (await res.json())['resources/js/app.js']?.file
    if (file && !loadedEntry.endsWith('/' + file)) newVersionReady = true
  } catch {}
}
document.addEventListener('visibilitychange', async () => {
  if (document.visibilityState === 'hidden') { hiddenAt = Date.now(); return }
  const away = hiddenAt ? Date.now() - hiddenAt : 0
  await checkNewVersion()
  if (newVersionReady && away > 10 * 60 * 1000) reloadOnce()
})
router.beforeEach((to) => {
  if (newVersionReady) { window.location.href = to.fullPath; return false }
})
