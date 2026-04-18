<template>
  <!-- /admin/v2/dashboard (Phase 2-C 묶음 9 + Post Charts) -->
  <div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
      <h2 class="text-xl font-bold">📊 통계 대시보드</h2>
      <div class="flex items-center gap-2">
        <select v-model="days" @change="load()" class="px-3 py-1 border rounded text-sm">
          <option value="7">최근 7일</option>
          <option value="30">최근 30일</option>
          <option value="90">최근 90일</option>
        </select>
        <button @click="exportCsv" :disabled="!kpi?.series?.length" class="px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white rounded text-xs font-semibold disabled:opacity-50">
          📥 CSV Export
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-sm text-gray-400 p-6 text-center">로딩 중...</div>

    <!-- KPI 카드 -->
    <div v-if="kpi" class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div class="bg-white rounded-xl shadow-sm p-4">
        <p class="text-xs text-gray-500">총 회원</p>
        <p class="text-2xl font-bold">{{ kpi.summary.total_users.toLocaleString() }}</p>
      </div>
      <div class="bg-white rounded-xl shadow-sm p-4">
        <p class="text-xs text-gray-500">DAU 평균</p>
        <p class="text-2xl font-bold">{{ kpi.summary.dau_avg }}</p>
      </div>
      <div class="bg-white rounded-xl shadow-sm p-4">
        <p class="text-xs text-gray-500">신규 가입</p>
        <p class="text-2xl font-bold">{{ kpi.summary.new_users_sum }}</p>
      </div>
      <div class="bg-white rounded-xl shadow-sm p-4">
        <p class="text-xs text-gray-500">매출</p>
        <p class="text-2xl font-bold">${{ kpi.summary.revenue_sum.toFixed(2) }}</p>
      </div>
      <div class="bg-white rounded-xl shadow-sm p-4">
        <p class="text-xs text-gray-500">게시글</p>
        <p class="text-2xl font-bold">{{ kpi.summary.posts_sum }}</p>
      </div>
      <div class="bg-white rounded-xl shadow-sm p-4">
        <p class="text-xs text-gray-500">댓글</p>
        <p class="text-2xl font-bold">{{ kpi.summary.comments_sum }}</p>
      </div>
      <div class="bg-white rounded-xl shadow-sm p-4">
        <p class="text-xs text-gray-500">결제 건수</p>
        <p class="text-2xl font-bold">{{ kpi.summary.payments_sum }}</p>
      </div>
      <div class="bg-white rounded-xl shadow-sm p-4">
        <p class="text-xs text-gray-500">신고</p>
        <p class="text-2xl font-bold">{{ kpi.summary.reports_sum }}</p>
      </div>
    </div>

    <!-- 유저 성장 라인 차트 -->
    <div v-if="kpi?.series?.length" class="bg-white rounded-xl shadow-sm p-4">
      <h3 class="font-semibold mb-3">📈 유저·콘텐츠 추이 (최근 {{ days }}일)</h3>
      <KpiLineChart :series="kpi.series" />
    </div>

    <!-- 매출 바 차트 -->
    <div v-if="kpi?.series?.length && kpi.summary.revenue_sum > 0" class="bg-white rounded-xl shadow-sm p-4">
      <h3 class="font-semibold mb-3">💰 일별 매출</h3>
      <RevenueBarChart :series="kpi.series" />
    </div>

    <!-- 시계열 간이 테이블 -->
    <div v-if="kpi?.series?.length" class="bg-white rounded-xl shadow-sm p-4">
      <h3 class="font-semibold mb-3">📅 일별 지표 (최근 {{ days }}일)</h3>
      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead class="bg-gray-50 text-gray-500">
            <tr>
              <th class="px-2 py-1 text-left">날짜</th>
              <th class="px-2 py-1">DAU</th>
              <th class="px-2 py-1">신규</th>
              <th class="px-2 py-1">글</th>
              <th class="px-2 py-1">댓글</th>
              <th class="px-2 py-1">장터</th>
              <th class="px-2 py-1">부동산</th>
              <th class="px-2 py-1">구인</th>
              <th class="px-2 py-1">매출</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in kpi.series" :key="r.id" class="border-t">
              <td class="px-2 py-1 font-mono">{{ r.date }}</td>
              <td class="px-2 py-1 text-center">{{ r.dau }}</td>
              <td class="px-2 py-1 text-center">{{ r.new_users }}</td>
              <td class="px-2 py-1 text-center">{{ r.posts_count }}</td>
              <td class="px-2 py-1 text-center">{{ r.comments_count }}</td>
              <td class="px-2 py-1 text-center">{{ r.market_items_count }}</td>
              <td class="px-2 py-1 text-center">{{ r.real_estate_count }}</td>
              <td class="px-2 py-1 text-center">{{ r.jobs_count }}</td>
              <td class="px-2 py-1 text-right">${{ Number(r.revenue_usd).toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="text-xs text-gray-400 mt-2">💡 kpi:daily 커맨드를 cron 으로 매일 실행 시 자동 누적 (기본: 01:05).</p>
    </div>

    <!-- Funnel -->
    <div v-if="funnel" class="bg-white rounded-xl shadow-sm p-4">
      <h3 class="font-semibold mb-3">🔽 Funnel (최근 {{ funnel.days }}일)</h3>
      <div class="space-y-2">
        <div v-for="stage in funnel.stages" :key="stage.name" class="flex items-center gap-3">
          <div class="w-20 text-xs">{{ stage.name }}</div>
          <div class="flex-1 bg-gray-100 rounded h-6 overflow-hidden">
            <div class="bg-amber-400 h-full flex items-center px-2 text-xs text-white font-semibold" :style="{width: stage.pct + '%'}">
              {{ stage.count }} ({{ stage.pct }}%)
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Top users -->
    <div v-if="topPayers?.length" class="bg-white rounded-xl shadow-sm p-4">
      <h3 class="font-semibold mb-3">💰 상위 결제자 Top 10</h3>
      <table class="w-full text-sm">
        <tbody>
          <tr v-for="(u, i) in topPayers" :key="u.id" class="border-t">
            <td class="px-2 py-2 text-gray-400 w-8">{{ i + 1 }}</td>
            <td class="px-2 py-2">{{ u.nickname || u.email }}</td>
            <td class="px-2 py-2 text-right text-xs text-gray-500">{{ u.c }}회</td>
            <td class="px-2 py-2 text-right font-semibold">${{ Number(u.total).toFixed(2) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import KpiLineChart from '../../../components/admin/KpiLineChart.vue'
import RevenueBarChart from '../../../components/admin/RevenueBarChart.vue'

const loading = ref(true)
const days = ref(30)
const kpi = ref(null)
const funnel = ref(null)
const topPayers = ref([])

async function load() {
  loading.value = true
  try {
    const to = new Date()
    const from = new Date(to.getTime() - (days.value - 1) * 86400000)
    const fmt = d => d.toISOString().split('T')[0]
    const [k, f, t] = await Promise.all([
      axios.get(`/api/admin/analytics/kpi?from=${fmt(from)}&to=${fmt(to)}`),
      axios.get(`/api/admin/analytics/funnel?days=${days.value}`),
      axios.get(`/api/admin/analytics/top-users?metric=payment_amount&limit=10`),
    ])
    kpi.value = k.data.data
    funnel.value = f.data.data
    topPayers.value = t.data.data
  } catch (e) {
    console.error('analytics load failed', e)
  } finally {
    loading.value = false
  }
}

function exportCsv() {
  if (!kpi.value?.series?.length) return
  const cols = ['date', 'total_users', 'new_users', 'dau', 'mau', 'posts_count', 'comments_count', 'market_items_count', 'real_estate_count', 'jobs_count', 'payments_count', 'revenue_usd', 'reports_count']
  const header = cols.join(',')
  const rows = kpi.value.series.map(r => cols.map(c => JSON.stringify(r[c] ?? '')).join(','))
  const csv = '\uFEFF' + [header, ...rows].join('\n')  // BOM for Excel UTF-8
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `kpi_${kpi.value.from}_${kpi.value.to}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

onMounted(load)
</script>
