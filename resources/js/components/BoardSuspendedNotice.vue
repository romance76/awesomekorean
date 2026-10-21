<template>
  <div class="min-h-screen flex items-center justify-center px-4 py-10">
    <div class="card p-8 w-full max-w-md text-center">
      <div class="icon-chip w-16 h-16 bg-red-50 text-red-500 mx-auto mb-4"><AppIcon name="alert-circle" :size="32" :stroke-width="1.5" /></div>
      <h1 class="text-xl font-bold text-ink mb-2">이 게시판은 이용할 수 없어요</h1>
      <p class="text-ink-light leading-relaxed">
        <b class="text-ink">{{ info.board || '이 게시판' }}</b>은(는) 신고로 인하여 정지되어 사용할 수 없습니다.
      </p>
      <p class="text-sm text-ink-muted mt-3">
        정지 기간:
        <b v-if="info.permanent" class="text-red-600">영구</b>
        <b v-else class="text-ink">{{ untilText }}까지</b>
      </p>
      <p class="text-xs text-ink-faint mt-4 leading-relaxed">
        신고로 인한 정지가 3번 쌓이면 계정 이용이 정지돼요.<br />
        억울하거나 궁금한 점은 관리자에게 문의해 주세요.
      </p>
      <div class="mt-6 flex flex-col gap-1">
        <RouterLink to="/" class="btn-primary w-full">처음 화면으로</RouterLink>
        <RouterLink to="/contact" class="text-sm text-ink-muted hover:text-amber-600 py-3">관리자에게 문의하기</RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
// 신고로 이용이 정지된 게시판에 들어갔을 때 App.vue 가 보여 주는 안내 — 서버가 403 + code=board_suspended 로 막으면 ak:board-suspended 이벤트로 이 화면이 열린다
import { computed } from 'vue'
import AppIcon from './AppIcon.vue'

const props = defineProps({ info: { type: Object, required: true } })

// 정지가 끝나는 날(미국 동부 기준)을 "2026년 11월 21일" 로
const untilText = computed(() => {
  if (!props.info.until) return ''
  try {
    return new Date(props.info.until).toLocaleDateString('ko-KR', { timeZone: 'America/New_York', year: 'numeric', month: 'long', day: 'numeric' })
  } catch { return String(props.info.until).slice(0, 10) }
})
</script>
