<template>
  <div v-if="ads.length" class="space-y-2 w-full overflow-hidden">
    <div v-for="ad in ads" :key="ad.id"
      class="relative rounded-lg overflow-hidden cursor-pointer hover:opacity-90 transition"
      @click="handleClick(ad)">
      <img :src="ad.image_url" :alt="ad.title || 'AD'"
        :style="imgStyle"
        @error="e => e.target.src = '/images/ad-placeholder.png'" />
      <div class="absolute top-1 right-1 bg-black/40 text-white text-[7px] px-1 py-0.5 rounded">AD</div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'

// 인메모리 캐시 — 같은 page+position은 5분간 재사용
const _adCache = new Map()
const AD_CACHE_TTL = 300000

const props = defineProps({
  page: { type: String, required: true },
  position: { type: String, required: true },
  maxSlots: { type: Number, default: 3 }
})

const ads = ref([])

// 슬롯 사이즈: 좌/우 모두 컬럼 폭을 넘지 않도록 width 100% + max-width
const imgStyle = computed(() => {
  if (props.position === 'left') return { width: '100%', maxWidth: '200px', aspectRatio: '200 / 140', objectFit: 'cover', display: 'block', margin: '0 auto' }
  if (props.position === 'right') return { width: '100%', maxWidth: '300px', aspectRatio: '300 / 210', objectFit: 'cover', display: 'block', margin: '0 auto' }
  return { width: '100%', height: '80px', objectFit: 'cover', display: 'block', borderRadius: '8px' }
})

async function loadAds() {
  const cacheKey = `${props.page}|${props.position}|${props.maxSlots}`
  const cached = _adCache.get(cacheKey)
  if (cached && Date.now() - cached.ts < AD_CACHE_TTL) {
    ads.value = cached.data
    return
  }
  try {
    const { data } = await axios.get('/api/banners/active', {
      params: { page: props.page, position: props.position, limit: props.maxSlots }
    })
    ads.value = data.data || []
    _adCache.set(cacheKey, { data: ads.value, ts: Date.now() })
  } catch {}
}

function handleClick(ad) {
  axios.post(`/api/banners/${ad.id}/click`).catch(() => {})
  if (ad.link_url) window.open(ad.link_url, '_blank')
}

onMounted(loadAds)
watch(() => props.page, loadAds)
</script>
