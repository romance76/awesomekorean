<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-black text-gray-800">🛒 중고장터</h1>
    </div>

    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="item" class="grid grid-cols-12 gap-4">

      <!-- 왼쪽: 카테고리 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto space-y-3 pr-0.5">
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 카테고리</div>
            <RouterLink v-for="c in categories" :key="c.value" :to="c.value ? `/market?category=${c.value}` : '/market'"
              class="block w-full text-left px-3 py-2 text-xs transition"
              :class="item.category === c.value ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">
              {{ c.label }}
            </RouterLink>
            <button v-if="auth.isLoggedIn" @click="$router.push('/market?fav=1')"
              class="w-full text-left px-3 py-2 text-xs transition border-t text-gray-600 hover:bg-red-50/50">
              🔖 내 북마크<span v-if="favCount > 0" class="ml-0.5">({{ favCount }})</span>
            </button>
          </div>
          <AdSlot page="market" position="left" :maxSlots="2" />
        </div>
      </div>

      <!-- 가운데: 상세 -->
      <div class="col-span-12 lg:col-span-7 space-y-4">
        <!-- 사진 갤러리 (전체 폭) -->
        <div v-if="item.images?.length" class="bg-white rounded-xl shadow-sm border overflow-hidden">
          <div class="relative cursor-pointer" @click="lightboxImg = mainImage">
            <img :src="mainImage" style="width:100%; height:380px; object-fit:contain; background:#f9fafb;" />
          </div>
          <div v-if="item.images.length > 1" class="flex gap-1 p-2 overflow-x-auto bg-gray-50">
            <div v-for="(img, i) in item.images" :key="i" @click="selectedImgIdx = i"
              class="flex-shrink-0 rounded cursor-pointer border-2 transition overflow-hidden"
              :class="i === selectedImgIdx ? 'border-amber-400' : 'border-transparent hover:border-gray-300'"
              style="width:60px; height:45px;">
              <img :src="getImageUrl(img)" style="width:100%;height:100%;object-fit:cover;" />
            </div>
          </div>
        </div>
        <div v-else class="bg-gray-100 rounded-xl flex items-center justify-center text-5xl" style="height:200px;">📦</div>

        <!-- 가격/정보 + 판매자 (나란히) — 부동산 스타일 -->
        <div class="flex gap-3">
          <!-- 왼쪽: 상품 정보 -->
          <div class="flex-1 min-w-0 bg-white rounded-xl shadow-sm p-4"
            :style="item.promotion_tier && item.promotion_tier !== 'none' ? promoBorderStyle : 'border: 1px solid #e5e7eb; border-radius: 12px;'">
            <!-- 1행: 뱃지 + ❤️🚨 -->
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs px-2 py-0.5 rounded-full font-bold"
                  :class="{'bg-green-100 text-green-700':item.status==='active','bg-amber-100 text-amber-700':item.status==='reserved','bg-gray-200 text-gray-500':item.status==='sold'}">
                  {{ {active:'판매중',reserved:'예약중',sold:'판매완료'}[item.status] }}
                </span>
                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ conditionLabel }}</span>
                <span v-if="item.is_negotiable" class="text-[10px] text-amber-600 font-semibold">가격협의</span>
                <span v-if="item.hold_enabled" class="text-[10px] px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700">🔒 홀드가능</span>
              </div>
              <div class="flex items-center gap-3">
                <BookmarkToggle :active="liked" @toggle="toggleLike" size="lg" />
                <button @click="showReport = true" class="text-lg hover:scale-125 transition" style="filter:grayscale(100%);opacity:0.35;">🚨</button>
              </div>
            </div>
            <!-- 가격 -->
            <div class="text-2xl font-black text-amber-600">${{ Number(item.price).toLocaleString() }}</div>
            <h1 class="text-base font-bold text-gray-800 mt-1">{{ item.title }}</h1>
            <div class="text-xs text-gray-500 mt-1">📍 {{ item.city }}, {{ item.state }}</div>
            <!-- 스펙 + 등록일 -->
            <div class="flex items-center gap-4 mt-3 pt-3 border-t">
              <div class="text-center"><div class="text-lg font-black text-gray-800">{{ item.view_count || 0 }}</div><div class="text-[10px] text-gray-500">조회</div></div>
              <div class="ml-auto text-xs text-gray-600 text-right font-semibold">
                등록일: {{ formatFullDate(item.created_at) }}
              </div>
            </div>
          </div>

          <!-- 오른쪽: 판매자 정보 (부동산 스타일) -->
          <div class="hidden lg:block flex-shrink-0" style="width:200px;">
            <div class="bg-white rounded-xl shadow-sm border overflow-hidden h-full">
              <div class="px-3 py-2 border-b bg-amber-50 font-bold text-[11px] text-amber-900">📋 판매자 정보</div>
              <div class="p-3 space-y-2">
                <div v-if="item.user" class="flex items-center gap-2">
                  <img v-if="item.user.avatar" :src="'/storage/' + item.user.avatar" class="w-10 h-10 rounded-full object-cover border-2 border-amber-200" @error="e => e.target.style.display='none'" />
                  <div v-else class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center text-sm font-bold text-amber-700">
                    {{ (item.user.nickname || item.user.name || '?')[0] }}
                  </div>
                  <div class="min-w-0">
                    <div class="text-xs font-bold text-gray-800 truncate">{{ item.user?.nickname || item.user?.name }}</div>
                    <div class="text-[9px] text-gray-400">가입: {{ formatFullDate(item.user?.created_at) }}</div>
                    <div class="text-[9px] text-amber-600 font-semibold">거래 {{ sellerTradeCount }}회</div>
                  </div>
                </div>
                <!-- 친구/쪽지 -->
                <div v-if="auth.isLoggedIn && !isOwner" class="flex gap-1.5 pt-2 border-t">
                  <button @click="addFriend" class="flex-1 text-[11px] bg-green-50 text-green-700 font-bold py-1.5 rounded-lg hover:bg-green-100">👫 친구</button>
                  <button @click="sendMessage" class="flex-1 text-[11px] bg-blue-50 text-blue-700 font-bold py-1.5 rounded-lg hover:bg-blue-100">✉️ 쪽지</button>
                </div>
                <!-- 전화 (있으면) -->
                <a v-if="item.user?.phone" :href="'tel:'+item.user.phone"
                  class="flex items-center justify-center w-full bg-amber-400 text-amber-900 font-bold py-1.5 rounded-lg hover:bg-amber-500 text-[11px]">
                  📱 {{ item.user.phone }}
                </a>
                <!-- 홀드 -->
                <button v-if="item.hold_enabled && item.status==='active' && auth.isLoggedIn && !isOwner && !item.active_hold"
                  @click="showHoldModal = true" class="w-full bg-blue-500 text-white font-bold py-1.5 rounded-lg text-[11px] hover:bg-blue-600">
                  🔒 홀드 ({{ item.hold_price_per_6h }}P/6h)
                </button>
                <!-- 부스트 -->
                <button v-if="isOwner && item.status === 'active'" @click="showBoostModal = true"
                  class="w-full bg-purple-500 text-white font-bold py-1.5 rounded-lg text-[11px] hover:bg-purple-600">🚀 상위노출</button>
              </div>
            </div>
          </div>
        </div>

        <!-- 홀드 상태 + 수정/삭제 (모바일에서도 보이게) -->
        <div v-if="item.active_hold || isOwner || (item.hold_enabled && item.status==='active' && auth.isLoggedIn && !isOwner && !item.active_hold)" class="bg-white rounded-xl shadow-sm border p-4 space-y-2 lg:hidden">
          <div v-if="item.active_hold" class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
            <span class="text-amber-600 font-bold text-sm">🔒 홀드 중</span>
            <div class="text-xs text-gray-500">{{ item.active_hold.buyer?.nickname || item.active_hold.buyer?.name }}님 · 만료: {{ formatDateTime(item.active_hold.hold_until) }}</div>
          </div>
          <button v-if="item.hold_enabled && item.status==='active' && auth.isLoggedIn && !isOwner && !item.active_hold"
            @click="showHoldModal = true" class="w-full bg-blue-500 text-white font-bold py-2.5 rounded-xl text-sm hover:bg-blue-600">
            🔒 홀드하기 ({{ item.hold_price_per_6h }}P/6h)
          </button>
          <button v-if="isOwner && item.status === 'active'" @click="showBoostModal = true"
            class="w-full bg-purple-500 text-white font-bold py-2.5 rounded-xl text-sm hover:bg-purple-600">🚀 상위노출</button>
          <div v-if="isOwner" class="flex gap-2 pt-1 border-t mt-2">
            <RouterLink :to="`/market/write?edit=${item.id}`" class="flex-1 bg-gray-100 text-gray-700 font-semibold py-2 rounded-lg text-xs text-center">✏️ 수정</RouterLink>
            <button @click="deleteItem" class="flex-1 bg-red-50 text-red-600 font-semibold py-2 rounded-lg text-xs">🗑️ 삭제</button>
          </div>
        </div>

        <!-- 상세 설명 -->
        <div class="bg-white rounded-xl shadow-sm border p-4">
          <h2 class="font-bold text-sm text-gray-800 mb-2">📋 상세 설명</h2>
          <div class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ item.content }}</div>
        </div>

        <!-- 주의사항 -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
          <div class="flex items-start gap-2">
            <span class="text-amber-500 flex-shrink-0">⚠️</span>
            <div class="text-xs text-gray-500 leading-relaxed">
              <b class="text-gray-700">거래 전 주의!</b> 해당 게시글은 회원이 등록한 것으로 SomeKorean은 등록된 내용에 대하여 일체의 책임을 지지 않습니다.
              직거래 시 안전한 장소에서 만나시고, 선입금 요구에 주의하세요.
            </div>
          </div>
        </div>

        <!-- 수정/삭제 -->
        <div class="flex items-center gap-3 justify-end">
          <button @click="$router.back()" class="text-sm text-gray-400 hover:text-gray-600">← 목록</button>
        </div>

        <CommentSection v-if="item.id" :type="'market'" :typeId="item.id" />
      </div>

      <!-- 오른쪽: 위젯 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block space-y-3">
        <SidebarWidgets mode="detail" :currentCategory="item?.category || ''" :categoryLabel="categoryLabel"
          :inline="true" api-url="/api/market" detail-path="/market/" :current-id="item.id" label="물품"
          :filter-params="item.lat && item.lng ? { lat: item.lat, lng: item.lng, radius: 50 } : {}" />
        <AdSlot page="market" position="right" :maxSlots="2" />
      </div>
    </div>
  </div>

  <!-- 라이트박스 -->
  <div v-if="lightboxImg" class="fixed inset-0 bg-black/95 z-50 flex flex-col items-center justify-center" @click.self="lightboxImg=null">
    <button class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300 z-10" @click="lightboxImg=null">✕</button>
    <img :src="lightboxImg" style="max-width:90vw;max-height:85vh;object-fit:contain;border-radius:8px;" />
  </div>

  <!-- 홀드 모달 -->
  <div v-if="showHoldModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showHoldModal=false">
    <div class="bg-white rounded-2xl p-5 w-full max-w-sm">
      <h3 class="font-bold text-lg mb-3">🔒 홀드 신청</h3>
      <div class="text-xs text-gray-400 mb-3">6시간당 <b class="text-amber-600">{{ item.hold_price_per_6h }}P</b> · 최대 {{ item.hold_max_hours }}시간</div>
      <div class="grid grid-cols-3 gap-2 mb-3">
        <button v-for="h in holdOptions" :key="h" @click="holdHours = h"
          :class="holdHours === h ? 'bg-blue-500 text-white' : 'bg-gray-100'" class="py-2 rounded-lg text-sm font-bold">
          {{ h >= 24 ? (h/24) + '일' : h + '시간' }}
        </button>
      </div>
      <div class="bg-blue-50 rounded-lg p-3 mb-4 text-center">
        <div class="text-2xl font-black text-blue-600">{{ holdCost }}P</div>
        <div class="text-[10px] text-gray-400">판매자 {{ Math.floor(holdCost * 0.9) }}P · 수수료 {{ Math.ceil(holdCost * 0.1) }}P</div>
      </div>
      <div class="flex gap-2">
        <button @click="showHoldModal=false" class="flex-1 py-2 bg-gray-100 rounded-lg text-sm font-semibold">취소</button>
        <button @click="submitHold" :disabled="holdingInProgress" class="flex-1 py-2 bg-blue-500 text-white rounded-lg text-sm font-bold disabled:opacity-50">홀드 신청</button>
      </div>
    </div>
  </div>

  <!-- 부스트 모달 -->
  <div v-if="showBoostModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showBoostModal=false">
    <div class="bg-white rounded-2xl p-5 w-full max-w-sm">
      <h3 class="font-bold text-lg mb-3">🚀 상위노출</h3>
      <div class="grid grid-cols-3 gap-2 mb-3">
        <button @click="boostDays=1" :class="boostDays===1?'bg-purple-500 text-white':'bg-gray-100'" class="py-3 rounded-lg text-sm font-bold">1일<br><span class="text-xs">100P</span></button>
        <button @click="boostDays=3" :class="boostDays===3?'bg-purple-500 text-white':'bg-gray-100'" class="py-3 rounded-lg text-sm font-bold">3일<br><span class="text-xs">300P</span></button>
        <button @click="boostDays=7" :class="boostDays===7?'bg-purple-500 text-white':'bg-gray-100'" class="py-3 rounded-lg text-sm font-bold">7일<br><span class="text-xs">700P</span></button>
      </div>
      <div class="bg-purple-50 rounded-lg p-3 mb-4 text-center"><div class="text-2xl font-black text-purple-600">{{ boostDays * 100 }}P</div></div>
      <div class="flex gap-2">
        <button @click="showBoostModal=false" class="flex-1 py-2 bg-gray-100 rounded-lg text-sm font-semibold">취소</button>
        <button @click="submitBoost" :disabled="boostingInProgress" class="flex-1 py-2 bg-purple-500 text-white rounded-lg text-sm font-bold disabled:opacity-50">결제하기</button>
      </div>
    </div>
  </div>

  <!-- 신고 모달 -->
  <ReportModal :show="showReport" reportableType="App\Models\MarketItem" :reportableId="item?.id"
    contentType="trade" @close="showReport=false" @reported="showReport=false" />

  <!-- 쪽지 모달 -->
  <MessageModal :show="msgModal" :userId="item?.user_id" :userName="item?.user?.nickname || item?.user?.name || ''"
    @close="msgModal=false" @sent="msgModal=false" />
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import CommentSection from '../../components/CommentSection.vue'
import ReportModal from '../../components/ReportModal.vue'
import MessageModal from '../../components/MessageModal.vue'
import AdSlot from '../../components/AdSlot.vue'
import BookmarkToggle from '../../components/BookmarkToggle.vue'
import { useFriendAction, useBookmarkLike } from '../../composables/useSocialActions'
import { useBookmarkStore } from '../../stores/bookmarks'
import axios from 'axios'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const siteStore = useSiteStore()
const bStore = useBookmarkStore()
const BM_TYPE = 'App\\Models\\MarketItem'
const favCount = computed(() => bStore.getBookmarkedIds(BM_TYPE).length)
const item = ref(null)
const loading = ref(true)
const selectedImgIdx = ref(0)
const lightboxImg = ref(null)
const sellerTradeCount = ref(0)

const categories = [
  { value: '', label: '전체' },
  { value: 'electronics', label: '📱 전자기기' }, { value: 'furniture', label: '🪑 가구' },
  { value: 'clothing', label: '👕 의류' }, { value: 'auto', label: '🚗 자동차' },
  { value: 'baby', label: '👶 유아' }, { value: 'sports', label: '⚽ 스포츠' },
  { value: 'books', label: '📚 도서' }, { value: 'etc', label: '📋 기타' },
]

const isOwner = computed(() => item.value && String(item.value.user_id) === String(auth.user?.id))
const conditionLabel = computed(() => ({new:'새상품',like_new:'거의 새것',good:'양호',fair:'보통'})[item.value?.condition] || '')
const categoryLabel = computed(() => categories.find(c => c.value === item.value?.category)?.label || '전체')
const mainImage = computed(() => {
  if (!item.value?.images?.length) return null
  return getImageUrl(item.value.images[selectedImgIdx.value] || item.value.images[0])
})
const promoBorderStyle = computed(() => {
  const t = item.value?.promotion_tier
  if (t === 'national') return 'border: 2px solid #fca5a5; border-radius: 12px;'
  if (t === 'state_plus') return 'border: 2px solid #93c5fd; border-radius: 12px;'
  if (t === 'sponsored') return 'border: 2px solid #fde68a; border-radius: 12px;'
  return 'border: 1px solid #e5e7eb; border-radius: 12px;'
})

function getImageUrl(img) {
  if (!img) return ''
  return img.startsWith('http') || img.startsWith('/') ? img : '/storage/' + img
}

// 홀드
const showHoldModal = ref(false)
const holdHours = ref(6)
const holdingInProgress = ref(false)
const holdOptions = computed(() => [6, 12, 24, 48, 72, 168].filter(h => h <= (item.value?.hold_max_hours || 24)))
const holdCost = computed(() => Math.ceil(holdHours.value / 6) * (item.value?.hold_price_per_6h || 0))

async function submitHold() {
  if (!confirm(`${holdHours.value}시간 홀드에 ${holdCost.value}P 차감됩니다.`)) return
  holdingInProgress.value = true
  try {
    const { data } = await axios.post(`/api/market/${item.value.id}/hold`, { hours: holdHours.value })
    siteStore.toast(data.message, 'success'); showHoldModal.value = false; loadItem()
  } catch (e) { siteStore.toast(e.response?.data?.message || '홀드 실패', 'error') }
  holdingInProgress.value = false
}

// 부스트
const showBoostModal = ref(false)
const boostDays = ref(1)
const boostingInProgress = ref(false)
async function submitBoost() {
  if (!confirm(`${boostDays.value}일 상위노출에 ${boostDays.value * 100}P 차감`)) return
  boostingInProgress.value = true
  try {
    const { data } = await axios.post(`/api/market/${item.value.id}/boost`, { days: boostDays.value })
    siteStore.toast(data.message, 'success'); showBoostModal.value = false; loadItem()
  } catch (e) { siteStore.toast(e.response?.data?.message || '실패', 'error') }
  boostingInProgress.value = false
}

// 좋아요 (Bookmark API — bStore 동기화)
const { liked, check: checkLike, toggle: doToggleLike } = useBookmarkLike(BM_TYPE)
async function toggleLike() {
  const result = await bStore.toggle(BM_TYPE, item.value.id)
  if (result !== null) liked.value = result
}

// 친구 요청
const { sendRequest: doSendFriend } = useFriendAction()
async function addFriend() { await doSendFriend(item.value.user_id) }

// 쪽지
const msgModal = ref(false)
function sendMessage() { msgModal.value = true }

// 신고
const showReport = ref(false)
async function deleteItem() {
  if (!confirm('정말 삭제하시겠습니까?')) return
  try { await axios.delete(`/api/market/${item.value.id}`); router.push('/market') } catch {}
}

function formatDateTime(dt) { if (!dt) return ''; const d = new Date(dt); return d.toLocaleDateString('ko-KR') + ' ' + d.toLocaleTimeString('ko-KR', {hour:'2-digit',minute:'2-digit'}) }
function formatFullDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  return `${d.getFullYear()}.${d.getMonth()+1}.${d.getDate()}`
}
function timeAgo(dt) {
  if (!dt) return ''; const s = (Date.now() - new Date(dt)) / 1000
  if (s < 3600) return Math.floor(s/60) + '분 전'; if (s < 86400) return Math.floor(s/3600) + '시간 전'
  if (s < 604800) return Math.floor(s/86400) + '일 전'; return new Date(dt).toLocaleDateString('ko-KR')
}

async function loadItem() {
  try {
    const { data } = await axios.get(`/api/market/${route.params.id}`)
    item.value = data.data
    try {
      const { data: trades } = await axios.get(`/api/market?user_id=${item.value.user_id}&per_page=1`)
      sellerTradeCount.value = trades.data?.total || 0
    } catch {}
  } catch (err) {
    // Issue #17: 존재하지 않는 id → /404
    if (err.response?.status === 404) {
      router.replace('/404')
    } else {
      siteStore.toast?.('페이지를 불러올 수 없습니다', 'error')
    }
  }
}

onMounted(async () => {
  bStore.loadAll()
  await loadItem()
  loading.value = false
  if (item.value?.id) checkLike(item.value.id)
})
</script>
