<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <button @click="$router.back()" class="btn-ghost text-sm mb-3"><AppIcon name="arrow-left" :size="14" />업소록</button>
    <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
    <div v-else-if="biz" class="grid grid-cols-12 gap-4">
      <div class="col-span-12 lg:col-span-9">
      <!-- 업소 정보 -->
      <div class="card overflow-hidden mb-4">
        <div class="px-5 py-4">
          <div class="flex items-center gap-2 mb-2">
            <span class="badge-primary">{{ biz.category }}</span>
            <span v-if="biz.subcategory" class="badge-gray">{{ biz.subcategory }}</span>
          </div>
          <div class="flex items-center justify-between gap-2">
            <h1 class="text-lg font-bold text-ink">{{ biz.name }}</h1>
            <BookmarkToggle v-if="auth.isLoggedIn" :active="isFavorited" @toggle="toggleFav" size="lg" class="flex-shrink-0" />
          </div>
          <div class="flex items-center gap-1 mt-1">
            <span class="text-amber-400">{{ '★'.repeat(Math.round(biz.rating)) }}{{ '☆'.repeat(5 - Math.round(biz.rating)) }}</span>
            <span class="text-sm text-ink-light font-semibold">{{ biz.rating }}</span>
            <span class="text-xs text-ink-muted">({{ biz.review_count }}개 리뷰)</span>
          </div>
        </div>
        <div class="px-5 py-3 border-t border-gray-50 space-y-2 text-sm text-ink-light">
          <div v-if="biz.phone" class="flex items-center gap-1.5"><AppIcon name="phone" :size="14" class="text-ink-muted" /><a :href="'tel:'+biz.phone" class="text-amber-600 hover:underline">{{ biz.phone }}</a></div>
          <div v-if="biz.address" class="flex items-center gap-1.5"><AppIcon name="map-pin" :size="14" class="text-ink-muted" />{{ biz.address }}, {{ biz.city }}, {{ biz.state }} {{ biz.zipcode }}</div>
          <div v-if="biz.website" class="flex items-center gap-1.5"><AppIcon name="globe" :size="14" class="text-ink-muted" /><a :href="biz.website" target="_blank" class="text-amber-600 hover:underline truncate">{{ biz.website }}</a></div>
          <div v-if="biz.email" class="flex items-center gap-1.5"><AppIcon name="mail" :size="14" class="text-ink-muted" />{{ biz.email }}</div>
        </div>
        <div v-if="biz.hours" class="px-5 py-3 border-t border-gray-50">
          <h3 class="font-bold text-sm text-ink mb-2 flex items-center gap-1.5"><AppIcon name="clock" :size="14" class="text-ink-muted" />영업시간</h3>
          <div class="grid grid-cols-2 gap-1 text-xs text-ink-light">
            <div v-for="(time, day) in biz.hours" :key="day">
              <span class="font-semibold">{{ {mon:'월',tue:'화',wed:'수',thu:'목',fri:'금',sat:'토',sun:'일'}[day] || day }}:</span> {{ time }}
            </div>
          </div>
        </div>
        <div v-if="biz.description" class="px-5 py-4 border-t border-gray-50 text-sm text-ink-light whitespace-pre-wrap">{{ biz.description }}</div>
        <!-- 상위노출 (소유자만) -->
        <div v-if="isOwner" class="px-5 py-4 border-t border-gray-50">
          <BoostButton resource="businesses" :item="biz" size="md" @updated="reload" />
        </div>
        <!-- 지도 -->
        <div v-if="biz.address || (biz.lat && biz.lng)" class="px-5 py-4 border-t border-gray-50">
          <h3 class="font-bold text-sm text-ink mb-2 flex items-center gap-1.5"><AppIcon name="map-pin" :size="14" class="text-ink-muted" />위치</h3>
          <LeafletMap :address="biz.address ? `${biz.address}, ${biz.city}, ${biz.state}` : ''" :lat="biz.lat" :lng="biz.lng" style="height:200px" class="rounded-lg overflow-hidden" />
        </div>
      </div>

      <!-- 리뷰 -->
      <div class="card overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-50 font-bold text-sm text-ink flex items-center gap-1.5"><AppIcon name="star" :size="15" class="text-amber-400" />리뷰 {{ biz.review_count }}개</div>

        <!-- 리뷰 작성 -->
        <div v-if="auth.isLoggedIn" class="px-5 py-3 border-b border-gray-50">
          <div class="flex items-center gap-2 mb-2">
            <span class="text-sm text-ink-light">별점:</span>
            <button v-for="s in 5" :key="s" @click="reviewForm.rating = s" class="text-xl transition" :class="s <= reviewForm.rating ? 'text-amber-400' : 'text-gray-300'">★</button>
          </div>
          <div class="flex gap-2">
            <input v-model="reviewForm.content" type="text" placeholder="리뷰를 남겨주세요..." class="input-soft flex-1" />
            <button @click="submitReview" class="btn-primary px-4 py-2 rounded-lg text-sm">등록</button>
          </div>
        </div>

        <div v-for="review in reviews" :key="review.id" class="px-5 py-3 border-b border-gray-50 last:border-0">
          <div class="flex items-center gap-2 mb-1">
            <span class="text-amber-400 text-sm">{{ '★'.repeat(review.rating) }}{{ '☆'.repeat(5-review.rating) }}</span>
            <span class="text-sm font-semibold text-ink">{{ review.user?.name }}</span>
            <span class="text-xs text-ink-faint">{{ formatDate(review.created_at) }}</span>
          </div>
          <div class="text-sm text-ink-light">{{ review.content }}</div>
        </div>
        <div v-if="!reviews.length" class="px-5 py-6 text-center text-sm text-ink-muted">아직 리뷰가 없습니다</div>
      </div>

      <!-- 댓글 -->
      <CommentSection v-if="biz.id" :type="'business'" :typeId="biz.id" class="mt-4" />
      <MobileBanner page="directory" class="lg:hidden mt-3" />

      <!-- 이전글 / 목록 / 다음글 -->
      <PostNavigator v-if="biz.id" :prev-id="prev?.id" :prev-title="prev?.name || prev?.title"
        :next-id="next?.id" :next-title="next?.name || next?.title"
        list-path="/directory" detail-base="/directory/" />
      </div>
      <!-- 사이드바 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block">
        <SidebarWidgets mode="detail" :currentCategory="biz?.category || ''" api-url="/api/businesses" detail-path="/directory/" :current-id="biz.id"
          label="업소" recommend-label="추천 업소" quick-label="최근 등록"
          :filter-params="biz.lat && biz.lng ? { lat: biz.lat, lng: biz.lng, radius: 50 } : {}"
          :links="[{to:'/directory',icon:'📋',label:'전체 업소록'},{to:'/directory/register',icon:'✏️',label:'업소 등록'}]" />
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import CommentSection from '../../components/CommentSection.vue'
import MobileBanner from '../../components/MobileBanner.vue'
import PostNavigator from '../../components/PostNavigator.vue'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import LeafletMap from '../../components/LeafletMap.vue'
import BoostButton from '../../components/BoostButton.vue'
import AppIcon from '../../components/AppIcon.vue'
import BookmarkToggle from '../../components/BookmarkToggle.vue'
import axios from 'axios'
const BM_TYPE = 'App\\Models\\Business'
const route = useRoute()
const auth = useAuthStore()
const biz = ref(null)
const isFavorited = ref(false)
const prev = ref(null)
const next = ref(null)
const reviews = ref([])
const loading = ref(true)
const reviewForm = reactive({ rating: 5, content: '' })
const isOwner = computed(() => biz.value && auth.user?.id && String(biz.value.owner_id || biz.value.user_id) === String(auth.user.id))
async function reload() {
  try {
    const { data } = await axios.get(`/api/businesses/${route.params.id}`)
    biz.value = data.data
    prev.value = data.prev || null
    next.value = data.next || null
    reviews.value = data.data.reviews || []
  } catch {}
}
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
async function loadFavorited() {
  if (!auth.isLoggedIn || !biz.value) return
  try {
    const { data } = await axios.get('/api/bookmarks/check', {
      params: { type: BM_TYPE, ids: biz.value.id },
    })
    isFavorited.value = (data.data || []).includes(biz.value.id)
  } catch {}
}
async function toggleFav() {
  if (!auth.isLoggedIn) return
  try {
    const { data } = await axios.post('/api/bookmarks', {
      bookmarkable_type: BM_TYPE,
      bookmarkable_id: biz.value.id,
    })
    isFavorited.value = data.bookmarked
  } catch {}
}
onMounted(async () => {
  try {
    const { data } = await axios.get(`/api/businesses/${route.params.id}`)
    biz.value = data.data
    prev.value = data.prev || null
    next.value = data.next || null
    reviews.value = data.data.reviews || []
    await loadFavorited()
  } catch {}
  loading.value = false
})
</script>
