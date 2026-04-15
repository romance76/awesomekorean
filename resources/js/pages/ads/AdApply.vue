<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-5xl mx-auto px-4 py-5">
    <h1 class="text-xl font-black text-gray-800 mb-2">📢 광고 신청 (월간 경매)</h1>
    <p class="text-sm text-gray-500 mb-1">매달 말일 24시간 입찰 접수 → 최고 입찰자 순으로 배정</p>
    <p class="text-xs text-amber-600 font-bold mb-5">다음 경매: {{ nextAuctionDate }}</p>

    <!-- ═══ Step 1: 페이지 선택 ═══ -->
    <div class="bg-white rounded-2xl shadow-sm border p-5 mb-5">
      <h2 class="font-bold text-gray-800 text-sm mb-3">1️⃣ 광고 노출 페이지 선택</h2>
      <div class="flex gap-3 mb-4">
        <button @click="pageType='home'; selectedSubs=[]" class="flex-1 py-3 rounded-xl font-bold text-sm border-2 transition"
          :class="pageType==='home' ? 'border-amber-500 bg-amber-50 text-amber-800' : 'border-gray-200 text-gray-500 hover:border-amber-300'">
          🏠 메인 (홈) — 1개 페이지
        </button>
        <button @click="pageType='sub'" class="flex-1 py-3 rounded-xl font-bold text-sm border-2 transition"
          :class="pageType==='sub' ? 'border-amber-500 bg-amber-50 text-amber-800' : 'border-gray-200 text-gray-500 hover:border-amber-300'">
          📄 서브 페이지 (복수 선택 가능)
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
      <div v-if="pageCount > 1" class="mt-2 text-xs text-amber-600 font-bold">
        {{ pageCount }}개 페이지 선택 → 가격 × {{ pageCount }}
      </div>
    </div>

    <!-- ═══ Step 2: 지역 선택 (가격에 영향) ═══ -->
    <div class="bg-white rounded-2xl shadow-sm border p-5 mb-5">
      <h2 class="font-bold text-gray-800 text-sm mb-3">2️⃣ 타겟 지역 선택</h2>
      <p class="text-xs text-gray-400 mb-3">페이지 종류에 따라 지역 설정이 다릅니다</p>

      <!-- 전국 페이지 안내 -->
      <div v-if="selectedNationalPages.length" class="bg-blue-50 border border-blue-200 rounded-xl p-3 mb-3">
        <div class="text-xs font-bold text-blue-700 mb-1">🌐 전국 페이지 (자동 전국 노출)</div>
        <div class="text-[10px] text-blue-600">
          {{ selectedNationalPages.map(k => subPages.find(s => s.key === k)?.label || (k === 'home' ? '홈' : k)).join(', ') }}
          — 별도 지역 설정 불필요
        </div>
      </div>

      <!-- 지역별 페이지 개별 설정 -->
      <div v-if="selectedRegionalPages.length">
        <div class="text-xs font-bold text-gray-700 mb-2">📍 지역별 페이지 (개별 지역 선택)</div>
        <div class="space-y-2 mb-3">
          <div v-for="pk in selectedRegionalPages" :key="pk" class="border rounded-xl p-3 bg-gray-50">
            <div class="flex items-center gap-2 mb-2">
              <span class="text-xs font-bold text-gray-700">{{ subPages.find(s => s.key === pk)?.icon }} {{ subPages.find(s => s.key === pk)?.label }}</span>
              <span v-if="pageGeoSettings[pk]?.value" class="text-[10px] text-green-600 font-bold ml-auto">✅ {{ pageGeoSettings[pk].value }}</span>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
              <label class="flex items-center gap-1 cursor-pointer text-xs">
                <input type="radio" :name="'geo_'+pk" value="county" :checked="pageGeoSettings[pk]?.geo==='county'" @change="setPageGeo(pk,'county')" class="accent-green-500" />
                <span :class="pageGeoSettings[pk]?.geo==='county' ? 'text-green-700 font-bold' : 'text-gray-500'">카운티</span>
                <span class="text-[10px] text-gray-400">기본</span>
              </label>
              <label class="flex items-center gap-1 cursor-pointer text-xs">
                <input type="radio" :name="'geo_'+pk" value="state" :checked="pageGeoSettings[pk]?.geo==='state'" @change="setPageGeo(pk,'state')" class="accent-blue-500" />
                <span :class="pageGeoSettings[pk]?.geo==='state' ? 'text-blue-700 font-bold' : 'text-gray-500'">주</span>
                <span class="text-[10px] text-gray-400">+{{ geoMarkup.state.toLocaleString() }}P</span>
              </label>
              <label class="flex items-center gap-1 cursor-pointer text-xs">
                <input type="radio" :name="'geo_'+pk" value="all" :checked="pageGeoSettings[pk]?.geo==='all'" @change="setPageGeo(pk,'all')" class="accent-amber-500" />
                <span :class="pageGeoSettings[pk]?.geo==='all' ? 'text-amber-700 font-bold' : 'text-gray-500'">전국</span>
                <span class="text-[10px] text-gray-400">+{{ (geoMarkup.state + geoMarkup.national).toLocaleString() }}P</span>
              </label>
            </div>
          </div>
        </div>

        <!-- Zip Code 공유 조회 (카운티/주 선택된 페이지가 있을 때만) -->
        <div v-if="hasRegionalNeedingZip" class="border-t pt-3">
          <div class="text-xs font-bold text-gray-600 mb-2">📮 Zip Code 조회 (카운티/주 페이지에 일괄 적용)</div>
          <div class="flex gap-2">
            <input v-model="zipInput" @input="onZipInput" placeholder="Zip Code (5자리)" maxlength="5" class="flex-1 border rounded-lg px-3 py-2 text-sm" />
            <button @click="lookupZip" :disabled="zipLoading" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-xs">{{ zipLoading ? '...' : '조회' }}</button>
          </div>
          <div v-if="zipResult" class="mt-2 text-[10px] text-green-600 font-bold">✅ {{ zipResult.city }}, {{ zipResult.state }} — 해당 페이지에 적용됨</div>
        </div>
      </div>

      <!-- 선택된 페이지 없을 때 -->
      <div v-if="!selectedNationalPages.length && !selectedRegionalPages.length" class="text-center py-4 text-xs text-gray-400">
        먼저 Step 1에서 페이지를 선택해 주세요
      </div>
    </div>

    <!-- ═══ Step 3: 슬롯 등급 선택 ═══ -->
    <div class="bg-white rounded-2xl shadow-sm border p-5 mb-5">
      <h2 class="font-bold text-gray-800 text-sm mb-3">3️⃣ 광고 등급 · 슬롯 선택</h2>

      <div class="border-2 border-gray-200 rounded-xl overflow-hidden bg-gray-50">
        <div class="bg-gradient-to-r from-amber-400 to-orange-400 h-8 flex items-center px-4">
          <span class="text-[10px] font-black text-amber-900">SomeKorean — {{ pageType === 'home' ? '홈' : '서브 페이지' }}</span>
        </div>
        <div class="grid grid-cols-12 gap-2 p-3 min-h-[320px]">
          <!-- 왼쪽 사이드바 -->
          <div class="col-span-3 space-y-2">
            <div class="bg-white rounded-lg border p-2"><div class="text-[9px] font-bold text-gray-400">📋 카테고리</div></div>

            <div @click="selectSlot('left', 1, 'premium')"
              class="border-2 rounded-lg p-2 text-center cursor-pointer transition-all"
              :class="isSelected('left',1) ? 'border-amber-500 bg-amber-50 shadow-md' : 'border-yellow-400 bg-yellow-50/50 hover:border-amber-400'">
              <div class="text-xs mb-0.5">{{ isSelected('left',1) ? '✅' : '🥇' }}</div>
              <div class="text-[9px] font-black text-yellow-700">프리미엄</div>
              <div class="text-[8px] text-gray-500">고정 독점 · 200×150</div>
              <div class="text-[9px] font-bold text-red-600 mt-0.5">{{ slotMinPrice('left','premium').toLocaleString() }}P/월{{ pageCount>1 ? ' ×'+pageCount : '' }}</div>
            </div>

            <div @click="selectSlot('left', 2, 'standard')"
              class="border-2 rounded-lg p-2 text-center cursor-pointer transition-all"
              :class="isSelected('left',2) ? 'border-amber-500 bg-amber-50 shadow-md' : 'border-blue-300 bg-blue-50/50 hover:border-amber-400'">
              <div class="text-xs mb-0.5">{{ isSelected('left',2) ? '✅' : '🥈' }}</div>
              <div class="text-[9px] font-black text-blue-700">스탠다드</div>
              <div class="text-[8px] text-gray-500">2개 랜덤 · 200×150</div>
              <div class="text-[9px] font-bold text-red-600 mt-0.5">{{ slotMinPrice('left','standard').toLocaleString() }}P/월{{ pageCount>1 ? ' ×'+pageCount : '' }}</div>
            </div>

            <div @click="selectSlot('left', 3, 'economy')"
              class="border-2 rounded-lg p-2 text-center cursor-pointer transition-all"
              :class="isSelected('left',3) ? 'border-amber-500 bg-amber-50 shadow-md' : 'border-green-300 bg-green-50/50 hover:border-amber-400'">
              <div class="text-xs mb-0.5">{{ isSelected('left',3) ? '✅' : '🥉' }}</div>
              <div class="text-[9px] font-black text-green-700">이코노미</div>
              <div class="text-[8px] text-gray-500">5개 랜덤 · 200×150</div>
              <div class="text-[9px] font-bold text-red-600 mt-0.5">{{ slotMinPrice('left','economy').toLocaleString() }}P/월{{ pageCount>1 ? ' ×'+pageCount : '' }}</div>
            </div>
          </div>

          <!-- 메인 -->
          <div class="col-span-6">
            <div class="bg-white rounded-lg border p-4 h-full flex flex-col justify-center items-center">
              <div class="text-[10px] text-gray-300 font-bold mb-3">메인 콘텐츠 영역</div>
              <div class="space-y-1.5 w-full"><div v-for="i in 5" :key="i" class="h-2 bg-gray-100 rounded w-full"></div></div>
            </div>
          </div>

          <!-- 오른쪽 사이드바 -->
          <div class="col-span-3 space-y-2">
            <div class="bg-white rounded-lg border p-2"><div class="text-[9px] font-bold text-gray-400">🔥 인기글</div></div>

            <div @click="selectSlot('right', 1, 'premium')"
              class="border-2 rounded-lg p-2 text-center cursor-pointer transition-all"
              :class="isSelected('right',1) ? 'border-amber-500 bg-amber-50 shadow-md' : 'border-yellow-400 bg-yellow-50/50 hover:border-amber-400'">
              <div class="text-xs mb-0.5">{{ isSelected('right',1) ? '✅' : '🥇' }}</div>
              <div class="text-[9px] font-black text-yellow-700">프리미엄</div>
              <div class="text-[8px] text-gray-500">고정 독점 · 300×250</div>
              <div class="text-[9px] font-bold text-red-600 mt-0.5">{{ slotMinPrice('right','premium').toLocaleString() }}P/월{{ pageCount>1 ? ' ×'+pageCount : '' }}</div>
            </div>

            <div @click="selectSlot('right', 2, 'economy')"
              class="border-2 rounded-lg p-2 text-center cursor-pointer transition-all"
              :class="isSelected('right',2) ? 'border-amber-500 bg-amber-50 shadow-md' : 'border-green-300 bg-green-50/50 hover:border-amber-400'">
              <div class="text-xs mb-0.5">{{ isSelected('right',2) ? '✅' : '🥉' }}</div>
              <div class="text-[9px] font-black text-green-700">이코노미</div>
              <div class="text-[8px] text-gray-500">3개 랜덤 · 300×250</div>
              <div class="text-[9px] font-bold text-red-600 mt-0.5">{{ slotMinPrice('right','economy').toLocaleString() }}P/월{{ pageCount>1 ? ' ×'+pageCount : '' }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- 등급 설명 -->
      <div class="mt-3 grid grid-cols-3 gap-2 text-[10px]">
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-2"><span class="font-bold text-yellow-700">🥇 프리미엄</span><br>한 달 독점. 100% 보장.</div>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-2"><span class="font-bold text-blue-700">🥈 스탠다드</span><br>2명 랜덤 교대. ~50%.</div>
        <div class="bg-green-50 border border-green-200 rounded-lg p-2"><span class="font-bold text-green-700">🥉 이코노미</span><br>3~5명 랜덤. 부담없는 가격.</div>
      </div>
    </div>

    <!-- ═══ Step 4: 상세 설정 ═══ -->
    <Transition name="slide">
      <div v-if="selectedSlot" class="bg-white rounded-2xl shadow-sm border p-5 mb-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-bold text-gray-800">4️⃣ {{ tierLabels[selectedSlot.tier] }} 광고 설정</h2>
          <button @click="selectedSlot=null" class="text-gray-400 hover:text-gray-600 text-sm">✕</button>
        </div>
        <div class="space-y-4">
          <div>
            <label class="text-xs font-bold text-gray-600 block mb-1">광고 제목</label>
            <input v-model="adForm.title" @input="saveDraft" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="광고 이름" />
          </div>
          <div>
            <label class="text-xs font-bold text-gray-600 block mb-1">광고 이미지 <span class="text-amber-600 font-normal">(권장: {{ recommendedSize }})</span></label>
            <input ref="fileInput" type="file" accept="image/*" @change="onImageChange" class="w-full border rounded-lg px-3 py-2 text-sm" />
            <!-- 이미지 미리보기 + 사이즈 경고 -->
            <div v-if="imagePreview" class="mt-2 border rounded-lg p-3 bg-gray-50">
              <img :src="imagePreview" class="max-h-40 rounded-lg border mx-auto" />
              <div class="mt-2 text-center">
                <div class="text-xs text-gray-600">업로드 이미지: <span class="font-bold">{{ imgWidth }}×{{ imgHeight }}px</span></div>
                <div v-if="imgSizeOk" class="text-xs text-green-600 font-bold mt-1">✅ 권장 사이즈와 일치합니다</div>
                <div v-else-if="imgRatioOk" class="text-xs text-amber-600 font-bold mt-1">⚠️ 비율은 맞지만 사이즈가 다릅니다 (자동 조정됨)</div>
                <div v-else class="text-xs text-red-600 font-bold mt-1">❌ 권장 비율({{ recommendedRatio }})과 다릅니다 — 이미지가 잘릴 수 있습니다</div>
                <div class="flex justify-center gap-2 mt-2">
                  <button @click="confirmImage" class="bg-green-500 text-white font-bold px-4 py-1.5 rounded-lg text-xs">이대로 사용</button>
                  <button @click="resetImage" class="bg-gray-300 text-gray-700 font-bold px-4 py-1.5 rounded-lg text-xs">다시 업로드</button>
                </div>
              </div>
            </div>
            <div v-if="imageConfirmed" class="mt-1 text-[10px] text-green-600 font-bold">✅ 이미지 확정됨 ({{ imgWidth }}×{{ imgHeight }}px)</div>
          </div>
          <div>
            <label class="text-xs font-bold text-gray-600 block mb-1">클릭 시 URL (선택)</label>
            <div class="flex gap-2">
              <input v-model="adForm.link_url" @input="saveDraft" class="flex-1 border rounded-lg px-3 py-2 text-sm" placeholder="somekorean.com 또는 https://..." />
              <button v-if="adForm.link_url" @click="previewUrl" type="button" class="bg-blue-500 text-white font-bold px-3 py-2 rounded-lg text-xs hover:bg-blue-600 flex-shrink-0">확인</button>
            </div>
            <div v-if="urlConfirmed" class="mt-1 text-[10px] text-green-600 font-bold">✅ {{ normalizedUrl }} 확정됨</div>
          </div>

          <!-- 입찰 금액 (자동 계산) -->
          <div class="border-2 border-amber-300 rounded-xl p-4 bg-amber-50/50">
            <label class="text-xs font-bold text-amber-800 block mb-2">💰 월간 입찰 금액</label>
            <!-- 가격 산출 내역 -->
            <div class="bg-white rounded-lg p-3 mb-3 text-xs space-y-1 border">
              <div class="flex justify-between">
                <span class="text-gray-500">슬롯: {{ posLabels[selectedSlot.position] }} {{ tierLabels[selectedSlot.tier] }}</span>
                <span class="font-bold">{{ basePricePerSlot.toLocaleString() }}P</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">지역 추가금 합계</span>
                <span class="font-bold">+{{ geoExtra.toLocaleString() }}P</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">페이지: {{ pageCount }}개{{ pageCount>1 ? ' ('+selectedPageNames+')' : '' }}</span>
                <span class="font-bold">× {{ pageCount }}</span>
              </div>
              <div class="flex justify-between border-t pt-1 mt-1">
                <span class="font-bold text-amber-800">최소 입찰가</span>
                <span class="font-black text-amber-800">{{ totalMinBid.toLocaleString() }}P</span>
              </div>
            </div>

            <div class="flex items-center gap-3">
              <input type="number" v-model.number="adForm.bid_amount" :min="totalMinBid" step="100" @input="saveDraft"
                class="flex-1 border-2 border-amber-400 rounded-lg px-4 py-3 text-lg font-black text-amber-800 text-center" />
              <span class="text-lg font-black text-amber-700">P</span>
            </div>
            <div class="mt-2 text-xs">
              <span v-if="hasEnough" class="text-green-600">보유: {{ (auth.user?.points||0).toLocaleString() }}P ✅</span>
              <span v-else class="text-red-600">
                보유: {{ (auth.user?.points||0).toLocaleString() }}P ❌
                <button @click="goPointShop" class="ml-2 bg-red-500 text-white px-3 py-1 rounded-lg text-[10px] font-bold">충전 →</button>
              </span>
            </div>
          </div>

          <button @click="submitAd" :disabled="submitting || !canSubmit"
            class="w-full py-3 rounded-xl font-bold text-sm transition disabled:opacity-50"
            :class="canSubmit ? 'bg-amber-400 text-amber-900 hover:bg-amber-500' : 'bg-gray-200 text-gray-400'">
            {{ submitting ? '신청 중...' : `입찰 신청 (${(adForm.bid_amount||0).toLocaleString()}P)` }}
          </button>
        </div>
      </div>
    </Transition>

    <!-- ═══ 내 광고 ═══ -->
    <div class="bg-white rounded-2xl shadow-sm border p-5">
      <h2 class="font-bold text-gray-800 mb-4">📋 내 입찰 내역</h2>
      <div v-if="loading" class="text-center py-8 text-gray-400 text-sm">로딩중...</div>
      <div v-else-if="!myAds.length" class="text-center py-8 text-gray-400 text-sm">신청한 광고가 없습니다</div>
      <div v-else class="space-y-3">
        <div v-for="ad in myAds" :key="ad.id" class="border rounded-xl p-3 flex gap-3">
          <div class="w-20 h-14 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
            <img :src="ad.image_url" class="w-full h-full object-cover" @error="e=>e.target.style.display='none'" />
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-1.5 flex-wrap">
              <span class="text-[10px] px-2 py-0.5 rounded-full font-bold" :class="statusClasses[ad.status]">{{ statusLabels[ad.status] }}</span>
              <span class="text-[10px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full">{{ posLabels[ad.position] }}{{ ad.slot_number }}</span>
              <span class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-bold">{{ (ad.bid_amount||0).toLocaleString() }}P</span>
            </div>
            <div class="text-sm font-bold text-gray-800 truncate mt-0.5">{{ ad.title }}</div>
            <div class="text-[10px] text-gray-400">{{ (ad.target_pages||[ad.page]).join(', ') }} · {{ ad.geo_scope!=='all'?ad.geo_value:'전국' }}</div>
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
const urlConfirmed = ref(false)
const normalizedUrl = ref('')
const pageType = ref('home')
const selectedSubs = ref([])
const DRAFT_KEY = 'sk_ad_draft'

// 카운티 기본가 (관리자 설정에서 로드)
const basePrices = ref({
  left_premium: 8000, left_standard: 7000, left_economy: 4000,
  right_premium: 10000, right_economy: 6000
})
// 지역별 추가금 (관리자 설정에서 로드)
const geoMarkup = ref({ state: 2000, national: 3000 })

const adForm = reactive({ title: '', link_url: '', bid_amount: 4000 })

const subPages = [
  { key: 'community', icon: '💬', label: '커뮤니티' }, { key: 'qa', icon: '❓', label: 'Q&A' },
  { key: 'jobs', icon: '💼', label: '구인구직' }, { key: 'market', icon: '🛒', label: '중고장터' },
  { key: 'realestate', icon: '🏠', label: '부동산' }, { key: 'directory', icon: '🏪', label: '업소록' },
  { key: 'clubs', icon: '👥', label: '동호회' }, { key: 'news', icon: '📰', label: '뉴스' },
  { key: 'recipes', icon: '🍳', label: '레시피' }, { key: 'groupbuy', icon: '🤝', label: '공동구매' },
  { key: 'events', icon: '🎉', label: '이벤트' }, { key: 'shorts', icon: '🎬', label: '숏츠' },
  { key: 'games', icon: '🎮', label: '게임' }, { key: 'music', icon: '🎵', label: '음악' },
]

// 전국 페이지 (자동 geo_scope='all')
const nationalPages = ['home', 'community', 'qa', 'news', 'recipes', 'shorts', 'games', 'music']
// 지역별 페이지 (개별 geo 선택 필요)
const regionalPages = ['jobs', 'market', 'realestate', 'directory', 'clubs', 'events', 'groupbuy']

// 페이지별 지역 설정
const pageGeoSettings = ref({})

const posLabels = { left: '좌측', right: '우측' }
const tierLabels = { premium: '🥇 프리미엄', standard: '🥈 스탠다드', economy: '🥉 이코노미' }
const geoLabels = { all: '전국', state: '주', county: '카운티' }
const statusLabels = { pending:'입찰대기', active:'게시중', rejected:'거절', expired:'만료', paused:'중지' }
const statusClasses = { pending:'bg-amber-100 text-amber-700', active:'bg-green-100 text-green-700', rejected:'bg-red-100 text-red-700', expired:'bg-gray-200 text-gray-500', paused:'bg-gray-200 text-gray-500' }

const nextAuctionDate = computed(() => {
  const now = new Date()
  const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  return `${lastDay.getFullYear()}.${lastDay.getMonth()+1}.${lastDay.getDate()}`
})

const pageCount = computed(() => pageType.value === 'home' ? 1 : Math.max(1, selectedSubs.value.length))
const selectedPageNames = computed(() => selectedSubs.value.map(k => subPages.find(s => s.key === k)?.label).join(', '))

// 슬롯별 카운티 기본가
function getBasePrice(position, tier) {
  return basePrices.value[`${position}_${tier}`] || 4000
}

// 현재 선택된 전국 페이지 목록
const selectedNationalPages = computed(() => {
  if (pageType.value === 'home') return ['home']
  return selectedSubs.value.filter(k => nationalPages.includes(k))
})

// 현재 선택된 지역별 페이지 목록
const selectedRegionalPages = computed(() => {
  if (pageType.value === 'home') return []
  return selectedSubs.value.filter(k => regionalPages.includes(k))
})

// 카운티/주 선택된 지역 페이지가 있는지 (zip 조회 필요 여부)
const hasRegionalNeedingZip = computed(() => {
  return selectedRegionalPages.value.some(pk => {
    const g = pageGeoSettings.value[pk]?.geo
    return g === 'county' || g === 'state'
  })
})

// 페이지별 geo 설정 함수
function setPageGeo(pageKey, geo) {
  if (!pageGeoSettings.value[pageKey]) pageGeoSettings.value[pageKey] = { geo: 'county', value: '' }
  pageGeoSettings.value[pageKey] = { ...pageGeoSettings.value[pageKey], geo, value: geo === 'all' ? '' : pageGeoSettings.value[pageKey].value }
  saveDraft()
}

// 선택/해제 시 pageGeoSettings 자동 관리
watch(selectedSubs, (newVal, oldVal) => {
  const current = { ...pageGeoSettings.value }
  // 새로 추가된 페이지
  for (const key of newVal) {
    if (!current[key]) {
      if (nationalPages.includes(key)) {
        current[key] = { geo: 'all', value: '' }
      } else {
        current[key] = { geo: 'county', value: '' }
      }
    }
  }
  // 제거된 페이지
  for (const key of Object.keys(current)) {
    if (!newVal.includes(key) && key !== 'home') {
      delete current[key]
    }
  }
  pageGeoSettings.value = current
}, { deep: true })

// 홈 선택 시 자동 설정
watch(pageType, (val) => {
  if (val === 'home') {
    pageGeoSettings.value = { home: { geo: 'all', value: '' } }
  }
})

// 페이지별 지역 추가금 계산
function getPageGeoExtra(pageKey) {
  const setting = pageGeoSettings.value[pageKey]
  if (!setting || setting.geo === 'county') return 0
  if (setting.geo === 'state') return geoMarkup.value.state
  if (setting.geo === 'all') return geoMarkup.value.state + geoMarkup.value.national
  return 0
}

// 전체 지역 추가금 합계
const geoExtra = computed(() => {
  if (pageType.value === 'home') {
    return geoMarkup.value.state + geoMarkup.value.national // 홈은 전국
  }
  let total = 0
  for (const key of selectedSubs.value) {
    total += getPageGeoExtra(key)
  }
  return total
})

// 슬롯 1개의 기본가
const basePricePerSlot = computed(() => {
  if (!selectedSlot.value) return 0
  return getBasePrice(selectedSlot.value.position, selectedSlot.value.tier)
})

// 총 최소 입찰가 = 기본가 × 페이지 수 + 각 페이지별 지역 추가금 합계
const totalMinBid = computed(() => basePricePerSlot.value * pageCount.value + geoExtra.value)

// 슬롯 선택 UI에 표시할 가격 (페이지당 평균 geo 적용)
function slotMinPrice(position, tier) {
  const base = getBasePrice(position, tier)
  const avgGeo = pageCount.value > 0 ? Math.round(geoExtra.value / pageCount.value) : 0
  return base + avgGeo
}

const hasEnough = computed(() => (auth.user?.points || 0) >= (adForm.bid_amount || 0))
const canSubmit = computed(() => {
  if (!adForm.title || !adImage.value || !imageConfirmed.value || !selectedSlot.value) return false
  if (adForm.bid_amount < totalMinBid.value) return false
  if (!hasEnough.value) return false
  if (pageType.value === 'sub' && !selectedSubs.value.length) return false
  // 지역별 페이지는 카운티/주 선택 시 geo_value 필수
  for (const pk of selectedRegionalPages.value) {
    const s = pageGeoSettings.value[pk]
    if (s && s.geo !== 'all' && !s.value) return false
  }
  return true
})

function isSelected(pos, slot) { return selectedSlot.value?.position === pos && selectedSlot.value?.slot === slot }

function selectSlot(position, slot, tier) {
  selectedSlot.value = { position, slot, tier }
  // 항상 해당 슬롯의 최소 입찰가로 리셋
  const base = getBasePrice(position, tier)
  adForm.bid_amount = base * pageCount.value + geoExtra.value
  saveDraft()
}

// 지역/페이지 변경 시 최소 입찰가로 리셋
watch([pageCount, pageGeoSettings], () => {
  if (selectedSlot.value) {
    adForm.bid_amount = totalMinBid.value
  }
}, { deep: true })

const fileInput = ref(null)
const imgWidth = ref(0)
const imgHeight = ref(0)
const imageConfirmed = ref(false)

const recommendedSize = computed(() => selectedSlot.value?.position === 'left' ? '200×150px' : '300×250px')
const recommendedW = computed(() => selectedSlot.value?.position === 'left' ? 200 : 300)
const recommendedH = computed(() => selectedSlot.value?.position === 'left' ? 150 : 250)
const recommendedRatio = computed(() => `${recommendedW.value}:${recommendedH.value}`)

const imgSizeOk = computed(() => imgWidth.value === recommendedW.value && imgHeight.value === recommendedH.value)
const imgRatioOk = computed(() => {
  if (!imgWidth.value || !imgHeight.value) return false
  const target = recommendedW.value / recommendedH.value
  const actual = imgWidth.value / imgHeight.value
  return Math.abs(target - actual) < 0.05
})

function onImageChange(e) {
  const f = e.target.files[0]
  if (!f) return
  imageConfirmed.value = false
  adImage.value = f
  imagePreview.value = URL.createObjectURL(f)
  // 이미지 크기 측정
  const img = new Image()
  img.onload = () => { imgWidth.value = img.naturalWidth; imgHeight.value = img.naturalHeight }
  img.src = imagePreview.value
}

function confirmImage() { imageConfirmed.value = true }
function resetImage() {
  adImage.value = null; imagePreview.value = null; imageConfirmed.value = false
  imgWidth.value = 0; imgHeight.value = 0
  if (fileInput.value) fileInput.value.value = ''
}

// URL 정규화 + 미리보기 팝업
function normalizeUrl(raw) {
  let url = raw.trim()
  if (!url) return ''
  if (!/^https?:\/\//i.test(url)) url = 'https://' + url
  return url
}

async function previewUrl() {
  const url = normalizeUrl(adForm.link_url)
  if (!url) return
  normalizedUrl.value = url

  const ok = await showConfirm(
    `이 주소가 맞습니까?\n\n${url}\n\n맞으면 "확인", 다시 입력하려면 "취소"`,
    'URL 확인'
  )
  if (ok) {
    adForm.link_url = url
    urlConfirmed.value = true
    saveDraft()
  } else {
    urlConfirmed.value = false
  }
}
function onZipInput() { if (zipInput.value.length === 5) lookupZip() }

async function lookupZip() {
  if (zipInput.value.length !== 5) return; zipLoading.value = true
  try {
    const r = await fetch(`https://api.zippopotam.us/us/${zipInput.value}`)
    if (!r.ok) throw 0; const d = await r.json(); const p = d.places?.[0]
    if (p) {
      zipResult.value = { state: p['state abbreviation'], city: p['place name'] }
      // 모든 지역별 페이지(카운티/주)에 일괄 적용
      const updated = { ...pageGeoSettings.value }
      for (const pk of selectedRegionalPages.value) {
        const s = updated[pk]
        if (s && s.geo !== 'all') {
          updated[pk] = { ...s, value: s.geo === 'state' ? p['state abbreviation'] : p['place name'] }
        }
      }
      pageGeoSettings.value = updated
      saveDraft()
    }
  } catch { showAlert('유효하지 않은 Zip Code', '오류') }
  zipLoading.value = false
}

function saveDraft() { try { localStorage.setItem(DRAFT_KEY, JSON.stringify({ pageType: pageType.value, selectedSubs: selectedSubs.value, selectedSlot: selectedSlot.value, adForm: { ...adForm }, pageGeoSettings: pageGeoSettings.value, zipInput: zipInput.value, ts: Date.now() })) } catch {} }
function loadDraft() { try { const r = localStorage.getItem(DRAFT_KEY); if (!r) return; const d = JSON.parse(r); if (Date.now() - d.ts > 86400000) { localStorage.removeItem(DRAFT_KEY); return }; pageType.value = d.pageType || 'home'; selectedSubs.value = d.selectedSubs || []; selectedSlot.value = d.selectedSlot || null; if (d.adForm) Object.assign(adForm, d.adForm); if (d.pageGeoSettings) pageGeoSettings.value = d.pageGeoSettings; zipInput.value = d.zipInput || '' } catch {} }
function clearDraft() { try { localStorage.removeItem(DRAFT_KEY) } catch {} }
watch([pageType, selectedSubs, selectedSlot, pageGeoSettings], saveDraft, { deep: true })

function goPointShop() { saveDraft(); router.push('/dashboard?tab=points') }

async function submitAd() {
  if (!canSubmit.value || submitting.value) return
  if (!hasEnough.value) { const ok = await showConfirm(`포인트 부족 (보유: ${(auth.user?.points || 0).toLocaleString()}P)\n충전?`, '부족'); if (ok) goPointShop(); return }
  submitting.value = true
  const fd = new FormData()
  fd.append('title', adForm.title); fd.append('link_url', adForm.link_url || '')
  fd.append('page', pageType.value === 'home' ? 'home' : 'sub')
  // 페이지별 지역 설정 포함한 target_pages
  const targetPages = []
  if (pageType.value === 'home') {
    targetPages.push({ page: 'home', geo: 'all', geo_value: '' })
  } else {
    for (const pk of selectedNationalPages.value) {
      targetPages.push({ page: pk, geo: 'all', geo_value: '' })
    }
    for (const pk of selectedRegionalPages.value) {
      const s = pageGeoSettings.value[pk] || { geo: 'county', value: '' }
      targetPages.push({ page: pk, geo: s.geo, geo_value: s.value })
    }
  }
  fd.append('target_pages', JSON.stringify(targetPages))
  fd.append('position', selectedSlot.value.position); fd.append('slot_number', selectedSlot.value.slot)
  fd.append('tier', selectedSlot.value.tier)
  fd.append('bid_amount', adForm.bid_amount)
  if (adImage.value) fd.append('image', adImage.value)
  try {
    const { data } = await axios.post('/api/banners/apply', fd)
    showAlert(data.message, '입찰 신청'); selectedSlot.value = null
    Object.assign(adForm, { title: '', link_url: '', bid_amount: 4000 })
    adImage.value = null; imagePreview.value = null; selectedSubs.value = []; pageGeoSettings.value = {}; clearDraft()
    await loadMyAds()
  } catch (e) {
    const m = e.response?.data?.message || '실패'
    if (m.includes('부족')) { const ok = await showConfirm(m + '\n충전?', '부족'); if (ok) goPointShop() } else showAlert(m, '오류')
  }
  submitting.value = false
}

async function loadMyAds() { try { const { data } = await axios.get('/api/banners/my'); myAds.value = data.data || [] } catch {}; loading.value = false }
async function loadPrices() {
  try {
    const { data } = await axios.get('/api/ad-settings/public')
    if (data.data?.slot_min_prices) basePrices.value = { ...basePrices.value, ...data.data.slot_min_prices }
    if (data.data?.geo_markup) geoMarkup.value = { ...geoMarkup.value, ...data.data.geo_markup }
  } catch {}
}

onMounted(() => { loadMyAds(); loadPrices(); loadDraft() })
</script>
<style scoped>
.slide-enter-active,.slide-leave-active{transition:all .3s ease}
.slide-enter-from,.slide-leave-to{opacity:0;transform:translateY(-10px)}
</style>
