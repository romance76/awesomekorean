import { ref } from 'vue'
import axios from 'axios'

// 회원 등급 15단계 목록(이름·필요 포인트·링 이미지). 앱 전체에서 한 번만 불러와 공유한다.
const tiers = ref([])
let loading = null

function load() {
  if (!loading) {
    loading = axios.get('/api/member-grades')
      .then(({ data }) => { tiers.value = data.data || [] })
      .catch(() => { loading = null })
  }
  return loading
}

export function useMemberGrades() {
  load()
  const tierOf = (level) => tiers.value.find(t => t.level === Number(level)) || null
  return { tiers, tierOf, ready: loading }
}
