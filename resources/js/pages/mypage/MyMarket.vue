<template>
  <!-- /mypage/market (Phase 2-C Post) -->
  <div class="bg-white rounded-xl shadow-sm p-4">
    <div class="flex items-center justify-between mb-3">
      <h3 class="font-bold">🛒 내 판매 물품</h3>
      <router-link to="/market/write" class="px-3 py-1.5 bg-amber-400 hover:bg-amber-500 text-white rounded text-sm font-semibold">+ 등록</router-link>
    </div>

    <div v-if="loading" class="p-6 text-center text-sm text-gray-400">로딩 중...</div>
    <div v-else-if="!items.length" class="p-10 text-center text-sm text-gray-500">
      <p class="text-3xl mb-2">📦</p>
      <p>등록한 물품이 없습니다.</p>
    </div>
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
      <div v-for="m in items" :key="m.id" class="border rounded-lg overflow-hidden hover:shadow-md transition">
        <router-link :to="`/market/${m.id}`">
          <img :src="m.images?.[0] || m.image_url || '/images/placeholder.png'" @error="$event.target.src='/images/placeholder.png'" class="w-full h-32 object-cover bg-gray-100" />
        </router-link>
        <div class="p-3">
          <p class="font-semibold text-sm truncate">{{ m.title }}</p>
          <p class="text-amber-600 font-bold text-sm mt-0.5">${{ Number(m.price || 0).toLocaleString() }}</p>
          <div class="flex items-center gap-1.5 text-xs mt-1">
            <span :class="statusBadge(m.status || m.sale_status)">{{ statusLabel(m.status || m.sale_status) }}</span>
            <span class="text-gray-400 ml-auto">👁 {{ m.view_count || 0 }}</span>
          </div>
          <div class="flex gap-1 mt-2 pt-2 border-t">
            <router-link :to="`/market/${m.id}/edit`" class="flex-1 text-center py-1 bg-gray-100 hover:bg-gray-200 rounded text-xs">수정</router-link>
            <button @click="del(m)" class="flex-1 py-1 bg-red-100 hover:bg-red-200 text-red-700 rounded text-xs">삭제</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'

const auth = useAuthStore()
const site = useSiteStore()
const items = ref([])
const loading = ref(true)

const statusBadge = (s) => {
  const m = {
    active: 'bg-green-100 text-green-700', available: 'bg-green-100 text-green-700',
    reserved: 'bg-yellow-100 text-yellow-700', sold: 'bg-gray-100 text-gray-500',
  }
  return 'px-2 py-0.5 rounded text-xs ' + (m[s] || 'bg-gray-100 text-gray-500')
}
const statusLabel = (s) => ({active:'판매중',available:'판매중',reserved:'예약중',sold:'판매완료'}[s] || s || '-')

async function load() {
  loading.value = true
  try {
    if (!auth.user?.id) await auth.fetchUser?.()
    const uid = auth.user?.id
    const { data } = await axios.get(`/api/market?user_id=${uid}&per_page=50`)
    items.value = data?.data?.data || data?.data || []
  } finally { loading.value = false }
}

async function del(m) {
  if (!confirm(`"${m.title}" 를 삭제하시겠습니까?`)) return
  try {
    await axios.delete(`/api/market/${m.id}`)
    items.value = items.value.filter(x => x.id !== m.id)
    site.toast('삭제되었습니다', 'success')
  } catch { site.toast('삭제 실패', 'error') }
}

onMounted(load)
</script>
