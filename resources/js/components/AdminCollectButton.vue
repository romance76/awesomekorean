<!-- 관리자 "지금 수집" 버튼 (음악·뉴스·숏츠).
     누르면 서버가 바로 "수집을 시작했어요" 로 답하고 백그라운드에서 돈다. 버튼은 "수집 중…" 으로 바뀌고
     (연타해도 서버가 막아 줌), 7초마다 진행 상태를 확인해 끝나면 알림 + 목록 새로고침(done 이벤트).
     화면을 떠나면 확인을 멈춘다. 휴대폰(mobile)에서는 터치 높이 50px 이상의 꽉 찬 버튼. -->
<template>
<div :class="mobile ? 'w-full' : 'flex flex-col items-end'">
  <button type="button" @click="job.start()" :disabled="job.running.value"
    class="inline-flex items-center justify-center gap-1.5 font-semibold text-white bg-blue-500 transition-colors disabled:opacity-60"
    :class="mobile ? 'w-full min-h-[50px] rounded-xl text-[15px] font-bold' : 'px-3 py-2 rounded-xl text-sm hover:bg-blue-600'">
    <AppIcon :name="icon" :size="14" :class="{ 'animate-spin': job.running.value }" />{{ job.running.value ? '수집 중…' : label }}
  </button>
  <p v-if="job.running.value" class="mt-1.5 text-ink-muted" :class="mobile ? 'text-[14px]' : 'text-xs max-w-[22rem] text-right'" role="status" aria-live="polite">{{ job.runningText.value }}</p>
  <p v-else-if="job.lastText.value" class="mt-1.5" :class="[mobile ? 'text-[14px]' : 'text-xs max-w-[22rem] text-right', job.lastOk.value ? 'text-ink-muted' : 'text-red-600']">{{ job.lastText.value }}</p>
</div>
</template>

<script setup>
import { useCollectJob } from '../composables/useCollectJob'
import { useModal } from '../composables/useModal'
import AppIcon from './AppIcon.vue'

const props = defineProps({
  kind: { type: String, required: true },        // music | news | shorts
  endpoint: { type: String, required: true },    // 시작 주소 (예: /api/admin/fetch-music)
  label: { type: String, required: true },       // 평소 버튼 글자
  icon: { type: String, default: 'refresh' },
  mobile: { type: Boolean, default: false },     // 관리자 휴대폰 화면(AdminLayout 의 adminIsMobile)
})
// toast: 휴대폰은 페이지의 위쪽 알림(say) / done: 수집이 끝났을 때 목록 새로고침용
const emit = defineEmits(['toast', 'done'])
const { showAlert } = useModal()

const job = useCollectJob({
  kind: props.kind,
  endpoint: props.endpoint,
  onNotify(text, isError, phase) {
    if (props.mobile) emit('toast', text, isError)
    else if (phase !== 'start') showAlert(text)   // PC: 시작 안내는 버튼 상태로 충분, 끝남·오류는 팝업
  },
  onDone(last) { emit('done', last) },
})
</script>
