<template>
<div class="min-h-screen bg-gray-50">
  <!-- 히어로 -->
  <div class="bg-gradient-to-br from-blue-600 via-cyan-500 to-teal-400 text-white">
    <div class="max-w-5xl mx-auto px-4 py-16 text-center">
      <div class="inline-flex items-center gap-2 bg-white/20 rounded-full px-4 py-1.5 text-sm font-bold mb-4">💙 AwesomeKorean 안심서비스</div>
      <h1 class="text-3xl md:text-4xl font-black leading-tight">소중한 분의 안전을<br/>전화 한 통으로 확인합니다</h1>
      <p class="text-blue-100 mt-4 max-w-xl mx-auto">보호자가 등록하면, 시스템이 자동으로 보호대상에게 전화를 걸어 안부를 확인합니다. 응답이 없으면 보호자에게 즉시 알림이 갑니다.</p>
      <div class="mt-8 flex justify-center gap-3 flex-wrap">
        <RouterLink :to="auth.isLoggedIn ? '/dashboard?tab=elder' : '/login?redirect=/dashboard?tab=elder'"
          class="bg-white text-blue-600 font-bold px-8 py-3 rounded-xl text-lg hover:bg-blue-50 shadow-lg transition">
          {{ auth.isLoggedIn ? '서비스 사용하기' : '로그인하고 시작' }}
        </RouterLink>
        <a href="#how" class="border-2 border-white/50 text-white font-bold px-8 py-3 rounded-xl text-lg hover:bg-white/10 transition">자세히 보기 ↓</a>
      </div>
    </div>
  </div>

  <div class="max-w-5xl mx-auto px-4">
    <!-- 이용 흐름 -->
    <div id="how" class="py-12">
      <h2 class="text-2xl font-black text-gray-800 text-center mb-8">어떻게 이용하나요?</h2>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="text-center">
          <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">1️⃣</div>
          <h3 class="font-bold text-gray-800 text-sm">보호대상 등록</h3>
          <p class="text-xs text-gray-500 mt-1">보호하고 싶은 분의 이메일을 검색하여 등록합니다</p>
        </div>
        <div class="text-center">
          <div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">2️⃣</div>
          <h3 class="font-bold text-gray-800 text-sm">전화 스케줄 설정</h3>
          <p class="text-xs text-gray-500 mt-1">랜덤 또는 지정 시간에 자동으로 전화가 걸립니다</p>
        </div>
        <div class="text-center">
          <div class="w-16 h-16 bg-amber-100 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">3️⃣</div>
          <h3 class="font-bold text-gray-800 text-sm">자동 안부 전화</h3>
          <p class="text-xs text-gray-500 mt-1">시스템이 보호대상에게 전화를 걸어 안부를 확인합니다</p>
        </div>
        <div class="text-center">
          <div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">4️⃣</div>
          <h3 class="font-bold text-gray-800 text-sm">미응답 시 알림</h3>
          <p class="text-xs text-gray-500 mt-1">3회 미응답 시 보호자에게 전화 + 알림 발송</p>
        </div>
      </div>
    </div>

    <!-- 주요 기능 -->
    <div class="py-12 border-t">
      <h2 class="text-2xl font-black text-gray-800 text-center mb-8">주요 기능</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div class="bg-white rounded-2xl shadow-sm border p-6 flex items-start gap-4">
          <div class="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">📞</div>
          <div>
            <h3 class="font-bold text-gray-800">자동 안부 전화</h3>
            <p class="text-sm text-gray-500 mt-1">설정한 시간에 보호대상에게 자동 전화가 걸립니다. "안전하시면 1번을 눌러주세요" 안내 후 응답을 확인합니다.</p>
          </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border p-6 flex items-start gap-4">
          <div class="w-14 h-14 bg-red-100 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">🚨</div>
          <div>
            <h3 class="font-bold text-gray-800">미응답 긴급 알림</h3>
            <p class="text-sm text-gray-500 mt-1">15분 내 3회 전화에도 응답이 없으면, 보호자에게 전화 2회 + 사이트 알림 + 쪽지를 자동 발송합니다.</p>
          </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border p-6 flex items-start gap-4">
          <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">🕐</div>
          <div>
            <h3 class="font-bold text-gray-800">랜덤 / 스케줄 선택</h3>
            <p class="text-sm text-gray-500 mt-1">랜덤 시간대(무료) 또는 정확한 스케줄(유료, 50P/회)을 선택할 수 있습니다. 요일별 시간 설정도 가능합니다.</p>
          </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border p-6 flex items-start gap-4">
          <div class="w-14 h-14 bg-amber-100 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">📋</div>
          <div>
            <h3 class="font-bold text-gray-800">전화 기록 로그</h3>
            <p class="text-sm text-gray-500 mt-1">모든 전화 기록(시간, 응답여부, 시도횟수)이 자동 저장됩니다. 보호자가 언제든 확인할 수 있습니다.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- 요금 안내 -->
    <div class="py-12 border-t">
      <h2 class="text-2xl font-black text-gray-800 text-center mb-3">요금 안내</h2>
      <p class="text-center text-gray-500 text-sm mb-8">기본 서비스는 무료, 스케줄 전화는 포인트로 이용</p>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-3xl mx-auto">
        <div class="bg-white rounded-2xl shadow-sm border p-6 text-center">
          <div class="text-3xl mb-2">🆓</div>
          <div class="font-black text-gray-800 text-lg">랜덤 전화</div>
          <div class="text-2xl font-black text-green-600 my-2">무료</div>
          <div class="text-xs text-gray-500">설정한 시간대 내에서 랜덤한 시간에 전화</div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border-2 border-amber-400 p-6 text-center relative">
          <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-amber-400 text-amber-900 text-[10px] font-bold px-3 py-0.5 rounded-full">인기</div>
          <div class="text-3xl mb-2">📅</div>
          <div class="font-black text-gray-800 text-lg">스케줄 전화</div>
          <div class="text-2xl font-black text-amber-600 my-2">50P/회</div>
          <div class="text-xs text-gray-500">원하는 요일/시간에 정확히 전화<br/>15분 내 재전화 무료</div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border p-6 text-center">
          <div class="text-3xl mb-2">💎</div>
          <div class="font-black text-gray-800 text-lg">30일 무제한</div>
          <div class="text-2xl font-black text-blue-600 my-2">$14.99</div>
          <div class="text-xs text-gray-500">스케줄 전화 포함<br/>30일간 모든 전화 무제한</div>
        </div>
      </div>
    </div>

    <!-- 대상 -->
    <div class="py-12 border-t">
      <h2 class="text-2xl font-black text-gray-800 text-center mb-3">이런 분들에게 추천합니다</h2>
      <p class="text-center text-gray-500 text-sm mb-8">프로필 설정에서 안심서비스를 허용하면 보호자가 등록할 수 있습니다</p>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-blue-50 rounded-xl p-5 text-center">
          <div class="text-3xl mb-2">🏠</div>
          <div class="font-bold text-gray-800 text-sm">독거 어르신</div>
          <div class="text-xs text-gray-500 mt-1">미국에서 혼자 생활하시는 한인 어르신</div>
        </div>
        <div class="bg-blue-50 rounded-xl p-5 text-center">
          <div class="text-3xl mb-2">🎓</div>
          <div class="font-bold text-gray-800 text-sm">유학생</div>
          <div class="text-xs text-gray-500 mt-1">부모님이 한국에 계신 유학생</div>
        </div>
        <div class="bg-blue-50 rounded-xl p-5 text-center">
          <div class="text-3xl mb-2">💼</div>
          <div class="font-bold text-gray-800 text-sm">1인 가구</div>
          <div class="text-xs text-gray-500 mt-1">혼자 살고 있는 직장인/프리랜서</div>
        </div>
      </div>
    </div>

    <!-- CTA -->
    <div class="py-12 border-t text-center">
      <div class="bg-gradient-to-r from-blue-500 to-cyan-500 rounded-2xl p-8 text-white">
        <h2 class="text-2xl font-black">지금 바로 시작하세요</h2>
        <p class="text-blue-100 text-sm mt-2">대시보드에서 보호대상 등록과 전화 스케줄을 설정할 수 있습니다</p>
        <RouterLink :to="auth.isLoggedIn ? '/dashboard?tab=elder' : '/login?redirect=/dashboard?tab=elder'"
          class="mt-5 inline-block bg-white text-blue-600 font-bold px-8 py-3 rounded-xl text-lg hover:bg-blue-50 shadow-lg">
          {{ auth.isLoggedIn ? '💙 서비스 사용하기' : '💙 로그인하고 시작하기' }}
        </RouterLink>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { useAuthStore } from '../../stores/auth'
const auth = useAuthStore()
</script>
