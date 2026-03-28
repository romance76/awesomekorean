<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <div class="bg-gradient-to-r from-yellow-500 to-orange-500 text-white py-8 px-4 text-center">
      <div class="text-4xl mb-1">🏆</div>
      <h1 class="text-xl font-bold">리더보드</h1>
      <p class="text-yellow-100 text-sm mt-1">한인 커뮤니티 TOP 20</p>
    </div>

    <!-- 탭 -->
    <div class="flex bg-white border-b">
      <button v-for="tab in tabs" :key="tab.type"
        @click="activeTab = tab.type; loadData()"
        class="flex-1 py-3 text-sm font-medium transition"
        :class="activeTab === tab.type ? 'text-orange-600 border-b-2 border-orange-600' : 'text-gray-500'">
        {{ tab.label }}
      </button>
    </div>

    <div class="max-w-[1200px] mx-auto px-4 py-4">
      <!-- TOP 3 포디움 -->
      <div v-if="!loading && data.length >= 3" class="flex items-end justify-center gap-2 mb-6 mt-2">
        <!-- 2등 -->
        <div class="flex flex-col items-center">
          <img :src="getAvatar(data[1])" class="w-12 h-12 rounded-full border-2 border-gray-400 object-cover" />
          <div class="w-20 bg-gray-300 rounded-t-xl pt-4 pb-2 mt-1 text-center">
            <div class="text-xs font-bold text-gray-700 truncate px-1">{{ data[1].username }}</div>
            <div class="text-xs text-gray-600">{{ formatVal(data[1].value) }}</div>
          </div>
          <div class="bg-gray-400 text-white text-xs font-bold w-20 py-1 text-center rounded-b-xl">🥈 2위</div>
        </div>
        <!-- 1등 -->
        <div class="flex flex-col items-center -mb-2">
          <div class="text-2xl mb-1">👑</div>
          <img :src="getAvatar(data[0])" class="w-16 h-16 rounded-full border-4 border-yellow-400 object-cover shadow-lg" />
          <div class="w-24 bg-yellow-400 rounded-t-xl pt-6 pb-2 mt-1 text-center">
            <div class="text-xs font-bold text-yellow-900 truncate px-1">{{ data[0].username }}</div>
            <div class="text-xs text-yellow-800">{{ formatVal(data[0].value) }}</div>
          </div>
          <div class="bg-yellow-500 text-white text-xs font-bold w-24 py-1 text-center rounded-b-xl">🥇 1위</div>
        </div>
        <!-- 3등 -->
        <div class="flex flex-col items-center">
          <img :src="getAvatar(data[2])" class="w-12 h-12 rounded-full border-2 border-orange-400 object-cover" />
          <div class="w-20 bg-orange-200 rounded-t-xl pt-2 pb-2 mt-1 text-center">
            <div class="text-xs font-bold text-orange-800 truncate px-1">{{ data[2].username }}</div>
            <div class="text-xs text-orange-700">{{ formatVal(data[2].value) }}</div>
          </div>
          <div class="bg-orange-400 text-white text-xs font-bold w-20 py-1 text-center rounded-b-xl">🥉 3위</div>
        </div>
      </div>

      <!-- 로딩 -->
      <div v-if="loading" class="text-center py-10 text-gray-400">불러오는 중...</div>

      <!-- 리스트 (4위~) -->
      <div v-else class="space-y-2">
        <div v-for="(user, idx) in data.slice(3)" :key="user.id"
          class="flex items-center gap-3 bg-white rounded-xl p-3 shadow-sm"
          :class="user.id == myId ? 'ring-2 ring-blue-300' : ''">
          <div class="w-8 text-center font-bold text-gray-400">{{ idx + 4 }}</div>
          <img :src="getAvatar(user)" class="w-10 h-10 rounded-full object-cover" />
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-1">
              <span class="font-medium text-gray-800 truncate">{{ user.name || user.username }}</span>
              <span class="text-xs px-1.5 py-0.5 rounded-full" :class="levelColor(user.level)">{{ user.level }}</span>
            </div>
            <div class="text-xs text-gray-500">@{{ user.username }}</div>
          </div>
          <div class="text-right">
            <div class="font-bold text-orange-600">{{ formatVal(user.value) }}</div>
            <div class="text-xs text-gray-400">{{ unitLabel }}</div>
          </div>
        </div>
      </div>

      <!-- 내 순위 -->
      <div v-if="myRank" class="mt-6 bg-blue-50 border border-blue-200 rounded-xl p-4">
        <div class="flex items-center gap-3">
          <div class="text-2xl font-bold text-blue-600">{{ myRank }}위</div>
          <div>
            <div class="font-medium text-gray-800">나의 순위</div>
            <div class="text-sm text-gray-500">{{ unitLabel + ': ' + formatVal(myValue) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const auth   = useAuthStore()
const myId   = auth.user?.id
const data   = ref([])
const loading = ref(false)
const activeTab = ref('points')

const tabs = [
  { type: 'points', label: '💎 포인트' },
  { type: 'posts',  label: '✏️ 게시글' },
  { type: 'quiz',   label: '🧠 퀴즈' },
]

const unitLabel = computed(() => ({
  points: 'P', posts: '개', quiz: 'P'
}[activeTab.value]))

const myRank  = computed(() => { const i = data.value.findIndex(u => u.id == myId); return i >= 0 ? i+1 : null })
const myValue = computed(() => data.value.find(u => u.id == myId)?.value ?? 0)

function formatVal(v) {
  const n = parseInt(v)
  return isNaN(n) ? '0' : n.toLocaleString()
}

function getAvatar(user) {
  return user.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name||user.username)}&background=random`
}

function levelColor(level) {
  return {
    '씨앗': 'bg-gray-100 text-gray-600',
    '새싹': 'bg-green-100 text-green-700',
    '나무': 'bg-blue-100 text-blue-700',
    '숲':   'bg-emerald-100 text-emerald-700',
    '참나무':'bg-yellow-100 text-yellow-700',
  }[level] ?? 'bg-gray-100 text-gray-600'
}

async function loadData() {
  loading.value = true
  try {
    const { data: res } = await axios.get(`/api/games/leaderboard?type=${activeTab.value}`)
    data.value = res.data
  } catch { }
  loading.value = false
}

onMounted(loadData)
</script>
