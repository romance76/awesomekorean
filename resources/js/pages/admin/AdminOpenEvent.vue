<template>
<div>
  <div class="mb-4">
    <div class="text-xs text-ink-muted">관리자 › 광고/가격 › 오픈 이벤트</div>
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mt-1">
      <span class="icon-chip w-9 h-9 bg-rose-50 text-rose-600"><AppIcon name="gift" :size="20" /></span>
      오픈 이벤트
      <span class="text-[11px] font-bold px-2 py-0.5 rounded-full" :class="stateChip.cls">{{ stateChip.text }}</span>
    </h1>
    <p class="text-xs text-ink-faint mt-0.5">소프트 오픈 기간에 포인트 적립 · 무료 · 할인을 한 번에 켜요. 원래 가격은 그대로 두고, 기간(미국 동부 날짜)이 끝나면 자동으로 원래대로 돌아와요.</p>
  </div>

  <div v-if="error" class="mb-3 bg-red-50 border border-red-200 text-red-600 text-xs rounded-xl p-3">{{ error }}</div>
  <div v-if="notice" class="mb-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs rounded-xl p-3">{{ notice }}</div>

  <div v-if="loading" class="card px-4 py-8 text-sm text-ink-muted text-center">불러오는 중...</div>

  <template v-else-if="cfg">
    <!-- 1. 전체 켜기 / 기간 -->
    <div class="card p-4 mb-3">
      <div class="flex items-center justify-between gap-3 flex-wrap">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" v-model="cfg.enabled" class="w-5 h-5 accent-rose-500" />
          <span class="font-bold text-ink">오픈 이벤트 사용</span>
        </label>
        <button @click="loadRecommended" class="text-xs font-bold text-blue-600 hover:underline">추천 설정 불러오기 (10/15 ~ 11/30)</button>
      </div>
      <p class="text-xs text-ink-muted mt-1">꺼져 있으면 아무것도 바뀌지 않아요. 켜 두면 시작일 00:00부터 종료일 23:59까지(미국 동부 시간) 자동 적용돼요. 오늘 날짜: {{ today }}</p>
      <div class="grid grid-cols-2 gap-3 mt-3 max-w-md">
        <label class="text-xs text-ink-muted">시작일
          <input type="date" v-model="cfg.starts_on" class="input-soft w-full mt-1" />
        </label>
        <label class="text-xs text-ink-muted">종료일
          <input type="date" v-model="cfg.ends_on" class="input-soft w-full mt-1" />
        </label>
      </div>
    </div>

    <!-- 2. 적용 항목 선택 -->
    <div class="card p-4 mb-3">
      <div class="font-bold text-ink mb-2">적용할 항목 선택</div>
      <div class="divide-y divide-gray-100">
        <div class="py-2.5 flex items-center gap-3 flex-wrap">
          <input type="checkbox" v-model="cfg.earn.on" class="w-4 h-4 accent-rose-500" />
          <div class="flex-1 min-w-[180px]">
            <div class="text-sm font-bold text-ink">포인트 적립 · 가입 보너스 배수</div>
            <div class="text-[11px] text-ink-muted">글쓰기·댓글·출석·가입 보너스 등 "받는 포인트"를 곱해요. (하루 횟수 한도는 그대로)</div>
          </div>
          <select v-model.number="cfg.earn.multiplier" :disabled="!cfg.earn.on" class="input-soft !w-auto !px-2 !py-1 text-sm">
            <option v-for="n in 10" :key="n" :value="n">{{ n }}배</option>
          </select>
        </div>

        <div v-for="m in meta" :key="m.key" class="py-2.5 flex items-center gap-3 flex-wrap">
          <input type="checkbox" v-model="cfg.items[m.key].on" class="w-4 h-4 accent-rose-500" />
          <div class="flex-1 min-w-[180px]">
            <div class="text-sm font-bold text-ink">{{ m.label }}</div>
            <div class="text-[11px] text-ink-muted">{{ hints[m.key] }}</div>
          </div>
          <div class="flex items-center gap-1" :class="!cfg.items[m.key].on ? 'opacity-40' : ''">
            <button v-for="p in presets(m.key)" :key="p" type="button" @click="cfg.items[m.key].pct = p" :disabled="!cfg.items[m.key].on"
              class="text-[11px] font-bold px-2 py-1 rounded-md" :class="cfg.items[m.key].pct === p ? 'bg-rose-500 text-white' : 'bg-gray-100 text-ink-muted hover:bg-gray-200'">{{ p === 100 ? '무료' : p + '%' }}</button>
            <input type="number" min="0" :max="m.key === 'flyer_usd' ? 95 : 100" v-model.number="cfg.items[m.key].pct" :disabled="!cfg.items[m.key].on" class="input-soft !w-16 !px-2 !py-1 text-sm text-right" />
            <span class="text-xs text-ink-muted">% 할인</span>
          </div>
        </div>

        <div class="py-2.5 flex items-center gap-3 flex-wrap">
          <input type="checkbox" v-model="cfg.photos.on" class="w-4 h-4 accent-rose-500" />
          <div class="flex-1 min-w-[180px]">
            <div class="text-sm font-bold text-ink">장터·부동산 무료 사진 장수</div>
            <div class="text-[11px] text-ink-muted">기존 무료 장수보다 많을 때만 늘어나요. (등록 자체는 원래 무료)</div>
          </div>
          <div class="flex items-center gap-1" :class="!cfg.photos.on ? 'opacity-40' : ''">
            <input type="number" min="0" max="30" v-model.number="cfg.photos.count" :disabled="!cfg.photos.on" class="input-soft !w-16 !px-2 !py-1 text-sm text-right" />
            <span class="text-xs text-ink-muted">장까지 무료</span>
          </div>
        </div>

        <div class="py-2.5 flex items-center gap-3 flex-wrap">
          <input type="checkbox" v-model="cfg.purchase_bonus.on" class="w-4 h-4 accent-rose-500" />
          <div class="flex-1 min-w-[180px]">
            <div class="text-sm font-bold text-ink">포인트 구매 추가 보너스</div>
            <div class="text-[11px] text-ink-muted">결제 금액 구간별 기존 보너스에 더해서 지급돼요.</div>
          </div>
          <div class="flex items-center gap-1" :class="!cfg.purchase_bonus.on ? 'opacity-40' : ''">
            <span class="text-xs text-ink-muted">+</span>
            <input type="number" min="0" max="100" v-model.number="cfg.purchase_bonus.pct" :disabled="!cfg.purchase_bonus.on" class="input-soft !w-16 !px-2 !py-1 text-sm text-right" />
            <span class="text-xs text-ink-muted">%</span>
          </div>
        </div>
      </div>
      <p class="text-[11px] text-ink-faint mt-2">달러 결제 할인은 최대 95%예요(0원 결제 방지). 최소 주문 금액은 이벤트 동안 50센트로 낮춰져요.</p>
    </div>

    <!-- 3. 사이트 안내 문구 -->
    <div class="card p-4 mb-3">
      <div class="font-bold text-ink mb-2">사이트 상단 안내 문구</div>
      <div class="space-y-2 max-w-xl">
        <input v-model="cfg.headline" maxlength="40" placeholder="제목" class="input-soft w-full" />
        <input v-model="cfg.subline" maxlength="100" placeholder="부제목" class="input-soft w-full" />
      </div>
      <div v-if="perks.length" class="flex flex-wrap gap-1.5 mt-3">
        <span v-for="p in perks" :key="p" class="text-[11px] font-bold px-2 py-1 rounded-full bg-rose-50 text-rose-600">{{ p }}</span>
      </div>
    </div>

    <!-- 4. 저장 -->
    <div class="flex items-center gap-2 mb-4">
      <button @click="save" :disabled="saving" class="btn-primary px-5 py-2 text-sm">{{ saving ? '저장 중...' : (cfg.enabled ? '적용하기' : '저장 (꺼짐 상태)') }}</button>
      <span class="text-xs text-ink-muted">저장하면 사이트 전체에 1분 안에 반영돼요.</span>
    </div>

    <!-- 5. 미리보기 -->
    <div class="card p-4">
      <div class="font-bold text-ink mb-1">적용 미리보기 (원래 → 이벤트)</div>
      <p class="text-[11px] text-ink-muted mb-2">저장된 설정 기준이에요. 항목을 바꿨다면 저장 후 확인하세요.</p>
      <div v-for="(rows, g) in grouped" :key="g" class="mb-3">
        <div class="text-xs font-bold text-ink-muted mb-1">{{ g }}</div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <tbody>
              <tr v-for="r in rows" :key="r.label" class="border-t border-gray-100">
                <td class="py-1.5 pr-2 text-ink">{{ r.label }}</td>
                <td class="py-1.5 pr-2 text-ink-muted whitespace-nowrap text-right">{{ r.original }}</td>
                <td class="py-1.5 px-1 text-ink-faint">→</td>
                <td class="py-1.5 font-bold whitespace-nowrap text-right" :class="r.original !== r.event ? 'text-rose-600' : 'text-ink-muted'">{{ r.event }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </template>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const cfg = ref(null)
const state = ref('off')
const today = ref('')
const meta = ref([])
const perks = ref([])
const preview = ref([])
const defaults = ref(null)
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const notice = ref('')

const hints = {
  chat_create: '1:1 · 그룹 · 공개 채팅방을 만들 때 드는 포인트',
  chat_entry: '공개 채팅방에 들어갈 때 드는 포인트 (기본은 선택 안 함)',
  bump: '중고장터 끌어올리기 비용 (회차가 늘수록 올라가는 금액도 함께 할인)',
  promotion: '구인구직·장터·부동산·업소록·동호회 상위노출 하루 가격',
  banner: '배너·텍스트 광고 신청 시 실제로 빠지는 포인트',
  flyer_usd: 'NEW 전단 광고를 달러로 결제할 때 (카드/PayPal 등)',
}

const stateChip = computed(() => ({
  off: { text: '꺼짐', cls: 'bg-gray-100 text-ink-muted' },
  scheduled: { text: '예정', cls: 'bg-amber-50 text-amber-700' },
  active: { text: '진행 중', cls: 'bg-emerald-50 text-emerald-700' },
  ended: { text: '종료', cls: 'bg-gray-100 text-ink-muted' },
}[state.value] || { text: '', cls: '' }))

const grouped = computed(() => {
  const g = {}
  for (const r of preview.value) (g[r.group] ||= []).push(r)
  return g
})

const presets = (key) => key === 'flyer_usd' ? [50, 90, 95] : [50, 90, 100]

function apply(d) {
  cfg.value = d.config
  state.value = d.state
  perks.value = d.perks || []
  preview.value = d.preview || []
  if (d.today) today.value = d.today
  if (d.items_meta) meta.value = d.items_meta
  if (d.defaults) defaults.value = d.defaults
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/open-event')
    apply(data.data)
    error.value = ''
  } catch (e) {
    error.value = e.response?.status === 403 ? '최고 관리자만 볼 수 있는 화면이에요.' : '불러오지 못했어요.'
  } finally { loading.value = false }
}

function loadRecommended() {
  if (!defaults.value) return
  const d = JSON.parse(JSON.stringify(defaults.value))
  d.enabled = cfg.value.enabled
  cfg.value = d
  notice.value = '추천 설정을 불러왔어요. 아래 "적용하기"를 눌러야 저장돼요.'
}

async function save() {
  error.value = ''; notice.value = ''
  if (cfg.value.enabled && cfg.value.ends_on < cfg.value.starts_on) { error.value = '종료일이 시작일보다 빠를 수 없어요.'; return }
  saving.value = true
  try {
    const { data } = await axios.put('/api/admin/open-event', cfg.value)
    apply(data.data)
    notice.value = data.message || '저장했어요.'
  } catch (e) {
    const errs = e.response?.data?.errors
    error.value = errs ? Object.values(errs).flat().join(' ') : '저장하지 못했어요.'
  } finally { saving.value = false }
}

onMounted(load)
</script>
