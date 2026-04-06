<template>
<div class="fixed inset-0 bg-black z-40 flex flex-col">
  <!-- 상단 바 -->
  <div class="absolute top-0 left-0 right-0 z-50 flex items-center justify-between px-4 py-3">
    <RouterLink to="/" class="text-white text-sm font-bold opacity-80 hover:opacity-100">← 홈</RouterLink>
    <h1 class="text-white font-black text-sm">📱 숏츠</h1>
    <RouterLink v-if="auth.isLoggedIn" to="/shorts/upload" class="text-white text-sm opacity-80 hover:opacity-100">+ 업로드</RouterLink>
    <span v-else></span>
  </div>

  <!-- 메인 비디오 영역 -->
  <div v-if="loading" class="flex-1 flex items-center justify-center text-white">로딩중...</div>
  <div v-else-if="!shorts.length" class="flex-1 flex items-center justify-center text-white text-sm">숏츠가 없습니다</div>
  <div v-else class="flex-1 relative overflow-hidden">
    <!-- 현재 비디오 -->
    <div class="w-full h-full flex items-center justify-center">
      <div class="w-full max-w-md h-full max-h-[90vh] relative">
        <iframe
          :key="current.youtube_id"
          :src="`https://www.youtube.com/embed/${current.youtube_id}?autoplay=1&loop=1&controls=1&modestbranding=1`"
          class="w-full h-full rounded-xl"
          frameborder="0"
          allow="autoplay; encrypted-media"
          allowfullscreen
        ></iframe>

        <!-- 오른쪽 액션 버튼 -->
        <div class="absolute right-3 bottom-32 flex flex-col items-center gap-5">
          <button @click="toggleLike" class="flex flex-col items-center">
            <div class="w-10 h-10 bg-white/20 backdrop-blur rounded-full flex items-center justify-center text-xl">{{ liked ? '❤️' : '🤍' }}</div>
            <span class="text-white text-[10px] mt-1">{{ current.like_count }}</span>
          </button>
          <div class="flex flex-col items-center">
            <div class="w-10 h-10 bg-white/20 backdrop-blur rounded-full flex items-center justify-center text-xl">💬</div>
            <span class="text-white text-[10px] mt-1">{{ current.comment_count }}</span>
          </div>
          <div class="flex flex-col items-center">
            <div class="w-10 h-10 bg-white/20 backdrop-blur rounded-full flex items-center justify-center text-xl">🔗</div>
            <span class="text-white text-[10px] mt-1">공유</span>
          </div>
        </div>

        <!-- 하단 정보 -->
        <div class="absolute bottom-4 left-4 right-16">
          <div class="text-white font-bold text-sm drop-shadow">{{ current.title }}</div>
          <div class="text-white/70 text-xs mt-1">{{ current.user?.name || '익명' }}</div>
        </div>
      </div>
    </div>

    <!-- 위/아래 네비 버튼 -->
    <button @click="prev" :disabled="idx <= 0"
      class="absolute top-1/2 -translate-y-full left-1/2 -translate-x-1/2 -mt-20 w-10 h-10 bg-white/20 backdrop-blur rounded-full flex items-center justify-center text-white text-xl hover:bg-white/40 disabled:opacity-20 transition">
      ▲
    </button>
    <button @click="next" :disabled="idx >= shorts.length - 1"
      class="absolute top-1/2 translate-y-0 left-1/2 -translate-x-1/2 mt-10 w-10 h-10 bg-white/20 backdrop-blur rounded-full flex items-center justify-center text-white text-xl hover:bg-white/40 disabled:opacity-20 transition">
      ▼
    </button>

    <!-- 카운터 -->
    <div class="absolute bottom-4 right-4 text-white/50 text-xs">
      {{ idx + 1 }} / {{ shorts.length }}
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const auth = useAuthStore()
const shorts = ref([])
const idx = ref(0)
const loading = ref(true)
const liked = ref(false)

const current = computed(() => shorts.value[idx.value] || {})

function next() {
  if (idx.value < shorts.value.length - 1) { idx.value++; liked.value = false }
}
function prev() {
  if (idx.value > 0) { idx.value--; liked.value = false }
}

async function toggleLike() {
  if (!auth.isLoggedIn || !current.value.id) return
  try {
    const { data } = await axios.post(`/api/shorts/${current.value.id}/like`)
    liked.value = data.liked
    shorts.value[idx.value].like_count += data.liked ? 1 : -1
  } catch {}
}

function onKeydown(e) {
  if (e.key === 'ArrowDown' || e.key === 'j') next()
  if (e.key === 'ArrowUp' || e.key === 'k') prev()
}

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/shorts?per_page=50')
    shorts.value = data.data?.data || []
  } catch {}
  loading.value = false
  window.addEventListener('keydown', onKeydown)
})

onUnmounted(() => { window.removeEventListener('keydown', onKeydown) })
</script>
