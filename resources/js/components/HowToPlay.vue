<template>
<div class="htp" :class="{ open }">
  <button class="htp-toggle" @click="open = !open" type="button">
    <span class="htp-icon">📖</span>
    <span class="htp-title">게임 방법</span>
    <span class="htp-chevron" :class="{ rot: open }">▾</span>
  </button>
  <div v-if="open" class="htp-body">
    <ul class="htp-list">
      <li v-for="(line, i) in lines" :key="i">{{ line }}</li>
    </ul>
  </div>
</div>
</template>

<script setup>
import { ref } from 'vue'

defineProps({
  lines: { type: Array, required: true },
})

const open = ref(false)
</script>

<style scoped>
.htp {
  background: rgba(255,255,255,0.7); border-radius: 14px; margin-bottom: 12px;
  overflow: hidden; border: 1px solid rgba(0,0,0,0.06);
}
:global(.theme-dark) .htp { background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.1); }

.htp-toggle {
  width: 100%; display: flex; align-items: center; gap: 8px;
  padding: 11px 14px; background: transparent; border: none; cursor: pointer;
  font-size: 13px; font-weight: 800; color: inherit; text-align: left;
}
.htp-icon { font-size: 15px; }
.htp-title { flex: 1; }
.htp-chevron { transition: transform 0.2s ease; opacity: 0.6; }
.htp-chevron.rot { transform: rotate(180deg); }

.htp-body { padding: 0 16px 14px; }
.htp-list { margin: 0; padding-left: 18px; display: flex; flex-direction: column; gap: 6px; }
.htp-list li { font-size: 12.5px; line-height: 1.5; opacity: 0.85; }
</style>
