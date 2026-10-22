<template>
<div class="min-h-screen">
  <div class="max-w-5xl mx-auto px-4 py-5">
    <RouterLink to="/youtubers" class="inline-flex items-center gap-1 text-[13px] font-semibold text-ink-muted hover:text-amber-700 mb-3">← 한인 유튜버</RouterLink>

    <div v-if="loading" class="py-16 text-center text-ink-muted text-sm">불러오는 중...</div>
    <div v-else-if="!ch" class="py-16 text-center text-ink-muted text-sm">채널을 찾을 수 없어요.</div>

    <template v-else>
      <!-- 채널 머리 -->
      <div class="card overflow-hidden mb-4">
        <div v-if="ch.banner_url" class="h-24 sm:h-36 bg-surface"><img :src="ch.banner_url" alt="" class="w-full h-full object-cover" @error="e => e.target.style.display = 'none'" /></div>
        <div class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-4">
          <img v-if="ch.thumbnail_url" :src="ch.thumbnail_url" alt="" class="w-20 h-20 rounded-full object-cover bg-surface shrink-0" @error="e => e.target.style.display = 'none'" />
          <div class="min-w-0 flex-1">
            <h1 class="text-xl font-extrabold text-ink break-words">{{ ch.title }}</h1>
            <div class="mt-1 text-[13px] text-ink-muted">
              <span v-if="ch.handle">{{ ch.handle }} · </span>구독자 {{ fmtCount(ch.subscriber_count) }} · 영상 {{ fmtCount(ch.video_count) }}개
              <span class="ml-1 badge-gray">{{ ch.category_label }}</span>
            </div>
            <p v-if="ch.intro" class="mt-2 text-[13.5px] text-ink-light break-words">{{ ch.intro }}</p>
          </div>
          <a :href="ch.url" target="_blank" rel="noopener noreferrer" class="btn-primary shrink-0 inline-flex items-center justify-center gap-1.5 px-5 py-2.5 text-sm">
            <AppIcon name="external-link" :size="14" />유튜브에서 구독하기
          </a>
        </div>
      </div>

      <!-- 탭 -->
      <div class="flex gap-1 border-b border-gray-100 mb-4">
        <button v-for="t in tabs" :key="t.key" @click="setTab(t.key)" class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors"
          :class="tab === t.key ? 'border-amber-500 text-amber-700' : 'border-transparent text-ink-muted hover:text-ink-light'">{{ t.label }}<span v-if="t.count != null" class="ml-1 text-[11px] text-ink-faint">{{ t.count }}</span></button>
      </div>

      <!-- 영상·쇼츠 -->
      <template v-if="tab !== 'about'">
        <div v-if="vLoading && !videos.length" class="py-12 text-center text-ink-muted text-sm">불러오는 중...</div>
        <div v-else-if="!videos.length" class="py-12 text-center text-ink-muted text-sm">{{ tab === 'short' ? '쇼츠가 아직 없어요.' : '영상이 아직 없어요.' }}</div>
        <div v-else :class="tab === 'short' ? 'grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3' : 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4'">
          <button v-for="v in videos" :key="v.id" @click="play = v" class="text-left group">
            <div class="relative bg-surface rounded-xl overflow-hidden" :class="tab === 'short' ? 'aspect-[9/16]' : 'aspect-video'">
              <img v-if="v.thumbnail_url" :src="v.thumbnail_url" alt="" loading="lazy" class="w-full h-full object-cover" @error="e => e.target.style.display = 'none'" />
              <span class="absolute inset-0 grid place-items-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/25"><AppIcon name="play" :size="34" class="text-white" /></span>
              <span v-if="v.duration_seconds" class="absolute right-1.5 bottom-1.5 text-[11px] font-bold bg-black/70 text-white px-1.5 py-0.5 rounded">{{ fmtDur(v.duration_seconds) }}</span>
            </div>
            <div class="mt-1.5 text-[13px] font-semibold text-ink line-clamp-2 break-words">{{ v.title }}</div>
            <div class="text-[11.5px] text-ink-muted">조회 {{ fmtCount(v.view_count) }}<template v-if="v.published_at"> · {{ ago(v.published_at) }}</template></div>
          </button>
        </div>
        <div v-if="vPage < vLast" class="text-center mt-6">
          <button @click="loadVideos(vPage + 1)" :disabled="vLoading" class="btn-secondary px-6 py-2 text-sm">{{ vLoading ? '불러오는 중...' : '더 보기' }}</button>
        </div>
      </template>

      <!-- 소개 -->
      <div v-else class="card p-5 text-[14px] text-ink-light whitespace-pre-wrap break-words leading-relaxed">{{ ch.description || '채널 소개가 없어요.' }}</div>
    </template>
  </div>

  <!-- 재생창: 유튜브 공식 임베드 -->
  <div v-if="play" class="fixed inset-0 z-[80] bg-black/80 flex items-center justify-center p-3" @click.self="play = null">
    <div class="w-full relative" :class="tab === 'short' ? 'max-w-[360px]' : 'max-w-4xl'">
      <button @click="play = null" class="absolute -top-10 right-0 text-white p-2" aria-label="닫기"><AppIcon name="x" :size="22" /></button>
      <div class="bg-black rounded-xl overflow-hidden" :class="tab === 'short' ? 'aspect-[9/16] max-h-[85vh]' : 'aspect-video'">
        <iframe :src="`https://www.youtube-nocookie.com/embed/${play.video_id}?autoplay=1&rel=0`" class="w-full h-full" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen title="유튜브 영상"></iframe>
      </div>
      <div class="mt-2 text-white text-[13px] font-semibold line-clamp-2">{{ play.title }}</div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const route = useRoute()
const ch = ref(null)
const loading = ref(true)
const tab = ref('video')
const videos = ref([])
const vPage = ref(1)
const vLast = ref(1)
const vLoading = ref(false)
const play = ref(null)

const tabs = computed(() => [
  { key: 'video', label: '영상', count: ch.value?.videos_total },
  { key: 'short', label: '쇼츠', count: ch.value?.shorts_total },
  { key: 'about', label: '소개', count: null },
])

function fmtCount(n) {
  n = Number(n || 0)
  if (n >= 10000) return (n / 10000).toFixed(n >= 100000 ? 0 : 1).replace(/\.0$/, '') + '만'
  if (n >= 1000) return (n / 1000).toFixed(1).replace(/\.0$/, '') + '천'
  return String(n)
}
function fmtDur(s) {
  s = Number(s || 0)
  const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), r = s % 60
  return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(r).padStart(2, '0')
}
function ago(d) {
  const t = new Date(d).getTime()
  if (!t) return ''
  const days = Math.floor((Date.now() - t) / 86400000)
  if (days < 1) return '오늘'
  if (days < 30) return days + '일 전'
  if (days < 365) return Math.floor(days / 30) + '개월 전'
  return Math.floor(days / 365) + '년 전'
}

async function loadVideos(p) {
  vLoading.value = true
  try {
    const { data } = await axios.get(`/api/youtubers/${route.params.id}/videos`, { params: { type: tab.value === 'short' ? 'short' : 'video', page: p } })
    const d = data.data || {}
    videos.value = p === 1 ? (d.items || []) : videos.value.concat(d.items || [])
    vPage.value = d.meta?.current_page || p
    vLast.value = d.meta?.last_page || 1
  } catch { /* 영상이 안 떠도 화면 유지 */ } finally { vLoading.value = false }
}
function setTab(k) {
  tab.value = k
  if (k !== 'about') { videos.value = []; loadVideos(1) }
}

async function loadChannel() {
  loading.value = true
  try {
    const { data } = await axios.get(`/api/youtubers/${route.params.id}`)
    ch.value = data.data
    if (document) document.title = `${ch.value.title} | 어썸코리안`
    await loadVideos(1)
  } catch { ch.value = null } finally { loading.value = false }
}

watch(() => route.params.id, (v, o) => { if (v && v !== o) { tab.value = 'video'; loadChannel() } })
onMounted(loadChannel)
</script>
