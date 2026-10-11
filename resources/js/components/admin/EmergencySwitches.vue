<template>
<!-- 비상 스위치 (최고관리자 전용) — 문제가 생겼을 때 한 번에 멈추는 스위치. 누를 때마다 확인창을 거치고 기록이 남아요 -->
<div v-if="!hidden" :class="mobile ? 'rounded-2xl bg-white border border-gray-100 p-3.5 space-y-3' : 'card p-4 space-y-3'" role="region" aria-label="비상 스위치">
  <div>
    <div class="flex items-center gap-2 flex-wrap">
      <span class="font-bold text-ink" :class="mobile ? 'text-[16px]' : 'text-sm'">비상 스위치</span>
      <span v-if="loaded" class="px-2 py-0.5 rounded-full font-bold" :class="[pausedCount ? 'bg-red-100 text-red-700' : 'bg-emerald-50 text-emerald-700', mobile ? 'text-[12px]' : 'text-[11px]']">
        {{ pausedCount ? `지금 ${pausedCount}개 멈춰 있어요` : '모두 정상 작동 중' }}
      </span>
    </div>
    <p class="text-ink-muted leading-relaxed mt-0.5" :class="mobile ? 'text-[13px]' : 'text-xs'">문제가 생겼을 때 한 번에 멈추는 스위치예요. 멈춘 것은 언제든 다시 켤 수 있고, 누른 기록이 남아요.</p>
  </div>

  <div v-if="loadError" class="rounded-xl bg-gray-50 px-3 py-2.5 text-ink-muted" :class="mobile ? 'text-[14px]' : 'text-sm'">
    스위치를 불러오지 못했어요. <button type="button" class="font-bold underline" @click="load">다시 시도</button>
  </div>
  <div v-else-if="!loaded" class="text-ink-muted py-2" :class="mobile ? 'text-[14px]' : 'text-sm'">불러오는 중...</div>

  <div v-for="s in items" :key="s.key" class="rounded-2xl border p-3"
    :class="s.paused ? 'border-red-200 bg-red-50/60' : 'border-gray-100 bg-gray-50/60'">
    <div class="flex items-start gap-3">
      <span class="icon-chip w-9 h-9 shrink-0" :class="s.paused ? 'bg-red-100 text-red-600' : 'bg-white text-amber-600'"><AppIcon :name="s.icon || 'shield'" :size="18" /></span>
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="font-bold text-ink" :class="mobile ? 'text-[15px]' : 'text-sm'">{{ s.label }}</span>
          <span class="px-2 py-0.5 rounded-full font-bold" :class="[s.paused ? 'bg-red-600 text-white' : 'bg-emerald-100 text-emerald-700', mobile ? 'text-[12px]' : 'text-[11px]']">{{ s.paused ? '멈춰 있어요' : '정상 작동 중' }}</span>
        </div>
        <p class="text-ink-light leading-relaxed mt-1 break-words" :class="mobile ? 'text-[13.5px]' : 'text-xs'">{{ s.description }}</p>
        <p v-if="s.changed_at" class="text-ink-faint mt-1 tabular-nums" :class="mobile ? 'text-[12.5px]' : 'text-[11px]'">
          마지막 변경 {{ tAtl(s.changed_at) }} (애틀랜타) · {{ tUtc(s.changed_at) }} (UTC)<span v-if="s.changed_by"> · {{ s.changed_by }}</span>
        </p>
      </div>
    </div>
    <button type="button" @click="toggle(s)" :disabled="busyKey === s.key"
      class="mt-3 w-full rounded-xl font-bold disabled:opacity-50"
      :class="[s.paused ? 'bg-emerald-500 text-white' : 'bg-white border-2 border-red-300 text-red-600', mobile ? 'min-h-[52px] text-[16px]' : 'min-h-[44px] text-sm']">
      {{ busyKey === s.key ? '처리 중...' : (s.paused ? '다시 켜기' : '멈추기') }}
    </button>
  </div>

  <p v-if="msg" class="rounded-xl px-3 py-2.5" :class="[msgError ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700', mobile ? 'text-[14px]' : 'text-sm']" role="status">{{ msg }}</p>
</div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import AppIcon from '../AppIcon.vue'
import { useModal } from '../../composables/useModal'

const props = defineProps({ variant: { type: String, default: 'desktop' } })
const mobile = computed(() => props.variant === 'mobile')
const { showConfirm } = useModal()

const items = ref([])
const loaded = ref(false)
const loadError = ref(false)
const hidden = ref(false)        // 최고관리자가 아니면(403) 통째로 숨김
const busyKey = ref('')
const msg = ref('')
const msgError = ref(false)
let msgTimer = null

const pausedCount = computed(() => items.value.filter(s => s.paused).length)
const tAtl = (iso) => iso ? new Date(iso).toLocaleString('ko-KR', { timeZone: 'America/New_York', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }) : ''
const tUtc = (iso) => iso ? new Date(iso).toLocaleString('ko-KR', { timeZone: 'UTC', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }) : ''

function say(text, error = false) {
  msg.value = text; msgError.value = error
  clearTimeout(msgTimer)
  msgTimer = setTimeout(() => { msg.value = '' }, 6000)
}

async function load() {
  loadError.value = false
  try {
    const { data } = await axios.get('/api/admin/emergency-switches')
    items.value = data.data || []
    loaded.value = true
  } catch (e) {
    if (e.response?.status === 403 || e.response?.status === 401) hidden.value = true
    else { loadError.value = true; loaded.value = true }
  }
}

// 누르면 반드시 확인창(useModal)을 거친다 — 서버는 최고관리자만 받고 admin_audit_log 에 누가·언제·켬/끔을 남긴다
async function toggle(s) {
  if (busyKey.value) return
  const ok = await showConfirm(s.confirm, s.paused ? `${s.label} 다시 켜기` : `${s.label} 멈추기`)
  if (!ok) return
  busyKey.value = s.key
  try {
    const { data } = await axios.put(`/api/admin/emergency-switches/${s.key}`, { paused: !s.paused })
    const i = items.value.findIndex(x => x.key === s.key)
    if (i >= 0 && data.data) items.value.splice(i, 1, data.data)
    say(data.message || (s.paused ? '다시 켰어요.' : '멈췄어요.'))
  } catch (e) {
    say(e.response?.data?.message || '바꾸지 못했어요. 잠시 후 다시 시도해 주세요.', true)
    load()
  }
  busyKey.value = ''
}

onMounted(load)
onBeforeUnmount(() => clearTimeout(msgTimer))
</script>
