// 관리자 즐겨찾기(자주 쓰는 메뉴) — 서버(GET/PUT /api/admin/favorites)에 저장해 PC 와 휴대폰이 같은 구성을 본다.
// 서버가 안 되면(오프라인·표 준비 전) 이 브라우저(localStorage)에 임시로 두고 계속 쓸 수 있다.
import { ref } from 'vue'
import axios from 'axios'

const KEY = 'admin_favorites_v1'
const MAX = 12
const favs = ref(readLocal())
let loaded = false
let loading = null

function readLocal() {
  try { const v = JSON.parse(localStorage.getItem(KEY) || '[]'); return Array.isArray(v) ? v.filter(x => typeof x === 'string').slice(0, MAX) : [] } catch { return [] }
}
function writeLocal(list) { try { localStorage.setItem(KEY, JSON.stringify(list)) } catch {} }

async function load(force = false) {
  if (loaded && !force) return
  if (loading) return loading
  loading = (async () => {
    try {
      const { data } = await axios.get('/api/admin/favorites')
      const list = Array.isArray(data?.data) ? data.data : []
      // 서버에 아직 아무것도 없고 이 브라우저에 예전 목록이 있으면 서버로 올려 준다(처음 한 번)
      if (!list.length && favs.value.length) await save(favs.value)
      else { favs.value = list.slice(0, MAX); writeLocal(favs.value) }
      loaded = true
    } catch { /* 서버가 안 되면 이 브라우저 목록을 그대로 쓴다 */ }
    loading = null
  })()
  return loading
}

async function save(list) {
  writeLocal(list)
  try { await axios.put('/api/admin/favorites', { items: list }) } catch { /* 다음 변경 때 다시 시도 */ }
}

export function useAdminFavorites() {
  const isFav = (to) => favs.value.includes(to)
  async function toggle(to) {
    const next = isFav(to) ? favs.value.filter(x => x !== to) : [...favs.value, to].slice(-MAX)
    favs.value = next
    await save(next)
  }
  return { favs, isFav, toggle, load }
}
