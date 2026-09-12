<template>
<div>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-2">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="coins" :size="20" /></span>
    포인트 설정
  </h1>
  <p class="text-sm text-ink-muted mb-6">모든 포인트 수치를 여기서 수정할 수 있습니다. 저장하면 즉시 반영됩니다.</p>

  <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
  <div v-else class="space-y-6">
    <div v-for="(items, cat) in grouped" :key="cat" class="card overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-50 font-bold text-sm flex items-center gap-1.5" :class="catStyles[cat]?.bg || 'bg-gray-50 text-ink'">
        <AppIcon :name="catStyles[cat]?.icon || 'list'" :size="14" /> {{ catStyles[cat]?.label || cat }}
        <NewFeatureBadge v-if="NEW_CATEGORIES.has(cat)" :desc="catStyles[cat]?.label + ' — 회원 등급 산정에 쓰이는 누적 포인트 기준값을 여기서 조정할 수 있습니다.'" />
      </div>
      <div class="divide-y divide-gray-50">
        <div v-for="item in items" :key="item.key" class="px-5 py-3 flex items-center gap-4">
          <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-ink flex items-center gap-1.5">
              {{ item.label }}
              <NewFeatureBadge v-if="NEW_KEYS.has(item.key)" :desc="item.description || item.label" />
            </div>
            <div class="text-[11px] text-ink-faint">{{ item.key }} {{ item.description ? '— ' + item.description : '' }}</div>
          </div>
          <input v-model="item.value" class="input-soft !w-40 !px-3 !py-1.5 text-sm text-right font-mono" />
        </div>
      </div>
    </div>

    <div class="flex items-center gap-3">
      <button @click="save" :disabled="saving" class="btn-primary !px-6 !py-2.5">
        {{ saving ? '저장중...' : '전체 저장' }}
      </button>
      <span v-if="msg" class="text-sm" :class="msgOk?'text-green-600':'text-red-500'">{{ msg }}</span>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import NewFeatureBadge from '../../components/NewFeatureBadge.vue'

const loading = ref(true)
const saving = ref(false)
const msg = ref('')
const msgOk = ref(false)
const grouped = ref({})
const allItems = ref([])

// 2026-09-12 배치(게시판별 포인트 재설계 1~3단계 + 회원등급/뱃지)에서
// 새로 추가된 설정 — NewFeatureBadge가 올해까지는 "NEW"+설명을,
// 내년부터는 설명 풍선말만 보여준다(사용자 결정).
const NEW_CATEGORIES = new Set(['grade'])
const NEW_KEYS = new Set([
  'content_earn_daily_max', 'like_reward_amount', 'like_reward_daily_max',
  'market_sale_complete', 'market_sale_complete_daily_max',
  'club_member_join', 'club_member_join_daily_max',
  'event_join', 'event_join_daily_max',
  'groupbuy_join_bonus', 'groupbuy_join_bonus_daily_max', 'groupbuy_complete',
  'business_claim_approved',
  'job_hire_complete', 'job_hire_complete_daily_max',
  'realestate_rent_complete', 'realestate_rent_complete_daily_max',
  // grade_N_min 항목들은 'grade' 카테고리 헤더에 이미 뱃지가 붙어있어(전부
  // 새 항목이라 카테고리 단위로 표시) 행마다 중복 표시하지 않음.
  'promo_max_clubs_national', 'promo_max_clubs_state_plus', 'promo_max_clubs_sponsored',
  'promo_price_clubs_national', 'promo_price_clubs_state_plus', 'promo_price_clubs_sponsored',
])

const catStyles = {
  earn: { icon: 'gift', label: '포인트 적립', bg: 'bg-green-50 text-green-800' },
  spend: { icon: 'coins', label: '포인트 사용 (차감)', bg: 'bg-red-50 text-red-800' },
  image: { icon: 'image', label: '이미지 업로드', bg: 'bg-blue-50 text-blue-800' },
  spam: { icon: 'shield', label: '스팸 방지 (중고장터)', bg: 'bg-orange-50 text-orange-800' },
  auction: { icon: 'store', label: '업소록 옥션', bg: 'bg-purple-50 text-purple-800' },
  package: { icon: 'wallet', label: '구매 패키지 (가격|포인트|보너스)', bg: 'bg-amber-50 text-amber-800' },
  promotion: { icon: 'flame', label: '상위노출 슬롯/가격', bg: 'bg-indigo-50 text-indigo-800' },
  grade: { icon: 'trophy', label: '회원 등급 기준점 (누적 포인트)', bg: 'bg-teal-50 text-teal-800' },
}

async function load() {
  try {
    const { data } = await axios.get('/api/admin/point-settings')
    grouped.value = data.data || {}
    allItems.value = Object.values(data.data || {}).flat()
  } catch {}
  loading.value = false
}

async function save() {
  saving.value = true; msg.value = ''
  try {
    await axios.post('/api/admin/point-settings', { settings: allItems.value.map(i => ({ key: i.key, value: i.value })) })
    msg.value = '저장되었습니다!'; msgOk.value = true
  } catch (e) {
    msg.value = e.response?.data?.message || '저장 실패'; msgOk.value = false
  }
  saving.value = false
}

onMounted(load)
</script>
