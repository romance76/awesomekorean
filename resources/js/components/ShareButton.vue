<template>
  <button @click="share" class="text-gray-500 hover:text-amber-600 transition flex items-center gap-1 text-sm"
    :title="'링크 공유'" :aria-label="'공유'">
    <span class="text-base">🔗</span>
    <span v-if="label" class="hidden sm:inline">{{ label }}</span>
  </button>
  <div v-if="copied" class="fixed top-20 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-xs px-3 py-2 rounded-lg shadow-xl z-[100]">
    {{ copiedMsg }}
  </div>
</template>

<script setup>
// P2B-11: 공용 공유 버튼 — Web Share API 우선, 없으면 클립보드 복사 폴백
import { ref } from 'vue'

const props = defineProps({
  title: { type: String, default: '' },
  text: { type: String, default: '' },
  url: { type: String, default: '' },
  label: { type: String, default: '공유' },
})

const copied = ref(false)
const copiedMsg = ref('')

async function share() {
  const targetUrl = props.url || window.location.href
  const shareData = {
    title: props.title || document.title,
    text: props.text || '',
    url: targetUrl,
  }

  // Web Share API 지원 환경 (주로 모바일)
  if (navigator.share) {
    try {
      await navigator.share(shareData)
      return
    } catch (e) {
      // 사용자 취소는 AbortError — 조용히 종료
      if (e.name === 'AbortError') return
    }
  }

  // 폴백: 클립보드 복사
  try {
    await navigator.clipboard.writeText(targetUrl)
    copiedMsg.value = '📋 링크가 복사되었습니다'
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  } catch {
    // 클립보드 실패 시 프롬프트로 URL 표시
    prompt('링크를 복사하세요:', targetUrl)
  }
}
</script>
