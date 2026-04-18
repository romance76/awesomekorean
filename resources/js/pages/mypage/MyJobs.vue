<template>
  <!-- /mypage/jobs (Phase 2-C Post) -->
  <div class="bg-white rounded-xl shadow-sm p-4">
    <div class="flex items-center justify-between mb-3">
      <h3 class="font-bold">💼 내 구인/구직 공고</h3>
      <router-link to="/jobs/write" class="px-3 py-1.5 bg-amber-400 hover:bg-amber-500 text-white rounded text-sm font-semibold">+ 등록</router-link>
    </div>

    <div v-if="loading" class="p-6 text-center text-sm text-gray-400">로딩 중...</div>
    <div v-else-if="!items.length" class="p-10 text-center text-sm text-gray-500">
      <p class="text-3xl mb-2">💼</p>
      <p>등록한 공고가 없습니다.</p>
    </div>
    <ul v-else class="divide-y">
      <li v-for="j in items" :key="j.id" class="py-3">
        <router-link :to="`/jobs/${j.id}`" class="block">
          <p class="font-semibold text-sm">{{ j.title }}</p>
          <p class="text-xs text-gray-600 mt-1">
            {{ j.company || j.company_name }} · {{ j.city }} {{ j.state }}
          </p>
          <p class="text-xs text-gray-400 mt-0.5">
            <span :class="['inline-block px-2 py-0.5 rounded text-xs mr-1', j.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
              {{ j.is_active ? '게시중' : '종료' }}
            </span>
            {{ fmtDate(j.created_at) }}
          </p>
        </router-link>
        <div class="flex gap-1 mt-2">
          <button @click="toggleActive(j)" class="flex-1 py-1 rounded text-xs" :class="j.is_active ? 'bg-gray-100 hover:bg-gray-200' : 'bg-amber-100 hover:bg-amber-200 text-amber-700'">
            {{ j.is_active ? '종료' : '재게시' }}
          </button>
          <router-link :to="`/jobs/${j.id}/edit`" class="flex-1 text-center py-1 bg-gray-100 hover:bg-gray-200 rounded text-xs">수정</router-link>
          <button @click="del(j)" class="flex-1 py-1 bg-red-100 hover:bg-red-200 text-red-700 rounded text-xs">삭제</button>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useSiteStore } from '../../stores/site'

const site = useSiteStore()
const items = ref([])
const loading = ref(true)
const fmtDate = (s) => s ? new Date(s).toLocaleDateString('ko-KR') : ''

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/my-jobs')
    items.value = data?.data?.data || data?.data || []
  } finally { loading.value = false }
}

async function toggleActive(j) {
  try {
    await axios.post(`/api/jobs/${j.id}`, { is_active: !j.is_active })
    j.is_active = !j.is_active
    site.toast('변경되었습니다', 'success')
  } catch { site.toast('변경 실패', 'error') }
}

async function del(j) {
  if (!confirm(`"${j.title}" 를 삭제하시겠습니까?`)) return
  try {
    await axios.delete(`/api/jobs/${j.id}`)
    items.value = items.value.filter(x => x.id !== j.id)
    site.toast('삭제되었습니다', 'success')
  } catch { site.toast('삭제 실패', 'error') }
}

onMounted(load)
</script>
