<template>
<!-- 텍스트 전용 인라인 광고 (리스트 중간 / 상세 본문 하단)
     - 텍스트가 박스보다 길면 → 마퀴 스크롤 (우→좌)
     - 짧으면 → 좌우 천천히 왕복 (4초)
     - 클릭 시 link_url 이동 -->
<div v-if="guide.on" class="my-2">
  <AdGuideBox name="텍스트 인라인" icon="📝" tone="text" size-label="상호+전화+설명 한 줄 · 리스트 안" :price="guide.priceOf('inline-text_text')" :used="!!ad" :apply-to="{ page: props.page, position: 'inline-text', slot: 1, tier: 'text' }" />
</div>
<div v-else-if="ad" class="tia-wrap" @click="handleClick">
  <div class="tia-badge">AD</div>
  <div ref="frameRef" class="tia-frame">
    <div ref="contentRef" class="tia-content"
      :class="isOverflowing ? 'tia-marquee' : 'tia-sway'"
      :style="{ animationDuration: duration + 's' }">
      <span v-if="ad.business_name || ad.title" class="tia-name">
        {{ ad.business_name || ad.title }}
      </span>
      <span v-if="ad.phone" class="tia-phone">☎ {{ ad.phone }}</span>
      <span v-if="ad.description" class="tia-desc">· {{ ad.description }}</span>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted, nextTick, watch, onUnmounted } from 'vue'
import axios from 'axios'
import { useAdGuideStore } from '../stores/adGuide'
import AdGuideBox from './AdGuideBox.vue'

const props = defineProps({
  page: { type: String, default: 'home' },
  // 외부에서 ad 객체 직접 주입 가능 (테스트/mockup 용)
  manualAd: { type: Object, default: null },
  // API 자동 로드 on/off
  autoLoad: { type: Boolean, default: true },
  // 애니메이션 속도: 'slow'(느림, 기본) | 'normal' | 'fast'
  speed: { type: String, default: 'slow' },
})

const ad = ref(null)
const guide = useAdGuideStore()
const frameRef = ref(null)
const contentRef = ref(null)
const isOverflowing = ref(false)
const duration = ref(15)

async function loadAd() {
  if (props.manualAd) { ad.value = props.manualAd; return }
  if (!props.autoLoad) return
  try {
    const { data } = await axios.get('/api/banners/text-inline', {
      params: { page: props.page }
    })
    ad.value = data.data || null
  } catch { ad.value = null }
}

async function measureOverflow() {
  await nextTick()
  if (!frameRef.value || !contentRef.value) return
  const fw = frameRef.value.offsetWidth
  const cw = contentRef.value.scrollWidth
  isOverflowing.value = cw > fw + 2

  // 속도별 배수 (값이 클수록 느림)
  // slow=0.06 (기존 3배 느림) / normal=0.035 / fast=0.02 (기존값)
  const factor = { slow: 0.06, normal: 0.035, fast: 0.02 }[props.speed] || 0.06
  const swayFactor = { slow: 6, normal: 4.5, fast: 3 }[props.speed] || 6

  if (isOverflowing.value) {
    duration.value = Math.min(60, Math.max(12, Math.round(cw * factor)))
  } else {
    duration.value = swayFactor
  }
}

function handleClick() {
  if (!ad.value) return
  axios.post(`/api/banners/${ad.value.id}/click`).catch(() => {})
  if (ad.value.link_url) window.open(ad.value.link_url, '_blank')
}

let ro = null
onMounted(async () => {
  await loadAd()
  await measureOverflow()
  if (window.ResizeObserver && frameRef.value) {
    ro = new ResizeObserver(() => measureOverflow())
    ro.observe(frameRef.value)
  }
})
onUnmounted(() => ro?.disconnect())
watch(() => [props.page, props.manualAd], async () => { await loadAd(); await measureOverflow() })
watch(() => props.speed, measureOverflow)
</script>

<style scoped>
.tia-wrap {
  position: relative;
  border: 1.5px solid #d8b4fe;
  background: linear-gradient(90deg, #faf5ff, #fdf4ff);
  border-radius: 10px;
  padding: 10px 14px;
  margin: 8px 0;
  cursor: pointer;
  transition: box-shadow 0.15s, border-color 0.15s;
}
.tia-wrap:hover { border-color: #a855f7; box-shadow: 0 4px 14px rgba(168,85,247,0.18); }

.tia-badge {
  position: absolute;
  top: 4px; right: 6px;
  background: rgba(124, 58, 237, 0.8);
  color: #fff;
  font-size: 8px;
  font-weight: 700;
  padding: 1px 5px;
  border-radius: 3px;
  letter-spacing: 0.5px;
  z-index: 2;
}

.tia-frame {
  overflow: hidden;
  white-space: nowrap;
  width: 100%;
  position: relative;
  padding-right: 24px; /* AD 배지 공간 */
}

.tia-content {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  white-space: nowrap;
  will-change: transform;
}

.tia-name { font-weight: 800; color: #6b21a8; font-size: 13px; }
.tia-phone { color: #7c3aed; font-weight: 700; font-size: 12px; }
.tia-desc { color: #4b5563; font-size: 12px; }

/* 마퀴: 오른쪽 밖에서 시작 → 왼쪽 밖으로 */
@keyframes tia-marquee {
  0%   { transform: translateX(100%); }
  100% { transform: translateX(-100%); }
}
.tia-marquee { animation: tia-marquee linear infinite; }

/* 왕복: 좌우 살짝 천천히 */
@keyframes tia-sway {
  0%, 100% { transform: translateX(0); }
  50%      { transform: translateX(10px); }
}
.tia-sway { animation: tia-sway ease-in-out infinite; }

@media (max-width: 640px) {
  .tia-name { font-size: 12px; }
  .tia-phone, .tia-desc { font-size: 11px; }
  .tia-wrap { padding: 8px 12px; }
}
</style>
