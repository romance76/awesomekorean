<template>
<Teleport to="body">
  <div v-if="show" class="fixed inset-0 bg-black/60 z-50 flex items-end lg:items-center justify-center" @click.self="$emit('close')">
    <div class="bg-white w-full lg:max-w-2xl lg:rounded-2xl rounded-t-2xl shadow-2xl overflow-hidden flex flex-col"
      style="max-height: 90vh; max-height: 90dvh;">

      <!-- 헤더 -->
      <div class="px-4 py-3 border-b border-gray-50 flex items-center justify-between flex-shrink-0">
        <h3 class="font-bold text-sm text-ink flex items-center gap-2">
          <span class="icon-chip w-7 h-7 bg-emerald-50 text-emerald-600"><AppIcon name="graduation-cap" :size="15" /></span>
          주변 학교 (5마일 이내)
        </h3>
        <button @click="$emit('close')" class="text-ink-faint hover:text-ink p-1 transition-colors"><AppIcon name="x" :size="18" /></button>
      </div>

      <!-- 지도 -->
      <div class="flex-shrink-0 relative">
        <div v-if="mapLoading" class="flex items-center justify-center bg-gray-100 text-ink-muted text-sm" style="height:220px;">
          지도 로딩중...
        </div>
        <div v-show="!mapLoading" ref="mapEl" style="height:220px; width:100%; z-index:0;"></div>
      </div>

      <!-- 학교 목록 -->
      <div class="flex-1 overflow-y-auto min-h-0">
        <!-- 로딩 -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-12 gap-2">
          <div class="animate-spin w-6 h-6 border-4 border-emerald-500 border-t-transparent rounded-full"></div>
          <span class="text-sm text-ink-muted">학교 검색중...</span>
        </div>

        <!-- 결과 없음 -->
        <div v-else-if="!schools.length" class="py-12 text-center">
          <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="graduation-cap" :size="28" :stroke-width="1.5" /></div>
          <p class="text-sm text-ink-muted">주변 5마일 이내에 학교가 없습니다</p>
        </div>

        <!-- 학교 카드 목록 -->
        <div v-else class="divide-y divide-gray-50">
          <button v-for="(s, i) in schools" :key="i" @click="focusSchool(s, i)"
            class="w-full text-left px-4 py-3 hover:bg-emerald-50/50 transition-colors flex items-start gap-3"
            :class="selectedIdx === i ? 'bg-emerald-50' : ''">
            <!-- 번호 -->
            <div class="w-6 h-6 rounded-full flex-shrink-0 flex items-center justify-center text-[11px] font-bold mt-0.5"
              :class="selectedIdx === i ? 'bg-emerald-500 text-white' : 'bg-gray-100 text-ink-muted'">
              {{ i + 1 }}
            </div>
            <!-- 정보 -->
            <div class="flex-1 min-w-0">
              <div class="text-sm font-bold text-ink truncate">{{ s.name }}</div>
              <div class="text-xs text-ink-muted mt-0.5 truncate flex items-center gap-0.5"><AppIcon name="map-pin" :size="11" />{{ s.address }}</div>
              <div class="flex items-center gap-2 mt-1 flex-wrap">
                <!-- 별점 -->
                <div v-if="s.rating" class="flex items-center gap-0.5">
                  <span class="text-amber-400 text-xs">{{ '★'.repeat(Math.round(s.rating)) }}</span>
                  <span class="text-xs font-bold text-ink">{{ s.rating }}</span>
                  <span class="text-[11px] text-ink-faint">({{ s.user_ratings_total }})</span>
                </div>
                <!-- 학교 유형 뱃지 -->
                <span v-for="t in schoolTypeBadges(s.types)" :key="t"
                  class="text-[11px] px-1.5 py-0.5 rounded-full font-semibold"
                  :class="typeBadgeClass(t)">
                  {{ t }}
                </span>
              </div>
            </div>
            <!-- 거리 -->
            <div class="text-[11px] text-ink-faint flex-shrink-0 mt-1">
              {{ calcDistance(s.lat, s.lng) }}
            </div>
          </button>
        </div>
      </div>

      <!-- 푸터 -->
      <div class="px-4 py-2 border-t border-gray-50 bg-gray-50 text-[11px] text-ink-faint text-center flex-shrink-0">
        Google Places API 기반 · 매물 위치 반경 5마일 · {{ schools.length }}개 학교
      </div>
    </div>
  </div>
</Teleport>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'
import AppIcon from './AppIcon.vue'
import axios from 'axios'

const props = defineProps({
  show: Boolean,
  lat: [Number, String],
  lng: [Number, String],
})
const emit = defineEmits(['close'])

const schools = ref([])
const loading = ref(false)
const mapLoading = ref(true)
const mapEl = ref(null)
const selectedIdx = ref(-1)

let mapInstance = null
let markers = []
let leafletLoaded = false

// Leaflet CDN 동적 로드
async function ensureLeaflet() {
  if (leafletLoaded && window.L) return

  // CSS
  if (!document.querySelector('link[href*="leaflet"]')) {
    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
    document.head.appendChild(link)
  }

  // JS
  if (!window.L) {
    await new Promise((resolve, reject) => {
      const script = document.createElement('script')
      script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
      script.onload = resolve
      script.onerror = reject
      document.head.appendChild(script)
    })
  }

  leafletLoaded = true
}

function schoolTypeBadges(types) {
  if (!types) return []
  const badges = []
  if (types.includes('primary_school') || types.includes('elementary_school')) badges.push('Elementary')
  if (types.includes('secondary_school')) badges.push('Middle/High')
  if (types.includes('university')) badges.push('University')
  if (!badges.length && types.includes('school')) badges.push('School')
  return badges
}

function typeBadgeClass(type) {
  if (type === 'Elementary') return 'bg-blue-100 text-blue-700'
  if (type === 'Middle/High') return 'bg-purple-100 text-purple-700'
  if (type === 'University') return 'bg-red-100 text-red-700'
  return 'bg-gray-100 text-gray-600'
}

function calcDistance(sLat, sLng) {
  if (!sLat || !sLng || !props.lat || !props.lng) return ''
  const R = 3959 // miles
  const dLat = (sLat - props.lat) * Math.PI / 180
  const dLng = (sLng - props.lng) * Math.PI / 180
  const a = Math.sin(dLat/2)**2 + Math.cos(props.lat*Math.PI/180) * Math.cos(sLat*Math.PI/180) * Math.sin(dLng/2)**2
  const d = R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a))
  return d < 0.1 ? '근처' : d.toFixed(1) + 'mi'
}

async function initMap() {
  await ensureLeaflet()
  mapLoading.value = false
  await nextTick()

  if (!mapEl.value || !window.L) return

  const lat = Number(props.lat)
  const lng = Number(props.lng)

  mapInstance = window.L.map(mapEl.value, { zoomControl: false }).setView([lat, lng], 13)
  window.L.control.zoom({ position: 'topright' }).addTo(mapInstance)

  window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
    maxZoom: 18,
  }).addTo(mapInstance)

  // 매물 위치 (빨간 마커)
  const homeIcon = window.L.divIcon({
    className: '',
    html: '<div style="background:#ef4444;width:14px;height:14px;border-radius:50%;border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.3);"></div>',
    iconSize: [14, 14],
    iconAnchor: [7, 7],
  })
  window.L.marker([lat, lng], { icon: homeIcon }).addTo(mapInstance).bindPopup('<b>📍 매물 위치</b>')
}

function addSchoolMarkers() {
  if (!mapInstance || !window.L) return

  // 기존 마커 제거
  markers.forEach(m => mapInstance.removeLayer(m))
  markers = []

  schools.value.forEach((s, i) => {
    if (!s.lat || !s.lng) return
    const icon = window.L.divIcon({
      className: '',
      html: `<div style="background:#22c55e;color:white;width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:bold;border:2px solid white;box-shadow:0 2px 4px rgba(0,0,0,0.3);">${i + 1}</div>`,
      iconSize: [22, 22],
      iconAnchor: [11, 11],
    })
    const marker = window.L.marker([s.lat, s.lng], { icon })
      .addTo(mapInstance)
      .bindPopup(`<b>${s.name}</b><br><span style="font-size:11px;">${s.address || ''}</span>${s.rating ? '<br>⭐ ' + s.rating : ''}`)
    markers.push(marker)
  })

  // Fit bounds
  if (schools.value.length) {
    const allPoints = [[Number(props.lat), Number(props.lng)], ...schools.value.filter(s => s.lat && s.lng).map(s => [s.lat, s.lng])]
    mapInstance.fitBounds(allPoints, { padding: [30, 30] })
  }
}

function focusSchool(s, i) {
  selectedIdx.value = i
  if (mapInstance && s.lat && s.lng) {
    mapInstance.setView([s.lat, s.lng], 15, { animate: true })
    if (markers[i]) markers[i].openPopup()
  }
}

watch(() => props.show, async (v) => {
  if (!v) {
    // cleanup
    if (mapInstance) { mapInstance.remove(); mapInstance = null }
    markers = []
    schools.value = []
    selectedIdx.value = -1
    mapLoading.value = true
    return
  }

  if (!props.lat || !props.lng) return

  loading.value = true

  // 지도 + API 병렬
  const [_, schoolsRes] = await Promise.all([
    initMap(),
    axios.get('/api/places/nearby-schools', { params: { lat: props.lat, lng: props.lng } }).catch(() => ({ data: { data: [] } })),
  ])

  schools.value = schoolsRes.data?.data || []
  loading.value = false

  await nextTick()
  addSchoolMarkers()
})
</script>
