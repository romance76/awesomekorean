<template>
  <!-- /mypage/friends (Phase 2-C Post) -->
  <div class="space-y-3">
    <div class="bg-white rounded-xl shadow-sm p-4">
      <div class="flex items-center justify-between mb-3">
        <h3 class="font-bold">👫 친구 ({{ counts.friends || 0 }})</h3>
        <router-link to="/friends" class="text-xs text-amber-600 hover:text-amber-800">전체 보기 →</router-link>
      </div>

      <!-- 상태 탭 -->
      <div class="flex gap-1 mb-3">
        <button
          v-for="t in tabs" :key="t.key"
          @click="activeStatus = t.key"
          :class="['px-3 py-1 rounded-full text-xs', activeStatus === t.key ? 'bg-amber-400 text-white font-semibold' : 'bg-gray-100 hover:bg-gray-200']"
        >{{ t.icon }} {{ t.label }} ({{ counts[t.key] || 0 }})</button>
      </div>

      <div v-if="loading" class="p-6 text-center text-sm text-gray-400">로딩 중...</div>
      <div v-else-if="!filtered.length" class="p-10 text-center text-sm text-gray-500">
        <p class="text-3xl mb-2">👥</p>
        <p>{{ activeStatus === 'pending' ? '대기 중인 친구 요청이 없습니다.' : '등록된 친구가 없습니다.' }}</p>
      </div>
      <ul v-else class="divide-y">
        <li v-for="f in filtered" :key="f.id" class="py-3 flex items-center gap-3">
          <img :src="f.friend?.avatar || f.user?.avatar || '/images/default-avatar.png'" @error="$event.target.src='/images/default-avatar.png'" class="w-10 h-10 rounded-full object-cover bg-gray-100" />
          <div class="flex-1 min-w-0">
            <p class="font-semibold text-sm truncate">{{ (f.friend || f.user)?.nickname || (f.friend || f.user)?.name }}</p>
            <p class="text-xs text-gray-500">{{ (f.friend || f.user)?.city }}</p>
          </div>
          <div class="flex gap-1">
            <template v-if="activeStatus === 'pending'">
              <button @click="accept(f)" class="px-2 py-1 bg-amber-400 hover:bg-amber-500 text-white rounded text-xs">수락</button>
              <button @click="remove(f)" class="px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xs">거절</button>
            </template>
            <template v-else-if="activeStatus === 'blocked'">
              <button @click="remove(f)" class="px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xs">차단 해제</button>
            </template>
            <template v-else>
              <button @click="remove(f)" class="px-2 py-1 bg-red-100 hover:bg-red-200 text-red-700 rounded text-xs">삭제</button>
            </template>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useSiteStore } from '../../stores/site'

const site = useSiteStore()
const friends = ref([])
const loading = ref(true)
const activeStatus = ref('accepted')
const tabs = [
  { key: 'accepted', label: '친구', icon: '✅' },
  { key: 'pending',  label: '대기', icon: '⏳' },
  { key: 'blocked',  label: '차단', icon: '🚫' },
]

const filtered = computed(() => friends.value.filter(f => (f.status || 'accepted') === activeStatus.value))
const counts = computed(() => {
  const c = { friends: 0 }
  tabs.forEach(t => { c[t.key] = friends.value.filter(f => (f.status || 'accepted') === t.key).length })
  c.friends = c.accepted
  return c
})

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/friends')
    friends.value = data?.data || []
  } finally { loading.value = false }
}

async function accept(f) {
  try {
    await axios.post(`/api/friends/accept/${f.id}`)
    f.status = 'accepted'
    site.toast('수락되었습니다', 'success')
  } catch { site.toast('실패', 'error') }
}

async function remove(f) {
  const label = activeStatus.value === 'pending' ? '거절' : activeStatus.value === 'blocked' ? '차단 해제' : '삭제'
  if (!confirm(`${label}하시겠습니까?`)) return
  try {
    await axios.delete(`/api/friends/${f.id}`)
    friends.value = friends.value.filter(x => x.id !== f.id)
    site.toast('완료', 'success')
  } catch { site.toast('실패', 'error') }
}

onMounted(load)
</script>
