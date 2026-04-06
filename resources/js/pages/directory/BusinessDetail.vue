<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <button @click="$router.back()" class="text-sm text-gray-500 hover:text-amber-600 mb-3">← 업소록</button>
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="biz" class="grid grid-cols-12 gap-4">
      <div class="col-span-12 lg:col-span-9">
      <!-- 업소 정보 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
        <div class="px-5 py-4">
          <div class="flex items-center gap-2 mb-2">
            <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">{{ biz.category }}</span>
            <span v-if="biz.subcategory" class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ biz.subcategory }}</span>
          </div>
          <h1 class="text-lg font-bold text-gray-900">🏪 {{ biz.name }}</h1>
          <div class="flex items-center gap-1 mt-1">
            <span class="text-amber-400">{{ '★'.repeat(Math.round(biz.rating)) }}{{ '☆'.repeat(5 - Math.round(biz.rating)) }}</span>
            <span class="text-sm text-gray-600 font-semibold">{{ biz.rating }}</span>
            <span class="text-xs text-gray-400">({{ biz.review_count }}개 리뷰)</span>
          </div>
        </div>
        <div class="px-5 py-3 border-t space-y-2 text-sm text-gray-600">
          <div v-if="biz.phone">📱 <a :href="'tel:'+biz.phone" class="text-amber-600 hover:underline">{{ biz.phone }}</a></div>
          <div v-if="biz.address">📍 {{ biz.address }}, {{ biz.city }}, {{ biz.state }} {{ biz.zipcode }}</div>
          <div v-if="biz.website">🌐 <a :href="biz.website" target="_blank" class="text-amber-600 hover:underline">{{ biz.website }}</a></div>
          <div v-if="biz.email">📧 {{ biz.email }}</div>
        </div>
        <div v-if="biz.hours" class="px-5 py-3 border-t">
          <h3 class="font-bold text-sm text-gray-800 mb-2">🕐 영업시간</h3>
          <div class="grid grid-cols-2 gap-1 text-xs text-gray-600">
            <div v-for="(time, day) in biz.hours" :key="day">
              <span class="font-semibold">{{ {mon:'월',tue:'화',wed:'수',thu:'목',fri:'금',sat:'토',sun:'일'}[day] || day }}:</span> {{ time }}
            </div>
          </div>
        </div>
        <div v-if="biz.description" class="px-5 py-4 border-t text-sm text-gray-700 whitespace-pre-wrap">{{ biz.description }}</div>
        <!-- 지도 -->
        <div v-if="biz.address || (biz.lat && biz.lng)" class="px-5 py-4 border-t">
          <h3 class="font-bold text-sm text-gray-800 mb-2">📍 위치</h3>
          <LeafletMap :address="biz.address ? `${biz.address}, ${biz.city}, ${biz.state}` : ''" :lat="biz.lat" :lng="biz.lng" style="height:200px" class="rounded-lg overflow-hidden" />
        </div>
      </div>

      <!-- 리뷰 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-3 border-b font-bold text-sm text-gray-800">📝 리뷰 {{ biz.review_count }}개</div>

        <!-- 리뷰 작성 -->
        <div v-if="auth.isLoggedIn" class="px-5 py-3 border-b">
          <div class="flex items-center gap-2 mb-2">
            <span class="text-sm text-gray-600">별점:</span>
            <button v-for="s in 5" :key="s" @click="reviewForm.rating = s" class="text-xl" :class="s <= reviewForm.rating ? 'text-amber-400' : 'text-gray-300'">★</button>
          </div>
          <div class="flex gap-2">
            <input v-model="reviewForm.content" type="text" placeholder="리뷰를 남겨주세요..." class="flex-1 border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 outline-none" />
            <button @click="submitReview" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm hover:bg-amber-500">등록</button>
          </div>
        </div>

        <div v-for="review in reviews" :key="review.id" class="px-5 py-3 border-b last:border-0">
          <div class="flex items-center gap-2 mb-1">
            <span class="text-amber-400 text-sm">{{ '★'.repeat(review.rating) }}{{ '☆'.repeat(5-review.rating) }}</span>
            <span class="text-sm font-semibold text-gray-700">{{ review.user?.name }}</span>
            <span class="text-xs text-gray-400">{{ formatDate(review.created_at) }}</span>
          </div>
          <div class="text-sm text-gray-600">{{ review.content }}</div>
        </div>
        <div v-if="!reviews.length" class="px-5 py-6 text-center text-sm text-gray-400">아직 리뷰가 없습니다</div>
      </div>
      </div>
      <!-- 사이드바 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block">
        <SidebarWidgets api-url="/api/businesses" detail-path="/directory/" :current-id="biz.id"
          label="업소" recommend-label="추천 업소" quick-label="최근 등록"
          :links="[{to:'/directory',icon:'📋',label:'전체 업소록'},{to:'/directory/register',icon:'✏️',label:'업소 등록'}]" />
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import LeafletMap from '../../components/LeafletMap.vue'
import axios from 'axios'
const route = useRoute()
const auth = useAuthStore()
const biz = ref(null)
const reviews = ref([])
const loading = ref(true)
const reviewForm = reactive({ rating: 5, content: '' })
function formatDate(dt) { return dt ? new Date(dt).toLocaleDateString('ko-KR') : '' }
async function submitReview() {
  if (!reviewForm.content.trim()) return
  try {
    await axios.post(`/api/businesses/${biz.value.id}/reviews`, reviewForm)
    const { data } = await axios.get(`/api/businesses/${biz.value.id}`)
    biz.value = data.data
    reviews.value = data.data.reviews || []
    reviewForm.content = ''
  } catch {}
}
onMounted(async () => {
  try {
    const { data } = await axios.get(`/api/businesses/${route.params.id}`)
    biz.value = data.data
    reviews.value = data.data.reviews || []
  } catch {}
  loading.value = false
})
</script>
