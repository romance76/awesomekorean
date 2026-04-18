<template>
  <div class="space-y-4">
    <!-- 내 보호대상 -->
    <div class="bg-white rounded-xl shadow-sm p-5">
      <h3 class="font-bold mb-3">💙 내 보호대상 (Ward)</h3>
      <div v-if="loadingWards" class="p-4 text-center text-sm text-gray-400">로딩 중...</div>
      <div v-else-if="!wards.length" class="p-6 text-center text-sm text-gray-500">
        <p class="text-2xl mb-2">🤝</p>
        <p>등록된 보호대상이 없습니다.</p>
      </div>
      <ul v-else class="divide-y">
        <li v-for="w in wards" :key="w.id" class="py-2.5 flex items-center gap-3">
          <img :src="w.ward?.avatar || '/images/default-avatar.png'" @error="$event.target.src='/images/default-avatar.png'" class="w-10 h-10 rounded-full bg-gray-100" />
          <div class="flex-1">
            <p class="font-semibold text-sm">{{ w.ward?.nickname || w.ward?.name }}</p>
            <p class="text-xs text-gray-500">
              <span :class="['px-1.5 py-0.5 rounded text-xs', w.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700']">
                {{ w.status === 'active' ? '매칭됨' : '대기' }}
              </span>
            </p>
          </div>
        </li>
      </ul>

      <!-- 보호대상 검색 추가 -->
      <div class="mt-4 pt-4 border-t">
        <p class="text-sm font-semibold mb-2">+ 보호대상 추가</p>
        <div class="flex gap-2">
          <input v-model="searchEmail" type="email" placeholder="보호대상 이메일" class="flex-1 border rounded px-3 py-2 text-sm" />
          <button @click="search" class="px-3 py-2 bg-amber-400 hover:bg-amber-500 text-white rounded text-sm font-semibold">검색</button>
        </div>
        <div v-if="searchResult" class="mt-2 p-3 border rounded-lg flex items-center justify-between">
          <div>
            <p class="font-semibold text-sm">{{ searchResult.nickname || searchResult.name }}</p>
            <p class="text-xs text-gray-500">{{ searchResult.email }}</p>
          </div>
          <button @click="registerWard" class="px-3 py-1.5 bg-amber-400 hover:bg-amber-500 text-white rounded text-xs font-semibold">등록</button>
        </div>
      </div>
    </div>

    <!-- 체크인 스케줄 -->
    <div class="bg-white rounded-xl shadow-sm p-5">
      <h3 class="font-bold mb-3">📅 체크인 스케줄</h3>
      <div class="grid grid-cols-2 gap-3">
        <label class="block">
          <span class="text-xs text-gray-500">일일 체크인 시각</span>
          <input v-model="schedule.time" type="time" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
        </label>
        <label class="block">
          <span class="text-xs text-gray-500">체크인 주기</span>
          <select v-model="schedule.frequency" class="w-full border rounded px-3 py-2 mt-1 text-sm">
            <option value="daily">매일</option>
            <option value="weekdays">평일만</option>
            <option value="custom">커스텀</option>
          </select>
        </label>
      </div>
      <div class="flex justify-end mt-3">
        <button @click="saveSchedule" class="px-4 py-2 bg-amber-400 hover:bg-amber-500 text-white rounded-lg text-sm font-semibold">💾 저장</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import { useSiteStore } from '../../stores/site'
const site = useSiteStore()
const wards = ref([])
const loadingWards = ref(true)
const searchEmail = ref('')
const searchResult = ref(null)
const schedule = reactive({ time: '09:00', frequency: 'daily' })

async function load() {
  try {
    const { data } = await axios.get('/api/elder/my-wards').catch(() => ({ data: { data: [] } }))
    wards.value = data?.data || []
  } finally { loadingWards.value = false }
}

async function search() {
  searchResult.value = null
  try {
    const { data } = await axios.get(`/api/elder/search-ward?email=${encodeURIComponent(searchEmail.value)}`)
    searchResult.value = data?.data
    if (!searchResult.value) site.toast('해당 이메일 유저 없음', 'error')
  } catch { site.toast('검색 실패', 'error') }
}

async function registerWard() {
  if (!searchResult.value) return
  try {
    await axios.post('/api/elder/register-ward', { ward_id: searchResult.value.id })
    site.toast('보호대상이 등록되었습니다', 'success')
    searchResult.value = null; searchEmail.value = ''
    await load()
  } catch (e) { site.toast(e.response?.data?.message || '등록 실패', 'error') }
}

async function saveSchedule() {
  try {
    await axios.post('/api/elder/save-schedule', schedule)
    site.toast('스케줄이 저장되었습니다', 'success')
  } catch { site.toast('저장 실패', 'error') }
}

onMounted(load)
</script>
