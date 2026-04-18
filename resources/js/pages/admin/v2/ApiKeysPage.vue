<template>
  <!-- /admin/v2/integrations/api-keys (Phase 2-C 묶음 6) -->
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold">🔑 API 키 관리</h2>
        <p class="text-xs text-gray-500">암호화 저장 · 사용량 집계 · 호출 로그 · Kill Switch</p>
      </div>
      <button class="px-3 py-2 bg-amber-400 hover:bg-amber-500 text-white rounded-lg text-sm font-semibold">+ 새 API 키</button>
    </div>

    <!-- 탭 -->
    <div class="flex gap-1 border-b">
      <button
        v-for="t in tabs" :key="t.key"
        @click="activeTab = t.key"
        :class="['px-4 py-2 text-sm font-medium border-b-2', activeTab === t.key ? 'border-amber-400 text-amber-600' : 'border-transparent text-gray-500 hover:text-gray-700']"
      >{{ t.label }}</button>
    </div>

    <!-- 탭 1: 키 목록 -->
    <div v-if="activeTab === 'list'" class="bg-white rounded-xl shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
          <tr>
            <th class="px-4 py-2 text-left">서비스</th>
            <th class="px-4 py-2 text-left">이름</th>
            <th class="px-4 py-2 text-left">키 (마스킹)</th>
            <th class="px-4 py-2 text-center">상태</th>
            <th class="px-4 py-2 text-left">마지막 검증</th>
            <th class="px-4 py-2 text-center">액션</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="k in keys" :key="k.id" class="border-t hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-xs">{{ k.service }}</td>
            <td class="px-4 py-3">{{ k.name }}</td>
            <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ k.api_key_masked }}</td>
            <td class="px-4 py-3 text-center">
              <span v-if="k.is_active && !k.test_mode" class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs">✅ 활성</span>
              <span v-else-if="k.test_mode" class="px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded text-xs">⚠️ 테스트</span>
              <span v-else class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs">❌ 비활성</span>
            </td>
            <td class="px-4 py-3 text-xs text-gray-500">{{ k.last_verified_at ? fmtDate(k.last_verified_at) : '-' }}</td>
            <td class="px-4 py-3 text-center space-x-1">
              <button @click="testKey(k)" class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200">테스트</button>
              <button v-if="auth.can('api.keys.reveal')" @click="revealKey(k)" class="px-2 py-1 text-xs bg-gray-100 rounded hover:bg-gray-200">공개</button>
            </td>
          </tr>
          <tr v-if="!keys.length"><td colspan="6" class="px-4 py-8 text-center text-gray-400">등록된 API 키 없음</td></tr>
        </tbody>
      </table>
    </div>

    <!-- 탭 2: 사용량 -->
    <div v-if="activeTab === 'usage'" class="bg-white rounded-xl shadow-sm p-4">
      <h3 class="font-semibold mb-3">📊 최근 7일 사용량</h3>
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
          <tr><th class="px-3 py-2 text-left">서비스</th><th class="px-3 py-2">날짜</th><th class="px-3 py-2">성공</th><th class="px-3 py-2">에러</th><th class="px-3 py-2">평균(ms)</th></tr>
        </thead>
        <tbody>
          <tr v-for="u in usage" :key="u.id" class="border-t">
            <td class="px-3 py-2 font-mono text-xs">{{ u.service }}</td>
            <td class="px-3 py-2 text-xs">{{ u.date }}</td>
            <td class="px-3 py-2 text-center text-green-600">{{ u.success_count }}</td>
            <td class="px-3 py-2 text-center text-red-600">{{ u.error_count }}</td>
            <td class="px-3 py-2 text-center">{{ u.avg_response_ms }}</td>
          </tr>
          <tr v-if="!usage.length"><td colspan="5" class="px-3 py-6 text-center text-gray-400">집계 데이터 없음 (외부 API 호출 기록 시 누적)</td></tr>
        </tbody>
      </table>
    </div>

    <!-- 탭 3: 로그 -->
    <div v-if="activeTab === 'logs'" class="bg-white rounded-xl shadow-sm p-4">
      <h3 class="font-semibold mb-3">📋 최근 에러·로그</h3>
      <ul class="divide-y text-xs font-mono">
        <li v-for="l in logs" :key="l.id" class="py-2">
          <span :class="['px-2 py-0.5 rounded text-xs mr-2', l.level === 'error' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700']">{{ l.level }}</span>
          <span class="text-gray-500">{{ fmtDate(l.created_at) }}</span>
          <span class="ml-2 font-semibold">{{ l.service }}</span>
          <span class="ml-2">{{ l.message }}</span>
        </li>
        <li v-if="!logs.length" class="py-6 text-center text-gray-400">로그 없음</li>
      </ul>
    </div>

    <!-- 탭 4: Kill Switch -->
    <div v-if="activeTab === 'killswitch'" class="bg-white rounded-xl shadow-sm p-4 space-y-3">
      <h3 class="font-semibold">⚠️ Kill Switch (API 개별 비활성화)</h3>
      <p class="text-xs text-gray-500">비활성 시 해당 API 호출이 전부 차단됩니다. 영향을 반드시 확인 후 실행하세요.</p>
      <div v-for="k in keys" :key="k.id" class="border rounded-lg p-3 flex items-center justify-between">
        <div>
          <p class="font-semibold text-sm">{{ k.name }}</p>
          <p class="text-xs text-gray-500">{{ k.feature_list || k.description || '영향 설명 없음' }}</p>
        </div>
        <button
          @click="toggleActive(k)"
          :class="['px-3 py-1.5 rounded text-xs font-semibold', k.is_active ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-red-100 text-red-700 hover:bg-red-200']"
        >{{ k.is_active ? '✅ 활성' : '❌ 비활성' }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../../../stores/auth'

const auth = useAuthStore()
const tabs = [
  { key: 'list', label: '🔑 키 목록' },
  { key: 'usage', label: '📊 사용량' },
  { key: 'logs', label: '📋 로그' },
  { key: 'killswitch', label: '⚠️ Kill Switch' },
]
const activeTab = ref('list')
const keys = ref([])
const usage = ref([])
const logs = ref([])

const fmtDate = (s) => s ? new Date(s).toLocaleString('ko-KR') : '-'

async function loadKeys() {
  const { data } = await axios.get('/api/admin/api-keys')
  keys.value = data.data || []
}
async function loadUsage() {
  const { data } = await axios.get('/api/admin/api-keys/usage?days=7')
  usage.value = data.data || []
}
async function loadLogs() {
  const { data } = await axios.get('/api/admin/api-keys/logs')
  logs.value = data.data || []
}

async function testKey(k) {
  try {
    const { data } = await axios.post(`/api/admin/api-keys/${k.id}/test`)
    alert(`서비스: ${data.service}\n활성: ${data.is_active}\n테스트 모드: ${data.test_mode}\n키 존재: ${data.key_present} (${data.key_length} chars)`)
  } catch (e) { alert('테스트 실패: ' + (e.response?.data?.message || e.message)) }
}
async function revealKey(k) {
  if (!confirm(`정말 "${k.name}" 키를 평문으로 확인하시겠습니까? (감사 로그에 기록됩니다)`)) return
  try {
    const { data } = await axios.get(`/api/admin/api-keys/${k.id}/reveal`)
    prompt('API Key (복사):', data.api_key)
  } catch (e) { alert('권한 없음 또는 실패') }
}
async function toggleActive(k) {
  try {
    await axios.put(`/api/admin/api-keys/${k.id}`, { is_active: !k.is_active })
    k.is_active = !k.is_active
  } catch (e) { alert('변경 실패') }
}

onMounted(async () => {
  await loadKeys()
  await loadUsage()
  await loadLogs()
})
</script>
