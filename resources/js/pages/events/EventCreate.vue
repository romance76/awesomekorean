<template>
<div class="min-h-screen">
  <div class="page-main px-4 py-5">
    <PageHeader :title="isEdit ? (isSweepstakes ? '경품 추첨 이벤트 수정' : '이벤트 수정') : '이벤트 등록'" icon="calendar" fallback="/events" />
    <div class="card p-5 space-y-4">
      <!-- 사이트 최고관리자 전용: 경품 추첨 이벤트로 등록 -->
      <div v-if="isSuperAdmin && !isEdit" class="bg-amber-50 border border-amber-200 rounded-xl p-3">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" v-model="isSweepstakes" class="w-4 h-4 accent-amber-500" />
          <span class="text-sm font-bold text-amber-800 flex items-center gap-1"><AppIcon name="gift" :size="14" />경품 추첨(Sweepstakes) 이벤트로 등록</span>
        </label>
        <p class="text-[11px] text-amber-700 mt-1">체크하면 이 이벤트가 "이벤트" 목록에 노출되고, 사용자는 보유 Entry로 응모할 수 있습니다.</p>
      </div>
      <div v-else-if="isEdit && isSweepstakes" class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-sm font-bold text-amber-800 flex items-center gap-1.5">
        <AppIcon name="gift" :size="14" />경품 추첨 이벤트
      </div>

      <template v-if="isSweepstakes">
        <!-- 1. 기본 정보 -->
        <section class="sw-sec">
          <h3 class="sw-h">1. 기본 정보</h3>
          <div>
            <label class="input-label">제목 *</label>
            <input v-model="form.title" type="text" placeholder="예: 추석 맞이 경품 추첨" class="input-soft px-3" :class="errClass('title')" />
            <p v-if="fieldErrors.title" class="text-xs text-red-500 mt-1">{{ fieldErrors.title }}</p>
          </div>
          <div>
            <label class="input-label">설명</label>
            <textarea v-model="form.content" rows="5" placeholder="이벤트 소개, 참여 방법 등을 적어주세요" class="input-soft px-3"></textarea>
          </div>
          <div class="inline-flex items-center gap-2 text-xs bg-amber-50 text-amber-800 rounded-lg px-3 py-1.5 font-bold">
            카테고리: 경품 추첨 · 주최: 어썸코리안
          </div>
        </section>

        <!-- 2. 상단 배너 -->
        <section class="sw-sec">
          <h3 class="sw-h">2. 이벤트 상단 배너 이미지</h3>
          <ImageUploadBox mode="pick" aspect="8 / 3" :recommend-ratio="8/3" :max-m-b="5" :soft-m-b="1.5"
            :preview="previewImg" :removable="false" :error="fieldErrors.image" @pick="onBannerPick" />
          <p class="text-[11px] text-ink-faint">권장 크기 <b>1600 × 600 px</b> (비율 8:3), JPG/PNG/WebP, 1.5MB 이하 — 이벤트 맨 위에 크게 표시되는 배너예요. 글자는 가운데에 두면 잘리지 않아요.</p>
        </section>

        <!-- 3. 일정 -->
        <section class="sw-sec">
          <h3 class="sw-h">3. 응모 기간</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="input-label">시작일시 *</label>
              <input v-model="form.start_date" type="datetime-local" class="input-soft px-3" :class="errClass('start_date')" />
              <p v-if="fieldErrors.start_date" class="text-xs text-red-500 mt-1">{{ fieldErrors.start_date }}</p>
            </div>
            <div>
              <label class="input-label">종료일시</label>
              <input v-model="form.end_date" type="datetime-local" class="input-soft px-3" :class="errClass('end_date')" />
              <p v-if="fieldErrors.end_date" class="text-xs text-red-500 mt-1">{{ fieldErrors.end_date }}</p>
            </div>
          </div>
        </section>

        <!-- 4. 진행 방식 -->
        <section class="sw-sec">
          <h3 class="sw-h">4. 진행 방식</h3>
          <div class="grid grid-cols-2 gap-2">
            <button type="button" class="sw-seg" :class="isOnline ? 'on' : ''" @click="isOnline = true">💻 온라인 추첨</button>
            <button type="button" class="sw-seg" :class="!isOnline ? 'on' : ''" @click="isOnline = false">📍 현장 이벤트</button>
          </div>
          <p class="text-[11px] text-ink-faint">{{ isOnline ? '온라인 = 사이트에서 추첨해요. 장소 입력이 필요 없어요.' : '현장 = 가라지세일·오프라인 행사처럼 장소가 있는 이벤트예요.' }}</p>
          <div v-if="!isOnline" class="grid grid-cols-2 gap-3">
            <div>
              <label class="input-label">장소명 *</label>
              <input v-model="form.venue" type="text" placeholder="예: 둘루스 한인회관" class="input-soft px-3" :class="errClass('venue')" />
              <p v-if="fieldErrors.venue" class="text-xs text-red-500 mt-1">{{ fieldErrors.venue }}</p>
            </div>
            <div>
              <label class="input-label">주소 *</label>
              <input v-model="form.address" type="text" placeholder="상세 주소" class="input-soft px-3" :class="errClass('address')" />
              <p v-if="fieldErrors.address" class="text-xs text-red-500 mt-1">{{ fieldErrors.address }}</p>
            </div>
            <div>
              <label class="input-label">도시</label>
              <input v-model="form.city" type="text" placeholder="Atlanta" class="input-soft px-3" />
            </div>
            <div>
              <label class="input-label">주</label>
              <input v-model="form.state" type="text" placeholder="GA" class="input-soft px-3" />
            </div>
          </div>
        </section>

        <!-- 5. 당첨 인원 · 상품 -->
        <section class="sw-sec">
          <h3 class="sw-h">5. 당첨 인원 · 상품</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <button type="button" class="sw-seg" :class="sw.prize_mode === 'same' ? 'on' : ''" :disabled="swLocked" @click="setMode('same')">🎁 같은 상품 N명</button>
            <button type="button" class="sw-seg" :class="sw.prize_mode === 'tiered' ? 'on' : ''" :disabled="swLocked" @click="setMode('tiered')">🏆 등수별 차등 상품 (1~10등)</button>
          </div>

          <template v-if="sw.prize_mode === 'same'">
            <div class="flex items-center gap-3">
              <label class="input-label !mb-0">당첨 인원</label>
              <div class="flex items-center gap-2">
                <button type="button" class="sw-step" :disabled="swLocked || sw.winner_count <= 1" @click="sw.winner_count--">−</button>
                <span class="w-10 text-center font-black text-lg">{{ sw.winner_count }}</span>
                <button type="button" class="sw-step" :disabled="swLocked || sw.winner_count >= 10" @click="sw.winner_count++">+</button>
                <span class="text-sm text-ink-muted">명 (1~10)</span>
              </div>
            </div>
            <p v-if="fieldErrors.winner_count" class="text-xs text-red-500">{{ fieldErrors.winner_count }}</p>
            <div class="flex gap-3">
              <div class="w-28 flex-shrink-0">
                <label class="input-label">상품 이미지</label>
                <ImageUploadBox mode="upload" kind="prize" aspect="1 / 1" :recommend-ratio="1" :max-m-b="5" :soft-m-b="1" :disabled="swLocked"
                  :preview="sw.prize_image_url" @uploaded="u => sw.prize_image_url = u" @remove="sw.prize_image_url = ''" />
              </div>
              <div class="flex-1 min-w-0 space-y-3">
                <div>
                  <label class="input-label">상품명 *</label>
                  <input v-model="sw.prize_name" type="text" maxlength="100" :disabled="swLocked" placeholder="예: $100 기프트카드" class="input-soft px-3 disabled:opacity-60" :class="errClass('prize_name')" />
                  <p v-if="fieldErrors.prize_name" class="text-xs text-red-500 mt-1">{{ fieldErrors.prize_name }}</p>
                </div>
                <div>
                  <label class="input-label">상품 가치 ($)</label>
                  <input v-model.number="sw.prize_value" type="number" min="0" step="0.01" :disabled="swLocked" class="input-soft px-3 disabled:opacity-60" :class="errClass('prize_value')" />
                  <p v-if="fieldErrors.prize_value" class="text-xs text-red-500 mt-1">{{ fieldErrors.prize_value }}</p>
                </div>
              </div>
            </div>
            <p class="text-[11px] text-ink-faint">상품 이미지는 800 × 800 px 정사각 권장, 1MB 이하.</p>
          </template>

          <template v-else>
            <div class="flex items-center gap-3">
              <label class="input-label !mb-0">등수 개수</label>
              <div class="flex items-center gap-2">
                <button type="button" class="sw-step" :disabled="swLocked || sw.winner_count <= 2" @click="sw.winner_count--">−</button>
                <span class="w-10 text-center font-black text-lg">{{ sw.winner_count }}</span>
                <button type="button" class="sw-step" :disabled="swLocked || sw.winner_count >= 10" @click="sw.winner_count++">+</button>
                <span class="text-sm text-ink-muted">등까지 (2~10)</span>
              </div>
            </div>
            <PrizeTierEditor v-model:tiers="sw.prize_tiers" :disabled="swLocked" :show-errors="tierErrors" />
            <p v-if="fieldErrors.prize_tiers" class="text-xs text-red-500">{{ fieldErrors.prize_tiers }}</p>
          </template>

          <div class="bg-amber-50 rounded-xl px-3 py-2 text-sm font-bold text-amber-800">
            총 당첨 {{ winnerCountNum }}명 · 상품 총액 ${{ totalValue.toLocaleString() }}
          </div>
          <p class="text-[11px] text-ink-faint">한 사람은 한 번만 당첨돼요. 낮은 등수부터 10~15초 간격으로 추첨돼요.</p>
          <p v-if="swLocked" class="text-xs text-amber-700">추첨이 시작되었거나 참가자가 있어 당첨 인원·상품은 변경할 수 없어요.</p>
        </section>

        <!-- 6. 추첨 화면 -->
        <section class="sw-sec">
          <h3 class="sw-h">6. 추첨 화면</h3>
          <div class="text-sm font-bold text-ink">🎱 3D 추첨기</div>
          <p class="text-[11px] text-ink-faint">색상·배경·로고는 관리자 &gt; 응모권 추첨에서 꾸밀 수 있어요</p>
        </section>

        <!-- 7. 참가 조건 · 공식 규정 -->
        <section class="sw-sec">
          <h3 class="sw-h">7. 참가 조건 · 공식 규정</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="input-label">최소 연령</label>
              <input v-model.number="sw.minimum_age" type="number" min="0" class="input-soft px-3" :class="errClass('minimum_age')" />
              <p v-if="fieldErrors.minimum_age" class="text-xs text-red-500 mt-1">{{ fieldErrors.minimum_age }}</p>
            </div>
            <div>
              <label class="input-label">참가 가능 지역 (State, 쉼표구분, 비우면 전체)</label>
              <input v-model="swRegionsInput" placeholder="예: GA,FL,NC" class="input-soft px-3" :class="errClass('eligible_regions')" />
              <p v-if="fieldErrors.eligible_regions" class="text-xs text-red-500 mt-1">{{ fieldErrors.eligible_regions }}</p>
            </div>
          </div>
          <div>
            <label class="input-label">무구매 조건 안내문</label>
            <textarea v-model="sw.no_purchase_required_text" rows="2" class="input-soft px-3" :class="errClass('no_purchase_required_text')"></textarea>
            <p v-if="fieldErrors.no_purchase_required_text" class="text-xs text-red-500 mt-1">{{ fieldErrors.no_purchase_required_text }}</p>
          </div>
          <div class="text-sm">
            <a href="/sweepstakes/rules" target="_blank" rel="noopener" class="text-blue-600 font-bold hover:underline">공식 규정 보기</a>
            <p class="text-[11px] text-ink-faint mt-0.5">규정 내용은 관리자 &gt; 경품 추첨 공식 규정에서 수정해요.</p>
            <p v-if="legacyRulesUrl" class="text-[11px] text-ink-faint mt-0.5 break-all">기존 등록된 규정 주소: {{ legacyRulesUrl }}</p>
          </div>
        </section>
      </template>

      <template v-else>
      <div>
        <label class="input-label">제목 *</label>
        <input v-model="form.title" type="text" placeholder="예: 한인 문화 축제" class="input-soft px-3" :class="errClass('title')" />
        <p v-if="fieldErrors.title" class="text-xs text-red-500 mt-1">{{ fieldErrors.title }}</p>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="input-label">카테고리</label>
          <select v-model="form.category" class="input-soft px-3">
            <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
          </select>
        </div>
        <div>
          <label class="input-label">주최</label>
          <input v-model="form.organizer" type="text" placeholder="주최 단체/개인" class="input-soft px-3" />
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="input-label">시작일시 *</label>
          <input v-model="form.start_date" type="datetime-local" class="input-soft px-3" :class="errClass('start_date')" />
          <p v-if="fieldErrors.start_date" class="text-xs text-red-500 mt-1">{{ fieldErrors.start_date }}</p>
        </div>
        <div>
          <label class="input-label">종료일시</label>
          <input v-model="form.end_date" type="datetime-local" class="input-soft px-3" :class="errClass('end_date')" />
          <p v-if="fieldErrors.end_date" class="text-xs text-red-500 mt-1">{{ fieldErrors.end_date }}</p>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="input-label">장소명</label>
          <input v-model="form.venue" type="text" placeholder="장소명" class="input-soft px-3" />
        </div>
        <div>
          <label class="input-label">주소</label>
          <input v-model="form.address" type="text" placeholder="상세 주소" class="input-soft px-3" />
        </div>
      </div>

      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="input-label">도시</label>
          <input v-model="form.city" type="text" placeholder="Atlanta" class="input-soft px-3" />
        </div>
        <div>
          <label class="input-label">주</label>
          <input v-model="form.state" type="text" placeholder="GA" class="input-soft px-3" />
        </div>
        <div>
          <label class="input-label">가격 ($)</label>
          <input v-model.number="form.price" type="number" min="0" placeholder="0 = 무료" class="input-soft px-3" :class="errClass('price')" />
          <p v-if="fieldErrors.price" class="text-xs text-red-500 mt-1">{{ fieldErrors.price }}</p>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="input-label">최대 참가자 (0=무제한)</label>
          <input v-model.number="form.max_attendees" type="number" min="0" class="input-soft px-3" :class="errClass('max_attendees')" />
          <p v-if="fieldErrors.max_attendees" class="text-xs text-red-500 mt-1">{{ fieldErrors.max_attendees }}</p>
        </div>
        <div>
          <label class="input-label">관련 URL</label>
          <input v-model="form.url" type="url" placeholder="https://..." class="input-soft px-3" :class="errClass('url')" />
          <p v-if="fieldErrors.url" class="text-xs text-red-500 mt-1">{{ fieldErrors.url }}</p>
        </div>
      </div>

      <div v-if="!isSweepstakes">
        <label class="input-label">완료 인증 보상 포인트 (선택)</label>
        <input v-model.number="form.reward_points" type="number" min="0" placeholder="0 = 보상 없음" class="input-soft px-3" :class="errClass('reward_points')" />
        <p v-if="fieldErrors.reward_points" class="text-xs text-red-500 mt-1">{{ fieldErrors.reward_points }}</p>
        <p class="text-[11px] text-ink-faint mt-1">0보다 크면 참가자가 완료 인증 파일을 제출할 수 있고, 관리자 확인 후 이 포인트가 지급됩니다.</p>
      </div>

      <!-- 이미지 -->
      <div>
        <label class="input-label">이벤트 이미지</label>
        <div class="mt-1 flex items-center gap-3">
          <div v-if="!previewImg" class="w-24 h-24 rounded-xl flex flex-col items-center justify-center flex-shrink-0" :class="fieldErrors.image ? 'bg-red-50 text-red-400 ring-1 ring-red-300' : 'bg-[#F4F6F8] text-ink-muted'">
            <AppIcon name="camera" :size="24" :stroke-width="1.5" />
          </div>
          <img v-if="previewImg" :src="previewImg" class="w-24 h-24 rounded-xl object-cover border border-gray-100" />
          <label class="btn-soft text-xs cursor-pointer">
            <AppIcon name="camera" :size="14" /> {{ previewImg ? '변경' : '업로드' }}
            <input type="file" accept="image/*" @change="onImage" class="hidden" />
          </label>
        </div>
        <p v-if="fieldErrors.image" class="text-xs text-red-500 mt-1">{{ fieldErrors.image }}</p>
      </div>

      <div>
        <label class="input-label">상세 내용</label>
        <textarea v-model="form.content" rows="8" placeholder="이벤트 상세 내용을 작성해주세요" class="input-soft px-3"></textarea>
      </div>
      </template>

      <div v-if="error" class="text-red-500 text-sm bg-red-50 rounded-xl px-3 py-2">
        {{ error }}
        <span v-if="Object.keys(fieldErrors).length">— 빨간색으로 표시된 입력칸을 확인해주세요.</span>
      </div>

      <div class="flex gap-3 pt-2">
        <button @click="submit" :disabled="submitting"
          class="btn-primary px-6">
          {{ submitting ? '저장 중...' : (isSweepstakes ? (isEdit ? '경품 추첨 이벤트 수정' : '경품 추첨 이벤트 등록') : (isEdit ? '수정 완료' : '이벤트 등록')) }}
        </button>
        <button @click="$router.back()" class="btn-secondary px-6">취소</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import PageHeader from '../../components/PageHeader.vue'
import ImageUploadBox from '../../components/events/ImageUploadBox.vue'
import PrizeTierEditor from '../../components/events/PrizeTierEditor.vue'
import { useAuthStore } from '../../stores/auth'

const DEFAULT_NO_PURCHASE = '이 이벤트는 구매가 필요 없습니다. Entry는 구매할 수 없으며 무료 활동(가입, 출석 등)으로만 얻을 수 있어요.'
const RULES_PATH = '/sweepstakes/rules'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const editId = computed(() => route.params.id)
const isEdit = computed(() => !!editId.value)
const isSuperAdmin = computed(() => auth.user?.role === 'super_admin')
const isSweepstakes = ref(false)
const isOnline = ref(true)
const sw = reactive({
  prize_mode: 'same', prize_name: '', prize_value: '', prize_image_url: '',
  minimum_age: 18, official_rules_url: '', no_purchase_required_text: DEFAULT_NO_PURCHASE,
  winner_count: 1, prize_tiers: [],
})
const swLocked = ref(false)   // 수정 불가(추첨 시작 후 참가자 있음 / 당첨자 확정)
const tierErrors = ref(false)
const swRegionsInput = ref('')
const winnerCountNum = computed(() => Math.min(10, Math.max(1, Math.floor(Number(sw.winner_count)) || 1)))
const legacyRulesUrl = computed(() => (sw.official_rules_url && sw.official_rules_url !== RULES_PATH) ? sw.official_rules_url : '')

function syncTiers() {
  const n = winnerCountNum.value
  const old = sw.prize_tiers
  sw.prize_tiers = Array.from({ length: n }, (_, i) => old[i] ? { ...old[i], rank: i + 1 } : { rank: i + 1, prize_name: '', prize_value: '', prize_image: '' })
}
watch(() => sw.winner_count, () => {
  if (sw.winner_count > 10) sw.winner_count = 10
  if (sw.winner_count < 1) sw.winner_count = 1
  if (sw.prize_mode === 'tiered') syncTiers()
})
function setMode(m) {
  if (swLocked.value || sw.prize_mode === m) return
  sw.prize_mode = m
  if (m === 'tiered') { if (sw.winner_count < 2) sw.winner_count = 2; syncTiers() }
}
const totalValue = computed(() => {
  if (sw.prize_mode === 'tiered') return sw.prize_tiers.reduce((a, t) => a + (Number(t.prize_value) || 0), 0)
  return (Number(sw.prize_value) || 0) * winnerCountNum.value
})

const categories = [
  { value: 'culture', label: '🎭 문화' },
  { value: 'networking', label: '🤝 네트워킹' },
  { value: 'education', label: '📚 교육/세미나' },
  { value: 'community', label: '🏘️ 커뮤니티' },
  { value: 'sports', label: '⚽ 스포츠' },
  { value: 'food', label: '🍽️ 음식/맛집' },
  { value: 'music', label: '🎵 음악/공연' },
  { value: 'religion', label: '⛪ 종교' },
  { value: 'business', label: '💼 비즈니스' },
  { value: 'other', label: '📋 기타' },
]

const form = reactive({
  title: '', category: 'culture', organizer: '', start_date: '', end_date: '',
  venue: '', address: '', city: '', state: '', price: 0, content: '',
  max_attendees: 0, url: '', reward_points: 0,
})
const imageFile = ref(null)
const previewImg = ref(null)
const error = ref('')
const submitting = ref(false)
const fieldErrors = reactive({})

function errClass(field) {
  return fieldErrors[field] ? '!border-red-400 !ring-1 !ring-red-300' : ''
}

function onImage(e) {
  const file = e.target.files[0]
  if (!file) return
  imageFile.value = file
  previewImg.value = URL.createObjectURL(file)
}
function onBannerPick(file) {
  imageFile.value = file
  previewImg.value = URL.createObjectURL(file)
  delete fieldErrors.image
}

function validateSweepstakes() {
  if (!form.title || !form.start_date) return '제목과 시작일시를 입력해주세요'
  if (!isOnline.value && (!form.venue.trim() || !form.address.trim())) return '현장 이벤트는 장소명과 주소를 입력해주세요'
  if (sw.prize_mode === 'same') {
    if (!sw.prize_name.trim()) return '상품명을 입력해주세요'
  } else {
    tierErrors.value = true
    const miss = sw.prize_tiers.find(t => !(t.prize_name || '').trim())
    if (miss) return `${miss.rank}등 상품명을 입력해주세요`
  }
  return ''
}

async function submit() {
  Object.keys(fieldErrors).forEach(k => delete fieldErrors[k])
  tierErrors.value = false
  if (isSweepstakes.value) {
    const msg = validateSweepstakes()
    if (msg) { error.value = msg; return }
  } else if (!form.title || !form.start_date) { error.value = '제목과 시작일을 입력해주세요'; return }
  submitting.value = true; error.value = ''

  const fd = new FormData()
  if (isSweepstakes.value) {
    fd.set('event_type', 'sweepstakes')
    fd.set('title', form.title)
    if (form.content) { fd.set('content', form.content); fd.set('description', form.content) }
    fd.set('start_date', form.start_date)
    if (form.end_date) fd.set('end_date', form.end_date)
    fd.set('is_online', isOnline.value ? '1' : '0')
    if (!isOnline.value) {
      fd.set('venue', form.venue); fd.set('address', form.address)
      if (form.city) fd.set('city', form.city)
      if (form.state) fd.set('state', form.state)
    }
    fd.set('winner_count', winnerCountNum.value)
    fd.set('prize_mode', sw.prize_mode)
    if (sw.prize_mode === 'same') {
      fd.set('prize_name', sw.prize_name.trim())
      if (sw.prize_value !== '' && sw.prize_value !== null) fd.set('prize_value', sw.prize_value)
      if (sw.prize_image_url) fd.set('prize_image_url', sw.prize_image_url)
    } else {
      const tiers = sw.prize_tiers.map(t => ({
        rank: t.rank, prize_name: (t.prize_name || '').trim(),
        prize_value: t.prize_value === '' || t.prize_value == null ? null : Number(t.prize_value),
        prize_image: t.prize_image || null,
      }))
      fd.set('prize_tiers', JSON.stringify(tiers))
      fd.set('prize_name', tiers[0].prize_name)
      if (tiers[0].prize_value != null) fd.set('prize_value', tiers[0].prize_value)
      if (tiers[0].prize_image) fd.set('prize_image_url', tiers[0].prize_image)
    }
    fd.set('draw_style', 'lottery3d')
    fd.set('minimum_age', sw.minimum_age || 18)
    const regions = swRegionsInput.value ? swRegionsInput.value.split(',').map(s => s.trim().toUpperCase()).filter(Boolean) : []
    regions.forEach(r => fd.append('eligible_regions[]', r))
    fd.set('official_rules_url', legacyRulesUrl.value || RULES_PATH)
    fd.set('no_purchase_required_text', sw.no_purchase_required_text || DEFAULT_NO_PURCHASE)
    if (imageFile.value) fd.append('image', imageFile.value)
  } else {
    Object.entries(form).forEach(([k, v]) => {
      if (k === 'max_attendees' && !v) return // 0/빈값 = 무제한, 보내지 않음
      if (v !== '' && v !== null) fd.append(k, v)
    })
    if (form.price == 0) fd.set('is_free', '1')
    if (imageFile.value) fd.append('image', imageFile.value)
  }

  try {
    if (isEdit.value) {
      fd.append('_method', 'PUT')
      await axios.post(`/api/events/${editId.value}`, fd)
      router.push(`/events?open=${editId.value}`)
    } else {
      const { data } = await axios.post('/api/events', fd)
      router.push(`/events?open=${data.data.id}`)
    }
  } catch (e) {
    error.value = e.response?.data?.message || '저장 실패'
    const errors = e.response?.data?.errors
    if (errors) {
      for (const [field, msgs] of Object.entries(errors)) {
        fieldErrors[field] = Array.isArray(msgs) ? msgs[0] : msgs
      }
      await nextTick()
      document.querySelector('.border-red-400, .ring-red-300')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
  }
  submitting.value = false
}

onMounted(async () => {
  if (isEdit.value) {
    try {
      const { data } = await axios.get(`/api/events/${editId.value}`)
      const e = data.data
      Object.assign(form, {
        title: e.title || '', category: e.category || 'culture', organizer: e.organizer || '',
        start_date: e.start_date ? e.start_date.slice(0, 16) : '',
        end_date: e.end_date ? e.end_date.slice(0, 16) : '',
        venue: e.venue || '', address: e.address || '', city: e.city || '', state: e.state || '',
        price: e.price || 0, content: e.content || '', max_attendees: e.max_attendees || 0, url: e.url || '',
        reward_points: e.reward_points || 0,
      })
      if (e.image_url) previewImg.value = e.image_url
      if (e.event_type === 'sweepstakes' && e.sweepstakes) {
        const s = e.sweepstakes
        isSweepstakes.value = true
        isOnline.value = e.is_online === undefined || e.is_online === null ? !(e.venue || e.address) : !!Number(e.is_online)
        let tiers = s.prize_tiers
        if (typeof tiers === 'string') { try { tiers = JSON.parse(tiers) } catch { tiers = [] } }
        tiers = Array.isArray(tiers) ? tiers : []
        const n = Math.min(10, Math.max(1, Number(s.winner_count) || 1))
        const mode = s.prize_mode === 'tiered' || (!s.prize_mode && n > 1 && tiers.length) ? 'tiered' : 'same'
        Object.assign(sw, {
          prize_mode: mode,
          prize_name: s.prize_name || '',
          prize_value: s.prize_value || '',
          prize_image_url: s.prize_image || '',
          minimum_age: s.minimum_age || 18,
          official_rules_url: s.official_rules_url || '',
          no_purchase_required_text: s.no_purchase_required_text || DEFAULT_NO_PURCHASE,
          winner_count: mode === 'tiered' ? Math.max(2, n) : n,
        })
        if (mode === 'tiered') {
          sw.prize_tiers = Array.from({ length: sw.winner_count }, (_, i) => {
            const hit = tiers.find(t => Number(t?.rank) === i + 1) || {}
            return { rank: i + 1, prize_name: hit.prize_name || '', prize_value: hit.prize_value ?? '', prize_image: hit.prize_image || '' }
          })
        }
        swLocked.value = !!s.edit_locked || s.status === 'winner_selected'
        swRegionsInput.value = (s.eligible_regions || []).join(',')
      }
    } catch {}
  }
})
</script>

<style scoped>
.sw-sec { border: 1px solid #f1f1f1; border-radius: 16px; padding: 16px; display: flex; flex-direction: column; gap: 12px; background: #fff; }
.sw-h { font-size: 14px; font-weight: 800; color: #3a2a12; }
.sw-seg { border: 2px solid #f0f0f0; border-radius: 12px; padding: 10px 12px; font-size: 14px; font-weight: 700; color: #444; background: #fff; transition: all .15s; }
.sw-seg.on { border-color: #FC226B; background: #FFF0F5; color: #B80F49; }
.sw-seg:disabled { opacity: .6; cursor: not-allowed; }
.sw-step { width: 36px; height: 36px; border-radius: 10px; border: 1px solid #e5e5e5; background: #fff; font-size: 18px; font-weight: 800; line-height: 1; }
.sw-step:disabled { opacity: .35; }
</style>
