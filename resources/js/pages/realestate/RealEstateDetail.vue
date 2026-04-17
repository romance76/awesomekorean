<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-black text-gray-800">🏠 부동산</h1>
      <RouterLink v-if="auth.isLoggedIn" to="/realestate/write" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs">✏️ 등록</RouterLink>
    </div>

    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="listing" class="grid grid-cols-12 gap-4">

      <!-- 왼쪽: 카테고리 (리스트와 동일) -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto space-y-3 pr-0.5">
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 부동산</div>
            <div class="flex border-b">
              <RouterLink to="/realestate?type=rent" class="flex-1 py-1.5 text-[10px] font-bold text-center transition"
                :class="listing.type==='rent' ? 'bg-blue-500 text-white' : 'text-gray-400 hover:bg-gray-50'">🔑 렌트</RouterLink>
              <RouterLink to="/realestate?type=sale" class="flex-1 py-1.5 text-[10px] font-bold text-center transition"
                :class="listing.type==='sale' ? 'bg-red-500 text-white' : 'text-gray-400 hover:bg-gray-50'">🏠 매매</RouterLink>
            </div>
            <RouterLink to="/realestate" class="block w-full text-left px-3 py-1.5 text-xs text-gray-600 hover:bg-amber-50/50">전체</RouterLink>
            <template v-for="group in sideSubcats" :key="group.label">
              <div class="px-3 py-1 bg-gray-50 text-[9px] text-gray-500 font-bold border-t">{{ group.label }}</div>
              <RouterLink v-for="c in group.items" :key="c.value"
                :to="`/realestate?type=${listing.type}&cat=${c.value}`"
                class="block px-3 py-1 text-[11px] pl-5 transition cursor-pointer"
                :class="listing.property_type === c.value ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/30'">
                {{ c.label }}
              </RouterLink>
            </template>
            <!-- 내 하트 -->
            <RouterLink to="/realestate?fav=1" class="block w-full text-left px-3 py-1.5 text-xs transition border-t text-gray-600 hover:bg-red-50/50">
              🔖 내 북마크<span v-if="favCount > 0" class="ml-0.5 text-red-500 font-bold">({{ favCount }})</span>
            </RouterLink>
          </div>
          <AdSlot page="realestate" position="left" :maxSlots="2" />
        </div>
      </div>

      <!-- 중앙: 상세 -->
      <div class="col-span-12 lg:col-span-7 space-y-4">
        <!-- 사진 갤러리 (전체 폭) -->
        <div v-if="allPhotos.length" class="bg-white rounded-xl shadow-sm border overflow-hidden">
          <div class="relative cursor-pointer" @click="openLightbox(activePhotoIdx)">
            <img :src="photoUrl(allPhotos[activePhotoIdx])" style="width:100%; height:400px; object-fit:cover;" />
            <div class="absolute bottom-3 right-3 bg-black/60 text-white text-xs px-2 py-1 rounded">
              {{ activePhotoIdx + 1 }} / {{ allPhotos.length }}
            </div>
          </div>
          <div v-if="allPhotos.length > 1" class="flex gap-1 p-2 overflow-x-auto bg-gray-50">
            <div v-for="(p, idx) in allPhotos" :key="idx" @click="activePhotoIdx = idx"
              class="flex-shrink-0 rounded cursor-pointer border-2 transition overflow-hidden"
              :class="idx === activePhotoIdx ? 'border-amber-400' : 'border-transparent hover:border-gray-300'"
              style="width:60px; height:45px;">
              <img :src="photoUrl(p)" style="width:100%;height:100%;object-fit:cover;" />
            </div>
          </div>
        </div>
        <div v-else class="bg-gray-100 rounded-xl flex items-center justify-center text-4xl" style="height:200px;">🏠</div>

        <!-- 가격/정보 + 판매자 (나란히) -->
        <div class="flex gap-3">
          <!-- 왼쪽: 매물 정보 -->
          <div class="flex-1 min-w-0 bg-white rounded-xl shadow-sm p-4"
            :style="promoBorderStyle">
            <!-- 1행: 태그 + ❤️🚨 -->
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <span class="text-[10px] px-1.5 py-0.5 rounded-full font-bold" :class="listing.type==='sale'?'bg-red-100 text-red-700':'bg-blue-100 text-blue-700'">
                  {{ listing.type==='sale' ? '매매' : '렌트' }}
                </span>
                <span class="text-[10px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded-full font-bold">{{ listing.property_type }}</span>
                <span v-if="listing.promotion_tier==='national'" class="text-[9px] bg-red-500 text-white font-bold px-1.5 py-0.5 rounded">🌐 전국구</span>
                <span v-else-if="listing.promotion_tier==='state_plus'" class="text-[9px] bg-blue-500 text-white font-bold px-1.5 py-0.5 rounded">⭐ 주+</span>
                <span v-else-if="listing.promotion_tier==='sponsored'" class="text-[9px] bg-amber-500 text-white font-bold px-1.5 py-0.5 rounded">📢 스폰서</span>
              </div>
              <div class="flex items-center gap-3">
                <BookmarkToggle :active="isFavorited" @toggle="toggleFav" size="lg" />
                <button v-if="listing.user_id !== auth.user?.id" @click="reportListing"
                  class="text-lg hover:scale-125 transition" :style="isReported ? '' : 'filter:grayscale(100%);opacity:0.35;'"
                  :title="isReported ? '신고됨' : '신고'">🚨</button>
              </div>
            </div>
            <!-- 가격 -->
            <div class="text-2xl font-black text-gray-900">${{ Number(listing.price).toLocaleString() }}<span v-if="listing.type==='rent'" class="text-sm font-bold text-gray-500">/월</span></div>
            <h1 class="text-base font-bold text-gray-800 mt-1">{{ listing.title }}</h1>
            <div class="text-xs text-gray-500 mt-1">📍 {{ listing.address ? listing.address + ', ' : '' }}{{ listing.city }}, {{ listing.state }} {{ listing.zipcode }}</div>
            <!-- 스펙 + 등록일 -->
            <div class="flex items-center gap-4 mt-3 pt-3 border-t">
              <div v-if="listing.bedrooms" class="text-center"><div class="text-lg font-black text-gray-800">{{ listing.bedrooms }}</div><div class="text-[10px] text-gray-500">beds</div></div>
              <div v-if="listing.bathrooms" class="text-center"><div class="text-lg font-black text-gray-800">{{ listing.bathrooms }}</div><div class="text-[10px] text-gray-500">baths</div></div>
              <div v-if="listing.sqft" class="text-center"><div class="text-lg font-black text-gray-800">{{ Number(listing.sqft).toLocaleString() }}</div><div class="text-[10px] text-gray-500">sqft</div></div>
              <div class="text-center"><div class="text-lg font-black text-gray-800">{{ listing.view_count || 0 }}</div><div class="text-[10px] text-gray-500">조회</div></div>
              <div class="ml-auto text-xs text-gray-600 text-right font-semibold">
                등록일: {{ fmtDate(listing.created_at) }}
              </div>
            </div>
          </div>

          <!-- 오른쪽: 판매자 정보 (친구추가/쪽지 + 전화/이메일) -->
          <div class="hidden lg:block flex-shrink-0" style="width:200px;">
            <div class="bg-white rounded-xl shadow-sm border overflow-hidden h-full">
              <div class="px-3 py-2 border-b bg-amber-50 font-bold text-[11px] text-amber-900">📋 판매자 정보</div>
              <div class="p-3 space-y-2">
                <div v-if="listing.user" class="flex items-center gap-2">
                  <img v-if="listing.user.avatar" :src="listing.user.avatar" class="w-10 h-10 rounded-full object-cover border-2 border-amber-200" />
                  <div v-else class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center text-sm font-bold text-amber-700">
                    {{ (listing.user.nickname || listing.user.name || '?')[0] }}
                  </div>
                  <div class="min-w-0">
                    <UserName :userId="listing.user.id" :name="listing.user.real_name || listing.user.name" className="text-xs font-bold text-gray-800 truncate block" />
                    <div class="text-[9px] text-gray-400">가입: {{ fmtDate(listing.user.created_at) }}</div>
                  </div>
                </div>
                <!-- 친구추가/쪽지 -->
                <div v-if="listing.user_id !== auth.user?.id" class="flex gap-1.5 pt-2 border-t">
                  <button @click="sendFriendRequest" class="flex-1 text-[11px] bg-green-50 text-green-700 font-bold py-1.5 rounded-lg hover:bg-green-100">👫 친구</button>
                  <button @click="sendMessage" class="flex-1 text-[11px] bg-blue-50 text-blue-700 font-bold py-1.5 rounded-lg hover:bg-blue-100">✉️ 쪽지</button>
                </div>
                <!-- 전화/이메일 (등록자가 입력한 경우만) -->
                <div v-if="listing.contact_phone || listing.contact_email" class="space-y-1.5 pt-2 border-t">
                  <a v-if="listing.contact_phone" :href="'tel:'+listing.contact_phone"
                    class="flex items-center justify-center w-full bg-amber-400 text-amber-900 font-bold py-1.5 rounded-lg hover:bg-amber-500 text-[11px]">
                    📱 {{ listing.contact_phone }}
                  </a>
                  <a v-if="listing.contact_email" :href="'mailto:'+listing.contact_email"
                    class="flex items-center justify-center w-full bg-gray-100 text-gray-700 font-bold py-1.5 rounded-lg hover:bg-gray-200 text-[11px]">
                    📧 이메일
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 설명 -->
        <div class="bg-white rounded-xl shadow-sm border p-4">
          <h2 class="font-bold text-sm text-gray-800 mb-2">📝 상세 설명</h2>
          <div class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ listing.content }}</div>
        </div>

        <!-- 위치 (Leaflet 지도) -->
        <div v-if="listing.lat && listing.lng" class="bg-white rounded-xl shadow-sm border overflow-hidden">
          <h2 class="font-bold text-sm text-gray-800 px-4 pt-3 mb-1">📍 위치</h2>
          <div ref="mapEl" style="height:280px; width:100%; z-index:0;"></div>
        </div>

        <!-- 수정/삭제 -->
        <div v-if="isOwner" class="flex items-center gap-3 justify-end">
          <RouterLink :to="`/realestate/write?edit=${listing.id}`" class="text-sm text-amber-600 hover:text-amber-800">✏️ 수정</RouterLink>
          <button @click="deleteListing" class="text-sm text-red-400 hover:text-red-600">🗑 삭제</button>
        </div>

        <CommentSection v-if="listing.id" type="realestate" :typeId="listing.id" />
      </div>

      <!-- 오른쪽: 위젯 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block space-y-3">
        <SidebarWidgets mode="detail" :currentCategory="listing?.property_type || ''" api-url="/api/realestate" detail-path="/realestate/" :current-id="listing.id"
          label="매물"
          :filter-params="{ ...(listing.lat && listing.lng ? { lat: listing.lat, lng: listing.lng, radius: 50 } : {}), property_type: listing.property_type, type: listing.type }" />
        <AdSlot page="realestate" position="right" :maxSlots="2" />
      </div>
    </div>
  </div>

  <!-- 라이트박스 -->
  <div v-if="lightboxIdx !== null" class="fixed inset-0 bg-black/95 z-50 flex flex-col items-center justify-center" @click.self="lightboxIdx=null">
    <button class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300 z-10" @click="lightboxIdx=null">✕</button>
    <img v-if="allPhotos[lightboxIdx]" :src="photoUrl(allPhotos[lightboxIdx])" style="max-width:90vw;max-height:75vh;object-fit:contain;border-radius:8px;" />
    <div class="flex gap-2 mt-4 overflow-x-auto px-4">
      <div v-for="(p, idx) in allPhotos" :key="idx" @click="lightboxIdx=idx"
        class="flex-shrink-0 rounded cursor-pointer border-2 overflow-hidden"
        :class="idx === lightboxIdx ? 'border-amber-400' : 'border-transparent'"
        style="width:50px; height:38px;">
        <img :src="photoUrl(p)" style="width:100%;height:100%;object-fit:cover;" />
      </div>
    </div>
    <div class="text-white text-sm mt-2">{{ lightboxIdx + 1 }} / {{ allPhotos.length }}</div>
  </div>

  <!-- 쪽지 모달 -->
  <MessageModal :show="msgModal" :userId="listing?.user_id" :userName="listing?.user?.nickname || listing?.user?.name || ''"
    @close="msgModal=false" @sent="msgModal=false" />

  <!-- 신고 모달 -->
  <ReportModal :show="reportModal" reportableType="App\Models\RealEstateListing" :reportableId="listing?.id"
    contentType="trade" @close="reportModal=false" @reported="isReported=true; reportModal=false" />

</div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import CommentSection from '../../components/CommentSection.vue'
import AdSlot from '../../components/AdSlot.vue'
import ReportModal from '../../components/ReportModal.vue'
import MessageModal from '../../components/MessageModal.vue'
import { useFriendAction } from '../../composables/useSocialActions'
import { useSiteStore } from '../../stores/site'
import axios from 'axios'
import BookmarkToggle from '../../components/BookmarkToggle.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const listing = ref(null)
const loading = ref(true)
const lightboxIdx = ref(null)
const activePhotoIdx = ref(0)
const siteStore = useSiteStore()
const msgModal = ref(false)
const isFavorited = ref(false)
const isReported = ref(false)
const favCount = ref(0)
const reportModal = ref(false)

// 지도
const mapEl = ref(null)
let mapInstance = null

// 왼쪽 사이드바 카테고리 데이터
const rentSubcats = [
  { label: '주거용', items: [
    { value: 'studio', label: '스튜디오' },{ value: '1br', label: '1BR' },{ value: '2br', label: '2BR' },
    { value: '3br_plus', label: '3BR 이상' },{ value: 'roommate', label: '룸메이트' },
    { value: 'minbak', label: '민박' },{ value: 'etc_home', label: '기타' },
  ]},
  { label: '상업용', items: [
    { value: 'office_rent', label: '오피스' },{ value: 'retail_rent', label: '소매' },
    { value: 'store_rent', label: '상가' },{ value: 'building_rent', label: '건물' },{ value: 'etc_commercial', label: '기타' },
  ]},
]
const saleSubcats = [
  { label: '주거용 매매', items: [
    { value: 'house', label: '하우스' },{ value: 'condo', label: '콘도' },
    { value: 'duplex', label: '듀플렉스' },{ value: 'villa', label: '빌라' },
    { value: 'townhouse', label: '타운하우스' },{ value: 'etc_home', label: '기타' },
  ]},
  { label: '상업용 매매', items: [
    { value: 'office_sale', label: '오피스' },{ value: 'retail_sale', label: '소매' },
    { value: 'store_sale', label: '상가' },{ value: 'building', label: '건물' },{ value: 'etc_commercial', label: '기타' },
  ]},
]
const sideSubcats = computed(() => listing.value?.type === 'sale' ? saleSubcats : rentSubcats)

// 프로모션 보더
const promoBorderStyle = computed(() => {
  const t = listing.value?.promotion_tier
  if (t === 'national') return 'border: 2px solid #fca5a5; border-radius: 12px;'
  if (t === 'state_plus') return 'border: 2px solid #93c5fd; border-radius: 12px;'
  if (t === 'sponsored') return 'border: 2px solid #fde68a; border-radius: 12px;'
  return 'border: 1px solid #e5e7eb; border-radius: 12px;'
})

async function checkFav() {
  if (!auth.isLoggedIn || !listing.value) return
  try {
    const { data } = await axios.get('/api/bookmarks/check', {
      params: { type: 'App\\Models\\RealEstateListing', ids: listing.value.id },
    })
    isFavorited.value = (data.data || []).includes(listing.value.id)
  } catch {}
}
async function toggleFav() {
  if (!auth.isLoggedIn) return
  try {
    const { data } = await axios.post('/api/bookmarks', {
      bookmarkable_type: 'App\\Models\\RealEstateListing',
      bookmarkable_id: listing.value.id,
    })
    isFavorited.value = data.bookmarked
    if (data.bookmarked) favCount.value++
    else favCount.value = Math.max(0, favCount.value - 1)
  } catch {}
}
async function loadFavCount() {
  if (!auth.isLoggedIn) return
  try {
    const { data } = await axios.get('/api/bookmarks', { params: { type: 'App\\Models\\RealEstateListing', per_page: 1 } })
    favCount.value = data.data?.total || 0
  } catch {}
}

const allPhotos = computed(() => {
  const imgs = listing.value?.images
  return Array.isArray(imgs) ? imgs : []
})

const isOwner = computed(() => auth.user?.id === listing.value?.user_id || auth.user?.is_admin)

function photoUrl(path) {
  if (!path) return ''
  return String(path).startsWith('http') || String(path).startsWith('/storage/') ? path : '/storage/' + path
}
function openLightbox(idx) { lightboxIdx.value = idx }
function fmtDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  return `${d.getFullYear()}.${d.getMonth()+1}.${d.getDate()}`
}
async function deleteListing() {
  if (!confirm('정말 삭제하시겠습니까?')) return
  try { await axios.delete(`/api/realestate/${listing.value.id}`); router.push('/realestate') } catch {}
}

// 판매자 액션
const { sendRequest: doSendFriend, loading: friendLoading } = useFriendAction()
async function sendFriendRequest() { await doSendFriend(listing.value.user_id) }
function sendMessage() { msgModal.value = true }
function reportListing() { reportModal.value = true }

// ── Leaflet 지도 ──
async function ensureLeaflet() {
  if (window.L) return
  if (!document.querySelector('link[href*="leaflet"]')) {
    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
    document.head.appendChild(link)
  }
  if (!window.L) {
    await new Promise((resolve, reject) => {
      const s = document.createElement('script')
      s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
      s.onload = resolve; s.onerror = reject
      document.head.appendChild(s)
    })
  }
}

async function initMap() {
  if (!listing.value?.lat || !listing.value?.lng || !mapEl.value) return
  // mapEl이 실제 크기를 가질 때까지 대기
  if (mapEl.value.offsetHeight === 0) {
    await new Promise(r => setTimeout(r, 200))
    if (!mapEl.value || mapEl.value.offsetHeight === 0) return
  }
  await ensureLeaflet()
  const lat = Number(listing.value.lat), lng = Number(listing.value.lng)
  mapInstance = window.L.map(mapEl.value, { zoomControl: true, scrollWheelZoom: true }).setView([lat, lng], 14)
  window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap', maxZoom: 18,
  }).addTo(mapInstance)
  // 매물 마커 (빨간)
  const homeIcon = window.L.divIcon({
    className: '',
    html: '<div style="background:#ef4444;width:16px;height:16px;border-radius:50%;border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.4);"></div>',
    iconSize: [16, 16], iconAnchor: [8, 8],
  })
  window.L.marker([lat, lng], { icon: homeIcon }).addTo(mapInstance)
    .bindPopup(`<b>📍 매물 위치</b><br>${listing.value.address || ''}`)
}

onMounted(async () => {
  try {
    const { data } = await axios.get(`/api/realestate/${route.params.id}`)
    listing.value = data.data
    checkFav()
    loadFavCount()
    await nextTick()
    // Leaflet은 DOM이 완전히 렌더된 후 초기화해야 함
    setTimeout(() => initMap(), 100)
  } catch (err) {
    if (err.response?.status === 404) router.replace('/404')
  }
  loading.value = false
})
</script>
