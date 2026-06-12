<template>
<div>
  <div v-if="rec.pointsEarned.value > 0" class="gre-points">+{{ rec.pointsEarned.value }}P 획득!</div>
  <div v-if="rec.newRecord.value" class="gre-record">⭐ 신기록! (이전: {{ rec.prevTimeMs.value ? rec.formatTime(rec.prevTimeMs.value) : '처음 기록' }})</div>
  <div class="gre-time">⏱️ 기록: {{ rec.formatTime(rec.elapsedMs.value) }}</div>
  <GameLeaderboard :ref="el => rec.lbRef.value = el" :slug="slug" :level="rec.recordLevel.value" />
</div>
</template>

<script setup>
import GameLeaderboard from './GameLeaderboard.vue'
defineProps({
  rec: { type: Object, required: true },
  slug: { type: String, required: true },
})
</script>

<style scoped>
.gre-points { background: linear-gradient(135deg, #FF8A53, #F2570F); color: #fff; padding: 8px 20px; border-radius: 18px; font-size: 16px; font-weight: 800; margin: 6px auto; display: inline-block; box-shadow: 0 4px 12px rgba(255,107,44,0.25); }
.gre-record { background: #FDF2F8; border: 1px solid #FBCFE8; color: #DB2777; padding: 8px 20px; border-radius: 18px; font-size: 14px; font-weight: 800; margin: 6px auto 8px; display: inline-block; }
.gre-time { color: #8B95A1; font-size: 14px; margin: 4px 0 10px; font-weight: 600; text-align: center; }
</style>
