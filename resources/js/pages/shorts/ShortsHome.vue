<template>
  <div class="sc" ref="containerRef">
    <!-- Fixed Header -->
    <div class="sc-header">
      <button class="sc-back" @click="$router.back()">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
          <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
        </svg>
      </button>
      <span class="sc-title">쇼츠</span>
      <div class="sc-tabs">
        <button :class="['sc-tab', { active: activeTab === 'all' }]" @click="setTab('all')">전체</button>
        <button :class="['sc-tab', { active: activeTab === 'following' }]" @click="setTab('following')">팔로잉</button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="sc-loading">
      <div class="sc-spinner"></div>
    </div>

    <!-- Slides -->
    <div
      v-for="(short, idx) in shorts"
      :key="short.id + '-' + idx"
      :data-index="idx"
      class="sc-slide"
      ref="slideEls"
    >
      <!-- Background blur -->
      <div class="sc-bg" :style="short.thumbnail ? `background-image:url(${short.thumbnail})` : 'background:#111'"></div>

      <!-- Video -->
      <div class="sc-video">
        <!-- Active slide: play iframe -->
        <iframe
          v-if="short.platform === 'youtube' && idx === currentIndex"
          :src="embedUrl(short)"
          frameborder="0"
          allow="autoplay; encrypted-media; gyroscope; picture-in-picture; web-share"
          allowfullscreen
          playsinline
          class="w-full h-full"
        ></iframe>
        <!-- Inactive slide: show thumbnail -->
        <div v-else class="w-full h-full flex items-center justify-center bg-black relative">
          <img v-if="short.thumbnail" :src="short.thumbnail" class="w-full h-full object-cover" :alt="short.title" />
          <div class="absolute inset-0 flex items-center justify-center">
            <div class="w-16 h-16 bg-white/30 backdrop-blur rounded-full flex items-center justify-center">
              <span class="text-white text-3xl ml-1">▶</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Info overlay (pointer-events none so video touch works) -->
      <div class="sc-info">
        <div class="sc-channel">{{ short.channel_name || short.author || '채널' }}</div>
        <div class="sc-desc">{{ short.title }}</div>
        <div class="sc-tags" v-if="short.tags">
          <span v-for="tag in parseTags(short.tags)" :key="tag" class="sc-tag">#{{ tag }}</span>
        </div>
      </div>

      <!-- Action buttons -->
      <div class="sc-actions">
        <button class="sc-act-btn" @click.stop="toggleLike(short)">
          <svg width="28" height="28" viewBox="0 0 24 24" :fill="short.liked ? '#ff4757' : 'white'">
            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
          </svg>
          <span>{{ formatCount(short.like_count || 0) }}</span>
        </button>
        <button class="sc-act-btn" @click.stop="openComments(short)">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="white">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
          </svg>
          <span>{{ formatCount(short.comment_count || 0) }}</span>
        </button>
        <button class="sc-act-btn" @click.stop="shareShort(short)">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="white">
            <path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/>
          </svg>
          <span>공유</span>
        </button>
        <button class="sc-act-btn" @click.stop="saveShort(short)">
          <svg width="28" height="28" viewBox="0 0 24 24" :fill="short.saved ? '#ffd32a' : 'white'">
            <path d="M17 3H7c-1.1 0-2 .9-2 2v16l7-3 7 3V5c0-1.1-.9-2-2-2z"/>
          </svg>
          <span>저장</span>
        </button>
      </div>

      <!-- Nav arrows -->
      <div class="sc-nav">
        <button v-if="idx > 0" class="sc-nav-btn sc-nav-up" @click.stop="goTo(idx - 1)">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="white">
            <path d="M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6z"/>
          </svg>
        </button>
        <button v-if="idx < shorts.length - 1" class="sc-nav-btn sc-nav-down" @click.stop="goTo(idx + 1)">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="white">
            <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6z"/>
          </svg>
        </button>
      </div>

      <!-- Progress indicator -->
      <div class="sc-progress">
        <span class="sc-progress-text">{{ idx + 1 }} / {{ shorts.length }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'

const containerRef = ref(null)
const slideEls = ref([])
const shorts = ref([])
const currentIndex = ref(0)
const loading = ref(true)
const activeTab = ref('all')
let observer = null
let page = 1
let hasMore = true
let loadingMore = false

// ── Load shorts ──────────────────────────────────────────
async function loadShorts(reset = false) {
  if (reset) {
    shorts.value = []
    page = 1
    hasMore = true
    loading.value = true
  }
  if (!hasMore || loadingMore) return
  loadingMore = true
  try {
    const res = await axios.get('/api/shorts', {
      params: { page, per_page: 20, tab: activeTab.value, sort: 'random' }
    })
    const items = res.data.data || res.data || []
    if (items.length === 0) {
      hasMore = false
    } else {
      shorts.value.push(...items)
      page++
    }
  } catch (e) {
    console.error('shorts load error', e)
  } finally {
    loading.value = false
    loadingMore = false
  }
}

function setTab(tab) {
  activeTab.value = tab
  currentIndex.value = 0
  loadShorts(true)
}

// ── Embed URL ────────────────────────────────────────────
function embedUrl(short) {
  // Use embed_url directly if available
  if (short.embed_url) {
    const vidMatch = short.embed_url.match(/embed\/([^?&/]+)/)
    if (vidMatch) {
      const vid = vidMatch[1]
      return `https://www.youtube.com/embed/${vid}?autoplay=1&mute=0&loop=1&playlist=${vid}&rel=0&controls=1&playsinline=1&enablejsapi=1`
    }
    return short.embed_url
  }
  // Fallback: extract from url field
  if (short.url) {
    const vid = extractYouTubeId(short.url)
    if (vid) {
      return `https://www.youtube.com/embed/${vid}?autoplay=1&mute=0&loop=1&playlist=${vid}&rel=0&controls=1&playsinline=1&enablejsapi=1`
    }
  }
  return ''
}

function extractYouTubeId(url) {
  const m = url.match(/(?:v=|youtu\.be\/|embed\/)([^&?\/]+)/)
  return m ? m[1] : ''
}

function parseTags(tags) {
  if (!tags) return []
  if (Array.isArray(tags)) return tags
  try { return JSON.parse(tags) } catch { return tags.split(',').map(t => t.trim()) }
}

function formatCount(n) {
  if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M'
  if (n >= 1000) return (n / 1000).toFixed(1) + 'K'
  return String(n)
}

// ── Navigation ───────────────────────────────────────────
function goTo(idx) {
  if (idx < 0 || idx >= shorts.value.length) return
  const slides = slideEls.value
  if (slides && slides[idx]) {
    slides[idx].scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
  // Load more when near end
  if (idx >= shorts.value.length - 5) {
    loadShorts()
  }
}

// ── IntersectionObserver ─────────────────────────────────
function setupObserver() {
  if (observer) observer.disconnect()
  observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting && entry.intersectionRatio >= 0.6) {
        const idx = Number(entry.target.dataset.index)
        currentIndex.value = idx
        if (idx >= shorts.value.length - 5) loadShorts()
      }
    })
  }, { threshold: 0.6 })

  nextTick(() => {
    const slides = slideEls.value
    if (slides) slides.forEach(el => { if (el) observer.observe(el) })
  })
}

// ── Actions ──────────────────────────────────────────────
async function toggleLike(short) {
  try {
    const res = await axios.post(`/api/shorts/${short.id}/like`)
    short.liked = res.data.liked
    short.like_count = res.data.like_count
  } catch (e) { console.error(e) }
}

function openComments(short) {
  // TODO: open comment drawer
}

function shareShort(short) {
  if (navigator.share) {
    navigator.share({ title: short.title, url: window.location.href })
  } else {
    navigator.clipboard?.writeText(window.location.href)
  }
}

function saveShort(short) {
  short.saved = !short.saved
}

// ── Lifecycle ─────────────────────────────────────────────
onMounted(async () => {
  await loadShorts()
  setupObserver()
})

onUnmounted(() => {
  if (observer) observer.disconnect()
})
</script>

<style scoped>
/* ── Container: full-screen scroll-snap ── */
.sc {
  position: fixed;
  inset: 0;
  overflow-y: scroll;
  scroll-snap-type: y mandatory;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
  background: #000;
}
.sc::-webkit-scrollbar { display: none; }

/* ── Fixed header ── */
.sc-header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
  display: flex;
  align-items: center;
  padding: 12px 16px;
  background: linear-gradient(to bottom, rgba(0,0,0,0.7), transparent);
  pointer-events: none;
}
.sc-header > * { pointer-events: auto; }
.sc-back {
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
}
.sc-title {
  color: white;
  font-size: 18px;
  font-weight: 700;
  margin: 0 12px;
}
.sc-tabs {
  display: flex;
  gap: 8px;
  margin-left: auto;
}
.sc-tab {
  background: rgba(255,255,255,0.15);
  border: 1px solid rgba(255,255,255,0.3);
  color: white;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 13px;
  cursor: pointer;
  transition: background 0.2s;
}
.sc-tab.active {
  background: white;
  color: #000;
}

/* ── Each slide ── */
.sc-slide {
  position: relative;
  width: 100vw;
  height: 100dvh;
  scroll-snap-align: start;
  scroll-snap-stop: always;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #000;
}

/* ── Blurred background ── */
.sc-bg {
  position: absolute;
  inset: 0;
  background-size: cover;
  background-position: center;
  filter: blur(20px) brightness(0.4);
  transform: scale(1.1);
  z-index: 0;
}

/* ── Video box ── */
.sc-video {
  position: relative;
  z-index: 2;
  width: min(100vw, calc(100dvh * 9 / 16));
  aspect-ratio: 9 / 16;
  max-height: 100dvh;
  background: #000;
}
.sc-video iframe {
  width: 100%;
  height: 100%;
  border: none;
  display: block;
}
.sc-novideo {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 16px;
  text-align: center;
  padding: 20px;
}

/* ── Info overlay ── */
.sc-info {
  position: absolute;
  bottom: 80px;
  left: 12px;
  right: 80px;
  z-index: 10;
  pointer-events: none;
}
.sc-channel {
  color: white;
  font-size: 15px;
  font-weight: 700;
  margin-bottom: 4px;
  text-shadow: 0 1px 4px rgba(0,0,0,0.8);
}
.sc-desc {
  color: rgba(255,255,255,0.9);
  font-size: 13px;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-shadow: 0 1px 4px rgba(0,0,0,0.8);
}
.sc-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 6px;
}
.sc-tag {
  color: #7ecfff;
  font-size: 12px;
}

/* ── Action buttons ── */
.sc-actions {
  position: absolute;
  right: 10px;
  bottom: 100px;
  z-index: 10;
  display: flex;
  flex-direction: column;
  gap: 20px;
  align-items: center;
}
.sc-act-btn {
  background: none;
  border: none;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  color: white;
  font-size: 11px;
  text-shadow: 0 1px 4px rgba(0,0,0,0.8);
  filter: drop-shadow(0 1px 4px rgba(0,0,0,0.8));
}

/* ── Nav buttons ── */
.sc-nav {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  z-index: 10;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.sc-nav-btn {
  background: rgba(0,0,0,0.4);
  border: none;
  border-radius: 50%;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  backdrop-filter: blur(4px);
}

/* ── Progress ── */
.sc-progress {
  position: absolute;
  top: 60px;
  right: 12px;
  z-index: 10;
  background: rgba(0,0,0,0.4);
  border-radius: 10px;
  padding: 2px 8px;
}
.sc-progress-text {
  color: rgba(255,255,255,0.7);
  font-size: 11px;
}

/* ── Loading ── */
.sc-loading {
  position: fixed;
  inset: 0;
  z-index: 200;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #000;
}
.sc-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid rgba(255,255,255,0.2);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>