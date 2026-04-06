<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-4xl mx-auto px-4 py-5">
    <button @click="$router.back()" class="text-sm text-gray-500 hover:text-amber-600 mb-3">← 목록으로</button>

    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="job" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="px-5 py-4 border-b">
        <div class="flex items-center gap-2 mb-2">
          <span class="text-xs px-2 py-0.5 rounded-full font-bold"
            :class="job.type==='full'?'bg-blue-100 text-blue-700':job.type==='part'?'bg-green-100 text-green-700':'bg-orange-100 text-orange-700'">
            {{ {full:'풀타임',part:'파트타임',contract:'계약직'}[job.type] || job.type }}
          </span>
          <span class="text-xs text-gray-400">{{ job.city }}, {{ job.state }}</span>
        </div>
        <h1 class="text-lg font-bold text-gray-900">{{ job.title }}</h1>
        <div class="text-sm text-amber-700 font-semibold mt-1">{{ job.company }}</div>
        <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
          <span>💰 ${{ job.salary_min }}~${{ job.salary_max }}/{{ job.salary_type }}</span>
          <span>👁 {{ job.view_count }}조회</span>
          <span>{{ formatDate(job.created_at) }}</span>
        </div>
      </div>
      <div class="px-5 py-5 text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{{ job.content }}</div>
      <div class="px-5 py-4 border-t bg-amber-50">
        <div class="text-sm font-semibold text-amber-900 mb-2">📞 연락처</div>
        <div v-if="job.contact_phone" class="text-sm text-gray-700">📱 {{ job.contact_phone }}</div>
        <div v-if="job.contact_email" class="text-sm text-gray-700">📧 {{ job.contact_email }}</div>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
const route = useRoute()
const job = ref(null)
const loading = ref(true)
function formatDate(dt) { return dt ? new Date(dt).toLocaleDateString('ko-KR') : '' }
onMounted(async () => {
  try { const { data } = await axios.get(`/api/jobs/${route.params.id}`); job.value = data.data } catch {}
  loading.value = false
})
</script>
