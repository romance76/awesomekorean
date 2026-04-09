<template>
<div class="min-h-screen bg-gray-50">

  <!-- ═══ 랜딩 페이지 (비로그인 또는 미설정) ═══ -->
  <div v-if="page === 'landing'">
    <!-- 히어로 -->
    <div class="bg-gradient-to-br from-blue-600 via-cyan-500 to-teal-400 text-white">
      <div class="max-w-5xl mx-auto px-4 py-16 text-center">
        <div class="inline-flex items-center gap-2 bg-white/20 rounded-full px-4 py-1.5 text-sm font-bold mb-4">💙 SomeKorean 안심서비스</div>
        <h1 class="text-3xl md:text-4xl font-black leading-tight">미국에서 혼자 계신<br/>한인 분들의 안전을 지킵니다</h1>
        <p class="text-blue-100 mt-4 max-w-xl mx-auto">매일 한 번의 체크인으로 가족에게 안부를 전하고, 위급 시 한 번의 터치로 도움을 요청하세요.</p>
        <div class="mt-8 flex justify-center gap-3">
          <RouterLink v-if="auth.isLoggedIn" to="/dashboard?tab=elder" class="bg-white text-blue-600 font-bold px-8 py-3 rounded-xl text-lg hover:bg-blue-50 shadow-lg transition">서비스 사용하기</RouterLink>
          <RouterLink v-else to="/login?redirect=/dashboard?tab=elder" class="bg-white text-blue-600 font-bold px-8 py-3 rounded-xl text-lg hover:bg-blue-50 shadow-lg transition">로그인하고 시작</RouterLink>
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
            <h3 class="font-bold text-gray-800 text-sm">보호자 등록</h3>
            <p class="text-xs text-gray-500 mt-1">가족이나 지인의 이메일을 등록하세요</p>
          </div>
          <div class="text-center">
            <div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">2️⃣</div>
            <h3 class="font-bold text-gray-800 text-sm">매일 체크인</h3>
            <p class="text-xs text-gray-500 mt-1">하루 한 번 "잘 지내요" 버튼을 눌러주세요</p>
          </div>
          <div class="text-center">
            <div class="w-16 h-16 bg-amber-100 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">3️⃣</div>
            <h3 class="font-bold text-gray-800 text-sm">자동 알림</h3>
            <p class="text-xs text-gray-500 mt-1">보호자에게 체크인 결과가 자동 전달됩니다</p>
          </div>
          <div class="text-center">
            <div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">4️⃣</div>
            <h3 class="font-bold text-gray-800 text-sm">긴급 SOS</h3>
            <p class="text-xs text-gray-500 mt-1">위급 시 한 번의 터치로 보호자에게 긴급 알림</p>
          </div>
        </div>
      </div>

      <!-- 주요 기능 -->
      <div class="py-12 border-t">
        <h2 class="text-2xl font-black text-gray-800 text-center mb-8">주요 기능</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div class="bg-white rounded-2xl shadow-sm border p-6 flex items-start gap-4">
            <div class="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">✅</div>
            <div>
              <h3 class="font-bold text-gray-800">매일 체크인</h3>
              <p class="text-sm text-gray-500 mt-1">하루 한 번 버튼만 누르면 됩니다. 포인트도 적립되고, 보호자에게 자동으로 안부가 전달됩니다.</p>
            </div>
          </div>
          <div class="bg-white rounded-2xl shadow-sm border p-6 flex items-start gap-4">
            <div class="w-14 h-14 bg-red-100 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">🚨</div>
            <div>
              <h3 class="font-bold text-gray-800">긴급 SOS</h3>
              <p class="text-sm text-gray-500 mt-1">넘어지거나, 아프거나, 위험한 상황에서 SOS 버튼 한 번으로 보호자에게 즉시 알림이 갑니다.</p>
            </div>
          </div>
          <div class="bg-white rounded-2xl shadow-sm border p-6 flex items-start gap-4">
            <div class="w-14 h-14 bg-amber-100 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">⏰</div>
            <div>
              <h3 class="font-bold text-gray-800">미체크인 자동 경고</h3>
              <p class="text-sm text-gray-500 mt-1">설정한 시간까지 체크인하지 않으면 보호자에게 "오늘 체크인이 없습니다" 알림이 자동 전송됩니다.</p>
            </div>
          </div>
          <div class="bg-white rounded-2xl shadow-sm border p-6 flex items-start gap-4">
            <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">👨‍👩‍👧</div>
            <div>
              <h3 class="font-bold text-gray-800">보호자 대시보드</h3>
              <p class="text-sm text-gray-500 mt-1">가족이 보호자로 등록하면 체크인 기록, SOS 이력을 한눈에 확인할 수 있습니다.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- 대상 -->
      <div class="py-12 border-t">
        <h2 class="text-2xl font-black text-gray-800 text-center mb-3">이런 분들에게 추천합니다</h2>
        <p class="text-center text-gray-500 text-sm mb-8">무료이며 언제든 해제할 수 있습니다</p>
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
          <p class="text-blue-100 text-sm mt-2">설정은 1분이면 됩니다</p>
          <button v-if="auth.isLoggedIn" @click="page='setup'" class="mt-5 bg-white text-blue-600 font-bold px-8 py-3 rounded-xl text-lg hover:bg-blue-50 shadow-lg">💙 안심서비스 시작하기</button>
          <RouterLink v-else to="/login" class="mt-5 inline-block bg-white text-blue-600 font-bold px-8 py-3 rounded-xl text-lg hover:bg-blue-50 shadow-lg">로그인하고 시작하기</RouterLink>
        </div>
      </div>
    </div>
  </div>

  <!-- 로그인한 사용자는 대시보드로 안내 -->
  <div v-else class="max-w-3xl mx-auto px-4 py-12 text-center">
    <div class="text-4xl mb-4">💙</div>
    <h2 class="text-xl font-black text-gray-800 mb-2">안심서비스 관리</h2>
    <p class="text-sm text-gray-500 mb-6">대시보드에서 보호대상 등록, 스케줄 설정 등을 관리할 수 있습니다.</p>
    <RouterLink to="/dashboard?tab=elder" class="bg-blue-500 text-white font-bold px-8 py-3 rounded-xl text-lg hover:bg-blue-600 shadow-lg transition inline-block">대시보드에서 관리하기</RouterLink>
  </div>
</div>
</template>

<script setup>
import { ref } from 'vue'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const page = ref(auth.isLoggedIn ? 'loggedIn' : 'landing')
</script>
