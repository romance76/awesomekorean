<template>
<!-- 시스템 상태 요약 (최고관리자 전용) — 빨간 표시는 "지금 확인이 필요한 것", 노란 표시는 "조심할 것" -->
<div v-if="!hidden" :data-level="data?.level || ''" aria-label="시스템 상태" role="region"
  :class="[mobile ? 'rounded-2xl bg-white border p-3.5' : 'card p-4', data?.level === 'bad' ? '!border-red-300 border-2' : (mobile ? 'border-gray-100' : '')]">
  <button type="button" @click="toggleOpen" :aria-expanded="open"
    class="w-full flex items-center gap-3 text-left" :class="mobile ? 'min-h-[52px]' : 'min-h-[40px]'">
    <span class="w-3 h-3 rounded-full shrink-0" :class="dot(data?.level)"></span>
    <span class="flex-1 min-w-0">
      <span class="flex items-center gap-2 flex-wrap">
        <span class="font-bold text-ink" :class="mobile ? 'text-[16px]' : 'text-[15px]'">시스템 상태</span>
        <span v-if="data" class="px-2 py-0.5 rounded-full font-bold" :class="[pill(data.level), mobile ? 'text-[12px]' : 'text-[11px]']">{{ levelText(data.level) }}</span>
      </span>
      <span class="block text-ink-muted" :class="mobile ? 'text-[13px]' : 'text-xs'">{{ summary }}</span>
    </span>
    <AppIcon :name="open ? 'chevron-up' : 'chevron-down'" :size="18" class="text-ink-muted" />
  </button>

  <div v-if="loadError" class="mt-2 rounded-xl bg-gray-50 px-3 py-2.5 text-ink-muted" :class="mobile ? 'text-[14px]' : 'text-sm'">
    상태를 불러오지 못했어요. <button type="button" class="font-bold underline" @click="load">다시 시도</button>
  </div>

  <!-- 경고 설명 (빨강/노랑일 때만, 항상 보임) -->
  <div v-if="data && data.alerts && data.alerts.length" class="mt-2.5 space-y-2" role="alert">
    <div v-for="a in data.alerts" :key="a.key" class="rounded-xl px-3 py-2.5 leading-relaxed border"
      :class="[a.level === 'bad' ? 'bg-red-50 text-red-700 border-red-100' : 'bg-amber-50 text-amber-800 border-amber-100', mobile ? 'text-[14px]' : 'text-sm']">
      <b class="mr-1">{{ a.level === 'bad' ? '확인이 필요해요' : '조심하세요' }} ·</b>{{ a.message }}
    </div>
  </div>

  <!-- 자세히 -->
  <div v-if="open && data" class="mt-3 border-t border-gray-100">
    <div v-for="r in rows" :key="r.key" class="flex items-start gap-3 py-2.5 border-b border-gray-50 last:border-0">
      <span class="w-2.5 h-2.5 rounded-full shrink-0 mt-1.5" :class="dot(r.state)"></span>
      <div class="flex-1 min-w-0">
        <div class="text-ink-muted" :class="mobile ? 'text-[13px]' : 'text-xs'">{{ r.label }}</div>
        <div class="font-bold text-ink break-words" :class="mobile ? 'text-[15px]' : 'text-sm'">{{ r.value }}</div>
        <div v-if="r.sub" class="text-ink-faint tabular-nums" :class="mobile ? 'text-[12.5px]' : 'text-[11px]'">{{ r.sub }}</div>
      </div>
    </div>
    <div class="flex items-center justify-between gap-2 pt-2">
      <span class="text-ink-faint tabular-nums" :class="mobile ? 'text-[12.5px]' : 'text-[11px]'">{{ updatedText }}</span>
      <button type="button" @click="load" :disabled="loading" class="px-3 rounded-xl font-bold text-ink-light bg-gray-100 disabled:opacity-50"
        :class="mobile ? 'min-h-[44px] text-[14px]' : 'min-h-[36px] text-xs'">{{ loading ? '확인 중...' : '새로고침' }}</button>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import AppIcon from '../AppIcon.vue'

const props = defineProps({ variant: { type: String, default: 'desktop' } })
const mobile = computed(() => props.variant === 'mobile')

const data = ref(null)
const loading = ref(false)
const loadError = ref(false)
const hidden = ref(false)        // 최고관리자가 아니면(403) 카드를 통째로 숨김
const open = ref(false)
let timer = null
let userToggled = false
function toggleOpen() { userToggled = true; open.value = !open.value }

async function load() {
  loading.value = true
  loadError.value = false
  try {
    const res = await axios.get('/api/admin/system-status')
    data.value = res.data?.data || null
    // 경고가 있으면 자세히 펼쳐 둠 (처음 한 번만)
    if (data.value?.level === 'bad' && !userToggled) open.value = true
  } catch (e) {
    if (e.response?.status === 403 || e.response?.status === 401) hidden.value = true
    else loadError.value = true
  }
  loading.value = false
}

const dot = (s) => ({ ok: 'bg-emerald-500', warn: 'bg-amber-500', bad: 'bg-red-500' }[s] || 'bg-gray-300')
const pill = (s) => ({ ok: 'bg-emerald-50 text-emerald-700', warn: 'bg-amber-100 text-amber-800', bad: 'bg-red-100 text-red-700' }[s] || 'bg-gray-100 text-ink-muted')
const levelText = (s) => ({ ok: '정상', warn: '주의', bad: '확인 필요' }[s] || '확인 중')

const tAtl = (iso) => iso ? new Date(iso).toLocaleString('ko-KR', { timeZone: 'America/New_York', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }) : ''
const tUtc = (iso) => iso ? new Date(iso).toLocaleString('ko-KR', { timeZone: 'UTC', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }) : ''
function ago(sec) {
  if (sec == null) return ''
  if (sec < 60) return `${sec}초 전`
  if (sec < 3600) return `${Math.floor(sec / 60)}분 전`
  if (sec < 86400) return `${Math.floor(sec / 3600)}시간 전`
  return `${Math.floor(sec / 86400)}일 전`
}

const summary = computed(() => {
  const d = data.value
  if (!d) return loadError.value ? '불러오지 못했어요' : '불러오는 중...'
  if (d.level === 'ok') return '큐·스케줄러·저장공간 모두 정상이에요'
  return d.alerts.length === 1 ? '확인할 항목이 1개 있어요' : `확인할 항목이 ${d.alerts.length}개 있어요`
})

const rows = computed(() => {
  const d = data.value
  if (!d) return []
  const q = d.queue || {}, s = d.scheduler || {}, k = d.disk || {}, m = d.memory || {}, p = d.deploy || {}
  return [
    { key: 'queue', label: '백그라운드 작업 (알림·메일 등)', state: q.state,
      value: q.pending == null && q.failed == null ? '확인 못 함' : `대기 ${q.pending ?? '–'}건 · 실패 ${q.failed ?? '–'}건` },
    { key: 'scheduler', label: '자동 작업 실행기 (마지막 실행)', state: s.state,
      value: s.last_run_at ? ago(s.age_seconds) : (s.state === 'unknown' ? '기록 확인 중' : '기록 없음'),
      sub: s.last_run_at ? `${tAtl(s.last_run_at)} (애틀랜타) · ${tUtc(s.last_run_at)} (UTC)` : '' },
    { key: 'disk', label: '서버 저장공간', state: k.state,
      value: k.used_percent == null ? '확인 못 함' : `${k.used_percent}% 사용 · 남은 ${k.free_gb}GB`,
      sub: k.total_gb ? `전체 ${k.total_gb}GB` : '' },
    { key: 'memory', label: '서버 메모리', state: m.state,
      value: m.used_percent == null ? '확인 못 함' : `${m.used_percent}% 사용 · 여유 약 ${m.available_mb}MB`,
      sub: m.total_mb ? `전체 ${m.total_mb}MB` + (m.swap_used_mb ? ` · 보조 메모리(스왑) ${m.swap_used_mb}MB 사용` : '') : '' },
    { key: 'deploy', label: '최근 배포', state: p.state,
      value: p.at ? ago(p.age_seconds) : '기록 없음',
      sub: p.at ? `${tAtl(p.at)} (애틀랜타) · ${tUtc(p.at)} (UTC)${p.commit ? ' · ' + p.commit : ''}` : '' },
  ]
})

const updatedText = computed(() => data.value?.generated_at ? `확인 시각 ${tAtl(data.value.generated_at)} (애틀랜타) · ${tUtc(data.value.generated_at)} (UTC)` : '')

onMounted(() => {
  load()
  timer = setInterval(() => { if (!document.hidden && !hidden.value) load() }, 60000)
})
onBeforeUnmount(() => { clearInterval(timer) })
</script>
