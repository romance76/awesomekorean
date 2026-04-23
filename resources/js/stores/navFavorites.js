import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useLangStore } from './lang'
import { useSiteStore } from './site'
import { useAuthStore } from './auth'

const ALL_MENUS = [
  { key: 'home', label: '홈', label_en: 'Home', icon: '🏠', path: '/' },
  { key: 'community', label: '커뮤니티', label_en: 'Community', icon: '💬', path: '/community' },
  { key: 'qa', label: 'Q&A', label_en: 'Q&A', icon: '❓', path: '/qa' },
  { key: 'jobs', label: '구인구직', label_en: 'Jobs', icon: '💼', path: '/jobs' },
  { key: 'market', label: '중고장터', label_en: 'Market', icon: '🛒', path: '/market' },
  { key: 'directory', label: '업소록', label_en: 'Directory', icon: '📋', path: '/directory' },
  { key: 'realestate', label: '부동산', label_en: 'Real Estate', icon: '🏠', path: '/realestate' },
  { key: 'events', label: '이벤트', label_en: 'Events', icon: '🎉', path: '/events' },
  { key: 'news', label: '뉴스', label_en: 'News', icon: '📰', path: '/news' },
  { key: 'recipes', label: '레시피', label_en: 'Recipes', icon: '🍳', path: '/recipes' },
  { key: 'clubs', label: '동호회', label_en: 'Clubs', icon: '👥', path: '/clubs' },
  { key: 'games', label: '게임', label_en: 'Games', icon: '🎮', path: '/games' },
  { key: 'shorts', label: '숏츠', label_en: 'Shorts', icon: '📱', path: '/shorts' },
  { key: 'music', label: '음악듣기', label_en: 'Music', icon: '🎵', path: '/music' },
  { key: 'groupbuy', label: '공동구매', label_en: 'Group Buy', icon: '🤝', path: '/groupbuy' },
  { key: 'chat', label: '채팅', label_en: 'Chat', icon: '💭', path: '/chat' },
  { key: 'friends', label: '친구', label_en: 'Friends', icon: '👫', path: '/friends' },
  { key: 'elder', label: '안심서비스', label_en: 'Elder Care', icon: '💙', path: '/elder' },
  { key: 'comms', label: '안심 커뮤', label_en: 'Comms', icon: '📞', path: '/comms' },
  { key: 'shopping', label: '쇼핑', label_en: 'Shopping', icon: '🛍️', path: '/shopping' },
  { key: 'dashboard', label: 'MY', label_en: 'My', icon: '👤', path: '/dashboard' },
]

const DEFAULT_KEYS = ['home', 'community', 'games', 'chat', 'dashboard']
const STORAGE_KEY = 'sk_nav_favorites'
const MAX_FAVORITES = 8

export const useNavFavoritesStore = defineStore('navFavorites', () => {
  const favoriteKeys = ref(loadFromStorage())

  function loadFromStorage() {
    try {
      const saved = localStorage.getItem(STORAGE_KEY)
      if (saved) {
        const parsed = JSON.parse(saved)
        if (Array.isArray(parsed) && parsed.length >= 3) return parsed
      }
    } catch {}
    return [...DEFAULT_KEYS]
  }

  function save() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(favoriteKeys.value))
  }

  const favoriteMenus = computed(() => {
    const langStore = useLangStore()
    const siteStore = useSiteStore()
    const auth = useAuthStore()
    const ko = langStore.locale === 'ko'
    const menuMap = {}
    ALL_MENUS.forEach(m => { menuMap[m.key] = m })
    // 관리자 메뉴 설정 맵 (enabled=false / admin_only 필터링용)
    const mc = siteStore.menuConfig
    const cfgMap = (mc && Array.isArray(mc)) ? Object.fromEntries(mc.map(m => [m.key, m])) : null
    return favoriteKeys.value
      .map(key => menuMap[key])
      .filter(Boolean)
      .filter(m => {
        // dashboard 는 항상 노출 (메뉴 설정과 무관)
        if (m.key === 'dashboard') return true
        if (!cfgMap) return true
        const cfg = cfgMap[m.key]
        if (!cfg) return true
        if (cfg.enabled === false) return false
        if (cfg.admin_only && !auth.isAdmin) return false
        return true
      })
      .map(m => ({
        ...m,
        label: ko ? m.label : (m.label_en || m.label),
      }))
  })

  // 햄버거 메뉴에서 사용 — 즐겨찾기 여부 체크
  function isFavorite(key) {
    return favoriteKeys.value.includes(key)
  }

  function toggleFavorite(key) {
    const idx = favoriteKeys.value.indexOf(key)
    if (idx >= 0) {
      // 최소 3개는 유지
      if (favoriteKeys.value.length <= 3) return false
      favoriteKeys.value.splice(idx, 1)
    } else {
      if (favoriteKeys.value.length >= MAX_FAVORITES) return false
      favoriteKeys.value.push(key)
    }
    save()
    return true
  }

  function resetToDefault() {
    favoriteKeys.value = [...DEFAULT_KEYS]
    save()
  }

  return {
    favoriteKeys,
    favoriteMenus,
    isFavorite,
    toggleFavorite,
    resetToDefault,
    ALL_MENUS,
    MAX_FAVORITES,
  }
})
