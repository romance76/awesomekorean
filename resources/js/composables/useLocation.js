import { ref, computed } from 'vue'

const city = ref(null)
const radius = ref('30')

// 한인 밀집 주요 도시 목록
const KOREAN_CITIES = [
  { name: 'Los Angeles', state: 'CA', lat: 34.0522, lng: -118.2437, label: 'LA (한인타운)' },
  { name: 'New York', state: 'NY', lat: 40.7128, lng: -74.0060, label: '뉴욕' },
  { name: 'Bergen County', state: 'NJ', lat: 40.9176, lng: -74.0712, label: '뉴저지 (버겐)' },
  { name: 'Atlanta', state: 'GA', lat: 33.7490, lng: -84.3880, label: '아틀란타' },
  { name: 'Chicago', state: 'IL', lat: 41.8781, lng: -87.6298, label: '시카고' },
  { name: 'Dallas', state: 'TX', lat: 32.7767, lng: -96.7970, label: '달라스' },
  { name: 'Houston', state: 'TX', lat: 29.7604, lng: -95.3698, label: '휴스턴' },
  { name: 'Seattle', state: 'WA', lat: 47.6062, lng: -122.3321, label: '시애틀' },
  { name: 'San Francisco', state: 'CA', lat: 37.7749, lng: -122.4194, label: '샌프란시스코' },
  { name: 'Washington', state: 'DC', lat: 38.9072, lng: -77.0369, label: '워싱턴 DC' },
  { name: 'Philadelphia', state: 'PA', lat: 39.9526, lng: -75.1652, label: '필라델피아' },
  { name: 'Irvine', state: 'CA', lat: 33.6846, lng: -117.8265, label: '어바인 (OC)' },
  { name: 'Fullerton', state: 'CA', lat: 33.8703, lng: -117.9242, label: '풀러턴' },
  { name: 'Flushing', state: 'NY', lat: 40.7654, lng: -73.8328, label: '플러싱 (퀸즈)' },
  { name: 'Honolulu', state: 'HI', lat: 21.3069, lng: -157.8583, label: '호놀룰루' },
  { name: 'Las Vegas', state: 'NV', lat: 36.1699, lng: -115.1398, label: '라스베가스' },
  { name: 'Denver', state: 'CO', lat: 39.7392, lng: -104.9903, label: '덴버' },
]

export function useLocation() {
  // 매 페이지 진입 시 프로필 기본 위치로 리셋 (auth store에서 읽음)
  function init() {
    try {
      const userStr = localStorage.getItem('sk_user')
      if (!userStr) return
      const u = JSON.parse(userStr)
      if (u.default_radius) radius.value = String(u.default_radius)
      if (u.city && u.state && (u.latitude || u.lat)) {
        city.value = {
          name: u.city, state: u.state,
          lat: parseFloat(u.latitude || u.lat || 0),
          lng: parseFloat(u.longitude || u.lng || 0),
          label: u.city,
        }
      } else {
        city.value = null
      }
    } catch {}
  }

  function setCity(c) { city.value = c }

  function selectKoreanCity(index) {
    if (index === -1) {
      city.value = null
      radius.value = '0'
    } else {
      city.value = KOREAN_CITIES[index]
      radius.value = '30'
    }
  }

  function setRadius(r) { radius.value = r }

  const locationQuery = computed(() => {
    if (radius.value === '0') return {}
    if (!city.value?.lat) return {}
    return { lat: city.value.lat, lng: city.value.lng, radius: parseInt(radius.value) }
  })

  const displayText = computed(() => {
    if (radius.value === '0') return '전국'
    if (!city.value) return '위치 선택'
    return city.value.label || (city.value.name + ', ' + city.value.state)
  })

  return {
    city, radius, locationQuery, displayText,
    koreanCities: KOREAN_CITIES,
    init, setCity, setRadius, selectKoreanCity,
  }
}
