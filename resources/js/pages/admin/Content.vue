<template>
<div>
  <h1 class="text-xl font-black text-gray-800 mb-4">📝 콘텐츠 관리</h1>

  <!-- 검색 + 필터 -->
  <div class="bg-white rounded-xl shadow-sm border p-3 mb-4">
    <div class="flex flex-wrap gap-2">
      <select v-model="boardFilter" @change="load()" class="border rounded-lg px-2 py-1.5 text-xs outline-none">
        <option value="">전체 게시판</option>
        <option v-for="b in boards" :key="b.id" :value="b.id">{{ b.name }}</option>
      </select>
      <form @submit.prevent="load()" class="flex-1 flex gap-1 min-w-[150px]">
        <input v-model="search" type="text" placeholder="제목/작성자 검색..." class="flex-1 border rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-amber-400" />
        <button type="submit" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs">검색</button>
      </form>
    </div>
    <div class="text-[10px] text-gray-400 mt-1">전체 {{ totalPosts }}건</div>
  </div>

  <div v-if="loading" class="text-center py-8 text-gray-400">로딩중...</div>
  <div v-else class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 border-b"><tr>
        <th class="px-3 py-2 text-left text-xs text-gray-500 w-8">#</th>
        <th class="px-3 py-2 text-left text-xs text-gray-500">제목</th>
        <th class="px-3 py-2 text-left text-xs text-gray-500">게시판</th>
        <th class="px-3 py-2 text-left text-xs text-gray-500">작성자</th>
        <th class="px-3 py-2 text-xs text-gray-500">💬</th>
        <th class="px-3 py-2 text-xs text-gray-500">❤️</th>
        <th class="px-3 py-2 text-xs text-gray-500">👁</th>
        <th class="px-3 py-2 text-xs text-gray-500">날짜</th>
        <th class="px-3 py-2 text-xs text-gray-500">관리</th>
      </tr></thead>
      <tbody>
        <tr v-for="p in posts" :key="p.id" class="border-b last:border-0 hover:bg-amber-50/30" :class="p.is_hidden ? 'opacity-40 bg-red-50/30' : ''">
          <td class="px-3 py-2 text-xs text-gray-400">{{ p.id }}</td>
          <td class="px-3 py-2.5 max-w-[250px]">
            <div class="truncate">
              <span v-if="p.is_pinned" class="text-amber-500 mr-1">📌</span>
              <RouterLink :to="`/community/${p.board?.slug||'free'}/${p.id}`" class="hover:text-amber-700 font-medium" target="_blank">{{ p.title }}</RouterLink>
            </div>
            <div class="text-[10px] text-gray-400 truncate mt-0.5">{{ (p.content || '').slice(0, 50) }}...</div>
          </td>
          <td class="px-3 py-2.5"><span class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-semibold">{{ p.board?.name || '-' }}</span></td>
          <td class="px-3 py-2.5 text-xs text-gray-600">{{ p.user?.name }}</td>
          <td class="px-3 py-2.5 text-center text-xs text-gray-500">{{ p.comment_count }}</td>
          <td class="px-3 py-2.5 text-center text-xs text-gray-500">{{ p.like_count }}</td>
          <td class="px-3 py-2.5 text-center text-xs text-gray-400">{{ p.view_count }}</td>
          <td class="px-3 py-2.5 text-[10px] text-gray-400">{{ p.created_at?.slice(0,10) }}</td>
          <td class="px-3 py-2.5 text-center space-x-1">
            <button @click="pinPost(p)" class="text-xs" :class="p.is_pinned?'text-amber-500':'text-gray-300 hover:text-amber-500'" title="고정">📌</button>
            <button @click="hidePost(p)" class="text-xs" :class="p.is_hidden?'text-green-500':'text-gray-300 hover:text-red-500'" :title="p.is_hidden?'보이기':'숨기기'">{{ p.is_hidden ? '👁' : '🚫' }}</button>
            <button @click="deletePost(p)" class="text-xs text-gray-300 hover:text-red-600" title="삭제">🗑</button>
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
import { ref, onMounted } from 'vue'
import axios from 'axios'
const posts = ref([]); const boards = ref([]); const loading = ref(true)
const page = ref(1); const lastPage = ref(1); const totalPosts = ref(0)
const search = ref(''); const boardFilter = ref('')

async function load(p=1) {
  loading.value=true; page.value=p
  const params = { page: p }
  if (search.value) params.search = search.value
  if (boardFilter.value) params.board_id = boardFilter.value
  try {
    const { data } = await axios.get('/api/admin/posts', { params })
    posts.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
    totalPosts.value = data.data?.total || 0
  } catch {}
  loading.value = false
}
async function pinPost(p) { try { await axios.post(`/api/admin/posts/${p.id}/pin`); p.is_pinned=!p.is_pinned } catch {} }
async function hidePost(p) { try { await axios.post(`/api/admin/posts/${p.id}/hide`); p.is_hidden=!p.is_hidden } catch {} }
async function deletePost(p) { if(!confirm('삭제?'))return; try { await axios.delete(`/api/admin/posts/${p.id}`); posts.value=posts.value.filter(x=>x.id!==p.id) } catch {} }

onMounted(async () => {
  try { const { data } = await axios.get('/api/admin/boards'); boards.value = data.data || [] } catch {}
  load()
})
</script>
