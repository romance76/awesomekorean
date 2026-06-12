<template>
<div>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-1">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="gamepad" :size="20" /></span>
    게임 관리
  </h1>
  <p class="text-xs text-ink-muted mb-4">각 게임의 노출/숨김, 카테고리, 순서를 조정합니다. 설정 아이콘을 눌러 게임별 세부 설정 페이지로 이동할 수 있습니다.</p>

  <!-- 특화 관리 링크 -->
  <div class="flex gap-2 mb-4 flex-wrap">
    <RouterLink to="/admin/poker" class="btn-secondary !px-3 !py-1.5 !text-xs"><AppIcon name="gamepad" :size="14" /> 포커 토너먼트</RouterLink>
    <RouterLink to="/admin/pricing" class="btn-secondary !px-3 !py-1.5 !text-xs"><AppIcon name="coins" :size="14" /> 포인트 설정</RouterLink>
  </div>

  <!-- 카테고리 필터 -->
  <div class="flex gap-1.5 mb-4 overflow-x-auto">
    <button v-for="c in categories" :key="c.key" @click="activeCat = c.key"
      class="flex-shrink-0 inline-flex items-center gap-1 text-xs font-bold px-3 py-1.5 rounded-full transition-colors"
      :class="activeCat === c.key ? 'bg-amber-400 text-white shadow-btn' : 'bg-white text-ink-muted shadow-card hover:bg-gray-50'">
      <AppIcon :name="c.icon" :size="13" /> {{ c.label }} <span class="opacity-60 ml-1">{{ countByCategory(c.key) }}</span>
    </button>
  </div>

  <!-- 요약 통계 -->
  <div class="grid grid-cols-3 gap-2 mb-4">
    <div class="card p-3 text-center">
      <div class="text-2xl font-black text-green-600">{{ activeCount }}</div>
      <div class="text-xs text-ink-muted font-semibold mt-0.5">활성 게임</div>
    </div>
    <div class="card p-3 text-center">
      <div class="text-2xl font-black text-ink-muted">{{ inactiveCount }}</div>
      <div class="text-xs text-ink-muted font-semibold mt-0.5">비활성 게임</div>
    </div>
    <div class="card p-3 text-center">
      <div class="text-2xl font-black text-amber-600">{{ games.length }}</div>
      <div class="text-xs text-ink-muted font-semibold mt-0.5">전체</div>
    </div>
  </div>

  <!-- 게임 목록 -->
  <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
  <div v-else class="card overflow-hidden divide-y divide-gray-50">
    <div v-for="(g, idx) in filteredGames" :key="g.id"
      class="flex items-center gap-3 px-4 py-3 hover:bg-amber-50/40 transition-colors"
      :class="!g.is_active ? 'opacity-60' : ''">
      <div class="flex-shrink-0 w-8 text-center text-xs text-ink-faint font-mono">{{ idx + 1 }}</div>
      <div class="flex-shrink-0 w-10 h-10 bg-gray-50 rounded-lg flex items-center justify-center text-2xl">{{ g.icon }}</div>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-bold text-ink truncate">{{ g.name }}</div>
        <div class="text-[11px] text-ink-muted truncate">{{ g.description || g.slug }} · {{ g.path }}</div>
      </div>
      <span class="text-[11px] font-bold px-2 py-0.5 rounded-full flex-shrink-0" :class="catBadge(g.category)">{{ catLabel(g.category) }}</span>

      <!-- 순서 이동 (전체 탭에서만) -->
      <div class="flex flex-col gap-0.5 flex-shrink-0">
        <button @click="move(g, -1)" :disabled="idx === 0 || activeCat !== 'all'" class="text-ink-faint hover:text-amber-600 disabled:opacity-20 transition-colors"><AppIcon name="chevron-up" :size="12" /></button>
        <button @click="move(g, 1)" :disabled="idx === filteredGames.length - 1 || activeCat !== 'all'" class="text-ink-faint hover:text-amber-600 disabled:opacity-20 transition-colors"><AppIcon name="chevron-down" :size="12" /></button>
      </div>

      <!-- 설정 페이지 링크 -->
      <RouterLink :to="`/admin/games/settings/${g.slug}`" class="text-ink-muted hover:text-amber-600 flex-shrink-0 transition-colors" title="세부 설정"><AppIcon name="settings" :size="18" /></RouterLink>

      <!-- 활성 토글 -->
      <label class="flex-shrink-0 cursor-pointer relative inline-flex items-center">
        <input type="checkbox" :checked="g.is_active" @change="toggle(g)" class="sr-only peer" />
        <div class="w-9 h-5 bg-gray-300 peer-checked:bg-green-500 rounded-full transition-colors"></div>
        <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-4"></div>
      </label>
    </div>
    <div v-if="!filteredGames.length" class="py-16 text-center">
      <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="gamepad" :size="28" :stroke-width="1.5" /></div>
      <p class="text-sm text-ink-muted">게임이 없습니다</p>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const games = ref([])
const loading = ref(true)
const activeCat = ref('all')

const categories = [
  { key: 'all',       icon: 'gamepad',        label: '전체' },
  { key: 'card',      icon: 'grid',           label: '카드' },
  { key: 'brain',     icon: 'sparkles',       label: '두뇌' },
  { key: 'arcade',    icon: 'monitor',        label: '아케이드' },
  { key: 'word',      icon: 'edit',           label: '단어/퀴즈' },
  { key: 'education', icon: 'graduation-cap', label: '교육' },
]

const filteredGames = computed(() => {
  if (activeCat.value === 'all') return games.value
  return games.value.filter(g => g.category === activeCat.value)
})

const activeCount = computed(() => games.value.filter(g => g.is_active).length)
const inactiveCount = computed(() => games.value.filter(g => !g.is_active).length)

function countByCategory(key) {
  if (key === 'all') return games.value.length
  return games.value.filter(g => g.category === key).length
}

function catLabel(c) {
  return { card: '카드', brain: '두뇌', arcade: '아케이드', word: '단어', education: '교육' }[c] || c
}
function catBadge(c) {
  return {
    card:      'bg-purple-100 text-purple-700',
    brain:     'bg-blue-100 text-blue-700',
    arcade:    'bg-red-100 text-red-700',
    word:      'bg-green-100 text-green-700',
    education: 'bg-amber-100 text-amber-700',
  }[c] || 'bg-gray-100 text-ink-light'
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/games')
    games.value = data.data || []
  } catch {}
  loading.value = false
}

async function toggle(g) {
  const prev = g.is_active
  g.is_active = !g.is_active
  try { await axios.post(`/api/admin/games/${g.id}/toggle`) }
  catch { g.is_active = prev; alert('토글 실패') }
}

async function move(g, dir) {
  const list = [...games.value]
  const idx = list.findIndex(x => x.id === g.id)
  const newIdx = idx + dir
  if (newIdx < 0 || newIdx >= list.length) return
  const [item] = list.splice(idx, 1)
  list.splice(newIdx, 0, item)
  games.value = list
  try { await axios.post('/api/admin/games/reorder', { ids: list.map(x => x.id) }) }
  catch { alert('순서 저장 실패'); load() }
}

onMounted(load)
</script>
