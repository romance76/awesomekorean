<template>
  <!-- /mypage/realestate (Phase 2-C Post) -->
  <div class="bg-white rounded-xl shadow-sm p-4">
    <div class="flex items-center justify-between mb-3">
      <h3 class="font-bold">🏠 내 부동산 매물</h3>
      <router-link to="/realestate/write" class="px-3 py-1.5 bg-amber-400 hover:bg-amber-500 text-white rounded text-sm font-semibold">+ 등록</router-link>
    </div>

    <div v-if="loading" class="p-6 text-center text-sm text-gray-400">로딩 중...</div>
    <div v-else-if="!items.length" class="p-10 text-center text-sm text-gray-500">
      <p class="text-3xl mb-2">🏘️</p>
      <p>등록한 매물이 없습니다.</p>
    </div>
    <ul v-else class="divide-y">
      <li v-for="r in items" :key="r.id" class="py-3">
        <router-link :to="`/realestate/${r.id}`" class="block">
          <p class="font-semibold text-sm">{{ r.title }}</p>
          <p class="text-xs text-gray-600 mt-1">
            <span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded">{{ typeLabel(r.type || r.listing_type) }}</span>
            <span class="ml-2">{{ r.city }} {{ r.state }}</span>
          </p>
          <p class="text-amber-600 font-bold text-sm mt-1">${{ Number(r.price || r.monthly_rent || 0).toLocaleString() }}</p>
        </router-link>
        <div class="flex gap-1 mt-2">
          <router-link :to="`/realestate/${r.id}/edit`" class="flex-1 text-center py-1 bg-gray-100 hover:bg-gray-200 rounded text-xs">수정</router-link>
          <button @click="del(r)" class="flex-1 py-1 bg-red-100 hover:bg-red-200 text-red-700 rounded text-xs">삭제</button>
        </div>
      </li>
    </ul>
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

const typeLabel = (t) => ({ rent: '렌트', sale: '매매', roommate: '룸메이트', sublet: '단기' }[t] || t)

async function load() {
  loading.value = true
  try {
    if (!auth.user?.id) await auth.fetchUser?.()
    const { data } = await axios.get(`/api/realestate?user_id=${auth.user?.id}&per_page=50`)
    items.value = data?.data?.data || data?.data || []
  } finally { loading.value = false }
}

async function del(r) {
  if (!confirm(`"${r.title}" 를 삭제하시겠습니까?`)) return
  try {
    await axios.delete(`/api/realestate/${r.id}`)
    items.value = items.value.filter(x => x.id !== r.id)
    site.toast('삭제되었습니다', 'success')
  } catch { site.toast('삭제 실패', 'error') }
}

onMounted(load)
</script>
