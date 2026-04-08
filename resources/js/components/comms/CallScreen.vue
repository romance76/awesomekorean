<template>
  <Transition name="call-slide">
    <div v-if="show"
         class="fixed inset-0 z-[999] flex flex-col items-center justify-center
                bg-gradient-to-br from-gray-950 via-gray-900 to-green-950
                text-white font-sans"
         :style="{
           paddingTop: 'calc(60px + env(safe-area-inset-top))',
           paddingBottom: 'calc(40px + env(safe-area-inset-bottom))',
         }">

      <!-- ═══ Incoming call (ringing) ═══ -->
      <div v-if="callStatus === 'ringing'"
           class="flex flex-col items-center justify-between h-full w-full max-w-xs px-6">
        <!-- Caller info -->
        <div class="text-center mt-10">
          <div class="relative inline-block">
            <img :src="incomingCall?.caller_avatar || '/images/default-avatar.png'"
                 class="w-24 h-24 rounded-full object-cover border-[3px] border-green-500/50 animate-pulse-ring"
                 @error="$event.target.src = '/images/default-avatar.png'">
          </div>
          <p class="text-2xl font-bold mt-4">{{ incomingCall?.caller_name }}</p>
          <p class="text-sm text-white/60 mt-1">음성 통화 수신 중...</p>
        </div>

        <!-- Answer / Decline buttons -->
        <div class="flex gap-12 mb-5">
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
      <div v-else class="flex flex-col items-center w-full max-w-xs gap-0">
        <!-- Status label -->
        <p class="text-sm text-white/60 mb-8 tracking-wide">
          <span v-if="callStatus === 'calling'">연결 중...</span>
          <span v-else-if="callStatus === 'connected'">통화 중 &middot; {{ durationFormatted }}</span>
          <span v-else-if="callStatus === 'ended'">통화 종료</span>
        </p>

        <!-- Remote user avatar + name -->
        <div class="text-center mb-8">
          <img :src="remoteUser?.avatar || '/images/default-avatar.png'"
               class="w-28 h-28 rounded-full object-cover border-[3px] border-white/15 mx-auto"
               @error="$event.target.src = '/images/default-avatar.png'">
          <p class="text-2xl font-bold mt-4">{{ remoteUser?.name }}</p>
        </div>

        <!-- Audio wave animation (when connected) -->
        <div v-if="callStatus === 'connected'"
             class="flex items-center gap-1.5 h-10 mb-10">
          <span v-for="i in 5" :key="i"
                class="w-1 rounded-sm bg-green-500/70 animate-audio-wave"
                :style="{ animationDelay: `${i * 0.1}s` }"></span>
        </div>

        <!-- Controls -->
        <div class="flex gap-7 mt-auto">
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
  </Transition>
</template>

<script setup>
defineProps({
  show:              Boolean,
  callStatus:        String,
  incomingCall:      Object,
  remoteUser:        Object,
  isMuted:           Boolean,
  isSpeaker:         Boolean,
  durationFormatted: String,
})

defineEmits(['answer', 'decline', 'end', 'toggle-mute'])
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
