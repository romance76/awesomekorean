<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="text-xl font-black text-gray-800">📰 뉴스</h1>
      <form @submit.prevent="loadNews()" class="flex gap-1">
        <input v-model="searchQ" type="text" placeholder="뉴스 검색..." class="border rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-amber-400 outline-none w-40" />
        <button type="submit" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500">검색</button>
      </form>
    </div>

    <div class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 카테고리 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 카테고리</div>
          <button @click="activeCat=null; activeItem=null; loadNews()" class="w-full text-left px-3 py-2 text-xs transition"
            :class="!activeCat ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">전체</button>
          <button v-for="cat in categories" :key="cat.id" @click="activeCat=cat; activeItem=null; loadNews()"
            class="w-full text-left px-3 py-2 text-xs transition"
            :class="activeCat?.id===cat.id ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">{{ cat.name }}</button>
        </div>
      </div>

      <!-- 메인: 뉴스 목록 -->
      <div class="col-span-12 lg:col-span-7">
        <!-- 모바일 카테고리 -->
        <div class="lg:hidden mb-3">
          <select @change="e => { activeCat = categories.find(c=>c.id==e.target.value)||null; loadNews() }" class="w-full border rounded-lg px-3 py-2 text-sm">
            <option :value="null">전체</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
          </select>
        </div>

        <div class="mb-2">
          <span class="font-bold text-amber-700 text-sm">{{ activeCat ? activeCat.name : '전체' }}</span>
          <span v-if="!activeCat" class="text-xs text-gray-400 ml-2">모든 뉴스를 볼 수 있습니다</span>
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
                <span>👁 {{ activeItem.view_count }}조회</span>
              </div>
            </div>
            <!-- 대표 이미지 (본문에 같은 이미지 없을 때만) -->
            <div v-if="activeItem.image_url && !hasImageInContent" class="px-5 pb-3">
              <img :src="activeItem.image_url" class="w-full max-h-80 object-cover rounded-lg" @error="e=>e.target.style.display='none'" />
            </div>
            <!-- 본문 (단락 구분 + 이미지) -->
            <div class="px-5 py-5 border-t text-sm text-gray-700 leading-7">
              <template v-for="(block, i) in contentBlocks" :key="i">
                <img v-if="block.type==='img'" :src="block.src" class="w-full rounded-lg my-4" @error="e=>e.target.style.display='none'" />
                <p v-else class="mb-4" style="text-indent: 0.5em;">{{ block.text }}</p>
              </template>
            </div>
            <div v-if="activeItem.source_url" class="px-5 py-3 border-t">
              <a :href="activeItem.source_url" target="_blank" class="text-amber-600 text-sm hover:underline">📎 원문 보기 →</a>
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
        <div v-if="activeCat" class="mb-3 text-sm font-bold text-amber-700">{{ activeCat.name }}</div>

        <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
        <div v-else-if="!items.length" class="text-center py-12 text-gray-400">뉴스가 없습니다</div>
        <div v-else class="space-y-2">
          <div v-for="item in items" :key="item.id" @click="openItem(item)"
            class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md hover:border-amber-300 transition cursor-pointer">
            <div class="flex gap-3 p-3">
              <div v-if="item.image_url" class="w-24 h-16 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                <img :src="item.image_url" class="w-full h-full object-cover" @error="e=>e.target.style.display='none'" />
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 mb-1">
                  <span class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-semibold">{{ item.category?.name || '뉴스' }}</span>
                  <span class="text-[10px] text-gray-400">{{ item.source }}</span>
                </div>
                <div class="text-sm font-medium text-gray-800 line-clamp-2">{{ item.title }}</div>
                <div class="text-[10px] text-gray-400 mt-1">👁 {{ item.view_count }} · {{ formatDate(item.published_at) }}</div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="lastPage > 1 && !activeItem" class="flex justify-center gap-1.5 mt-4">
          <button v-for="pg in Math.min(lastPage, 10)" :key="pg" @click="loadNews(pg)"
            class="px-3 py-1 rounded text-sm" :class="pg===page?'bg-amber-400 text-amber-900 font-bold':'bg-white border text-gray-600'">{{ pg }}</button>
        </div>
        </div>
      </div>

      <!-- 오른쪽: 위젯 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block">
        <SidebarWidgets :inline="true" @select="openItem" api-url="/api/news" detail-path="/news/" :current-id="0"
          label="뉴스" />
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { useRoute } from 'vue-router'
import { ref, computed, onMounted } from 'vue'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import axios from 'axios'

const items = ref([])
const categories = ref([])
const activeCat = ref(null)
const activeItem = ref(null)
const searchQ = ref('')

// 본문 이미지 중복 체크 (확장자 무시)
const hasImageInContent = computed(() => {
  if (!activeItem.value?.content || !activeItem.value?.image_url) return false
  // 확장자 제거 후 비교
  const baseUrl = activeItem.value.image_url.replace(/\.\w+$/, '')
  return activeItem.value.content.includes(baseUrl)
})

// 본문 파싱: 마크다운 이미지 + 단락 분리
const contentBlocks = computed(() => {
  if (!activeItem.value?.content) return []
  const text = activeItem.value.content

  // 마크다운 이미지 분리
  const parts = text.split(/!\[.*?\]\((.+?)\)/)
  const blocks = []

  for (let i = 0; i < parts.length; i++) {
    if (i % 2 === 0) {
      // 텍스트 파트 — 문장 단위로 단락 분리
      const textPart = parts[i].trim()
      if (!textPart) continue

      // '다.' '했다.' '한다.' 등으로 끝나는 문장 뒤에 줄바꿈 추가
      const paragraphs = textPart
        .replace(/([.!?])(\s*)([가-힣A-Z"'\[])/g, '$1\n\n$3')
        .split(/\n{2,}/)
        .map(p => p.trim())
        .filter(p => p.length > 0)

      paragraphs.forEach(p => {
        if (p.length > 10) blocks.push({ type: 'text', text: p })
      })
    } else {
      // 이미지 URL
      const src = parts[i]
      // 대표 이미지와 같은 URL이면 건너뛰기 (확장자 무시)
      const baseUrl = activeItem.value?.image_url?.replace(/\.\w+$/, '') || ''
      const srcBase = src.replace(/\.\w+$/, '')
      if (srcBase !== baseUrl) {
        blocks.push({ type: 'img', src })
      }
    }
  }

  return blocks
})

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
    items.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
  } catch {}
  loading.value = false
}

onMounted(async () => {
  const [nRes, cRes] = await Promise.allSettled([
    axios.get('/api/news?per_page=20'),
    axios.get('/api/news/categories'),
  ])
  if (nRes.status === 'fulfilled') { items.value = nRes.value.data?.data?.data || []; lastPage.value = nRes.value.data?.data?.last_page || 1 }
  if (cRes.status === 'fulfilled') categories.value = cRes.value.data?.data || []

  // URL에 id가 있으면 해당 항목 인라인 열기
  const itemId = route.params.id
  if (itemId) {
    try { const { data } = await axios.get('/api/news/' + itemId); activeItem.value = data.data } catch {}
  }
  loading.value = false
})
</script>
