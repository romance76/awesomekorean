<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더: 모바일 -->
    <div class="lg:hidden mb-3">
      <div class="flex items-center justify-between mb-2">
        <h1 class="flex items-center gap-2 text-lg font-bold text-ink">
          <span class="icon-chip w-8 h-8 bg-sky-50 text-sky-600"><AppIcon name="newspaper" :size="17" /></span>
          뉴스
        </h1>
        <div class="flex items-center gap-2">
          <button @click="showFilter = true" class="btn-secondary !text-xs !px-3 !py-2"><AppIcon name="search" :size="14" />필터</button>
        </div>
      </div>
      <div class="flex items-center gap-1.5 overflow-x-auto">
        <span v-if="searchQ" class="badge-gray">
          "{{ searchQ }}"
        </span>
      </div>
    </div>

    <!-- 모바일 필터 바텀시트 -->
    <MobileFilter v-model="showFilter" @apply="loadNews()" @reset="activeCat = null; searchQ = ''; loadNews()">
      <div class="mb-4">
        <label class="input-label">검색어</label>
        <input v-model="searchQ" type="text" placeholder="뉴스 검색..."
          class="input-soft" />
      </div>
      <div>
        <label class="input-label">카테고리</label>
        <div class="grid grid-cols-3 gap-1.5">
          <button @click="activeCat = null"
            class="text-xs py-2 rounded-lg font-semibold border transition-colors"
            :class="!activeCat ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-ink-light hover:bg-gray-50'">
            전체
          </button>
          <button v-for="c in categories" :key="c.id" @click="activeCat = c"
            class="text-xs py-2 rounded-lg font-semibold border transition-colors"
            :class="activeCat?.id === c.id ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-ink-light hover:bg-gray-50'">
            {{ c.name }}
          </button>
        </div>
      </div>
    </MobileFilter>

    <!-- 헤더: 데스크탑 -->
    <div class="hidden lg:flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-sky-50 text-sky-600"><AppIcon name="newspaper" :size="20" /></span>
        뉴스
      </h1>
      <form @submit.prevent="loadNews()" class="flex gap-1.5">
        <input v-model="searchQ" type="text" placeholder="뉴스 검색..." class="input-soft !w-40" />
        <button type="submit" class="btn-primary !px-4">검색</button>
      </form>
    </div>

    <div class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 카테고리 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto space-y-3 pr-0.5">
          <div class="card overflow-hidden">
            <div class="px-3 py-2.5 border-b border-gray-50 font-bold text-xs text-ink flex items-center gap-1.5"><AppIcon name="list" :size="13" class="text-amber-500" />카테고리</div>
            <button @click="showFavorites=false; activeCat=null; activeItem=null; loadNews()" class="w-full text-left px-3 py-2 text-xs transition-colors"
              :class="!showFavorites && !activeCat ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50'">전체</button>
            <button v-for="cat in categories" :key="cat.id" @click="showFavorites=false; activeCat=cat; activeItem=null; loadNews()"
              class="w-full text-left px-3 py-2 text-xs transition-colors"
              :class="!showFavorites && activeCat?.id===cat.id ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50'">{{ cat.name }}</button>
            <button v-if="auth.isLoggedIn" @click="showFavorites=true; activeItem=null; loadFavoritesPage()"
              class="w-full text-left px-3 py-2 text-xs transition-colors border-t border-gray-50 flex items-center gap-1"
              :class="showFavorites ? 'bg-red-50 text-red-600 font-bold' : 'text-ink-light hover:bg-red-50/50'">
              <AppIcon name="bookmark" :size="12" />내 북마크<span v-if="favCount > 0" class="ml-0.5">({{ favCount }})</span>
            </button>
          </div>
          <AdSlot page="news" position="left" :maxSlots="2" />
        </div>
      </div>

      <!-- 메인: 뉴스 목록 -->
      <div class="col-span-12 lg:col-span-7">

        <div class="mb-2">
          <span v-if="showFavorites" class="font-bold text-red-600 text-sm inline-flex items-center gap-1"><AppIcon name="bookmark" :size="14" />내 북마크</span>
          <template v-else>
            <span class="font-bold text-amber-700 text-sm">{{ activeCat ? activeCat.name : '전체' }}</span>
            <span v-if="!activeCat" class="text-xs text-ink-muted ml-2">모든 뉴스를 볼 수 있습니다</span>
          </template>
        </div>

        <!-- ═══ 상세 모드 ═══ -->
        <div v-if="activeItem">
          <div class="card overflow-hidden">
            <div class="px-5 py-4">
              <div class="flex items-center gap-2 mb-2">
                <span class="badge-primary">{{ activeItem.category?.name || '뉴스' }}</span>
                <span class="text-xs text-ink-muted">{{ activeItem.source }}</span>
              </div>
              <h2 class="text-lg font-bold text-ink leading-snug">{{ activeItem.title }}</h2>
              <div class="flex items-center gap-3 mt-2 text-xs text-ink-muted">
                <span>{{ formatDate(activeItem.published_at) }}</span>
                <span class="flex items-center gap-1"><AppIcon name="eye" :size="13" />{{ activeItem.view_count }}회</span>
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
            <div class="px-5 py-5 border-t border-gray-50 text-sm text-ink-light leading-7">
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
                class="mt-2 p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800">
                ⓘ 본문 일부만 표시됩니다. 전체 기사는 아래 <b>원문 보기</b> 링크에서 확인하세요.
              </div>

              <!-- English Original (TIME 기사만) -->
              <div v-if="activeItem.content_en" class="mt-6 border-t border-gray-50 pt-4">
                <div class="flex items-center gap-2 mb-3">
                  <span class="badge-blue">English Original</span>
                  <button @click="showEnglish = !showEnglish" class="text-xs text-ink-muted hover:text-amber-600 underline transition-colors">
                    {{ showEnglish ? '접기' : '펼치기' }}
                  </button>
                </div>
                <div v-if="showEnglish" class="text-sm text-ink-light leading-7">
                  <h3 v-if="activeItem.title_en" class="font-bold text-ink mb-3">{{ activeItem.title_en }}</h3>
                  <template v-for="(block, i) in englishContentBlocks" :key="'en-'+i">
                    <img v-if="block.type==='img'" :src="block.src"
                      class="block mx-auto rounded-lg my-4"
                      style="max-width: 100%; width: auto; height: auto;"
                      @error="e=>e.target.style.display='none'" />
                    <p v-else class="mb-3 text-ink-muted italic" style="text-indent: 0.5em;">{{ block.text }}</p>
                  </template>
                </div>
              </div>
            </div>
            <div v-if="activeItem.source_url" class="px-5 py-3 border-t border-gray-50 bg-amber-50/30">
              <a :href="activeItem.source_url" target="_blank"
                class="btn-primary">
                <AppIcon name="external-link" :size="15" />원문 전체 보기
              </a>
            </div>
          </div>
          <!-- 이전글 / 목록 / 다음글 (같은 카테고리 한정, 서버 prev/next) -->
          <div class="mt-4 flex items-stretch card text-sm overflow-hidden">
            <button v-if="adjPrev" @click="navItem(-1)"
              class="flex-1 min-w-0 px-4 py-3 hover:bg-amber-50 text-left text-ink-light border-r border-gray-50 transition-colors">
              <div class="text-ink-muted text-xs flex items-center gap-1"><AppIcon name="chevron-left" :size="12" />이전글</div>
              <div class="text-xs text-ink-light truncate mt-0.5">{{ adjPrev.title || '' }}</div>
            </button>
            <div v-else class="flex-1 min-w-0 px-4 py-3 text-left text-ink-faint border-r border-gray-50 text-xs flex items-center gap-1"><AppIcon name="chevron-left" :size="12" />이전글 없음</div>

            <button @click="activeItem=null; adjPrev=null; adjNext=null"
              class="px-5 py-3 hover:bg-amber-50 text-center text-ink font-bold border-r border-gray-50 flex-shrink-0 transition-colors">
              목록
            </button>

            <button v-if="adjNext" @click="navItem(1)"
              class="flex-1 min-w-0 px-4 py-3 hover:bg-amber-50 text-right text-ink-light transition-colors">
              <div class="text-ink-muted text-xs flex items-center justify-end gap-1">다음글<AppIcon name="chevron-right" :size="12" /></div>
              <div class="text-xs text-ink-light truncate mt-0.5">{{ adjNext.title || '' }}</div>
            </button>
            <div v-else class="flex-1 min-w-0 px-4 py-3 text-right text-ink-faint text-xs flex items-center justify-end gap-1">다음글 없음<AppIcon name="chevron-right" :size="12" /></div>
          </div>
        </div>

        <!-- ═══ 목록 모드 ═══ -->
        <div v-else>
        <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
        <div v-else-if="!items.length" class="py-16 text-center">
          <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="newspaper" :size="28" :stroke-width="1.5" /></div>
          <p class="text-sm text-ink-muted">뉴스가 없습니다</p>
        </div>
        <div v-else class="space-y-2">
          <template v-for="(item, i) in items" :key="item.id">
          <div @click="openItem(item)"
            class="card card-hover overflow-hidden cursor-pointer">
            <div class="flex gap-3 p-3">
              <!-- 썸네일 (항상 표시: 이미지 있으면 이미지, 없으면 아이콘) -->
              <div class="w-24 h-16 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0 flex items-center justify-center text-gray-300">
                <img v-if="item.thumbnail_url || item.image_url"
                  :src="item.thumbnail_url || thumb(item.image_url, 200)"
                  loading="lazy" decoding="async"
                  class="w-full h-full object-cover"
                  @error="e => { e.target.style.display='none'; e.target.parentElement.innerHTML='<span class=\'text-2xl\'>📰</span>' }" />
                <AppIcon v-else name="newspaper" :size="24" :stroke-width="1.5" />
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 mb-1">
                  <span v-if="!activeCat" class="badge-primary !text-[11px] !px-2">{{ item.category?.name || '뉴스' }}</span>
                  <span v-if="item.source?.startsWith('TIME')" class="badge-blue !text-[11px] !px-2 font-bold">EN→KO</span>
                  <span class="text-[11px] text-ink-muted">{{ item.source }}</span>
                </div>
                <div class="text-sm font-semibold text-ink line-clamp-2 leading-snug">{{ item.title }}</div>
                <div class="flex items-center gap-2 mt-1">
                  <span class="text-xs text-ink-muted flex items-center gap-1"><AppIcon name="eye" :size="12" />{{ item.view_count }} · {{ formatDate(item.published_at) }}</span>
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
import AppIcon from '../../components/AppIcon.vue'

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
const adjPrev = ref(null) // 같은 카테고리 내 이전글 (서버 prev)
const adjNext = ref(null) // 같은 카테고리 내 다음글 (서버 next)
async function openItem(item) {
  currentIdx.value = items.value.findIndex(i => i.id === item.id)
  adjPrev.value = null
  adjNext.value = null
  try {
    const { data } = await axios.get(`/api/news/${item.id}`)
    activeItem.value = data.data
    adjPrev.value = data.prev || null
    adjNext.value = data.next || null
  }
  catch { activeItem.value = item }
  if (activeItem.value?.category_id) activeCat.value = categories.value.find(c => c.id === activeItem.value.category_id) || null
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
function navItem(dir) {
  const target = dir < 0 ? adjPrev.value : adjNext.value
  if (target && target.id) {
    const inList = items.value.find(i => i.id === target.id)
    openItem(inList || { id: target.id, title: target.title })
  }
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
