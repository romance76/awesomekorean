import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'

export const useSiteStore = defineStore('site', () => {
  const siteName = ref('AwesomeKorean')
  const logoUrl = ref('/images/logo_00.jpg')
  const menus = ref([])
  const loaded = ref(false)
  const darkMode = ref(false)
  const toasts = ref([])
  const settings = ref(null) // 전체 설정 데이터 캐시
  const menuConfig = ref(null)
  let toastId = 0
  let loadPromise = null

  function toast(message, type = 'info', duration = 3000) {
    const id = ++toastId
    toasts.value.push({ id, message, type })
    if (duration > 0) setTimeout(() => removeToast(id), duration)
  }

  function removeToast(id) {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  async function load() {
    if (loaded.value) return settings.value
    // 동시 호출 방지: 진행 중인 요청이 있으면 같은 Promise 재사용
    if (loadPromise) return loadPromise
    loadPromise = _doLoad()
    return loadPromise
  }

  async function _doLoad() {
    try {
      const { data } = await axios.get('/api/settings/public')
      if (data.data) {
        settings.value = data.data
        siteName.value = data.data.site_name || 'AwesomeKorean'
        logoUrl.value = data.data.logo_url || '/images/logo_00.jpg'
        // 메뉴 설정도 여기서 파싱
        if (data.data.menu_config) {
          const parsed = typeof data.data.menu_config === 'string'
            ? JSON.parse(data.data.menu_config) : data.data.menu_config
          if (Array.isArray(parsed) && parsed.length) menuConfig.value = parsed
        }
      }
    } catch {}
    finally { loaded.value = true; loadPromise = null }
    return settings.value
  }

  // 설정값 가져오기 (load 완료 후 사용)
  function getSetting(key, defaultVal = null) {
    return settings.value?.[key] ?? defaultVal
  }

  function isEnabled(key) { return true }
  function getOrder(key) { return 999 }

  return {
    siteName, logoUrl, menus, loaded, darkMode, toasts, settings, menuConfig,
    toast, removeToast, load, getSetting, isEnabled, getOrder,
  }
})
