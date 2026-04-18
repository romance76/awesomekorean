<template>
  <!-- /mypage/security — Phase 2-C 묶음 3: 신규 보안 기능 -->
  <div class="space-y-4">
    <!-- 활성 세션 -->
    <div class="bg-white rounded-xl shadow-sm p-5">
      <div class="flex items-center justify-between mb-3">
        <h3 class="font-bold">🔐 활성 세션</h3>
        <button
          v-if="sessions.length > 1"
          @click="terminateOthers"
          class="text-xs px-3 py-1.5 bg-red-100 text-red-700 hover:bg-red-200 rounded font-semibold"
        >🚪 다른 기기 전체 로그아웃</button>
      </div>
      <div v-if="loadingSessions" class="text-sm text-gray-400 text-center py-4">로딩 중...</div>
      <div v-else-if="!sessions.length" class="text-sm text-gray-500 text-center py-4">활성 세션 없음</div>
      <ul v-else class="divide-y">
        <li v-for="s in sessions" :key="s.id" class="py-3 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="text-2xl">{{ deviceIcon(s.device) }}</span>
            <div>
              <p class="text-sm font-semibold">
                {{ s.device || '알 수 없음' }}
                <span v-if="s.is_current" class="ml-2 px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded">현재 세션</span>
              </p>
              <p class="text-xs text-gray-500">{{ s.ip }} · {{ fmtDate(s.created_at) }}</p>
              <p class="text-xs text-gray-400 truncate max-w-md">{{ s.user_agent }}</p>
            </div>
          </div>
          <button
            v-if="!s.is_current"
            @click="terminate(s.id)"
            class="text-xs px-2 py-1 bg-red-100 text-red-700 hover:bg-red-200 rounded"
          >종료</button>
        </li>
      </ul>
    </div>

    <!-- 로그인 기록 -->
    <div class="bg-white rounded-xl shadow-sm p-5">
      <h3 class="font-bold mb-3">📜 로그인 기록 (최근 50건)</h3>
      <div v-if="loadingHistory" class="text-sm text-gray-400 text-center py-4">로딩 중...</div>
      <div v-else-if="!history.length" class="text-sm text-gray-500 text-center py-4">기록 없음</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-xs text-gray-500 uppercase bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left">일시</th>
              <th class="px-3 py-2 text-left">기기</th>
              <th class="px-3 py-2 text-left">IP</th>
              <th class="px-3 py-2 text-center">상태</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="h in history" :key="h.id" class="border-t">
              <td class="px-3 py-2 text-xs">{{ fmtDate(h.created_at) }}</td>
              <td class="px-3 py-2 text-xs">{{ deviceIcon(h.device) }} {{ h.device }}</td>
              <td class="px-3 py-2 text-xs font-mono">{{ h.ip }}</td>
              <td class="px-3 py-2 text-center">
                <span v-if="h.successful" class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs">✅ 성공</span>
                <span v-else class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs" :title="h.failure_reason">❌ 실패</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 보안 팁 -->
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-xs text-gray-700 space-y-1">
      <p>💡 <strong>의심스러운 로그인</strong>을 발견하면 즉시 비밀번호를 변경하세요.</p>
      <p>💡 <strong>다른 기기 전체 로그아웃</strong> 후 비밀번호 변경으로 완전히 차단할 수 있습니다.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useSiteStore } from '../../stores/site'

const site = useSiteStore()
const sessions = ref([])
const history = ref([])
const loadingSessions = ref(true)
const loadingHistory = ref(true)

const fmtDate = (s) => s ? new Date(s).toLocaleString('ko-KR') : ''
const deviceIcon = (d) => ({ iOS: '📱', Android: '📱', Windows: '💻', macOS: '💻', Linux: '💻' }[d] || '🖥️')

async function loadSessions() {
  loadingSessions.value = true
  try {
    const { data } = await axios.get('/api/security/sessions')
    sessions.value = data.data || []
  } finally { loadingSessions.value = false }
}
async function loadHistory() {
  loadingHistory.value = true
  try {
    const { data } = await axios.get('/api/security/login-history?limit=50')
    history.value = data.data || []
  } finally { loadingHistory.value = false }
}

async function terminate(id) {
  if (!confirm('이 세션을 종료하시겠습니까?')) return
  try {
    await axios.delete(`/api/security/sessions/${id}`)
    site.toast('세션이 종료되었습니다', 'success')
    await loadSessions()
  } catch { site.toast('종료 실패', 'error') }
}

async function terminateOthers() {
  if (!confirm('현재 세션을 제외한 모든 다른 기기 로그아웃을 진행할까요?')) return
  try {
    const { data } = await axios.post('/api/security/sessions/terminate-others')
    site.toast(`${data.terminated || 0} 개 세션 종료됨`, 'success')
    await loadSessions()
  } catch { site.toast('실패', 'error') }
}

onMounted(async () => {
  await loadSessions()
  await loadHistory()
})
</script>
