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
  // 홈 화면 자동 재생: 처음엔 소리 없이(muted) 시작하고, 회원이 스피커를 눌러 소리를 켜면 다음 방문부터 소리 켠 채로 시작
  const muted = ref(false)
  const autoStarted = ref(false)   // 홈이 자동으로 시작한 재생인지 (회원이 직접 고른 곡이면 false)
  const homeAutoDone = ref(false)  // 이번 접속에서 홈 자동 재생을 이미 했는지 (플레이어를 닫은 뒤 홈에 다시 와도 또 켜지 않음)
  const toggleTick = ref(0)        // 다른 화면에서 재생/일시정지를 요청할 때 올림 → 공용 플레이어가 실제 플레이어를 제어

  const hasTrack = computed(() => !!currentTrack.value)
  const currentIndex = computed(() => playlist.value.findIndex(t => t.id === currentTrack.value?.id))

  // keep=true: 홈 자동 재생·목록 자동 넘김처럼 '회원이 고른 게 아닌' 재생 — 음소거 상태를 그대로 유지.
  // 그 외(회원이 곡을 직접 고름)는 소리를 켠 상태로 시작
  function play(track, opts) {
    if (track) currentTrack.value = track
    isPlaying.value = true
    if (!opts?.keep) { autoStarted.value = false; muted.value = false }
  }
  function soundPref() { try { return localStorage.getItem('ak_music_sound') === '1' } catch { return false } }
  function setMuted(v) {   // 회원이 스피커를 눌러 바꾼 값 — 다음 방문에도 기억
    muted.value = !!v
    try { localStorage.setItem('ak_music_sound', v ? '0' : '1') } catch {}
  }
  function requestToggle() { toggleTick.value++ }
  function pause() { isPlaying.value = false }
  function toggle() { isPlaying.value = !isPlaying.value }
  function stop() { autoStarted.value = false; muted.value = false; currentTrack.value = null; isPlaying.value = false; progress.value = 0; currentTime.value = 0 }
  function next() {
    if (!playlist.value.length) return
    const idx = playlist.value.findIndex(t => t.id === currentTrack.value?.id)
    const nextIdx = (idx + 1) % playlist.value.length
    play(playlist.value[nextIdx], { keep: true })
  }
  function prev() {
    if (!playlist.value.length) return
    const idx = playlist.value.findIndex(t => t.id === currentTrack.value?.id)
    const prevIdx = (idx - 1 + playlist.value.length) % playlist.value.length
    play(playlist.value[prevIdx], { keep: true })
  }
  function addToPlaylist(track) {
    if (!playlist.value.find(t => t.id === track.id)) playlist.value.push(track)
  }
  function setProgress(pct, time, dur) {
    progress.value = pct
    currentTime.value = time
    duration.value = dur
  }

  return { currentTrack, isPlaying, playlist, volume, progress, currentTime, duration, hasTrack, currentIndex, inlinePlaying, muted, autoStarted, homeAutoDone, toggleTick, soundPref, setMuted, requestToggle, play, pause, toggle, stop, next, prev, addToPlaylist, setProgress }
})
