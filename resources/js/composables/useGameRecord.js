import { ref } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../stores/auth'
import { useSiteStore } from '../stores/site'

/**
 * 게임 레벨별 시간 기록 + 포인트 지급 + 리더보드 공통 로직
 *
 * 사용법:
 *   const rec = useGameRecord('flag')
 *   // 게임 시작 시:
 *   rec.start(level.value)
 *   // 게임 종료 시 (won = 이겼는지, leveledUp = 이번 판에서 레벨업 했는지):
 *   await rec.end({ won, leveledUp, score })
 *   // 템플릿:
 *   rec.elapsedMs / rec.pointsEarned / rec.newRecord / rec.prevTimeMs / rec.recordLevel / rec.lbRef
 *
 * 결과 화면에 이렇게 바로 꽂을 수 있음:
 *   <GameLeaderboard :ref="rec.lbRef" slug="flag" :level="rec.recordLevel" />
 */
export function useGameRecord(slug) {
  const auth = useAuthStore()
  const siteStore = useSiteStore()

  const startAt = ref(0)
  const elapsedMs = ref(0)
  const recordLevel = ref(1)
  const pointsEarned = ref(0)
  const newRecord = ref(false)
  const prevTimeMs = ref(null)
  const lbRef = ref(null)

  function start(level) {
    recordLevel.value = Number(level) || 1
    pointsEarned.value = 0
    newRecord.value = false
    prevTimeMs.value = null
    elapsedMs.value = 0
    startAt.value = Date.now()
  }

  async function end({ won, leveledUp = false, score = 0 } = {}) {
    elapsedMs.value = Date.now() - startAt.value
    if (!auth.isLoggedIn || !won) return
    try {
      const { data } = await axios.post('/api/games/result', {
        game_slug: slug,
        level: recordLevel.value,
        time_ms: elapsedMs.value,
        score,
        won: true,
        leveled_up: !!leveledUp,
      })
      const r = data.data || {}
      pointsEarned.value = r.points_earned || 0
      newRecord.value = !!r.new_record
      prevTimeMs.value = r.prev_time_ms
      if (pointsEarned.value > 0) {
        siteStore.toast(`+${pointsEarned.value}P 획득!`, 'success')
        if (auth.user) auth.user.points = r.balance ?? auth.user.points
      }
      lbRef.value?.reload?.()
    } catch {}
  }

  function formatTime(ms) {
    if (!ms) return '-'
    return (Math.round(ms / 10) / 100).toFixed(2) + '초'
  }

  return {
    startAt, elapsedMs, recordLevel, pointsEarned, newRecord, prevTimeMs, lbRef,
    start, end, formatTime,
  }
}
