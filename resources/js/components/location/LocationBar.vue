<template>
  <div class="mb-4">
    <!-- Desktop: 한 줄 -->
    <div class="hidden sm:flex items-center gap-2 bg-white rounded-xl border border-gray-200 px-3 py-2 shadow-sm">
      <button @click="showModal=true" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-50 text-blue-700 text-sm font-medium hover:bg-blue-100 transition flex-shrink-0">
        <span class="w-2 h-2 rounded-full bg-blue-600"></span>
        {{ displayCity }}
        <span class="text-xs">▼</span>
      </button>
      <div class="w-px h-6 bg-gray-200"></div>
      <div class="flex gap-1">
        <button v-for="r in radiusOpts" :key="r" @click="onRadius(r)"
          :class="['px-2.5 py-1 rounded-md text-xs font-medium transition',
            selectedRadius===r ? 'bg-blue-600 text-white' : 'text-gray-500 hover:bg-gray-100']">{{ r }}</button>
      </div>
      <div class="w-px h-6 bg-gray-200"></div>
      <input v-model="keyword" :placeholder="placeholder" @keyup.enter="doSearch"
        class="flex-1 text-sm outline-none min-w-0" />
      <button @click="doSearch" class="bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm font-semibold hover:bg-blue-700 transition flex-shrink-0">검색</button>
    </div>
    <!-- Mobile: 두 줄 -->
    <div class="sm:hidden space-y-2">
      <div class="flex items-center gap-2">
        <button @click="showModal=true" class="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-blue-50 text-blue-700 text-sm font-medium flex-shrink-0">
          <span class="w-2 h-2 rounded-full bg-blue-600"></span>{{ displayCity }}<span class="text-xs">▼</span>
        </button>
        <div class="flex gap-1 overflow-x-auto flex-1">
          <button v-for="r in radiusOpts" :key="r" @click="onRadius(r)"
            :class="['px-2 py-1 rounded-md text-xs font-medium whitespace-nowrap transition',
              selectedRadius===r ? 'bg-blue-600 text-white' : 'text-gray-500 bg-gray-100']">{{ r }}</button>
        </div>
      </div>
      <div class="flex gap-2">
        <input v-model="keyword" :placeholder="placeholder" @keyup.enter="doSearch"
          class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none" />
        <button @click="doSearch" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold">검색</button>
      </div>
    </div>
    <LocationModal v-if="showModal" :current-city="city" @select="onCitySelect" @close="showModal=false" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useLocation } from '../../composables/useLocation'
import LocationModal from './LocationModal.vue'

const props = defineProps({
  placeholder: { type: String, default: '검색...' },
  radiusOptions: { type: Array, default: () => ['30mi','50mi','100mi','전국'] }
})
const emit = defineEmits(['search','location-change'])

const { city, radius, displayText: displayCity, init, setCity, setRadius } = useLocation()
const showModal = ref(false)
const keyword = ref('')
const selectedRadius = ref('30mi')
const radiusOpts = ref(props.radiusOptions)

onMounted(() => init())

function onRadius(r) { selectedRadius.value = r; setRadius(r); emit('location-change', { city: city.value, radius: r }) }
function onCitySelect(c) { setCity(c); showModal.value = false; emit('location-change', { city: c, radius: selectedRadius.value }) }
function doSearch() { emit('search', { keyword: keyword.value, city: city.value, radius: selectedRadius.value }) }
</script>
