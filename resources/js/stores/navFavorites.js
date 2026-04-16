import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useLangStore } from './lang'

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
    const ko = langStore.locale === 'ko'
    const menuMap = {}
    ALL_MENUS.forEach(m => { menuMap[m.key] = m })
    return favoriteKeys.value
      .map(key => menuMap[key])
      .filter(Boolean)
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
