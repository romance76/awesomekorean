<template>
<div v-if="show" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
    <div class="bg-gradient-to-r from-[#FF8A53] to-[#F2570F] px-5 py-4 text-white flex justify-between items-center">
      <div>
        <div class="font-black text-lg flex items-center gap-1.5"><AppIcon name="coins" :size="18" /> 게임머니 환전</div>
        <div class="text-xs opacity-80">포인트 ↔ 게임머니</div>
      </div>
      <button @click="$emit('close')" class="text-white/80 hover:text-white text-2xl transition-colors">✕</button>
    </div>

    <div class="p-5">
      <!-- 잔액 -->
      <div class="grid grid-cols-2 gap-2 mb-4">
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-center">
          <div class="text-xs text-amber-700 flex items-center justify-center gap-1"><AppIcon name="coins" :size="12" /> 포인트</div>
          <div class="text-lg font-black text-amber-900">{{ Number(data.points||0).toLocaleString() }}</div>
        </div>
        <div class="bg-purple-50 border border-purple-200 rounded-lg p-3 text-center">
          <div class="text-xs text-purple-700 flex items-center justify-center gap-1"><AppIcon name="gamepad" :size="12" /> 게임머니</div>
          <div class="text-lg font-black text-purple-900">{{ Number(data.game_points||0).toLocaleString() }}</div>
        </div>
      </div>

      <!-- 환율 안내 -->
      <div class="bg-gray-50 border border-gray-100 rounded-lg p-2.5 text-xs text-ink-light mb-4">
        환율: 1P = {{ Number(settings.rate_to_game||10000).toLocaleString() }} GM<br>
        역환전 수수료: {{ settings.withdraw_fee_pct }}%
      </div>

      <!-- 탭 -->
      <div class="flex gap-1 mb-3 border-b border-gray-100">
        <button @click="mode='exchange'" class="px-3 py-2 text-sm font-bold border-b-2 -mb-px transition-colors flex items-center gap-1"
          :class="mode==='exchange' ? 'border-amber-500 text-amber-600' : 'border-transparent text-ink-muted'">
          <AppIcon name="refresh" :size="13" /> 환전 (P → GM)
        </button>
        <button @click="mode='withdraw'" class="px-3 py-2 text-sm font-bold border-b-2 -mb-px transition-colors flex items-center gap-1"
          :class="mode==='withdraw' ? 'border-amber-500 text-amber-600' : 'border-transparent text-ink-muted'">
          <AppIcon name="dollar" :size="13" /> 역환전 (GM → P)
        </button>
      </div>

      <!-- 환전 (P → GM) -->
      <div v-if="mode==='exchange'" class="space-y-3">
        <div>
          <label class="input-label text-xs">포인트 입력 (최소 {{ settings.min_exchange_p }}P)</label>
          <input v-model.number="exchangeP" type="number" :min="settings.min_exchange_p" :max="data.points"
            class="input-soft w-full text-sm mt-1" placeholder="환전할 포인트" />
          <div class="mt-1 text-xs text-ink-muted">
            = <strong class="text-purple-700">{{ Number((exchangeP||0) * (settings.rate_to_game||10000)).toLocaleString() }}</strong> 게임머니 받음
          </div>
        </div>
        <div class="flex gap-1">
          <button v-for="p in [100, 500, 1000, 5000]" :key="p" @click="exchangeP = Math.min(p, data.points)"
            class="flex-1 text-xs bg-gray-100 hover:bg-amber-100 px-2 py-1 rounded transition-colors text-ink-light">{{ p }}P</button>
          <button @click="exchangeP = data.points" class="flex-1 text-xs bg-amber-100 hover:bg-amber-200 px-2 py-1 rounded transition-colors text-amber-700 font-semibold">전액</button>
        </div>
        <button @click="doExchange" :disabled="busy || !exchangeP"
          class="w-full bg-amber-400 text-white font-bold py-2 rounded-lg hover:bg-amber-500 disabled:opacity-50 transition-colors shadow-btn">
          {{ busy ? '처리중...' : `${(exchangeP||0).toLocaleString()}P → 게임머니 받기` }}
        </button>
      </div>

      <!-- 역환전 (GM → P) -->
      <div v-else class="space-y-3">
        <div>
          <label class="input-label text-xs">게임머니 입력 (최소 {{ Number(settings.min_withdraw_gm).toLocaleString() }} GM)</label>
          <input v-model.number="withdrawGm" type="number" :min="settings.min_withdraw_gm" :max="data.game_points"
            class="input-soft w-full text-sm mt-1" placeholder="역환전할 게임머니" />
          <div class="mt-1 text-xs text-ink-muted">
            수수료 {{ settings.withdraw_fee_pct }}% 차감 후
            = <strong class="text-amber-700">{{ Math.floor((withdrawGm||0) * (100 - (settings.withdraw_fee_pct||10)) / 100 / (settings.rate_to_game||10000)).toLocaleString() }}P</strong> 받음
          </div>
        </div>
        <div class="flex gap-1">
          <button v-for="p in [100000, 1000000, 10000000]" :key="p" @click="withdrawGm = Math.min(p, data.game_points)"
            class="flex-1 text-xs bg-gray-100 hover:bg-amber-100 px-2 py-1 rounded transition-colors text-ink-light">{{ (p/10000).toLocaleString() }}만</button>
          <button @click="withdrawGm = data.game_points" class="flex-1 text-xs bg-amber-100 hover:bg-amber-200 px-2 py-1 rounded transition-colors text-amber-700 font-semibold">전액</button>
        </div>
        <button @click="doWithdraw" :disabled="busy || !withdrawGm"
          class="w-full bg-amber-500 text-white font-bold py-2 rounded-lg hover:bg-amber-600 disabled:opacity-50 transition-colors shadow-btn">
          {{ busy ? '처리중...' : `${(withdrawGm||0).toLocaleString()} GM → 포인트 받기` }}
        </button>
      </div>

      <div v-if="message" class="mt-3 p-2 rounded text-xs" :class="messageType==='ok'?'bg-green-50 text-green-800':'bg-red-50 text-red-800'">
        {{ message }}
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, watch } from 'vue'
import AppIcon from './AppIcon.vue'
import axios from 'axios'

const props = defineProps({ show: Boolean })
const emit = defineEmits(['close', 'updated'])

const mode = ref('exchange')
const data = ref({ points: 0, game_points: 0 })
const settings = ref({ rate_to_game: 10000, withdraw_fee_pct: 10, min_exchange_p: 10, min_withdraw_gm: 100000 })
const exchangeP = ref(null)
const withdrawGm = ref(null)
const busy = ref(false)
const message = ref('')
const messageType = ref('ok')

async function load() {
  try {
    const { data: res } = await axios.get('/api/game-money')
    data.value = res.data
    settings.value = res.data.settings
  } catch {}
}

async function doExchange() {
  busy.value = true; message.value = ''
  try {
    const { data: res } = await axios.post('/api/game-money/exchange', { amount_p: exchangeP.value })
    message.value = res.message; messageType.value = 'ok'
    data.value.points = res.data.points
    data.value.game_points = res.data.game_points
    exchangeP.value = null
    emit('updated', res.data)
  } catch (e) {
    message.value = e.response?.data?.message || '환전 실패'
    messageType.value = 'err'
  }
  busy.value = false
}

async function doWithdraw() {
  busy.value = true; message.value = ''
  try {
    const { data: res } = await axios.post('/api/game-money/withdraw', { amount_gm: withdrawGm.value })
    message.value = res.message; messageType.value = 'ok'
    data.value.points = res.data.points
    data.value.game_points = res.data.game_points
    withdrawGm.value = null
    emit('updated', res.data)
  } catch (e) {
    message.value = e.response?.data?.message || '역환전 실패'
    messageType.value = 'err'
  }
  busy.value = false
}

watch(() => props.show, (v) => { if (v) load() }, { immediate: true })
</script>
