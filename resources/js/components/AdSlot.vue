<template>
  <div class="w-full overflow-hidden">
    <!-- 광고 위치 안내 모드: 실제 광고 대신 자리표시 박스 -->
    <div v-if="guide.on" class="space-y-2">
      <AdGuideBox v-for="g in guideSlots" :key="g.name" v-bind="g" />
    </div>

    <div v-else-if="ads.length" class="space-y-2">
      <div v-for="ad in ads" :key="ad.id"
        class="relative rounded-lg overflow-hidden cursor-pointer hover:opacity-90 transition"
        @click="handleClick(ad)">
        <img :src="ad.image_url" :alt="ad.title || 'AD'"
          :style="imgStyle"
          @error="e => e.target.src = '/images/ad-placeholder.png'" />
        <div class="absolute top-1 right-1 bg-black/40 text-white text-[7px] px-1 py-0.5 rounded">AD</div>
      </div>
    </div>

    <button v-if="showGuideLink && (position === 'left' || position === 'right')" type="button" @click="guide.toggle()"
      class="mt-2 w-full text-center text-[11px] text-ink-faint hover:text-amber-600 transition-colors py-1">
      {{ guide.on ? '광고 위치 안내 끄기' : '광고 위치 확인하기' }}
    </button>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useBannerStore } from '../stores/banners'
import { useAdGuideStore } from '../stores/adGuide'
import AdGuideBox from './AdGuideBox.vue'
import { adSize } from '../utils/adSizes'
import axios from 'axios'

const props = defineProps({
  page: { type: String, required: true },
  position: { type: String, required: true },
  maxSlots: { type: Number, default: 3 },
  showGuideLink: { type: Boolean, default: true }
})

const bannerStore = useBannerStore()
const guide = useAdGuideStore()
const ads = ref([])

const imgStyle = computed(() => {
  if (props.position === 'left') return { width: '100%', maxWidth: '200px', aspectRatio: adSize('left').ratio, objectFit: 'cover', display: 'block', margin: '0 auto' }
  if (props.position === 'right') return { width: '100%', maxWidth: '300px', aspectRatio: adSize('right').ratio, objectFit: 'cover', display: 'block', margin: '0 auto' }
  return { width: '100%', height: '80px', objectFit: 'cover', display: 'block', borderRadius: '8px' }
})

// 안내 모드에 보여줄 자리: 좌측 = 프리미엄 A / 스탠다드 A, 우측 = 프리미엄 B / 스탠다드 B (페이지의 노출 개수만큼)
const guideSlots = computed(() => {
  const left = props.position === 'left'
  const letter = left ? 'A' : 'B'
  const sz = adSize(props.position)
  const ratio = sz.ratio
  const maxWidth = left ? '200px' : '300px'
  const all = [
    { name: `프리미엄 ${letter}`, icon: '🥇', tone: 'premium', price: guide.priceOf(`${props.position}_premium`), used: ads.value.some(a => a.slot_number === 1) },
    { name: `스탠다드 ${letter}`, icon: '🥈', tone: 'standard', price: guide.priceOf(`${props.position}_standard`), used: ads.value.some(a => a.slot_number === 2) },
  ]
  return all.slice(0, Math.max(1, Math.min(props.maxSlots, 2)))
    .map(s => ({ ...s, sizeLabel: `고정 독점 · 이미지 ${sz.w}×${sz.h} 권장`, ratio, maxWidth }))
})

async function loadAds() {
  await bannerStore.loadForPage(props.page)
  const all = props.position === 'left' ? bannerStore.getLeft(props.page) : bannerStore.getRight(props.page)
  ads.value = all.slice(0, props.maxSlots)
}

function handleClick(ad) {
  axios.post(`/api/banners/${ad.id}/click`).catch(() => {})
  if (ad.link_url) window.open(ad.link_url, '_blank')
}

onMounted(loadAds)
watch(() => props.page, loadAds)
</script>
