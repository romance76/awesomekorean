<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <button @click="$router.back()" class="text-sm text-gray-500 hover:text-amber-600 mb-3">← 돌아가기</button>
    <h1 class="text-xl font-black text-gray-800 mb-4">📋 포인트 적립 규칙</h1>

    <div class="space-y-4">
      <!-- 적립 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-4 py-3 bg-green-50 border-b font-bold text-sm text-green-800">💰 포인트 적립</div>
        <div class="divide-y">
          <div v-for="rule in earnRules" :key="rule.action" class="px-4 py-2.5 flex justify-between text-sm">
            <span class="text-gray-700">{{ rule.action }}</span>
            <span class="font-bold text-green-600">+{{ rule.points }}P</span>
          </div>
        </div>
      </div>

      <!-- 사용 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-4 py-3 bg-red-50 border-b font-bold text-sm text-red-800">🛒 포인트 사용</div>
        <div class="divide-y">
          <div v-for="rule in spendRules" :key="rule.action" class="px-4 py-2.5 flex justify-between text-sm">
            <span class="text-gray-700">{{ rule.action }}</span>
            <span class="font-bold text-red-500">{{ rule.points }}</span>
          </div>
        </div>
      </div>

      <!-- 게임 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-4 py-3 bg-amber-50 border-b font-bold text-sm text-amber-800">🎮 게임 포인트</div>
        <div class="divide-y">
          <div v-for="rule in gameRules" :key="rule.action" class="px-4 py-2.5 flex justify-between text-sm">
            <span class="text-gray-700">{{ rule.action }}</span>
            <span class="font-bold text-amber-600">{{ rule.points }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
const earnRules = [
  { action: '회원가입', points: 10 },
  { action: '일일 로그인', points: 2 },
  { action: '게시글 작성', points: 5 },
  { action: '댓글 작성 (일일 최대 10회)', points: 3 },
  { action: '안심서비스 체크인', points: 5 },
  { action: 'Q&A 답변 채택', points: '현상금 포인트' },
]
const spendRules = [
  { action: '중고장터 찜/예약 (에스크로)', points: '-100P~' },
  { action: 'Q&A 현상금 설정', points: '-설정금액' },
  { action: '게임 베팅', points: '-베팅금액' },
]
const gameRules = [
  { action: '일일 룰렛 (1일 1회)', points: '0~300P' },
  { action: '게임 승리 보상', points: '1~5P' },
  { action: '게임 베팅 승리', points: '베팅 x2' },
]
</script>
