import './bootstrap'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import { useSiteStore } from './stores/site'

const pinia = createPinia()
const app = createApp(App)
app.use(pinia)
app.use(router)

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
