import { defineStore } from "pinia"
import { ref, computed } from "vue"

export const useMusicStore = defineStore("music", () => {
  const currentTrack = ref(null)    // { id, title, artist, thumbnail, youtubeId }
  const playlist = ref([])
  const isPlaying = ref(false)
  const currentTime = ref(0)
  const duration = ref(0)
  const currentIndex = ref(-1)

  function play(track, list = null) {
    if (list) playlist.value = list
    currentTrack.value = track
    const idx = playlist.value.findIndex(t => t.id === track.id)
    if (idx >= 0) currentIndex.value = idx
    isPlaying.value = true
  }

  function pause() { isPlaying.value = false }
  function resume() { isPlaying.value = true }
  function toggle() { isPlaying.value = !isPlaying.value }

  function next() {
    if (playlist.value.length === 0) return
    currentIndex.value = (currentIndex.value + 1) % playlist.value.length
    currentTrack.value = playlist.value[currentIndex.value]
    isPlaying.value = true
  }

  function prev() {
    if (playlist.value.length === 0) return
    currentIndex.value = (currentIndex.value - 1 + playlist.value.length) % playlist.value.length
    currentTrack.value = playlist.value[currentIndex.value]
    isPlaying.value = true
  }

  function stop() {
    isPlaying.value = false
    currentTrack.value = null
    currentIndex.value = -1
  }

  const hasTrack = computed(() => !!currentTrack.value)
  const progress = computed(() => duration.value ? (currentTime.value / duration.value) * 100 : 0)

  return {
    currentTrack, playlist, isPlaying, currentTime, duration, currentIndex,
    play, pause, resume, toggle, next, prev, stop, hasTrack, progress
  }
})
