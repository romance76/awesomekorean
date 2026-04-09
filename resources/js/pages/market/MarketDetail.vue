<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <button @click="$router.back()" class="text-sm text-gray-500 hover:text-amber-600 mb-3">← 장터 목록</button>
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="item" class="grid grid-cols-12 gap-4">
    <div class="col-span-12 lg:col-span-9 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <!-- 이미지 -->
      <div v-if="item.images?.length" class="flex overflow-x-auto gap-1 bg-gray-100">
        <img v-for="(img,i) in item.images" :key="i" :src="'/storage/'+img" class="h-64 object-cover" @error="e=>e.target.style.display='none'" />
      </div>
      <div v-else class="h-48 bg-gray-100 flex items-center justify-center text-5xl">📦</div>

      <div class="px-5 py-4">
        <div class="flex items-center gap-2 mb-2">
          <span class="text-xs px-2 py-0.5 rounded-full font-bold"
            :class="{'bg-green-100 text-green-700':item.status==='active','bg-amber-100 text-amber-700':item.status==='reserved','bg-gray-200 text-gray-500':item.status==='sold'}">
            {{ {active:'판매중',reserved:'예약중',sold:'판매완료'}[item.status] }}
          </span>
          <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ item.condition }}</span>
          <span v-if="item.is_negotiable" class="text-xs text-amber-600">가격협의 가능</span>
        </div>
        <h1 class="text-lg font-bold text-gray-900">{{ item.title }}</h1>
        <div class="text-2xl font-black text-amber-600 mt-2">${{ Number(item.price).toLocaleString() }}</div>
        <div class="text-xs text-gray-400 mt-1">{{ item.city }}, {{ item.state }} · {{ item.view_count }}조회</div>
      </div>
      <div class="px-5 py-4 border-t text-sm text-gray-700 whitespace-pre-wrap">{{ item.content }}</div>
      <div class="px-5 py-3 border-t flex gap-3">
        <button v-if="item.status==='active' && auth.isLoggedIn" @click="reserve"
          class="bg-amber-400 text-amber-900 font-bold px-6 py-2 rounded-lg text-sm hover:bg-amber-500">🛒 찜하기 (100P)</button>
        <RouterLink v-if="auth.isLoggedIn" to="/chat" class="bg-gray-100 text-gray-700 font-semibold px-6 py-2 rounded-lg text-sm hover:bg-gray-200">💬 채팅하기</RouterLink>
      </div>

      <!-- 댓글 -->
      <CommentSection v-if="item.id" :type="'market'" :typeId="item.id" class="mt-4" />
    </div>

    <!-- 사이드바 -->
    <div class="col-span-12 lg:col-span-3 hidden lg:block">
      <SidebarWidgets
        api-url="/api/market"
        detail-path="/market/"
        :current-id="item.id"
        label="물품"
        recommend-label="추천 물품"
        quick-label="방금 올라온"
        :links="[
          { to: '/market', icon: '📋', label: '전체 장터' },
          { to: '/market/write', icon: '✏️', label: '물품 등록' },
          { to: '/jobs', icon: '💼', label: '구인구직' },
        ]"
      />
    </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import CommentSection from '../../components/CommentSection.vue'
import axios from 'axios'
const route = useRoute()
const auth = useAuthStore()
const siteStore = useSiteStore()
const item = ref(null)
const relatedItems = ref([])
const loading = ref(true)
async function reserve() {
  if (!confirm('100 포인트를 에스크로하여 찜하시겠습니까?')) return
  try {
    const { data } = await axios.post(`/api/market/${item.value.id}/reserve`, { points: 100 })
    siteStore.toast(data.message, 'success')
    item.value.status = 'reserved'
  } catch (e) { siteStore.toast(e.response?.data?.message || '예약 실패', 'error') }
}
onMounted(async () => {
  try {
    const { data } = await axios.get(`/api/market/${route.params.id}`)
    item.value = data.data
    // 관련 물품
    try {
      const { data: rData } = await axios.get(`/api/market?category=${item.value.category}&per_page=5`)
      relatedItems.value = (rData.data?.data || []).filter(r => r.id !== item.value.id).slice(0, 5)
    } catch {}
  } catch {}
  loading.value = false
})
</script>
