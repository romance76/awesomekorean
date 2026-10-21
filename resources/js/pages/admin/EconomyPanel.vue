<template>
<!-- 이코노미 한눈에: 포인트 · Entry · 매출 (슈퍼관리자 계정은 무제한이라 포인트·Entry 합계에서 뺀다) -->
<div class="bg-white border border-gray-100 rounded-2xl p-3.5 md:p-4 mb-3 md:mb-4">
  <div class="flex items-center gap-2 font-bold text-[15px] md:text-sm text-ink mb-3">
    <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="coins" :size="14" /></span>이코노미
    <span v-if="report.economy_excluded_admins" class="ml-auto text-[11px] font-normal text-ink-faint">슈퍼관리자 {{ report.economy_excluded_admins }}명(무제한)은 포인트·Entry 합계에서 제외</span>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
    <section v-for="s in sections" :key="s.key" class="rounded-xl border border-gray-100 p-3" :class="s.bg">
      <div class="flex items-center gap-1.5 text-[13px] font-bold text-ink mb-2">{{ s.icon }} {{ s.title }}</div>
      <div class="grid grid-cols-2 gap-x-3 gap-y-2">
        <div v-for="r in s.rows" :key="r.label">
          <div class="text-[11.5px] text-ink-muted">{{ r.label }}</div>
          <div class="text-[17px] font-bold tabular-nums leading-tight" :class="r.cls">{{ r.value }}</div>
        </div>
      </div>
    </section>
  </div>

  <!-- 최고관리자 전용: 시험·더미 계정의 포인트·Entry 정리 -->
  <div v-if="isSuper" class="mt-3 flex items-center gap-2 flex-wrap">
    <button type="button" @click="openCleanup" class="text-[12px] font-bold px-3 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50">시험 계정 포인트·Entry 정리</button>
    <span class="text-[11px] text-ink-faint">운영 계정과 슈퍼관리자는 건드리지 않아요. 실행 전 미리보기·백업·비밀번호 확인을 거쳐요.</span>
  </div>

  <div v-if="cl.open" class="fixed inset-0 z-[80] bg-black/40 flex items-end md:items-center justify-center p-3" @click.self="cl.open = false">
    <div class="bg-white rounded-2xl w-full max-w-lg max-h-[88vh] overflow-y-auto p-4 shadow-xl">
      <div class="flex items-center mb-2"><div class="text-[16px] font-extrabold text-ink">시험 계정 포인트·Entry 정리</div><button class="ml-auto text-ink-muted text-xl leading-none px-1" @click="cl.open = false" aria-label="닫기">×</button></div>
      <div v-if="cl.loading" class="py-8 text-center text-sm text-ink-muted">미리보기를 불러오는 중…</div>
      <template v-else-if="cl.data">
        <div class="rounded-xl bg-red-50 p-3 text-[13px] text-ink mb-3">
          <div class="font-bold text-red-600 mb-1">정리 대상 (아래 계정들만 비웁니다)</div>
          <div>계정 <b>{{ n(cl.data.target.accounts) }}</b>개 · 포인트 <b>{{ n(cl.data.target.points) }}</b>P · 게임 포인트 <b>{{ n(cl.data.target.game_points) }}</b> · Entry <b>{{ n(cl.data.target.entries) }}</b></div>
          <div class="text-ink-muted">지워질 기록: 포인트 {{ n(cl.data.target.point_logs) }}건 · Entry {{ n(cl.data.target.entry_logs) }}건 (실행 전 백업 파일로 저장돼요)</div>
        </div>
        <div v-if="cl.data.sample?.length" class="mb-3">
          <div class="text-[12px] font-bold text-ink-light mb-1">대상 예시 (포인트 많은 순)</div>
          <div v-for="u in cl.data.sample" :key="u.id" class="flex justify-between text-[12px] py-0.5 border-b border-gray-50"><span class="truncate">#{{ u.id }} {{ u.name }} · {{ u.email }}</span><span class="tabular-nums shrink-0 ml-2">{{ n(u.points) }}P · {{ n(u.entries) }}E</span></div>
        </div>
        <div class="mb-3">
          <div class="text-[12px] font-bold text-green-700 mb-1">건드리지 않는 계정 ({{ cl.data.protected.length }}명)</div>
          <div v-for="u in cl.data.protected" :key="u.id" class="flex justify-between text-[12px] py-0.5 border-b border-gray-50"><span class="truncate">#{{ u.id }} {{ u.name }} · {{ u.email }}<span v-if="u.super" class="text-red-500"> (슈퍼관리자)</span></span><span class="tabular-nums shrink-0 ml-2">{{ u.super ? '∞' : n(u.points) + 'P · ' + n(u.entries) + 'E' }}</span></div>
        </div>
        <div v-if="cl.done" class="rounded-xl bg-green-50 text-green-700 text-[13px] p-3">{{ cl.done }}</div>
        <template v-else-if="cl.data.target.accounts > 0">
          <label class="block text-[12px] text-ink-muted mb-1">확인을 위해 <b class="text-ink">정리</b> 라고 입력해 주세요</label>
          <input v-model="cl.confirm" type="text" class="input-soft w-full !py-2 !px-3 text-sm mb-2" placeholder="정리" />
          <div v-if="cl.error" class="text-[12px] text-red-600 mb-2">{{ cl.error }}</div>
          <button type="button" :disabled="cl.confirm.trim() !== '정리' || cl.busy" @click="runCleanup" class="w-full py-2.5 rounded-xl bg-red-600 text-white text-sm font-bold disabled:opacity-40">{{ cl.busy ? '정리 중…' : '정리 실행 (비밀번호 확인이 나와요)' }}</button>
        </template>
        <div v-else class="text-[13px] text-ink-muted text-center py-2">정리할 계정이 없어요.</div>
      </template>
      <div v-else-if="cl.error" class="text-[13px] text-red-600 py-4">{{ cl.error }}</div>
    </div>
  </div>
</div>
</template>

<script setup>
import { computed, reactive } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import { useAuthStore } from '../../stores/auth'

const props = defineProps({ report: { type: Object, required: true } })
const emit = defineEmits(['changed'])
const auth = useAuthStore()
const isSuper = computed(() => auth.user?.role === 'super_admin')

// 시험 계정 정리: 미리보기 → "정리" 입력 → (서버가 비밀번호 재확인을 요구하면 창이 뜬다) → 백업 후 실행
const cl = reactive({ open: false, loading: false, busy: false, data: null, error: '', confirm: '', done: '' })
async function openCleanup() {
  Object.assign(cl, { open: true, loading: true, data: null, error: '', confirm: '', done: '' })
  try { cl.data = (await axios.get('/api/admin/economy/cleanup/preview')).data.data }
  catch (e) { cl.error = e.response?.data?.message || '미리보기를 불러오지 못했어요.' }
  cl.loading = false
}
async function runCleanup() {
  if (cl.busy) return
  cl.busy = true; cl.error = ''
  try {
    const { data } = await axios.post('/api/admin/economy/cleanup', { confirm: cl.confirm.trim() })
    cl.done = (data.message || '정리했어요.') + (data.data?.backup ? ' 백업: ' + data.data.backup : '')
    emit('changed')
  } catch (e) { cl.error = e.response?.data?.message || '정리하지 못했어요.' }
  cl.busy = false
}

const n = v => Number(v || 0).toLocaleString()
const money = v => '$' + Number(v || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })

const sections = computed(() => {
  const r = props.report
  const p = r.points || {}, e = r.entries || {}, m = r.payments || {}
  return [
    { key: 'points', icon: '🪙', title: '포인트', bg: 'bg-amber-50/40', rows: [
      { label: '누적 적립', value: '+' + n(p.total_issued), cls: 'text-green-600' },
      { label: '누적 소비', value: '-' + n(p.total_spent), cls: 'text-red-600' },
      { label: '오늘 적립', value: '+' + n(p.today_issued), cls: 'text-green-500' },
      { label: '오늘 소비', value: '-' + n(p.today_spent), cls: 'text-red-500' },
      { label: '회원 보유 합계', value: n(p.circulating) + 'P', cls: 'text-amber-600' },
    ] },
    { key: 'entries', icon: '🎟', title: 'Entry', bg: 'bg-violet-50/40', rows: [
      { label: '누적 지급', value: '+' + n(e.total_issued), cls: 'text-green-600' },
      { label: '누적 사용', value: '-' + n(e.total_spent), cls: 'text-red-600' },
      { label: '오늘 지급', value: '+' + n(e.today_issued), cls: 'text-green-500' },
      { label: '오늘 사용', value: '-' + n(e.today_spent), cls: 'text-red-500' },
      { label: '회원 보유 합계', value: n(e.circulating), cls: 'text-violet-600' },
    ] },
    { key: 'revenue', icon: '💵', title: '매출', bg: 'bg-blue-50/40', rows: [
      { label: '누적 매출', value: money(m.total_revenue), cls: 'text-blue-600' },
      { label: '이달 매출', value: money(m.month_revenue), cls: 'text-blue-500' },
      { label: '오늘 매출', value: money(m.today_revenue), cls: 'text-blue-500' },
      { label: '주문 (완료)', value: n(m.total_orders) + '건 (' + n(m.completed) + ')', cls: 'text-ink' },
      { label: '환불', value: n(m.refunded) + '건', cls: 'text-red-500' },
    ] },
  ]
})
</script>
