import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'

// /info(서버사이드 Blade) ↔ SPA를 오갈 때마다 풀 리로드가 일어나는데, 그때마다
// /api/settings/public 응답을 기다리는 동안 메뉴가 비어 있다가 채워지면서
// 깜빡이는 게 보임. localStorage에 직전 메뉴 구성을 캐시해뒀다가 최초 렌더부터
// 바로 써서, 매번 리로드할 때마다 생기는 깜빡임을 없앤다(최초 1회 방문 시에만
// 캐시가 없어 비어 있다가 채워짐).
const MENU_CACHE_KEY = 'ak_menu_config_cache'

function readMenuCache() {
  try {
    const parsed = JSON.parse(localStorage.getItem(MENU_CACHE_KEY))
    return Array.isArray(parsed) && parsed.length ? parsed : null
  } catch { return null }
}

function writeMenuCache(arr) {
  try { localStorage.setItem(MENU_CACHE_KEY, JSON.stringify(arr)) } catch {}
}

export const useSiteStore = defineStore('site', () => {
  const siteName = ref('AwesomeKorean')
  const logoUrl = ref('/images/logo.png')
  const logoDarkUrl = ref('') // 다크 배경(푸터 등)용 — 미설정 시 컴포넌트가 텍스트로 폴백
  const menus = ref([])
  const loaded = ref(false)
  const darkMode = ref(false)
  const toasts = ref([])
  const settings = ref(null) // 전체 설정 데이터 캐시
  const menuConfig = ref(readMenuCache())
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
        logoUrl.value = data.data.logo_url || '/images/logo.png'
        logoDarkUrl.value = data.data.logo_dark_url || ''
        // 메뉴 설정도 여기서 파싱
        if (data.data.menu_config) {
          const parsed = typeof data.data.menu_config === 'string'
            ? JSON.parse(data.data.menu_config) : data.data.menu_config
          if (Array.isArray(parsed) && parsed.length) {
            menuConfig.value = parsed
            writeMenuCache(parsed)
          }
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

  // 관리자 '메뉴 관리'에서 저장 직후 호출 — load()는 세션당 한 번만 서버에서
  // 가져오고 이후론 캐시된 값을 그대로 돌려주기 때문에, SPA 안에서 메뉴를
  // 저장해도 광고/가격 센터 같은 다른 화면이 구버전 menuConfig를 계속 보고
  // 있던 문제(새로고침해야만 반영됨)가 있었음 — 저장한 값을 스토어에 바로
  // 반영해서 같은 세션 내 모든 화면이 즉시 최신 상태를 보도록 함.
  function updateMenuConfig(menus) {
    menuConfig.value = menus
    writeMenuCache(menus)
  }

  return {
    siteName, logoUrl, logoDarkUrl, menus, loaded, darkMode, toasts, settings, menuConfig,
    toast, removeToast, load, getSetting, isEnabled, getOrder, updateMenuConfig,
  }
})
