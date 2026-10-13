// MONEY 왼쪽 메뉴용 분야·주제 목록 — 화면을 옮겨 다녀도 다시 부르지 않고 한 번만 불러 와서 같이 쓴다.
import { reactive } from 'vue'
import axios from 'axios'

export const moneyNav = reactive({ cafes: [], loaded: false, loading: false })

export async function loadMoneyNav() {
  if (moneyNav.loaded || moneyNav.loading) return
  moneyNav.loading = true
  try {
    const { data } = await axios.get('/api/money/cafes')
    // 글이 아직 하나도 없는 분야는 숨긴다 (막다른 길 방지)
    moneyNav.cafes = (data.data || []).filter(c => (c.post_count || 0) > 0)
    moneyNav.loaded = true
  } catch {}
  moneyNav.loading = false
}
