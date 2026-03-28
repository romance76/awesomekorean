<template>
  <div class="max-w-[1200px] mx-auto px-4 py-6">
    <h1 class="text-xl font-bold text-gray-800 mb-4">포인트 현황</h1>

    <!-- 요약 카드 -->
    <div class="grid grid-cols-3 gap-3 mb-6">
      <div class="bg-gradient-to-br from-red-500 to-red-600 text-white rounded-xl p-4 text-center">
        <p class="text-xs opacity-80 mb-1">보유 포인트</p>
        <p class="text-2xl font-bold">{{ balance.points?.toLocaleString() }}</p>
        <p class="text-xs opacity-70 mt-1">P</p>
      </div>
      <div class="bg-gradient-to-br from-green-500 to-green-600 text-white rounded-xl p-4 text-center">
        <p class="text-xs opacity-80 mb-1">캐시 잔액</p>
        <p class="text-2xl font-bold">${{ Number(balance.cash || 0).toFixed(2) }}</p>
        <p class="text-xs opacity-70 mt-1">USD</p>
      </div>
      <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-xl p-4 text-center">
        <p class="text-xs opacity-80 mb-1">등급</p>
        <p class="text-xl font-bold">{{ balance.level }}</p>
        <p class="text-xs opacity-70 mt-1">{{ nextLevel }}</p>
      </div>
    </div>

    <!-- 출석체크 -->
    <div v-if="!checkedIn" class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6 flex items-center justify-between">
      <div>
        <p class="font-medium text-yellow-800">오늘 출석체크를 안 하셨어요!</p>
        <p class="text-xs text-yellow-600 mt-0.5">출석 체크하고 포인트 받으세요</p>
      </div>
      <button @click="doCheckin" :disabled="checkingIn"
        class="bg-yellow-500 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-yellow-600 disabled:opacity-50">
        {{ checkingIn ? '처리 중...' : '출석체크 (+10P)' }}
      </button>
    </div>
    <div v-else class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6 text-center text-green-700 text-sm font-medium">
      ✓ 오늘 출석체크 완료!
    </div>

    <!-- 포인트 전환 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
      <h2 class="font-semibold text-gray-800 mb-3">포인트 → 캐시 전환</h2>
      <p class="text-xs text-gray-500 mb-3">1,000P = $1.00 · 최소 5,000P · 월 최대 50,000P</p>
      <div class="flex space-x-2">
        <input v-model="convertAmount" type="number" step="1000" min="5000" placeholder="전환할 포인트 입력"
          class="flex-1 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
        <button @click="doConvert" :disabled="converting"
          class="bg-red-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50">
          {{ converting ? '처리 중...' : '전환' }}
        </button>
      </div>
      <p v-if="convertAmount >= 5000" class="text-xs text-gray-500 mt-1">
        → ${{ (convertAmount / 1000).toFixed(2) }} 캐시로 전환
      </p>
    </div>

    <!-- 포인트 적립 방법 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
      <h2 class="font-semibold text-gray-800 mb-3">포인트 적립 방법</h2>
      <div class="grid grid-cols-2 gap-2 text-sm">
        <div v-for="item in earnMethods" :key="item.label" class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
          <span class="text-gray-600">{{ item.label }}</span>
          <span class="font-semibold text-red-600">+{{ item.points }}P</span>
        </div>
      </div>
    </div>

    <!-- 내역 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100">
        <h2 class="font-semibold text-gray-800">포인트 내역</h2>
      </div>
      <div v-if="historyLoading" class="text-center py-8 text-gray-400 text-sm">불러오는 중...</div>
      <ul v-else>
        <li v-for="log in history" :key="log.id" class="px-5 py-3 border-b border-gray-50 last:border-0 flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-800">{{ log.memo || log.action }}</p>
            <p class="text-xs text-gray-400">{{ formatDate(log.created_at) }}</p>
          </div>
          <span :class="['font-semibold text-sm', log.amount > 0 ? 'text-green-600' : 'text-red-500']">
            {{ log.amount > 0 ? '+' : '' }}{{ log.amount.toLocaleString() }}P
          </span>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '../../stores/auth';
import axios from 'axios';

const authStore = useAuthStore();
const balance = ref({ points: 0, cash: 0, level: '' });
const history = ref([]);
const historyLoading = ref(true);
const checkedIn = ref(false);
const checkingIn = ref(false);
const convertAmount = ref('');
const converting = ref(false);

const nextLevel = computed(() => {
  const p = balance.value.points;
  if (p < 1000) return `새싹까지 ${(1000 - p).toLocaleString()}P`;
  if (p < 5000) return `나무까지 ${(5000 - p).toLocaleString()}P`;
  if (p < 20000) return `숲까지 ${(20000 - p).toLocaleString()}P`;
  if (p < 50000) return `참나무까지 ${(50000 - p).toLocaleString()}P`;
  return 'MAX 등급!';
});

const earnMethods = [
  { label: '일일 출석체크', points: 10 },
  { label: '게시글 작성', points: 30 },
  { label: '댓글 작성', points: 5 },
  { label: '추천 받음', points: 10 },
  { label: '연속 7일 출석', points: 50 },
  { label: '신규 회원 추천', points: 500 },
];

async function doCheckin() {
  checkingIn.value = true;
  try {
    const { data } = await axios.post('/api/points/checkin');
    checkedIn.value = true;
    balance.value.points = data.points;
    alert(data.message);
    await authStore.fetchMe();
    await loadHistory();
  } catch(e) {
    if (e.response?.data?.message) { alert(e.response.data.message); checkedIn.value = true; }
  } finally { checkingIn.value = false; }
}

async function doConvert() {
  const amt = Number(convertAmount.value);
  if (amt < 5000 || amt % 1000 !== 0) { alert('1,000P 단위로 최소 5,000P 이상 전환 가능합니다.'); return; }
  if (!confirm(`${amt.toLocaleString()}P를 $${(amt/1000).toFixed(2)} 캐시로 전환하시겠습니까?`)) return;
  converting.value = true;
  try {
    const { data } = await axios.post('/api/points/convert', { points: amt });
    balance.value.points = data.points;
    balance.value.cash = data.cash;
    convertAmount.value = '';
    alert(data.message);
    await loadHistory();
  } catch(e) {
    alert(e.response?.data?.message || '오류가 발생했습니다.');
  } finally { converting.value = false; }
}

async function loadHistory() {
  const { data } = await axios.get('/api/points/history');
  history.value = data.data;
  historyLoading.value = false;
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('ko-KR');
}

onMounted(async () => {
  const { data } = await axios.get('/api/points/balance');
  balance.value = data;
  await loadHistory();
});
</script>
