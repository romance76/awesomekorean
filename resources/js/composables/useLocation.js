import { ref, computed } from 'vue'

const STORAGE_KEY = 'sk_location'
const city = ref(null)
const radius = ref('30mi')
const initialized = ref(false)

export function useLocation() {
  async function init() {
    if (initialized.value) return
    initialized.value = true
    const cached = localStorage.getItem(STORAGE_KEY)
    if (cached) { city.value = JSON.parse(cached); return }
    try {
      const token = localStorage.getItem('sk_token')
      if (!token) return
      const res = await fetch('/api/user/location', { headers: { Authorization: 'Bearer ' + token } })
      const data = await res.json()
      if (data.city) {
        city.value = data.city
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data.city))
        return
      }
    } catch {}
    // Fallback: try /api/auth/me
    try {
      const token = localStorage.getItem('sk_token')
      if (!token) return
      const res = await fetch('/api/auth/me', { headers: { Authorization: 'Bearer ' + token } })
      const data = await res.json()
      const u = data.user || data.data || data
      if (u.city && u.state) {
        const c = { name: u.city, state: u.state, lat: u.latitude ? parseFloat(u.latitude) : (u.lat ? parseFloat(u.lat) : null), lng: u.longitude ? parseFloat(u.longitude) : (u.lng ? parseFloat(u.lng) : null) }
        city.value = c
        localStorage.setItem(STORAGE_KEY, JSON.stringify(c))
      }
    } catch {}
  }

  function setCity(c) { city.value = c; localStorage.setItem(STORAGE_KEY, JSON.stringify(c)) }
  function setRadius(r) { radius.value = r }

  const locationQuery = computed(() => {
    if (radius.value === '\uc804\uad6d') return {}
    if (!city.value?.lat) return {}
    return { lat: city.value.lat, lng: city.value.lng, radius: parseInt(radius.value) }
  })

  const displayText = computed(() => {
    if (radius.value === '\uc804\uad6d') return '\uc804\uad6d'
    if (!city.value) return '\uc704\uce58 \uc120\ud0dd'
    return city.value.name + ', ' + city.value.state
  })

  return { city, radius, locationQuery, displayText, init, setCity, setRadius }
}
