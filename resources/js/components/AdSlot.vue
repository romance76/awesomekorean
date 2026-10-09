<template>
  <div class="w-full overflow-hidden" :class="position === 'right' && topGap ? 'mt-3' : ''">
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

    <template v-if="showGuideLink && (position === 'left' || position === 'right')">
      <!-- 안내 모드: 링크 자리에 신청 / 닫기 버튼 -->
      <template v-if="guide.on">
      <div class="mt-2 flex gap-1.5">
        <RouterLink :to="{ path: '/ad-apply', query: { page } }"
          class="flex-1 text-center text-[11px] font-bold text-white bg-gradient-to-r from-[#FF8A4D] to-[#FC226B] rounded-lg py-1.5">광고 신청</RouterLink>
        <button type="button" @click="guide.close()"
          class="flex-1 text-center text-[11px] font-bold text-ink-light bg-gray-100 hover:bg-gray-200 rounded-lg py-1.5 transition-colors">닫기</button>
      </div>
      <button type="button" @click="showPhone = true"
        class="mt-1.5 w-full text-center text-[11px] font-bold text-purple-700 bg-purple-50 hover:bg-purple-100 border border-purple-200 rounded-lg py-1.5 transition-colors">📱 폰 화면 보기</button>
      <AdPhonePreview v-model="showPhone" :page="page" :page-label="pageLabel" />
      </template>
      <button v-else type="button" @click="guide.open()"
        class="mt-2 w-full text-center text-[11px] text-ink-faint hover:text-amber-600 transition-colors py-1">광고 위치 확인하기</button>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useBannerStore } from '../stores/banners'
import { useAdGuideStore } from '../stores/adGuide'
import AdGuideBox from './AdGuideBox.vue'
import AdPhonePreview from './AdPhonePreview.vue'
import { adSize } from '../utils/adSizes'
import axios from 'axios'

const props = defineProps({
  page: { type: String, required: true },
  position: { type: String, required: true },
  maxSlots: { type: Number, default: 3 },
  showGuideLink: { type: Boolean, default: true },
  // 오른쪽 사이드바에서 위 위젯 카드와 광고 사이를 띄운다 (왼쪽 사이드바와 같은 간격)
  topGap: { type: Boolean, default: true }
})

const bannerStore = useBannerStore()
const guide = useAdGuideStore()
const ads = ref([])
const showPhone = ref(false)
const PAGE_LABELS = { home: '홈', community: '커뮤니티', qa: 'Q&A', jobs: '구인구직', market: '중고장터', realestate: '부동산', directory: '업소록', clubs: '동호회', news: '뉴스', recipes: '레시피', groupbuy: '공동구매', events: '이벤트' }
const pageLabel = computed(() => PAGE_LABELS[props.page] || '목록')

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
  const apply = (slot, tier) => ({ page: props.page, position: props.position, slot, tier })
  const all = [
    { name: `프리미엄 ${letter}`, icon: '🥇', tone: 'premium', price: guide.priceOf(`${props.position}_premium`), used: ads.value.some(a => a.slot_number === 1), applyTo: apply(1, 'premium') },
    { name: `스탠다드 ${letter}`, icon: '🥈', tone: 'standard', price: guide.priceOf(`${props.position}_standard`), used: ads.value.some(a => a.slot_number === 2), applyTo: apply(2, 'standard') },
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
