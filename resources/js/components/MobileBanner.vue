<template>
<!-- 모바일 전용 배너 슬롯.
     slot="premium" → bid_amount 최고가 확정 (히어로 아래 용)
     slot="random"  → 슬롯 2/3 가중 랜덤 (콘텐츠 중간 용)
-->
<a v-if="ad && ad.image_url" @click.prevent="handleClick"
  :href="ad.link_url || '#'"
  class="mobile-banner-wrap">
  <img :src="ad.image_url" :alt="ad.title || '광고'"
    class="mobile-banner-img"
    @error="e => e.target.style.display='none'" />
  <span class="ad-badge">AD</span>
</a>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  page: { type: String, default: 'home' },
  slot: { type: String, default: 'random' }, // 'premium' | 'random'
})

const ad = ref(null)

async function load() {
  try {
    const { data } = await axios.get('/api/banners/mobile-slot', {
      params: { page: props.page, slot: props.slot }
    })
    ad.value = data.data || null
  } catch { ad.value = null }
}

function handleClick() {
  if (!ad.value) return
  axios.post(`/api/banners/${ad.value.id}/click`).catch(() => {})
  if (ad.value.link_url) window.open(ad.value.link_url, '_blank')
}

onMounted(load)
watch(() => [props.page, props.slot], load)
</script>

<style scoped>
.mobile-banner-wrap {
  display: block;
  position: relative;
  width: 100%;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
  cursor: pointer;
  transition: transform 0.15s;
}
.mobile-banner-wrap:hover { transform: translateY(-1px); }

.mobile-banner-img {
  width: 100%;
  height: 90px;
  object-fit: cover;
  display: block;
}

.ad-badge {
  position: absolute;
  top: 6px;
  right: 6px;
  background: rgba(0, 0, 0, 0.55);
  color: #fff;
  font-size: 9px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  letter-spacing: 0.5px;
}
</style>
