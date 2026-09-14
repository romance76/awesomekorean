<template>
<div class="min-h-screen">
  <div class="max-w-6xl mx-auto px-4 py-6 relative z-[1]">
    <!-- 헤더 -->
    <div class="flex items-start justify-between mb-1 flex-wrap gap-3">
      <div>
        <h1 class="lobby-hero-title">게임</h1>
        <p class="lobby-hero-sub">포인트 받으며 즐기는 미니게임 모음</p>
      </div>
      <div class="flex items-center gap-2 text-sm flex-wrap mt-1">
        <RouterLink to="/games/leaderboard" class="lobby-btn-ghost text-xs flex items-center gap-1">
          <AppIcon name="trophy" :size="14" /> 리더보드
        </RouterLink>
        <template v-if="auth.isLoggedIn">
          <div class="lobby-points-badge">
            <AppIcon name="coins" :size="16" />
            <span class="text-base tracking-tight">{{ (auth.user?.points || 0).toLocaleString() }}<span class="text-xs">P</span></span>
          </div>
          <button @click="onSpinClick" :class="['lobby-spin-btn', spunToday ? 'is-done' : '']">
            <AppIcon name="sparkles" :size="14" /> {{ spunToday ? '오늘 완료' : '일일 룰렛' }}
          </button>
        </template>
      </div>
    </div>

    <!-- 카테고리 빠른 이동 -->
    <div class="flex gap-1.5 my-4 overflow-x-auto pb-1 scrollbar-hide">
      <a v-for="cat in categories" :key="cat.key" :href="`#cat-${cat.key}`" class="lobby-cat-pill">
        {{ cat.icon }} {{ cat.label }}
      </a>
    </div>

    <DailySpinModal :show="showSpin" @close="showSpin=false" @earned="onSpinEarned" />

    <div v-if="loading" class="text-center py-16 text-sm text-ink-muted">로딩중...</div>

    <template v-else>
      <!-- 카지노 배너 -->
      <RouterLink v-if="casinoGame" :to="casinoGame.path" class="lobby-card lobby-casino-card group flex items-center gap-4 mb-8">
        <div class="lobby-casino-icon">{{ casinoGame.icon }}</div>
        <div class="flex-1 min-w-0">
          <div class="text-base font-black text-white">{{ casinoGame.name }}</div>
          <div class="text-xs text-white/80 mt-0.5">{{ casinoGame.description }}</div>
        </div>
        <div class="lobby-casino-enter">입장 <AppIcon name="arrow-right" :size="14" /></div>
      </RouterLink>

      <!-- 인기 게임 (피처드, 큰 카드) -->
      <section class="mb-8">
        <h2 class="lobby-section-title"><AppIcon name="flame" :size="18" class="text-red-500" /> 인기 게임</h2>
        <div class="lobby-featured-grid">
          <RouterLink v-for="game in featuredGames" :key="game.path" :to="game.path"
            class="lobby-card lobby-game-tile lobby-tile-lg group" :style="tileStyle(game.slug)">
            <span class="lobby-tile-brand">AK</span>
            <span class="lobby-tile-icon">{{ game.icon }}</span>
            <div class="lobby-tile-title lobby-tile-title-lg">{{ game.name }}</div>
            <div class="lobby-tile-desc">{{ game.description }}</div>
          </RouterLink>
        </div>
      </section>

      <!-- 카테고리별 섹션 -->
      <section v-for="cat in gameCategories" :key="cat.key" :id="`cat-${cat.key}`" class="mb-8 scroll-mt-20">
        <h2 class="lobby-section-title">{{ cat.icon }} {{ cat.label }}</h2>
        <div class="lobby-tile-grid">
          <RouterLink v-for="game in gamesByCategory[cat.key]" :key="game.path" :to="game.path"
            class="lobby-card lobby-game-tile group" :style="tileStyle(game.slug)">
            <span class="lobby-tile-brand">AK</span>
            <span class="lobby-tile-icon">{{ game.icon }}</span>
            <div class="lobby-tile-title">{{ game.name }}</div>
            <div class="lobby-tile-desc">{{ game.description }}</div>
          </RouterLink>
        </div>
      </section>
    </template>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import DailySpinModal from '../../components/DailySpinModal.vue'
import AppIcon from '../../components/AppIcon.vue'
import axios from 'axios'

const auth = useAuthStore()
const siteStore = useSiteStore()
const allGames = ref([])
const loading = ref(true)
const showSpin = ref(false)
const spunToday = ref(false)

function onSpinClick() {
  if (!auth.isLoggedIn) return
  if (spunToday.value) {
    siteStore.toast('오늘은 이미 룰렛을 돌렸습니다 🌙', 'info')
    return
  }
  showSpin.value = true
}

function onSpinEarned({ points }) {
  spunToday.value = true
  if (points > 0) auth.user.points = (auth.user.points || 0) + points
}

async function checkSpinStatus() {
  if (!auth.isLoggedIn) return
  try {
    const { data } = await axios.get('/api/points/balance')
    spunToday.value = !!data.daily_spin_done
    if (typeof data.data?.points === 'number') auth.user.points = data.data.points
  } catch {}
}

const categories = [
  { key: 'card', icon: '🃏', label: '카드' },
  { key: 'brain', icon: '🧠', label: '두뇌' },
  { key: 'arcade', icon: '👾', label: '아케이드' },
  { key: 'word', icon: '📝', label: '단어/퀴즈' },
  { key: 'education', icon: '📚', label: '교육' },
]

const casinoGame = computed(() => allGames.value.find(g => g.slug === 'casino'))
const nonCasinoGames = computed(() => allGames.value.filter(g => g.slug !== 'casino'))
const featuredGames = computed(() => nonCasinoGames.value.slice(0, 2))
const gameCategories = computed(() => categories.filter(c => gamesByCategory.value[c.key]?.length))
const gamesByCategory = computed(() => {
  const map = {}
  for (const g of nonCasinoGames.value) {
    if (!map[g.category]) map[g.category] = []
    map[g.category].push(g)
  }
  return map
})

// 게임마다 고유한 배경색 (야후 게임즈 스타일 — 카드 하나하나가 또렷하게 구분되는 단색 브랜드 카드)
const GAME_COLORS = {
  memory: '#e11d48', '2048': '#1d4ed8', omok: '#334155', puzzle: '#0e7490', bingo: '#a21caf',
  speedcalc: '#0369a1', seniormemory: '#be185d', stroop: '#4338ca',
  snake: '#15803d', towerdefense: '#334155', slots: '#c2410c', stocksim: '#059669',
  wordle: '#4d7c0f', wordchain: '#7e22ce', wordblank: '#0f766e', spelling: '#b91c1c',
  typing: '#3730a3', wordcard: '#b45309', hangul: '#92400e', counting: '#0e7490',
  colors: '#a21caf', shapes: '#0d9488', satwords: '#9a3412', proverb: '#78350f',
  flag: '#1e40af', uslife: '#166534', animals: '#9a3412', idiom: '#6b21a8',
}
function tileStyle(slug) {
  return { background: GAME_COLORS[slug] || '#57534e' }
}

onMounted(async () => {
  checkSpinStatus()
  try {
    const { data } = await axios.get('/api/games')
    allGames.value = (data.data || []).map(g => ({
      slug: g.slug,
      path: g.path,
      icon: g.icon,
      name: g.name,
      description: g.description,
      category: g.category,
    }))
  } catch {}
  loading.value = false
})
</script>

<style scoped>
.lobby-hero-title { font-size: 34px; font-weight: 900; letter-spacing: -0.02em; color: #1c1917; line-height: 1.1; }
.lobby-hero-sub { font-size: 13px; color: #78716c; margin-top: 4px; }

.lobby-btn-ghost {
  display: inline-flex; align-items: center; gap: 4px;
  background: #fff; border: 1px solid #EDE8E2; color: #57534e;
  padding: 7px 14px; border-radius: 999px; font-weight: 700;
  box-shadow: 0 1px 2px rgba(27,22,19,.04), 0 8px 24px -12px rgba(27,22,19,.10);
  transition: transform .15s ease;
}
.lobby-btn-ghost:hover { transform: translateY(-1px); }

.lobby-points-badge {
  display: inline-flex; align-items: center; gap: 6px;
  background-image: linear-gradient(135deg,#fb923c,#ea580c); color: #fff;
  padding: 9px 16px; border-radius: 999px; font-weight: 800;
  box-shadow: 0 10px 24px -8px rgba(234,88,12,0.5), inset 0 1px 0 rgba(255,255,255,.35);
}

.lobby-spin-btn {
  display: inline-flex; align-items: center; gap: 4px;
  background-image: linear-gradient(135deg,#fbbf24,#f59e0b); color: #fff;
  padding: 9px 14px; border-radius: 999px; font-weight: 800; font-size: 12px;
  box-shadow: 0 8px 20px -6px rgba(245,158,11,0.5); transition: transform .15s ease;
}
.lobby-spin-btn:hover { transform: translateY(-1px); }
.lobby-spin-btn.is-done { background-image: none; background: rgba(0,0,0,0.06); color: #a8a29e; box-shadow: none; cursor: not-allowed; }

.lobby-cat-pill {
  padding: 7px 14px; border-radius: 999px; font-size: 12px; font-weight: 800;
  white-space: nowrap; flex-shrink: 0; cursor: pointer; text-decoration: none;
  background: #fff; border: 1px solid #EDE8E2; color: #78716c;
  transition: transform .15s ease, background .15s ease;
}
.lobby-cat-pill:hover { color: #b45309; border-color: #FFC7A6; }

.lobby-section-title {
  display: flex; align-items: center; gap: 8px;
  font-size: 18px; font-weight: 900; color: #1c1917; margin-bottom: 12px;
}

.lobby-card {
  display: block; border-radius: 18px; overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
  transition: all .2s ease; text-decoration: none;
}
.lobby-card:hover { transform: translateY(-3px); box-shadow: 0 14px 30px rgba(0,0,0,0.14); }

.lobby-casino-card {
  background-image: linear-gradient(135deg,#FF8A53,#F2570F);
  padding: 16px 20px;
  box-shadow: 0 10px 24px -8px rgba(242,87,15,0.35);
}
.lobby-casino-icon {
  width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0;
  background: rgba(255,255,255,0.18); display: flex; align-items: center; justify-content: center;
  font-size: 22px;
}
.lobby-casino-enter {
  flex-shrink: 0; display: inline-flex; align-items: center; gap: 4px;
  background: rgba(255,255,255,0.95); color: #F2570F;
  font-weight: 800; padding: 8px 16px; border-radius: 999px; font-size: 13px;
  transition: transform .15s ease;
}
.lobby-card:hover .lobby-casino-enter { transform: translateX(3px); }

/* 야후 게임즈 스타일 브랜드 타일 카드 */
.lobby-tile-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
@media (min-width: 640px) { .lobby-tile-grid { grid-template-columns: repeat(3, 1fr); } }
@media (min-width: 1024px) { .lobby-tile-grid { grid-template-columns: repeat(4, 1fr); } }

.lobby-featured-grid { display: grid; grid-template-columns: 1fr; gap: 12px; }
@media (min-width: 640px) { .lobby-featured-grid { grid-template-columns: repeat(2, 1fr); } }

.lobby-game-tile {
  position: relative; aspect-ratio: 4 / 3; padding: 14px;
  display: flex; flex-direction: column; justify-content: flex-end;
}
.lobby-tile-lg { aspect-ratio: 16 / 9; padding: 20px; }

.lobby-tile-brand {
  position: absolute; top: 10px; left: 10px;
  font-size: 10px; font-weight: 900; letter-spacing: 0.05em;
  color: rgba(255,255,255,0.85); background: rgba(0,0,0,0.18);
  padding: 2px 7px; border-radius: 6px;
}
.lobby-tile-icon {
  position: absolute; top: 8px; right: 8px; font-size: 22px;
  background: rgba(255,255,255,0.22); border-radius: 999px;
  width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;
}
.lobby-tile-lg .lobby-tile-icon { font-size: 30px; width: 44px; height: 44px; }

.lobby-tile-title {
  font-size: 16px; font-weight: 900; color: #fff; line-height: 1.15;
  text-shadow: 0 2px 6px rgba(0,0,0,0.25);
}
.lobby-tile-title-lg { font-size: 24px; }
.lobby-tile-desc { font-size: 11px; color: rgba(255,255,255,0.8); margin-top: 3px; }
.lobby-tile-lg .lobby-tile-desc { font-size: 13px; }
</style>
