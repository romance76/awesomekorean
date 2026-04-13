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
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 카테고리</div>
          <RouterLink v-for="c in categories" :key="c.value" :to="c.value ? `/market?category=${c.value}` : '/market'"
            class="block w-full text-left px-3 py-2 text-xs transition"
            :class="item.category === c.value ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">
            {{ c.label }}
          </RouterLink>
        </div>
      </div>

      <!-- 가운데: 이미지 + 설명 -->
      <div class="col-span-12 lg:col-span-7">
        <div class="mb-2">
          <span class="font-bold text-amber-700 text-sm">{{ categoryLabel }}</span>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <!-- 이미지 갤러리 -->
          <div v-if="item.images?.length" class="bg-gray-50 p-3">
            <div class="rounded-lg overflow-hidden bg-white flex items-center justify-center cursor-pointer mb-2" style="height: 360px;" @click="lightboxImg = mainImage">
              <img v-if="mainImage" :src="mainImage" class="max-w-full max-h-full object-contain" />
            </div>
            <div v-if="item.images.length > 1" class="flex gap-2 overflow-x-auto">
              <div v-for="(img, i) in item.images" :key="i" @click="selectedImgIdx = i"
                class="w-16 h-16 rounded-lg overflow-hidden border-2 cursor-pointer flex-shrink-0"
                :class="selectedImgIdx === i ? 'border-amber-400' : 'border-gray-200'">
                <img :src="getImageUrl(img)" class="w-full h-full object-cover" />
              </div>
            </div>
          </div>
          <div v-else class="h-48 bg-gray-100 flex items-center justify-center text-5xl">📦</div>

          <!-- 상세 설명 -->
          <div class="px-5 py-4 border-t">
            <h3 class="font-bold text-sm text-gray-800 mb-2">📋 상세 설명</h3>
            <div class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ item.content }}</div>
          </div>

          <!-- 주의사항 -->
          <div class="mx-5 mb-4 bg-gray-50 border border-gray-200 rounded-lg p-3">
            <div class="flex items-start gap-2">
              <span class="text-amber-500 flex-shrink-0">⚠️</span>
              <div class="text-xs text-gray-500 leading-relaxed">
                <b class="text-gray-700">거래 전 주의!</b> 해당 게시글은 회원이 등록한 것으로 SomeKorean은 등록된 내용에 대하여 일체의 책임을 지지 않습니다.
                직거래 시 안전한 장소에서 만나시고, 선입금 요구에 주의하세요.
              </div>
            </div>
          </div>

          <!-- 댓글 -->
          <CommentSection v-if="item.id" :type="'market'" :typeId="item.id" />
        </div>

        <div class="flex justify-between mt-3">
          <button @click="$router.back()" class="text-xs text-gray-400 hover:text-gray-600">← 목록</button>
        </div>
      </div>

      <!-- 오른쪽: 상품 정보 + 액션 + 판매자 -->
      <div class="col-span-12 lg:col-span-3">
        <div class="space-y-3 lg:sticky lg:top-20">

          <!-- 상품 정보 카드 -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <!-- 상태 뱃지 -->
            <div class="flex items-center gap-1.5 flex-wrap mb-2">
              <span class="text-xs px-2 py-0.5 rounded-full font-bold"
                :class="{'bg-green-100 text-green-700':item.status==='active','bg-amber-100 text-amber-700':item.status==='reserved','bg-gray-200 text-gray-500':item.status==='sold'}">
                {{ {active:'판매중',reserved:'예약중',sold:'판매완료'}[item.status] }}
              </span>
              <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ conditionLabel }}</span>
              <span v-if="item.is_negotiable" class="text-[10px] text-amber-600 font-semibold">가격협의</span>
              <span v-if="item.hold_enabled" class="text-[10px] px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700">🔒 홀드가능</span>
            </div>

            <!-- 제목 -->
            <h1 class="text-lg font-bold text-gray-900 leading-snug">{{ item.title }}</h1>

            <!-- 가격 -->
            <div class="text-2xl font-black text-amber-600 mt-2">${{ Number(item.price).toLocaleString() }}</div>

            <!-- 위치 + 조회 -->
            <div class="flex items-center gap-2 text-xs text-gray-500 mt-2">
              <span>📍 {{ item.city }}, {{ item.state }}</span>
              <span>👁 {{ item.view_count }}</span>
              <span>{{ timeAgo(item.created_at) }}</span>
            </div>
          </div>

          <!-- 액션 버튼 카드 -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 space-y-2">
            <!-- 홀드 상태 -->
            <div v-if="item.active_hold" class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-2">
              <span class="text-amber-600 font-bold text-sm">🔒 홀드 중</span>
              <div class="text-xs text-gray-500">{{ item.active_hold.buyer?.nickname || item.active_hold.buyer?.name }}님</div>
              <div class="text-xs text-amber-700">만료: {{ formatDateTime(item.active_hold.hold_until) }}</div>
            </div>

            <!-- 홀드 버튼 (취소 없음) -->
            <button v-if="item.hold_enabled && item.status==='active' && auth.isLoggedIn && !isOwner && !item.active_hold"
              @click="showHoldModal = true"
              class="w-full bg-blue-500 text-white font-bold py-3 rounded-xl text-sm hover:bg-blue-600">
              🔒 홀드하기 ({{ item.hold_price_per_6h }}P/6h)
            </button>

            <!-- 부스트 (판매자) -->
            <button v-if="isOwner && item.status === 'active'"
              @click="showBoostModal = true"
              class="w-full bg-purple-500 text-white font-bold py-3 rounded-xl text-sm hover:bg-purple-600">
              🚀 상위노출 (100P/일)
            </button>

            <!-- 좋아요 + 신고 -->
            <div class="flex gap-2">
              <button @click="toggleLike" class="flex-1 py-2.5 rounded-xl border text-sm font-semibold transition"
                :class="liked ? 'bg-red-50 border-red-200 text-red-500' : 'bg-white border-gray-200 text-gray-500 hover:text-red-400'">
                {{ liked ? '❤️ 좋아요' : '🤍 좋아요' }}
              </button>
              <button @click="showReport = true" class="px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-gray-500 text-sm hover:text-red-500">🚨</button>
            </div>

            <!-- 수정/삭제 -->
            <div v-if="isOwner" class="flex gap-2 pt-1 border-t mt-2">
              <RouterLink :to="`/market/write?edit=${item.id}`" class="flex-1 bg-gray-100 text-gray-700 font-semibold py-2 rounded-lg text-xs text-center">✏️ 수정</RouterLink>
              <button @click="deleteItem" class="flex-1 bg-red-50 text-red-600 font-semibold py-2 rounded-lg text-xs">🗑️ 삭제</button>
            </div>
          </div>

          <!-- 판매자 정보 -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <div class="text-xs font-bold text-gray-500 mb-3">판매자 정보</div>
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center text-lg font-bold text-amber-700 overflow-hidden flex-shrink-0">
                <img v-if="item.user?.avatar" :src="'/storage/' + item.user.avatar" class="w-full h-full object-cover" @error="e => e.target.style.display='none'" />
                <span v-else>{{ (item.user?.name || '?')[0] }}</span>
              </div>
              <div class="flex-1">
                <div class="font-bold text-gray-800 text-sm">{{ item.user?.nickname || item.user?.name }}</div>
                <div class="text-[10px] text-gray-400 mt-0.5">가입: {{ formatFullDate(item.user?.created_at) }}</div>
                <div class="text-[10px] text-amber-600 font-semibold mt-0.5">거래 {{ sellerTradeCount }}회</div>
              </div>
            </div>
            <div v-if="auth.isLoggedIn && !isOwner" class="flex gap-2 mt-3">
              <button @click="addFriend" class="flex-1 bg-blue-50 text-blue-700 border border-blue-200 font-bold py-2 rounded-lg text-xs hover:bg-blue-100">👋 친구추가</button>
              <button @click="sendMessage" class="flex-1 bg-amber-50 text-amber-700 border border-amber-200 font-bold py-2 rounded-lg text-xs hover:bg-amber-100">✉️ 쪽지</button>
            </div>
          </div>

          <!-- 위젯 -->
          <SidebarWidgets :inline="true" api-url="/api/market" detail-path="/market/" :current-id="item.id" label="물품" />
        </div>
      </div>
    </div>
  </div>

  <!-- 라이트박스 -->
  <div v-if="lightboxImg" class="fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4" @click="lightboxImg=null">
    <button @click="lightboxImg=null" class="absolute top-4 right-4 text-white text-3xl">✕</button>
    <img :src="lightboxImg" class="max-w-full max-h-[90vh] rounded-lg" @click.stop />
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
  <div v-if="showReport" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showReport=false">
    <div class="bg-white rounded-2xl p-5 w-full max-w-sm">
      <h3 class="font-bold text-lg mb-3">🚨 신고하기</h3>
      <select v-model="reportReason" class="w-full border rounded-lg px-3 py-2 text-sm mb-3">
        <option value="">신고 사유 선택</option>
        <option value="spam">스팸/광고</option><option value="scam">사기 의심</option>
        <option value="inappropriate">부적절한 내용</option><option value="fake">허위 매물</option>
      </select>
      <div class="flex gap-2">
        <button @click="showReport=false" class="flex-1 py-2 bg-gray-100 rounded-lg text-sm">취소</button>
        <button @click="submitReport" class="flex-1 py-2 bg-red-500 text-white rounded-lg text-sm font-bold">신고</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import CommentSection from '../../components/CommentSection.vue'
import axios from 'axios'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const siteStore = useSiteStore()
const item = ref(null)
const loading = ref(true)
const liked = ref(false)
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

function toggleLike() { liked.value = !liked.value }

async function addFriend() {
  try {
    await axios.post('/api/friends/request', { to_user_id: item.value.user_id })
    siteStore.toast('친구 요청을 보냈습니다', 'success')
  } catch (e) { siteStore.toast(e.response?.data?.message || '요청 실패', 'error') }
}

function sendMessage() {
  router.push(`/friends?message=${item.value.user_id}`)
}
const showReport = ref(false)
const reportReason = ref('')
function submitReport() { siteStore.toast('신고가 접수되었습니다', 'success'); showReport.value = false }
async function deleteItem() {
  if (!confirm('정말 삭제하시겠습니까?')) return
  try { await axios.delete(`/api/market/${item.value.id}`); router.push('/market') } catch {}
}

function formatDateTime(dt) { if (!dt) return ''; const d = new Date(dt); return d.toLocaleDateString('ko-KR') + ' ' + d.toLocaleTimeString('ko-KR', {hour:'2-digit',minute:'2-digit'}) }
function formatFullDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  return `${d.getFullYear()}년 ${d.getMonth()+1}월 ${d.getDate()}일`
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
    // 판매자 거래 횟수
    try {
      const { data: trades } = await axios.get(`/api/market?user_id=${item.value.user_id}&per_page=1`)
      sellerTradeCount.value = trades.data?.total || 0
    } catch {}
  } catch {}
}

onMounted(async () => { await loadItem(); loading.value = false })
</script>
