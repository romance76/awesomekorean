<template>
<!--
  상세 화면 상단 도구 (내 위치 / 반경 / 검색 / 등록). 목록 화면 상단과 같은 모양.
  위치·반경을 바꾸거나 검색하면 해당 목록으로 이동해서 그 조건으로 보여준다.
  선택값은 섹션별 위치 필터 저장소(locationFilter)에 저장되어 목록과 상세 사이에서 유지된다.
-->
<div class="flex items-center gap-2 flex-nowrap whitespace-nowrap">
  <template v-if="location">
    <span class="text-amber-600"><AppIcon name="map-pin" :size="15" /></span>
    <select v-model="idx" @change="onCity" aria-label="지역" class="input-soft w-auto pl-2.5 pr-8 py-1.5 text-xs font-semibold">
      <option value="-2" v-if="myCity">📌 내 위치 ({{ myCity.label || myCity.name }})</option>
      <option value="-1">🇺🇸 전국</option>
      <optgroup label="한인 밀집 도시">
        <option v-for="(c, i) in koreanCities" :key="i" :value="String(i)">{{ c.label }}</option>
      </optgroup>
    </select>
    <select v-if="idx !== '-1'" v-model="rad" @change="goList(false)" aria-label="반경" class="input-soft w-auto pl-2.5 pr-8 py-1.5 text-xs">
      <option value="10">10mi</option><option value="30">30mi</option><option value="50">50mi</option><option value="100">100mi</option>
    </select>
  </template>
  <form v-if="search" @submit.prevent="goList(true)" class="flex gap-1">
    <input v-model="q" type="text" :placeholder="placeholder" aria-label="검색" class="input-soft w-40 px-3 py-1.5 text-sm" />
    <button type="submit" class="btn-primary px-3 py-1.5 text-xs">검색</button>
  </form>
  <RouterLink v-if="writePath && auth.isLoggedIn" :to="writePath" class="btn-primary px-3 py-1.5 text-xs whitespace-nowrap"><AppIcon name="edit" :size="13" />{{ writeLabel }}</RouterLink>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useLocationFilterStore } from '../stores/locationFilter'
import { useLocation } from '../composables/useLocation'
import AppIcon from './AppIcon.vue'

const props = defineProps({
  section: { type: String, required: true },       // locationFilter 섹션 키 (jobs, market, realestate, groupbuy, directory, clubs …)
  listPath: { type: String, required: true },      // 목록 주소 (예: /groupbuy)
  writePath: { type: String, default: '' },        // 등록 주소 (비우면 버튼 숨김, 로그인 필요)
  writeLabel: { type: String, default: '등록' },
  location: { type: Boolean, default: true },      // 위치/반경 선택 표시
  search: { type: Boolean, default: true },
  placeholder: { type: String, default: '검색...' },
})

const router = useRouter()
const auth = useAuthStore()
const locFilter = useLocationFilterStore()
const { city, koreanCities, init: initLocation } = useLocation()

const myCity = ref(null)
const idx = ref('-2')                                // '-2' 내 위치, '-1' 전국, '0'~ 도시 번호
const rad = ref(String(auth.user?.default_radius || 30))
const q = ref('')

onMounted(async () => {
  await initLocation()
  if (city.value) myCity.value = { ...city.value }
  const saved = locFilter.get(props.section)
  if (saved) { idx.value = String(saved.cityIdx); rad.value = String(saved.radius) }
  else if (myCity.value) { idx.value = '-2' }
  else { idx.value = '-1'; rad.value = '0' }
  if (idx.value === '-1') rad.value = '0'
  else if (rad.value === '0') rad.value = '30'
})

function onCity() {
  if (idx.value === '-1') rad.value = '0'
  else if (rad.value === '0') rad.value = '30'
  goList(false)
}

function goList(withSearch) {
  locFilter.set(props.section, { cityIdx: idx.value, radius: rad.value })
  const term = q.value.trim()
  router.push(withSearch && term ? { path: props.listPath, query: { search: term } } : props.listPath)
}
</script>
