<template>
  <!-- /admin/v2/dashboard (Phase 2-C Post UX) — 시각적 대시보드 홈 -->
  <div class="space-y-4">
    <!-- 상단 환영 + 현재 시간 -->
    <div class="bg-gradient-to-r from-gray-800 via-gray-700 to-amber-600 rounded-xl p-5 text-white">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <p class="text-sm opacity-80">👋 {{ greeting() }}</p>
          <h2 class="text-2xl font-bold">{{ auth.user?.nickname || auth.user?.name || '관리자' }}님</h2>
          <p class="text-xs opacity-75 mt-1">{{ today }} · {{ dayOfWeek }}</p>
        </div>
        <div class="text-right">
          <p class="text-xs opacity-80">시스템 상태</p>
          <p class="text-lg font-bold">{{ healthLabel }}</p>
        </div>
      </div>
    </div>

    <!-- 빠른 작업 -->
    <div class="bg-white rounded-xl shadow-sm p-4">
      <p class="text-xs text-gray-500 font-semibold mb-3">⚡ 빠른 작업</p>
      <div class="grid grid-cols-3 sm:grid-cols-6 gap-3">
        <router-link v-for="q in quickActions" :key="q.to" :to="q.to"
          class="flex flex-col items-center gap-1 p-3 rounded-lg hover:bg-amber-50 transition border border-gray-100 hover:border-amber-300">
          <span class="text-2xl">{{ q.icon }}</span>
          <span class="text-xs text-gray-700 text-center font-semibold">{{ q.label }}</span>
          <span v-if="q.badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full px-1.5 font-bold">{{ q.badge }}</span>
        </router-link>
      </div>
    </div>

    <!-- KPI 카드 요약 -->
    <div v-if="kpi?.summary" class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <router-link to="/admin/v2/users" class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition">
        <div class="flex items-center justify-between mb-1"><span class="text-2xl">👥</span><span class="text-xs text-gray-400">→</span></div>
        <p class="text-xs text-gray-500">총 회원</p>
        <p class="text-2xl font-bold">{{ kpi.summary.total_users?.toLocaleString() ?? 0 }}</p>
      </router-link>
      <router-link to="/admin/v2/analytics/users" class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition">
        <div class="flex items-center justify-between mb-1"><span class="text-2xl">📈</span><span class="text-xs text-gray-400">→</span></div>
        <p class="text-xs text-gray-500">DAU 평균</p>
        <p class="text-2xl font-bold">{{ kpi.summary.dau_avg ?? '?' }}</p>
      </router-link>
      <router-link to="/admin/v2/payments" class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition">
        <div class="flex items-center justify-between mb-1"><span class="text-2xl">💳</span><span class="text-xs text-gray-400">→</span></div>
        <p class="text-xs text-gray-500">매출 (30일)</p>
        <p class="text-2xl font-bold">${{ (kpi.summary.revenue_sum || 0).toFixed(2) }}</p>
      </router-link>
      <router-link to="/admin/v2/security/reports" class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition">
        <div class="flex items-center justify-between mb-1"><span class="text-2xl">🚨</span><span class="text-xs text-gray-400">→</span></div>
        <p class="text-xs text-gray-500">신고 (30일)</p>
        <p class="text-2xl font-bold" :class="kpi.summary.reports_sum > 10 ? 'text-red-600' : ''">{{ kpi.summary.reports_sum ?? 0 }}</p>
      </router-link>
    </div>

    <!-- 좌우 2열: 실시간 알림 피드 + 최근 가입 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <!-- 실시간 알림 -->
      <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center justify-between mb-3">
          <h3 class="font-semibold">🔔 실시간 알림</h3>
          <router-link to="/admin/v2/security/audit" class="text-xs text-amber-600 hover:text-amber-800">전체 →</router-link>
        </div>
        <div v-if="recentAudit.length">
          <ul class="divide-y text-sm">
            <li v-for="r in recentAudit.slice(0, 6)" :key="r.id" class="py-2 flex items-center gap-2">
              <span :class="auditBadge(r.action)" class="text-xs px-1.5 py-0.5 rounded">{{ r.action }}</span>
              <span class="flex-1 text-xs text-gray-600 truncate">{{ r.admin_name || '#' + r.admin_id }}</span>
              <span class="text-xs text-gray-400">{{ fmtRelative(r.created_at) }}</span>
            </li>
          </ul>
        </div>
        <p v-else class="text-sm text-gray-400 text-center py-6">최근 알림 없음</p>
      </div>

      <!-- 최근 가입 -->
      <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center justify-between mb-3">
          <h3 class="font-semibold">🆕 최근 가입 (24시간)</h3>
          <router-link to="/admin/v2/users" class="text-xs text-amber-600 hover:text-amber-800">전체 →</router-link>
        </div>
        <div v-if="recentUsers.length">
          <ul class="divide-y">
            <li v-for="u in recentUsers.slice(0, 6)" :key="u.id" class="py-2 flex items-center gap-2">
              <img :src="u.avatar || '/images/default-avatar.png'" @error="$event.target.src='/images/default-avatar.png'" class="w-8 h-8 rounded-full bg-gray-100" />
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold truncate">{{ u.nickname || u.name }}</p>
                <p class="text-xs text-gray-500 truncate">{{ u.email }}</p>
              </div>
              <span class="text-xs text-gray-400">{{ fmtRelative(u.created_at) }}</span>
            </li>
          </ul>
        </div>
        <p v-else class="text-sm text-gray-400 text-center py-6">최근 24시간 가입 없음</p>
      </div>
    </div>

    <!-- 전체 분석 대시보드 링크 -->
    <div class="bg-gradient-to-r from-amber-50 to-orange-50 rounded-xl p-4 flex items-center justify-between">
      <div>
        <p class="font-semibold">📊 상세 분석·차트 보기</p>
        <p class="text-xs text-gray-500 mt-1">KPI 시계열·Funnel·매출 차트·Top 결제자</p>
      </div>
      <router-link to="/admin/v2/analytics/users" class="px-4 py-2 bg-amber-400 hover:bg-amber-500 text-white rounded-lg text-sm font-semibold whitespace-nowrap">
        분석 대시보드 →
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../../../stores/auth'

const auth = useAuthStore()
const kpi = ref(null)
const recentAudit = ref([])
const recentUsers = ref([])
const health = ref(null)

const today = new Date().toLocaleDateString('ko-KR')
const dayOfWeek = ['일요일','월요일','화요일','수요일','목요일','금요일','토요일'][new Date().getDay()]

const greeting = () => {
  const h = new Date().getHours()
  if (h < 6) return '밤 늦은 시간에 수고 많으십니다 🌙'
  if (h < 12) return '좋은 아침입니다 ☀️'
  if (h < 18) return '오후도 힘내세요 ☕'
  return '늦은 시간 수고 많습니다 🌆'
}

const healthLabel = computed(() => {
  if (!health.value) return '확인 중...'
  return { healthy: '🟢 정상', degraded: '🟡 일부 경고', critical: '🔴 심각' }[health.value.overall] || '확인 중'
})

const quickActions = computed(() => [
  { to: '/admin/v2/users',              icon: '👥', label: '회원 관리' },
  { to: '/admin/v2/security/reports',   icon: '🚨', label: '신고 처리', badge: kpi.value?.summary?.reports_sum > 0 ? kpi.value.summary.reports_sum : null },
  { to: '/admin/v2/content',            icon: '📝', label: '게시글' },
  { to: '/admin/v2/users/point-ops',    icon: '💰', label: '포인트 운영' },
  { to: '/admin/v2/communication/broadcast', icon: '📣', label: '알림 발송' },
  { to: '/admin/v2/security/anomaly',   icon: '⚠️', label: '이상 활동' },
])

const auditBadge = (a) => {
  if (!a) return 'bg-gray-100 text-gray-500'
  if (a.includes('delete') || a.includes('revoke') || a.includes('ban')) return 'bg-red-100 text-red-700'
  if (a.includes('reset')) return 'bg-orange-100 text-orange-700'
  if (a.includes('grant') || a.includes('approve')) return 'bg-green-100 text-green-700'
  return 'bg-gray-100 text-gray-600'
}

const fmtRelative = (s) => {
  if (!s) return ''
  const diff = Date.now() - new Date(s).getTime()
  const min = Math.floor(diff / 60000)
  if (min < 1) return '방금'
  if (min < 60) return `${min}분 전`
  const hr = Math.floor(min / 60)
  if (hr < 24) return `${hr}시간 전`
  return `${Math.floor(hr / 24)}일 전`
}

onMounted(async () => {
  // 병렬 로드
  const promises = [
    axios.get('/api/admin/analytics/kpi?from=' + dateDaysAgo(30) + '&to=' + dateDaysAgo(0)).catch(() => null),
    axios.get('/api/admin/analytics/audit-log').catch(() => null),
    axios.get('/api/admin/users?per_page=10&sort=-created_at').catch(() => null),
    axios.get('/api/admin/system/health').catch(() => null),
  ]
  const [kpiRes, auditRes, usersRes, healthRes] = await Promise.all(promises)
  kpi.value = kpiRes?.data?.data
  recentAudit.value = auditRes?.data?.data || []
  const allUsers = usersRes?.data?.data?.data || usersRes?.data?.data || []
  recentUsers.value = allUsers.slice(0, 10)
  health.value = healthRes?.data?.data
})

function dateDaysAgo(n) {
  const d = new Date()
  d.setDate(d.getDate() - n)
  return d.toISOString().split('T')[0]
}
</script>
