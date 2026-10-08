<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <p class="text-[13px] text-ink-muted px-0.5">게임별 노출/숨김과 순서를 바꿔요. ⚙ 를 누르면 게임별 세부 설정으로 가요.</p>
  <div class="grid grid-cols-2 gap-2">
    <RouterLink to="/admin/poker" class="min-h-[50px] rounded-xl bg-white border border-gray-200 grid place-items-center text-[15px] font-bold text-ink">🃏 포커 토너먼트</RouterLink>
    <RouterLink to="/admin/pricing" class="min-h-[50px] rounded-xl bg-white border border-gray-200 grid place-items-center text-[15px] font-bold text-ink">🪙 포인트 설정</RouterLink>
  </div>
  <div class="grid grid-cols-3 gap-2">
    <div class="bg-white border border-gray-100 rounded-2xl p-3 text-center"><div class="text-[22px] font-black text-green-600">{{ activeCount }}</div><div class="text-[12px] text-ink-muted">활성</div></div>
    <div class="bg-white border border-gray-100 rounded-2xl p-3 text-center"><div class="text-[22px] font-black text-ink-muted">{{ inactiveCount }}</div><div class="text-[12px] text-ink-muted">비활성</div></div>
    <div class="bg-white border border-gray-100 rounded-2xl p-3 text-center"><div class="text-[22px] font-black text-amber-600">{{ games.length }}</div><div class="text-[12px] text-ink-muted">전체</div></div>
  </div>
  <div class="flex gap-2 overflow-x-auto scrollbar-hide" role="group" aria-label="게임 종류">
    <button v-for="c in categories" :key="c.key" @click="activeCat = c.key" :aria-pressed="activeCat === c.key"
      class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px]" :class="activeCat === c.key ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200 font-medium'">
      {{ c.label }} <span class="text-[13px]" :class="activeCat === c.key ? 'text-white/80' : 'text-ink-muted'">{{ countByCategory(c.key) }}</span></button>
  </div>
  <p v-if="activeCat !== 'all'" class="text-[12px] text-ink-faint px-0.5">순서 변경은 “전체” 탭에서만 할 수 있어요.</p>
  <div v-if="loading" class="text-center py-10 text-ink-muted text-[15px]">불러오는 중...</div>
  <div v-else-if="!filteredGames.length" class="text-center py-12 text-ink-muted text-[15px]">게임이 없어요.</div>
  <div v-else class="space-y-2">
    <div v-for="(g, idx) in filteredGames" :key="g.id" class="bg-white border border-gray-100 rounded-2xl p-3 flex items-center gap-2.5" :class="!g.is_active ? 'opacity-60' : ''">
      <div class="shrink-0 w-11 h-11 bg-gray-50 rounded-xl grid place-items-center text-[26px]" aria-hidden="true">{{ g.icon }}</div>
      <div class="min-w-0 flex-1">
        <div class="text-[16px] font-bold text-ink truncate">{{ g.name }}</div>
        <div class="flex items-center gap-1.5 mt-0.5">
          <span class="text-[11px] font-bold px-2 py-0.5 rounded-full" :class="catBadge(g.category)">{{ catLabel(g.category) }}</span>
          <span class="text-[12px] text-ink-muted truncate">{{ g.description || g.slug }}</span>
        </div>
      </div>
      <div v-if="activeCat === 'all'" class="shrink-0 flex flex-col">
        <button @click="move(g, -1)" :disabled="idx === 0" class="min-h-[28px] min-w-[44px] text-ink-muted disabled:opacity-20 text-[14px]" :aria-label="`${g.name} 위로`">▲</button>
        <button @click="move(g, 1)" :disabled="idx === filteredGames.length - 1" class="min-h-[28px] min-w-[44px] text-ink-muted disabled:opacity-20 text-[14px]" :aria-label="`${g.name} 아래로`">▼</button>
      </div>
      <RouterLink :to="`/admin/games/settings/${g.slug}`" class="shrink-0 min-h-[44px] min-w-[44px] grid place-items-center rounded-xl bg-gray-50 text-ink-light" :aria-label="`${g.name} 세부 설정`"><AppIcon name="settings" :size="20" /></RouterLink>
      <button @click="toggle(g)" role="switch" :aria-checked="!!g.is_active" :aria-label="`${g.name} 노출`" class="shrink-0 relative w-[52px] h-[32px] rounded-full transition-colors" :class="g.is_active ? 'bg-green-500' : 'bg-gray-300'">
        <span class="absolute top-[3px] left-[3px] w-[26px] h-[26px] bg-white rounded-full shadow transition-transform" :class="g.is_active ? 'translate-x-5' : ''"></span>
      </button>
    </div>
  </div>
  <Teleport to="body">
    <div v-if="toast" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg" :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
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
        <div class="text-sm font-bold text-ink truncate flex items-center gap-1.5">
          {{ g.name }}
          <NewFeatureBadge v-if="g.slug === 'global'" desc="개별 게임에 설정이 없을 때 쓰이는 전역 기본값(게임당 지급 포인트 등)을 여기서 편집할 수 있습니다." />
        </div>
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
import { ref, computed, inject, onMounted, onBeforeUnmount } from 'vue'
import { RouterLink } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import NewFeatureBadge from '../../components/NewFeatureBadge.vue'

// 관리자 휴대폰 화면이면 카드 + 큰 스위치 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const toast = ref(null); let toastTimer = null
function say(text, error = false) { toast.value = { text, error }; clearTimeout(toastTimer); toastTimer = setTimeout(() => { toast.value = null }, 3000) }
onBeforeUnmount(() => clearTimeout(toastTimer))
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
  catch { g.is_active = prev; if (isMobile.value) say('바꾸지 못했어요', true); else alert('토글 실패') }
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
  catch { if (isMobile.value) say('순서를 저장하지 못했어요', true); else alert('순서 저장 실패'); load() }
}

onMounted(load)
</script>

<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
