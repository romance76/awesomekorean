<template>
  <div class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center" @click.self="$emit('close')">
    <div class="bg-white w-full sm:max-w-lg sm:rounded-2xl rounded-t-2xl max-h-[80vh] overflow-y-auto">
      <div class="w-10 h-1 bg-gray-300 rounded-full mx-auto mt-3 sm:hidden"></div>
      <div class="p-5">
        <div class="flex gap-2 mb-4">
          <button @click="activeTab='preset'" :class="['flex-1 py-2 rounded-lg text-sm font-semibold transition', activeTab==='preset' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600']">한인 밀집 도시</button>
          <button @click="activeTab='direct'" :class="['flex-1 py-2 rounded-lg text-sm font-semibold transition', activeTab==='direct' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600']">직접 입력</button>
        </div>

        <div v-if="activeTab==='preset'">
          <div v-for="region in regions" :key="region.name" class="mb-4">
            <h3 class="text-xs font-bold text-gray-500 mb-2">{{ region.name }}</h3>
            <div class="flex flex-wrap gap-1.5">
              <button v-for="c in region.cities" :key="c.name"
                @click="$emit('select', c)"
                :class="['px-3 py-1.5 rounded-full text-xs border transition',
                  currentCity?.name===c.name ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-200 hover:border-blue-400']">
                {{ c.label }}
              </button>
            </div>
          </div>
        </div>

        <div v-if="activeTab==='direct'" class="space-y-3">
          <p class="text-xs text-gray-500">도시명 또는 ZIP Code를 입력하세요</p>
          <div class="flex gap-2">
            <input v-model="directInput" placeholder="예: Suwanee, GA 또는 30024" @input="onInput"
              class="flex-1 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            <button @click="applyDirect" class="bg-blue-600 text-white px-4 py-2.5 rounded-lg text-sm font-semibold">적용</button>
          </div>
          <div v-if="zipResolved" class="text-sm text-green-600">✓ ZIP {{ directInput }} → <strong>{{ zipResolved.name }}, {{ zipResolved.state }}</strong></div>
          <div v-if="zipLoading" class="text-sm text-gray-400">조회 중...</div>
          <div v-if="zipError" class="text-sm text-red-500">ZIP Code를 찾을 수 없어요</div>
        </div>

        <button @click="$emit('close')" class="w-full mt-4 py-2.5 bg-gray-100 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-200">닫기</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
defineProps({ currentCity: Object })
const emit = defineEmits(['select','close'])
const activeTab = ref('preset')
const directInput = ref('')
const zipResolved = ref(null)
const zipLoading = ref(false)
const zipError = ref(false)
let timer = null

const regions = [
  { name: '서부', cities: [
    { name:'Los Angeles',state:'CA',label:'LA·로스앤젤레스',lat:34.0522,lng:-118.2437 },
    { name:'Orange County',state:'CA',label:'OC·오렌지카운티',lat:33.7175,lng:-117.8311 },
    { name:'San Diego',state:'CA',label:'샌디에고',lat:32.7157,lng:-117.1611 },
    { name:'San Francisco',state:'CA',label:'샌프란시스코',lat:37.7749,lng:-122.4194 },
    { name:'Seattle',state:'WA',label:'시애틀',lat:47.6062,lng:-122.3321 },
    { name:'Las Vegas',state:'NV',label:'라스베가스',lat:36.1699,lng:-115.1398 },
  ]},
  { name: '남부·동남부', cities: [
    { name:'Atlanta',state:'GA',label:'애틀란타',lat:33.749,lng:-84.388 },
    { name:'Dallas',state:'TX',label:'달라스',lat:32.7767,lng:-96.797 },
    { name:'Houston',state:'TX',label:'휴스턴',lat:29.7604,lng:-95.3698 },
    { name:'Austin',state:'TX',label:'오스틴',lat:30.2672,lng:-97.7431 },
    { name:'Charlotte',state:'NC',label:'샬럿',lat:35.2271,lng:-80.8431 },
    { name:'Miami',state:'FL',label:'마이애미',lat:25.7617,lng:-80.1918 },
  ]},
  { name: '동부', cities: [
    { name:'New York',state:'NY',label:'뉴욕',lat:40.7128,lng:-74.006 },
    { name:'New Jersey',state:'NJ',label:'뉴저지',lat:40.0583,lng:-74.4057 },
    { name:'Washington',state:'DC',label:'워싱턴DC',lat:38.9072,lng:-77.0369 },
    { name:'Boston',state:'MA',label:'보스턴',lat:42.3601,lng:-71.0589 },
    { name:'Philadelphia',state:'PA',label:'필라델피아',lat:39.9526,lng:-75.1652 },
  ]},
  { name: '중부', cities: [
    { name:'Chicago',state:'IL',label:'시카고',lat:41.8781,lng:-87.6298 },
    { name:'Denver',state:'CO',label:'덴버',lat:39.7392,lng:-104.9903 },
    { name:'Minneapolis',state:'MN',label:'미네소타',lat:44.9778,lng:-93.265 },
  ]},
]

function onInput() {
  zipResolved.value = null; zipError.value = false
  const v = directInput.value.trim()
  if (/^\d{5}$/.test(v)) { clearTimeout(timer); timer = setTimeout(() => resolveZip(v), 500) }
}
async function resolveZip(zip) {
  zipLoading.value = true; zipError.value = false
  try {
    const r = await fetch('https://api.zippopotam.us/us/' + zip)
    if (!r.ok) throw new Error()
    const d = await r.json(); const p = d.places[0]
    zipResolved.value = { name: p['place name'], state: p['state abbreviation'], lat: parseFloat(p.latitude), lng: parseFloat(p.longitude) }
  } catch { zipError.value = true } finally { zipLoading.value = false }
}
function applyDirect() {
  if (zipResolved.value) { emit('select', zipResolved.value); return }
  const v = directInput.value.trim(); if (!v) return
  const parts = v.split(',')
  emit('select', { name: parts[0]?.trim(), state: parts[1]?.trim() || '', lat: null, lng: null })
}
</script>
