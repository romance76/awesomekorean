<template>
<div>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-6">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="gamepad" :size="20" /></span>
    포커 관리
  </h1>

  <!-- Overview Cards -->
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
    <div v-for="s in overviewCards" :key="s.label" class="card p-4">
      <div class="text-ink-muted text-xs mb-1">{{ s.label }}</div>
      <div class="text-lg font-bold" :class="s.color">{{ s.value }}</div>
    </div>
  </div>

  <!-- Wallet Management -->
  <div class="card mb-6 overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-50 flex justify-between items-center">
      <h2 class="flex items-center gap-2 font-bold text-sm text-ink">
        <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="coins" :size="14" /></span>지갑 관리
      </h2>
      <div class="text-xs text-ink-muted">총 {{ wallets.length }}건</div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 text-ink-muted text-xs">
            <th class="px-4 py-2 text-left">&#50976;&#51200;</th>
            <th class="px-4 py-2 text-left">&#51060;&#47700;&#51068;</th>
            <th class="px-4 py-2 text-right">&#52841; &#51092;&#44256;</th>
            <th class="px-4 py-2 text-right">&#52509; &#51077;&#44552;</th>
            <th class="px-4 py-2 text-right">&#52509; &#52636;&#44552;</th>
            <th class="px-4 py-2 text-center">&#51312;&#51221;</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="w in wallets" :key="w.id" class="border-t border-gray-50 hover:bg-amber-50/40 transition-colors">
            <td class="px-4 py-2 font-semibold text-ink">{{ w.user?.name || w.user?.nickname || '?' }}</td>
            <td class="px-4 py-2 text-ink-muted text-xs">{{ w.user?.email || '-' }}</td>
            <td class="px-4 py-2 text-right font-mono font-bold text-amber-600">{{ (w.chips_balance || 0).toLocaleString() }}</td>
            <td class="px-4 py-2 text-right font-mono text-emerald-600">{{ (w.total_deposited || 0).toLocaleString() }}</td>
            <td class="px-4 py-2 text-right font-mono text-red-500">{{ (w.total_withdrawn || 0).toLocaleString() }}</td>
            <td class="px-4 py-2 text-center">
              <button @click="openAdjust(w)" class="text-xs text-blue-600 hover:underline font-bold">&#51312;&#51221;</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-if="wallets.length === 0" class="p-8 text-center text-ink-muted text-sm">지갑 데이터가 없습니다.</div>
  </div>

  <!-- Tournament Management -->
  <div class="card mb-6 overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-50 flex justify-between items-center">
      <h2 class="flex items-center gap-2 font-bold text-sm text-ink">
        <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="trophy" :size="14" /></span>토너먼트 관리
      </h2>
      <button @click="showCreateTournament = true" class="btn-primary !px-4 !py-2 !text-xs"><AppIcon name="plus" :size="13" /> 새 토너먼트</button>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead><tr class="bg-gray-50 text-ink-muted text-xs">
          <th class="px-4 py-2 text-left">제목</th>
          <th class="px-4 py-2 text-center">상태</th>
          <th class="px-4 py-2 text-right">바이인</th>
          <th class="px-4 py-2 text-right">참가자</th>
          <th class="px-4 py-2 text-center">시작 시간</th>
          <th class="px-4 py-2 text-center">관리</th>
        </tr></thead>
        <tbody>
          <tr v-for="t in tournaments" :key="t.id" class="border-t border-gray-50 hover:bg-amber-50/40 transition-colors">
            <td class="px-4 py-2 font-semibold text-ink">
              {{ t.title }}
              <span v-if="t.is_template" class="ml-1 inline-flex items-center gap-0.5 text-[11px] bg-blue-50 text-blue-600 px-1.5 py-0.5 rounded-full font-bold"><AppIcon name="refresh" :size="10" /> 반복</span>
            </td>
            <td class="px-4 py-2 text-center">
              <span :class="{'bg-blue-50 text-blue-700': t.status==='scheduled', 'bg-emerald-50 text-emerald-700': t.status==='registering', 'bg-amber-50 text-amber-700': t.status==='running', 'bg-gray-100 text-ink-light': t.status==='finished' || t.status==='cancelled'}" class="text-xs px-2 py-0.5 rounded-full font-bold">{{ t.status }}</span>
            </td>
            <td class="px-4 py-2 text-right font-mono">{{ (t.buy_in || 0).toLocaleString() }}</td>
            <td class="px-4 py-2 text-right font-mono">{{ t.entries_count || 0 }}/{{ t.max_players }}</td>
            <td class="px-4 py-2 text-center text-xs text-ink-muted">{{ formatNY(t.scheduled_at) }}</td>
            <td class="px-4 py-2 text-center">
              <button v-if="t.status !== 'running' && t.status !== 'finished'" @click="cancelTournament(t.id)" class="text-xs text-red-500 hover:underline">취소</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-if="!tournaments.length" class="p-6 text-center text-ink-muted text-sm">토너먼트가 없습니다.</div>
  </div>

  <!-- Create Tournament Modal -->
  <div v-if="showCreateTournament" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showCreateTournament=false">
    <div class="bg-white rounded-2xl p-6 w-[420px] shadow-xl max-h-[90vh] overflow-y-auto">
      <h3 class="flex items-center gap-2 font-bold text-ink mb-4">
        <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="trophy" :size="14" /></span>토너먼트 생성
      </h3>

      <!-- 모드 선택 -->
      <div class="flex bg-gray-100 rounded-xl p-1 mb-4">
        <button @click="newTournament.is_schedule = false"
          :class="!newTournament.is_schedule ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'"
          class="flex-1 py-2 rounded-lg text-sm font-semibold transition-colors">일회성</button>
        <button @click="newTournament.is_schedule = true"
          :class="newTournament.is_schedule ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'"
          class="flex-1 py-2 rounded-lg text-sm font-semibold transition-colors inline-flex items-center justify-center gap-1"><AppIcon name="refresh" :size="13" /> 반복 스케줄</button>
      </div>

      <div class="space-y-3">
        <div><label class="input-label">제목</label><input v-model="newTournament.title" class="input-soft" placeholder="18:00 데일리 $500"></div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="input-label">타입</label><select v-model="newTournament.type" class="input-soft"><option value="freeroll">프리롤</option><option value="micro">마이크로</option><option value="regular">레귤러</option><option value="high_roller">하이롤러</option></select></div>
          <div><label class="input-label">바이인</label><input v-model.number="newTournament.buy_in" type="number" class="input-soft"></div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="input-label">시작 칩</label><input v-model.number="newTournament.starting_chips" type="number" class="input-soft"></div>
          <div><label class="input-label">최대 인원</label><input v-model.number="newTournament.max_players" type="number" class="input-soft"></div>
        </div>

        <!-- 일회성: 날짜/시간 선택 -->
        <div v-if="!newTournament.is_schedule">
          <label class="input-label">시작 시간</label>
          <input v-model="newTournament.scheduled_at" type="datetime-local" class="input-soft">
        </div>

        <!-- 반복: 시간 + 요일 선택 -->
        <template v-else>
          <div><label class="input-label">매일 시작 시간</label><input v-model="newTournament.schedule_time" type="time" class="input-soft"></div>
          <div>
            <label class="input-label">반복 요일</label>
            <div class="flex gap-1 flex-wrap">
              <button v-for="d in dayOptions" :key="d.value" @click="toggleDay(d.value)"
                :class="newTournament.schedule_days.includes(d.value) ? 'bg-amber-400 text-white shadow-btn' : 'bg-gray-100 text-ink-muted hover:bg-gray-200'"
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors">{{ d.label }}</button>
            </div>
          </div>
          <div class="flex items-start gap-1.5 bg-blue-50 rounded-xl p-3 text-xs text-blue-700">
            <AppIcon name="info" :size="14" class="mt-0.5" /> 매일 자정에 다음 날 토너먼트가 자동 생성됩니다. 생성 즉시 참가 신청이 열립니다.
          </div>
        </template>
      </div>
      <div class="flex gap-2 mt-4">
        <button @click="createTournament" class="btn-primary flex-1 !py-2">{{ newTournament.is_schedule ? '스케줄 등록' : '생성' }}</button>
        <button @click="showCreateTournament=false" class="btn-secondary flex-1 !py-2">취소</button>
      </div>
    </div>
  </div>

  <!-- Settings -->
  <div class="card">
    <div class="px-4 py-3 border-b border-gray-50">
      <h2 class="flex items-center gap-2 font-bold text-sm text-ink">
        <span class="icon-chip w-7 h-7 bg-blue-50 text-blue-600"><AppIcon name="settings" :size="14" /></span>포커 설정
      </h2>
    </div>
    <div class="p-4 space-y-4 max-w-lg">
      <div v-for="f in settingsFields" :key="f.key" class="flex items-center justify-between">
        <label class="text-sm text-ink-light">{{ f.label }}</label>
        <input v-if="f.type === 'number'" v-model.number="settings[f.key]" type="number"
          class="input-soft w-32 !px-3 !py-1.5 text-right" />
        <label v-else class="relative inline-flex items-center cursor-pointer">
          <input v-model="settings[f.key]" type="checkbox" class="sr-only peer">
          <div class="w-9 h-5 bg-gray-200 peer-checked:bg-amber-500 rounded-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
        </label>
      </div>
      <div class="pt-2">
        <button @click="saveSettings" :disabled="saving"
          class="btn-primary !px-6 !py-2">
          {{ saving ? '&#51200;&#51109; &#51473;...' : '&#49444;&#51221; &#51200;&#51109;' }}
        </button>
      </div>
    </div>
  </div>

  <!-- Adjust Modal -->
  <div v-if="adjustWallet" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="adjustWallet = null">
    <div class="bg-white rounded-2xl p-6 w-80 shadow-xl">
      <h3 class="font-bold text-ink mb-4">&#52841; &#51092;&#44256; &#51312;&#51221;</h3>
      <div class="text-sm text-ink-light mb-1">{{ adjustWallet.user?.name || '?' }}</div>
      <div class="text-xs text-ink-muted mb-3">&#54788;&#51116; &#51092;&#44256;: <span class="font-bold text-amber-600">{{ (adjustWallet.chips_balance || 0).toLocaleString() }}</span></div>
      <input v-model.number="adjustAmount" type="number"
        class="input-soft mb-2"
        placeholder="&#49352; &#51091;&#44256; &#51077;&#47141;" />
      <div class="text-xs text-ink-muted mb-4">
        &#52264;&#51060;: <span :class="adjustAmount - (adjustWallet.chips_balance || 0) >= 0 ? 'text-emerald-500' : 'text-red-500'" class="font-bold">
          {{ adjustAmount - (adjustWallet.chips_balance || 0) >= 0 ? '+' : '' }}{{ (adjustAmount - (adjustWallet.chips_balance || 0)).toLocaleString() }}
        </span>
      </div>
      <div class="flex gap-2">
        <button @click="submitAdjust" :disabled="adjusting"
          class="btn-primary flex-1 !py-2">
          {{ adjusting ? '&#52376;&#47532; &#51473;...' : '&#51200;&#51109;' }}
        </button>
        <button @click="adjustWallet = null" class="btn-secondary flex-1 !py-2">&#52712;&#49548;</button>
      </div>
      <div v-if="adjustError" class="text-xs text-red-500 mt-2 text-center">{{ adjustError }}</div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const overview = ref({})
const wallets = ref([])
const settings = ref({
  min_deposit: 1000,
  min_withdraw: 1000,
  withdraw_fee_pct: 5,
  max_buy_in: 10000,
  enabled: true,
})
const saving = ref(false)
const adjustWallet = ref(null)
const adjustAmount = ref(0)
const adjusting = ref(false)
const adjustError = ref('')
const tournaments = ref([])
const scheduleTemplates = ref([])
const showCreateTournament = ref(false)
const newTournament = ref({
  title: '', type: 'regular', buy_in: 500, starting_chips: 15000,
  max_players: 90, scheduled_at: '', is_schedule: false,
  schedule_time: '18:00', schedule_days: ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
})

const dayOptions = [
  { value: 'mon', label: '월' }, { value: 'tue', label: '화' },
  { value: 'wed', label: '수' }, { value: 'thu', label: '목' },
  { value: 'fri', label: '금' }, { value: 'sat', label: '토' },
  { value: 'sun', label: '일' },
]

function toggleDay(day) {
  const idx = newTournament.value.schedule_days.indexOf(day)
  if (idx >= 0) newTournament.value.schedule_days.splice(idx, 1)
  else newTournament.value.schedule_days.push(day)
}

function formatNY(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleString('ko-KR', { timeZone: 'America/New_York', year: 'numeric', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const overviewCards = computed(() => [
  { label: '\uCD1D \uAC8C\uC784', value: (overview.value.total_games || 0).toLocaleString(), color: 'text-blue-600' },
  { label: '\uCD1D \uC9C0\uAC11', value: (overview.value.total_wallets || 0).toLocaleString(), color: 'text-ink' },
  { label: '\uCD1D \uC785\uAE08', value: (overview.value.total_deposited || 0).toLocaleString(), color: 'text-emerald-600' },
  { label: '\uCD1D \uCD9C\uAE08', value: (overview.value.total_withdrawn || 0).toLocaleString(), color: 'text-red-500' },
  { label: '\uC720\uD1B5 \uCE69', value: (overview.value.chips_in_circulation || 0).toLocaleString(), color: 'text-amber-600' },
  { label: '\uD65C\uC131 \uC720\uC800', value: (overview.value.active_players || 0).toLocaleString(), color: 'text-purple-600' },
])

const settingsFields = [
  { key: 'min_deposit', label: '\uCD5C\uC18C \uC785\uAE08', type: 'number' },
  { key: 'min_withdraw', label: '\uCD5C\uC18C \uCD9C\uAE08', type: 'number' },
  { key: 'withdraw_fee_pct', label: '\uCD9C\uAE08 \uC218\uC218\uB8CC (%)', type: 'number' },
  { key: 'max_buy_in', label: '\uCD5C\uB300 \uBC14\uC774\uC778', type: 'number' },
  { key: 'enabled', label: '\uD3EC\uCEE4 \uD65C\uC131\uD654', type: 'toggle' },
]

onMounted(async () => {
  try {
    const [ov, wl, st, tn] = await Promise.all([
      axios.get('/api/admin/poker/overview'),
      axios.get('/api/admin/poker/wallets'),
      axios.get('/api/admin/poker/settings'),
      axios.get('/api/admin/poker/tournaments'),
    ])
    if (ov.data.success) overview.value = ov.data.data
    if (wl.data.success) wallets.value = wl.data.data?.data || wl.data.data || []
    if (st.data.success) Object.assign(settings.value, st.data.data)
    if (tn.data.success) {
      const d = tn.data.data
      if (d?.templates !== undefined) {
        scheduleTemplates.value = d.templates || []
        tournaments.value = d.tournaments?.data || d.tournaments || []
      } else {
        tournaments.value = d?.data || d || []
      }
    }
  } catch (e) {
    console.error('Admin poker load failed', e)
  }
})

function openAdjust(w) {
  adjustWallet.value = w
  adjustAmount.value = w.chips_balance || 0
  adjustError.value = ''
}

async function submitAdjust() {
  adjusting.value = true
  adjustError.value = ''
  try {
    await axios.put(`/api/admin/poker/wallets/${adjustWallet.value.id}`, { chips_balance: adjustAmount.value })
    adjustWallet.value.chips_balance = adjustAmount.value
    adjustWallet.value = null
  } catch (e) {
    adjustError.value = e.response?.data?.message || e.message
  } finally {
    adjusting.value = false
  }
}

async function createTournament() {
  try {
    const payload = { ...newTournament.value }
    const { data } = await axios.post('/api/admin/poker/tournaments', payload)
    if (data.success) {
      // Refresh list
      const tn = await axios.get('/api/admin/poker/tournaments')
      if (tn.data.success) {
      const d = tn.data.data
      if (d?.templates !== undefined) {
        scheduleTemplates.value = d.templates || []
        tournaments.value = d.tournaments?.data || d.tournaments || []
      } else {
        tournaments.value = d?.data || d || []
      }
    }
      showCreateTournament.value = false
      newTournament.value = { title: '', type: 'regular', buy_in: 500, starting_chips: 15000, max_players: 90, scheduled_at: '', is_schedule: false, schedule_time: '18:00', schedule_days: ['mon','tue','wed','thu','fri','sat','sun'] }
      alert(data.message)
    }
  } catch (e) { alert(e.response?.data?.message || e.message) }
}

async function cancelTournament(id) {
  if (!confirm('이 토너먼트를 취소하시겠습니까? 참가자 전원에게 환불됩니다.')) return
  try {
    await axios.delete(`/api/admin/poker/tournaments/${id}`)
    tournaments.value = tournaments.value.filter(t => t.id !== id)
  } catch (e) { alert(e.response?.data?.message || e.message) }
}

async function saveSettings() {
  saving.value = true
  try {
    await axios.put('/api/admin/poker/settings', settings.value)
    alert('\uC124\uC815\uC774 \uC800\uC7A5\uB418\uC5C8\uC2B5\uB2C8\uB2E4.')
  } catch (e) {
    alert('\uC800\uC7A5 \uC2E4\uD328: ' + (e.response?.data?.message || e.message))
  } finally {
    saving.value = false
  }
}
</script>
