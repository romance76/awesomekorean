<template>
<!-- 지급·보상 센터 — 돈과 연결된 지급을 한 곳에서: ① 회원 찾아 포인트·Entry 지급/차감 + 실제 받은 구매 금액 기록 ② 보상 승인(이벤트 완료 인증·레시피 보상) ③ 지급 기록 -->
<div class="space-y-3 pb-20" :class="isMobile ? 'ag-m' : ''">
  <div v-if="toastMsg" class="fixed top-16 left-1/2 -translate-x-1/2 z-[95] max-w-[92vw] px-4 py-3 rounded-xl text-[14px] font-bold text-white shadow-lg" :class="toastErr ? 'bg-red-600' : 'bg-ink'" role="status">{{ toastMsg }}</div>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
    <span class="icon-chip w-9 h-9 bg-emerald-50 text-emerald-600"><AppIcon name="gift" :size="20" /></span>
    지급·보상 센터
  </h1>
  <p class="text-[13px] text-ink-muted leading-relaxed">포인트·Entry 지급, 실제로 받은 구매 금액 기록, 보상 승인을 한 곳에서 해요. 모든 지급은 <b>감사 로그</b>에 남아요. (포인트 = 커뮤니티 점수, Entry = 성과 보상, 구매 금액 = 진짜 돈 — 서로 바꿀 수 없어요)</p>

  <div class="grid grid-cols-3 gap-1 bg-gray-200/70 rounded-2xl p-1" role="tablist">
    <button v-for="t in tabs" :key="t.k" @click="setSection(t.k)" role="tab" :aria-selected="section === t.k"
      class="min-h-[46px] rounded-xl text-[14px] font-bold flex items-center justify-center gap-1" :class="section === t.k ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'">
      {{ t.l }}<span v-if="t.k === 'rewards' && pendingRewards" class="min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[11px] grid place-items-center">{{ pendingRewards }}</span>
    </button>
  </div>

  <!-- ───────── 직접 지급 ───────── -->
  <div v-if="section === 'give'" class="space-y-3">
    <div class="card p-3.5 space-y-2">
      <label class="block text-[13px] font-bold text-ink-muted">회원 찾기 (이름·이메일·번호·전화) <span class="font-normal text-ink-faint">— 글자를 치면 바로 찾아요</span></label>
      <div class="relative">
        <input v-model="q" @input="searchSoon" @keydown.enter.prevent="searchNow" type="search" placeholder="예: 홍길동 / 2905 / kim@gmail.com" autocomplete="off" class="input-soft w-full px-3 min-h-[46px]" />
        <span v-if="searching" class="absolute right-3 top-1/2 -translate-y-1/2 text-[12px] text-ink-muted">찾는 중...</span>
      </div>
      <p v-if="searchError" class="text-[13px] text-red-600">{{ searchError }}</p>
      <template v-else-if="!picked">
        <p v-if="q.trim() && q.trim().length < minLen" class="text-[12px] text-ink-faint">{{ minLen }}글자 이상 입력하면 찾기 시작해요.</p>
        <p v-else-if="searched && !searching && !results.length" class="text-[13px] text-ink-muted">‘{{ lastQuery }}’ 에 맞는 회원이 없어요.</p>
        <p v-else-if="results.length" class="text-[12px] text-ink-muted">{{ results.length }}명 찾았어요 — 한 명을 눌러 선택하세요.</p>
      </template>
      <div v-if="results.length && !picked" class="border border-gray-200 rounded-xl overflow-hidden">
        <div class="hidden sm:grid grid-cols-[48px_1.2fr_1.6fr_90px_70px_70px] gap-2 px-3 py-1.5 bg-gray-50 text-[11px] font-bold text-ink-faint"><span>번호</span><span>이름</span><span>이메일</span><span class="text-right">포인트</span><span class="text-right">Entry</span><span>권한</span></div>
        <button v-for="u in results" :key="u.id" @click="pick(u)" class="w-full text-left px-3 py-2 min-h-[44px] border-t border-gray-100 first:border-t-0 hover:bg-amber-50 active:bg-amber-50 grid grid-cols-[1fr_auto] sm:grid-cols-[48px_1.2fr_1.6fr_90px_70px_70px] gap-x-2 items-center text-[13px]">
          <span class="hidden sm:block text-ink-faint tabular-nums">{{ u.id }}</span>
          <span class="min-w-0 truncate font-bold text-ink">{{ u.nickname || u.name }}<span class="sm:hidden text-[11px] font-normal text-ink-faint"> #{{ u.id }}</span><span class="sm:hidden block text-[11px] font-normal text-ink-faint truncate">{{ u.email }}</span></span>
          <span class="hidden sm:block min-w-0 truncate text-ink-muted">{{ u.email }}</span>
          <span class="text-right tabular-nums text-amber-700 font-bold">{{ (u.points || 0).toLocaleString() }}<span class="sm:hidden">P</span></span>
          <span class="hidden sm:block text-right tabular-nums text-violet-700">{{ (u.entries || 0).toLocaleString() }}</span>
          <span class="hidden sm:block text-[11px] text-ink-muted">{{ roleName(u.role) }}</span>
        </button>
      </div>
    </div>

    <template v-if="picked">
      <!-- 선택한 회원 + 지금 잔액 -->
      <div class="card p-3.5">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0"><div class="text-[16px] font-bold text-ink truncate">{{ picked.nickname || picked.name }} <span class="text-[12px] text-ink-muted font-normal">#{{ picked.id }}</span></div><div class="text-[12px] text-ink-faint truncate">{{ picked.email }}</div></div>
          <button @click="clearPick" class="shrink-0 min-h-[40px] px-3 rounded-lg bg-gray-100 text-[13px] font-bold text-ink-light">다른 회원</button>
        </div>
        <div class="grid grid-cols-3 gap-2 mt-3">
          <div class="bg-amber-50 rounded-xl p-2.5"><div class="text-[11px] text-ink-muted">보유 포인트</div><div class="text-[18px] font-black text-amber-600 tabular-nums">{{ (bal.points ?? 0).toLocaleString() }}P</div></div>
          <div class="bg-violet-50 rounded-xl p-2.5"><div class="text-[11px] text-ink-muted">보유 Entry</div><div class="text-[18px] font-black text-violet-600 tabular-nums">{{ (bal.entries ?? 0).toLocaleString() }}</div></div>
          <div class="bg-emerald-50 rounded-xl p-2.5"><div class="text-[11px] text-ink-muted">누적 구매(카드·직접)</div><div class="text-[18px] font-black text-emerald-600 tabular-nums">${{ money(bal.spent) }}</div></div>
        </div>
      </div>

      <!-- 포인트 -->
      <div class="card p-3.5 space-y-2">
        <h2 class="text-[15px] font-bold text-ink">포인트 지급 / 차감</h2>
        <div class="grid grid-cols-2 gap-2">
          <div class="grid grid-cols-2 gap-1 bg-gray-100 rounded-xl p-1"><button type="button" @click="pt.sign = 1" class="min-h-[40px] rounded-lg font-bold text-[14px]" :class="pt.sign === 1 ? 'bg-white text-emerald-700 shadow-sm' : 'text-ink-muted'">+ 지급</button><button type="button" @click="pt.sign = -1" class="min-h-[40px] rounded-lg font-bold text-[14px]" :class="pt.sign === -1 ? 'bg-white text-red-600 shadow-sm' : 'text-ink-muted'">− 차감</button></div>
          <input v-model.number="pt.amount" type="number" inputmode="numeric" min="1" max="1000000" placeholder="포인트" class="input-soft px-3 min-h-[46px]" />
        </div>
        <input v-model="pt.reason" type="text" maxlength="90" placeholder="사유 (선택, 포인트 내역에 '관리자 조정: …'으로 남아요)" class="input-soft w-full px-3 min-h-[46px]" />
        <button @click="givePoints" :disabled="busy || !pt.amount" class="w-full min-h-[48px] rounded-xl bg-emerald-500 text-white font-bold disabled:opacity-40">포인트 반영</button>
      </div>

      <!-- Entry -->
      <div class="card p-3.5 space-y-2">
        <h2 class="text-[15px] font-bold text-ink">Entry 지급 / 차감 <span v-if="!isSuper" class="text-[12px] font-normal text-ink-faint">· 최고관리자만</span></h2>
        <template v-if="isSuper">
          <div class="grid grid-cols-2 gap-2">
            <div class="grid grid-cols-2 gap-1 bg-gray-100 rounded-xl p-1"><button type="button" @click="en.sign = 1" class="min-h-[40px] rounded-lg font-bold text-[14px]" :class="en.sign === 1 ? 'bg-white text-emerald-700 shadow-sm' : 'text-ink-muted'">+ 지급</button><button type="button" @click="en.sign = -1" class="min-h-[40px] rounded-lg font-bold text-[14px]" :class="en.sign === -1 ? 'bg-white text-red-600 shadow-sm' : 'text-ink-muted'">− 차감</button></div>
            <input v-model.number="en.amount" type="number" inputmode="numeric" min="1" placeholder="Entry 개수" class="input-soft px-3 min-h-[46px]" />
          </div>
          <input v-model="en.desc" type="text" maxlength="120" placeholder="사유 (꼭 입력 — 예: 리뷰 보상 수동 지급)" class="input-soft w-full px-3 min-h-[46px]" />
          <button @click="giveEntries" :disabled="busy || !en.amount || en.desc.trim().length < 2" class="w-full min-h-[48px] rounded-xl bg-violet-500 text-white font-bold disabled:opacity-40">Entry 반영</button>
        </template>
      </div>

      <!-- 실제 구매 금액 -->
      <div class="card p-3.5 space-y-2">
        <h2 class="text-[15px] font-bold text-ink">실제 받은 구매 금액 기록 <span v-if="!isSuper" class="text-[12px] font-normal text-ink-faint">· 최고관리자만</span></h2>
        <p class="text-[12px] text-ink-muted leading-relaxed">카드가 아닌 방법(Zelle·현금·수표)으로 <b>이미 받은 돈</b>을 기록해요. 매출에 잡히고 <b>인보이스가 자동 발행</b>돼요. 새 광고·상위노출을 대신 열어 주지는 않아요(그건 해당 관리 화면에서). 비밀번호 재확인이 필요해요.</p>
        <template v-if="isSuper">
          <div class="grid grid-cols-2 gap-2">
            <select v-model="mp.kind" class="input-soft px-3 min-h-[46px]"><option v-for="k in kinds" :key="k.v" :value="k.v">{{ k.l }}</option></select>
            <div class="flex items-center gap-1"><span class="text-ink-muted font-bold">$</span><input v-model.number="mp.amount" type="number" inputmode="decimal" min="0.5" step="0.01" placeholder="받은 금액" class="input-soft w-full px-3 min-h-[46px]" /></div>
          </div>
          <input v-model="mp.description" type="text" maxlength="150" placeholder="무엇을 구매했나요? (예: 배너 광고 30일 — 홈 왼쪽 프리미엄)" class="input-soft w-full px-3 min-h-[46px]" />
          <div class="grid grid-cols-2 gap-2">
            <select v-model="mp.method" class="input-soft px-3 min-h-[46px]"><option value="zelle">Zelle</option><option value="cash">현금</option><option value="check">수표</option><option value="other">기타</option></select>
            <input v-model="mp.memo" type="text" maxlength="120" placeholder="메모 (선택, 예: 확인번호)" class="input-soft px-3 min-h-[46px]" />
          </div>
          <button @click="recordPayment" :disabled="busy || !mp.amount || mp.description.trim().length < 2" class="w-full min-h-[48px] rounded-xl bg-amber-500 text-white font-bold disabled:opacity-40">구매 금액 기록 + 인보이스 발행</button>
          <p v-if="lastInvoice" class="text-[13px] text-emerald-700">기록했어요. <button @click="openInvoice(lastInvoice.payment_id)" class="underline font-bold">정식 인보이스 열기 {{ lastInvoice.invoice_no || '' }}</button></p>
        </template>
      </div>
    </template>

    <div class="flex flex-wrap gap-2 text-[13px]">
      <RouterLink to="/admin/payments?tab=revenue" class="px-3 min-h-[40px] rounded-lg bg-white border border-gray-200 font-bold text-ink-light flex items-center">광고·결제 매출 보기 ›</RouterLink>
      <RouterLink to="/admin/sweepstakes?tab=pending" class="px-3 min-h-[40px] rounded-lg bg-white border border-gray-200 font-bold text-ink-light flex items-center">경품 발송 관리 ›</RouterLink>
      <RouterLink to="/admin/audit-log" class="px-3 min-h-[40px] rounded-lg bg-white border border-gray-200 font-bold text-ink-light flex items-center">감사 로그 ›</RouterLink>
    </div>
  </div>

  <!-- ───────── 보상 승인 (예전 '신고·문의 › 보상 승인' 화면을 여기로 합침) ───────── -->
  <div v-else-if="section === 'rewards'"><AdminRewardRequests /></div>

  <!-- ───────── 지급 기록 (한 줄씩 쭉, 누르면 자세히) ───────── -->
  <div v-else class="space-y-2">
    <p v-if="logError" class="bg-red-50 text-red-600 text-sm rounded-xl p-3">{{ logError }}</p>
    <div class="flex items-center gap-1.5 flex-wrap">
      <button v-for="c in logChips" :key="c.k" @click="logFilter = c.k" class="min-h-[36px] px-3 rounded-full border text-[13px]" :class="logFilter === c.k ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200'">{{ c.l }} <span class="tabular-nums opacity-70">{{ c.n }}</span></button>
      <button @click="loadLog" class="ml-auto min-h-[36px] px-3 text-[13px] font-bold text-ink-light underline">새로고침</button>
    </div>
    <div class="card overflow-hidden">
      <div class="hidden sm:grid grid-cols-[120px_80px_1fr_1.6fr_110px] gap-2 px-3 py-2 bg-gray-50 border-b border-gray-100 text-[11px] font-bold text-ink-faint"><span>시각(애틀랜타)</span><span>구분</span><span>회원</span><span>내용</span><span class="text-right">금액</span></div>
      <button v-for="r in logShown" :key="r.kind + r.id" @click="openLog(r)" class="w-full text-left px-3 py-2 min-h-[44px] border-b border-gray-50 last:border-0 hover:bg-amber-50/50 active:bg-amber-50 grid grid-cols-[1fr_auto] sm:grid-cols-[120px_80px_1fr_1.6fr_110px] gap-x-2 gap-y-0.5 items-center text-[13px]">
        <span class="hidden sm:block text-[12px] text-ink-muted tabular-nums">{{ fmtAtShort(r.at) }}</span>
        <span class="hidden sm:block"><span class="inline-block px-1.5 py-0.5 rounded text-[11px] font-bold" :class="kindChip(r.kind)">{{ kindLabel(r.kind) }}</span></span>
        <span class="min-w-0 truncate font-bold text-ink">{{ r.user_name }}<span class="sm:hidden text-[11px] font-normal text-ink-faint"> · {{ kindLabel(r.kind) }} · {{ fmtAtShort(r.at) }}</span></span>
        <span class="hidden sm:block min-w-0 truncate text-ink-muted">{{ r.label }}</span>
        <b class="text-right tabular-nums" :class="Number(r.amount) < 0 ? 'text-red-600' : 'text-emerald-600'">{{ fmtAmt(r) }}</b>
      </button>
      <p v-if="!logShown.length" class="text-center text-ink-muted py-10 text-[14px]">{{ logLoading ? '불러오는 중...' : '지급 기록이 없어요' }}</p>
    </div>

    <!-- 눌렀을 때 자세히 -->
    <DetailPanel v-model="logOpen" :title="logSel ? (kindLabel(logSel.kind) + ' · ' + logSel.user_name) : ''">
      <div v-if="logSel" class="space-y-3 text-[14px]">
        <div class="grid grid-cols-[88px_1fr] gap-y-2 gap-x-3">
          <span class="text-ink-muted">구분</span><span><span class="inline-block px-1.5 py-0.5 rounded text-[12px] font-bold" :class="kindChip(logSel.kind)">{{ kindLabel(logSel.kind) }}</span></span>
          <span class="text-ink-muted">금액</span><b class="tabular-nums" :class="Number(logSel.amount) < 0 ? 'text-red-600' : 'text-emerald-600'">{{ fmtAmt(logSel) }}</b>
          <span class="text-ink-muted">회원</span><span>{{ logSel.user_name }} <span class="text-ink-faint">#{{ logSel.user_id }}</span></span>
          <template v-if="logSel.email"><span class="text-ink-muted">이메일</span><span class="break-all">{{ logSel.email }}</span></template>
          <span class="text-ink-muted">내용</span><span class="break-words">{{ logSel.label || '—' }}</span>
          <template v-if="logSel.pay_kind"><span class="text-ink-muted">결제 종류</span><span>{{ kinds.find(k => k.v === logSel.pay_kind)?.l || logSel.pay_kind }}</span></template>
          <span class="text-ink-muted">시각</span><span>{{ fmtAt(logSel.at) }}</span>
        </div>
      </div>
      <template #footer>
        <div v-if="logSel" class="flex flex-wrap gap-2">
          <button v-if="logSel.kind === 'payment'" @click="openInvoice(logSel.id)" class="min-h-[46px] px-4 rounded-xl bg-amber-50 text-amber-700 font-bold text-[14px]">정식 인보이스 열기</button>
          <button @click="goMember(logSel.user_id)" class="min-h-[46px] px-4 rounded-xl bg-gray-100 text-ink-light font-bold text-[14px]">이 회원으로 지급하기</button>
        </div>
      </template>
    </DetailPanel>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, computed, inject, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import AdminRewardRequests from './AdminRewardRequests.vue'
import DetailPanel from '../../components/admin/DetailPanel.vue'
import { useAuthStore } from '../../stores/auth'
import { useAdminConfirm } from '../../composables/useAdminConfirm'
import { useLangStore } from '../../stores/lang'
const langStore = useLangStore()   // 인보이스는 보는 사람의 사이트 언어(KO/EN)로 연다

const route = useRoute(); const router = useRouter(); const auth = useAuthStore()
const { confirmAction } = useAdminConfirm()
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const isSuper = computed(() => auth.user?.role === 'super_admin')

const tabs = [{ k: 'give', l: '직접 지급' }, { k: 'rewards', l: '보상 승인' }, { k: 'log', l: '지급 기록' }]
const section = ref(['give', 'rewards', 'log'].includes(String(route.query.section)) ? String(route.query.section) : 'give')
function setSection(k) { section.value = k; router.replace({ query: { ...route.query, section: k } }); if (k === 'log') loadLog() }

const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const busy = ref(false)
const toastMsg = ref(''); const toastErr = ref(false); let tt = null
function say(msg, err = false) { toastMsg.value = msg; toastErr.value = err; clearTimeout(tt); tt = setTimeout(() => { toastMsg.value = '' }, 4200) }
function apiMsg(e, d) { const errs = e.response?.data?.errors; return (errs ? Object.values(errs).flat().join(' ') : e.response?.data?.message) || d }

// ── 회원 찾기 ──
const q = ref(''); const results = ref([]); const searched = ref(false); const picked = ref(null)
const searching = ref(false); const searchError = ref(''); const lastQuery = ref('')
const bal = reactive({ points: 0, entries: 0, spent: 0 })
const roleName = (r) => ({ super_admin: '최고관리자', admin: '관리자', moderator: '운영자', business: '기업', user: '일반' }[r] || r)
// 숫자만 치면(회원 번호) 1글자부터, 글자는 2글자부터 찾는다
const minLen = computed(() => (/^[0-9]+$/.test(q.value.trim()) ? 1 : 2))
let st = null; let seq = 0
function searchSoon() { clearTimeout(st); st = setTimeout(searchNow, 250) }
async function searchNow() {
  clearTimeout(st)
  const term = q.value.trim()
  searchError.value = ''
  if (term.length < minLen.value) { results.value = []; searched.value = false; searching.value = false; return }
  const my = ++seq
  searching.value = true
  try {
    const { data } = await axios.get('/api/admin/users', { params: { search: term, per_page: 10 } })
    if (my !== seq) return   // 더 새로운 검색이 있으면 이 결과는 버림
    results.value = data.data?.data || []; searched.value = true; lastQuery.value = term
  } catch (e) {
    if (my !== seq) return
    results.value = []; searched.value = false
    searchError.value = apiMsg(e, '회원을 찾지 못했어요. 잠시 뒤 다시 시도해 주세요.')
  }
  if (my === seq) searching.value = false
}
async function pick(u) { picked.value = u; results.value = []; q.value = (u.nickname || u.name) + ' (#' + u.id + ')'; await refreshBal() }
function clearPick() { picked.value = null; q.value = ''; results.value = []; searched.value = false; searchError.value = '' }
async function refreshBal() {
  if (!picked.value) return
  try {
    const { data } = await axios.get(`/api/admin/users/${picked.value.id}/detail`)
    const d = data.data || {}
    bal.points = d.user?.points ?? 0; bal.entries = d.user?.entries ?? 0; bal.spent = d.summary?.total_spent_usd ?? 0
  } catch {}
}

// ── 포인트 ──
const pt = reactive({ sign: 1, amount: null, reason: '' })
async function givePoints() {
  const amount = pt.sign * Math.trunc(Number(pt.amount || 0)); if (!amount) return
  const u = picked.value
  if (!await confirmAction({ title: amount > 0 ? '포인트 지급' : '포인트 차감', message: `'${u.nickname || u.name}' 회원에게 ${Math.abs(amount).toLocaleString()}P 를 ${amount > 0 ? '지급' : '차감'}합니다.`, kind: 'money', confirmLabel: amount > 0 ? '지급' : '차감' })) return
  busy.value = true
  try { const { data } = await axios.post(`/api/admin/users/${u.id}/points`, { amount, reason: pt.reason.trim() || undefined }); say(data.message || '반영했어요'); pt.amount = null; pt.reason = ''; await refreshBal() }
  catch (e) { say(apiMsg(e, '포인트를 반영하지 못했어요'), true) }
  busy.value = false
}

// ── Entry ──
const en = reactive({ sign: 1, amount: null, desc: '' })
async function giveEntries() {
  const amount = en.sign * Math.trunc(Number(en.amount || 0)); if (!amount) return
  const u = picked.value
  if (!await confirmAction({ title: amount > 0 ? 'Entry 지급' : 'Entry 차감', message: `'${u.nickname || u.name}' 회원에게 Entry ${Math.abs(amount).toLocaleString()}개를 ${amount > 0 ? '지급' : '차감'}합니다.\n사유: ${en.desc.trim()}`, kind: 'money', confirmLabel: amount > 0 ? '지급' : '차감' })) return
  busy.value = true
  try { await axios.post('/api/admin/entries/adjust', { user_id: u.id, amount, description: en.desc.trim() }); say('Entry 를 반영했어요'); en.amount = null; en.desc = ''; await refreshBal() }
  catch (e) { say(apiMsg(e, 'Entry 를 반영하지 못했어요'), true) }
  busy.value = false
}

// ── 실제 구매 금액 ──
const kinds = [{ v: 'banner', l: '배너 광고' }, { v: 'text_ad', l: '텍스트 광고' }, { v: 'promotion', l: '상위노출' }, { v: 'flyer', l: 'NEW 전면광고' }, { v: 'event_request', l: '경품 이벤트 의뢰' }]
const mp = reactive({ kind: 'banner', amount: null, description: '', method: 'zelle', memo: '' })
const lastInvoice = ref(null)
async function recordPayment() {
  const u = picked.value
  if (!await confirmAction({ title: '구매 금액 기록', message: `'${u.nickname || u.name}' 회원이 ${kinds.find(k => k.v === mp.kind).l}(으)로 $${money(mp.amount)} 를 ${mp.method === 'zelle' ? 'Zelle' : mp.method === 'cash' ? '현금' : mp.method === 'check' ? '수표' : '기타 방법'}로 냈다고 기록합니다. 매출에 잡히고 인보이스가 발행돼요.`, kind: 'danger', confirmLabel: '기록하기' })) return
  busy.value = true
  try {
    const { data } = await axios.post('/api/admin/grants/manual-payment', { user_id: u.id, kind: mp.kind, amount: mp.amount, description: mp.description.trim(), method: mp.method, memo: mp.memo.trim() || undefined })
    say(data.message || '기록했어요'); lastInvoice.value = data.data || null
    mp.amount = null; mp.description = ''; mp.memo = ''; await refreshBal()
  } catch (e) { say(apiMsg(e, '기록하지 못했어요'), true) }
  busy.value = false
}
async function openInvoice(paymentId) {
  const w = window.open('', '_blank'); if (!w) { say('팝업을 허용해 주세요', true); return }
  try { const { data } = await axios.get(`/api/admin/payments/${paymentId}/invoice`, { params: { lang: langStore.locale }, responseType: 'text', transformResponse: [(d) => d] }); w.document.open(); w.document.write(data); w.document.close() }
  catch { w.close(); say('인보이스를 열지 못했어요', true) }
}

// ── 지급 기록 ──
const log = ref([]); const logLoading = ref(false); const logError = ref('')
async function loadLog() {
  logLoading.value = true; logError.value = ''
  try { const { data } = await axios.get('/api/admin/grants/recent'); log.value = data.data || [] } catch (e) { logError.value = apiMsg(e, '기록을 불러오지 못했어요') }
  logLoading.value = false
}
const logFilter = ref('all'); const logOpen = ref(false); const logSel = ref(null)
const kindLabel = (k) => ({ points: '포인트', entries: 'Entry', payment: '구매 금액' }[k] || k)
const kindChip = (k) => ({ points: 'bg-amber-50 text-amber-700', entries: 'bg-violet-50 text-violet-700', payment: 'bg-emerald-50 text-emerald-700' }[k] || 'bg-gray-100 text-ink-muted')
const logChips = computed(() => [{ k: 'all', l: '전체', n: log.value.length }, ...['points', 'entries', 'payment'].map(k => ({ k, l: kindLabel(k), n: log.value.filter(r => r.kind === k).length }))])
const logShown = computed(() => logFilter.value === 'all' ? log.value : log.value.filter(r => r.kind === logFilter.value))
function openLog(r) { logSel.value = r; logOpen.value = true }
async function goMember(uid) {
  logOpen.value = false; setSection('give')
  try { const { data } = await axios.get('/api/admin/users', { params: { search: String(uid), per_page: 5 } }); const u = (data.data?.data || []).find(x => x.id === uid); if (u) await pick(u) } catch {}
}
function fmtAtShort(s) { if (!s) return ''; const d = new Date(s); return isNaN(d) ? String(s) : d.toLocaleString('en-US', { timeZone: 'America/New_York', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }) }
const fmtAmt = (r) => r.kind === 'payment' ? '$' + money(r.amount) : ((Number(r.amount) > 0 ? '+' : '') + Number(r.amount).toLocaleString() + (r.kind === 'points' ? 'P' : ''))
function fmtAt(s) { if (!s) return ''; const d = new Date(s); return isNaN(d) ? String(s) : d.toLocaleString('ko-KR', { timeZone: 'America/New_York', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }) + ' (애틀랜타)' }

// ── 보상 승인 대기 건수 (탭 배지) ──
const pendingRewards = ref(0)
async function loadPending() { try { const { data } = await axios.get('/api/admin/todo-counts'); pendingRewards.value = data.data?.event_proofs_pending || 0 } catch {} }

onMounted(async () => {
  loadPending()
  if (section.value === 'log') loadLog()
  const uid = Number(route.query.user)
  if (uid) { try { const { data } = await axios.get('/api/admin/users', { params: { search: String(uid), per_page: 5 } }); const u = (data.data?.data || []).find(x => x.id === uid); if (u) await pick(u) } catch {} }
})
watch(() => route.query.section, (v) => { if (['give', 'rewards', 'log'].includes(String(v)) && v !== section.value) section.value = String(v) })
</script>

<style>
.ag-m input:not([type=checkbox]), .ag-m select { font-size: 16px; }
</style>
