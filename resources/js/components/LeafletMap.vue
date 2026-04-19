<template>
  <div style="width:100%">
    <div v-if="loading" class="flex flex-col items-center justify-center bg-gray-100 rounded-xl text-gray-400 text-sm gap-2" style="height:280px">
      <div class="animate-spin w-6 h-6 border-4 border-blue-500 border-t-transparent rounded-full"></div>
      <span>지도 불러오는 중...</span>
    </div>
    <div v-show="!loading" ref="mapEl" style="width:100%;height:280px;border-radius:12px;z-index:0;"></div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  lat: { type: [Number, String], default: null },
  lng: { type: [Number, String], default: null },
  name: { type: String, default: '' },
  address: { type: String, default: '' }
})

const mapEl = ref(null)
const loading = ref(true)
let mapInstance = null

// Fallback coords for known Korean-American cities
const CITY_COORDS = {
  'los angeles': [34.0522, -118.2437], 'la': [34.0522, -118.2437],
  'new york': [40.7128, -74.0060],    'ny': [40.7128, -74.0060],
  'flushing': [40.7673, -73.8330],    'fort lee': [40.8501, -73.9707],
  'chicago': [41.8781, -87.6298],     'houston': [29.7604, -95.3698],
  'seattle': [47.6062, -122.3321],    'atlanta': [33.7490, -84.3880],
  'dallas': [32.7767, -96.7970],      'san francisco': [37.7749, -122.4194],
  'washington': [38.9072, -77.0369],  'las vegas': [36.1699, -115.1398],
  'boston': [42.3601, -71.0589],      'philadelphia': [39.9526, -75.1652],
  'miami': [25.7617, -80.1918],       'san diego': [32.7157, -117.1611],
  'denver': [39.7392, -104.9903],     'annandale': [38.8304, -77.1944],
  'honolulu': [21.3069, -157.8583],   'portland': [45.5051, -122.6750],
  'minneapolis': [44.9778, -93.2650], 'detroit': [42.3314, -83.0458],
  'phoenix': [33.4484, -112.0740],    'baltimore': [39.2904, -76.6122],
}

function getCityCoords(address) {
  const lower = (address || '').toLowerCase()
  for (const [city, coords] of Object.entries(CITY_COORDS)) {
    if (lower.includes(city)) return coords
  }
  return null
}

async function geocode(query) {
  const key = 'geo:' + query
  const cached = sessionStorage.getItem(key)
  if (cached) {
    const d = JSON.parse(cached)
    if (d) return d
  }
  try {
    const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=us&q=' + encodeURIComponent(query)
    const res = await fetch(url, {
      headers: { 'Accept-Language': 'en', 'User-Agent': 'AwesomeKorean/1.0' }
    })
    const data = await res.json()
    if (data && data.length > 0) {
      const result = [parseFloat(data[0].lat), parseFloat(data[0].lon)]
      sessionStorage.setItem(key, JSON.stringify(result))
      return result
    }
  } catch(e) { console.warn('Geocode error:', e) }
  sessionStorage.setItem(key, 'null')
  return null
}

onMounted(async () => {
  try {
    let lat = props.lat ? parseFloat(props.lat) : null
    let lng = props.lng ? parseFloat(props.lng) : null
    let coords = null

    if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
      coords = [lat, lng]
    } else if (props.address) {
      // 1) Try full address geocoding
      coords = await geocode(props.address)
      // 2) Try city fallback from address string
      if (!coords) coords = getCityCoords(props.address)
      // 3) Try just city,state portion (last part of address)
      if (!coords) {
        const parts = props.address.split(',')
        if (parts.length >= 2) {
          const cityState = parts.slice(-2).join(',').trim()
          coords = await geocode(cityState)
        }
      }
    }

    // Default fallback: center of USA
    if (!coords) coords = [37.0902, -95.7129]

    loading.value = false
    await new Promise(r => setTimeout(r, 80))
    if (!mapEl.value) return

    const L = (await import('leaflet')).default
    await import('leaflet/dist/leaflet.css')

    // CDN icons - avoids bundler path issues
    const base = 'https://unpkg.com/leaflet@1.9.4/dist/images/'
    L.Marker.prototype.options.icon = L.icon({
      iconUrl: base + 'marker-icon.png',
      iconRetinaUrl: base + 'marker-icon-2x.png',
      shadowUrl: base + 'marker-shadow.png',
      iconSize: [25, 41], iconAnchor: [12, 41],
      popupAnchor: [1, -34], shadowSize: [41, 41]
    })

    const zoom = (lat && lng) ? 15 : coords[2] ? coords[2] : 13
    mapInstance = L.map(mapEl.value, { scrollWheelZoom: false, zoomControl: true })
      .setView([coords[0], coords[1]], zoom)

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      maxZoom: 19
    }).addTo(mapInstance)

    if (props.address || props.name) {
      L.marker([coords[0], coords[1]]).addTo(mapInstance)
        .bindPopup(`<strong>${props.name || ''}</strong>${props.address ? '<br><small>' + props.address + '</small>' : ''}`)
        .openPopup()
    }
  } catch(e) {
    console.error('LeafletMap error:', e)
    loading.value = false
  }
})

onUnmounted(() => { if (mapInstance) { mapInstance.remove(); mapInstance = null } })
</script>
