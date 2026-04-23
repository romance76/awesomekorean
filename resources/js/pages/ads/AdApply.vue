<template>
<div :class="embedded ? '' : 'min-h-screen bg-gray-50'">
  <div :class="embedded ? '' : 'max-w-5xl mx-auto px-4 py-5'">
    <h1 class="text-xl font-black text-gray-800 mb-2">📢 광고 신청 (월간 경매)</h1>
    <p class="text-sm text-gray-500 mb-1">매달 말일 24시간 입찰 접수 → 최고 입찰자 순으로 배정</p>
    <p class="text-xs text-amber-600 font-bold mb-2">다음 경매: {{ nextAuctionDate }}</p>
    <div v-if="adDiscountPct" class="mb-5 bg-red-50 border border-red-200 rounded-lg px-3 py-2 flex items-center gap-2">
      <span class="text-lg">🎉</span>
      <div class="flex-1">
        <div class="text-sm font-bold text-red-700">{{ adPromotion?.title }} — 전체 광고 {{ adDiscountPct }}% 할인 진행중</div>
        <div class="text-[10px] text-red-500">종료: {{ fmtPromoEnd(adPromotion?.ends_at) }}</div>
      </div>
    </div>

    <!-- ═══ Step 1: 페이지 선택 (단일 선택) ═══ -->
    <div class="bg-white rounded-2xl shadow-sm border p-5 mb-5">
      <h2 class="font-bold text-gray-800 text-sm mb-3">1️⃣ 광고 노출 페이지 선택</h2>

      <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
        <!-- 홈 -->
        <label class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition text-xs font-bold"
          :class="selectedSub === 'home' ? 'border-amber-500 bg-amber-50 text-amber-800' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
          <input type="radio" name="page_select" value="home" v-model="selectedSub" class="accent-amber-500" />
          🏠 홈
        </label>
        <!-- 서브 페이지들 -->
        <label v-for="sp in subPages" :key="sp.key"
          class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition text-xs font-bold"
          :class="selectedSub === sp.key ? 'border-amber-500 bg-amber-50 text-amber-800' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
          <input type="radio" name="page_select" :value="sp.key" v-model="selectedSub" class="accent-amber-500" />
          {{ sp.icon }} {{ sp.label }}
        </label>
      </div>
    </div>

    <!-- ═══ Step 2: 지역 선택 (가격에 영향) ═══ -->
    <div class="bg-white rounded-2xl shadow-sm border p-5 mb-5">
      <h2 class="font-bold text-gray-800 text-sm mb-3">2️⃣ 타겟 지역 선택</h2>
      <p class="text-xs text-gray-400 mb-3">페이지 종류에 따라 지역 설정이 다릅니다</p>

      <!-- 페이지 미선택 -->
      <div v-if="!selectedSub" class="text-center py-4 text-xs text-gray-400">
        먼저 Step 1에서 페이지를 선택해 주세요
      </div>

      <!-- 전국 페이지 안내 -->
      <div v-else-if="isNationalPage" class="bg-blue-50 border border-blue-200 rounded-xl p-3">
        <div class="text-xs font-bold text-blue-700 mb-1">🌐 전국 자동 노출</div>
        <div class="text-[10px] text-blue-600">
          {{ selectedPageLabel }} — 전국 페이지이므로 별도 지역 설정이 불필요합니다
        </div>
      </div>

      <!-- 지역별 페이지 설정 -->
      <div v-else>
        <div class="text-xs font-bold text-gray-700 mb-2">📍 타겟 지역 선택</div>
        <div class="border rounded-xl p-3 bg-gray-50 mb-3">
          <div class="flex items-center gap-2 flex-wrap">
            <label class="flex items-center gap-1 cursor-pointer text-xs px-2 py-1.5 rounded-lg border transition"
              :class="adForm.geo_scope==='city' ? 'border-purple-400 bg-purple-50' : 'border-gray-200'">
              <input type="radio" name="geo_scope" value="city" v-model="adForm.geo_scope" class="accent-purple-500" />
              <span :class="adForm.geo_scope==='city' ? 'text-purple-700 font-bold' : 'text-gray-500'">시티</span>
              <span class="text-[10px] text-gray-400">-{{ geoMarkup.cityDiscount.toLocaleString() }}P</span>
            </label>
            <label class="flex items-center gap-1 cursor-pointer text-xs px-2 py-1.5 rounded-lg border transition"
              :class="adForm.geo_scope==='county' ? 'border-green-400 bg-green-50' : 'border-gray-200'">
              <input type="radio" name="geo_scope" value="county" v-model="adForm.geo_scope" class="accent-green-500" />
              <span :class="adForm.geo_scope==='county' ? 'text-green-700 font-bold' : 'text-gray-500'">카운티</span>
              <span class="text-[10px] text-amber-600 font-bold">기본</span>
            </label>
            <label class="flex items-center gap-1 cursor-pointer text-xs px-2 py-1.5 rounded-lg border transition"
              :class="adForm.geo_scope==='state' ? 'border-blue-400 bg-blue-50' : 'border-gray-200'">
              <input type="radio" name="geo_scope" value="state" v-model="adForm.geo_scope" class="accent-blue-500" />
              <span :class="adForm.geo_scope==='state' ? 'text-blue-700 font-bold' : 'text-gray-500'">주</span>
              <span class="text-[10px] text-gray-400">+{{ geoMarkup.state.toLocaleString() }}P</span>
            </label>
            <label class="flex items-center gap-1 cursor-pointer text-xs px-2 py-1.5 rounded-lg border transition"
              :class="adForm.geo_scope==='all' ? 'border-amber-400 bg-amber-50' : 'border-gray-200'">
              <input type="radio" name="geo_scope" value="all" v-model="adForm.geo_scope" class="accent-amber-500" />
              <span :class="adForm.geo_scope==='all' ? 'text-amber-700 font-bold' : 'text-gray-500'">전국</span>
              <span class="text-[10px] text-gray-400">+{{ (geoMarkup.state + geoMarkup.national).toLocaleString() }}P</span>
            </label>
          </div>
          <div class="mt-2 text-[10px] text-gray-400">
            {{ adForm.geo_scope === 'city' ? '특정 시티만 타겟 (가장 저렴)' : adForm.geo_scope === 'county' ? '카운티 전체 타겟 (기본가)' : adForm.geo_scope === 'state' ? '주 전체 타겟' : '미국 전국 타겟' }}
          </div>
        </div>

        <!-- Zip Code 조회 (카운티/주 선택 시) -->
        <div v-if="adForm.geo_scope !== 'all'" class="border-t pt-3">
          <div class="text-xs font-bold text-gray-600 mb-2">📮 Zip Code 조회</div>
          <div class="flex gap-2">
            <input v-model="zipInput" @input="onZipInput" placeholder="Zip Code (5자리)" maxlength="5" class="flex-1 border rounded-lg px-3 py-2 text-sm" />
            <button @click="lookupZip" :disabled="zipLoading" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-xs">{{ zipLoading ? '...' : '조회' }}</button>
          </div>
          <div v-if="zipResult" class="mt-2 text-[10px] text-green-600 font-bold">
            ✅ {{ adForm.geo_scope === 'city' ? zipResult.city + ', ' + zipResult.state : adForm.geo_scope === 'county' ? zipResult.county + ', ' + zipResult.state : zipResult.state }} — {{ adForm.geo_scope === 'city' ? '시티' : adForm.geo_scope === 'county' ? '카운티' : '주' }} 타겟 적용됨
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ Step 3: 슬롯 등급 선택 ═══ -->
    <div class="bg-white rounded-2xl shadow-sm border p-5 mb-5">
      <h2 class="font-bold text-gray-800 text-sm mb-3">3️⃣ 광고 등급 · 슬롯 선택</h2>

      <div v-if="!selectedSub" class="text-center py-8 text-xs text-gray-400">먼저 Step 1에서 페이지를 선택해 주세요</div>

      <template v-else>
        <div class="border-2 border-gray-200 rounded-xl overflow-hidden bg-gray-50">
          <div class="bg-gradient-to-r from-amber-400 to-orange-400 h-8 flex items-center px-4">
            <span class="text-[10px] font-black text-amber-900">AwesomeKorean — {{ selectedPageLabel }}</span>
          </div>
          <div class="grid grid-cols-12 gap-2 p-3 min-h-[320px]">
            <!-- 왼쪽 사이드바 -->
            <div v-if="pageSlotConfig.left_slots > 0" class="col-span-3 space-y-2">
              <div class="bg-white rounded-lg border p-2"><div class="text-[9px] font-bold text-gray-400">📋 카테고리</div></div>

              <div v-if="pageSlotConfig.left_slots >= 1" @click="selectSlot('left', 1, 'premium')"
                class="border-2 rounded-lg p-2 text-center cursor-pointer transition-all"
                :class="isSelected('left',1) ? 'border-amber-500 bg-amber-50 shadow-md' : 'border-yellow-400 bg-yellow-50/50 hover:border-amber-400'">
                <div class="text-xs mb-0.5">{{ isSelected('left',1) ? '✅' : '🥇' }}</div>
                <div class="text-[9px] font-black text-yellow-700">프리미엄</div>
                <div class="text-[8px] text-gray-500">고정 독점 · 200×140</div>
                <div class="text-[9px] font-bold text-red-600 mt-0.5">
                  <span v-if="adDiscountPct" class="text-gray-400 line-through font-normal mr-1">{{ slotOriginalPrice('left','premium').toLocaleString() }}</span>{{ slotMinPrice('left','premium').toLocaleString() }}P/월
                </div>
              </div>

              <div v-if="pageSlotConfig.left_slots >= 2" @click="selectSlot('left', 2, 'standard')"
                class="border-2 rounded-lg p-2 text-center cursor-pointer transition-all"
                :class="isSelected('left',2) ? 'border-amber-500 bg-amber-50 shadow-md' : 'border-blue-300 bg-blue-50/50 hover:border-amber-400'">
                <div class="text-xs mb-0.5">{{ isSelected('left',2) ? '✅' : '🥈' }}</div>
                <div class="text-[9px] font-black text-blue-700">스탠다드</div>
                <div class="text-[8px] text-gray-500">2개 랜덤 · 200×140</div>
                <div class="text-[9px] font-bold text-red-600 mt-0.5">
                  <span v-if="adDiscountPct" class="text-gray-400 line-through font-normal mr-1">{{ slotOriginalPrice('left','standard').toLocaleString() }}</span>{{ slotMinPrice('left','standard').toLocaleString() }}P/월
                </div>
              </div>

              <div v-if="pageSlotConfig.left_slots >= 3" @click="selectSlot('left', 3, 'economy')"
                class="border-2 rounded-lg p-2 text-center cursor-pointer transition-all"
                :class="isSelected('left',3) ? 'border-amber-500 bg-amber-50 shadow-md' : 'border-green-300 bg-green-50/50 hover:border-amber-400'">
                <div class="text-xs mb-0.5">{{ isSelected('left',3) ? '✅' : '🥉' }}</div>
                <div class="text-[9px] font-black text-green-700">이코노미</div>
                <div class="text-[8px] text-gray-500">5개 랜덤 · 200×140</div>
                <div class="text-[9px] font-bold text-red-600 mt-0.5">
                  <span v-if="adDiscountPct" class="text-gray-400 line-through font-normal mr-1">{{ slotOriginalPrice('left','economy').toLocaleString() }}</span>{{ slotMinPrice('left','economy').toLocaleString() }}P/월
                </div>
              </div>
            </div>

            <!-- 메인 -->
            <div :class="mainColSpan">
              <div class="bg-white rounded-lg border p-4 h-full flex flex-col justify-center items-center">
                <div class="text-[10px] text-gray-300 font-bold mb-3">메인 콘텐츠 영역</div>
                <div class="space-y-1.5 w-full"><div v-for="i in 5" :key="i" class="h-2 bg-gray-100 rounded w-full"></div></div>
              </div>
            </div>

            <!-- 오른쪽 사이드바 -->
            <div v-if="pageSlotConfig.right_slots > 0" class="col-span-3 space-y-2">
              <div class="bg-white rounded-lg border p-2"><div class="text-[9px] font-bold text-gray-400">🔥 인기글</div></div>

              <div v-if="pageSlotConfig.right_slots >= 1" @click="selectSlot('right', 1, 'premium')"
                class="border-2 rounded-lg p-2 text-center cursor-pointer transition-all"
                :class="isSelected('right',1) ? 'border-amber-500 bg-amber-50 shadow-md' : 'border-yellow-400 bg-yellow-50/50 hover:border-amber-400'">
                <div class="text-xs mb-0.5">{{ isSelected('right',1) ? '✅' : '🥇' }}</div>
                <div class="text-[9px] font-black text-yellow-700">프리미엄</div>
                <div class="text-[8px] text-gray-500">고정 독점 · 300×210</div>
                <div class="text-[9px] font-bold text-red-600 mt-0.5">
                  <span v-if="adDiscountPct" class="text-gray-400 line-through font-normal mr-1">{{ slotOriginalPrice('right','premium').toLocaleString() }}</span>{{ slotMinPrice('right','premium').toLocaleString() }}P/월
                </div>
              </div>

              <div v-if="pageSlotConfig.right_slots >= 2" @click="selectSlot('right', 2, 'economy')"
                class="border-2 rounded-lg p-2 text-center cursor-pointer transition-all"
                :class="isSelected('right',2) ? 'border-amber-500 bg-amber-50 shadow-md' : 'border-green-300 bg-green-50/50 hover:border-amber-400'">
                <div class="text-xs mb-0.5">{{ isSelected('right',2) ? '✅' : '🥉' }}</div>
                <div class="text-[9px] font-black text-green-700">이코노미</div>
                <div class="text-[8px] text-gray-500">3개 랜덤 · 300×210</div>
                <div class="text-[9px] font-bold text-red-600 mt-0.5">
                  <span v-if="adDiscountPct" class="text-gray-400 line-through font-normal mr-1">{{ slotOriginalPrice('right','economy').toLocaleString() }}</span>{{ slotMinPrice('right','economy').toLocaleString() }}P/월
                </div>
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
      </template>
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
              <input v-model="adForm.link_url" @input="saveDraft" class="flex-1 border rounded-lg px-3 py-2 text-sm" placeholder="awesomekorean.com 또는 https://..." />
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
                <span class="text-gray-500">지역 {{ geoExtra >= 0 ? '추가금' : '할인' }}</span>
                <span class="font-bold" :class="geoExtra < 0 ? 'text-red-500' : ''">{{ geoExtra >= 0 ? '+' : '' }}{{ geoExtra.toLocaleString() }}P</span>
              </div>
              <div v-if="adDiscountPct" class="flex justify-between text-red-600">
                <span class="font-bold">🎉 {{ adPromotion?.title }}</span>
                <span class="font-bold">-{{ adDiscountPct }}%</span>
              </div>
              <div class="flex justify-between border-t pt-1 mt-1">
                <span class="font-bold text-amber-800">최소 입찰가</span>
                <span class="font-black text-amber-800 flex items-baseline gap-2">
                  <span v-if="adDiscountPct" class="text-gray-400 line-through text-xs font-normal">{{ totalMinBidOriginal.toLocaleString() }}P</span>
                  {{ totalMinBid.toLocaleString() }}P
                </span>
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

const props = defineProps({ embedded: { type: Boolean, default: false } })
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
const selectedSub = ref('')
const DRAFT_KEY = 'sk_ad_draft'

// 카운티 기본가 (관리자 설정에서 로드)
const basePrices = ref({
  left_premium: 8000, left_standard: 7000, left_economy: 4000,
  right_premium: 10000, right_economy: 6000
})
// 지역별 추가금 (관리자 설정에서 로드)
const geoMarkup = ref({ cityDiscount: 1000, state: 2000, national: 3000 })

// 페이지별 슬롯 설정 (관리자 API에서 로드)
const pageConfigs = ref({})

const adForm = reactive({ title: '', link_url: '', bid_amount: 4000, geo_scope: 'county', geo_value: '' })

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

const posLabels = { left: '좌측', right: '우측' }
const tierLabels = { premium: '🥇 프리미엄', standard: '🥈 스탠다드', economy: '🥉 이코노미' }
const statusLabels = { pending:'입찰대기', active:'게시중', rejected:'거절', expired:'만료', paused:'중지' }
const statusClasses = { pending:'bg-amber-100 text-amber-700', active:'bg-green-100 text-green-700', rejected:'bg-red-100 text-red-700', expired:'bg-gray-200 text-gray-500', paused:'bg-gray-200 text-gray-500' }

const nextAuctionDate = computed(() => {
  const now = new Date()
  const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  return `${lastDay.getFullYear()}.${lastDay.getMonth()+1}.${lastDay.getDate()}`
})

// 선택된 페이지가 전국 페이지인지
const isNationalPage = computed(() => nationalPages.includes(selectedSub.value))

// 선택된 페이지의 라벨
const selectedPageLabel = computed(() => {
  if (selectedSub.value === 'home') return '홈'
  return subPages.find(s => s.key === selectedSub.value)?.label || selectedSub.value
})

// 선택된 페이지의 슬롯 설정 (관리자 API 데이터 기반, 기본값 fallback)
const pageSlotConfig = computed(() => {
  const cfg = pageConfigs.value[selectedSub.value]
  if (cfg) return cfg
  // 기본값: 좌 3, 우 2
  return { left_slots: 3, right_slots: 2 }
})

// 메인 콘텐츠 컬럼 span (사이드바 유무에 따라 동적)
const mainColSpan = computed(() => {
  const hasLeft = pageSlotConfig.value.left_slots > 0
  const hasRight = pageSlotConfig.value.right_slots > 0
  if (hasLeft && hasRight) return 'col-span-6'
  if (hasLeft || hasRight) return 'col-span-9'
  return 'col-span-12'
})

// 슬롯별 카운티 기본가
function getBasePrice(position, tier) {
  return basePrices.value[`${position}_${tier}`] || 4000
}

// 지역 추가금 계산 (단일 페이지)
const geoExtra = computed(() => {
  if (!selectedSub.value) return 0
  // 전국 페이지는 자동 전국이지만 추가금 포함
  if (isNationalPage.value) {
    return geoMarkup.value.state + geoMarkup.value.national
  }
  // 지역별 페이지: 카운티가 기본(0), 시티는 -1000, 주는 +2000, 전국은 +5000
  if (adForm.geo_scope === 'city') return -geoMarkup.value.cityDiscount
  if (adForm.geo_scope === 'county') return 0
  if (adForm.geo_scope === 'state') return geoMarkup.value.state
  if (adForm.geo_scope === 'all') return geoMarkup.value.state + geoMarkup.value.national
  return 0
})

// 슬롯 1개의 기본가
const basePricePerSlot = computed(() => {
  if (!selectedSlot.value) return 0
  return getBasePrice(selectedSlot.value.position, selectedSlot.value.tier)
})

// 현재 활성 광고 할인 이벤트
const adPromotion = ref(null)
const adDiscountPct = computed(() => adPromotion.value?.discount_pct || 0)

function fmtPromoEnd(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  return `${d.getFullYear()}.${d.getMonth()+1}.${d.getDate()} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`
}

function applyDiscount(price) {
  if (!adDiscountPct.value) return price
  return Math.round(price * (100 - adDiscountPct.value) / 100)
}

// 원가(할인 전) — 취소선 표시용
function slotOriginalPrice(position, tier) {
  return getBasePrice(position, tier) + geoExtra.value
}

// 총 최소 입찰가 = (기본가 + 지역 추가금) × 할인 적용
const totalMinBid = computed(() => applyDiscount(basePricePerSlot.value + geoExtra.value))
const totalMinBidOriginal = computed(() => basePricePerSlot.value + geoExtra.value)

// 슬롯 선택 UI에 표시할 가격 (할인 적용)
function slotMinPrice(position, tier) {
  return applyDiscount(getBasePrice(position, tier) + geoExtra.value)
}

const hasEnough = computed(() => (auth.user?.points || 0) >= (adForm.bid_amount || 0))
const canSubmit = computed(() => {
  if (!selectedSub.value) return false
  if (!adForm.title || !adImage.value || !imageConfirmed.value || !selectedSlot.value) return false
  if (adForm.bid_amount < totalMinBid.value) return false
  if (!hasEnough.value) return false
  // 지역별 페이지에서 카운티/주 선택 시 geo_value 필수
  if (!isNationalPage.value && adForm.geo_scope !== 'all' && !adForm.geo_value) return false
  return true
})

function isSelected(pos, slot) { return selectedSlot.value?.position === pos && selectedSlot.value?.slot === slot }

function selectSlot(position, slot, tier) {
  selectedSlot.value = { position, slot, tier }
  adForm.bid_amount = getBasePrice(position, tier) + geoExtra.value
  saveDraft()
}

// 페이지 변경 시 geo_scope 리셋 + 슬롯 선택 해제
watch(selectedSub, (val) => {
  if (!val) return
  if (isNationalPage.value) {
    adForm.geo_scope = 'all'
    adForm.geo_value = ''
  } else {
    adForm.geo_scope = 'county'
    adForm.geo_value = ''
  }
  // 슬롯 선택 해제 (새 페이지의 슬롯 설정이 다를 수 있음)
  selectedSlot.value = null
  zipResult.value = null
  saveDraft()
})

// 지역 변경 시 최소 입찰가로 리셋
watch(() => adForm.geo_scope, () => {
  if (selectedSlot.value) {
    adForm.bid_amount = totalMinBid.value
  }
  // 전국 선택 시 geo_value 초기화
  if (adForm.geo_scope === 'all') {
    adForm.geo_value = ''
  }
  saveDraft()
})

const fileInput = ref(null)
const imgWidth = ref(0)
const imgHeight = ref(0)
const imageConfirmed = ref(false)

const recommendedSize = computed(() => selectedSlot.value?.position === 'left' ? '200×140px' : '300×210px')
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
      // geo_scope에 따라 값 설정
      if (adForm.geo_scope === 'city') adForm.geo_value = p['place name']
      else if (adForm.geo_scope === 'county') adForm.geo_value = p['place name'] // county는 zip API에서 city로 대체
      else if (adForm.geo_scope === 'state') adForm.geo_value = p['state abbreviation']
      else adForm.geo_value = ''
      saveDraft()
    }
  } catch { showAlert('유효하지 않은 Zip Code', '오류') }
  zipLoading.value = false
}

function saveDraft() {
  try {
    localStorage.setItem(DRAFT_KEY, JSON.stringify({
      selectedSub: selectedSub.value,
      selectedSlot: selectedSlot.value,
      adForm: { ...adForm },
      zipInput: zipInput.value,
      ts: Date.now()
    }))
  } catch {}
}

function loadDraft() {
  try {
    const r = localStorage.getItem(DRAFT_KEY)
    if (!r) return
    const d = JSON.parse(r)
    if (Date.now() - d.ts > 86400000) { localStorage.removeItem(DRAFT_KEY); return }
    selectedSub.value = d.selectedSub || ''
    selectedSlot.value = d.selectedSlot || null
    if (d.adForm) Object.assign(adForm, d.adForm)
    zipInput.value = d.zipInput || ''
  } catch {}
}

function clearDraft() { try { localStorage.removeItem(DRAFT_KEY) } catch {} }
watch([selectedSub, selectedSlot], saveDraft, { deep: true })

function goPointShop() { saveDraft(); router.push('/dashboard?tab=points') }

async function submitAd() {
  if (!canSubmit.value || submitting.value) return
  if (!hasEnough.value) { const ok = await showConfirm(`포인트 부족 (보유: ${(auth.user?.points || 0).toLocaleString()}P)\n충전?`, '부족'); if (ok) goPointShop(); return }
  submitting.value = true
  const fd = new FormData()
  fd.append('title', adForm.title)
  fd.append('link_url', adForm.link_url || '')
  fd.append('page', selectedSub.value)
  fd.append('target_pages', JSON.stringify([selectedSub.value]))

  // geo 설정
  if (isNationalPage.value) {
    fd.append('geo_scope', 'all')
    fd.append('geo_value', '')
  } else {
    fd.append('geo_scope', adForm.geo_scope)
    fd.append('geo_value', adForm.geo_value)
  }

  fd.append('position', selectedSlot.value.position)
  fd.append('slot_number', selectedSlot.value.slot)
  fd.append('tier', selectedSlot.value.tier)
  fd.append('bid_amount', adForm.bid_amount)
  if (adImage.value) fd.append('image', adImage.value)
  try {
    const { data } = await axios.post('/api/banners/apply', fd)
    showAlert(data.message, '입찰 신청')
    selectedSlot.value = null
    Object.assign(adForm, { title: '', link_url: '', bid_amount: 4000, geo_scope: 'county', geo_value: '' })
    adImage.value = null; imagePreview.value = null; selectedSub.value = ''; clearDraft()
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
    // 활성 광고 할인 이벤트 별도 로드
    try {
      const promoRes = await axios.get('/api/pricing-promotions/active')
      adPromotion.value = promoRes.data?.data?.ad || null
    } catch {}
    if (data.data?.geo_markup) geoMarkup.value = { ...geoMarkup.value, ...data.data.geo_markup }
    // 페이지별 슬롯 설정: 서버는 top-level 에 페이지 키 (home, community...) 로 응답하므로
    // 특수 키 제외하고 left_slots/right_slots 구조를 가진 것만 수집
    const SPECIAL = new Set(['slot_min_prices','geo_markup'])
    const cfgs = {}
    Object.entries(data.data || {}).forEach(([k, v]) => {
      if (SPECIAL.has(k)) return
      if (v && typeof v === 'object' && ('left_slots' in v || 'right_slots' in v)) {
        cfgs[k] = v
      }
    })
    if (Object.keys(cfgs).length) pageConfigs.value = cfgs
  } catch {}
}

onMounted(() => { loadMyAds(); loadPrices(); loadDraft() })
</script>
<style scoped>
.slide-enter-active,.slide-leave-active{transition:all .3s ease}
.slide-enter-from,.slide-leave-to{opacity:0;transform:translateY(-10px)}
</style>
