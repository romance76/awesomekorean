<template>
<div>
  <div class="mb-4">
    <div class="text-xs text-ink-muted">관리자 › 시스템 › 방문 분석</div>
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mt-1">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="chart-bar" :size="20" /></span>
      방문 분석 (구글 애널리틱스)
    </h1>
  </div>

  <div class="card p-4 mb-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
      <div>
        <div class="text-xs text-ink-muted">추적 코드 상태</div>
        <div v-if="status" class="mt-1 flex items-center gap-2">
          <span class="text-[11px] font-bold px-2 py-0.5 rounded-md" :class="status.enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'">{{ status.enabled ? '켜짐' : '꺼짐' }}</span>
          <span v-if="status.measurement_id" class="font-mono text-sm text-ink">{{ status.measurement_id }}</span>
        </div>
      </div>
      <button @click="runCheck" :disabled="checking" class="btn-primary px-4 py-2 text-sm disabled:opacity-50">
        <AppIcon name="search" :size="14" />{{ checking ? '검사 중... (최대 30초)' : '사이트 전체 검사' }}
      </button>
    </div>
    <p v-if="status && !status.enabled" class="text-xs text-ink-light mt-3 leading-relaxed">
      아직 측정 ID가 등록되지 않았어요. <b>관리자 › 설정 › API 키 관리</b>에서 서비스 코드 <span class="font-mono">google_analytics</span> 로 구글 애널리틱스의 <b>측정 ID(G-로 시작)</b>를 등록하면 켜지고, 아래 검사 버튼으로 사이트 전체에 들어갔는지 확인할 수 있어요.
    </p>
    <p v-else class="text-xs text-ink-faint mt-3">주요 화면 주소와 최근 정보 글 40개를 실제로 열어 보고, 각 페이지에 추적 코드가 들어 있는지 확인해요.</p>
  </div>

  <div v-if="error" class="mb-3 bg-red-50 border border-red-200 text-red-600 text-xs rounded-xl p-3">{{ error }}</div>

  <div v-if="result" class="card p-4">
    <div class="flex items-center gap-3 flex-wrap">
      <div class="text-2xl font-black" :class="result.missing.length ? 'text-amber-600' : 'text-emerald-600'">{{ result.with_tag }} / {{ result.total }}</div>
      <div class="text-sm text-ink">{{ result.message }}</div>
    </div>
    <div v-if="result.missing.length" class="mt-3 space-y-1">
      <div class="text-xs font-bold text-ink-muted">추적 코드가 없는 페이지</div>
      <div v-for="m in result.missing" :key="m.url" class="text-xs flex gap-2">
        <span class="font-mono text-red-500 shrink-0">{{ m.status || '응답없음' }}</span>
        <span class="text-ink-light break-all">{{ decode(m.url) }}</span>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const status = ref(null)
const result = ref(null)
const checking = ref(false)
const error = ref('')

const decode = (u) => { try { return decodeURIComponent(u) } catch { return u } }

async function loadStatus() {
  try { const { data } = await axios.get('/api/admin/analytics/status'); status.value = data.data }
  catch (e) { error.value = e.response?.status === 403 ? '최고 관리자만 볼 수 있는 화면이에요.' : '상태를 불러오지 못했어요.' }
}

async function runCheck() {
  checking.value = true; error.value = ''; result.value = null
  try { const { data } = await axios.post('/api/admin/analytics/check', {}, { timeout: 60000 }); result.value = data.data; status.value = { enabled: data.data.enabled, measurement_id: data.data.measurement_id } }
  catch (e) { error.value = e.response?.status === 429 ? '잠시 후 다시 시도해 주세요.' : '검사하지 못했어요.' }
  finally { checking.value = false }
}

onMounted(loadStatus)
</script>
