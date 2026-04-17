import './bootstrap'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import { useSiteStore } from './stores/site'
import { initSentry } from './sentry'

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

// Sentry 초기화 (DSN 없으면 no-op, Kay 가 나중에 DSN 입력 시 자동 활성화)
initSentry(app, router)

const authStore = useAuthStore()
authStore.initialize()
if (authStore.isLoggedIn) authStore.fetchUser()
else authStore.resolveInit()

const siteStore = useSiteStore()
siteStore.load()

app.mount('#app')

router.onError((err) => {
  if (err?.message?.includes('Failed to fetch dynamically imported module') || err?.name === 'ChunkLoadError') {
    const last = sessionStorage.getItem('_chunk_reload')
    if (!last || Date.now() - Number(last) > 10000) {
      sessionStorage.setItem('_chunk_reload', String(Date.now()))
      window.location.reload()
    }
  }
})
