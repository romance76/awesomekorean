<template>
  <div v-if="ad" class="lg:hidden my-2">
    <div class="relative rounded-lg overflow-hidden cursor-pointer" @click="handleClick">
      <img :src="ad.image_url" :alt="'AD'" class="w-full h-[80px] object-cover" @error="$event.target.style.display='none'" />
      <div class="absolute top-1 right-1 bg-black/40 text-white text-[7px] px-1 py-0.5 rounded">AD</div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  page: { type: String, required: true }
})

const ad = ref(null)

async function loadAd() {
  try {
    const { data } = await axios.get('/api/banners/mobile', { params: { page: props.page } })
    ad.value = data.data
  } catch {}
}

function handleClick() {
  if (!ad.value) return
  axios.post(`/api/banners/${ad.value.id}/click`).catch(() => {})
  if (ad.value.link_url) window.open(ad.value.link_url, '_blank')
}

onMounted(loadAd)
</script>
