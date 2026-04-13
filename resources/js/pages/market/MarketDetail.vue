<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-6xl mx-auto px-4 py-5">
    <button @click="$router.back()" class="text-sm text-gray-500 hover:text-amber-600 mb-3">← 장터 목록</button>
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="item">

      <!-- ═══ 상단: 이미지 + 상품 정보 ═══ -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

        <!-- 왼쪽: 이미지 갤러리 -->
        <div>
          <!-- 메인 이미지 -->
          <div class="bg-white rounded-xl border overflow-hidden aspect-square flex items-center justify-center cursor-pointer" @click="lightboxImg = mainImage">
            <img v-if="mainImage" :src="mainImage" class="w-full h-full object-contain" @error="e => e.target.src=''" />
            <div v-else class="text-6xl text-gray-300">📦</div>
          </div>
          <!-- 썸네일 리스트 -->
          <div v-if="item.images?.length > 1" class="flex gap-2 mt-3 overflow-x-auto">
            <div v-for="(img, i) in item.images" :key="i"
              @click="selectedImgIdx = i"
              class="w-16 h-16 rounded-lg overflow-hidden border-2 cursor-pointer flex-shrink-0 transition"
              :class="selectedImgIdx === i ? 'border-amber-400' : 'border-gray-200 hover:border-gray-300'">
              <img :src="getImageUrl(img)" class="w-full h-full object-cover" @error="e => e.target.style.display='none'" />
            </div>
          </div>
        </div>

        <!-- 오른쪽: 상품 정보 + 액션 -->
        <div class="space-y-4">
          <!-- 상태 뱃지 -->
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-xs px-2.5 py-1 rounded-full font-bold"
              :class="{'bg-green-100 text-green-700':item.status==='active','bg-amber-100 text-amber-700':item.status==='reserved','bg-gray-200 text-gray-500':item.status==='sold'}">
              {{ {active:'판매중',reserved:'예약중',sold:'판매완료'}[item.status] }}
            </span>
            <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600">{{ conditionLabel }}</span>
            <span v-if="item.is_negotiable" class="text-xs px-2 py-1 rounded-full bg-amber-50 text-amber-600 font-semibold">가격협의</span>
            <span v-if="item.boosted_until && new Date(item.boosted_until) > new Date()" class="text-xs px-2 py-1 rounded-full bg-purple-100 text-purple-700 font-bold">🚀 상위노출</span>
            <span v-if="item.hold_enabled" class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-700">🔒 홀드 가능</span>
          </div>

          <!-- 제목 -->
          <h1 class="text-xl lg:text-2xl font-bold text-gray-900 leading-tight">{{ item.title }}</h1>

          <!-- 가격 -->
          <div class="text-3xl font-black text-amber-600">${{ Number(item.price).toLocaleString() }}</div>

          <!-- 위치 + 조회수 -->
          <div class="flex items-center gap-3 text-sm text-gray-500">
            <span>📍 {{ item.city }}, {{ item.state }}</span>
            <span>👁 {{ item.view_count }}회</span>
            <span>{{ timeAgo(item.created_at) }}</span>
          </div>

          <!-- 홀드 상태 -->
          <div v-if="item.active_hold" class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
            <div class="flex items-center gap-2">
              <span class="text-amber-600 font-bold">🔒 홀드 중</span>
              <span class="text-xs text-gray-500">{{ item.active_hold.buyer?.nickname || item.active_hold.buyer?.name }}님</span>
            </div>
            <div class="text-xs text-amber-700 mt-1">만료: {{ formatDateTime(item.active_hold.hold_until) }}</div>
          </div>

          <!-- 액션 버튼 -->
          <div class="space-y-2 pt-2">
            <!-- 홀드 버튼 -->
            <button v-if="item.hold_enabled && item.status==='active' && auth.isLoggedIn && !isOwner && !item.active_hold"
              @click="showHoldModal = true"
              class="w-full bg-blue-500 text-white font-bold py-3 rounded-xl text-sm hover:bg-blue-600 transition">
              🔒 홀드하기 ({{ item.hold_price_per_6h }}P / 6시간)
            </button>

            <!-- 홀드 취소 -->
            <button v-if="item.active_hold && (item.active_hold.buyer_id == auth.user?.id || isOwner)"
              @click="cancelHold"
              class="w-full bg-red-50 text-red-600 border border-red-200 font-bold py-3 rounded-xl text-sm hover:bg-red-100 transition">
              홀드 취소
            </button>

            <!-- 부스트 (판매자) -->
            <button v-if="isOwner && item.status === 'active'"
              @click="showBoostModal = true"
              class="w-full bg-purple-500 text-white font-bold py-3 rounded-xl text-sm hover:bg-purple-600 transition">
              🚀 상위노출 (100P/일)
            </button>

            <div class="flex gap-2">
              <RouterLink v-if="auth.isLoggedIn && !isOwner" to="/chat"
                class="flex-1 bg-amber-400 text-amber-900 font-bold py-3 rounded-xl text-sm text-center hover:bg-amber-500 transition">
                💬 채팅하기
              </RouterLink>
              <button @click="toggleLike" class="w-14 h-12 rounded-xl border flex items-center justify-center text-lg transition"
                :class="liked ? 'bg-red-50 border-red-200 text-red-500' : 'bg-white border-gray-200 text-gray-400 hover:text-red-400'">
                {{ liked ? '❤️' : '🤍' }}
              </button>
              <button @click="showReport = true" class="w-14 h-12 rounded-xl border border-gray-200 bg-white flex items-center justify-center text-lg text-gray-400 hover:text-red-500 transition">
                🚨
              </button>
            </div>

            <!-- 수정/삭제 (판매자) -->
            <div v-if="isOwner" class="flex gap-2 pt-1">
              <RouterLink :to="`/market/write?edit=${item.id}`" class="flex-1 bg-gray-100 text-gray-700 font-semibold py-2 rounded-lg text-sm text-center hover:bg-gray-200">✏️ 수정</RouterLink>
              <button @click="deleteItem" class="flex-1 bg-red-50 text-red-600 font-semibold py-2 rounded-lg text-sm hover:bg-red-100">🗑️ 삭제</button>
            </div>
          </div>

          <!-- 판매자 정보 -->
          <div class="bg-white rounded-xl border p-4 mt-4">
            <div class="text-xs font-bold text-gray-500 mb-3">판매자 정보</div>
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center text-lg font-bold text-amber-700 overflow-hidden flex-shrink-0">
                <img v-if="item.user?.avatar" :src="'/storage/' + item.user.avatar" class="w-full h-full object-cover" @error="e => e.target.style.display='none'" />
                <span v-else>{{ (item.user?.name || '?')[0] }}</span>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-bold text-gray-800">{{ item.user?.nickname || item.user?.name }}</div>
                <div class="text-xs text-gray-400 mt-0.5">가입: {{ formatDate(item.user?.created_at) }}</div>
              </div>
              <button v-if="auth.isLoggedIn && !isOwner" class="text-xs bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-full hover:bg-amber-500">팔로우</button>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══ 중간: 상세 설명 ═══ -->
      <div class="bg-white rounded-xl border p-6 mb-4">
        <h2 class="font-bold text-gray-800 mb-3 text-sm">📋 상세 설명</h2>
        <div class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ item.content }}</div>
      </div>

      <!-- ═══ 주의사항 ═══ -->
      <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-4">
        <div class="flex items-start gap-2">
          <span class="text-amber-500 flex-shrink-0">⚠️</span>
          <div>
            <div class="font-bold text-sm text-gray-700">거래 전 주의!</div>
            <div class="text-xs text-gray-500 mt-1 leading-relaxed">
              해당 게시글은 회원이 등록한 것으로 SomeKorean은 등록된 내용에 대하여 일체의 책임을 지지 않습니다.
              직거래 시 안전한 장소에서 만나시고, 선입금 요구에 주의하세요.
              부적절한 게시글이라면 신고해주세요.
            </div>
          </div>
        </div>
      </div>

      <!-- ═══ 댓글 ═══ -->
      <div class="bg-white rounded-xl border overflow-hidden">
        <CommentSection v-if="item.id" :type="'market'" :typeId="item.id" />
      </div>

    </div>
  </div>

  <!-- ═══ 이미지 라이트박스 ═══ -->
  <div v-if="lightboxImg" class="fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4" @click="lightboxImg=null">
    <button @click="lightboxImg=null" class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300">✕</button>
    <img :src="lightboxImg" class="max-w-full max-h-[90vh] rounded-lg" @click.stop />
  </div>

  <!-- ═══ 홀드 모달 ═══ -->
  <div v-if="showHoldModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showHoldModal=false">
    <div class="bg-white rounded-2xl p-5 w-full max-w-sm">
      <h3 class="font-bold text-lg mb-3">🔒 홀드 신청</h3>
      <p class="text-sm text-gray-600 mb-3">{{ item.title }}</p>
      <div class="text-xs text-gray-400 mb-3">6시간당 <b class="text-amber-600">{{ item.hold_price_per_6h }}P</b> · 최대 {{ item.hold_max_hours }}시간</div>
      <div class="grid grid-cols-3 gap-2 mb-3">
        <button v-for="h in holdOptions" :key="h" @click="holdHours = h"
          :class="holdHours === h ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700'"
          class="py-2 rounded-lg text-sm font-bold">{{ h >= 24 ? (h/24) + '일' : h + '시간' }}</button>
      </div>
      <div class="bg-blue-50 rounded-lg p-3 mb-4 text-center">
        <div class="text-xs text-gray-500">차감 포인트</div>
        <div class="text-2xl font-black text-blue-600">{{ holdCost }}P</div>
        <div class="text-[10px] text-gray-400">판매자 {{ Math.floor(holdCost * 0.9) }}P · 수수료 {{ Math.ceil(holdCost * 0.1) }}P</div>
      </div>
      <div class="flex gap-2">
        <button @click="showHoldModal=false" class="flex-1 py-2 bg-gray-100 rounded-lg text-sm font-semibold">취소</button>
        <button @click="submitHold" :disabled="holdingInProgress" class="flex-1 py-2 bg-blue-500 text-white rounded-lg text-sm font-bold disabled:opacity-50">홀드 신청</button>
      </div>
    </div>
  </div>

  <!-- ═══ 부스트 모달 ═══ -->
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

  <!-- ═══ 신고 모달 ═══ -->
  <div v-if="showReport" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showReport=false">
    <div class="bg-white rounded-2xl p-5 w-full max-w-sm">
      <h3 class="font-bold text-lg mb-3">🚨 신고하기</h3>
      <select v-model="reportReason" class="w-full border rounded-lg px-3 py-2 text-sm mb-3">
        <option value="">신고 사유 선택</option>
        <option value="spam">스팸/광고</option>
        <option value="scam">사기 의심</option>
        <option value="inappropriate">부적절한 내용</option>
        <option value="fake">허위 매물</option>
        <option value="etc">기타</option>
      </select>
      <textarea v-model="reportContent" rows="3" placeholder="상세 사유를 입력해주세요 (선택)" class="w-full border rounded-lg px-3 py-2 text-sm resize-none mb-3"></textarea>
      <div class="flex gap-2">
        <button @click="showReport=false" class="flex-1 py-2 bg-gray-100 rounded-lg text-sm font-semibold">취소</button>
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

const isOwner = computed(() => item.value && String(item.value.user_id) === String(auth.user?.id))
const conditionLabel = computed(() => ({new:'새상품',like_new:'거의 새것',good:'양호',fair:'보통'})[item.value?.condition] || '')

const mainImage = computed(() => {
  if (!item.value?.images?.length) return null
  return getImageUrl(item.value.images[selectedImgIdx.value] || item.value.images[0])
})

function getImageUrl(img) {
  if (!img) return ''
  if (img.startsWith('http') || img.startsWith('/')) return img
  return '/storage/' + img
}

// 홀드
const showHoldModal = ref(false)
const holdHours = ref(6)
const holdingInProgress = ref(false)
const holdOptions = computed(() => {
  const max = item.value?.hold_max_hours || 24
  return [6, 12, 24, 48, 72, 168].filter(h => h <= max)
})
const holdCost = computed(() => Math.ceil(holdHours.value / 6) * (item.value?.hold_price_per_6h || 0))

async function submitHold() {
  if (!confirm(`${holdHours.value}시간 홀드에 ${holdCost.value}P 차감됩니다. 계속?`)) return
  holdingInProgress.value = true
  try {
    const { data } = await axios.post(`/api/market/${item.value.id}/hold`, { hours: holdHours.value })
    siteStore.toast(data.message, 'success')
    showHoldModal.value = false
    loadItem()
  } catch (e) { siteStore.toast(e.response?.data?.message || '홀드 실패', 'error') }
  holdingInProgress.value = false
}

async function cancelHold() {
  if (!confirm('홀드를 취소하시겠습니까?')) return
  try {
    const { data } = await axios.post(`/api/market/${item.value.id}/hold/cancel`)
    siteStore.toast(data.message, 'success')
    loadItem()
  } catch (e) { siteStore.toast(e.response?.data?.message || '취소 실패', 'error') }
}

// 부스트
const showBoostModal = ref(false)
const boostDays = ref(1)
const boostingInProgress = ref(false)

async function submitBoost() {
  const cost = boostDays.value * 100
  if (!confirm(`${boostDays.value}일 상위노출에 ${cost}P 차감`)) return
  boostingInProgress.value = true
  try {
    const { data } = await axios.post(`/api/market/${item.value.id}/boost`, { days: boostDays.value })
    siteStore.toast(data.message, 'success')
    showBoostModal.value = false
    loadItem()
  } catch (e) { siteStore.toast(e.response?.data?.message || '부스트 실패', 'error') }
  boostingInProgress.value = false
}

// 좋아요
function toggleLike() { liked.value = !liked.value }

// 신고
const showReport = ref(false)
const reportReason = ref('')
const reportContent = ref('')
function submitReport() {
  siteStore.toast('신고가 접수되었습니다', 'success')
  showReport.value = false
  reportReason.value = ''; reportContent.value = ''
}

// 삭제
async function deleteItem() {
  if (!confirm('정말 삭제하시겠습니까?')) return
  try {
    await axios.delete(`/api/market/${item.value.id}`)
    siteStore.toast('삭제되었습니다', 'success')
    router.push('/market')
  } catch (e) { siteStore.toast('삭제 실패', 'error') }
}

// 유틸
function formatDateTime(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  return d.toLocaleDateString('ko-KR') + ' ' + d.toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' })
}
function formatDate(dt) { return dt ? new Date(dt).toLocaleDateString('ko-KR') : '' }
function timeAgo(dt) {
  if (!dt) return ''
  const diff = (Date.now() - new Date(dt)) / 1000
  if (diff < 3600) return Math.floor(diff / 60) + '분 전'
  if (diff < 86400) return Math.floor(diff / 3600) + '시간 전'
  if (diff < 604800) return Math.floor(diff / 86400) + '일 전'
  return new Date(dt).toLocaleDateString('ko-KR')
}

async function loadItem() {
  try {
    const { data } = await axios.get(`/api/market/${route.params.id}`)
    item.value = data.data
  } catch {}
}

onMounted(async () => {
  await loadItem()
  loading.value = false
})
</script>
