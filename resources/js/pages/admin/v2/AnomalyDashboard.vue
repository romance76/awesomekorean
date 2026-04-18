<template>
  <!-- /admin/v2/security/anomaly (Phase 2-C Post - Kay #9) -->
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">⚠️ 이상 활동 대시보드</h2>
      <select v-model.number="hours" @change="load" class="px-3 py-1.5 border rounded text-sm">
        <option :value="1">최근 1시간</option>
        <option :value="6">최근 6시간</option>
        <option :value="24">최근 24시간</option>
        <option :value="168">최근 7일</option>
      </select>
    </div>

    <div v-if="loading" class="text-sm text-gray-400 p-6 text-center">로딩 중...</div>

    <!-- KPI 카드 그리드 -->
    <div v-if="data?.signals" class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div v-for="(signal, key) in data.signals" :key="key" :class="cardClass(signal.level)">
        <p class="text-xs opacity-75">{{ signal.label }}</p>
        <p class="text-2xl font-bold my-1">{{ signal.count?.toLocaleString() ?? '-' }}</p>
        <p v-if="signal.rate !== undefined" class="text-xs opacity-75">{{ signal.rate }}%</p>
      </div>
    </div>

    <!-- 상세 섹션들 -->
    <div v-if="data?.signals?.bulk_signups?.suspicious_domains?.length" class="bg-white rounded-xl shadow-sm p-4">
      <h3 class="font-semibold mb-2">🤖 의심 도메인 (대량 가입)</h3>
      <table class="w-full text-sm">
        <tbody>
          <tr v-for="d in data.signals.bulk_signups.suspicious_domains" :key="d.domain" class="border-t">
            <td class="px-3 py-2 font-mono">{{ d.domain }}</td>
            <td class="px-3 py-2 text-right font-bold text-amber-600">{{ d.c }}건</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="data?.signals?.mass_reports?.repeated_targets?.length" class="bg-white rounded-xl shadow-sm p-4">
      <h3 class="font-semibold mb-2">🚨 중복 신고 대상</h3>
      <table class="w-full text-sm">
        <thead class="text-xs text-gray-500 uppercase bg-gray-50">
          <tr><th class="px-3 py-2 text-left">대상 유형</th><th class="px-3 py-2 text-left">ID</th><th class="px-3 py-2 text-right">신고 수</th></tr>
        </thead>
        <tbody>
          <tr v-for="t in data.signals.mass_reports.repeated_targets" :key="t.reportable_type + '-' + t.reportable_id" class="border-t">
            <td class="px-3 py-2 text-xs">{{ shortType(t.reportable_type) }}</td>
            <td class="px-3 py-2 font-mono text-xs">#{{ t.reportable_id }}</td>
            <td class="px-3 py-2 text-right text-red-600 font-bold">{{ t.c }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="data?.signals?.suspicious_ips?.ips?.length" class="bg-white rounded-xl shadow-sm p-4">
      <h3 class="font-semibold mb-2">🕵️ 의심 IP (다중 계정 시도)</h3>
      <table class="w-full text-sm">
        <thead class="text-xs text-gray-500 uppercase bg-gray-50">
          <tr><th class="px-3 py-2 text-left">IP</th><th class="px-3 py-2 text-right">시도한 이메일</th><th class="px-3 py-2 text-right">총 시도</th></tr>
        </thead>
        <tbody>
          <tr v-for="ip in data.signals.suspicious_ips.ips" :key="ip.ip" class="border-t">
            <td class="px-3 py-2 font-mono text-xs">{{ ip.ip }}</td>
            <td class="px-3 py-2 text-right">{{ ip.unique_emails }}</td>
            <td class="px-3 py-2 text-right text-red-600 font-bold">{{ ip.attempts }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="data?.signals?.new_post_spam?.top_posters?.length" class="bg-white rounded-xl shadow-sm p-4">
      <h3 class="font-semibold mb-2">📝 과다 게시 유저 (스팸 의심)</h3>
      <table class="w-full text-sm">
        <tbody>
          <tr v-for="u in data.signals.new_post_spam.top_posters" :key="u.user_id" class="border-t">
            <td class="px-3 py-2">유저 #{{ u.user_id }}</td>
            <td class="px-3 py-2 text-right font-bold text-amber-600">{{ u.c }}개</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
const data = ref(null)
const loading = ref(true)
const hours = ref(24)

const shortType = (t) => (t || '').split('\\').pop().toLowerCase()

const cardClass = (level) => {
  const base = 'p-4 rounded-xl shadow-sm '
  return base + ({
    critical: 'bg-red-500 text-white',
    warning: 'bg-amber-500 text-white',
    info: 'bg-white',
  }[level] || 'bg-white')
}

async function load() {
  loading.value = true
  try {
    const { data: res } = await axios.get(`/api/admin/anomaly/overview?hours=${hours.value}`)
    data.value = res.data
  } finally { loading.value = false }
}
onMounted(load)
</script>
