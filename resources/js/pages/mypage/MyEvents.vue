<template>
  <div class="bg-white rounded-xl shadow-sm p-4">
    <h3 class="font-bold mb-3">🎉 내 이벤트</h3>
    <div v-if="loading" class="p-6 text-center text-sm text-gray-400">로딩 중...</div>
    <div v-else-if="!items.length" class="p-10 text-center text-sm text-gray-500">
      <p class="text-3xl mb-2">🎉</p>
      <p>참여한 이벤트가 없습니다.</p>
      <router-link to="/events" class="inline-block mt-3 px-4 py-2 bg-amber-400 hover:bg-amber-500 text-white rounded-lg text-sm font-semibold">이벤트 둘러보기 →</router-link>
    </div>
    <ul v-else class="divide-y">
      <li v-for="e in items" :key="e.id" class="py-3">
        <router-link :to="`/events/${e.id}`" class="block">
          <p class="font-semibold text-sm">{{ e.title }}</p>
          <p class="text-xs text-gray-500 mt-1">
            📅 {{ fmtDate(e.starts_at || e.start_date) }} · {{ e.location || e.city }}
          </p>
          <p class="text-xs text-amber-600 mt-0.5">
            <span v-if="e.participation_status === 'going'">✅ 참가 확정</span>
            <span v-else-if="e.participation_status === 'interested'">👀 관심</span>
            <span v-else>{{ e.status || '진행중' }}</span>
          </p>
        </router-link>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
const items = ref([])
const loading = ref(true)
const fmtDate = (s) => s ? new Date(s).toLocaleDateString('ko-KR') : ''
onMounted(async () => {
  try {
    const { data } = await axios.get('/api/events?mine=1&per_page=50').catch(() => ({ data: { data: [] } }))
    items.value = data?.data?.data || data?.data || []
  } finally { loading.value = false }
})
</script>
