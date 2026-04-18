<template>
  <div class="bg-white rounded-xl shadow-sm p-4">
    <div class="flex items-center justify-between mb-3">
      <h3 class="font-bold">📢 내 광고 신청</h3>
      <router-link to="/ad-apply" class="px-3 py-1.5 bg-amber-400 hover:bg-amber-500 text-white rounded text-sm font-semibold">+ 새 광고</router-link>
    </div>
    <div v-if="loading" class="p-6 text-center text-sm text-gray-400">로딩 중...</div>
    <div v-else-if="!items.length" class="p-10 text-center text-sm text-gray-500">
      <p class="text-3xl mb-2">📢</p><p>신청한 광고가 없습니다.</p>
    </div>
    <ul v-else class="divide-y">
      <li v-for="a in items" :key="a.id" class="py-3">
        <p class="font-semibold text-sm">{{ a.title }}</p>
        <div class="flex items-center gap-2 mt-1 text-xs">
          <span :class="statusBadge(a.status)">{{ statusLabel(a.status) }}</span>
          <span class="text-gray-500">{{ a.position || a.ad_slot }}</span>
          <span class="text-gray-400 ml-auto">{{ fmtDate(a.starts_at) }} ~ {{ fmtDate(a.ends_at) }}</span>
        </div>
        <p v-if="a.amount" class="text-xs text-amber-600 mt-0.5 font-semibold">${{ Number(a.amount).toFixed(2) }}</p>
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
const statusBadge = (s) => 'px-2 py-0.5 rounded text-xs ' + ({
  pending: 'bg-yellow-100 text-yellow-700', active: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700', paused: 'bg-gray-100 text-gray-500',
  expired: 'bg-gray-100 text-gray-400',
}[s] || 'bg-gray-100')
const statusLabel = (s) => ({ pending: '검토중', active: '게시중', rejected: '거절', paused: '일시정지', expired: '만료' }[s] || s)
onMounted(async () => {
  try {
    const { data } = await axios.get('/api/banners/my').catch(() => ({ data: { data: [] } }))
    items.value = data?.data?.data || data?.data || []
  } finally { loading.value = false }
})
</script>
