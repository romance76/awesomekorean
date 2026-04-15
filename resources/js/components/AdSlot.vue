<template>
  <div v-if="ads.length" class="space-y-2">
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

const props = defineProps({
  page: { type: String, required: true },
  position: { type: String, required: true },
  maxSlots: { type: Number, default: 3 }
})

const ads = ref([])

// 슬롯 사이즈: 왼쪽 200×100, 오른쪽 300×200
const imgStyle = computed(() => {
  if (props.position === 'left') return { width: '200px', height: '100px', objectFit: 'cover', display: 'block' }
  if (props.position === 'right') return { width: '100%', maxWidth: '300px', height: '200px', objectFit: 'cover', display: 'block' }
  // 모바일 인라인 (리스트 사이)
  return { width: '100%', height: '80px', objectFit: 'cover', display: 'block', borderRadius: '8px' }
})

async function loadAds() {
  try {
    const { data } = await axios.get('/api/banners/active', {
      params: { page: props.page, position: props.position, limit: props.maxSlots }
    })
    ads.value = data.data || []
  } catch {}
}

function handleClick(ad) {
  axios.post(`/api/banners/${ad.id}/click`).catch(() => {})
  if (ad.link_url) window.open(ad.link_url, '_blank')
}

onMounted(loadAds)
watch(() => props.page, loadAds)
</script>
