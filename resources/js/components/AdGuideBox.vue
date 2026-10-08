<template>
  <!-- 광고 위치 안내용 자리표시 박스 (안내 모드에서만 사용) -->
  <div class="agb" :class="tone" :style="boxStyle">
    <div class="agb-tier">{{ icon }} {{ name }}</div>
    <div class="agb-size">{{ sizeLabel }}</div>
    <div v-if="price" class="agb-price">{{ price.toLocaleString() }}P/월~</div>
    <button v-if="used === false && applyTo" type="button" class="agb-state free agb-btn" @click.stop="apply">비어 있음 · 신청하기 ›</button>
    <div v-else-if="used !== null" class="agb-state" :class="used ? 'used' : 'free'">{{ used ? '현재 노출 중' : '비어 있음 · 신청 가능' }}</div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'

const props = defineProps({
  name: { type: String, required: true },
  icon: { type: String, default: '' },
  tone: { type: String, default: 'premium' },   // premium | standard | text
  sizeLabel: { type: String, default: '' },
  price: { type: Number, default: 0 },
  used: { type: Boolean, default: null },
  ratio: { type: String, default: '' },          // CSS aspect-ratio 예: '200 / 140'
  maxWidth: { type: String, default: '' },
  // 비어 있는 자리를 누르면 광고 신청 화면으로 보낼 정보 { page, position, slot, tier }
  applyTo: { type: Object, default: null },
})

const router = useRouter()
function apply() {
  const a = props.applyTo
  router.push({ path: '/ad-apply', query: { page: a.page, position: a.position, slot: a.slot, tier: a.tier } })
}

const boxStyle = computed(() => ({
  ...(props.ratio ? { aspectRatio: props.ratio } : {}),
  ...(props.maxWidth ? { maxWidth: props.maxWidth, margin: '0 auto' } : {}),
}))
</script>

<style scoped>
.agb {
  position: relative; width: 100%;
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px;
  border: 2px dashed; border-radius: 10px; padding: 6px; text-align: center;
  animation: agb-pulse 1.8s ease-in-out infinite;
}
.agb.premium { border-color: #f59e0b; background: rgba(254, 243, 199, .85); color: #92400e; }
.agb.standard { border-color: #3b82f6; background: rgba(219, 234, 254, .85); color: #1e40af; }
.agb.text { border-color: #a855f7; background: rgba(243, 232, 255, .85); color: #6b21a8; }
.agb-tier { font-size: 12px; font-weight: 900; }
.agb-size { font-size: 10px; opacity: .8; }
.agb-price { font-size: 11px; font-weight: 800; color: #dc2626; }
.agb-state { font-size: 9px; font-weight: 700; padding: 1px 6px; border-radius: 999px; }
.agb-state.free { background: #fff; color: #16a34a; }
.agb-btn { cursor: pointer; border: 1px solid #16a34a; }
.agb-btn:hover { background: #16a34a; color: #fff; }
.agb-state.used { background: #fff; color: #6b7280; }
@keyframes agb-pulse { 0%,100% { box-shadow: 0 0 0 0 rgba(240, 38, 107, .25); } 50% { box-shadow: 0 0 0 5px rgba(240, 38, 107, 0); } }
</style>
