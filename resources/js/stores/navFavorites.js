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
  { key: 'flyers', label: 'NEW', label_en: 'NEW', icon: '🆕', path: '/new' },
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
  // 서버에서 그리는 페이지(검색엔진용) — 라우터가 아니라 일반 이동 (햄버거 메뉴와 동일)
  { key: 'info', label: '정보', label_en: 'Info', icon: '📘', path: '/info', external: true },
  { key: 'dashboard', label: 'MY', label_en: 'My', icon: '👤', path: '/dashboard' },
]

const DEFAULT_KEYS = ['home', 'community', 'games', 'chat', 'dashboard']
const STORAGE_KEY = 'sk_nav_favorites'
const MAX_FAVORITES = ALL_MENUS.length // 사실상 제한 없음 (모든 메뉴를 하단바에 추가 가능)

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

  // 하단 메뉴 = 왼쪽(햄버거) 메뉴와 같은 기준: 관리자 메뉴 설정의 "순서·이름·경로"를 그대로 따르고
  // (끈 메뉴·관리자 전용·로그인 필요 메뉴는 같은 규칙으로 숨김), 그 중 별(★)을 누른 것만 보여 준다.
  // MY 는 메뉴 설정에 없는 고정 메뉴라 맨 끝에 둔다.
  const favoriteMenus = computed(() => {
    const langStore = useLangStore()
    const siteStore = useSiteStore()
    const auth = useAuthStore()
    const ko = langStore.locale === 'ko'
    const defMap = Object.fromEntries(ALL_MENUS.map(m => [m.key, m]))
    const fav = new Set(favoriteKeys.value)
    const mc = siteStore.menuConfig
    let list
    if (Array.isArray(mc)) {
      list = mc
        .filter(m => m.enabled !== false)
        .filter(m => !m.admin_only || auth.isAdmin)
        .filter(m => !m.login_required || auth.isLoggedIn)
        .map(m => {
          const def = defMap[m.key] || {}
          return {
            ...def, ...m,
            path: m.path || def.path || `/${m.key}`,
            label: ko ? (m.label || def.label || m.key) : (m.label_en || def.label_en || m.label || def.label || m.key),
          }
        })
        .filter(m => m.path && fav.has(m.key))
    } else {
      // 메뉴 설정을 아직 못 읽었을 때만 기본 목록
      list = ALL_MENUS
        .filter(m => m.key !== 'dashboard' && fav.has(m.key))
        .map(m => ({ ...m, label: ko ? m.label : (m.label_en || m.label) }))
    }
    // 상단 메뉴 묶음이 켜져 있으면 하단 탭 순서도 묶음 순서를 따른다 (묶음에 없는 메뉴는 뒤로)
    const groups = siteStore.menuGroups
    if (groups) {
      const order = new Map()
      groups.forEach(g => (g.items || []).forEach(k => { if (!order.has(k)) order.set(k, order.size) }))
      list = list.map((m, i) => ({ m, i })).sort((a, b) => (order.get(a.m.key) ?? 1000 + a.i) - (order.get(b.m.key) ?? 1000 + b.i)).map(x => x.m)
    }
    if (fav.has('dashboard')) {
      const d = defMap.dashboard
      list.push({ ...d, label: ko ? d.label : (d.label_en || d.label) })
    }
    return list
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
