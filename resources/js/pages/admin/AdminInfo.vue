<template>
<div>
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-lg font-bold">정보 관리</h1>
    <button @click="triggerGeneration" :disabled="generating"
      class="inline-flex items-center gap-1.5 bg-orange-500 text-white font-semibold px-4 py-2 rounded-xl text-sm hover:bg-orange-600 transition-colors disabled:opacity-50">
      🚀 {{ generating ? '생성 중...' : '지금 자동 생성' }}
    </button>
  </div>

  <div v-if="genStatus && genStatus.status === 'running'" class="mb-4 bg-orange-50 border border-orange-200 rounded-xl p-3">
    <div class="flex justify-between text-xs font-semibold text-orange-700 mb-1.5">
      <span>자동 생성 진행 중...</span>
      <span>{{ genStatus.completed || 0 }} / {{ genStatus.target || 10 }}</span>
    </div>
    <div class="h-2 bg-orange-100 rounded-full overflow-hidden">
      <div class="h-full bg-orange-500 transition-all" :style="{ width: progressPct + '%' }"></div>
    </div>
  </div>
  <div v-else-if="genStatus && genStatus.status === 'done' && genStatus.message" class="mb-4 bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-xs text-emerald-700">
    ✅ {{ genStatus.message }}
  </div>

  <div class="flex flex-wrap gap-2 mb-4">
    <select v-model="category" @change="load(1)" class="border border-line rounded-lg px-3 py-1.5 text-sm">
      <option value="">전체 카테고리</option>
      <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
    </select>
    <input v-model="search" @keyup.enter="load(1)" placeholder="제목 검색" class="border border-line rounded-lg px-3 py-1.5 text-sm flex-1 min-w-[160px]" />
  </div>

  <div class="overflow-x-auto border border-line rounded-xl">
    <table class="w-full text-sm">
      <thead class="bg-surface text-ink-light text-xs">
        <tr>
          <th class="text-left px-3 py-2">제목</th>
          <th class="text-left px-3 py-2">카테고리</th>
          <th class="text-left px-3 py-2">발행일</th>
          <th class="text-left px-3 py-2">조회</th>
          <th class="text-left px-3 py-2">상태</th>
          <th class="text-right px-3 py-2">관리</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="post in posts" :key="post.id" class="border-t border-line">
          <td class="px-3 py-2 max-w-xs truncate">{{ post.title }}</td>
          <td class="px-3 py-2">{{ post.category }}</td>
          <td class="px-3 py-2 text-xs text-ink-light">{{ post.published_at ? post.published_at.slice(0,10) : '-' }}</td>
          <td class="px-3 py-2">{{ post.view_count }}</td>
          <td class="px-3 py-2">
            <span :class="post.is_published ? 'text-emerald-600' : 'text-ink-faint'">{{ post.is_published ? '발행됨' : '비공개' }}</span>
          </td>
          <td class="px-3 py-2 text-right whitespace-nowrap">
            <button @click="openEdit(post)" class="text-blue-600 hover:underline mr-2">수정</button>
            <button @click="toggle(post)" class="text-amber-600 hover:underline mr-2">{{ post.is_published ? '비공개' : '발행' }}</button>
            <button @click="remove(post)" class="text-red-600 hover:underline">삭제</button>
          </td>
        </tr>
        <tr v-if="!posts.length">
          <td colspan="6" class="px-3 py-8 text-center text-ink-faint text-sm">글이 없습니다</td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="flex justify-center gap-2 mt-4" v-if="lastPage > 1">
    <button v-for="p in lastPage" :key="p" @click="load(p)"
      class="w-8 h-8 rounded-lg text-sm" :class="p === page ? 'bg-orange-500 text-white' : 'bg-surface text-ink-light'">{{ p }}</button>
  </div>

  <!-- 수정 모달 -->
  <div v-if="editing" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="editing=null">
    <div class="bg-white rounded-2xl p-5 w-full max-w-xl max-h-[85vh] overflow-y-auto">
      <h2 class="font-bold mb-3">글 수정</h2>
      <div class="space-y-3 text-sm">
        <div>
          <label class="block text-xs text-ink-light mb-1">제목</label>
          <input v-model="editing.title" class="w-full border border-line rounded-lg px-3 py-2" />
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">카테고리</label>
          <select v-model="editing.category" class="w-full border border-line rounded-lg px-3 py-2">
            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">요약(excerpt)</label>
          <textarea v-model="editing.excerpt" rows="2" class="w-full border border-line rounded-lg px-3 py-2"></textarea>
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">커버 이미지 URL</label>
          <input v-model="editing.cover_image_url" class="w-full border border-line rounded-lg px-3 py-2" />
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">본문 HTML</label>
          <textarea v-model="editing.body" rows="10" class="w-full border border-line rounded-lg px-3 py-2 font-mono text-xs"></textarea>
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">meta_title</label>
          <input v-model="editing.meta_title" class="w-full border border-line rounded-lg px-3 py-2" />
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">meta_description</label>
          <input v-model="editing.meta_description" class="w-full border border-line rounded-lg px-3 py-2" />
        </div>
      </div>
      <div class="flex justify-end gap-2 mt-4">
        <button @click="editing=null" class="px-4 py-2 rounded-lg bg-surface text-sm">취소</button>
        <button @click="save" class="px-4 py-2 rounded-lg bg-orange-500 text-white text-sm font-semibold">저장</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const posts = ref([])
const page = ref(1)
const lastPage = ref(1)
const category = ref('')
const search = ref('')
const editing = ref(null)
const generating = ref(false)
const genStatus = ref(null)
let pollTimer = null

const categories = ['생활정보', '이민·비자', '세금', '금융', '보험', '부동산', '교통', '교육', '날씨·안전', '통신', '창업·비즈니스']

const progressPct = computed(() => {
  if (!genStatus.value?.target) return 0
  return Math.min(100, Math.round((genStatus.value.completed / genStatus.value.target) * 100))
})

async function load(p = 1) {
  page.value = p
  const { data } = await axios.get('/api/admin/info-posts', { params: { page: p, category: category.value || undefined, search: search.value || undefined } })
  posts.value = data.data.data
  lastPage.value = data.data.last_page
}

function openEdit(post) {
  editing.value = { ...post }
}

async function save() {
  try {
    await axios.put(`/api/admin/info-posts/${editing.value.id}`, {
      title: editing.value.title,
      category: editing.value.category,
      excerpt: editing.value.excerpt,
      cover_image_url: editing.value.cover_image_url,
      body: editing.value.body,
      meta_title: editing.value.meta_title,
      meta_description: editing.value.meta_description,
    })
    editing.value = null
    load(page.value)
  } catch (e) { alert(e.response?.data?.message || '저장 실패') }
}

async function toggle(post) {
  await axios.patch(`/api/admin/info-posts/${post.id}/toggle`)
  load(page.value)
}

async function remove(post) {
  if (!confirm(`"${post.title}" 글을 삭제할까요?`)) return
  await axios.delete(`/api/admin/info-posts/${post.id}`)
  load(page.value)
}

async function fetchGenStatus() {
  const { data } = await axios.get('/api/admin/info-generation/status')
  genStatus.value = data
  if (data.status === 'running') {
    generating.value = true
  } else {
    generating.value = false
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null }
  }
}

async function triggerGeneration() {
  if (!confirm('글 10개를 자동 생성합니다. 보통 수 분~1시간 내 처리됩니다. 시작할까요?')) return
  generating.value = true
  await axios.post('/api/admin/info-generation/trigger')
  startPolling()
}

function startPolling() {
  if (pollTimer) return
  pollTimer = setInterval(fetchGenStatus, 10000)
  fetchGenStatus()
}

onMounted(() => {
  load(1)
  fetchGenStatus().then(() => { if (genStatus.value?.status === 'running') startPolling() })
})
onUnmounted(() => { if (pollTimer) clearInterval(pollTimer) })
</script>
