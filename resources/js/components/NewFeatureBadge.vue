<template>
<span class="nfb-wrap" @mouseenter="show=true" @mouseleave="show=false" @click="show=!show">
  <span v-if="showNewLabel" class="nfb-new">NEW</span>
  <span v-else class="nfb-dot"></span>
  <span v-if="show" class="nfb-tooltip">{{ desc }}</span>
</span>
</template>
<script setup>
import { ref, computed } from 'vue'

const props = defineProps({ desc: { type: String, required: true } })
const show = ref(false)

// "NEW" 문구는 올해까지만 표시하고 내년부터는 이 뱃지 자체(작은 점)는
// 남겨 풍선말 설명은 계속 보이되 NEW 텍스트만 빠지도록(사용자 결정).
const NEW_LABEL_CUTOFF = new Date('2027-01-01T00:00:00-05:00') // 미국 동부시간 기준
const showNewLabel = computed(() => new Date() < NEW_LABEL_CUTOFF)
</script>
<style scoped>
.nfb-wrap { position: relative; display: inline-flex; align-items: center; cursor: help; vertical-align: middle; }
.nfb-new {
  font-size: 9px; font-weight: 800; line-height: 1; color: #fff;
  background: linear-gradient(135deg, #FF8A53, #F2570F);
  padding: 2px 5px; border-radius: 999px; letter-spacing: 0.3px;
  box-shadow: 0 1px 3px rgba(242, 87, 15, 0.35);
}
.nfb-dot { width: 6px; height: 6px; border-radius: 999px; background: #F2570F; opacity: 0.6; }
.nfb-tooltip {
  position: absolute; z-index: 40; bottom: calc(100% + 6px); left: 50%; transform: translateX(-50%);
  background: #1f2937; color: #fff; font-size: 11px; font-weight: 500; line-height: 1.4;
  padding: 6px 9px; border-radius: 8px; white-space: normal; width: max-content; max-width: 220px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}
.nfb-tooltip::after {
  content: ''; position: absolute; top: 100%; left: 50%; transform: translateX(-50%);
  border: 5px solid transparent; border-top-color: #1f2937;
}
</style>
