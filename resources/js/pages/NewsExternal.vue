<template>
<div class="max-w-2xl mx-auto px-4 py-6">
  <div v-if="loading" class="text-center py-16 text-ink-muted">불러오는 중...</div>
  <div v-else-if="!headline" class="text-center py-16 text-ink-muted">기사를 찾을 수 없어요.</div>
  <div v-else class="card overflow-hidden">
    <img v-if="headline.image_url" :src="headline.image_url" alt="" class="w-full max-h-80 object-cover" />
    <div class="p-5">
      <div class="flex items-center gap-2 mb-2">
        <span class="text-xs font-bold text-amber-600">{{ headline.source }}</span>
        <span class="text-xs text-ink-faint">· {{ formattedDate }}</span>
      </div>
      <h1 class="text-[19px] font-bold text-ink leading-snug">{{ headline.title }}</h1>
      <p v-if="headline.summary" class="mt-3 text-[14.5px] text-ink-light leading-relaxed">{{ headline.summary }}</p>
      <p class="mt-4 text-[12.5px] text-ink-faint">
        이 기사는 {{ headline.source }}에서 제공한 헤드라인이에요. 전체 기사는 원문 사이트에서 확인할 수 있어요.
      </p>
      <a :href="headline.source_url" target="_blank" rel="noopener noreferrer"
        class="mt-5 inline-flex items-center gap-1.5 bg-amber-500 text-white font-bold px-5 py-3 rounded-full hover:bg-amber-600 transition-colors">
        원문에서 계속 읽기 <AppIcon name="external-link" :size="15" />
      </a>
    </div>
  </div>
  <RouterLink to="/" class="inline-block mt-4 text-sm text-ink-muted hover:text-amber-600 transition-colors">← 홈으로</RouterLink>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import AppIcon from '../components/AppIcon.vue'

const route = useRoute()
const headline = ref(null)
const loading = ref(true)

const formattedDate = computed(() => {
  if (!headline.value?.published_at) return ''
  const d = new Date(headline.value.published_at)
  return `${d.getMonth() + 1}월 ${d.getDate()}일 ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
})

onMounted(async () => {
  try {
    const { data } = await axios.get(`/api/external-headlines/${route.params.id}`)
    headline.value = data.data
  } catch {}
  loading.value = false
})
</script>
