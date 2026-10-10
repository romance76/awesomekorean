<template>
<!-- 헤더 아이콘 "큰 글씨로 보기" — 누르면 아래에서 설정 창이 올라온다. 사이트 설정(easy_view_enabled)이 꺼져 있으면 통째로 숨김 -->
<div v-if="easyViewEnabled" class="relative">
  <button @click="toggle" aria-label="큰 글씨로 보기" title="큰 글씨로 보기"
    class="relative p-2 text-ink-light hover:text-amber-500 transition-colors flex items-center justify-center" style="min-width:36px">
    <span class="font-black leading-none ev-icon-text" style="font-size:17px">가<span style="font-size:11px">가</span></span>
    <span v-if="active" class="absolute top-1 right-1 w-2 h-2 rounded-full bg-[#FC226B]"></span>
  </button>
  <!-- 처음 오는 분께 한 번만 보여주는 안내 -->
  <div v-if="showHint && !open" class="absolute right-0 top-full mt-2 z-[60] w-56 bg-ink text-white text-[13px] rounded-xl px-3 py-2.5 shadow-lift leading-snug" role="status">
    글씨가 작으면 여기를 눌러 크게 볼 수 있어요
    <button @click="markHintSeen" class="block mt-1.5 text-[12px] text-amber-300 font-bold">알겠어요</button>
  </div>

  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[9996] flex items-end sm:items-center justify-center" @click.self="open = false">
      <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
      <div class="relative bg-white w-full sm:max-w-sm rounded-t-3xl sm:rounded-2xl p-5 space-y-4" style="padding-bottom: calc(20px + env(safe-area-inset-bottom, 0px)); font-size:16px" role="dialog" aria-label="큰 글씨로 보기">
        <div class="flex items-center justify-between">
          <div class="text-[19px] font-extrabold text-ink">큰 글씨로 보기</div>
          <button @click="open = false" class="w-11 h-11 -mr-2 rounded-full text-ink-light text-[22px]" aria-label="닫기">✕</button>
        </div>
        <div>
          <div class="text-[14px] text-ink-light mb-2">글씨 크기</div>
          <div class="grid grid-cols-3 gap-2" role="group" aria-label="글씨 크기">
            <button v-for="o in levels" :key="o.v" @click="setEasyView({ level: o.v })" :aria-pressed="prefs.level === o.v"
              class="rounded-2xl border-2 flex flex-col items-center justify-center gap-0.5"
              style="min-height:72px"
              :class="prefs.level === o.v ? 'border-[#FC226B] bg-[#fff1f5] text-[#FC226B]' : 'border-gray-200 text-ink'">
              <span class="font-extrabold" :style="{ fontSize: (15 * o.fs) + 'px', lineHeight: 1.1 }">가</span>
              <span class="text-[13px] font-bold">{{ o.l }}</span>
            </button>
          </div>
        </div>
        <label class="flex items-center gap-3 rounded-2xl border-2 px-4 cursor-pointer" style="min-height:56px" :class="prefs.hc ? 'border-[#FC226B] bg-[#fff1f5]' : 'border-gray-200'">
          <input type="checkbox" :checked="prefs.hc" @change="setEasyView({ hc: $event.target.checked })" style="width:24px;height:24px;accent-color:#FC226B" />
          <span class="text-[16px] font-bold text-ink">글씨 진하게·선명하게</span>
        </label>
        <div class="flex gap-2">
          <button @click="resetEasyView" class="flex-1 rounded-2xl border-2 border-gray-200 text-[15px] font-bold text-ink-light" style="min-height:52px">처음 상태로</button>
          <button @click="open = false" class="flex-1 rounded-2xl bg-[#FC226B] text-white text-[16px] font-extrabold" style="min-height:52px">닫기</button>
        </div>
        <p class="text-[12px] text-ink-faint text-center leading-snug">한 번 정하면 기억해요. 다시 들어오거나 다른 기기에서 로그인해도 같은 크기로 보여요.</p>
      </div>
    </div>
  </Teleport>
</div>
</template>

<script setup>
import { ref, computed } from 'vue'
import {
  EV_LEVELS, easyViewEnabled, easyViewPrefs, easyViewHintSeen,
  setEasyView, resetEasyView, markHintSeen,
} from '../composables/useEasyView'

const levels = EV_LEVELS
const prefs = easyViewPrefs
const open = ref(false)
const active = computed(() => prefs.value.level !== 'md' || prefs.value.hc)
const showHint = computed(() => !easyViewHintSeen.value && !active.value)
function toggle() { open.value = !open.value; markHintSeen() }
</script>
