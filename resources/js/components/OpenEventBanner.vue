<template>
  <div v-if="info && !closed" class="bg-gradient-to-r from-amber-500 to-amber-500 text-white">
    <div class="max-w-6xl mx-auto px-4 py-2 flex items-center gap-3">
      <div class="min-w-0 flex-1 text-xs sm:text-sm leading-snug">
        <span class="font-bold">🎉 {{ info.headline }}</span>
        <span v-if="info.subline" class="opacity-95"> · {{ info.subline }}</span>
        <span class="opacity-80 hidden sm:inline"> · {{ endLabel }}까지</span>
      </div>
      <button type="button" @click="close" class="shrink-0 text-white/80 hover:text-white text-lg leading-none px-1" aria-label="닫기">×</button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const info = ref(null)
const closed = ref(false)

const KEY = 'ak_open_event_closed'
function todayStr() {
  const d = new Date()
  return `${d.getFullYear()}-${d.getMonth() + 1}-${d.getDate()}`
}

const endLabel = computed(() => {
  const m = /^\d{4}-(\d{2})-(\d{2})$/.exec(info.value?.ends_on || '')
  return m ? `${Number(m[1])}월 ${Number(m[2])}일` : ''
})

function close() {
  closed.value = true
  try { localStorage.setItem(KEY, todayStr()) } catch {}
}

onMounted(async () => {
  try { closed.value = localStorage.getItem(KEY) === todayStr() } catch {}
  if (closed.value) return
  try {
    const { data } = await axios.get('/api/open-event')
    if (data?.data?.active) info.value = data.data
  } catch {}
})
</script>
