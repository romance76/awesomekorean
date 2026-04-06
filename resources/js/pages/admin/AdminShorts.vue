<template>
<div>
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-black text-gray-800">📱 숏츠 관리</h1>
    <button @click="fetchShorts" :disabled="fetching" class="bg-blue-500 text-white font-bold px-4 py-2 rounded-lg text-sm hover:bg-blue-600 disabled:opacity-50">
      {{ fetching ? '수집 중...' : '🔄 YouTube 숏츠 수집' }}
    </button>
  </div>

  <!-- 통계 카드 -->
  <div class="grid grid-cols-3 gap-3 mb-4">
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
      <div class="text-2xl font-black text-amber-600">{{ totalShorts }}</div>
      <div class="text-xs text-gray-500">전체 숏츠</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
      <div class="text-2xl font-black text-blue-600">{{ totalViews.toLocaleString() }}</div>
      <div class="text-xs text-gray-500">전체 조회</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
      <div class="text-2xl font-black text-red-500">{{ totalLikes.toLocaleString() }}</div>
      <div class="text-xs text-gray-500">전체 좋아요</div>
    </div>
  </div>

  <!-- 안내 -->
  <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-4 text-xs text-amber-700">
    📱 숏츠는 매일 03:00에 YouTube API로 자동 수집됩니다 (한인 관련 22개 검색어, 최대 50개/일)
  </div>

  <!-- 검색 -->
  <div class="bg-white rounded-xl shadow-sm border p-3 mb-4">
    <form @submit.prevent="load()" class="flex gap-2">
      <input v-model="search" type="text" placeholder="제목 검색..." class="flex-1 border rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-amber-400" />
      <button type="submit" class="bg-amber-400 text-amber-900 font-bold px-4 py-1.5 rounded-lg text-xs">검색</button>
    </form>
  </div>

  <div v-if="loading" class="text-center py-8 text-gray-400">로딩중...</div>
  <div v-else class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 border-b"><tr>
        <th class="px-3 py-2 text-left text-xs text-gray-500 w-16">썸네일</th>
        <th class="px-3 py-2 text-left text-xs text-gray-500">제목</th>
        <th class="px-3 py-2 text-xs text-gray-500">👁</th>
        <th class="px-3 py-2 text-xs text-gray-500">❤️</th>
        <th class="px-3 py-2 text-xs text-gray-500">💬</th>
        <th class="px-3 py-2 text-xs text-gray-500">YouTube</th>
        <th class="px-3 py-2 text-xs text-gray-500">날짜</th>
        <th class="px-3 py-2 text-xs text-gray-500">관리</th>
      </tr></thead>
      <tbody>
        <tr v-for="item in items" :key="item.id" class="border-b last:border-0 hover:bg-amber-50/30">
          <td class="px-3 py-2">
            <img v-if="item.thumbnail_url" :src="item.thumbnail_url" class="w-12 h-8 object-cover rounded" @error="e=>e.target.style.display='none'" />
          </td>
          <td class="px-3 py-2 truncate max-w-[200px] font-medium text-gray-800">{{ item.title }}</td>
          <td class="px-3 py-2 text-center text-xs text-gray-500">{{ item.view_count }}</td>
          <td class="px-3 py-2 text-center text-xs text-gray-500">{{ item.like_count }}</td>
          <td class="px-3 py-2 text-center text-xs text-gray-500">{{ item.comment_count }}</td>
          <td class="px-3 py-2 text-center">
            <a v-if="item.youtube_id" :href="`https://youtube.com/shorts/${item.youtube_id}`" target="_blank" class="text-red-500 text-xs">▶</a>
          </td>
          <td class="px-3 py-2 text-[10px] text-gray-400">{{ item.created_at?.slice(0,10) }}</td>
          <td class="px-3 py-2 text-center">
            <button @click="deleteItem(item)" class="text-xs text-red-400 hover:text-red-600">삭제</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
    <button v-for="pg in Math.min(lastPage, 10)" :key="pg" @click="load(pg)"
      class="px-3 py-1 rounded text-sm" :class="pg===page?'bg-amber-400 text-amber-900 font-bold':'bg-white border text-gray-600'">{{ pg }}</button>
  </div>
</div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
const items = ref([]); const loading = ref(true); const page = ref(1); const lastPage = ref(1)
const search = ref(''); const totalShorts = ref(0); const fetching = ref(false)
async function fetchShorts() {
  fetching.value = true
  try { await axios.post('/api/admin/fetch-shorts'); alert('YouTube 숏츠 수집이 시작되었습니다!'); load() } catch(e) { alert(e.response?.data?.message || '수집 실패') }
  fetching.value = false
}
const totalViews = computed(() => items.value.reduce((s, i) => s + (i.view_count || 0), 0))
const totalLikes = computed(() => items.value.reduce((s, i) => s + (i.like_count || 0), 0))

async function load(p=1) {
  loading.value = true; page.value = p
  const params = { page: p, per_page: 20 }
  if (search.value) params.search = search.value
  try {
    const { data } = await axios.get('/api/shorts', { params })
    items.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
    totalShorts.value = data.data?.total || items.value.length
  } catch {}
  loading.value = false
}
async function deleteItem(item) { if(!confirm('삭제?'))return; try { await axios.delete('/api/shorts/'+item.id); items.value=items.value.filter(x=>x.id!==item.id); totalShorts.value-- }catch{} }
onMounted(()=>load())
</script>
