<template>
  <div class="min-h-screen flex items-center justify-center px-4 py-10">
    <div class="card p-8 w-full max-w-md text-center">
      <div class="icon-chip w-16 h-16 bg-amber-50 text-amber-600 mx-auto mb-4"><AppIcon name="settings" :size="32" :stroke-width="1.5" /></div>
      <h1 class="text-xl font-bold text-ink mb-2">지금은 점검 중이에요</h1>
      <p class="text-ink-light whitespace-pre-line leading-relaxed">{{ message }}</p>
      <p v-if="untilText" class="text-sm text-ink-muted mt-3">끝나는 시간(예상): {{ untilText }} <span class="text-ink-faint">(애틀랜타 시간)</span></p>
      <p class="text-xs text-ink-faint mt-4">점검이 끝나면 이 화면이 저절로 바뀌어요.</p>
      <p v-if="stillOn" class="text-sm text-amber-700 mt-2" role="status">아직 점검 중이에요. 조금 뒤에 다시 확인해 주세요.</p>
      <div class="mt-6 flex flex-col gap-1">
        <button type="button" class="btn-primary w-full" :disabled="checking" @click="check(true)">{{ checking ? '확인 중...' : '지금 다시 확인하기' }}</button>
        <RouterLink to="/login" class="text-sm text-ink-muted hover:text-amber-600 py-3">관리자이신가요? 로그인</RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
// 점검 모드 안내 화면 — 관리자 사이트 설정에서 점검 모드를 켜면 일반 회원에게 App.vue 가 이 화면을 보여 준다.
// 30초마다 서버에 점검이 풀렸는지 확인하고, 풀렸으면 화면을 새로 불러온다 (점검 중에 실패한 요청이 남아 있지 않도록).
import { ref, computed, onMounted, onUnmounted } from 'vue'
import AppIcon from './AppIcon.vue'
import { useSiteStore } from '../stores/site'

const site = useSiteStore()
const checking = ref(false)
const stillOn = ref(false)
let timer = null

const message = computed(() => site.maintenance.message || '지금은 사이트를 점검하고 있어요. 잠시 후 다시 이용해 주세요.')

// 관리자가 적은 "2026-10-11T15:00" → "10월 11일 오후 3:00" (적은 그대로의 시각, 시간대 변환 없음)
const untilText = computed(() => {
  const u = site.maintenance.until
  if (!u) return ''
  const m = /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/.exec(String(u))
  if (!m) return String(u)
  const h = Number(m[4])
  const h12 = h % 12 === 0 ? 12 : h % 12
  return `${Number(m[2])}월 ${Number(m[3])}일 ${h < 12 ? '오전' : '오후'} ${h12}:${m[5]}`
})

async function check(manual = false) {
  if (checking.value) return
  checking.value = true
  try {
    const ok = await site.refreshFlags()
    if (ok && !site.maintenance.active) { window.location.reload(); return }
    stillOn.value = manual && ok
  } finally { checking.value = false }
}

onMounted(() => { timer = setInterval(() => check(false), 30000) })
onUnmounted(() => clearInterval(timer))
</script>
