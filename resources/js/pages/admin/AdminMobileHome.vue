<template>
<div class="space-y-3">
  <div class="flex items-center justify-between px-0.5">
    <span class="text-[13px] font-bold text-ink-muted">오늘 할 일<span v-if="loaded && counts && counts.total" class="ml-1.5 text-red-600">총 {{ counts.total }}건</span></span>
    <button @click="load" class="min-h-[36px] px-2 text-[13px] font-bold text-ink-light underline" aria-label="새로고침">새로고침</button>
  </div>
  <div v-if="loadError" class="bg-white border border-red-100 rounded-2xl p-3.5 text-[14px] text-red-600">건수를 불러오지 못했어요. <button @click="load" class="font-bold underline">다시 시도</button></div>
  <RouterLink v-for="c in cards" :key="c.key" :to="c.to" class="flex items-center gap-3 bg-white border border-gray-100 rounded-2xl p-3.5 min-h-[64px] active:bg-amber-50">
    <span class="text-2xl font-black min-w-[44px] text-center tabular-nums" :class="c.n > 0 ? c.tone : 'text-ink-faint'">{{ loaded ? c.n : '–' }}</span>
    <span class="min-w-0"><span class="block text-[15px] font-bold text-ink">{{ c.label }}</span><span class="block text-[13px] text-ink-muted">{{ c.sub }}</span></span>
  </RouterLink>
  <RouterLink v-if="todoCount !== null" to="/admin/todos" class="flex items-center gap-3 bg-white border border-gray-100 rounded-2xl p-3.5 min-h-[64px] active:bg-amber-50">
    <span class="text-2xl font-black min-w-[44px] text-center tabular-nums text-amber-600">{{ todoCount }}</span>
    <span class="min-w-0"><span class="block text-[15px] font-bold text-ink">남은 할 일</span><span class="block text-[13px] text-ink-muted">할 일 목록 열기</span></span>
  </RouterLink>

  <div class="text-[13px] font-bold text-ink-muted px-0.5 pt-1">자주 쓰는 메뉴</div>
  <div class="grid grid-cols-2 gap-2.5">
    <RouterLink v-for="m in quick" :key="m.to" :to="m.to"
      class="relative flex flex-col justify-between gap-2.5 min-h-[84px] p-3 rounded-2xl bg-white border border-gray-100 active:bg-amber-50">
      <span class="w-9 h-9 rounded-xl grid place-items-center bg-amber-50 text-amber-600"><AppIcon :name="m.icon" :size="20" /></span>
      <span class="text-[15px] font-bold text-ink leading-tight">{{ m.label }}</span>
      <span v-if="m.isNew" class="absolute top-2.5 right-2.5 text-[11px] font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">NEW</span>
    </RouterLink>
  </div>

  <RouterLink to="/admin/overview" class="flex items-center justify-between bg-white border border-gray-100 rounded-2xl px-4 min-h-[52px] text-[15px] font-bold text-ink active:bg-amber-50">
    <span>종합 리포트 (회원·매출·포인트)</span><AppIcon name="chevron-right" :size="18" />
  </RouterLink>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const isSuper = computed(() => auth.user?.role === 'super_admin')

const loaded = ref(false)
const loadError = ref(false)
const counts = ref(null)
const todoCount = ref(null)   // 최고 관리자만 볼 수 있어서 못 불러오면 카드를 숨김

// 건수가 null 인 항목은 이 등급에게 보이지 않는 항목이라 카드를 만들지 않는다
const cards = computed(() => {
  const c = counts.value || {}
  const defs = [
    { key: 'reports', n: c.reports_pending, label: '처리 안 된 신고', sub: '보안/신고에서 확인', to: '/admin/security', tone: 'text-red-600' },
    { key: 'banners', n: c.banners_pending, label: '승인 대기 광고', sub: '광고 목록에서 승인', to: '/admin/banners', tone: 'text-amber-600' },
    { key: 'flyers', n: c.flyers_pending, label: '승인 대기 NEW 전단', sub: 'NEW 전단 광고에서 승인', to: '/admin/flyers', tone: 'text-amber-600' },
    { key: 'ownership', n: c.ownership_pending, label: '업소 소유권 승인 대기', sub: '클레임에서 확인', to: '/admin/claims', tone: 'text-amber-600' },
    { key: 'proofs', n: c.event_proofs_pending, label: '보상 승인 대기', sub: '보상 승인에서 확인', to: '/admin/rewards', tone: 'text-amber-600' },
    { key: 'prizes', n: c.prizes_unsent, label: '아직 안 보낸 경품', sub: '경품 추첨 관리에서 보내기', to: '/admin/sweepstakes', tone: 'text-red-600' },
    { key: 'queue', n: c.queue_failed, label: '실패한 백그라운드 작업', sub: '시스템에서 확인', to: '/admin/system', tone: 'text-red-600' },
  ]
  return defs.filter(d => d.n !== null && d.n !== undefined)
})

async function load() {
  loadError.value = false
  try {
    const { data } = await axios.get('/api/admin/todo-counts')
    counts.value = data.data || {}
  } catch {
    loadError.value = true
  }
  loaded.value = true
  if (isSuper.value) {
    try {
      const { data } = await axios.get('/api/admin/todos')
      todoCount.value = (data.data || []).filter(t => t.status !== 'done').length
    } catch {}
  }
}

const quick = computed(() => [
  { to: '/admin/members', icon: 'users', label: '회원관리' },
  { to: '/admin/payments', icon: 'wallet', label: '결제/오더' },
  { to: '/admin/security', icon: 'lock', label: '보안/신고' },
  { to: '/admin/accounts', icon: 'mail', label: '계정·이메일 현황' },
  ...(isSuper.value ? [
    { to: '/admin/todos', icon: 'list', label: '할 일 목록' },
    { to: '/admin/open-event', icon: 'gift', label: '오픈 이벤트', isNew: true },
    { to: '/admin/analytics', icon: 'chart-bar', label: '방문 분석' },
  ] : []),
])

onMounted(load)
</script>
