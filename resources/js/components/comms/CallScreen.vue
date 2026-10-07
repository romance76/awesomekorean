<template>
    <div v-if="show"
         class="fixed inset-0 z-[999] flex flex-col items-center justify-between
                bg-gradient-to-br from-gray-950 via-gray-900 to-green-950
                text-white font-sans overflow-hidden"
         style="padding-top: max(40px, var(--sat, 0px)); padding-bottom: max(30px, var(--sab, 0px));">

      <!-- ═══ Incoming call (ringing) ═══ -->
      <div v-if="callStatus === 'ringing'"
           class="flex flex-col items-center justify-center gap-6 flex-1 w-full max-w-xs px-6">
        <!-- Caller info -->
        <div class="text-center">
          <!-- 안심 전화 표시 -->
          <div v-if="isElderCall" class="mb-3">
            <div class="inline-flex items-center gap-2 bg-purple-600/80 px-4 py-2 rounded-full">
              <span class="text-lg">🛡️</span>
              <span class="text-sm font-bold">안심서비스 전화</span>
            </div>
          </div>
          <div class="relative inline-block">
            <img :src="isElderCall ? '/images/logo.png' : (incomingCall?.caller_avatar || '/images/default-avatar.svg')"
                 class="w-24 h-24 rounded-full object-cover border-[3px] animate-pulse-ring"
                 :class="isElderCall ? 'border-purple-500/50' : 'border-green-500/50'"
                 @error="$event.target.src = '/images/default-avatar.svg'">
          </div>
          <p class="text-2xl font-bold mt-4">{{ isElderCall ? '안심서비스' : incomingCall?.caller_name }}</p>
          <p class="text-sm text-white/60 mt-1">{{ isElderCall ? '안심 확인 전화가 왔습니다' : '음성 통화 수신 중...' }}</p>
          <p v-if="isElderCall" class="text-xs text-purple-300 mt-2">전화를 받으시면 안심 체크인이 완료됩니다</p>
        </div>

        <!-- Answer / Decline buttons -->
        <div class="flex gap-12">
          <!-- Decline -->
          <button @click="$emit('decline')"
                  class="flex flex-col items-center gap-2">
            <div class="w-16 h-16 rounded-full bg-red-500 flex items-center justify-center
                        hover:bg-red-400 transition-colors shadow-lg shadow-red-500/30">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10.68 13.31a16 16 0 003.41 2.6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81 19.79 19.79 0 01.01 1.18 2 2 0 012 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </div>
            <span class="text-xs text-white/60">거절</span>
          </button>

          <!-- Answer -->
          <button @click="$emit('answer')"
                  class="flex flex-col items-center gap-2">
            <div class="w-16 h-16 rounded-full bg-green-500 flex items-center justify-center
                        hover:bg-green-400 transition-colors shadow-lg shadow-green-500/30">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81 19.79 19.79 0 01.01 1.18 2 2 0 012 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
              </svg>
            </div>
            <span class="text-xs text-white/60">수락</span>
          </button>
        </div>
      </div>

      <!-- ═══ Active call (calling / connected / ended) ═══ -->
      <div v-else class="flex flex-col items-center justify-between w-full max-w-xs flex-1 py-4">
        <!-- 안심 전화 배지 -->
        <div v-if="isElderCall" class="inline-flex items-center gap-2 bg-purple-600/80 px-3 py-1 rounded-full mb-2">
          <span>🛡️</span><span class="text-xs font-bold">안심서비스</span>
        </div>

        <!-- Status label -->
        <p class="text-sm text-white/60 mb-4 tracking-wide">
          <span v-if="callStatus === 'calling'">발신 중...</span>
          <span v-else-if="callStatus === 'connecting'">연결 중...</span>
          <span v-else-if="callStatus === 'connected'">{{ isElderCall ? '안심 확인 중' : '통화 중' }} &middot; {{ durationFormatted }}</span>
          <span v-else-if="callStatus === 'ended'">{{ isElderCall ? '안심 체크인 완료' : '통화 종료' }}</span>
        </p>
        <p v-if="notice && callStatus === 'ended'" class="text-sm text-amber-300 text-center px-4 -mt-2 mb-3">{{ notice }}</p>

        <!-- Remote user avatar + name -->
        <div class="text-center mb-4">
          <img :src="isElderCall ? '/images/logo.png' : (remoteUser?.avatar || '/images/default-avatar.svg')"
               class="w-28 h-28 rounded-full object-cover border-[3px] border-white/15 mx-auto"
               @error="$event.target.src = '/images/default-avatar.svg'">
          <p class="text-2xl font-bold mt-4">{{ isElderCall ? '안심서비스' : remoteUser?.name }}</p>
        </div>

        <!-- ★ 모바일 오디오 차단 시 "소리 켜기" 버튼 -->
        <button v-if="remoteAudioBlocked && callStatus === 'connected'"
                @click="$emit('unblock-audio')"
                class="bg-yellow-400 text-black font-bold px-8 py-3 rounded-full mb-6 animate-pulse text-sm shadow-lg">
          🔊 탭하여 소리 켜기
        </button>

        <!-- Audio wave animation (when connected) -->
        <div v-if="callStatus === 'connected'"
             class="flex items-center gap-1.5 h-10 mb-10">
          <span v-for="i in 5" :key="i"
                class="w-1 rounded-sm bg-green-500/70 animate-audio-wave"
                :style="{ animationDelay: `${i * 0.1}s` }"></span>
        </div>

        <!-- Controls (하단 고정) -->
        <div class="flex gap-8 mt-auto pb-4">
          <!-- Mute -->
          <div class="flex flex-col items-center gap-2">
            <button @click="$emit('toggle-mute')"
                    class="w-14 h-14 rounded-full flex items-center justify-center transition-colors"
                    :class="isMuted ? 'bg-white/30' : 'bg-white/12 hover:bg-white/20'">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <template v-if="isMuted">
                  <line x1="1" y1="1" x2="23" y2="23"/>
                  <path d="M9 9v3a3 3 0 005.12 2.12M15 9.34V4a3 3 0 00-5.94-.6"/>
                  <path d="M17 16.95A7 7 0 015 12v-2m14 0v2a7 7 0 01-.11 1.23M12 19v3M8 23h8"/>
                </template>
                <template v-else>
                  <path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/>
                  <path d="M19 10v2a7 7 0 01-14 0v-2M12 19v3M8 23h8"/>
                </template>
              </svg>
            </button>
            <span class="text-xs text-white/60">{{ isMuted ? '음소거 해제' : '음소거' }}</span>
          </div>

          <!-- Speaker -->
          <div class="flex flex-col items-center gap-2">
            <button @click="$emit('toggle-speaker')"
                    class="w-14 h-14 rounded-full flex items-center justify-center transition-colors"
                    :class="isSpeaker ? 'bg-white/30' : 'bg-white/12 hover:bg-white/20'">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                <path v-if="isSpeaker" d="M19.07 4.93a10 10 0 010 14.14M15.54 8.46a5 5 0 010 7.07"/>
                <line v-else x1="23" y1="9" x2="17" y2="15"/>
              </svg>
            </button>
            <span class="text-xs text-white/60">{{ isSpeaker ? '스피커 ON' : '스피커 OFF' }}</span>
          </div>

          <!-- End call -->
          <div class="flex flex-col items-center gap-2">
            <button @click="$emit('end')"
                    class="w-14 h-14 rounded-full bg-red-500 flex items-center justify-center
                           hover:bg-red-400 transition-colors shadow-lg shadow-red-500/30">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10.68 13.31a16 16 0 003.41 2.6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81 19.79 19.79 0 01.01 1.18 2 2 0 012 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
            <span class="text-xs text-white/60">종료</span>
          </div>
        </div>
      </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  show:               Boolean,
  callStatus:         String,
  incomingCall:       Object,
  remoteUser:         Object,
  isMuted:            Boolean,
  isSpeaker:          Boolean,
  durationFormatted:  String,
  remoteAudioBlocked: Boolean,
  notice:             { type: String, default: '' },
})

const isElderCall = computed(() => {
  return props.incomingCall?.call_type === 'elder' || props.remoteUser?.call_type === 'elder'
})

defineEmits(['answer', 'decline', 'end', 'toggle-mute', 'toggle-speaker', 'unblock-audio'])
</script>

<!--
  Tailwind-only styles. Custom animations defined below since Tailwind
  doesn't have built-in pulse-ring or audio-wave keyframes.
  Using a non-scoped style block so Transition classes work properly.
-->
<style>
/* Slide-up transition for call screen */
.call-slide-enter-active,
.call-slide-leave-active {
  transition: transform 0.3s ease;
}
.call-slide-enter-from,
.call-slide-leave-to {
  transform: translateY(100%);
}

/* Pulsing ring around incoming caller avatar */
@keyframes pulse-ring {
  0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4); }
  50%      { box-shadow: 0 0 0 20px rgba(34, 197, 94, 0); }
}
.animate-pulse-ring {
  animation: pulse-ring 1.5s ease infinite;
}

/* Audio wave bars during connected call */
@keyframes audio-wave {
  from { height: 8px; }
  to   { height: 36px; }
}
.animate-audio-wave {
  animation: audio-wave 0.8s ease-in-out infinite alternate;
}
</style>
