<template>
<div class="min-h-screen">
  <div class="max-w-5xl mx-auto px-4 py-5">
    <!-- 헤더 -->
    <div class="flex items-center justify-between flex-wrap gap-2 mb-4">
      <div>
        <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
          <span class="icon-chip w-9 h-9 bg-rose-50 text-rose-600 text-base font-black">N</span>
          NEW <span class="text-sm font-semibold text-ink-muted">신장개업 · 폐업정리 · 우리 동네 새 소식</span>
        </h1>
      </div>
    </div>

    <!-- 지역 선택 -->
    <div class="flex items-center gap-2 flex-wrap mb-4">
      <button @click="setScope('national')" class="px-3.5 py-1.5 rounded-full text-sm font-bold border transition-colors"
        :class="scope === 'national' ? 'bg-ink text-white border-ink' : 'bg-white text-ink-light border-gray-200 hover:border-gray-300'">🇺🇸 전국</button>
      <button @click="setScope('state')" class="px-3.5 py-1.5 rounded-full text-sm font-bold border transition-colors"
        :class="scope === 'state' ? 'bg-ink text-white border-ink' : 'bg-white text-ink-light border-gray-200 hover:border-gray-300'">📍 내 지역</button>
      <select v-if="scope === 'state'" v-model="state" @change="load" class="input-soft w-auto pl-2.5 pr-8 py-1.5 text-sm font-semibold">
        <option v-for="s in US_STATES" :key="s.code" :value="s.code">{{ s.name }} ({{ s.code }})</option>
      </select>
      <span v-if="tz" class="text-xs text-ink-faint ml-auto">방송 시간은 {{ tzLabel(tz) }} 현지 시각 기준</span>
    </div>

    <div v-if="loading" class="text-center py-16 text-ink-muted">로딩중...</div>
    <template v-else>
      <!-- 지금 방송 중 (상단 전단) -->
      <section class="mb-8">
        <div v-if="featured" class="card overflow-hidden border-2 border-rose-200">
          <div class="px-4 py-2.5 bg-rose-50 flex items-center gap-2 flex-wrap">
            <span class="inline-flex items-center gap-1 text-xs font-black text-white bg-rose-500 px-2 py-0.5 rounded-full"><span class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></span>지금 방송 중</span>
            <span class="text-xs font-bold text-rose-700">{{ fmtHour(featured.live_hour) }} ~ {{ fmtHour(featured.live_hour + 1) }}</span>
            <span v-if="featured.source === 'national' && scope === 'state'" class="text-[11px] text-rose-600">· 이 시간 우리 지역 광고가 없어 전국 광고를 보여드려요</span>
          </div>
          <RouterLink :to="`/new/${featured.id}`" class="block bg-gray-50">
            <img :src="featured.image_url" :alt="featured.title" class="w-full max-h-[78vh] object-contain mx-auto" />
          </RouterLink>
          <div class="px-4 py-3 flex items-center justify-between gap-3 flex-wrap">
            <div class="min-w-0">
              <div class="text-xs font-bold text-rose-600 mb-0.5">{{ kindLabel(featured.kind) }}</div>
              <div class="font-bold text-ink truncate">{{ featured.title }}</div>
              <div v-if="featured.description" class="text-xs text-ink-muted mt-0.5 line-clamp-2">{{ featured.description }}</div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <a v-if="featured.phone" :href="`tel:${featured.phone}`" class="btn-secondary px-3 py-1.5 rounded-lg text-xs"><AppIcon name="phone" :size="12" />{{ featured.phone }}</a>
              <RouterLink :to="`/new/${featured.id}`" class="btn-primary px-3 py-1.5 rounded-lg text-xs">자세히 보기</RouterLink>
            </div>
          </div>
        </div>

        <!-- 이 시간대 광고가 비어 있을 때 -->
        <div v-else class="rounded-2xl border-2 border-dashed border-gray-200 bg-white py-12 px-5 text-center">
          <div class="text-3xl mb-2">📣</div>
          <div class="font-bold text-ink">지금 이 시간 광고 자리가 비어 있어요</div>
          <p class="text-xs text-ink-muted mt-1">새로 문 연 가게, 폐업 정리 세일 소식이 이 자리에 나와요.</p>
          <RouterLink to="/dashboard?tab=flyer" class="inline-block text-[11px] text-ink-faint underline mt-3 hover:text-rose-600">광고 신청: 마이페이지 → NEW 전면광고 신청</RouterLink>
        </div>
      </section>

      <!-- 방송 예정 전단 -->
      <section>
        <h2 class="flex items-center gap-1.5 font-bold text-ink text-sm mb-3"><AppIcon name="calendar" :size="14" class="text-rose-500" />오늘 · 앞으로 방송 예정</h2>
        <div v-if="!list.length" class="text-center py-10 text-sm text-ink-faint">예정된 전단이 아직 없어요</div>
        <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
          <RouterLink v-for="f in list" :key="f.id" :to="`/new/${f.id}`" class="card card-hover overflow-hidden block">
            <div class="relative aspect-[3/4] bg-gray-100">
              <img :src="f.image_url" :alt="f.title" loading="lazy" class="w-full h-full object-cover" />
              <span v-if="f.live" class="absolute top-2 left-2 text-[10px] font-black text-white bg-rose-500 px-1.5 py-0.5 rounded-full">LIVE</span>
              <span v-if="f.scope === 'national'" class="absolute top-2 right-2 text-[10px] font-bold text-white bg-blue-500 px-1.5 py-0.5 rounded-full">전국</span>
            </div>
            <div class="p-2.5">
              <div class="text-[11px] font-bold text-rose-600">{{ kindLabel(f.kind) }}</div>
              <div class="text-sm font-semibold text-ink truncate">{{ f.title }}</div>
              <div class="text-[11px] text-ink-muted mt-0.5 truncate">
                <template v-if="f.today_hours.length">오늘 {{ hourRanges(f.today_hours).join(', ') }}</template>
                <template v-else>{{ fmtDay(f.next_slot.date) }} {{ fmtHour(f.next_slot.hour) }}~</template>
              </div>
            </div>
          </RouterLink>
        </div>
      </section>
    </template>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useLocation } from '../../composables/useLocation'
import AppIcon from '../../components/AppIcon.vue'
import { US_STATES, kindLabel, fmtHour, hourRanges, fmtDay, tzLabel } from '../../utils/flyer'

const auth = useAuthStore()
const { city, init: initLocation } = useLocation()

const scope = ref('national')
const state = ref('GA')
const loading = ref(true)
const featured = ref(null)
const list = ref([])
const tz = ref('')
let timer = null
const viewed = new Set()

function setScope(s) { scope.value = s; load() }

async function load() {
  try {
    const { data } = await axios.get('/api/flyers', { params: { scope: scope.value, state: scope.value === 'state' ? state.value : undefined } })
    featured.value = data.data.featured
    list.value = data.data.list || []
    tz.value = data.data.tz
    // 같은 전단은 한 번 방문당 한 번만 노출 집계
    if (featured.value && !viewed.has(featured.value.id)) {
      viewed.add(featured.value.id)
      axios.post(`/api/flyers/${featured.value.id}/view`).catch(() => {})
    }
  } catch {}
  loading.value = false
}

onMounted(async () => {
  initLocation()
  const myState = (city.value?.state || auth.user?.state || '').toUpperCase()
  if (US_STATES.some(s => s.code === myState)) {
    state.value = myState
    scope.value = 'state'
  }
  await load()
  // 정각이 지나면 다음 시간대 전단으로 바뀌므로 1분마다 갱신
  timer = setInterval(() => { if (!document.hidden) load() }, 60000)
})
onBeforeUnmount(() => clearInterval(timer))
</script>
