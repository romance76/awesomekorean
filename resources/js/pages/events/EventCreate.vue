<template>
<div class="min-h-screen">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="calendar" :size="20" /></span>
      {{ isEdit ? (isSweepstakes ? '경품 추첨 이벤트 수정' : '이벤트 수정') : '이벤트 등록' }}
    </h1>
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

      <div>
        <label class="input-label">제목 *</label>
        <input v-model="form.title" type="text" placeholder="예: 한인 문화 축제" class="input-soft px-3" />
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
          <input v-model="form.start_date" type="datetime-local" class="input-soft px-3" />
        </div>
        <div>
          <label class="input-label">종료일시</label>
          <input v-model="form.end_date" type="datetime-local" class="input-soft px-3" />
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
          <input v-model.number="form.price" type="number" min="0" placeholder="0 = 무료" class="input-soft px-3" />
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="input-label">최대 참가자 (0=무제한)</label>
          <input v-model.number="form.max_attendees" type="number" min="0" class="input-soft px-3" />
        </div>
        <div>
          <label class="input-label">관련 URL</label>
          <input v-model="form.url" type="url" placeholder="https://..." class="input-soft px-3" />
        </div>
      </div>

      <div v-if="!isSweepstakes">
        <label class="input-label">완료 인증 보상 포인트 (선택)</label>
        <input v-model.number="form.reward_points" type="number" min="0" placeholder="0 = 보상 없음" class="input-soft px-3" />
        <p class="text-[11px] text-ink-faint mt-1">0보다 크면 참가자가 완료 인증 파일을 제출할 수 있고, 관리자 확인 후 이 포인트가 지급됩니다.</p>
      </div>

      <!-- 경품 추첨(Sweepstakes) 전용 필드 -->
      <div v-if="isSweepstakes" class="border-t border-gray-100 pt-4 space-y-3">
        <div class="text-xs font-bold text-ink-muted">경품 정보</div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="input-label">경품명 *</label>
            <input v-model="sw.prize_name" type="text" placeholder="예: $100 기프트카드" class="input-soft px-3" />
          </div>
          <div>
            <label class="input-label">경품 가치 ($)</label>
            <input v-model.number="sw.prize_value" type="number" min="0" step="0.01" class="input-soft px-3" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="input-label">최소 연령</label>
            <input v-model.number="sw.minimum_age" type="number" min="0" class="input-soft px-3" />
          </div>
          <div>
            <label class="input-label">참가 가능 지역 (State, 쉼표구분, 비우면 전체)</label>
            <input v-model="swRegionsInput" placeholder="예: GA,FL,NC" class="input-soft px-3" />
          </div>
        </div>
        <div>
          <label class="input-label">공식 규정 URL</label>
          <input v-model="sw.official_rules_url" type="url" placeholder="https://..." class="input-soft px-3" />
        </div>
        <div>
          <label class="input-label">무구매 조건 안내문</label>
          <textarea v-model="sw.no_purchase_required_text" rows="2" placeholder="예: 구매 없이도 참가할 수 있습니다..." class="input-soft px-3"></textarea>
        </div>
      </div>

      <!-- 이미지 -->
      <div>
        <label class="input-label">이벤트 이미지</label>
        <div class="mt-1 flex items-center gap-3">
          <div v-if="!previewImg" class="w-24 h-24 rounded-xl bg-[#F4F6F8] text-ink-muted flex flex-col items-center justify-center flex-shrink-0">
            <AppIcon name="camera" :size="24" :stroke-width="1.5" />
          </div>
          <img v-if="previewImg" :src="previewImg" class="w-24 h-24 rounded-xl object-cover border border-gray-100" />
          <label class="btn-soft text-xs cursor-pointer">
            <AppIcon name="camera" :size="14" /> {{ previewImg ? '변경' : '업로드' }}
            <input type="file" accept="image/*" @change="onImage" class="hidden" />
          </label>
        </div>
      </div>

      <div>
        <label class="input-label">상세 내용</label>
        <textarea v-model="form.content" rows="8" placeholder="이벤트 상세 내용을 작성해주세요" class="input-soft px-3"></textarea>
      </div>

      <div v-if="error" class="text-red-500 text-sm bg-red-50 rounded-xl px-3 py-2">{{ error }}</div>

      <div class="flex gap-3 pt-2">
        <button @click="submit" :disabled="submitting"
          class="btn-primary px-6">
          {{ submitting ? '저장 중...' : (isEdit ? '수정 완료' : '이벤트 등록') }}
        </button>
        <button @click="$router.back()" class="btn-secondary px-6">취소</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import { useAuthStore } from '../../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const editId = computed(() => route.params.id)
const isEdit = computed(() => !!editId.value)
const isSuperAdmin = computed(() => auth.user?.role === 'super_admin')
const isSweepstakes = ref(false)
const sw = reactive({ prize_name: '', prize_value: '', minimum_age: 18, official_rules_url: '', no_purchase_required_text: '' })
const swRegionsInput = ref('')

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

function onImage(e) {
  const file = e.target.files[0]
  if (!file) return
  imageFile.value = file
  previewImg.value = URL.createObjectURL(file)
}

async function submit() {
  if (!form.title || !form.start_date) { error.value = '제목과 시작일을 입력해주세요'; return }
  if (isSweepstakes.value && !sw.prize_name) { error.value = '경품명을 입력해주세요'; return }
  submitting.value = true; error.value = ''

  const fd = new FormData()
  Object.entries(form).forEach(([k, v]) => { if (v !== '' && v !== null) fd.append(k, v) })
  if (form.price == 0) fd.set('is_free', '1')
  if (imageFile.value) fd.append('image', imageFile.value)

  if (isSweepstakes.value) {
    fd.set('event_type', 'sweepstakes')
    fd.set('prize_name', sw.prize_name)
    if (sw.prize_value !== '' && sw.prize_value !== null) fd.set('prize_value', sw.prize_value)
    fd.set('minimum_age', sw.minimum_age || 18)
    if (sw.official_rules_url) fd.set('official_rules_url', sw.official_rules_url)
    if (sw.no_purchase_required_text) fd.set('no_purchase_required_text', sw.no_purchase_required_text)
    const regions = swRegionsInput.value ? swRegionsInput.value.split(',').map(s => s.trim().toUpperCase()).filter(Boolean) : []
    regions.forEach(r => fd.append('eligible_regions[]', r))
  }

  try {
    if (isEdit.value) {
      fd.append('_method', 'PUT')
      const { data } = await axios.post(`/api/events/${editId.value}`, fd)
      router.push(`/events/${editId.value}`)
    } else {
      const { data } = await axios.post('/api/events', fd)
      router.push(`/events/${data.data.id}`)
    }
  } catch (e) {
    error.value = e.response?.data?.message || '저장 실패'
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
        isSweepstakes.value = true
        Object.assign(sw, {
          prize_name: e.sweepstakes.prize_name || '',
          prize_value: e.sweepstakes.prize_value || '',
          minimum_age: e.sweepstakes.minimum_age || 18,
          official_rules_url: e.sweepstakes.official_rules_url || '',
          no_purchase_required_text: e.sweepstakes.no_purchase_required_text || '',
        })
        swRegionsInput.value = (e.sweepstakes.eligible_regions || []).join(',')
      }
    } catch {}
  }
})
</script>
