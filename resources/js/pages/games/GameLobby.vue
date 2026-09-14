<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5 relative z-[1]">
    <!-- 헤더 -->
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="lobby-icon-chip w-9 h-9"><AppIcon name="gamepad" :size="20" /></span>
        게임
      </h1>
      <div class="flex items-center gap-2 text-sm flex-wrap">
        <!-- 리더보드 (로그인 불필요) -->
        <RouterLink to="/games/leaderboard"
          class="lobby-btn-ghost text-xs flex items-center gap-1">
          <AppIcon name="trophy" :size="14" /> 리더보드
        </RouterLink>
        <template v-if="auth.isLoggedIn">
          <!-- 강조된 포인트 표시 -->
          <div class="lobby-points-badge">
            <AppIcon name="coins" :size="16" />
            <span class="text-base tracking-tight">{{ (auth.user?.points || 0).toLocaleString() }}<span class="text-xs">P</span></span>
          </div>
          <!-- 일일 룰렛 (팝업) -->
          <button @click="onSpinClick"
            :class="['lobby-spin-btn', spunToday ? 'is-done' : '']">
            <AppIcon name="sparkles" :size="14" /> {{ spunToday ? '오늘 완료' : '일일 룰렛' }}
          </button>
        </template>
      </div>
    </div>

    <!-- 일일 룰렛 모달 -->
    <DailySpinModal :show="showSpin" @close="showSpin=false" @earned="onSpinEarned" />

    <div class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 카테고리 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="lobby-glass-panel overflow-hidden sticky top-20">
          <div class="px-3 py-2.5 border-b border-white/40 font-bold text-xs text-ink flex items-center gap-1.5">
            <AppIcon name="list" :size="13" class="text-amber-500" /> 카테고리
          </div>
          <button v-for="cat in categories" :key="cat.key" @click="activeCat=cat.key"
            class="lobby-cat-row"
            :class="activeCat===cat.key ? 'is-active' : ''">
            {{ cat.icon }} {{ cat.label }}
          </button>
        </div>
      </div>

      <!-- 메인: 게임 카드 -->
      <div class="col-span-12 lg:col-span-7">
        <!-- 모바일 카테고리 -->
        <div class="lg:hidden flex gap-1.5 mb-3 overflow-x-auto pb-1 scrollbar-hide">
          <button v-for="cat in categories" :key="cat.key" @click="activeCat=cat.key"
            class="lobby-cat-pill"
            :class="activeCat===cat.key ? 'is-active' : ''">
            {{ cat.icon }} {{ cat.label }}
          </button>
        </div>

        <div v-if="loading" class="text-center py-16 text-sm text-ink-muted">로딩중...</div>
        <div v-else-if="!filteredGames.length" class="py-16 text-center">
          <div class="lobby-icon-chip w-14 h-14 mx-auto mb-3 opacity-50"><AppIcon name="gamepad" :size="28" :stroke-width="1.5" /></div>
          <p class="text-sm text-ink-muted">게임이 없습니다</p>
        </div>
        <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <RouterLink v-for="game in filteredGames" :key="game.path" :to="game.path"
            class="lobby-game-card group"
            :class="game.slug === 'casino'
              ? 'lobby-casino-card col-span-2 sm:col-span-3 flex items-center gap-4 text-left'
              : 'lobby-thumb-card'">
            <template v-if="game.slug === 'casino'">
              <div class="text-5xl flex-shrink-0">{{ game.icon }}</div>
              <div class="flex-1 min-w-0">
                <div class="text-lg font-black text-white">{{ game.name }}</div>
                <div class="text-xs text-white/85 mt-0.5">{{ game.description }}</div>
                <div class="flex gap-1 mt-2 text-lg">
                  <span>♠️</span><span>♦️</span><span>🎴</span><span>🂡</span>
                </div>
              </div>
              <div class="lobby-casino-enter">입장 →</div>
            </template>
            <template v-else>
              <div class="lobby-thumb-wrap">
                <GameThumb :slug="game.slug" class="lobby-thumb-img" />
              </div>
              <div class="lobby-thumb-body">
                <div class="text-sm font-bold text-ink group-hover:text-amber-700">{{ game.name }}</div>
                <div class="text-xs text-ink-muted mt-0.5">{{ game.description }}</div>
              </div>
            </template>
          </RouterLink>
        </div>
      </div>

      <!-- 오른쪽: 위젯 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block space-y-3">
        <div class="lobby-glass-panel overflow-hidden">
          <div class="px-3 py-2.5 border-b border-white/40 font-bold text-xs text-ink flex items-center gap-1.5">
            <AppIcon name="flame" :size="13" class="text-red-500" /> 인기 게임
          </div>
          <RouterLink v-for="g in popularGames" :key="g.path" :to="g.path"
            class="block px-3 py-2 hover:bg-white/50 transition-colors text-xs text-ink-light hover:text-amber-700">
            {{ g.icon }} {{ g.name }}
          </RouterLink>
        </div>
        <div class="lobby-glass-panel p-3">
          <div class="font-bold text-xs text-ink mb-2 flex items-center gap-1.5">
            <AppIcon name="megaphone" :size="13" class="text-amber-500" /> 게임 안내
          </div>
          <div class="text-xs text-ink-muted space-y-1">
            <div>• 게임 플레이 시 포인트 획득</div>
            <div>• 일일 룰렛으로 무료 포인트</div>
            <div>• 리더보드에 도전하세요</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import DailySpinModal from '../../components/DailySpinModal.vue'
import AppIcon from '../../components/AppIcon.vue'
import GameThumb from '../../components/GameThumb.vue'
import axios from 'axios'

const auth = useAuthStore()
const siteStore = useSiteStore()
const activeCat = ref('all')
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
  { key: 'all', icon: '🎮', label: '전체' },
  { key: 'card', icon: '🃏', label: '카드' },
  { key: 'brain', icon: '🧠', label: '두뇌' },
  { key: 'arcade', icon: '👾', label: '아케이드' },
  { key: 'word', icon: '📝', label: '단어/퀴즈' },
  { key: 'education', icon: '📚', label: '교육' },
]

const filteredGames = computed(() => {
  if (activeCat.value === 'all') return allGames.value
  return allGames.value.filter(g => g.category === activeCat.value)
})

const popularGames = computed(() => allGames.value.slice(0, 8))

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
.lobby-icon-chip {
  display: inline-flex; align-items: center; justify-content: center; border-radius: 12px;
  background-image: linear-gradient(135deg, rgba(251,191,36,0.25), rgba(249,115,22,0.2));
  color: #b45309; flex-shrink: 0;
}

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

.lobby-glass-panel {
  background: #fff; border: 1px solid #EDE8E2; border-radius: 20px;
  box-shadow: 0 1px 2px rgba(27,22,19,.04), 0 8px 24px -12px rgba(27,22,19,.10);
}

.lobby-cat-row {
  display: block; width: 100%; text-align: left; padding: 9px 14px; font-size: 13px;
  color: #57534e; background: transparent; border: none; cursor: pointer;
  transition: background .15s ease, color .15s ease;
}
.lobby-cat-row:hover { background: #F8F6F3; }
.lobby-cat-row.is-active {
  background-image: linear-gradient(135deg, rgba(251,191,36,0.25), rgba(249,115,22,0.15));
  color: #b45309; font-weight: 800;
}

.lobby-cat-pill {
  padding: 7px 14px; border-radius: 999px; font-size: 12px; font-weight: 800;
  white-space: nowrap; flex-shrink: 0; cursor: pointer;
  background: #fff; border: 1px solid #EDE8E2; color: #78716c;
  transition: transform .15s ease, background .15s ease;
}
.lobby-cat-pill.is-active {
  background-image: linear-gradient(135deg,#fbbf24,#f59e0b); color: #fff;
  border-color: transparent; box-shadow: 0 6px 16px -4px rgba(245,158,11,0.5);
}

.lobby-game-card {
  display: block; padding: 18px 14px; border-radius: 20px; overflow: hidden;
  background: #fff; border: 1px solid #EDE8E2;
  box-shadow: 0 1px 2px rgba(27,22,19,.04), 0 8px 24px -12px rgba(27,22,19,.10);
  transition: transform .15s ease, box-shadow .15s ease;
}
.lobby-game-card:hover { transform: translateY(-3px); box-shadow: 0 2px 4px rgba(27,22,19,.05), 0 16px 40px -16px rgba(255,90,31,.18); }

.lobby-thumb-card { padding: 0; display: flex; flex-direction: column; }
.lobby-thumb-wrap {
  position: relative; aspect-ratio: 4 / 3; width: 100%;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg,#f1f5f9,#e2e8f0); overflow: hidden;
}
.lobby-thumb-img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .3s ease; }
.lobby-thumb-card:hover .lobby-thumb-img { transform: scale(1.05); }
.lobby-thumb-body { padding: 10px 12px 12px; text-align: center; }

.lobby-casino-card {
  background-image: linear-gradient(135deg,#fb923c,#ea580c);
  border-color: transparent; padding: 20px;
  box-shadow: 0 16px 36px -10px rgba(234,88,12,0.5), inset 0 1px 0 rgba(255,255,255,0.25);
}
.lobby-casino-enter {
  flex-shrink: 0; background: rgba(255,255,255,0.9); color: #c2410c;
  font-weight: 800; padding: 10px 20px; border-radius: 999px; font-size: 14px;
  transition: transform .15s ease;
}
.lobby-game-card:hover .lobby-casino-enter { transform: translateX(3px); }
</style>
