<template>
<section v-if="sw" class="sw-panel px-4 lg:px-6 py-5 border-b border-gray-50 bg-gradient-to-b from-amber-50/60 to-white">
  <!-- 경품 -->
  <div class="flex items-start gap-3">
    <span class="icon-chip w-12 h-12 bg-amber-100 text-amber-600 text-2xl flex-shrink-0">🎁</span>
    <div class="min-w-0 flex-1">
      <div class="flex items-center gap-1.5 flex-wrap">
        <span class="text-[11px] font-bold text-amber-700 tracking-wide">경품</span>
        <span v-if="winnerCount > 1" class="badge bg-amber-100 text-amber-800">당첨 {{ winnerCount }}명</span>
        <span v-if="sw.status === 'winner_selected'" class="badge bg-emerald-50 text-emerald-700">추첨 완료</span>
      </div>
      <div class="text-lg lg:text-xl font-black text-ink leading-snug break-words">
        {{ sw.prize_name }}<span v-if="sw.prize_value" class="text-amber-600 text-base font-extrabold"> (${{ Number(sw.prize_value).toLocaleString() }})</span>
      </div>
    </div>
  </div>

  <!-- 등수별 경품 -->
  <ol v-if="winnerCount > 1" class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-1.5">
    <li v-for="t in tiers" :key="t.rank" class="flex items-center gap-2 bg-white border border-amber-100 rounded-lg px-3 py-1.5 text-sm">
      <span class="w-6 h-6 rounded-full bg-amber-400 text-white text-xs font-black grid place-items-center flex-shrink-0">{{ t.rank }}</span>
      <span class="text-xs text-ink-muted flex-shrink-0">{{ t.rank }}등</span>
      <span class="font-bold text-ink truncate">{{ t.prize_name || sw.prize_name }}</span>
    </li>
  </ol>

  <!-- 3D 추첨 연출: 이미 확정된 결과를 재생만 함 (당첨자 결정은 서버) -->
  <div v-if="sw.draw_style === 'lottery3d'" class="mt-4">
    <LotteryShowcase :key="event.id" :sweepstakes="sw" :recent="recentDraws" />
  </div>

  <!-- 결과 (3D 연출이 아닐 때만 별도 박스) -->
  <template v-if="sw.status === 'winner_selected'">
    <div v-if="sw.draw_style !== 'lottery3d'" class="mt-4">
      <div v-if="winnerCount > 1 && winners.length" class="bg-white border border-amber-200 rounded-xl p-4">
        <div class="text-center mb-2"><span class="text-2xl">🏆</span><div class="text-sm font-bold text-ink">당첨자 발표</div></div>
        <ol class="space-y-1.5">
          <li v-for="w in winners" :key="w.rank" class="flex items-center gap-2 text-sm">
            <span class="w-7 h-7 rounded-full bg-amber-400 text-white text-xs font-black grid place-items-center flex-shrink-0">{{ w.rank }}</span>
            <span class="text-xs text-ink-muted flex-shrink-0">{{ w.rank }}등</span>
            <span class="font-bold text-ink truncate">{{ w.name }}</span>
            <span v-if="w.prize" class="ml-auto text-xs text-amber-700 truncate max-w-[45%]">{{ w.prize }}</span>
          </li>
        </ol>
      </div>
      <div v-else class="bg-white border border-amber-200 rounded-xl p-4 text-center">
        <div class="text-2xl mb-1">🏆</div>
        <div class="font-bold text-ink">당첨자: {{ sw.winner_display_name || winners[0]?.name || '비공개' }}</div>
      </div>
    </div>
  </template>

  <!-- 참가 -->
  <template v-else>
    <div class="mt-4 bg-white rounded-2xl p-4 border border-amber-100 flex flex-col items-center">
      <template v-if="sw.draw_style !== 'lottery3d'">
        <div class="text-[11px] text-ink-faint mb-1 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>실시간 응모 현황</div>
        <SweepstakesWheel :my-entries="sw.my_entries || 0" :other-breakdown="sw.other_entries_breakdown || []" />
      </template>
      <div class="text-sm font-black text-amber-600 mt-2">내 당첨 확률 {{ sw.my_win_probability_pct || 0 }}%</div>
      <div v-if="winnerCount > 1" class="text-[11px] text-ink-faint mt-1 text-center">등수별로 한 명씩 추첨돼요. 한 사람은 한 번만 당첨됩니다.</div>
    </div>

    <div v-if="auth.isLoggedIn" class="mt-3">
      <div class="text-xs text-ink-muted mb-1.5">내 Entry 잔액: <span class="font-bold text-amber-600">🎟 {{ auth.user?.entries || 0 }}</span> · 이미 응모: {{ sw.my_entries || 0 }}</div>
      <button @click="enterModalOpen = true" :disabled="sw.status !== 'active'" class="btn-primary w-full py-3 disabled:opacity-40">🎟 Entry 사용해서 참가하기</button>
      <SweepstakesEnterModal :show="enterModalOpen" :sweepstakes="sw" :balance="Number(auth.user?.entries) || 0"
        @close="enterModalOpen = false" @entered="onEntered" />
      <p v-if="sw.status !== 'active'" class="text-xs text-ink-faint mt-2">현재 응모 기간이 아닙니다.</p>
    </div>
    <div v-else class="mt-3 text-xs text-ink-faint">응모하려면 로그인하세요</div>
  </template>

  <div class="text-[11px] text-ink-faint mt-4 space-y-0.5">
    <div v-if="sw.minimum_age">만 {{ sw.minimum_age }}세 이상 참가 가능</div>
    <div v-if="sw.eligible_regions?.length">참가 가능 지역: {{ sw.eligible_regions.join(', ') }}</div>
    <div v-if="sw.no_purchase_required_text">{{ sw.no_purchase_required_text }}</div>
    <div v-if="sw.official_rules_url"><a :href="sw.official_rules_url" target="_blank" rel="noopener" class="text-blue-600 hover:underline">공식 규정 보기</a></div>
  </div>

  <button v-if="canSelectWinner" @click="selectWinner" :disabled="selectingWinner"
    class="w-full mt-4 bg-violet-600 text-white font-bold py-2.5 rounded-xl text-sm hover:bg-violet-700 disabled:opacity-50 transition-colors">
    {{ selectingWinner ? '처리중...' : '당첨자 선정 (관리자)' }}
  </button>
</section>
</template>

<script setup>
import { ref, computed, defineAsyncComponent } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../../stores/auth'
import SweepstakesWheel from '../SweepstakesWheel.vue'
import SweepstakesEnterModal from '../SweepstakesEnterModal.vue'
// 3D 추첨 무대는 three.js 가 커서 필요할 때만 따로 내려받음 (별도 청크)
const LotteryShowcase = defineAsyncComponent(() => import('../LotteryShowcase.vue'))

const props = defineProps({
  event: { type: Object, required: true },
  recentDraws: { type: Array, default: () => [] },
})
const emit = defineEmits(['updated'])

const auth = useAuthStore()
const sw = computed(() => props.event?.sweepstakes || null)
const enterModalOpen = ref(false)
const selectingWinner = ref(false)

const winnerCount = computed(() => Math.max(1, Number(sw.value?.winner_count) || 1))

// prize_tiers 는 배열 또는 JSON 문자열로 올 수 있음 — 없으면 등수만 채움
const tiers = computed(() => {
  let raw = sw.value?.prize_tiers
  if (typeof raw === 'string') { try { raw = JSON.parse(raw) } catch { raw = [] } }
  const list = Array.isArray(raw) ? raw : []
  return Array.from({ length: winnerCount.value }, (_, i) => {
    const hit = list.find(t => Number(t?.rank) === i + 1)
    return { rank: i + 1, prize_name: hit?.prize_name || '' }
  })
})

// 당첨자 목록 (서버가 winners 를 내려주면 사용, 없으면 단일 당첨자 이름으로 대체)
const winners = computed(() => {
  // 서버 공개 응답은 draw.winners = [{rank, winning_ticket, winner_display_name, prize_label}]
  const w = (Array.isArray(sw.value?.draw?.winners) && sw.value.draw.winners.length) ? sw.value.draw.winners : sw.value?.winners
  if (Array.isArray(w) && w.length) {
    return w.map((x, i) => ({
      rank: Number(x.rank) || i + 1,
      prize: x.prize_label || x.prize_name || tiers.value[(Number(x.rank) || i + 1) - 1]?.prize_name || '',
      name: x.display_name || x.winner_display_name || x.nickname || x.user?.nickname || x.user?.name || x.name || '비공개',
    })).sort((a, b) => a.rank - b.rank)
  }
  if (sw.value?.winner_display_name) return [{ rank: 1, prize: '', name: sw.value.winner_display_name }]
  return []
})

const canSelectWinner = computed(() => {
  if (!sw.value || auth.user?.role !== 'super_admin') return false
  return sw.value.status !== 'winner_selected'
})

async function refresh() {
  const { data: fresh } = await axios.get(`/api/events/${props.event.id}`)
  emit('updated', fresh.data)
}

// 참가 개수 선택 → 확인 → OK 흐름은 SweepstakesEnterModal 이 처리. 참가 직후 데이터만 새로고침.
async function onEntered(res) {
  if (auth.user && res?.remaining_entries != null) auth.user.entries = res.remaining_entries
  try { await refresh() } catch {}
}

async function selectWinner() {
  const multi = winnerCount.value > 1
  if (!confirm(`"${props.event.title}" 당첨자${multi ? ` ${winnerCount.value}명` : ''}를 지금 선정하시겠습니까?\n\n이 작업은 서버에서 1회만 실행되며 절대 되돌릴 수 없습니다.`)) return
  selectingWinner.value = true
  try {
    const { data } = await axios.post(`/api/admin/sweepstakes/${sw.value.id}/select-winner`)
    const d = data.data || {}
    const list = Array.isArray(d.winners) ? d.winners : []
    if (list.length > 1) {
      alert('당첨자\n' + list.map((x, i) => `${x.rank || i + 1}등: ${x.nickname || x.display_name || x.user?.nickname || x.name || x.user?.name || '#' + (x.user_id ?? '')}`).join('\n'))
    } else {
      const one = list[0] || d.winner
      alert(`당첨자: ${one?.nickname || one?.display_name || one?.user?.nickname || one?.name || d.winner?.name || '#' + (d.winner_user_id ?? one?.user_id ?? '')}`)
    }
    await refresh()
  } catch (e) {
    alert(e.response?.data?.message || '당첨자 선정 실패')
  }
  selectingWinner.value = false
}
</script>
