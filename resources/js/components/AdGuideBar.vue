<template>
  <Teleport to="body">
    <Transition name="agbar">
      <div v-if="guide.on" class="fixed left-1/2 -translate-x-1/2 bottom-20 md:bottom-6 z-[60] w-[calc(100%-24px)] max-w-md">
        <div class="bg-slate-900/95 text-white rounded-2xl shadow-2xl px-4 py-3 flex items-center gap-3">
          <div class="flex-1 min-w-0">
            <div class="text-xs font-bold">📢 광고 위치 안내 중</div>
            <div class="text-[11px] text-slate-300 leading-snug">점선 박스가 광고가 들어가는 자리입니다.</div>
          </div>
          <RouterLink to="/ad-apply" @click="guide.close()"
            class="shrink-0 text-[11px] font-bold bg-gradient-to-r from-[#FF8A4D] to-[#F0266B] px-3 py-1.5 rounded-full">광고 신청</RouterLink>
          <button type="button" @click="guide.close()" class="shrink-0 text-[11px] text-slate-300 hover:text-white px-1" aria-label="닫기">닫기</button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { watch, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAdGuideStore } from '../stores/adGuide'

const guide = useAdGuideStore()
const route = useRoute()

// 다른 페이지로 가면 안내 모드는 자동으로 끈다
watch(() => route.fullPath, () => guide.close())

function onKey(e) { if (e.key === 'Escape') guide.close() }
onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<style scoped>
.agbar-enter-active, .agbar-leave-active { transition: all .2s ease; }
.agbar-enter-from, .agbar-leave-to { opacity: 0; transform: translate(-50%, 8px); }
</style>
