<template>
  <!-- /admin/v2/security/login-logs (Phase 2-C 묶음 4 신규) -->
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">🔒 로그인 실패 모니터링</h2>
      <select v-model.number="hours" @change="load" class="px-3 py-1 border rounded text-sm">
        <option :value="1">최근 1시간</option>
        <option :value="6">최근 6시간</option>
        <option :value="24">최근 24시간</option>
        <option :value="168">최근 7일</option>
      </select>
    </div>

    <div v-if="loading" class="text-sm text-gray-400 p-6 text-center">로딩 중...</div>

    <!-- IP 별 집계 -->
    <div v-if="data?.by_ip" class="bg-white rounded-xl shadow-sm p-4">
      <h3 class="font-semibold mb-3">🚨 IP별 실패 시도 Top 20</h3>
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
          <tr><th class="px-3 py-2 text-left">IP</th><th class="px-3 py-2 text-right">시도 수</th><th class="px-3 py-2 text-left">마지막 시도</th><th class="px-3 py-2 text-center">액션</th></tr>
        </thead>
        <tbody>
          <tr v-for="row in data.by_ip" :key="row.ip" class="border-t">
            <td class="px-3 py-2 font-mono text-xs">{{ row.ip }}</td>
            <td class="px-3 py-2 text-right">
              <span :class="['px-2 py-0.5 rounded text-xs font-semibold', row.attempts >= 10 ? 'bg-red-100 text-red-700' : row.attempts >= 5 ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600']">
                {{ row.attempts }}
              </span>
            </td>
            <td class="px-3 py-2 text-xs text-gray-500">{{ fmtDate(row.last_attempt) }}</td>
            <td class="px-3 py-2 text-center">
              <button @click="banIp(row.ip)" class="text-xs px-2 py-1 bg-red-100 text-red-700 hover:bg-red-200 rounded">🚫 IP 차단</button>
            </td>
          </tr>
          <tr v-if="!data.by_ip.length"><td colspan="4" class="p-6 text-center text-gray-400">실패 로그 없음</td></tr>
        </tbody>
      </table>
    </div>

    <!-- 최근 실패 -->
    <div v-if="data?.recent" class="bg-white rounded-xl shadow-sm p-4">
      <h3 class="font-semibold mb-3">📋 최근 실패 이벤트 (50)</h3>
      <ul class="divide-y text-xs">
        <li v-for="r in data.recent" :key="r.id" class="py-2 flex items-center justify-between gap-2">
          <div class="flex-1">
            <p><span class="font-mono">{{ r.ip }}</span> <span class="text-gray-500">{{ r.email || '(이메일 미상)' }}</span></p>
            <p class="text-gray-400">{{ fmtDate(r.created_at) }} · {{ r.device }} · {{ r.failure_reason }}</p>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const hours = ref(24)
const data = ref(null)
const loading = ref(true)

const fmtDate = (s) => s ? new Date(s).toLocaleString('ko-KR') : ''

async function load() {
  loading.value = true
  try {
    const res = await axios.get(`/api/admin/security/failed-logins?hours=${hours.value}`)
    data.value = res.data.data
  } finally { loading.value = false }
}

async function banIp(ip) {
  if (!confirm(`${ip} 를 영구 차단하시겠습니까?`)) return
  try {
    await axios.post('/api/admin/ip-bans', { ip, reason: 'Brute force login attempts' })
    alert('차단되었습니다')
  } catch (e) {
    alert('차단 실패: ' + (e.response?.data?.message || e.message))
  }
}

onMounted(load)
</script>
