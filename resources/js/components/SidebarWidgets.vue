<template>
<div class="space-y-3">
  <!-- ══ 상세 모드: 같은 카테고리 글 목록 ══ -->
  <template v-if="mode === 'detail'">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <!-- 카테고리 헤더 -->
      <div v-if="displayCategoryLabel" class="px-3 py-2.5 border-b bg-amber-50">
        <span class="text-xs font-bold text-amber-800">📁 {{ displayCategoryLabel }}</span>
        <span v-if="detailTotal" class="text-[10px] text-amber-600 ml-1">{{ detailTotal }}건</span>
      </div>
      <div class="py-1">
        <component v-for="(item, i) in detailItems" :key="item.id"
          :is="inline ? 'button' : 'RouterLink'" :to="inline ? undefined : detailPath + item.id"
          @click="inline && emit('select', item)"
          class="flex items-start gap-2 px-3 py-2 transition w-full text-left"
          :class="item.id == currentId ? 'bg-amber-50 border-l-2 border-amber-400' : 'hover:bg-amber-50/50'">
          <span class="text-xs font-bold flex-shrink-0 w-5 text-center"
            :class="item.id == currentId ? 'text-amber-600' : 'text-gray-400'">{{ (detailPage - 1) * 10 + i + 1 }}</span>
          <div class="min-w-0 flex-1">
            <span class="text-xs leading-snug line-clamp-2"
              :class="item.id == currentId ? 'text-amber-700 font-bold' : 'text-gray-700'">{{ item.title || item.name }}</span>
            <div v-if="item.city || item.user?.name" class="text-[10px] text-gray-400 mt-0.5 truncate">{{ item.city || item.user?.name || '' }}</div>
          </div>
        </component>
        <div v-if="tabLoading" class="py-4 text-center text-xs text-gray-400">로딩중...</div>
        <div v-else-if="!detailItems.length" class="py-4 text-center text-xs text-gray-400">데이터가 없습니다</div>
      </div>
      <!-- 페이지네이션 -->
      <div v-if="detailLastPage > 1" class="px-3 py-2 border-t flex justify-center items-center gap-1">
        <button @click="loadDetail(1)" :disabled="detailPage<=1" class="text-[10px] text-gray-400 hover:text-amber-700 disabled:opacity-30 px-1">«</button>
        <button @click="loadDetail(detailPage-1)" :disabled="detailPage<=1" class="text-[10px] text-gray-400 hover:text-amber-700 disabled:opacity-30 px-1">‹</button>
        <button v-for="pg in detailVisiblePages" :key="pg" @click="loadDetail(pg)"
          class="w-6 h-6 rounded text-[10px] font-bold" :class="pg===detailPage?'bg-amber-400 text-amber-900':'text-gray-400 hover:bg-amber-50'">{{ pg }}</button>
        <button @click="loadDetail(detailPage+1)" :disabled="detailPage>=detailLastPage" class="text-[10px] text-gray-400 hover:text-amber-700 disabled:opacity-30 px-1">›</button>
        <button @click="loadDetail(detailLastPage)" :disabled="detailPage>=detailLastPage" class="text-[10px] text-gray-400 hover:text-amber-700 disabled:opacity-30 px-1">»</button>
      </div>
    </div>
  </template>

  <!-- ══ 리스트 모드 (기존 + 카테고리 지원) ══ -->
  <template v-else>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="flex border-b">
        <button @click="popTab='views'; loadTab('views')" class="flex-1 py-2.5 text-xs font-bold transition"
          :class="popTab==='views' ? 'text-amber-700 border-b-2 border-amber-500 bg-amber-50' : 'text-gray-400'">많이 본 {{ label }}</button>
        <button @click="popTab='second'; loadTab('second')" class="flex-1 py-2.5 text-xs font-bold transition"
          :class="popTab==='second' ? 'text-amber-700 border-b-2 border-amber-500 bg-amber-50' : 'text-gray-400'">{{ secondTab?.label || '최신 ' + label }}</button>
      </div>
      <div class="py-1">
        <component v-for="(item, i) in currentItems" :key="item.id"
          :is="inline ? 'button' : 'RouterLink'" :to="inline ? undefined : detailPath + item.id"
          @click="inline && emit('select', item)"
          class="flex items-start gap-2 px-3 py-2 hover:bg-amber-50/50 transition w-full text-left">
          <span class="text-xs font-bold flex-shrink-0 w-5 text-center" :class="(currentPage - 1) * 10 + i < 3 ? 'text-amber-600' : 'text-gray-400'">{{ (currentPage - 1) * 10 + i + 1 }}</span>
          <span class="text-xs text-gray-700 leading-snug line-clamp-2">{{ item.title || item.name }}</span>
        </component>
        <div v-if="tabLoading" class="py-4 text-center text-xs text-gray-400">로딩중...</div>
        <div v-else-if="!currentItems.length" class="py-4 text-center text-xs text-gray-400">데이터가 없습니다</div>
      </div>
      <div v-if="currentLastPage > 1" class="px-3 py-2 border-t flex justify-center items-center gap-1">
        <button @click="goPage(1)" :disabled="currentPage<=1" class="text-[10px] text-gray-400 hover:text-amber-700 disabled:opacity-30 px-1">«</button>
        <button @click="goPage(currentPage-1)" :disabled="currentPage<=1" class="text-[10px] text-gray-400 hover:text-amber-700 disabled:opacity-30 px-1">‹</button>
        <button v-for="pg in visiblePages" :key="pg" @click="goPage(pg)"
          class="w-6 h-6 rounded text-[10px] font-bold" :class="pg===currentPage?'bg-amber-400 text-amber-900':'text-gray-400 hover:bg-amber-50'">{{ pg }}</button>
        <button @click="goPage(currentPage+1)" :disabled="currentPage>=currentLastPage" class="text-[10px] text-gray-400 hover:text-amber-700 disabled:opacity-30 px-1">›</button>
        <button @click="goPage(currentLastPage)" :disabled="currentPage>=currentLastPage" class="text-[10px] text-gray-400 hover:text-amber-700 disabled:opacity-30 px-1">»</button>
      </div>
    </div>

    <!-- 추천 글 -->
    <div v-if="recommendLabel && recommendItems.length" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="px-3 py-2.5 border-b font-bold text-xs text-gray-800">👍 {{ recommendLabel }}</div>
      <div class="py-1">
        <component v-for="item in recommendItems" :key="item.id"
          :is="inline ? 'button' : 'RouterLink'" :to="inline ? undefined : detailPath + item.id"
          @click="inline && emit('select', item)"
          class="block px-3 py-2 hover:bg-amber-50/50 transition w-full text-left">
          <div class="text-xs text-gray-700 line-clamp-2 leading-snug">{{ item.title || item.name }}</div>
          <div class="text-[10px] text-gray-400 mt-0.5">{{ item.user?.name || item.company || item.source || '' }}</div>
        </component>
      </div>
    </div>

    <!-- 실시간/바로가기 -->
    <div v-if="quickLabel && quickItems.length" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="px-3 py-2.5 border-b font-bold text-xs text-gray-800">⚡ {{ quickLabel }}</div>
      <div class="py-1">
        <component v-for="item in quickItems" :key="item.id"
          :is="inline ? 'button' : 'RouterLink'" :to="inline ? undefined : detailPath + item.id"
          @click="inline && emit('select', item)"
          class="flex items-center gap-2 px-3 py-1.5 hover:bg-amber-50/50 transition w-full text-left">
          <span class="text-[10px] text-amber-500">●</span>
          <span class="text-xs text-gray-600 truncate">{{ item.title || item.name }}</span>
        </component>
      </div>
    </div>
  </template>

  <!-- 빠른 링크 (공통) -->
  <div v-if="links.length" class="bg-white rounded-xl shadow-sm border border-gray-100 p-3">
    <div class="font-bold text-xs text-gray-800 mb-2">📋 바로가기</div>
    <RouterLink v-for="link in links" :key="link.to" :to="link.to"
      class="block text-xs text-gray-600 hover:text-amber-700 py-1 transition">
      {{ link.icon }} {{ link.label }}
    </RouterLink>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'

const _cache = new Map()
const CACHE_TTL = 300000

async function cachedGet(url, params) {
  const key = url + '|' + JSON.stringify(params)
  const cached = _cache.get(key)
  if (cached && Date.now() - cached.ts < CACHE_TTL) return cached.data
  const { data } = await axios.get(url, { params })
  _cache.set(key, { data, ts: Date.now() })
  return data
}

const props = defineProps({
  apiUrl: { type: String, required: true },
  detailPath: { type: String, required: true },
  currentId: { type: [Number, String], default: 0 },
  label: { type: String, default: '글' },
  recommendLabel: { type: String, default: '' },
  quickLabel: { type: String, default: '' },
  links: { type: Array, default: () => [] },
  filterParams: { type: Object, default: () => ({}) },
  inline: { type: Boolean, default: false },
  secondTab: { type: Object, default: null },
  // 새 props (하위호환: 없으면 기존 동작 그대로)
  currentCategory: { type: String, default: '' },
  categoryLabel: { type: String, default: '' },
  mode: { type: String, default: 'list' }, // 'list' | 'detail'
  // 부모에서 미리 로드한 데이터 (있으면 자체 API 호출 안 함)
  preloadedPopular: { type: Array, default: null },
  preloadedLatest: { type: Array, default: null },
  usePageData: { type: Boolean, default: false }, // true면 preloaded 대기, 자체 API 안 함
  categoryParam: { type: String, default: 'category' }, // API 파라미터명 (property_type, category_id 등)
})

const emit = defineEmits(['select'])

const displayCategoryLabel = computed(() => {
  if (props.categoryLabel) return props.categoryLabel
  if (props.currentCategory) return props.currentCategory
  return ''
})

// ── 리스트 모드 데이터 ──
const popTab = ref('views')
const tabLoading = ref(false)
const viewsItems = ref([])
const viewsPage = ref(1)
const viewsLastPage = ref(1)
const secondItemsData = ref([])
const secondPage = ref(1)
const secondLastPage = ref(1)
const recommendItems = ref([])
const quickItems = ref([])

const currentItems = computed(() => popTab.value === 'views' ? viewsItems.value : secondItemsData.value)
const currentPage = computed(() => popTab.value === 'views' ? viewsPage.value : secondPage.value)
const currentLastPage = computed(() => popTab.value === 'views' ? viewsLastPage.value : secondLastPage.value)

const visiblePages = computed(() => {
  const p = currentPage.value, last = currentLastPage.value
  const start = Math.max(1, p - 2), end = Math.min(last, start + 4)
  const pages = []
  for (let i = Math.max(1, end - 4); i <= end; i++) pages.push(i)
  return pages
})

// ── 상세 모드 데이터 ──
const detailItems = ref([])
const detailPage = ref(1)
const detailLastPage = ref(1)
const detailTotal = ref(0)

const detailVisiblePages = computed(() => {
  const p = detailPage.value, last = detailLastPage.value
  const start = Math.max(1, p - 2), end = Math.min(last, start + 4)
  const pages = []
  for (let i = Math.max(1, end - 4); i <= end; i++) pages.push(i)
  return pages
})

// ── API 호출 헬퍼: 카테고리 파라미터 자동 포함 ──
function buildParams(extra = {}) {
  const params = { ...props.filterParams, ...extra }
  if (props.currentCategory) params[props.categoryParam] = props.currentCategory
  return params
}

// ── 리스트 모드 로드 ──
async function loadTab(tab, page = 1) {
  tabLoading.value = true
  let sort = 'latest'
  if (tab === 'views') sort = 'popular'
  else if (props.secondTab?.sort) sort = props.secondTab.sort
  try {
    const data = await cachedGet(props.apiUrl, buildParams({ sort, per_page: 10, page }))
    const items = (data.data?.data || []).filter(i => i.id !== Number(props.currentId)).slice(0, 10)
    const totalItems = data.data?.total || items.length
    const lastPg = Math.ceil(totalItems / 10) || 1
    if (tab === 'views') { viewsItems.value = items; viewsPage.value = page; viewsLastPage.value = lastPg }
    else { secondItemsData.value = items; secondPage.value = page; secondLastPage.value = lastPg }
  } catch {}
  tabLoading.value = false
}

function goPage(pg) {
  if (pg < 1 || pg > currentLastPage.value) return
  loadTab(popTab.value, pg)
}

// ── 상세 모드 로드 ──
async function loadDetail(page = 1) {
  tabLoading.value = true
  try {
    const data = await cachedGet(props.apiUrl, buildParams({ sort: 'newest', per_page: 10, page }))
    detailItems.value = data.data?.data || []
    detailTotal.value = data.data?.total || 0
    detailPage.value = data.data?.current_page || page
    detailLastPage.value = data.data?.last_page || 1
  } catch {}
  tabLoading.value = false
}

// ── preloaded 데이터 변경 watch ──
watch(() => props.preloadedPopular, (v) => {
  if (v && props.mode !== 'detail') {
    viewsItems.value = v.filter(i => i.id !== Number(props.currentId)).slice(0, 10)
  }
})
watch(() => props.preloadedLatest, (v) => {
  if (v && props.mode !== 'detail') {
    secondItemsData.value = v.filter(i => i.id !== Number(props.currentId)).slice(0, 10)
    quickItems.value = secondItemsData.value.slice(0, 6)
  }
})

// ── 카테고리 변경 watch ──
watch(() => props.currentCategory, () => {
  // 캐시 무효화 (카테고리 바뀌면 다른 결과)
  if (props.mode === 'detail') {
    loadDetail(1)
  } else {
    loadTab('views', 1)
    loadTab('second', 1)
  }
})

// filterParams 변경 시 리로드 (위치 변경 등)
watch(() => props.filterParams, () => {
  if (props.mode === 'detail') loadDetail(1)
  else { loadTab('views', 1); loadTab('second', 1) }
}, { deep: true })

onMounted(async () => {
  if (props.mode === 'detail') {
    await loadDetail(1)
    return
  }

  if (props.usePageData) {
    // 부모가 page-data로 사이드바 데이터를 제공할 예정 → 자체 API 호출 안 함
    // watch에서 preloadedPopular/preloadedLatest 변경을 감지하여 반영
    if (props.preloadedPopular) {
      viewsItems.value = props.preloadedPopular.filter(i => i.id !== Number(props.currentId)).slice(0, 10)
    }
    if (props.preloadedLatest) {
      secondItemsData.value = props.preloadedLatest.filter(i => i.id !== Number(props.currentId)).slice(0, 10)
      quickItems.value = secondItemsData.value.slice(0, 6)
    }
  } else {
    // 기존 방식: 자체 API 호출
    await Promise.allSettled([loadTab('views', 1), loadTab('second', 1)])
    if (props.recommendLabel) {
      try {
        const data = await cachedGet(props.apiUrl, buildParams({ per_page: 5 }))
        recommendItems.value = (data.data?.data || []).filter(i => i.id !== Number(props.currentId)).slice(0, 5)
      } catch {}
    }
    quickItems.value = secondItemsData.value.slice(0, 6)
  }
})
</script>
