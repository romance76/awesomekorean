<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-5xl mx-auto px-4 py-5">
    <h1 class="text-xl font-black text-gray-800 mb-2">📢 광고 신청</h1>
    <p class="text-sm text-gray-500 mb-5">사이트 레이아웃에서 원하는 위치를 클릭하여 광고를 등록하세요</p>

    <!-- ═══ 시각적 레이아웃 선택 ═══ -->
    <div class="bg-white rounded-2xl shadow-sm border p-5 mb-5">
      <h2 class="font-bold text-gray-800 text-sm mb-4">🖥️ 광고 위치 선택</h2>
      <p class="text-xs text-gray-400 mb-4">클릭하면 해당 위치에 광고를 신청할 수 있습니다</p>

      <!-- 사이트 레이아웃 미리보기 -->
      <div class="border-2 border-gray-200 rounded-xl overflow-hidden bg-gray-50">
        <!-- 상단 헤더 (장식) -->
        <div class="bg-gradient-to-r from-amber-400 to-orange-400 h-10 flex items-center px-4">
          <span class="text-xs font-black text-amber-900">SomeKorean</span>
          <div class="flex-1"></div>
          <div class="flex gap-2">
            <span class="bg-white/30 text-[10px] px-2 py-0.5 rounded-full text-amber-900 font-bold">홈</span>
            <span class="bg-white/30 text-[10px] px-2 py-0.5 rounded-full text-amber-900 font-bold">커뮤니티</span>
            <span class="bg-white/30 text-[10px] px-2 py-0.5 rounded-full text-amber-900 font-bold">장터</span>
          </div>
        </div>

        <!-- 3컬럼 레이아웃 -->
        <div class="grid grid-cols-12 gap-2 p-3 min-h-[400px]">
          <!-- 왼쪽 사이드바 -->
          <div class="col-span-3 space-y-2">
            <div class="bg-white rounded-lg border p-2">
              <div class="text-[10px] font-bold text-gray-400 mb-1">📋 게시판</div>
              <div class="space-y-0.5">
                <div v-for="i in 5" :key="i" class="h-2 bg-gray-100 rounded w-full"></div>
              </div>
            </div>
            <div class="bg-white rounded-lg border p-2">
              <div class="text-[10px] font-bold text-gray-400 mb-1">⚡ 서비스</div>
              <div class="space-y-0.5">
                <div v-for="i in 4" :key="i" class="h-2 bg-gray-100 rounded w-3/4"></div>
              </div>
            </div>

            <!-- 왼쪽 광고 슬롯 3개 -->
            <div v-for="slot in 3" :key="'left-'+slot"
              @click="selectSlot('left', slot)"
              class="border-2 border-dashed rounded-lg p-3 text-center cursor-pointer transition-all group"
              :class="selectedSlot?.position==='left' && selectedSlot?.slot===slot
                ? 'border-amber-500 bg-amber-50 shadow-md'
                : 'border-blue-300 bg-blue-50/50 hover:border-amber-400 hover:bg-amber-50/50'">
              <div class="text-lg mb-1">{{ selectedSlot?.position==='left' && selectedSlot?.slot===slot ? '✅' : '📍' }}</div>
              <div class="text-[10px] font-bold" :class="selectedSlot?.position==='left' && selectedSlot?.slot===slot ? 'text-amber-700' : 'text-blue-600'">좌측 광고 {{ slot }}</div>
              <div class="text-[9px] text-gray-400">200 × 150 px</div>
              <div class="text-[9px] font-bold text-amber-600">200P/일</div>
            </div>
          </div>

          <!-- 메인 콘텐츠 -->
          <div class="col-span-6 space-y-2">
            <!-- 상단 광고 슬롯 -->
            <div @click="selectSlot('top', 1)"
              class="border-2 border-dashed rounded-lg p-4 text-center cursor-pointer transition-all"
              :class="selectedSlot?.position==='top'
                ? 'border-amber-500 bg-amber-50 shadow-md'
                : 'border-green-300 bg-green-50/50 hover:border-amber-400 hover:bg-amber-50/50'">
              <div class="text-lg mb-1">{{ selectedSlot?.position==='top' ? '✅' : '📍' }}</div>
              <div class="text-xs font-bold" :class="selectedSlot?.position==='top' ? 'text-amber-700' : 'text-green-600'">상단 광고 배너</div>
              <div class="text-[10px] text-gray-400">728 × 90 px</div>
              <div class="text-[10px] font-bold text-amber-600">500P/일 (프리미엄)</div>
            </div>

            <!-- 메인 콘텐츠 영역 (장식) -->
            <div class="bg-white rounded-lg border p-3">
              <div class="text-[10px] font-bold text-gray-400 mb-2">💬 최신 게시글</div>
              <div class="space-y-1.5">
                <div v-for="i in 5" :key="i" class="flex items-center gap-2">
                  <div class="h-2 bg-amber-100 rounded w-12"></div>
                  <div class="h-2 bg-gray-100 rounded flex-1"></div>
                </div>
              </div>
            </div>

            <!-- 중앙 광고 슬롯 -->
            <div @click="selectSlot('center', 1)"
              class="border-2 border-dashed rounded-lg p-4 text-center cursor-pointer transition-all"
              :class="selectedSlot?.position==='center'
                ? 'border-amber-500 bg-amber-50 shadow-md'
                : 'border-purple-300 bg-purple-50/50 hover:border-amber-400 hover:bg-amber-50/50'">
              <div class="text-lg mb-1">{{ selectedSlot?.position==='center' ? '✅' : '📍' }}</div>
              <div class="text-xs font-bold" :class="selectedSlot?.position==='center' ? 'text-amber-700' : 'text-purple-600'">중앙 광고 배너</div>
              <div class="text-[10px] text-gray-400">468 × 60 px</div>
              <div class="text-[10px] font-bold text-amber-600">300P/일</div>
            </div>

            <!-- 더미 콘텐츠 -->
            <div class="bg-white rounded-lg border p-3">
              <div class="text-[10px] font-bold text-gray-400 mb-2">📰 뉴스 / 레시피</div>
              <div class="grid grid-cols-3 gap-2">
                <div v-for="i in 3" :key="i" class="h-16 bg-gray-100 rounded"></div>
              </div>
            </div>
          </div>

          <!-- 오른쪽 사이드바 -->
          <div class="col-span-3 space-y-2">
            <div class="bg-white rounded-lg border p-2">
              <div class="text-[10px] font-bold text-gray-400 mb-1">🔥 인기글</div>
              <div class="space-y-0.5">
                <div v-for="i in 5" :key="i" class="h-2 bg-gray-100 rounded w-full"></div>
              </div>
            </div>
            <div class="bg-white rounded-lg border p-2">
              <div class="text-[10px] font-bold text-gray-400 mb-1">📊 통계</div>
              <div class="space-y-0.5">
                <div v-for="i in 3" :key="i" class="h-2 bg-gray-100 rounded w-2/3"></div>
              </div>
            </div>

            <!-- 오른쪽 광고 슬롯 2개 -->
            <div v-for="slot in 2" :key="'right-'+slot"
              @click="selectSlot('right', slot)"
              class="border-2 border-dashed rounded-lg p-3 text-center cursor-pointer transition-all group"
              :class="selectedSlot?.position==='right' && selectedSlot?.slot===slot
                ? 'border-amber-500 bg-amber-50 shadow-md'
                : 'border-orange-300 bg-orange-50/50 hover:border-amber-400 hover:bg-amber-50/50'">
              <div class="text-lg mb-1">{{ selectedSlot?.position==='right' && selectedSlot?.slot===slot ? '✅' : '📍' }}</div>
              <div class="text-[10px] font-bold" :class="selectedSlot?.position==='right' && selectedSlot?.slot===slot ? 'text-amber-700' : 'text-orange-600'">우측 광고 {{ slot }}</div>
              <div class="text-[9px] text-gray-400">300 × 250 px</div>
              <div class="text-[9px] font-bold text-amber-600">200P/일</div>
            </div>
          </div>
        </div>
      </div>

      <!-- 이미지 사이즈 가이드 -->
      <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
        <h3 class="text-xs font-bold text-blue-800 mb-2">📐 위치별 권장 이미지 사이즈</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[10px]">
          <div class="bg-white rounded p-2"><span class="font-bold text-green-700">상단:</span> 728 × 90 px</div>
          <div class="bg-white rounded p-2"><span class="font-bold text-purple-700">중앙:</span> 468 × 60 px</div>
          <div class="bg-white rounded p-2"><span class="font-bold text-blue-700">좌측:</span> 200 × 150 px</div>
          <div class="bg-white rounded p-2"><span class="font-bold text-orange-700">우측:</span> 300 × 250 px</div>
        </div>
      </div>
    </div>

    <!-- ═══ 광고 설정 폼 (슬롯 선택 후 표시) ═══ -->
    <Transition name="slide">
      <div v-if="selectedSlot" class="bg-white rounded-2xl shadow-sm border p-5 mb-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-bold text-gray-800">⚙️ {{ positionLabels[selectedSlot.position] }} 광고 설정</h2>
          <button @click="selectedSlot=null" class="text-gray-400 hover:text-gray-600 text-sm">✕ 닫기</button>
        </div>

        <div class="space-y-4">
          <!-- 광고 제목 -->
          <div>
            <label class="text-xs font-bold text-gray-600 block mb-1">광고 제목</label>
            <input v-model="adForm.title" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="광고 이름을 입력하세요" />
          </div>

          <!-- 배너 이미지 -->
          <div>
            <label class="text-xs font-bold text-gray-600 block mb-1">
              광고 이미지
              <span class="text-amber-600 font-normal ml-1">(권장: {{ sizeGuide[selectedSlot.position] }})</span>
            </label>
            <input type="file" accept="image/*" @change="onImageChange" class="w-full border rounded-lg px-3 py-2 text-sm" />
            <div v-if="imagePreview" class="mt-2">
              <img :src="imagePreview" class="max-h-32 rounded-lg border" />
            </div>
          </div>

          <!-- 클릭 URL -->
          <div>
            <label class="text-xs font-bold text-gray-600 block mb-1">클릭 시 이동 URL (선택)</label>
            <input v-model="adForm.link_url" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="https://..." />
          </div>

          <!-- 노출 페이지 -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="text-xs font-bold text-gray-600 block mb-1">노출 페이지</label>
              <select v-model="adForm.page" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="home">홈 (×1.3)</option>
                <option value="all">전체 페이지 (×1.5)</option>
                <option value="market">중고장터</option>
                <option value="jobs">구인구직</option>
                <option value="directory">업소록</option>
                <option value="news">뉴스</option>
                <option value="qa">Q&A</option>
                <option value="recipes">레시피</option>
                <option value="community">커뮤니티</option>
              </select>
            </div>
            <div>
              <label class="text-xs font-bold text-gray-600 block mb-1">위치</label>
              <div class="border rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-700">
                {{ positionLabels[selectedSlot.position] }}
                <span class="text-amber-600 font-bold ml-1">({{ dailyCosts[selectedSlot.position] }}P/일)</span>
              </div>
            </div>
          </div>

          <!-- 지역 범위 (Zip Code 기반) -->
          <div class="border rounded-lg p-4 bg-gray-50/50">
            <label class="text-xs font-bold text-gray-600 block mb-2">🌍 타겟 지역</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <select v-model="adForm.geo_scope" @change="onGeoScopeChange" class="w-full border rounded-lg px-3 py-2 text-sm">
                  <option value="all">전국</option>
                  <option value="state">주</option>
                  <option value="county">카운티</option>
                  <option value="city">시티</option>
                </select>
              </div>
              <div v-if="adForm.geo_scope !== 'all'">
                <div class="flex gap-2">
                  <input v-model="zipInput" @input="onZipInput" placeholder="Zip Code 입력 (5자리)"
                    maxlength="5" class="flex-1 border rounded-lg px-3 py-2 text-sm" />
                  <button @click="lookupZip" :disabled="zipLoading" class="bg-amber-400 text-amber-900 font-bold px-3 py-2 rounded-lg text-xs hover:bg-amber-500 disabled:opacity-50">
                    {{ zipLoading ? '...' : '조회' }}
                  </button>
                </div>
              </div>
            </div>
            <!-- Zip Code 결과 -->
            <div v-if="zipResult && adForm.geo_scope !== 'all'" class="mt-3 p-3 bg-white rounded-lg border space-y-2">
              <div class="text-xs text-gray-500">Zip Code 조회 결과:</div>
              <div class="grid grid-cols-3 gap-2 text-sm">
                <div><span class="text-[10px] text-gray-400 block">주</span><span class="font-bold">{{ zipResult.state }}</span></div>
                <div><span class="text-[10px] text-gray-400 block">카운티</span><span class="font-bold">{{ zipResult.county || '-' }}</span></div>
                <div><span class="text-[10px] text-gray-400 block">시티</span><span class="font-bold">{{ zipResult.city }}</span></div>
              </div>
              <div class="text-[10px] text-green-600 font-bold">
                ✅ {{ {state:'주',county:'카운티',city:'시티'}[adForm.geo_scope] }}: {{ adForm.geo_value }} 자동 설정됨
              </div>
            </div>
          </div>

          <!-- 기간 -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-xs font-bold text-gray-600 block mb-1">시작일</label>
              <input v-model="adForm.start_date" type="date" :min="today" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="text-xs font-bold text-gray-600 block mb-1">종료일</label>
              <input v-model="adForm.end_date" type="date" :min="adForm.start_date || today" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>

          <!-- 비용 계산 -->
          <div v-if="totalCost > 0" class="bg-amber-50 rounded-lg p-4 border border-amber-200">
            <div class="flex justify-between items-center">
              <div>
                <div class="text-xs text-gray-500">일일 비용</div>
                <div class="text-sm font-bold text-gray-800">{{ calculatedDailyCost.toLocaleString() }}P/일</div>
                <div class="text-[10px] text-gray-400 mt-0.5">
                  {{ dailyCosts[selectedSlot.position] }}P
                  <span v-if="adForm.page==='all'"> × 1.5 (전체 페이지)</span>
                  <span v-else-if="adForm.page==='home'"> × 1.3 (홈)</span>
                </div>
              </div>
              <div class="text-right">
                <div class="text-xs text-gray-500">총 비용 ({{ days }}일)</div>
                <div class="text-2xl font-black text-amber-700">{{ totalCost.toLocaleString() }}P</div>
                <div class="text-[10px]" :class="hasEnough ? 'text-green-600' : 'text-red-600'">
                  내 포인트: {{ (auth.user?.points||0).toLocaleString() }}P {{ hasEnough ? '✅' : '❌ 부족' }}
                </div>
              </div>
            </div>
          </div>

          <!-- 신청 버튼 -->
          <button @click="submitAd" :disabled="submitting || !canSubmit"
            class="w-full py-3 rounded-xl font-bold text-sm transition disabled:opacity-50"
            :class="canSubmit ? 'bg-amber-400 text-amber-900 hover:bg-amber-500' : 'bg-gray-200 text-gray-400'">
            {{ submitting ? '신청 중...' : `광고 신청하기 (${totalCost.toLocaleString()}P 차감)` }}
          </button>
        </div>
      </div>
    </Transition>

    <!-- ═══ 내 광고 목록 ═══ -->
    <div class="bg-white rounded-2xl shadow-sm border p-5">
      <h2 class="font-bold text-gray-800 mb-4">📋 내 광고 신청 내역</h2>
      <div v-if="loading" class="text-center py-8 text-gray-400 text-sm">로딩중...</div>
      <div v-else-if="!myAds.length" class="text-center py-8 text-gray-400 text-sm">신청한 광고가 없습니다</div>
      <div v-else class="space-y-3">
        <div v-for="ad in myAds" :key="ad.id" class="border rounded-xl p-4 flex gap-4 hover:bg-gray-50/50 transition">
          <div class="w-28 h-16 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
            <img :src="ad.image_url" class="w-full h-full object-cover" @error="e=>e.target.src='/images/placeholder.png'" />
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-xs px-2 py-0.5 rounded-full font-bold" :class="statusClasses[ad.status]">
                {{ statusLabels[ad.status] }}
              </span>
              <span class="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full">{{ pageLabels[ad.page] }}</span>
              <span class="text-[10px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full">{{ positionLabels[ad.position] }}</span>
            </div>
            <div class="text-sm font-bold text-gray-800 truncate mt-1">{{ ad.title }}</div>
            <div class="text-[10px] text-gray-400 mt-0.5">
              {{ ad.start_date?.slice(0,10) }} ~ {{ ad.end_date?.slice(0,10) }} · {{ ad.total_cost }}P · 노출{{ ad.impressions || 0 }} · 클릭{{ ad.clicks || 0 }}
            </div>
            <div v-if="ad.reject_reason" class="text-[10px] text-red-500 mt-0.5">거절사유: {{ ad.reject_reason }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useModal } from '../../composables/useModal'
import axios from 'axios'

const auth = useAuthStore()
const { showAlert } = useModal()

const loading = ref(true)
const myAds = ref([])
const selectedSlot = ref(null)
const submitting = ref(false)
const imagePreview = ref(null)
const adImage = ref(null)
const zipInput = ref('')
const zipLoading = ref(false)
const zipResult = ref(null)

const adForm = reactive({
  title: '', link_url: '', page: 'home', position: 'top',
  geo_scope: 'all', geo_value: '', start_date: '', end_date: ''
})

const today = new Date().toISOString().slice(0, 10)

const dailyCosts = { top: 500, center: 300, left: 200, right: 200 }
const positionLabels = { top: '상단', center: '중앙', left: '좌측', right: '우측' }
const pageLabels = { home:'홈', market:'장터', jobs:'구인', directory:'업소록', news:'뉴스', qa:'Q&A', recipes:'레시피', community:'커뮤니티', all:'전체' }
const statusLabels = { pending:'승인대기', active:'게시중', rejected:'거절', expired:'만료', paused:'중지' }
const statusClasses = {
  pending: 'bg-amber-100 text-amber-700',
  active: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
  expired: 'bg-gray-200 text-gray-500',
  paused: 'bg-gray-200 text-gray-500'
}
const sizeGuide = { top: '728 × 90 px', center: '468 × 60 px', left: '200 × 150 px', right: '300 × 250 px' }

function selectSlot(position, slot) {
  selectedSlot.value = { position, slot }
  adForm.position = position
  zipResult.value = null
  zipInput.value = ''
}

function onImageChange(e) {
  const file = e.target.files[0]
  if (file) {
    adImage.value = file
    imagePreview.value = URL.createObjectURL(file)
  }
}

function onGeoScopeChange() {
  adForm.geo_value = ''
  zipResult.value = null
  zipInput.value = ''
}

function onZipInput() {
  if (zipInput.value.length === 5) lookupZip()
}

async function lookupZip() {
  if (zipInput.value.length !== 5) return
  zipLoading.value = true
  try {
    const resp = await fetch(`https://api.zippopotam.us/us/${zipInput.value}`)
    if (!resp.ok) throw new Error('Invalid zip')
    const data = await resp.json()
    const place = data.places?.[0]
    if (place) {
      zipResult.value = {
        state: place['state abbreviation'] || place.state,
        city: place['place name'],
        county: ''
      }
      // 지역 범위에 따라 자동 설정
      if (adForm.geo_scope === 'state') adForm.geo_value = place['state abbreviation'] || place.state
      else if (adForm.geo_scope === 'city') adForm.geo_value = place['place name']
      else if (adForm.geo_scope === 'county') adForm.geo_value = place['place name']
    }
  } catch {
    zipResult.value = null
    showAlert('유효하지 않은 Zip Code입니다', '오류')
  }
  zipLoading.value = false
}

const calculatedDailyCost = computed(() => {
  let cost = dailyCosts[selectedSlot.value?.position] || 200
  if (adForm.page === 'all') cost = Math.floor(cost * 1.5)
  else if (adForm.page === 'home') cost = Math.floor(cost * 1.3)
  return cost
})

const days = computed(() => {
  if (!adForm.start_date || !adForm.end_date) return 0
  const d = Math.ceil((new Date(adForm.end_date) - new Date(adForm.start_date)) / 86400000) + 1
  return d > 0 ? d : 0
})

const totalCost = computed(() => calculatedDailyCost.value * days.value)
const hasEnough = computed(() => (auth.user?.points || 0) >= totalCost.value)
const canSubmit = computed(() => adForm.title && adImage.value && adForm.start_date && adForm.end_date && days.value > 0 && hasEnough.value && (adForm.geo_scope === 'all' || adForm.geo_value))

async function submitAd() {
  if (!canSubmit.value || submitting.value) return
  submitting.value = true
  const fd = new FormData()
  Object.keys(adForm).forEach(k => fd.append(k, adForm[k]))
  if (adImage.value) fd.append('image', adImage.value)
  try {
    const { data } = await axios.post('/api/banners/apply', fd)
    showAlert(data.message, '광고 신청')
    selectedSlot.value = null
    adForm.title = ''; adForm.link_url = ''; adForm.page = 'home'; adForm.geo_scope = 'all'; adForm.geo_value = ''; adForm.start_date = ''; adForm.end_date = ''
    adImage.value = null; imagePreview.value = null
    await loadMyAds()
  } catch (e) {
    showAlert(e.response?.data?.message || '신청 실패', '오류')
  }
  submitting.value = false
}

async function loadMyAds() {
  try {
    const { data } = await axios.get('/api/banners/my')
    myAds.value = data.data || []
  } catch {}
  loading.value = false
}

onMounted(loadMyAds)
</script>

<style scoped>
.slide-enter-active, .slide-leave-active { transition: all 0.3s ease; }
.slide-enter-from, .slide-leave-to { opacity: 0; transform: translateY(-10px); }
</style>
