import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'

export const useSiteStore = defineStore('site', () => {
  const siteName = ref('SomeKorean')
  const logoUrl = ref('/images/logo_00.jpg')
  const menus = ref([])
  const loaded = ref(false)
  const darkMode = ref(false)
  const toasts = ref([])
  let toastId = 0

  function toast(message, type = 'info', duration = 3000) {
    const id = ++toastId
    toasts.value.push({ id, message, type })
    if (duration > 0) setTimeout(() => removeToast(id), duration)
  }

  function removeToast(id) {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  async function load() {
    if (loaded.value) return
    try {
      const { data } = await axios.get('/api/settings/public')
      if (data.data) {
        siteName.value = data.data.site_name || 'SomeKorean'
        logoUrl.value = data.data.logo_url || '/images/logo_00.jpg'
      }
    } catch {}
    finally { loaded.value = true }
  }

  function isEnabled(key) { return true }
  function getOrder(key) { return 999 }

  return { siteName, logoUrl, menus, loaded, darkMode, toasts, toast, removeToast, load, isEnabled, getOrder }
})
