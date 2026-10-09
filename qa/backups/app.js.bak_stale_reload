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

router.onError((err) => {
  if (err?.message?.includes('Failed to fetch dynamically imported module') || err?.name === 'ChunkLoadError') {
    const last = sessionStorage.getItem('_chunk_reload')
    if (!last || Date.now() - Number(last) > 10000) {
      sessionStorage.setItem('_chunk_reload', String(Date.now()))
      window.location.reload()
    }
  }
})
