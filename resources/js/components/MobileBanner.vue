<template>
<!-- 모바일 전용 배너 슬롯.
     4광고 (프리미엄A/B + 스탠다드A/B) 가중 랜덤 회전 (35:35:15:15)
     - 리스트 페이지: 5번째 ↔ 6번째 아이템 사이
     - 상세 페이지: 댓글 ↔ 페이지네이션 사이 (텍스트 인라인은 상세에 없음)
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
})

const ad = ref(null)

async function load() {
  try {
    const { data } = await axios.get('/api/banners/mobile-slot', {
      params: { page: props.page }
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
watch(() => props.page, load)
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
