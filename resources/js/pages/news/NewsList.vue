<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더: 모바일 -->
    <div class="lg:hidden mb-3">
      <div class="flex items-center justify-between mb-2">
        <h1 class="text-lg font-black text-gray-800">📰 뉴스</h1>
        <div class="flex items-center gap-2">
          <button @click="showFilter = true" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold px-3 py-2 rounded-lg">🔍 필터</button>
        </div>
      </div>
      <div class="flex items-center gap-1.5 overflow-x-auto">
        <span v-if="searchQ" class="text-[10px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap">
          "{{ searchQ }}"
        </span>
      </div>
    </div>

    <!-- 모바일 필터 바텀시트 -->
    <MobileFilter v-model="showFilter" @apply="loadNews()" @reset="activeCat = null; searchQ = ''; loadNews()">
      <div class="mb-4">
        <label class="text-xs font-bold text-gray-600 mb-2 block">검색어</label>
        <input v-model="searchQ" type="text" placeholder="뉴스 검색..."
          class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-amber-400" />
      </div>
      <div>
        <label class="text-xs font-bold text-gray-600 mb-2 block">카테고리</label>
        <div class="grid grid-cols-3 gap-1.5">
          <button @click="activeCat = null"
            class="text-xs py-2 rounded-lg font-semibold border transition"
            :class="!activeCat ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
            전체
          </button>
          <button v-for="c in categories" :key="c.id" @click="activeCat = c"
            class="text-xs py-2 rounded-lg font-semibold border transition"
            :class="activeCat?.id === c.id ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
            {{ c.name }}
          </button>
        </div>
      </div>
    </MobileFilter>

    <!-- 헤더: 데스크탑 -->
    <div class="hidden lg:flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="text-xl font-black text-gray-800">📰 뉴스</h1>
      <form @submit.prevent="loadNews()" class="flex gap-1">
        <input v-model="searchQ" type="text" placeholder="뉴스 검색..." class="border rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-amber-400 outline-none w-40" />
        <button type="submit" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500">검색</button>
      </form>
    </div>

    <div class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 카테고리 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto space-y-3 pr-0.5">
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 카테고리</div>
            <button @click="showFavorites=false; activeCat=null; activeItem=null; loadNews()" class="w-full text-left px-3 py-2 text-xs transition"
              :class="!showFavorites && !activeCat ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">전체</button>
            <button v-for="cat in categories" :key="cat.id" @click="showFavorites=false; activeCat=cat; activeItem=null; loadNews()"
              class="w-full text-left px-3 py-2 text-xs transition"
              :class="!showFavorites && activeCat?.id===cat.id ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">{{ cat.name }}</button>
            <button v-if="auth.isLoggedIn" @click="showFavorites=true; activeItem=null; loadFavoritesPage()"
              class="w-full text-left px-3 py-2 text-xs transition border-t"
              :class="showFavorites ? 'bg-red-50 text-red-600 font-bold' : 'text-gray-600 hover:bg-red-50/50'">
              🔖 내 북마크<span v-if="favCount > 0" class="ml-0.5">({{ favCount }})</span>
            </button>
          </div>
          <AdSlot page="news" position="left" :maxSlots="2" />
        </div>
      </div>

      <!-- 메인: 뉴스 목록 -->
      <div class="col-span-12 lg:col-span-7">

        <div class="mb-2">
          <span v-if="showFavorites" class="font-bold text-red-600 text-sm">🔖 내 북마크</span>
          <template v-else>
            <span class="font-bold text-amber-700 text-sm">{{ activeCat ? activeCat.name : '전체' }}</span>
            <span v-if="!activeCat" class="text-xs text-gray-400 ml-2">모든 뉴스를 볼 수 있습니다</span>
          </template>
        </div>

        <!-- ═══ 상세 모드 ═══ -->
        <div v-if="activeItem">
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4">
              <div class="flex items-center gap-2 mb-2">
                <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">{{ activeItem.category?.name || '뉴스' }}</span>
                <span class="text-xs text-gray-400">{{ activeItem.source }}</span>
              </div>
              <h2 class="text-lg font-bold text-gray-900 leading-snug">{{ activeItem.title }}</h2>
              <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
                <span>{{ formatDate(activeItem.published_at) }}</span>
                <span>👁 {{ activeItem.view_count }}회</span>
                <BookmarkToggle v-if="auth.isLoggedIn" :active="favorited.has(activeItem.id)" @toggle="toggleFav(activeItem)" size="lg" class="ml-auto" />
              </div>
            </div>
            <!-- 대표 이미지 (본문에 이미지가 없을 때만) -->
            <div v-if="activeItem.image_url && !contentBlocks.some(b => b.type==='img')" class="px-5 pb-3">
              <img :src="activeItem.image_url"
                class="block mx-auto rounded-lg"
                style="max-width: 100%; width: auto; height: auto; max-height: 480px;"
                @error="e=>e.target.style.display='none'" />
            </div>
            <!-- 본문 (단락 구분 + 이미지) -->
            <div class="px-5 py-5 border-t text-sm text-gray-700 leading-7">
              <template v-for="(block, i) in contentBlocks" :key="i">
                <!-- 이미지: 원본 사이즈 유지 (작은 이미지는 작게, 큰 이미지는 컨테이너 너비로 제한) -->
                <img v-if="block.type==='img'" :src="block.src"
                  class="block mx-auto rounded-lg my-4"
                  style="max-width: 100%; width: auto; height: auto;"
                  @error="e=>e.target.style.display='none'" />
                <p v-else class="mb-5 leading-relaxed" style="text-indent: 0.5em;">{{ block.text }}</p>
              </template>
              <!-- 짧은 본문일 때 안내 -->
              <div v-if="(activeItem.content || '').length < 600 && activeItem.source_url"
                class="mt-2 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
                ⓘ 본문 일부만 표시됩니다. 전체 기사는 아래 <b>원문 보기</b> 링크에서 확인하세요.
              </div>

              <!-- English Original (TIME 기사만) -->
              <div v-if="activeItem.content_en" class="mt-6 border-t pt-4">
                <div class="flex items-center gap-2 mb-3">
                  <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-semibold">English Original</span>
                  <button @click="showEnglish = !showEnglish" class="text-xs text-gray-500 hover:text-amber-600 underline">
                    {{ showEnglish ? '접기' : '펼치기' }}
                  </button>
                </div>
                <div v-if="showEnglish" class="text-sm text-gray-600 leading-7">
                  <h3 v-if="activeItem.title_en" class="font-bold text-gray-800 mb-3">{{ activeItem.title_en }}</h3>
                  <template v-for="(block, i) in englishContentBlocks" :key="'en-'+i">
                    <img v-if="block.type==='img'" :src="block.src"
                      class="block mx-auto rounded-lg my-4"
                      style="max-width: 100%; width: auto; height: auto;"
                      @error="e=>e.target.style.display='none'" />
                    <p v-else class="mb-3 text-gray-500 italic" style="text-indent: 0.5em;">{{ block.text }}</p>
                  </template>
                </div>
              </div>
            </div>
            <div v-if="activeItem.source_url" class="px-5 py-3 border-t bg-amber-50/30">
              <a :href="activeItem.source_url" target="_blank"
                class="inline-flex items-center gap-1.5 bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm hover:bg-amber-500 transition">
                📎 원문 전체 보기 →
              </a>
            </div>
          </div>
          <div class="flex justify-between mt-3">
            <button @click="navItem(-1)" :disabled="currentIdx <= 0" class="text-xs text-gray-500 hover:text-amber-700 disabled:opacity-30">← 이전글</button>
            <button @click="activeItem=null" class="text-xs text-gray-400 hover:text-gray-600">목록</button>
            <button @click="navItem(1)" :disabled="currentIdx >= items.length-1" class="text-xs text-gray-500 hover:text-amber-700 disabled:opacity-30">다음글 →</button>
          </div>
        </div>

        <!-- ═══ 목록 모드 ═══ -->
        <div v-else>
        <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
        <div v-else-if="!items.length" class="text-center py-12 text-gray-400">뉴스가 없습니다</div>
        <div v-else class="space-y-2">
          <template v-for="(item, i) in items" :key="item.id">
          <div @click="openItem(item)"
            class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md hover:border-amber-300 transition cursor-pointer">
            <div class="flex gap-3 p-3">
              <!-- 썸네일 (항상 표시: 이미지 있으면 이미지, 없으면 이모지) -->
              <div class="w-24 h-16 bg-gradient-to-br from-amber-50 to-amber-100 rounded-lg overflow-hidden flex-shrink-0 flex items-center justify-center">
                <img v-if="item.thumbnail_url || item.image_url"
                  :src="item.thumbnail_url || thumb(item.image_url, 200)"
                  loading="lazy" decoding="async"
                  class="w-full h-full object-cover"
                  @error="e => { e.target.style.display='none'; e.target.parentElement.innerHTML='<span class=\'text-2xl\'>📰</span>' }" />
                <span v-else class="text-2xl">📰</span>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 mb-1">
                  <span v-if="!activeCat" class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-semibold">{{ item.category?.name || '뉴스' }}</span>
                  <span v-if="item.source?.startsWith('TIME')" class="text-[10px] bg-blue-100 text-blue-700 px-1 py-0.5 rounded-full font-bold">EN→KO</span>
                  <span class="text-[10px] text-gray-400">{{ item.source }}</span>
                </div>
                <div class="text-sm font-medium text-gray-800 line-clamp-2 leading-snug">{{ item.title }}</div>
                <div class="flex items-center gap-2 mt-1">
                  <span class="text-[10px] text-gray-400">👁 {{ item.view_count }} · {{ formatDate(item.published_at) }}</span>
                  <BookmarkToggle v-if="auth.isLoggedIn" :active="favorited.has(item.id)" @toggle="toggleFav(item)" size="sm" class="ml-auto" />
                </div>
              </div>
            </div>
          </div>
          <MobileAdInline v-if="i === 4" page="news" />
          </template>
        </div>

        <Pagination v-if="!activeItem" :page="page" :lastPage="lastPage" @page="loadNews" />
        </div>
      </div>

      <!-- 오른쪽: 위젯 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block">
        <SidebarWidgets :currentCategory="activeItem ? (activeItem.category_id || '') : (activeCat?.id || '')" categoryParam="category_id" :inline="true" @select="openItem" api-url="/api/news" detail-path="/news/" :current-id="activeItem?.id || 0"
          :mode="activeItem ? 'detail' : 'list'" :categoryLabel="activeItem?.category?.name || activeCat?.name || ''" label="뉴스" />
        <AdSlot page="news" position="right" :maxSlots="2" />
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { useRoute } from 'vue-router'
import { ref, computed, watch, onMounted } from 'vue'

import { useAuthStore } from '../../stores/auth'
import { useBookmarkStore } from '../../stores/bookmarks'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import { thumb } from '../../utils/thumb'
import axios from 'axios'
import AdSlot from '../../components/AdSlot.vue'
import BookmarkToggle from '../../components/BookmarkToggle.vue'

const auth = useAuthStore()
const bStore = useBookmarkStore()
const BM_TYPE = 'App\\Models\\News'
const route = useRoute()
const showFilter = ref(false)
const showFavorites = ref(false)
const favorited = ref(new Set())
const favCount = computed(() => bStore.getBookmarkedIds(BM_TYPE).length)
const items = ref([])
const categories = ref([])
const activeCat = ref(null)
const activeItem = ref(null)
const searchQ = ref('')
const showEnglish = ref(false)

// 마크다운 이미지 + 단락 분리 공유 함수
function parseContentToBlocks(content) {
  if (!content) return []
  const parts = content.split(/!\[.*?\]\((.+?)\)/)
  const blocks = []
  for (let i = 0; i < parts.length; i++) {
    if (i % 2 === 0) {
      const textPart = parts[i].trim()
      if (!textPart) continue
      const cleaned = textPart
        .replace(/Your browser does not support the\s*audio element[.\d:]*/g, '')
        .replace(/펼침.*?기사를 읽어드립니다/g, '')
        .replace(/기자수정 \d{4}-\d{2}-\d{2} \d{2}:\d{2}/g, '')
        .replace(/본문사회/g, '')
        .replace(/Read full article/gi, '')
        .replace(/Comments/gi, '')
      let paragraphs = cleaned.split(/\n{2,}/).map(p => p.trim()).filter(p => p.length > 10)
      // 줄바꿈 없이 긴 텍스트면 문장 단위로 자동 분할 (번역 결과가 한 덩어리일 때)
      if (paragraphs.length <= 1 && cleaned.length > 500) {
        paragraphs = cleaned.split(/(?<=[.!?。])\s+/).reduce((acc, sentence) => {
          const last = acc[acc.length - 1]
          if (last && last.length < 300) { acc[acc.length - 1] = last + ' ' + sentence }
          else { acc.push(sentence) }
          return acc
        }, []).filter(p => p.length > 10)
      }
      paragraphs.forEach(p => blocks.push({ type: 'text', text: p }))
    } else {
      blocks.push({ type: 'img', src: parts[i] })
    }
  }
  return blocks
}

const contentBlocks = computed(() => parseContentToBlocks(activeItem.value?.content))
const englishContentBlocks = computed(() => parseContentToBlocks(activeItem.value?.content_en))

const currentIdx = ref(-1)
async function openItem(item) {
  currentIdx.value = items.value.findIndex(i => i.id === item.id)
  try { const { data } = await axios.get(`/api/news/${item.id}`); activeItem.value = data.data }
  catch { activeItem.value = item }
  if (activeItem.value?.category_id) activeCat.value = categories.value.find(c => c.id === activeItem.value.category_id) || null
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
function navItem(dir) {
  const newIdx = currentIdx.value + dir
  if (newIdx >= 0 && newIdx < items.value.length) openItem(items.value[newIdx])
}
const loading = ref(true)
const page = ref(1)
const lastPage = ref(1)

function formatDate(dt) {
  if (!dt) return ''
  const h = Math.floor((Date.now() - new Date(dt).getTime()) / 3600000)
  if (h < 1) return '방금'
  if (h < 24) return h + '시간 전'
  return Math.floor(h / 24) + '일 전'
}

async function loadNews(p = 1) {
  loading.value = true; page.value = p
  const params = { page: p, per_page: 20 }
  if (activeCat.value) params.category_id = activeCat.value.id
  if (searchQ.value) params.search = searchQ.value
  try {
    const { data } = await axios.get('/api/news', { params })
    const raw = data.data?.data || []
    // 중복 제거 (같은 title 기준)
    const seen = new Set()
    items.value = raw.filter(item => {
      const key = item.title
      if (seen.has(key)) return false
      seen.add(key)
      return true
    })
    lastPage.value = data.data?.last_page || 1
  } catch {}
  loading.value = false
  loadFavorited()
}

// 좋아요 (Bookmark)
async function loadFavorited() {
  if (!auth.isLoggedIn || !items.value.length) return
  try {
    const ids = items.value.map(i => i.id).join(',')
    const { data } = await axios.get('/api/bookmarks/check', { params: { type: 'App\\Models\\News', ids } })
    favorited.value = new Set(data.data || [])
  } catch {}
}
async function toggleFav(item) {
  if (!auth.isLoggedIn) return
  const result = await bStore.toggle(BM_TYPE, item.id)
  if (result !== null) {
    if (result) favorited.value.add(item.id)
    else favorited.value.delete(item.id)
    favorited.value = new Set(favorited.value)
  }
}
async function loadFavoritesPage() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/bookmarks', { params: { type: 'App\\Models\\News', per_page: 50 } })
    const bms = data.data?.data || []
    items.value = bms.map(b => b.bookmarkable).filter(Boolean)
    lastPage.value = 1
    loadFavorited()
  } catch {}
  loading.value = false
}

onMounted(async () => {
  bStore.loadAll()
  const [nRes, cRes] = await Promise.allSettled([
    axios.get('/api/news?per_page=20'),
    axios.get('/api/news/categories'),
  ])
  if (cRes.status === 'fulfilled') categories.value = cRes.value.data?.data || []

  // URL 쿼리 파라미터로 카테고리 반영
  const catQuery = route.query.category
  if (catQuery && categories.value.length) {
    const found = categories.value.find(c => String(c.id) === String(catQuery))
    if (found) activeCat.value = found
  }

  if (activeCat.value) {
    await loadNews()
  } else if (nRes.status === 'fulfilled') {
    const raw2 = nRes.value.data?.data?.data || []
    const seen2 = new Set()
    items.value = raw2.filter(item => { const k = item.title; if (seen2.has(k)) return false; seen2.add(k); return true })
    lastPage.value = nRes.value.data?.data?.last_page || 1
  }

  // URL에 id가 있으면 해당 항목 인라인 열기
  const itemId = route.params.id
  if (itemId) {
    try { const { data } = await axios.get('/api/news/' + itemId); activeItem.value = data.data } catch {}
  }
  loading.value = false
})

// URL 쿼리 변경 시 카테고리 반영
watch(() => route.query.category, (catId) => {
  if (!catId) { activeCat.value = null; loadNews(); return }
  const found = categories.value.find(c => String(c.id) === String(catId))
  if (found) { activeCat.value = found; loadNews() }
})

watch(() => route.params.id, (newId, oldId) => {
  if (oldId && !newId) {
    loadNews()
    activeItem.value = null
  }
})
</script>
