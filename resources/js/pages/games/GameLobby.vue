<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-6 rounded-2xl">
        <h1 class="text-xl font-black">🎮 게임 & 포인트</h1>
        <p class="text-blue-100 text-sm mt-0.5">다양한 게임으로 즐겁게 시간을 보내세요!</p>
      </div>
    </div>

    <div class="max-w-[1200px] mx-auto px-4 py-6 space-y-6">

      <!-- 카드 게임 -->
      <div>
        <h2 class="font-bold text-gray-700 mb-3 flex items-center gap-2">🃏 카드 게임</h2>
        <div class="grid grid-cols-2 gap-4">
          <!-- 고스톱 -->
          <div class="bg-white rounded-2xl shadow p-4 flex flex-col items-center gap-2 hover:shadow-lg transition">
            <span class="text-4xl">🀄</span>
            <span class="font-bold text-gray-800">고스톱</span>
            <div class="flex gap-1.5 w-full mt-1">
              <router-link to="/games/go-stop/solo"
                class="flex-1 text-center bg-orange-100 text-orange-700 text-xs py-1.5 rounded-lg font-semibold hover:bg-orange-200">
                혼자하기
              </router-link>
              <router-link to="/games/go-stop"
                class="flex-1 text-center bg-green-100 text-green-700 text-xs py-1.5 rounded-lg font-semibold hover:bg-green-200">
                멀티
              </router-link>
            </div>
          </div>
          <!-- 블랙잭 -->
          <div class="bg-white rounded-2xl shadow p-4 flex flex-col items-center gap-2 hover:shadow-lg transition">
            <span class="text-4xl">🃏</span>
            <span class="font-bold text-gray-800">블랙잭</span>
            <span class="text-xs text-gray-500">딜러 vs 나</span>
            <router-link to="/games/blackjack"
              class="w-full text-center bg-emerald-100 text-emerald-700 text-xs py-1.5 rounded-lg font-semibold hover:bg-emerald-200">
              혼자하기
            </router-link>
          </div>
          <!-- 텍사스 홀덤 -->
          <div class="bg-white rounded-2xl shadow p-4 flex flex-col items-center gap-2 hover:shadow-lg transition">
            <span class="text-4xl">♠️</span>
            <span class="font-bold text-gray-800">텍사스 홀덤</span>
            <span class="text-xs text-gray-500">AI 딜러 vs 나</span>
            <router-link to="/games/holdem"
              class="w-full text-center bg-slate-100 text-slate-700 text-xs py-1.5 rounded-lg font-semibold hover:bg-slate-200">
              혼자하기
            </router-link>
          </div>
          <!-- 포커 -->
          <div class="bg-white rounded-2xl shadow p-4 flex flex-col items-center gap-2 hover:shadow-lg transition">
            <span class="text-4xl">🃏</span>
            <span class="font-bold text-gray-800">포커</span>
            <router-link to="/games/poker"
              class="w-full text-center bg-blue-100 text-blue-700 text-xs py-1.5 rounded-lg font-semibold hover:bg-blue-200 mt-1">
              멀티 플레이
            </router-link>
          </div>
        </div>
      </div>

      <!-- 두뇌 훈련 (치매예방) -->
      <div>
        <h2 class="font-bold text-gray-700 mb-3 flex items-center gap-2">🧠 두뇌 훈련 · 치매예방</h2>
        <div class="grid grid-cols-2 gap-4">
          <!-- 기억력 카드 -->
          <div class="bg-white rounded-2xl shadow p-4 flex flex-col items-center gap-2 hover:shadow-lg transition">
            <span class="text-4xl">🎴</span>
            <span class="font-bold text-gray-800">기억력 카드</span>
            <span class="text-xs text-gray-500">짝 찾기 · 두뇌 훈련</span>
            <div class="flex flex-wrap gap-1 justify-center mt-1">
              <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full">아이들</span>
              <span class="bg-purple-100 text-purple-700 text-xs px-2 py-0.5 rounded-full">치매예방</span>
            </div>
            <router-link to="/games/memory"
              class="w-full text-center bg-indigo-100 text-indigo-700 text-xs py-1.5 rounded-lg font-semibold hover:bg-indigo-200">
              플레이
            </router-link>
          </div>
          <!-- 일일 퀴즈 -->
          <router-link to="/games/quiz"
            class="bg-white rounded-2xl shadow p-4 flex flex-col items-center gap-2 hover:shadow-lg transition">
            <span class="text-4xl">🧠</span>
            <span class="font-bold text-gray-800">일일 퀴즈</span>
            <span class="text-xs text-gray-500">한국 문화 & 미국 생활</span>
            <span class="bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full">매일 20P</span>
          </router-link>
        </div>
      </div>

      <!-- 퍼즐 게임 (혼자 종일) -->
      <div>
        <h2 class="font-bold text-gray-700 mb-3 flex items-center gap-2">🎯 퍼즐 · 보드 게임</h2>
        <div class="grid grid-cols-2 gap-4">
          <!-- 2048 -->
          <div class="bg-white rounded-2xl shadow p-4 flex flex-col items-center gap-2 hover:shadow-lg transition">
            <span class="text-4xl">🔢</span>
            <span class="font-bold text-gray-800">2048</span>
            <span class="text-xs text-gray-500">숫자 타일 합치기</span>
            <span class="bg-yellow-100 text-yellow-700 text-xs px-2 py-0.5 rounded-full">중독성 ↑</span>
            <router-link to="/games/2048"
              class="w-full text-center bg-yellow-100 text-yellow-700 text-xs py-1.5 rounded-lg font-semibold hover:bg-yellow-200">
              플레이
            </router-link>
          </div>
          <!-- 빙고 -->
          <div class="bg-white rounded-2xl shadow p-4 flex flex-col items-center gap-2 hover:shadow-lg transition">
            <span class="text-4xl">🎱</span>
            <span class="font-bold text-gray-800">빙고</span>
            <span class="text-xs text-gray-500">5×5 컴퓨터 대전</span>
            <span class="bg-indigo-100 text-indigo-700 text-xs px-2 py-0.5 rounded-full">치매예방</span>
            <router-link to="/games/bingo"
              class="w-full text-center bg-indigo-100 text-indigo-700 text-xs py-1.5 rounded-lg font-semibold hover:bg-indigo-200">
              플레이
            </router-link>
          </div>
          <!-- 오목 -->
          <div class="bg-white rounded-2xl shadow p-4 flex flex-col items-center gap-2 hover:shadow-lg transition">
            <span class="text-4xl">⚫</span>
            <span class="font-bold text-gray-800">오목</span>
            <span class="text-xs text-gray-500">15×15 AI 대전</span>
            <span class="bg-stone-100 text-stone-700 text-xs px-2 py-0.5 rounded-full">집중력↑</span>
            <router-link to="/games/omok"
              class="w-full text-center bg-stone-100 text-stone-700 text-xs py-1.5 rounded-lg font-semibold hover:bg-stone-200">
              플레이
            </router-link>
          </div>
          <!-- 포인트 규칙 -->
          <router-link to="/rules"
            class="bg-gradient-to-br from-yellow-50 to-orange-50 border border-yellow-200 rounded-2xl shadow p-4 flex flex-col items-center gap-2 hover:shadow-lg transition">
            <span class="text-4xl">📋</span>
            <span class="font-bold text-gray-800">코인 규칙</span>
            <span class="text-xs text-gray-500">포인트 적립 방법</span>
            <span class="bg-orange-100 text-orange-700 text-xs px-2 py-0.5 rounded-full">규칙 보기 →</span>
          </router-link>
        </div>
      </div>

      <!-- 포인트 바로가기 -->
      <div class="grid grid-cols-2 gap-4">
        <router-link to="/points"
          class="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-2xl p-5 flex items-center gap-3 hover:opacity-90 transition">
          <span class="text-3xl">💎</span>
          <div>
            <div class="font-bold">내 포인트</div>
            <div class="text-blue-100 text-sm">{{ myPoints.toLocaleString() }}P</div>
          </div>
        </router-link>
        <router-link to="/games/shop"
          class="bg-gradient-to-r from-purple-500 to-pink-500 text-white rounded-2xl p-5 flex items-center gap-3 hover:opacity-90 transition">
          <span class="text-3xl">🛍️</span>
          <div>
            <div class="font-bold">포인트샵</div>
            <div class="text-purple-100 text-sm">특별 혜택 교환</div>
          </div>
        </router-link>
      </div>

      <!-- 리더보드 -->
      <router-link to="/games/leaderboard"
        class="bg-white rounded-2xl shadow p-4 flex items-center gap-4 hover:shadow-lg transition">
        <span class="text-4xl">🏆</span>
        <div>
          <div class="font-bold text-gray-800">리더보드</div>
          <div class="text-xs text-gray-500">전체 순위 확인 · Top 20</div>
        </div>
        <span class="ml-auto bg-yellow-100 text-yellow-700 text-xs px-2 py-0.5 rounded-full">확인하기 →</span>
      </router-link>

      <!-- 포인트 적립 방법 -->
      <div class="bg-white rounded-2xl shadow p-5">
        <h2 class="font-bold text-gray-800 mb-3">📋 포인트 적립 방법</h2>
        <div class="space-y-2">
          <div v-for="item in earnMethods" :key="item.action"
            class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
            <div class="flex items-center gap-2">
              <span>{{ item.icon }}</span>
              <span class="text-sm text-gray-700">{{ item.action }}</span>
            </div>
            <span class="text-sm font-semibold text-blue-600">+{{ item.pts }}P</span>
          </div>
        </div>
      </div>

      <!-- 진행 중인 고스톱 방 -->
      <div class="bg-white rounded-2xl shadow p-5">
        <div class="flex items-center justify-between mb-3">
          <h2 class="font-bold text-gray-800">🀄 대기 중인 방</h2>
          <button @click="loadRooms" class="text-blue-600 text-sm">새로고침</button>
        </div>
        <div v-if="loadingRooms" class="text-center py-4 text-gray-400">불러오는 중...</div>
        <div v-else-if="!rooms.length" class="text-center py-4 text-gray-400">
          대기 중인 방이 없습니다. 직접 방을 만들어 보세요!
        </div>
        <div v-else class="space-y-2">
          <div v-for="room in rooms" :key="room.id"
            class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
            <div>
              <span class="font-mono font-bold text-gray-800">{{ room.code }}</span>
              <span class="text-xs text-gray-500 ml-2">방장: {{ room.creator?.username }}</span>
              <span class="text-xs text-gray-500 ml-1">베팅 {{ room.bet_points }}P</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-xs text-gray-500">{{ room.players_count }}/{{ room.max_players }}명</span>
              <router-link :to="`/games/go-stop?room=${room.id}`"
                class="bg-red-600 text-white text-xs px-3 py-1 rounded-full">입장</router-link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const auth = useAuthStore()
const myPoints = ref(auth.user?.points_total ?? 0)
const rooms = ref([])
const loadingRooms = ref(false)

const earnMethods = [
  { icon: '📅', action: '출석 체크',         pts: 10 },
  { icon: '✏️', action: '게시글 작성',        pts: 30 },
  { icon: '💬', action: '댓글 작성',          pts: 5  },
  { icon: '👍', action: '받은 추천',          pts: 10 },
  { icon: '🧠', action: '일일 퀴즈 정답',     pts: 20 },
  { icon: '🀄', action: '고스톱 승리',        pts: '50~500' },
  { icon: '🚗', action: '라이드 이용',        pts: 20 },
  { icon: '💝', action: '매칭 프로필 인증',   pts: 200 },
  { icon: '👥', action: '신규 회원 추천',     pts: 500 },
]

async function loadRooms() {
  loadingRooms.value = true
  try {
    const { data } = await axios.get('/api/games/rooms')
    rooms.value = data
  } catch { }
  loadingRooms.value = false
}

onMounted(loadRooms)
</script>
