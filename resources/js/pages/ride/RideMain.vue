<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <!-- Header -->
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-5 rounded-2xl">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-xl font-black">🚗 알바 라이드</h1>
            <p class="text-sm opacity-80 mt-0.5">한인 커뮤니티 라이드 서비스</p>
          </div>
        </div>
      </div>
    </div>

    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Left column -->
        <div class="space-y-4">
          <!-- 승객 카드 -->
          <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
            <div class="flex items-center gap-3 mb-3">
              <span class="text-2xl w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">🧑‍💼</span>
              <div>
                <p class="font-bold text-sm text-gray-800">라이드 요청</p>
                <p class="text-gray-400 text-xs">한인 드라이버와 함께</p>
              </div>
            </div>
            <button
              @click="$router.push('/ride/request')"
              class="w-full bg-orange-500 text-white font-semibold py-2.5 rounded-lg text-sm text-center hover:bg-orange-600 transition"
            >라이드 요청하기</button>
          </div>

          <!-- 드라이버 카드 -->
          <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
            <div class="flex items-center gap-3 mb-3">
              <span class="text-2xl w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">🚕</span>
              <div>
                <p class="font-bold text-sm text-gray-800">드라이버로 활동</p>
                <p class="text-gray-400 text-xs">부업으로 수입을 올리세요</p>
              </div>
            </div>
            <button
              @click="$router.push('/ride/driver')"
              class="w-full bg-blue-600 text-white font-semibold py-2.5 rounded-lg text-sm text-center hover:bg-blue-700 transition"
            >드라이버 대시보드</button>
          </div>
        </div>

        <!-- Right column -->
        <div class="space-y-4">
          <!-- 요금 안내 -->
          <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
            <h2 class="font-bold text-sm mb-2 text-blue-600">요금 안내</h2>
            <div class="space-y-1.5 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-500">기본요금</span>
                <span class="text-gray-800 font-semibold">$3.00</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">거리요금</span>
                <span class="text-gray-800 font-semibold">$1.20/mile</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">최소요금</span>
                <span class="text-gray-800 font-semibold">$5.00</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">플랫폼 수수료</span>
                <span class="text-gray-800 font-semibold">15%</span>
              </div>
              <div class="flex justify-between text-orange-500">
                <span>야간 할증 (11pm-6am)</span>
                <span class="font-semibold">1.2x</span>
              </div>
            </div>
          </div>

          <!-- 이용 내역 -->
          <button
            @click="$router.push('/ride/history')"
            class="w-full bg-white text-gray-800 py-3 rounded-xl font-semibold flex items-center justify-between px-4 shadow-sm border border-gray-100 hover:shadow-md transition text-sm"
          >
            <span>📋 이용 내역 보기</span>
            <span class="text-gray-400">→</span>
          </button>

          <!-- 안내 -->
          <div class="bg-white rounded-xl p-4 text-sm text-gray-500 shadow-sm border border-gray-100">
            <p class="text-blue-600 font-semibold mb-1.5 text-sm">안전 안내</p>
            <p>• 모든 드라이버는 실명 인증 완료</p>
            <p>• 한국어 소통 가능한 드라이버</p>
            <p>• 라이드 이용 시 포인트 20P 적립</p>
            <p>• 문제 발생 시 앱 내 신고 가능</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
</script>
