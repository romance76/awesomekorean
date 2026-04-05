import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useMusicStore = defineStore('music', () => {
  const currentTrack = ref(null)
  const isPlaying = ref(false)
  const playlist = ref([])
  const volume = ref(80)

  const hasTrack = computed(() => !!currentTrack.value)

  function play(track) { currentTrack.value = track; isPlaying.value = true }
  function pause() { isPlaying.value = false }
  function stop() { currentTrack.value = null; isPlaying.value = false }
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

  return { currentTrack, isPlaying, playlist, volume, hasTrack, play, pause, stop, next, prev, addToPlaylist }
})
