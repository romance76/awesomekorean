<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-5xl mx-auto px-4 py-5">
    <h1 class="text-xl font-black text-gray-800 mb-2">📢 광고 신청 (월간 경매)</h1>
    <p class="text-sm text-gray-500 mb-1">매달 말일 24시간 동안 입찰 접수 → 최고 입찰자 순으로 슬롯 배정</p>
    <p class="text-xs text-amber-600 font-bold mb-5">다음 경매: {{ nextAuctionDate }} · 최소 입찰: {{ slotPrices.left || 50 }}P~</p>

    <!-- ═══ Step 1: 페이지 선택 ═══ -->
    <div class="bg-white rounded-2xl shadow-sm border p-5 mb-5">
      <h2 class="font-bold text-gray-800 text-sm mb-3">1️⃣ 광고 노출 페이지 선택</h2>
      <div class="flex gap-3 mb-4">
        <button @click="pageType='home'" class="flex-1 py-3 rounded-xl font-bold text-sm border-2 transition"
          :class="pageType==='home' ? 'border-amber-500 bg-amber-50 text-amber-800' : 'border-gray-200 text-gray-500 hover:border-amber-300'">
          🏠 메인 (홈)
        </button>
        <button @click="pageType='sub'" class="flex-1 py-3 rounded-xl font-bold text-sm border-2 transition"
          :class="pageType==='sub' ? 'border-amber-500 bg-amber-50 text-amber-800' : 'border-gray-200 text-gray-500 hover:border-amber-300'">
          📄 서브 페이지
        </button>
      </div>
      <div v-if="pageType==='sub'" class="grid grid-cols-3 sm:grid-cols-4 gap-2">
        <label v-for="sp in subPages" :key="sp.key"
          class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition text-xs font-bold"
          :class="selectedSubs.includes(sp.key) ? 'border-amber-500 bg-amber-50 text-amber-800' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
          <input type="checkbox" :value="sp.key" v-model="selectedSubs" class="accent-amber-500" />
          {{ sp.icon }} {{ sp.label }}
        </label>
      </div>
      <div v-if="pageType==='sub' && selectedSubs.length" class="mt-2 text-xs text-amber-600">
        선택: {{ selectedSubs.map(k => subPages.find(s=>s.key===k)?.label).join(', ') }}
      </div>
    </div>

    <!-- ═══ Step 2: 슬롯 위치 선택 ═══ -->
    <div class="bg-white rounded-2xl shadow-sm border p-5 mb-5">
      <h2 class="font-bold text-gray-800 text-sm mb-3">2️⃣ 광고 슬롯 선택</h2>
      <p class="text-xs text-gray-400 mb-4">왼쪽 또는 오른쪽 사이드바 슬롯을 선택하세요 (최소 월 입찰가 표시)</p>

      <div class="border-2 border-gray-200 rounded-xl overflow-hidden bg-gray-50">
        <div class="bg-gradient-to-r from-amber-400 to-orange-400 h-8 flex items-center px-4">
          <span class="text-[10px] font-black text-amber-900">SomeKorean — {{ pageType === 'home' ? '홈' : '서브 페이지' }}</span>
        </div>
        <div class="grid grid-cols-12 gap-2 p-3 min-h-[250px]">
          <!-- 왼쪽 사이드바 -->
          <div class="col-span-3 space-y-2">
            <div class="bg-white rounded-lg border p-2">
              <div class="text-[9px] font-bold text-gray-400 mb-1">📋 카테고리</div>
              <div v-for="i in 4" :key="i" class="h-1.5 bg-gray-100 rounded w-full mb-0.5"></div>
            </div>
            <div v-for="slot in 3" :key="'left-'+slot"
              @click="selectSlot('left', slot)"
              class="border-2 border-dashed rounded-lg p-2 text-center cursor-pointer transition-all"
              :class="selectedSlot?.position==='left' && selectedSlot?.slot===slot
                ? 'border-amber-500 bg-amber-50 shadow-md' : 'border-blue-300 bg-blue-50/50 hover:border-amber-400'">
              <div class="text-xs mb-0.5">{{ selectedSlot?.position==='left' && selectedSlot?.slot===slot ? '✅' : '📍' }}</div>
              <div class="text-[9px] font-bold" :class="selectedSlot?.position==='left' && selectedSlot?.slot===slot ? 'text-amber-700' : 'text-blue-600'">좌측 {{ slot }}</div>
              <div class="text-[8px] text-gray-400">200×150px</div>
              <div class="text-[8px] font-bold text-red-500 mt-0.5">최소 {{ slotPrices.left }}P/월</div>
            </div>
          </div>
          <!-- 메인 콘텐츠 (장식) -->
          <div class="col-span-6 space-y-2">
            <div class="bg-white rounded-lg border p-3 h-full flex flex-col justify-center items-center">
              <div class="text-[10px] text-gray-300 font-bold">메인 콘텐츠 영역</div>
              <div class="mt-2 space-y-1 w-full">
                <div v-for="i in 4" :key="i" class="h-2 bg-gray-100 rounded w-full"></div>
              </div>
            </div>
          </div>
          <!-- 오른쪽 사이드바 -->
          <div class="col-span-3 space-y-2">
            <div class="bg-white rounded-lg border p-2">
              <div class="text-[9px] font-bold text-gray-400 mb-1">🔥 인기글</div>
              <div v-for="i in 3" :key="i" class="h-1.5 bg-gray-100 rounded w-full mb-0.5"></div>
            </div>
            <div v-for="slot in 2" :key="'right-'+slot"
              @click="selectSlot('right', slot)"
              class="border-2 border-dashed rounded-lg p-2 text-center cursor-pointer transition-all"
              :class="selectedSlot?.position==='right' && selectedSlot?.slot===slot
                ? 'border-amber-500 bg-amber-50 shadow-md' : 'border-orange-300 bg-orange-50/50 hover:border-amber-400'">
              <div class="text-xs mb-0.5">{{ selectedSlot?.position==='right' && selectedSlot?.slot===slot ? '✅' : '📍' }}</div>
              <div class="text-[9px] font-bold" :class="selectedSlot?.position==='right' && selectedSlot?.slot===slot ? 'text-amber-700' : 'text-orange-600'">우측 {{ slot }}</div>
              <div class="text-[8px] text-gray-400">300×250px</div>
              <div class="text-[8px] font-bold text-red-500 mt-0.5">최소 {{ slotPrices.right }}P/월</div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-3 p-3 bg-blue-50 rounded-lg border border-blue-200">
        <h3 class="text-xs font-bold text-blue-800 mb-1">📐 권장 이미지 사이즈</h3>
        <div class="grid grid-cols-2 gap-2 text-[10px]">
          <div class="bg-white rounded p-1.5"><span class="font-bold text-blue-700">좌측:</span> 200 × 150 px</div>
          <div class="bg-white rounded p-1.5"><span class="font-bold text-orange-700">우측:</span> 300 × 250 px</div>
        </div>
      </div>
    </div>

    <!-- ═══ Step 3: 광고 설정 (슬롯 선택 후) ═══ -->
    <Transition name="slide">
      <div v-if="selectedSlot" class="bg-white rounded-2xl shadow-sm border p-5 mb-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-bold text-gray-800">3️⃣ 광고 상세 설정</h2>
          <button @click="selectedSlot=null" class="text-gray-400 hover:text-gray-600 text-sm">✕ 닫기</button>
        </div>

        <div class="space-y-4">
          <div>
            <label class="text-xs font-bold text-gray-600 block mb-1">광고 제목</label>
            <input v-model="adForm.title" @input="saveDraft" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="광고 이름" />
          </div>

          <div>
            <label class="text-xs font-bold text-gray-600 block mb-1">
              광고 이미지 <span class="text-amber-600 font-normal ml-1">(권장: {{ sizeGuide[selectedSlot.position] }})</span>
            </label>
            <input type="file" accept="image/*" @change="onImageChange" class="w-full border rounded-lg px-3 py-2 text-sm" />
            <div v-if="imagePreview" class="mt-2"><img :src="imagePreview" class="max-h-32 rounded-lg border" /></div>
          </div>

          <div>
            <label class="text-xs font-bold text-gray-600 block mb-1">클릭 시 이동 URL (선택)</label>
            <input v-model="adForm.link_url" @input="saveDraft" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="https://..." />
          </div>

          <!-- 지역 타겟팅 (시티 제거) -->
          <div class="border rounded-lg p-4 bg-gray-50/50">
            <label class="text-xs font-bold text-gray-600 block mb-2">🌍 타겟 지역</label>
            <div class="grid grid-cols-2 gap-3">
              <select v-model="adForm.geo_scope" @change="onGeoScopeChange" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="all">전국 (비로그인 유저에게도 노출)</option>
                <option value="state">주 (로그인 유저에게만)</option>
                <option value="county">카운티 (로그인 유저에게만)</option>
              </select>
              <div v-if="adForm.geo_scope !== 'all'">
                <div class="flex gap-2">
                  <input v-model="zipInput" @input="onZipInput" placeholder="Zip Code (5자리)" maxlength="5" class="flex-1 border rounded-lg px-3 py-2 text-sm" />
                  <button @click="lookupZip" :disabled="zipLoading" class="bg-amber-400 text-amber-900 font-bold px-3 py-2 rounded-lg text-xs">{{ zipLoading ? '...' : '조회' }}</button>
                </div>
              </div>
            </div>
            <div v-if="zipResult && adForm.geo_scope !== 'all'" class="mt-2 p-2 bg-white rounded-lg border text-sm">
              <span class="text-[10px] text-green-600 font-bold">✅ {{ adForm.geo_value }} 설정됨</span>
            </div>
            <p class="text-[10px] text-gray-400 mt-2">
              💡 전국: 비로그인 + 전국 브라우징 유저 노출 · 로컬: 해당 지역 로그인 유저만 (경쟁 적음)
            </p>
          </div>

          <!-- 입찰 금액 -->
          <div class="border-2 border-amber-300 rounded-xl p-4 bg-amber-50/50">
            <label class="text-xs font-bold text-amber-800 block mb-2">💰 월간 입찰 금액 (최소 {{ currentMinBid }}P)</label>
            <div class="flex items-center gap-3">
              <input type="number" v-model.number="adForm.bid_amount" :min="currentMinBid" step="10" @input="saveDraft"
                class="flex-1 border-2 border-amber-400 rounded-lg px-4 py-3 text-lg font-black text-amber-800 text-center" />
              <span class="text-lg font-black text-amber-700">P</span>
            </div>
            <p class="text-[10px] text-amber-600 mt-2">높은 입찰 = 높은 순위. 동일 슬롯 여러 입찰자 → 최고 금액 순 배정</p>
            <div class="mt-2 text-xs">
              <span v-if="hasEnough" class="text-green-600">내 포인트: {{ (auth.user?.points||0).toLocaleString() }}P ✅</span>
              <span v-else class="text-red-600">
                내 포인트: {{ (auth.user?.points||0).toLocaleString() }}P ❌ 부족
                <button @click="goPointShop" class="ml-2 bg-red-500 text-white px-3 py-1 rounded-lg text-[10px] font-bold hover:bg-red-600">
                  포인트 충전하기 →
                </button>
              </span>
            </div>
          </div>

          <!-- 신청 버튼 -->
          <button @click="submitAd" :disabled="submitting || !canSubmit"
            class="w-full py-3 rounded-xl font-bold text-sm transition disabled:opacity-50"
            :class="canSubmit ? 'bg-amber-400 text-amber-900 hover:bg-amber-500' : 'bg-gray-200 text-gray-400'">
            {{ submitting ? '신청 중...' : `입찰 신청하기 (${adForm.bid_amount || 0}P 차감)` }}
          </button>
        </div>
      </div>
    </Transition>

    <!-- ═══ 내 광고 목록 ═══ -->
    <div class="bg-white rounded-2xl shadow-sm border p-5">
      <h2 class="font-bold text-gray-800 mb-4">📋 내 광고 입찰 내역</h2>
      <div v-if="loading" class="text-center py-8 text-gray-400 text-sm">로딩중...</div>
      <div v-else-if="!myAds.length" class="text-center py-8 text-gray-400 text-sm">신청한 광고가 없습니다</div>
      <div v-else class="space-y-3">
        <div v-for="ad in myAds" :key="ad.id" class="border rounded-xl p-4 flex gap-4 hover:bg-gray-50/50 transition">
          <div class="w-24 h-16 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
            <img :src="ad.image_url" class="w-full h-full object-cover" @error="e=>e.target.style.display='none'" />
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-xs px-2 py-0.5 rounded-full font-bold" :class="statusClasses[ad.status]">{{ statusLabels[ad.status] }}</span>
              <span class="text-[10px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full">{{ posLabels[ad.position] }} {{ ad.slot_number || '' }}</span>
              <span class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-bold">{{ ad.bid_amount || ad.total_cost }}P</span>
              <span v-if="ad.geo_scope!=='all'" class="text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full">{{ ad.geo_value }}</span>
            </div>
            <div class="text-sm font-bold text-gray-800 truncate mt-1">{{ ad.title }}</div>
            <div class="text-[10px] text-gray-400 mt-0.5">
              {{ (ad.target_pages || [ad.page]).join(', ') }} · 노출{{ ad.impressions||0 }} · 클릭{{ ad.clicks||0 }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useModal } from '../../composables/useModal'
import axios from 'axios'

const router = useRouter()
const auth = useAuthStore()
const { showAlert, showConfirm } = useModal()

const loading = ref(true)
const myAds = ref([])
const selectedSlot = ref(null)
const submitting = ref(false)
const imagePreview = ref(null)
const adImage = ref(null)
const zipInput = ref('')
const zipLoading = ref(false)
const zipResult = ref(null)
const pageType = ref('home')
const selectedSubs = ref([])

// 관리자 설정에서 가져오는 슬롯별 최소 가격
const slotPrices = ref({ left: 50, right: 50 })

const adForm = reactive({
  title: '', link_url: '', geo_scope: 'all', geo_value: '', bid_amount: 50
})

const DRAFT_KEY = 'sk_ad_draft'

const subPages = [
  { key: 'community', icon: '💬', label: '커뮤니티' },
  { key: 'qa', icon: '❓', label: 'Q&A' },
  { key: 'jobs', icon: '💼', label: '구인구직' },
  { key: 'market', icon: '🛒', label: '중고장터' },
  { key: 'realestate', icon: '🏠', label: '부동산' },
  { key: 'directory', icon: '🏪', label: '업소록' },
  { key: 'clubs', icon: '👥', label: '동호회' },
  { key: 'news', icon: '📰', label: '뉴스' },
  { key: 'recipes', icon: '🍳', label: '레시피' },
  { key: 'groupbuy', icon: '🤝', label: '공동구매' },
  { key: 'events', icon: '🎉', label: '이벤트' },
]

const posLabels = { left: '좌측', right: '우측' }
const statusLabels = { pending:'입찰대기', active:'게시중', rejected:'거절', expired:'만료', paused:'중지' }
const statusClasses = { pending:'bg-amber-100 text-amber-700', active:'bg-green-100 text-green-700', rejected:'bg-red-100 text-red-700', expired:'bg-gray-200 text-gray-500', paused:'bg-gray-200 text-gray-500' }
const sizeGuide = { left: '200 × 150 px', right: '300 × 250 px' }

const nextAuctionDate = computed(() => {
  const now = new Date()
  const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  return `${lastDay.getFullYear()}.${lastDay.getMonth()+1}.${lastDay.getDate()}`
})

const currentMinBid = computed(() => slotPrices.value[selectedSlot.value?.position] || 50)
const hasEnough = computed(() => (auth.user?.points || 0) >= (adForm.bid_amount || 0))
const canSubmit = computed(() => {
  if (!adForm.title || !adImage.value || !selectedSlot.value) return false
  if (adForm.bid_amount < currentMinBid.value) return false
  if (!hasEnough.value) return false
  if (pageType.value === 'sub' && !selectedSubs.value.length) return false
  if (adForm.geo_scope !== 'all' && !adForm.geo_value) return false
  return true
})

// ─── localStorage 임시저장 ───
function saveDraft() {
  try {
    localStorage.setItem(DRAFT_KEY, JSON.stringify({
      pageType: pageType.value,
      selectedSubs: selectedSubs.value,
      selectedSlot: selectedSlot.value,
      adForm: { ...adForm },
      zipInput: zipInput.value,
      ts: Date.now()
    }))
  } catch {}
}

function loadDraft() {
  try {
    const raw = localStorage.getItem(DRAFT_KEY)
    if (!raw) return
    const d = JSON.parse(raw)
    // 24시간 이내 저장분만 복원
    if (Date.now() - d.ts > 86400000) { localStorage.removeItem(DRAFT_KEY); return }
    pageType.value = d.pageType || 'home'
    selectedSubs.value = d.selectedSubs || []
    selectedSlot.value = d.selectedSlot || null
    if (d.adForm) Object.assign(adForm, d.adForm)
    zipInput.value = d.zipInput || ''
  } catch {}
}

function clearDraft() { try { localStorage.removeItem(DRAFT_KEY) } catch {} }

// 폼 변경 감시 → 자동 저장
watch([pageType, selectedSubs, selectedSlot], saveDraft, { deep: true })

// ─── 포인트 부족 → 충전 페이지 ───
async function goPointShop() {
  saveDraft() // 현재 상태 저장
  router.push('/dashboard?tab=points')
}

function selectSlot(position, slot) {
  selectedSlot.value = { position, slot }
  adForm.bid_amount = Math.max(adForm.bid_amount, slotPrices.value[position] || 50)
  saveDraft()
}

function onImageChange(e) {
  const file = e.target.files[0]
  if (file) { adImage.value = file; imagePreview.value = URL.createObjectURL(file) }
}

function onGeoScopeChange() { adForm.geo_value = ''; zipResult.value = null; zipInput.value = ''; saveDraft() }
function onZipInput() { if (zipInput.value.length === 5) lookupZip() }

async function lookupZip() {
  if (zipInput.value.length !== 5) return
  zipLoading.value = true
  try {
    const resp = await fetch(`https://api.zippopotam.us/us/${zipInput.value}`)
    if (!resp.ok) throw new Error('Invalid')
    const data = await resp.json()
    const place = data.places?.[0]
    if (place) {
      zipResult.value = { state: place['state abbreviation'], city: place['place name'] }
      if (adForm.geo_scope === 'state') adForm.geo_value = place['state abbreviation']
      else if (adForm.geo_scope === 'county') adForm.geo_value = place['place name']
      saveDraft()
    }
  } catch { showAlert('유효하지 않은 Zip Code', '오류') }
  zipLoading.value = false
}

async function submitAd() {
  if (!canSubmit.value || submitting.value) return
  // 포인트 부족 더블체크
  if (!hasEnough.value) {
    const ok = await showConfirm(`포인트가 부족합니다 (보유: ${(auth.user?.points||0).toLocaleString()}P, 필요: ${adForm.bid_amount}P).\n포인트 충전 페이지로 이동하시겠습니까?`, '포인트 부족')
    if (ok) goPointShop()
    return
  }
  submitting.value = true
  const fd = new FormData()
  fd.append('title', adForm.title)
  fd.append('link_url', adForm.link_url || '')
  fd.append('page', pageType.value === 'home' ? 'home' : 'sub')
  fd.append('target_pages', JSON.stringify(pageType.value === 'home' ? ['home'] : selectedSubs.value))
  fd.append('position', selectedSlot.value.position)
  fd.append('slot_number', selectedSlot.value.slot)
  fd.append('geo_scope', adForm.geo_scope)
  fd.append('geo_value', adForm.geo_value)
  fd.append('bid_amount', adForm.bid_amount)
  if (adImage.value) fd.append('image', adImage.value)
  try {
    const { data } = await axios.post('/api/banners/apply', fd)
    showAlert(data.message, '입찰 신청')
    selectedSlot.value = null
    Object.assign(adForm, { title:'', link_url:'', geo_scope:'all', geo_value:'', bid_amount: currentMinBid.value })
    adImage.value = null; imagePreview.value = null; selectedSubs.value = []
    clearDraft()
    await loadMyAds()
  } catch (e) {
    const msg = e.response?.data?.message || '신청 실패'
    if (msg.includes('포인트 부족') || msg.includes('부족')) {
      const ok = await showConfirm(`${msg}\n\n포인트 충전 페이지로 이동하시겠습니까?`, '포인트 부족')
      if (ok) goPointShop()
    } else { showAlert(msg, '오류') }
  }
  submitting.value = false
}

async function loadMyAds() {
  try { const { data } = await axios.get('/api/banners/my'); myAds.value = data.data || [] } catch {}
  loading.value = false
}

async function loadSlotPrices() {
  try {
    const { data } = await axios.get('/api/ad-settings/public')
    const cfg = data.data || {}
    if (cfg.slot_min_prices) {
      slotPrices.value = cfg.slot_min_prices
    }
  } catch {}
}

onMounted(() => {
  loadMyAds()
  loadSlotPrices()
  loadDraft() // localStorage에서 임시저장 복원
})
</script>

<style scoped>
.slide-enter-active, .slide-leave-active { transition: all 0.3s ease; }
.slide-enter-from, .slide-leave-to { opacity: 0; transform: translateY(-10px); }
</style>
