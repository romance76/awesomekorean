<template>
<div class="min-h-screen">
  <div class="max-w-3xl mx-auto px-4 py-8">
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-6">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="info" :size="20" /></span>
      AwesomeKorean 소개
    </h1>
    <div v-if="content" class="card p-6 text-sm text-ink-light leading-relaxed whitespace-pre-wrap">{{ content }}</div>
    <div v-else class="card p-6 text-sm text-ink-light leading-relaxed space-y-4">
      <p>AwesomeKorean은 미국에 거주하는 한인들을 위한 올인원 커뮤니티 플랫폼입니다.</p>
      <p>구인구직, 중고장터, 업소록, 부동산, 뉴스, 이벤트, Q&A 등 한인 생활에 필요한 모든 서비스를 한 곳에서 제공합니다.</p>
      <p>문의: admin@awesomekorean.com</p>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useSiteStore } from '../../stores/site'
import AppIcon from '../../components/AppIcon.vue'
const content = ref('')
onMounted(async () => {
  const site = useSiteStore()
  await site.load()
  content.value = site.getSetting('about_page', '')
})
</script>
