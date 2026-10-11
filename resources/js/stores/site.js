import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
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
  // 서버가 첫 응답에 심어 준 현재 로고 주소를 먼저 사용 (설정을 불러오기 전에 옛 기본 로고가 비치는 깜빡임 방지)
  const boot = (typeof window !== 'undefined' && window.__BOOT__) || {}
  const logoUrl = ref(boot.logo_url || '/images/logo.png')
  const logoDarkUrl = ref(boot.logo_dark_url || '') // 다크 배경(푸터 등)용 — 미설정 시 컴포넌트가 텍스트로 폴백
  const menus = ref([])
  const loaded = ref(false)
  const darkMode = ref(false)
  const toasts = ref([])
  const settings = ref(null) // 전체 설정 데이터 캐시
  const menuConfig = ref(readMenuCache())
  let toastId = 0
  let loadPromise = null

  // ─── 관리자 "사이트 설정"의 점검 모드·가입 허용·이메일 인증·비밀번호 길이 ───
  // 점검 모드: 서버 공개 설정이 켜져 있다고 알리거나, API 가 503(maintenance)로 답하면 켜진다. 화면 표시는 App.vue 가 결정
  // (관리자·운영자로 로그인한 상태에서는 보여 주지 않는다).
  const maintenance = ref({ active: false, message: '', until: '' })
  const isOn = (v) => v === '1' || v === 1 || v === true
  function setMaintenance(active, info = {}) {
    maintenance.value = { active: !!active, message: info.message || '', until: info.until || '' }
  }
  function applyFlags(d) {
    if (!d) return
    if ('maintenance_mode' in d) setMaintenance(isOn(d.maintenance_mode), { message: d.maintenance_reason, until: d.maintenance_until })
  }
  if (typeof window !== 'undefined') {
    window.addEventListener('ak:maintenance', (e) => setMaintenance(true, e.detail || {}))
  }
  // 값이 아직 없으면(옛 응답·불러오기 전) 평소대로: 가입 허용, 인증 필요, 비밀번호 8자
  const signupOpen = computed(() => settings.value?.allow_signup !== '0' && settings.value?.allow_signup !== 0 && settings.value?.allow_signup !== false)
  const requireEmailVerify = computed(() => settings.value?.require_email_verify !== '0' && settings.value?.require_email_verify !== 0 && settings.value?.require_email_verify !== false)
  const minPasswordLength = computed(() => {
    const n = Number(settings.value?.min_password_length)
    return Number.isFinite(n) && n >= 8 ? Math.min(64, Math.floor(n)) : 8
  })
  // 점검 안내 화면이 "풀렸는지" 확인하려고 쓴다 — 캐시를 피해 최신 공개 설정만 다시 읽는다
  async function refreshFlags() {
    try {
      const { data } = await axios.get('/api/settings/public', { params: { _: Date.now() } })
      if (!data?.data) return false
      const next = { ...(settings.value || {}) }
      for (const k of ['maintenance_mode', 'maintenance_reason', 'maintenance_until', 'allow_signup', 'require_email_verify', 'min_password_length']) {
        if (k in data.data) next[k] = data.data[k]
      }
      settings.value = next
      applyFlags(data.data)
      return true
    } catch { return false }
  }

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
        applyFlags(data.data)
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
    maintenance, signupOpen, requireEmailVerify, minPasswordLength, refreshFlags,
    toast, removeToast, load, getSetting, isEnabled, getOrder, updateMenuConfig,
  }
})
