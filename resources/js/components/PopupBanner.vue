<template>
<Teleport to="body">
  <div v-if="current" class="fixed inset-0 z-[9998] flex items-center justify-center bg-black/60 p-4"
    @click.self="close">
    <div class="relative bg-white rounded-xl shadow-2xl overflow-hidden max-w-[95vw] max-h-[90vh]"
      :style="modalStyle">
      <!-- 닫기 버튼 -->
      <button @click="close" class="absolute top-2 right-2 w-8 h-8 rounded-full bg-black/40 hover:bg-black/60 text-white flex items-center justify-center z-10">✕</button>

      <!-- 이미지 타입 -->
      <div v-if="current.type === 'image' && current.image_url" @click="clickBanner" class="cursor-pointer">
        <img :src="current.image_url" :alt="current.title" class="block max-w-full max-h-[90vh]" />
      </div>

      <!-- 텍스트 타입 -->
      <div v-else-if="current.type === 'text'" @click="clickBanner"
        :class="current.link_url ? 'cursor-pointer' : ''"
        class="w-full h-full overflow-auto p-6"
        v-html="current.content"></div>

      <!-- 하루 1회 체크박스 (하단) -->
      <div v-if="current.display_mode === 'once_per_day'" class="absolute bottom-0 left-0 right-0 bg-gray-900/80 text-white text-xs px-4 py-2 flex items-center justify-between">
        <label class="flex items-center gap-2 cursor-pointer select-none">
          <input type="checkbox" v-model="hideToday" class="accent-amber-400" />
          오늘 하루 이 창을 열지 않음
        </label>
        <button @click="close" class="text-white/70 hover:text-white">닫기</button>
      </div>
    </div>
  </div>
</Teleport>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'

const router = useRouter()
const queue = ref([])
const currentIdx = ref(0)
const hideToday = ref(false)

const current = computed(() => queue.value[currentIdx.value] || null)

const modalStyle = computed(() => {
  if (!current.value || current.value.type !== 'text') return {}
  return {
    width: (current.value.width || 500) + 'px',
    height: (current.value.height || 300) + 'px',
  }
})

function today() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
}

function storageKey(banner) {
  return `popup_hide_${banner.id}_${today()}`
}

function shouldShow(banner) {
  if (!banner.is_active) return false
  if (banner.display_mode !== 'once_per_day') return true
  try { return localStorage.getItem(storageKey(banner)) !== '1' } catch { return true }
}

function close() {
  const b = current.value
  if (!b) return
  if (hideToday.value || b.display_mode === 'once_per_day') {
    try { localStorage.setItem(storageKey(b), '1') } catch {}
  }
  hideToday.value = false
  if (currentIdx.value < queue.value.length - 1) {
    currentIdx.value++
  } else {
    queue.value = []
    currentIdx.value = 0
  }
}

function clickBanner() {
  const b = current.value
  if (!b || !b.link_url) return
  if (b.display_mode === 'once_per_day') {
    try { localStorage.setItem(storageKey(b), '1') } catch {}
  }
  if (b.link_url.startsWith('http')) {
    window.open(b.link_url, '_blank')
  } else {
    router.push(b.link_url)
  }
  close()
}

async function loadBanners() {
  try {
    const { data } = await axios.get('/api/popup-banners/active')
    const items = (data.data || []).filter(shouldShow)
    if (items.length) {
      queue.value = items
      currentIdx.value = 0
    }
  } catch {}
}

onMounted(() => {
  // 조용한 UX: 200ms 지연 후 표시 (앱 마운트 직후 깜빡임 방지)
  setTimeout(loadBanners, 200)
})
</script>
