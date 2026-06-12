<template>
<div>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="chart-bar" :size="20" /></span>
    관리자 대시보드 — 종합 리포트
  </h1>

  <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
  <div v-else-if="report">

    <!-- 상단 KPI -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
      <RouterLink to="/admin/members" class="card card-hover p-4">
        <div class="text-2xl font-black text-amber-600">{{ report.users.total.toLocaleString() }}</div>
        <div class="text-xs text-ink-muted mt-1">전체 회원</div>
        <div class="text-[11px] text-green-600 mt-1">+오늘 {{ report.users.new_today }} · 이번주 {{ report.users.new_week }}</div>
      </RouterLink>
      <RouterLink to="/admin/payments" class="card card-hover p-4">
        <div class="text-2xl font-black text-blue-600">${{ Number(report.payments.total_revenue).toLocaleString() }}</div>
        <div class="text-xs text-ink-muted mt-1">누적 매출</div>
        <div class="text-[11px] text-blue-600 mt-1">이달 ${{ Number(report.payments.month_revenue).toLocaleString() }} · 오늘 ${{ Number(report.payments.today_revenue).toLocaleString() }}</div>
      </RouterLink>
      <RouterLink to="/admin/banners" class="card card-hover p-4">
        <div class="text-2xl font-black text-purple-600">{{ report.banners.active }}</div>
        <div class="text-xs text-ink-muted mt-1">활성 광고</div>
        <div class="text-[11px] text-orange-600 mt-1">대기 {{ report.banners.pending }} · {{ Number(report.banners.total_revenue).toLocaleString() }}P</div>
      </RouterLink>
      <RouterLink to="/admin/security" class="card card-hover p-4">
        <div class="text-2xl font-black text-red-600">{{ pendingReports }}</div>
        <div class="text-xs text-ink-muted mt-1">대기 신고</div>
        <div class="text-[11px] text-ink-muted mt-1">정지 회원 {{ report.users.banned }}</div>
      </RouterLink>
    </div>

    <!-- 포인트 이코노미 -->
    <div class="card p-4 mb-4">
      <div class="flex items-center gap-2 font-bold text-sm text-ink mb-3">
        <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="coins" :size="14" /></span>포인트 이코노미
      </div>
      <div class="grid grid-cols-4 gap-3 text-center">
        <div><div class="text-lg font-bold text-green-600">+{{ Number(report.points.total_issued).toLocaleString() }}</div><div class="text-xs text-ink-muted">누적 적립</div></div>
        <div><div class="text-lg font-bold text-red-600">-{{ Number(report.points.total_spent).toLocaleString() }}</div><div class="text-xs text-ink-muted">누적 소비</div></div>
        <div><div class="text-lg font-bold text-green-500">+{{ Number(report.points.today_issued).toLocaleString() }}</div><div class="text-xs text-ink-muted">오늘 적립</div></div>
        <div><div class="text-lg font-bold text-red-500">-{{ Number(report.points.today_spent).toLocaleString() }}</div><div class="text-xs text-ink-muted">오늘 소비</div></div>
      </div>
    </div>

    <!-- 게시판별 현황 -->
    <div class="card p-4 mb-4">
      <div class="flex items-center gap-2 font-bold text-sm text-ink mb-3">
        <span class="icon-chip w-7 h-7 bg-emerald-50 text-emerald-600"><AppIcon name="list" :size="14" /></span>게시판별 현황 (클릭하여 관리)
      </div>
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2">
        <RouterLink v-for="b in report.boards" :key="b.slug" :to="`/admin/${b.slug}`"
          class="border border-gray-100 rounded-xl p-3 hover:bg-amber-50 hover:border-amber-200 transition-colors">
          <div class="flex items-center justify-between">
            <div class="text-lg">{{ b.icon }}</div>
            <span v-if="b.reports > 0" class="bg-red-500 text-white text-[11px] font-bold px-1.5 rounded-full">{{ b.reports }}</span>
          </div>
          <div class="text-sm font-bold text-ink mt-1">{{ b.label }}</div>
          <div class="text-xs text-ink-muted">{{ b.total.toLocaleString() }}건</div>
          <div class="text-[11px] text-blue-600">오늘 +{{ b.today }} · 주 +{{ b.week }}</div>
        </RouterLink>
      </div>
    </div>

    <!-- 결제/주문 + 신고 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4">
      <!-- 최근 결제 -->
      <div class="card p-4">
        <div class="flex justify-between items-center mb-3">
          <div class="flex items-center gap-2 font-bold text-sm text-ink">
            <span class="icon-chip w-7 h-7 bg-blue-50 text-blue-600"><AppIcon name="wallet" :size="14" /></span>최근 결제/주문
          </div>
          <RouterLink to="/admin/payments" class="text-xs text-amber-600 hover:underline">전체 →</RouterLink>
        </div>
        <div v-if="!report.recent_payments?.length" class="text-center text-ink-muted text-xs py-4">최근 결제 없음</div>
        <div v-else class="divide-y divide-gray-50">
          <div v-for="p in report.recent_payments" :key="p.id" class="py-2 flex items-center justify-between text-xs">
            <div>
              <div class="font-medium text-ink">#{{ p.id }} · {{ p.user?.name || '-' }}</div>
              <div class="text-[11px] text-ink-faint">{{ p.created_at?.slice(0,16).replace('T',' ') }}</div>
            </div>
            <div class="text-right">
              <div class="font-bold text-green-700">${{ p.amount }}</div>
              <div class="text-[11px] text-ink-muted">+{{ p.points_purchased }}P · {{ p.status }}</div>
            </div>
          </div>
        </div>
        <div class="mt-2 pt-2 border-t border-gray-100 text-xs text-ink-muted flex justify-between">
          <span>완료 {{ report.payments.completed }}</span>
          <span>환불 {{ report.payments.refunded }}</span>
          <span>총 {{ report.payments.total_orders }}건</span>
        </div>
      </div>

      <!-- 최근 신고 -->
      <div class="card p-4">
        <div class="flex justify-between items-center mb-3">
          <div class="flex items-center gap-2 font-bold text-sm text-ink">
            <span class="icon-chip w-7 h-7 bg-red-50 text-red-500"><AppIcon name="alert-circle" :size="14" /></span>최근 신고
          </div>
          <RouterLink to="/admin/security" class="text-xs text-amber-600 hover:underline">전체 →</RouterLink>
        </div>
        <div v-if="!report.recent_reports?.length" class="text-center text-ink-muted text-xs py-4">최근 신고 없음</div>
        <div v-else class="divide-y divide-gray-50">
          <div v-for="r in report.recent_reports" :key="r.id" class="py-2 text-xs">
            <div class="flex justify-between">
              <span class="font-medium text-ink">{{ r.reporter?.name || '?' }}</span>
              <span :class="r.status==='pending'?'badge-primary':'badge-gray'">{{ r.status }}</span>
            </div>
            <div class="text-red-600">{{ r.reason }}</div>
            <div class="text-[11px] text-ink-faint">{{ r.reportable_type?.split('\\').pop() }} #{{ r.reportable_id }} · {{ r.created_at?.slice(0,10) }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Top 작성자 -->
    <div class="card p-4">
      <div class="flex items-center gap-2 font-bold text-sm text-ink mb-3">
        <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="trophy" :size="14" /></span>TOP 10 작성자 (커뮤니티)
      </div>
      <div v-if="!report.top_posters?.length" class="text-center text-ink-muted text-xs py-4">데이터 없음</div>
      <div v-else class="grid grid-cols-2 md:grid-cols-5 gap-2">
        <div v-for="(u, i) in report.top_posters" :key="u.id" class="border border-gray-100 rounded-lg p-2 text-xs">
          <div class="flex items-center justify-between">
            <span class="font-bold text-amber-600">#{{ i+1 }}</span>
            <span class="badge-blue !text-[11px]">{{ u.post_count }}개</span>
          </div>
          <div class="font-medium text-ink truncate mt-1">{{ u.name }}</div>
          <div class="text-[11px] text-ink-faint truncate">{{ u.email }}</div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const report = ref(null)
const loading = ref(true)

const pendingReports = computed(() =>
  report.value?.boards?.reduce((sum, b) => sum + (b.reports || 0), 0) || 0
)

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/admin/board-manager/full-report')
    report.value = data.data
  } catch (e) {
    // fallback to old endpoint
    try {
      const { data } = await axios.get('/api/admin/overview')
      report.value = {
        users: { total: data.data.total_users, new_today: 0, new_week: data.data.new_users_week, banned: 0 },
        payments: { total_revenue: 0, month_revenue: 0, today_revenue: 0, completed: 0, refunded: 0, total_orders: 0 },
        banners: { active: 0, pending: 0, total_revenue: 0 },
        points: { total_issued: 0, total_spent: 0, today_issued: 0, today_spent: 0 },
        boards: [], recent_payments: [], recent_reports: [], top_posters: [],
      }
    } catch {}
  }
  loading.value = false
})
</script>
