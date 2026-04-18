<template>
  <div class="bg-white rounded-xl shadow-sm p-4">
    <div class="flex items-center justify-between mb-3">
      <h3 class="font-bold">🤝 내 공동구매</h3>
      <router-link to="/groupbuy" class="text-xs text-amber-600 hover:text-amber-800">전체 보기 →</router-link>
    </div>
    <div class="flex gap-1 mb-3">
      <button v-for="t in tabs" :key="t.key" @click="tab = t.key; load()"
        :class="['px-3 py-1 rounded-full text-xs', tab === t.key ? 'bg-amber-400 text-white font-semibold' : 'bg-gray-100 hover:bg-gray-200']">
        {{ t.label }}
      </button>
    </div>
    <div v-if="loading" class="p-6 text-center text-sm text-gray-400">로딩 중...</div>
    <div v-else-if="!items.length" class="p-10 text-center text-sm text-gray-500">
      <p class="text-3xl mb-2">🤝</p><p>참여한 공구가 없습니다.</p>
    </div>
    <ul v-else class="divide-y">
      <li v-for="g in items" :key="g.id" class="py-3">
        <router-link :to="`/groupbuy/${g.id}`" class="block">
          <p class="font-semibold text-sm">{{ g.title }}</p>
          <div class="flex items-center gap-2 text-xs text-gray-500 mt-1">
            <span :class="statusBadge(g.status)">{{ statusLabel(g.status) }}</span>
            <span>{{ g.current_participants || g.participants_count || 0 }} / {{ g.target_participants || g.max_participants || '?' }}명</span>
            <span>${{ Number(g.price || 0).toLocaleString() }}</span>
          </div>
        </router-link>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
const tab = ref('joined')
const tabs = [{ key: 'joined', label: '참여중' }, { key: 'mine', label: '내 공구' }]
const items = ref([])
const loading = ref(true)
const statusBadge = (s) => ({ approved: 'bg-green-100 text-green-700', pending: 'bg-yellow-100 text-yellow-700', completed: 'bg-gray-100 text-gray-500', rejected: 'bg-red-100 text-red-700' }[s] || 'bg-gray-100')
const statusLabel = (s) => ({ approved: '진행중', pending: '대기', completed: '완료', rejected: '거절' }[s] || s)
async function load() {
  loading.value = true
  try {
    const url = tab.value === 'joined' ? '/api/groupbuys/my-joined' : '/api/groupbuys?mine=1'
    const { data } = await axios.get(url).catch(() => ({ data: { data: [] } }))
    items.value = data?.data?.data || data?.data || []
  } finally { loading.value = false }
}
onMounted(load)
</script>
<style scoped>.px-2.py-0\.5 { padding: 2px 8px; }</style>
