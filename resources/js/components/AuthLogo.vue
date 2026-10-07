<template>
  <div>
    <!-- 로그인/가입 화면에는 상단 메뉴가 없으므로 뒤로가기·홈 이동 수단을 제공 -->
    <div class="flex items-center justify-between -mt-3 mb-2">
      <button type="button" @click="goBack" class="inline-flex items-center gap-1 text-sm font-semibold text-ink-light hover:text-ink px-2 py-1.5 -ml-2 rounded-lg">
        <AppIcon name="arrow-left" :size="16" />뒤로
      </button>
      <RouterLink to="/" class="inline-flex items-center gap-1 text-sm font-semibold text-ink-light hover:text-ink px-2 py-1.5 -mr-2 rounded-lg">
        <AppIcon name="home" :size="16" />홈
      </RouterLink>
    </div>
    <div class="w-24 h-24 rounded-xl mx-auto mb-3 overflow-hidden">
      <img v-if="!imgError" :src="site.logoUrl" alt="AwesomeKorean" class="w-full h-full object-contain" @error="imgError = true" />
      <div v-else class="w-full h-full rounded-xl shadow-btn bg-gradient-to-br from-[#FF8A4D] to-[#F0266B] flex items-center justify-center text-3xl font-black text-white">AK</div>
    </div>
  </div>
</template>
<script setup>
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useSiteStore } from '../stores/site'
import AppIcon from './AppIcon.vue'
const site = useSiteStore()
const router = useRouter()
const imgError = ref(false)
// 이전 화면이 사이트 안에 있을 때만 뒤로, 직접 진입(링크·새 탭)이면 홈으로
function goBack() {
  if (window.history.state?.back) router.back()
  else router.push('/')
}
</script>
