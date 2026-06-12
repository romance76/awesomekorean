<template>
<div class="min-h-screen">
  <div class="max-w-2xl mx-auto px-4 py-5">
    <RouterLink to="/ad-apply" class="text-xs text-ink-muted hover:text-amber-600 transition-colors mb-2 inline-flex items-center gap-1"><AppIcon name="arrow-left" :size="13" />이미지 광고 신청으로</RouterLink>
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-1">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="edit" :size="20" /></span>
      텍스트 인라인 광고 신청
    </h1>
    <p class="text-sm text-ink-muted mb-4">상호 + 전화 + 한 줄 설명으로 리스트/상세 중간에 노출 · 이미지 불필요</p>

    <!-- 실시간 미리보기 -->
    <div class="mb-5">
      <div class="text-xs font-bold text-ink-light mb-1.5 flex items-center gap-1"><AppIcon name="search" :size="13" class="text-amber-600" />실시간 미리보기 (실제 노출 모양)</div>
      <TextInlineAd :manualAd="previewAd" :autoLoad="false" />
    </div>

    <!-- 입력 폼 -->
    <div class="card p-5 space-y-4">
      <!-- 상호 -->
      <div>
        <label class="input-label">상호 *</label>
        <input v-model="form.title" maxlength="30" class="input-soft"
          placeholder="예: 💈 강남미용실" />
        <div class="text-xs text-ink-faint mt-0.5">{{ form.title.length }}/30 · 이모지 앞에 붙이면 눈에 잘 띕니다</div>
      </div>

      <!-- 전화 -->
      <div>
        <label class="input-label">전화번호</label>
        <input v-model="form.phone" maxlength="20" class="input-soft"
          placeholder="예: 770-555-1234" />
      </div>

      <!-- 설명 -->
      <div>
        <label class="input-label">한 줄 설명 *</label>
        <input v-model="form.description" maxlength="120" class="input-soft"
          placeholder="예: 한인 전용 $30부터 · 첫방문 20% 할인 · Duluth GA" />
        <div class="text-xs text-ink-faint mt-0.5">{{ form.description.length }}/120</div>
      </div>

      <!-- 링크 -->
      <div>
        <label class="input-label">클릭 시 이동 링크 (선택)</label>
        <input v-model="form.link_url" type="url" class="input-soft"
          placeholder="https://example.com" />
      </div>

      <!-- 노출 페이지 -->
      <div>
        <label class="input-label mb-2">노출 페이지 *</label>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
          <button v-for="p in pages" :key="p.value" type="button"
            @click="form.page = p.value"
            :class="form.page === p.value ? 'bg-purple-100 border-purple-400 text-purple-800' : 'bg-white border-gray-200 text-ink-light hover:border-purple-200'"
            class="border-2 rounded-lg px-3 py-2 text-xs font-bold transition-colors inline-flex items-center justify-center gap-1">
            <AppIcon :name="p.icon" :size="13" /> {{ p.label }}
          </button>
        </div>
      </div>

      <!-- 기간 -->
      <div>
        <label class="input-label mb-2">노출 기간 *</label>
        <div class="grid grid-cols-3 gap-2">
          <button v-for="p in periods" :key="p.days" type="button"
            @click="form.period = p.days"
            :class="form.period === p.days ? 'bg-purple-100 border-purple-400 text-purple-800' : 'bg-white border-gray-200 text-ink-light hover:border-purple-200'"
            class="border-2 rounded-lg p-3 text-center transition-colors">
            <div class="text-sm font-black">{{ p.label }}</div>
            <div class="text-xs font-bold text-purple-600 mt-1">{{ p.price.toLocaleString() }}P</div>
          </button>
        </div>
      </div>

      <!-- 결제 요약 -->
      <div class="bg-purple-50 border border-purple-200 rounded-xl p-3 flex items-center justify-between">
        <div>
          <div class="text-xs text-purple-700">총 결제 금액</div>
          <div class="text-xs text-ink-muted">입찰가 (높을수록 노출 확률 ↑) 기본 {{ selectedPrice.toLocaleString() }}P</div>
        </div>
        <div class="text-right">
          <input v-model.number="form.bid_amount" type="number" :min="selectedPrice" step="100"
            class="w-32 border-2 border-purple-300 rounded-xl px-2 py-1 text-right text-lg font-black text-purple-800 outline-none focus:ring-4 focus:ring-purple-400/10" />
          <div class="text-xs text-ink-faint">P (최소 {{ selectedPrice.toLocaleString() }})</div>
        </div>
      </div>

      <!-- 제출 -->
      <button @click="submit" :disabled="!canSubmit || submitting"
        class="w-full bg-gradient-to-r from-[#FF8A53] to-[#F2570F] text-white font-bold py-3 rounded-xl disabled:opacity-50 hover:shadow-lg transition">
        {{ submitting ? '신청 중...' : `광고 신청 (${(form.bid_amount || 0).toLocaleString()}P)` }}
      </button>

      <p v-if="!auth.isLoggedIn" class="text-xs text-center text-red-500">로그인 후 신청 가능합니다</p>
      <p v-else-if="(auth.user?.points || 0) < form.bid_amount" class="text-xs text-center text-red-500">
        포인트 부족 · 보유: {{ (auth.user?.points || 0).toLocaleString() }}P
      </p>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import TextInlineAd from '../../components/TextInlineAd.vue'
import AppIcon from '../../components/AppIcon.vue'
import axios from 'axios'

const router = useRouter()
const auth = useAuthStore()
const site = useSiteStore()
const submitting = ref(false)

const form = reactive({
  title: '',
  phone: '',
  description: '',
  link_url: '',
  page: 'community',
  period: 30,
  bid_amount: 1000,
})

const pages = [
  { value: 'all',         icon: 'globe',          label: '전체' },
  { value: 'home',        icon: 'home',           label: '홈' },
  { value: 'community',   icon: 'message-circle', label: '커뮤니티' },
  { value: 'market',      icon: 'shopping-cart',  label: '장터' },
  { value: 'jobs',        icon: 'briefcase',      label: '구인구직' },
  { value: 'realestate',  icon: 'building',       label: '부동산' },
  { value: 'directory',   icon: 'store',          label: '업소록' },
  { value: 'clubs',       icon: 'users',          label: '동호회' },
  { value: 'events',      icon: 'calendar',       label: '이벤트' },
]

const periods = [
  { days: 7,  label: '7일',  price: 500 },
  { days: 30, label: '30일', price: 1000 },
  { days: 90, label: '90일', price: 2500 },
]

const selectedPrice = computed(() => periods.find(p => p.days === form.period)?.price || 1000)

// 페이지/기간 선택 시 기본 입찰가 자동 세팅
import { watch } from 'vue'
watch(() => form.period, (v) => {
  const base = periods.find(p => p.days === v)?.price || 1000
  if (form.bid_amount < base) form.bid_amount = base
})

const previewAd = computed(() => ({
  id: 0,
  title: form.title || '📝 상호를 입력하세요',
  phone: form.phone,
  description: form.description || '설명을 입력하면 여기에 보입니다',
}))

const canSubmit = computed(() =>
  auth.isLoggedIn
  && form.title.trim()
  && form.description.trim()
  && form.page
  && form.bid_amount >= selectedPrice.value
  && (auth.user?.points || 0) >= form.bid_amount
)

async function submit() {
  if (!canSubmit.value || submitting.value) return
  submitting.value = true
  try {
    const { data } = await axios.post('/api/banners/text-apply', {
      title: form.title,
      phone: form.phone || null,
      description: form.description,
      link_url: form.link_url || null,
      page: form.page,
      days: form.period,
      bid_amount: form.bid_amount,
    })
    site.toast?.(data.message || '광고 신청 완료! 검토 후 활성화됩니다.', 'success')
    router.push('/dashboard')
  } catch (e) {
    site.toast?.(e.response?.data?.message || '신청 실패', 'error')
  }
  submitting.value = false
}
</script>
