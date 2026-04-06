<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <button @click="$router.back()" class="text-sm text-gray-500 hover:text-amber-600 mb-3">← 구인 목록</button>

    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="job" class="grid grid-cols-12 gap-4">
      <!-- 메인 -->
      <div class="col-span-12 lg:col-span-9">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
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
            <div v-if="job.contact_phone" class="text-sm text-gray-700">📱 <a :href="'tel:'+job.contact_phone" class="text-amber-600 hover:underline">{{ job.contact_phone }}</a></div>
            <div v-if="job.contact_email" class="text-sm text-gray-700">📧 <a :href="'mailto:'+job.contact_email" class="text-amber-600 hover:underline">{{ job.contact_email }}</a></div>
          </div>
        </div>
      </div>

      <!-- 사이드바 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block space-y-3">
        <!-- 같은 카테고리 구인 -->
        <div v-if="relatedJobs.length" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
          <div class="font-bold text-sm text-amber-900 mb-3">💼 관련 채용</div>
          <div class="space-y-2">
            <RouterLink v-for="j in relatedJobs" :key="j.id" :to="`/jobs/${j.id}`"
              class="block py-1.5 border-b border-gray-50 last:border-0 hover:bg-amber-50 -mx-2 px-2 rounded transition">
              <div class="text-xs font-medium text-gray-700 truncate">{{ j.title }}</div>
              <div class="text-[10px] text-gray-400">{{ j.company }} · {{ j.city }}</div>
            </RouterLink>
          </div>
        </div>

        <!-- 최신 구인 -->
        <div v-if="recentJobs.length" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
          <div class="font-bold text-sm text-amber-900 mb-3">🕐 최신 채용</div>
          <div class="space-y-2">
            <RouterLink v-for="j in recentJobs" :key="j.id" :to="`/jobs/${j.id}`"
              class="block py-1.5 border-b border-gray-50 last:border-0 hover:bg-amber-50 -mx-2 px-2 rounded transition">
              <div class="text-xs font-medium text-gray-700 truncate">{{ j.title }}</div>
              <div class="text-[10px] text-gray-400">{{ j.company }} · ${{ j.salary_min }}/{{ j.salary_type }}</div>
            </RouterLink>
          </div>
        </div>

        <!-- 빠른 링크 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
          <div class="font-bold text-sm text-amber-900 mb-3">⚡ 바로가기</div>
          <div class="space-y-1">
            <RouterLink to="/jobs" class="block text-xs text-gray-600 hover:text-amber-700 py-1">📋 전체 채용공고</RouterLink>
            <RouterLink to="/jobs/write" class="block text-xs text-gray-600 hover:text-amber-700 py-1">✏️ 채용공고 등록</RouterLink>
            <RouterLink to="/directory" class="block text-xs text-gray-600 hover:text-amber-700 py-1">🏪 업소록</RouterLink>
          </div>
        </div>
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
const relatedJobs = ref([])
const recentJobs = ref([])
const loading = ref(true)
function formatDate(dt) { return dt ? new Date(dt).toLocaleDateString('ko-KR') : '' }
onMounted(async () => {
  try {
    const { data } = await axios.get(`/api/jobs/${route.params.id}`)
    job.value = data.data

    const [relRes, recRes] = await Promise.allSettled([
      axios.get(`/api/jobs?category=${job.value.category}&per_page=5`),
      axios.get('/api/jobs?per_page=5'),
    ])
    if (relRes.status === 'fulfilled') relatedJobs.value = (relRes.value.data?.data?.data || []).filter(j => j.id !== job.value.id).slice(0, 5)
    if (recRes.status === 'fulfilled') recentJobs.value = (recRes.value.data?.data?.data || []).filter(j => j.id !== job.value.id).slice(0, 5)
  } catch {}
  loading.value = false
})
</script>
