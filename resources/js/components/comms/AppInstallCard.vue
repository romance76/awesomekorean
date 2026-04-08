<template>
  <!-- Not installed yet -->
  <div v-if="!isInstalled"
       class="bg-gray-800 border border-gray-700 rounded-xl p-5">
    <!-- Card header -->
    <div class="flex items-start gap-3 mb-4">
      <div class="w-10 h-10 rounded-lg bg-green-500/10 flex items-center justify-center text-green-400 flex-shrink-0">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="5" y="2" width="14" height="20" rx="2"/>
          <line x1="12" y1="18" x2="12.01" y2="18"/>
        </svg>
      </div>
      <div class="min-w-0">
        <p class="text-sm font-semibold text-white">앱처럼 사용하기</p>
        <p class="text-xs text-gray-400 mt-0.5 leading-relaxed">홈화면에 추가하면 수신 알림과 벨소리가 작동해요</p>
      </div>
      <span class="ml-auto flex-shrink-0 text-[11px] font-semibold bg-green-500/15 text-green-400 px-2 py-0.5 rounded-full whitespace-nowrap">
        권장
      </span>
    </div>

    <!-- Android / Chrome install -->
    <template v-if="!isIos && canInstall">
      <div class="flex flex-col gap-2 mb-4">
        <div class="flex items-center gap-2 text-xs text-gray-300">
          <span class="w-1.5 h-1.5 rounded-full bg-green-500 flex-shrink-0"></span>
          수신 전화 알림 (앱 꺼져도)
        </div>
        <div class="flex items-center gap-2 text-xs text-gray-300">
          <span class="w-1.5 h-1.5 rounded-full bg-green-500 flex-shrink-0"></span>
          벨소리 및 진동
        </div>
        <div class="flex items-center gap-2 text-xs text-gray-300">
          <span class="w-1.5 h-1.5 rounded-full bg-green-500 flex-shrink-0"></span>
          채팅 메시지 알림
        </div>
      </div>

      <button @click="triggerInstall"
              class="w-full flex items-center justify-center gap-2 bg-green-600 hover:bg-green-500
                     text-white rounded-lg py-3 text-sm font-semibold transition-colors">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
          <polyline points="7 10 12 15 17 10"/>
          <line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
        홈화면에 추가
      </button>
    </template>

    <!-- iOS guidance -->
    <template v-else-if="isIos">
      <div class="bg-gray-900 rounded-lg p-4">
        <p class="text-xs font-semibold text-gray-300 mb-3">iPhone에서 설치하는 방법</p>
        <div class="flex flex-col gap-2">
          <div class="flex items-start gap-2.5 text-xs text-gray-400">
            <span class="w-5 h-5 rounded-full bg-green-600 text-white text-[10px] font-bold flex items-center justify-center flex-shrink-0">1</span>
            <span>하단 공유 버튼 탭</span>
          </div>
          <div class="flex items-start gap-2.5 text-xs text-gray-400">
            <span class="w-5 h-5 rounded-full bg-green-600 text-white text-[10px] font-bold flex items-center justify-center flex-shrink-0">2</span>
            <span><strong class="text-gray-200">"홈 화면에 추가"</strong> 선택</span>
          </div>
          <div class="flex items-start gap-2.5 text-xs text-gray-400">
            <span class="w-5 h-5 rounded-full bg-green-600 text-white text-[10px] font-bold flex items-center justify-center flex-shrink-0">3</span>
            <span>홈화면 아이콘으로 실행</span>
          </div>
        </div>
        <p class="text-[11px] text-gray-500 mt-3">* iOS 16.4 이상에서 알림 기능 지원</p>
      </div>
    </template>

    <!-- Success message -->
    <div v-if="installResult === 'accepted'"
         class="mt-3 bg-green-500/15 text-green-400 rounded-lg px-4 py-2.5 text-xs font-medium text-center">
      설치 완료! 홈화면에서 실행하세요
    </div>
  </div>

  <!-- Already installed -->
  <div v-else class="bg-green-500/5 border border-green-500/20 rounded-xl p-5">
    <div class="flex items-start gap-3">
      <div class="w-10 h-10 rounded-lg bg-green-600 flex items-center justify-center text-white flex-shrink-0">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
      </div>
      <div>
        <p class="text-sm font-semibold text-white">앱 설치 완료</p>
        <p class="text-xs text-gray-400 mt-0.5">수신 알림 및 벨소리가 활성화되어 있어요</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { usePwaInstall } from '@/composables/usePwaInstall'

const { canInstall, isInstalled, isIos, installResult, triggerInstall } = usePwaInstall()
</script>
