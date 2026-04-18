<template>
  <!-- /admin/v2/server/health (Phase 2-C Post) -->
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">🩺 시스템 헬스 체크</h2>
      <div class="flex items-center gap-2">
        <button @click="load" :disabled="loading" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded text-xs">🔄 재검사</button>
        <label class="flex items-center gap-1 text-xs">
          <input type="checkbox" v-model="autoRefresh" /> 30초 자동 갱신
        </label>
      </div>
    </div>

    <div v-if="loading" class="text-sm text-gray-400 p-6 text-center">검사 중...</div>

    <div v-else-if="data">
      <!-- 종합 상태 -->
      <div :class="overallCardClass(data.overall)">
        <p class="text-xs opacity-75">종합 상태</p>
        <p class="text-2xl font-bold my-1">{{ overallLabel(data.overall) }}</p>
        <p class="text-xs opacity-75">{{ fmtDate(data.checked_at) }}</p>
      </div>

      <!-- 체크 상세 -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
        <div v-for="(check, name) in data.checks" :key="name" class="bg-white rounded-xl shadow-sm p-4">
          <div class="flex items-center justify-between mb-2">
            <h3 class="font-semibold">{{ icon(name) }} {{ label(name) }}</h3>
            <span :class="statusBadge(check.status)">{{ check.status }}</span>
          </div>
          <dl class="text-xs space-y-1">
            <template v-for="(v, k) in check" :key="k">
              <div v-if="k !== 'status'" class="flex justify-between">
                <dt class="text-gray-500">{{ k }}</dt>
                <dd class="font-mono">{{ formatValue(v) }}</dd>
              </div>
            </template>
          </dl>
        </div>
      </div>

      <!-- 버전 정보 -->
      <div class="bg-white rounded-xl shadow-sm p-4 mt-4">
        <h3 class="font-semibold mb-3">📦 버전</h3>
        <dl class="grid grid-cols-3 gap-3 text-sm">
          <div><dt class="text-xs text-gray-500">PHP</dt><dd class="font-mono">{{ data.versions?.php }}</dd></div>
          <div><dt class="text-xs text-gray-500">Laravel</dt><dd class="font-mono">{{ data.versions?.laravel }}</dd></div>
          <div><dt class="text-xs text-gray-500">Timezone</dt><dd class="font-mono">{{ data.versions?.timezone }}</dd></div>
        </dl>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import axios from 'axios'

const data = ref(null)
const loading = ref(true)
const autoRefresh = ref(false)
let timer = null

const fmtDate = (s) => s ? new Date(s).toLocaleString('ko-KR') : ''

const icon = (k) => ({
  database: '🗄️', redis: '⚡', queue: '📦', storage: '💾',
  mail: '📧', apis: '🔌', reverb: '📡', activity_24h: '📊',
}[k] || '⚙️')

const label = (k) => ({
  database: '데이터베이스', redis: 'Redis', queue: 'Queue',
  storage: '디스크', mail: '이메일', apis: 'API 연동',
  reverb: 'Reverb', activity_24h: '24시간 활동',
}[k] || k)

const overallLabel = (o) => ({ healthy: '🟢 정상', degraded: '🟡 일부 경고', critical: '🔴 심각', unknown: '⚪ 불명' }[o] || o)

const overallCardClass = (o) => 'p-5 rounded-xl shadow-sm ' + ({
  healthy: 'bg-green-500 text-white',
  degraded: 'bg-amber-500 text-white',
  critical: 'bg-red-500 text-white',
}[o] || 'bg-white')

const statusBadge = (s) => 'px-2 py-0.5 rounded text-xs font-semibold ' + ({
  healthy: 'bg-green-100 text-green-700',
  warning: 'bg-yellow-100 text-yellow-700',
  degraded: 'bg-amber-100 text-amber-700',
  down: 'bg-red-100 text-red-700',
  configured: 'bg-blue-100 text-blue-700',
  not_configured: 'bg-gray-100 text-gray-500',
}[s] || 'bg-gray-100')

function formatValue(v) {
  if (v === null || v === undefined) return '-'
  if (typeof v === 'object') return JSON.stringify(v)
  return String(v)
}

async function load() {
  loading.value = true
  try {
    const { data: res } = await axios.get('/api/admin/system/health')
    data.value = res.data
  } finally { loading.value = false }
}

watch(autoRefresh, (v) => {
  if (v) { timer = setInterval(load, 30000) }
  else if (timer) { clearInterval(timer); timer = null }
})

onMounted(load)
onBeforeUnmount(() => { if (timer) clearInterval(timer) })
</script>
