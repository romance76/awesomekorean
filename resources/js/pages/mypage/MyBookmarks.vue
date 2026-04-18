<template>
  <!-- /mypage/bookmarks (Phase 2-C 묶음 3) -->
  <div class="bg-white rounded-xl shadow-sm p-4">
    <h3 class="font-bold mb-3">🔖 북마크</h3>

    <!-- 카테고리 탭 -->
    <div class="flex gap-2 mb-3 overflow-x-auto scrollbar-hide">
      <button
        v-for="t in typeTabs" :key="t.key"
        @click="activeType = t.key"
        :class="['px-3 py-1 rounded-full text-xs whitespace-nowrap', activeType === t.key ? 'bg-amber-400 text-white font-semibold' : 'bg-gray-100 hover:bg-gray-200']"
      >{{ t.label }} ({{ countOf(t.key) }})</button>
    </div>

    <div v-if="loading" class="p-6 text-center text-sm text-gray-400">로딩 중...</div>
    <div v-else-if="!filtered.length" class="p-10 text-center text-sm text-gray-500">
      <p class="text-3xl mb-2">📑</p>
      <p>북마크한 항목이 없습니다.</p>
    </div>
    <ul v-else class="divide-y">
      <li v-for="b in filtered" :key="b.id" class="py-3 flex items-center justify-between gap-2">
        <router-link :to="linkTo(b)" class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-gray-800 truncate">{{ b.title || b.target?.title || b.target_title || '제목 없음' }}</p>
          <p class="text-xs text-gray-500">{{ typeLabel(b.target_type || b.type) }} · {{ fmtDate(b.created_at) }}</p>
        </router-link>
        <button @click="remove(b)" class="text-xs px-2 py-1 bg-red-100 text-red-700 hover:bg-red-200 rounded">삭제</button>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useSiteStore } from '../../stores/site'

const site = useSiteStore()
const bookmarks = ref([])
const loading = ref(true)
const activeType = ref('all')

const typeTabs = [
  { key: 'all', label: '전체' },
  { key: 'post', label: '게시글' },
  { key: 'market_item', label: '장터' },
  { key: 'real_estate_listing', label: '부동산' },
  { key: 'job', label: '구인' },
  { key: 'business', label: '업소' },
  { key: 'event', label: '이벤트' },
  { key: 'recipe', label: '레시피' },
]
const fmtDate = (s) => s ? new Date(s).toLocaleDateString('ko-KR') : ''
const typeLabel = (t) => (typeTabs.find(x => x.key === t)?.label) || t

const filtered = computed(() => activeType.value === 'all'
  ? bookmarks.value
  : bookmarks.value.filter(b => (b.target_type || b.type) === activeType.value)
)
const countOf = (k) => k === 'all' ? bookmarks.value.length : bookmarks.value.filter(b => (b.target_type || b.type) === k).length

function linkTo(b) {
  const t = b.target_type || b.type
  const id = b.target_id || b.id
  const map = {
    post: `/community/${id}`,
    market_item: `/market/${id}`,
    real_estate_listing: `/realestate/${id}`,
    job: `/jobs/${id}`,
    business: `/directory/${id}`,
    event: `/events/${id}`,
    recipe: `/recipes/${id}`,
  }
  return map[t] || '/'
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/bookmarks')
    bookmarks.value = data?.data?.data || data?.data || []
  } finally { loading.value = false }
}

async function remove(b) {
  if (!confirm('이 북마크를 삭제하시겠습니까?')) return
  try {
    const t = b.target_type || b.type
    const id = b.target_id || b.id
    await axios.delete('/api/bookmarks', { data: { target_type: t, target_id: id } })
    bookmarks.value = bookmarks.value.filter(x => x.id !== b.id)
    site.toast('삭제되었습니다', 'success')
  } catch { site.toast('삭제 실패', 'error') }
}

onMounted(load)
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
