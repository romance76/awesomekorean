import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useMusicStore = defineStore('music', () => {
  const currentTrack = ref(null)
  const isPlaying = ref(false)
  const playlist = ref([])
  const volume = ref(80)
  const progress = ref(0)
  const currentTime = ref(0) // 초 단위
  const duration = ref(0)
  const inlinePlaying = ref(false) // 음악 페이지 인라인 플레이어 사용 중

  const hasTrack = computed(() => !!currentTrack.value)
  const currentIndex = computed(() => playlist.value.findIndex(t => t.id === currentTrack.value?.id))

  function play(track) {
    if (track) currentTrack.value = track
    isPlaying.value = true
  }
  function pause() { isPlaying.value = false }
  function toggle() { isPlaying.value = !isPlaying.value }
  function stop() { currentTrack.value = null; isPlaying.value = false; progress.value = 0; currentTime.value = 0 }
  function next() {
    if (!playlist.value.length) return
    const idx = playlist.value.findIndex(t => t.id === currentTrack.value?.id)
    const nextIdx = (idx + 1) % playlist.value.length
    play(playlist.value[nextIdx])
  }
  function prev() {
    if (!playlist.value.length) return
    const idx = playlist.value.findIndex(t => t.id === currentTrack.value?.id)
    const prevIdx = (idx - 1 + playlist.value.length) % playlist.value.length
    play(playlist.value[prevIdx])
  }
  function addToPlaylist(track) {
    if (!playlist.value.find(t => t.id === track.id)) playlist.value.push(track)
  }
  function setProgress(pct, time, dur) {
    progress.value = pct
    currentTime.value = time
    duration.value = dur
  }

  return { currentTrack, isPlaying, playlist, volume, progress, currentTime, duration, hasTrack, currentIndex, inlinePlaying, play, pause, toggle, stop, next, prev, addToPlaylist, setProgress }
})
