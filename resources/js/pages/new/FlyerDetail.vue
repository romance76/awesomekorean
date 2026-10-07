<template>
<div class="min-h-screen">
  <div class="page-main px-4 py-5">
    <RouterLink to="/new" class="text-xs text-ink-muted hover:text-rose-600 transition-colors mb-3 inline-flex items-center gap-1"><AppIcon name="arrow-left" :size="13" />NEW 목록으로</RouterLink>

    <div v-if="loading" class="text-center py-16 text-ink-muted">로딩중...</div>
    <div v-else-if="!ad" class="text-center py-16">
      <p class="text-ink-light font-semibold">존재하지 않거나 종료된 전단입니다</p>
    </div>
    <template v-else>
      <!-- 작성자/관리자에게만 보이는 상태 -->
      <div v-if="ad.status && ad.status !== 'approved'" class="mb-3 rounded-xl border px-3 py-2 text-sm font-semibold" :class="STATUS_LABEL[ad.status]?.cls">
        {{ STATUS_LABEL[ad.status]?.text }}<span v-if="ad.reject_reason" class="font-normal"> — {{ ad.reject_reason }}</span>
      </div>

      <div class="card overflow-hidden">
        <img :src="ad.image_url" :alt="ad.title" class="w-full object-contain bg-gray-50" @click="openLink" />
        <div class="p-4">
          <div class="flex items-center gap-2 mb-1 flex-wrap">
            <span class="text-xs font-bold text-rose-600">{{ kindLabel(ad.kind) }}</span>
            <span v-if="ad.live" class="text-[10px] font-black text-white bg-rose-500 px-1.5 py-0.5 rounded-full">지금 방송 중</span>
            <span class="text-[11px] text-ink-faint">{{ ad.scope === 'national' ? '🇺🇸 전국' : '📍 ' + stateName(ad.region_key) }}</span>
          </div>
          <h1 class="text-lg font-bold text-ink">{{ ad.title }}</h1>
          <p v-if="ad.description" class="text-sm text-ink-light mt-2 whitespace-pre-wrap">{{ ad.description }}</p>

          <div class="flex items-center gap-2 mt-4 flex-wrap">
            <a v-if="ad.phone" :href="`tel:${ad.phone}`" class="btn-secondary px-4 py-2 rounded-lg text-sm" @click="track"><AppIcon name="phone" :size="14" />{{ ad.phone }}</a>
            <a v-if="ad.link_url" :href="ad.link_url" target="_blank" rel="noopener noreferrer nofollow" class="btn-primary px-4 py-2 rounded-lg text-sm" @click="track"><AppIcon name="external-link" :size="14" />가게 페이지 열기</a>
          </div>
        </div>
      </div>

      <div v-if="ad.schedule?.length" class="card p-4 mt-4">
        <h2 class="flex items-center gap-1.5 font-bold text-ink text-sm mb-2"><AppIcon name="clock" :size="14" class="text-rose-500" />NEW 상단 방송 일정 <span class="text-[11px] font-normal text-ink-faint">({{ tzLabel(ad.tz) }} 현지 시각)</span></h2>
        <div class="space-y-1 text-sm">
          <div v-for="s in ad.schedule" :key="s.date" class="flex gap-3">
            <span class="w-24 text-ink-muted flex-shrink-0">{{ fmtDay(s.date) }}</span>
            <span class="text-ink">{{ hourRanges(s.hours).join(', ') }}</span>
          </div>
        </div>
      </div>

      <div v-if="ad.view_count !== undefined" class="text-xs text-ink-faint mt-3 text-right">노출 {{ ad.view_count.toLocaleString() }} · 클릭 {{ ad.click_count.toLocaleString() }}</div>
    </template>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import { kindLabel, fmtDay, hourRanges, tzLabel, stateName, STATUS_LABEL } from '../../utils/flyer'

const route = useRoute()
const ad = ref(null)
const loading = ref(true)
let clicked = false

function track() {
  if (clicked || !ad.value || ad.value.status !== undefined) return
  clicked = true
  axios.post(`/api/flyers/${ad.value.id}/click`).catch(() => {})
}
function openLink() { if (ad.value?.link_url) { track(); window.open(ad.value.link_url, '_blank', 'noopener,noreferrer') } }

onMounted(async () => {
  try {
    const { data } = await axios.get(`/api/flyers/${route.params.id}`)
    ad.value = data.data
    // status 필드는 작성자/관리자에게만 내려오므로, 없으면 일반 방문자 → 노출 집계
    if (ad.value.status === undefined) axios.post(`/api/flyers/${ad.value.id}/view`).catch(() => {})
  } catch {}
  loading.value = false
})
</script>
