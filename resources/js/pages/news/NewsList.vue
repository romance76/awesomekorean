<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <h1 class="text-xl font-black text-gray-800 mb-4">📰 뉴스</h1>

    <div class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 카테고리 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 카테고리</div>
          <button @click="activeCat=null; loadNews()" class="w-full text-left px-3 py-2 text-xs transition"
            :class="!activeCat ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">전체</button>
          <button v-for="cat in categories" :key="cat.id" @click="activeCat=cat; loadNews()"
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
            <div v-if="activeItem.image_url" class="px-5 pb-3">
              <img :src="activeItem.image_url" class="w-full max-h-96 object-cover rounded-lg" @error="e=>e.target.style.display='none'" />
            </div>
            <div class="px-5 py-5 border-t text-sm text-gray-700 leading-relaxed">
              <template v-for="(block, i) in contentBlocks" :key="i">
                <img v-if="block.type==='img'" :src="block.src" class="w-full rounded-lg my-3" @error="e=>e.target.style.display='none'" />
                <p v-else class="whitespace-pre-wrap mb-2">{{ block.text }}</p>
              </template>
            </div>
            <div v-if="activeItem.source_url" class="px-5 py-3 border-t">
              <a :href="activeItem.source_url" target="_blank" class="text-amber-600 text-sm hover:underline">📎 원문 보기 →</a>
            </div>
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
        <SidebarWidgets api-url="/api/news" detail-path="/news/" :current-id="0"
          label="뉴스" recommend-label="좋아할 기사" quick-label="실시간 뉴스"
          :links="[{to:'/news',icon:'📰',label:'전체 뉴스'},{to:'/community',icon:'💬',label:'커뮤니티'}]" />
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import axios from 'axios'

const items = ref([])
const categories = ref([])
const activeCat = ref(null)
const activeItem = ref(null)

// 본문에서 마크다운 이미지 ![alt](url) 파싱
const contentBlocks = computed(() => {
  if (!activeItem.value?.content) return []
  const lines = activeItem.value.content.split('\n')
  const blocks = []
  let textBuf = ''
  for (const line of lines) {
    const imgMatch = line.match(/!\[.*?\]\((.+?)\)/)
    if (imgMatch) {
      if (textBuf.trim()) { blocks.push({ type: 'text', text: textBuf.trim() }); textBuf = '' }
      blocks.push({ type: 'img', src: imgMatch[1] })
    } else {
      textBuf += line + '\n'
    }
  }
  if (textBuf.trim()) blocks.push({ type: 'text', text: textBuf.trim() })
  return blocks
})

async function openItem(item) {
  try { const { data } = await axios.get(`/api/news/${item.id}`); activeItem.value = data.data }
  catch { activeItem.value = item }
  window.scrollTo({ top: 0, behavior: 'smooth' })
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
  loading.value = false
})
</script>
