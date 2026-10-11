<template>
<!-- 관리자 감사 로그 (최고관리자 전용, 읽기 전용) — 누가 언제 무엇을 바꿨는지. 값은 저장하지 않고 "보낸 칸 이름"만 보여 준다. -->
<div class="space-y-3 pb-20" :class="isMobile ? 'al-m' : ''">
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
    <span class="icon-chip w-9 h-9 bg-gray-100 text-ink-light"><AppIcon name="shield" :size="20" /></span>
    감사 로그
  </h1>
  <p class="text-[13px] text-ink-muted leading-relaxed">관리자·운영자가 바꾼 내용의 기록이에요. 비밀번호·키 같은 값은 남기지 않고, 어떤 칸을 보냈는지만 보여요. 시각은 애틀랜타 시각(UTC 함께 표시)이에요.</p>

  <!-- 검색·필터 -->
  <div class="card p-3 space-y-2">
    <form @submit.prevent="load(1)" class="flex gap-2">
      <input v-model="q" type="text" placeholder="관리자 이름·이메일·동작·IP 검색" aria-label="검색" class="input-soft flex-1 px-3 py-2" />
      <button type="submit" class="btn-primary px-4 py-2 text-sm">검색</button>
    </form>
    <div class="flex flex-wrap gap-2 items-center">
      <select v-model="days" @change="load(1)" class="input-soft w-auto px-3 py-2 text-sm" aria-label="기간">
        <option :value="1">최근 24시간</option><option :value="7">최근 7일</option><option :value="30">최근 30일</option><option :value="0">전체</option>
      </select>
      <select v-model="target" @change="load(1)" class="input-soft w-auto px-3 py-2 text-sm" aria-label="대상">
        <option value="">모든 대상</option>
        <option v-for="t in targets" :key="t" :value="t">{{ t }}</option>
      </select>
      <label class="flex items-center gap-1.5 text-sm text-ink min-h-[40px]"><input type="checkbox" v-model="failed" @change="load(1)" /> 실패한 것만</label>
      <span class="ml-auto text-xs text-ink-faint">{{ total.toLocaleString() }}건</span>
    </div>
  </div>

  <p v-if="error" class="bg-red-50 text-red-600 text-sm rounded-xl p-3">{{ error }}</p>

  <!-- 목록 -->
  <div v-for="r in rows" :key="r.id" class="bg-white border border-gray-100 rounded-2xl px-4 py-3">
    <div class="flex items-start justify-between gap-2">
      <div class="min-w-0">
        <div class="text-[15px] font-bold text-ink break-words">{{ actionLabel(r) }}</div>
        <div class="text-[13px] text-ink-light mt-0.5">
          <b>{{ r.admin_name || ('#' + r.admin_id) }}</b>
          <span v-if="roleLabel(r.note)" class="ml-1.5 text-[11px] font-bold px-1.5 py-0.5 rounded-md bg-gray-100 text-ink-muted">{{ roleLabel(r.note) }}</span>
        </div>
      </div>
      <span v-if="r.http_status" class="shrink-0 text-[12px] font-bold px-2 py-0.5 rounded-md" :class="r.http_status >= 400 ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-700'">{{ r.http_status >= 400 ? '실패 ' + r.http_status : '성공' }}</span>
    </div>
    <div class="text-[12px] text-ink-faint mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5">
      <span>{{ fmt(r.created_at).atl }} <span class="text-ink-faint/80">(UTC {{ fmt(r.created_at).utc }})</span></span>
      <span v-if="r.ip">IP {{ r.ip }}</span>
      <span v-if="r.target_type">대상 {{ r.target_type }}<template v-if="r.target_id"> #{{ r.target_id }}</template></span>
    </div>
    <div v-if="r.fields?.length" class="text-[12px] text-ink-muted mt-1.5 break-words">보낸 칸: {{ r.fields.join(', ') }}</div>
  </div>
  <div v-if="!rows.length" class="text-center text-ink-muted py-12 text-[15px]">{{ loading ? '불러오는 중...' : '기록이 없어요' }}</div>

  <div v-if="lastPage > 1" class="flex items-center justify-center gap-3 pt-2">
    <button @click="load(page - 1)" :disabled="page <= 1 || loading" class="btn-secondary px-4 py-2 text-sm disabled:opacity-40">이전</button>
    <span class="text-sm text-ink-muted tabular-nums">{{ page }} / {{ lastPage }}</span>
    <button @click="load(page + 1)" :disabled="page >= lastPage || loading" class="btn-secondary px-4 py-2 text-sm disabled:opacity-40">다음</button>
  </div>
</div>
</template>

<script setup>
import { ref, computed, inject, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)

const rows = ref([]); const targets = ref([]); const loading = ref(false); const error = ref('')
const page = ref(1); const lastPage = ref(1); const total = ref(0)
const q = ref(''); const days = ref(7); const target = ref(''); const failed = ref(false)

async function load(p = 1) {
  if (p < 1) return
  loading.value = true; error.value = ''
  try {
    const params = { page: p, days: days.value }
    if (q.value.trim()) params.q = q.value.trim()
    if (target.value) params.target = target.value
    if (failed.value) params.failed = 1
    const { data } = await axios.get('/api/admin/audit-log', { params })
    rows.value = data.data?.data || []
    page.value = data.data?.current_page || p
    lastPage.value = data.data?.last_page || 1
    total.value = data.data?.total || 0
    if (data.targets) targets.value = data.targets
  } catch (e) {
    error.value = e.response?.data?.message || '기록을 불러오지 못했어요'
  }
  loading.value = false
}

// DB 시간은 UTC — 애틀랜타(미국 동부) 시각과 함께 보여 준다
function fmt(s) {
  if (!s) return { atl: '', utc: '' }
  const d = new Date(String(s).replace(' ', 'T') + (String(s).endsWith('Z') ? '' : 'Z'))
  if (isNaN(d)) return { atl: String(s), utc: '' }
  const atl = d.toLocaleString('ko-KR', { timeZone: 'America/New_York', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false })
  const utc = d.toLocaleString('ko-KR', { timeZone: 'UTC', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false })
  return { atl: '애틀랜타 ' + atl, utc }
}
function roleLabel(role) {
  return { super_admin: '최고관리자', admin: '관리자', moderator: '운영자' }[role] || ''
}
// "PUT users/12" → "수정 · users/12" 처럼 쉬운 말로
function actionLabel(r) {
  const [method, ...rest] = String(r.action || '').split(' ')
  const verb = { POST: '등록·실행', PUT: '수정', PATCH: '수정', DELETE: '삭제' }[method] || method
  return `${verb} · ${rest.join(' ')}`
}

onMounted(() => load(1))
</script>
