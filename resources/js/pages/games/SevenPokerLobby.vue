<template>
<div class="min-h-screen bg-gradient-to-br from-purple-900 via-indigo-900 to-purple-800 text-white">
  <!-- 헤더 -->
  <div class="sticky top-0 bg-black/40 backdrop-blur-md border-b border-white/10 px-4 py-3 flex items-center justify-between z-10">
    <div>
      <div class="text-xl font-black">🃏 세븐포커</div>
      <div class="text-[10px] opacity-60">한국식 7-card 포커</div>
    </div>
    <div class="flex items-center gap-2">
      <button @click="showExchange = true" class="bg-amber-400 hover:bg-amber-500 text-amber-900 font-bold px-3 py-2 rounded-lg text-sm">
        💰 환전
      </button>
      <div class="text-right text-xs">
        <div class="opacity-70">게임머니</div>
        <div class="font-black text-amber-300">{{ formatGm(wallet.game_points) }}</div>
      </div>
    </div>
  </div>

  <div class="p-4">
    <!-- 방 생성 버튼 -->
    <div class="flex justify-between items-center mb-3">
      <div class="text-sm opacity-70">현재 진행 중인 방 {{ rooms.length }}개</div>
      <div class="flex gap-2">
        <button @click="loadRooms" class="bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded text-xs">🔄 새로고침</button>
        <button @click="showCreate = true" class="bg-purple-500 hover:bg-purple-600 px-4 py-2 rounded-lg text-sm font-bold">+ 방 만들기</button>
      </div>
    </div>

    <!-- 방 목록 -->
    <div v-if="rooms.length === 0" class="text-center py-20 opacity-60">
      <div class="text-4xl mb-2">🎴</div>
      <div>진행 중인 방이 없습니다</div>
      <div class="text-xs mt-1 opacity-70">첫 방을 만들어보세요!</div>
    </div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
      <div v-for="r in rooms" :key="r.id"
        class="bg-white/10 hover:bg-white/20 border border-white/20 rounded-lg p-4 cursor-pointer transition"
        @click="enterRoom(r)">
        <div class="flex items-center justify-between">
          <div class="font-bold">{{ r.name }}</div>
          <span class="text-[10px] px-2 py-0.5 rounded-full"
            :class="r.status==='waiting' ? 'bg-green-500' : 'bg-orange-500'">
            {{ r.status==='waiting' ? '대기중' : '게임중' }}
          </span>
        </div>
        <div class="text-xs opacity-70 mt-1">호스트: {{ r.host }}</div>
        <div class="grid grid-cols-3 gap-2 mt-3 text-xs">
          <div><div class="opacity-50">최소</div><div>{{ formatGm(r.min_bet) }}</div></div>
          <div><div class="opacity-50">바이인</div><div>{{ formatGm(r.buy_in) }}</div></div>
          <div><div class="opacity-50">인원</div><div>{{ r.seats_taken }}/{{ r.max_seats }}</div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- 방 생성 모달 -->
  <div v-if="showCreate" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" @click.self="showCreate=false">
    <div class="bg-white text-gray-800 rounded-2xl shadow-xl w-full max-w-md p-5">
      <div class="flex justify-between items-center mb-4">
        <div class="font-black text-lg">🃏 방 만들기</div>
        <button @click="showCreate=false" class="text-gray-400 text-xl">✕</button>
      </div>
      <div class="space-y-3">
        <div>
          <label class="text-xs font-bold text-gray-600">방 이름</label>
          <input v-model="newRoom.name" class="w-full border rounded px-3 py-2 text-sm mt-1" placeholder="예: 초보환영방" />
        </div>
        <div>
          <label class="text-xs font-bold text-gray-600">최소 베팅 (게임머니)</label>
          <input v-model.number="newRoom.min_bet" type="number" class="w-full border rounded px-3 py-2 text-sm mt-1" />
        </div>
        <div>
          <label class="text-xs font-bold text-gray-600">바이인 (최소 보유 게임머니)</label>
          <input v-model.number="newRoom.buy_in" type="number" class="w-full border rounded px-3 py-2 text-sm mt-1" />
        </div>
        <div>
          <label class="text-xs font-bold text-gray-600">최대 인원</label>
          <select v-model.number="newRoom.max_seats" class="w-full border rounded px-3 py-2 text-sm mt-1">
            <option :value="2">2명 (1:1)</option>
            <option :value="4">4명</option>
            <option :value="6">6명 (풀)</option>
          </select>
        </div>
        <div class="bg-gray-50 rounded p-2 text-xs text-gray-600">
          현재 보유: <strong class="text-purple-700">{{ formatGm(wallet.game_points) }}</strong> 게임머니
          <div v-if="wallet.game_points < newRoom.buy_in" class="text-red-600 mt-1">⚠️ 바이인 부족</div>
        </div>
      </div>
      <div class="flex justify-end gap-2 mt-4">
        <button @click="showCreate=false" class="text-gray-500 px-4 py-2 text-sm">취소</button>
        <button @click="createRoom" :disabled="busy || !newRoom.name || wallet.game_points < newRoom.buy_in"
          class="bg-purple-600 text-white font-bold px-4 py-2 rounded text-sm hover:bg-purple-700 disabled:opacity-50">
          {{ busy ? '생성중...' : '방 만들기' }}
        </button>
      </div>
    </div>
  </div>

  <!-- 환전 모달 -->
  <GameMoneyExchange :show="showExchange" @close="showExchange=false" @updated="loadWallet" />
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import GameMoneyExchange from '../../components/GameMoneyExchange.vue'

const router = useRouter()
const rooms = ref([])
const wallet = ref({ points: 0, game_points: 0 })
const showCreate = ref(false)
const showExchange = ref(false)
const busy = ref(false)

const newRoom = ref({ name: '', min_bet: 10000, buy_in: 1000000, max_seats: 6 })

function formatGm(n) {
  n = Number(n || 0)
  if (n >= 100000000) return (n / 100000000).toFixed(1) + '억'
  if (n >= 10000) return Math.round(n / 10000) + '만'
  return n.toLocaleString()
}

async function loadRooms() {
  try { const { data } = await axios.get('/api/poker7/rooms'); rooms.value = data.data || [] } catch {}
}

async function loadWallet() {
  try { const { data } = await axios.get('/api/game-money'); wallet.value = data.data } catch {}
}

async function createRoom() {
  busy.value = true
  try {
    const { data } = await axios.post('/api/poker7/rooms', newRoom.value)
    showCreate.value = false
    router.push(`/poker7/room/${data.data.room.id}`)
  } catch (e) { alert(e.response?.data?.message || '방 생성 실패') }
  busy.value = false
}

async function enterRoom(room) {
  try {
    await axios.post(`/api/poker7/rooms/${room.id}/join`)
    router.push(`/poker7/room/${room.id}`)
  } catch (e) {
    if (e.response?.status === 422) alert(e.response.data.message)
    else alert('입장 실패')
  }
}

onMounted(() => { loadRooms(); loadWallet() })
</script>
