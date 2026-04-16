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
import { useBannerStore } from '../stores/banners'
import axios from 'axios'

const props = defineProps({
  page: { type: String, required: true },
  position: { type: String, required: true },
  maxSlots: { type: Number, default: 3 }
})

const bannerStore = useBannerStore()
const ads = ref([])

const imgStyle = computed(() => {
  if (props.position === 'left') return { width: '100%', maxWidth: '200px', aspectRatio: '200 / 140', objectFit: 'cover', display: 'block', margin: '0 auto' }
  if (props.position === 'right') return { width: '100%', maxWidth: '300px', aspectRatio: '300 / 210', objectFit: 'cover', display: 'block', margin: '0 auto' }
  return { width: '100%', height: '80px', objectFit: 'cover', display: 'block', borderRadius: '8px' }
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
