<template>
  <div class="min-h-screen bg-gray-50 pb-16">
    <!-- Header -->
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-6 rounded-2xl">
        <h1 class="text-xl font-black">⭐ 코인 · 포인트 규칙</h1>
        <p class="text-blue-100 text-sm mt-0.5">활동하면 포인트가 쌓여요! — Earn points by participating</p>
      </div>
    </div>

    <div class="max-w-[1200px] mx-auto px-4 py-6 space-y-5">

      <!-- 기본 활동 -->
      <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="bg-blue-50 px-5 py-3 flex items-center gap-2">
          <span class="text-xl">📝</span>
          <h2 class="font-black text-blue-800 text-base">기본 활동</h2>
        </div>
        <div class="divide-y divide-gray-50">
          <div v-for="rule in basicRules" :key="rule.action" class="flex items-center justify-between px-5 py-3.5">
            <div class="flex items-center gap-3">
              <span class="text-xl w-8 text-center">{{ rule.icon }}</span>
              <div>
                <p class="text-sm font-semibold text-gray-800">{{ rule.action }}</p>
                <p class="text-xs text-gray-500">{{ rule.desc }}</p>
              </div>
            </div>
            <div class="text-right flex-shrink-0 ml-4">
              <span class="font-black text-green-600 text-base">+{{ rule.points }}P</span>
              <p v-if="rule.limit" class="text-xs text-gray-400">{{ rule.limit }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- 커뮤니티 -->
      <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="bg-green-50 px-5 py-3 flex items-center gap-2">
          <span class="text-xl">🏘️</span>
          <h2 class="font-black text-green-800 text-base">커뮤니티 활동</h2>
        </div>
        <div class="divide-y divide-gray-50">
          <div v-for="rule in communityRules" :key="rule.action" class="flex items-center justify-between px-5 py-3.5">
            <div class="flex items-center gap-3">
              <span class="text-xl w-8 text-center">{{ rule.icon }}</span>
              <div>
                <p class="text-sm font-semibold text-gray-800">{{ rule.action }}</p>
                <p class="text-xs text-gray-500">{{ rule.desc }}</p>
              </div>
            </div>
            <div class="text-right flex-shrink-0 ml-4">
              <span class="font-black text-green-600 text-base">+{{ rule.points }}P</span>
              <p v-if="rule.limit" class="text-xs text-gray-400">{{ rule.limit }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- 매칭 -->
      <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="bg-pink-50 px-5 py-3 flex items-center gap-2">
          <span class="text-xl">💝</span>
          <h2 class="font-black text-pink-800 text-base">매칭 활동</h2>
        </div>
        <div class="divide-y divide-gray-50">
          <div v-for="rule in matchRules" :key="rule.action" class="flex items-center justify-between px-5 py-3.5">
            <div class="flex items-center gap-3">
              <span class="text-xl w-8 text-center">{{ rule.icon }}</span>
              <div>
                <p class="text-sm font-semibold text-gray-800">{{ rule.action }}</p>
                <p class="text-xs text-gray-500">{{ rule.desc }}</p>
              </div>
            </div>
            <div class="text-right flex-shrink-0 ml-4">
              <span class="font-black text-green-600 text-base">+{{ rule.points }}P</span>
              <p v-if="rule.limit" class="text-xs text-gray-400">{{ rule.limit }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- 게임 -->
      <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="bg-purple-50 px-5 py-3 flex items-center gap-2">
          <span class="text-xl">🎮</span>
          <h2 class="font-black text-purple-800 text-base">게임 활동</h2>
        </div>
        <div class="divide-y divide-gray-50">
          <div v-for="rule in gameRules" :key="rule.action" class="flex items-center justify-between px-5 py-3.5">
            <div class="flex items-center gap-3">
              <span class="text-xl w-8 text-center">{{ rule.icon }}</span>
              <div>
                <p class="text-sm font-semibold text-gray-800">{{ rule.action }}</p>
                <p class="text-xs text-gray-500">{{ rule.desc }}</p>
              </div>
            </div>
            <div class="text-right flex-shrink-0 ml-4">
              <span class="font-black text-green-600 text-base">+{{ rule.points }}P</span>
              <p v-if="rule.limit" class="text-xs text-gray-400">{{ rule.limit }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- 중고장터 -->
      <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="bg-orange-50 px-5 py-3 flex items-center gap-2">
          <span class="text-xl">🛒</span>
          <h2 class="font-black text-orange-800 text-base">중고장터</h2>
        </div>
        <div class="divide-y divide-gray-50">
          <div v-for="rule in marketRules" :key="rule.action" class="flex items-center justify-between px-5 py-3.5">
            <div class="flex items-center gap-3">
              <span class="text-xl w-8 text-center">{{ rule.icon }}</span>
              <div>
                <p class="text-sm font-semibold text-gray-800">{{ rule.action }}</p>
                <p class="text-xs text-gray-500">{{ rule.desc }}</p>
              </div>
            </div>
            <div class="text-right flex-shrink-0 ml-4">
              <span class="font-black text-green-600 text-base">+{{ rule.points }}P</span>
              <p v-if="rule.limit" class="text-xs text-gray-400">{{ rule.limit }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- 등급 시스템 -->
      <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="bg-yellow-50 px-5 py-3 flex items-center gap-2">
          <span class="text-xl">🏆</span>
          <h2 class="font-black text-yellow-800 text-base">등급 시스템</h2>
        </div>
        <div class="divide-y divide-gray-50">
          <div v-for="level in levels" :key="level.name" class="flex items-center gap-4 px-5 py-3.5">
            <span class="text-2xl w-10 text-center">{{ level.icon }}</span>
            <div class="flex-1">
              <p class="text-sm font-bold text-gray-800">{{ level.name }}</p>
              <p class="text-xs text-gray-500">{{ level.range }}</p>
            </div>
            <div class="text-right">
              <p class="text-xs font-semibold text-purple-600">{{ level.benefit }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- 규칙 안내 -->
      <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5">
        <h3 class="font-bold text-amber-800 mb-3">📌 주의사항</h3>
        <ul class="text-sm text-amber-700 space-y-1.5">
          <li>• 하루 최대 획득 가능 포인트: <strong>200P</strong></li>
          <li>• 어뷰징(복수 계정, 반복 조작)은 포인트 전액 삭제 및 계정 정지</li>
          <li>• 포인트는 현금으로 환전 불가, 플랫폼 내 사용만 가능</li>
          <li>• 규칙은 운영 상황에 따라 변경될 수 있습니다</li>
          <li>• 게임 포인트는 실제로 게임을 플레이해야 지급 (자리비움 감지 시 미지급)</li>
        </ul>
      </div>

    </div>
  </div>
</template>

<script setup>
const basicRules = [
  { icon: '🎉', action: '신규 가입', desc: '회원가입 시 1회 지급', points: 50, limit: '1회' },
  { icon: '📅', action: '출석 체크', desc: '매일 로그인 후 출석 버튼 클릭', points: 10, limit: '1회/일' },
  { icon: '👤', action: '프로필 완성', desc: '이름, 소개, 지역, 사진 등록', points: 20, limit: '1회' },
  { icon: '📧', action: '이메일 인증', desc: '이메일 주소 인증 완료', points: 15, limit: '1회' },
  { icon: '🤝', action: '친구 추가', desc: '서로 친구 수락 시 양측 모두 지급', points: 5, limit: '1회/친구' },
]

const communityRules = [
  { icon: '✏️', action: '글 작성', desc: '커뮤니티 게시판에 글 작성', points: 5, limit: '10회/일' },
  { icon: '💬', action: '댓글 작성', desc: '게시글에 댓글 달기', points: 2, limit: '20회/일' },
  { icon: '❤️', action: '좋아요 받기', desc: '내 글/댓글이 좋아요를 받을 때', points: 1, limit: '30회/일' },
  { icon: '📋', action: '구인구직 등록', desc: '구인 또는 구직 글 등록', points: 3, limit: '3회/일' },
  { icon: '📰', action: '업소록 등록', desc: '비즈니스 정보 등록', points: 10, limit: '1회/업소' },
  { icon: '⭐', action: '리뷰 작성', desc: '업소 후기 작성 (100자 이상)', points: 5, limit: '5회/일' },
]

const matchRules = [
  { icon: '💝', action: '여성 매칭 프로필 등록', desc: '매칭용 프로필 처음 등록', points: 30, limit: '1회' },
  { icon: '💙', action: '남성 매칭 프로필 등록', desc: '매칭용 프로필 처음 등록', points: 20, limit: '1회' },
  { icon: '👀', action: '상대 프로필 조회', desc: '매칭 탭에서 프로필 카드 확인', points: 1, limit: '10회/일' },
  { icon: '☕', action: '좋아요 받기 (Coffee?)', desc: '상대방이 나에게 좋아요', points: 5, limit: '5회/일' },
  { icon: '💑', action: '서로 매칭 성사', desc: '양방향 좋아요로 매칭 완료', points: 20, limit: '3회/일' },
]

const gameRules = [
  { icon: '🎮', action: '게임 플레이 5분', desc: '실제 플레이 (자리비움 미지급)', points: 2, limit: '30P/일' },
  { icon: '🏆', action: '게임 승리', desc: '컴퓨터 또는 상대방 이겨도 지급', points: 5, limit: '10회/일' },
  { icon: '🃏', action: '고스톱 완주', desc: '1판 완료 (포기 제외)', points: 3, limit: '5회/일' },
  { icon: '🀄', action: '오목/빙고 승리', desc: '각 게임 승리 시', points: 5, limit: '10회/일' },
  { icon: '🎯', action: '퀴즈 정답', desc: '오늘의 퀴즈 맞추기', points: 10, limit: '1회/일' },
  { icon: '🥇', action: '리더보드 TOP 10', desc: '주간 게임 리더보드 진입', points: 50, limit: '1회/주' },
]

const marketRules = [
  { icon: '📦', action: '중고 물품 등록', desc: '중고장터에 물건 올리기', points: 3, limit: '5회/일' },
  { icon: '🤝', action: '거래 완료', desc: '구매자/판매자 모두 완료 처리', points: 10, limit: '5회/일' },
  { icon: '🔒', action: 'HOLD 기능 사용', desc: '물건 예약 홀드 후 거래 완료시 추가', points: 5, limit: '거래 완료 시' },
  { icon: '⭐', action: '거래 후기 작성', desc: '거래 후 상대방 리뷰 작성', points: 3, limit: '1회/거래' },
]

const levels = [
  { icon: '🌱', name: '씨앗', range: '0 ~ 499P', benefit: '기본 기능' },
  { icon: '🌿', name: '새싹', range: '500 ~ 1,999P', benefit: '+5% 포인트 보너스' },
  { icon: '🌳', name: '나무', range: '2,000 ~ 4,999P', benefit: '+10% 보너스 · 광고 감소' },
  { icon: '🌲', name: '숲', range: '5,000 ~ 9,999P', benefit: '+15% 보너스 · 뱃지 표시' },
  { icon: '🏔️', name: '참나무', range: '10,000P 이상', benefit: '+20% 보너스 · 프리미엄 혜택' },
]
</script>
