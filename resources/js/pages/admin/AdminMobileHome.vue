<template>
<div class="space-y-3">
  <div class="text-[13px] font-bold text-ink-muted px-0.5">지금 확인할 것</div>

  <RouterLink to="/admin/security" class="flex items-center gap-3 bg-white border border-gray-100 rounded-2xl p-3.5 min-h-[64px] active:bg-amber-50">
    <span class="text-2xl font-black min-w-[44px] text-center tabular-nums" :class="reports > 0 ? 'text-red-600' : 'text-ink-faint'">{{ loaded ? reports : '–' }}</span>
    <span class="min-w-0"><span class="block text-[15px] font-bold text-ink">처리 안 된 신고</span><span class="block text-[13px] text-ink-muted">보안/신고에서 확인</span></span>
  </RouterLink>

  <RouterLink v-if="todoCount !== null" to="/admin/todos" class="flex items-center gap-3 bg-white border border-gray-100 rounded-2xl p-3.5 min-h-[64px] active:bg-amber-50">
    <span class="text-2xl font-black min-w-[44px] text-center tabular-nums text-amber-600">{{ todoCount }}</span>
    <span class="min-w-0"><span class="block text-[15px] font-bold text-ink">남은 할 일</span><span class="block text-[13px] text-ink-muted">할 일 목록 열기</span></span>
  </RouterLink>

  <RouterLink to="/admin/banners" class="flex items-center gap-3 bg-white border border-gray-100 rounded-2xl p-3.5 min-h-[64px] active:bg-amber-50">
    <span class="text-2xl font-black min-w-[44px] text-center tabular-nums" :class="pendingAds > 0 ? 'text-amber-600' : 'text-ink-faint'">{{ loaded ? pendingAds : '–' }}</span>
    <span class="min-w-0"><span class="block text-[15px] font-bold text-ink">승인 대기 광고</span><span class="block text-[13px] text-ink-muted">광고 목록에서 승인</span></span>
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
const reports = ref(0)
const pendingAds = ref(0)
const todoCount = ref(null)   // 최고 관리자만 볼 수 있어서 못 불러오면 카드를 숨김

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

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/admin/board-manager/full-report')
    const r = data.data || {}
    reports.value = (r.boards || []).reduce((s, b) => s + (b.reports || 0), 0)
    pendingAds.value = r.banners?.pending || 0
  } catch {
    try {
      const { data } = await axios.get('/api/admin/overview')
      reports.value = data.data?.pending_reports || 0
    } catch {}
  }
  loaded.value = true
  if (isSuper.value) {
    try {
      const { data } = await axios.get('/api/admin/todos')
      todoCount.value = (data.data || []).filter(t => t.status !== 'done').length
    } catch {}
  }
})
</script>
