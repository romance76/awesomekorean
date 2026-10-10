<template>
<Teleport to="body">
  <div v-if="reauthState.open" class="fixed inset-0 z-[9990] bg-black/45 flex items-end sm:items-center justify-center" @click.self="cancelReauth">
    <form class="w-full sm:max-w-sm bg-white rounded-t-3xl sm:rounded-2xl px-5 pt-5" role="dialog" aria-modal="true" aria-label="비밀번호 확인"
      :style="{ paddingBottom: 'calc(20px + env(safe-area-inset-bottom, 0px))' }" @submit.prevent="submitReauth(pw)">
      <div class="text-[18px] font-extrabold text-ink">비밀번호를 한 번 더 입력해 주세요</div>
      <p class="text-[13px] text-ink-muted mt-1 leading-relaxed">키·결제 설정·회원 삭제처럼 위험한 작업이라 확인이 필요해요. 한 번 확인하면 10분 동안은 다시 묻지 않아요.</p>
      <input ref="inputEl" v-model="pw" type="password" autocomplete="current-password" inputmode="text" maxlength="200"
        class="mt-3 w-full rounded-xl border border-gray-200 px-3.5 text-[16px]" style="min-height:50px" placeholder="내 관리자 비밀번호" />
      <p v-if="reauthState.error" class="mt-2 text-[14px] text-red-600">{{ reauthState.error }}</p>
      <div class="grid grid-cols-2 gap-2 mt-4">
        <button type="button" @click="cancelReauth" :disabled="reauthState.busy" class="rounded-xl bg-gray-100 text-ink text-[15px] font-bold" style="min-height:50px">취소</button>
        <button type="submit" :disabled="reauthState.busy" class="rounded-xl bg-amber-500 text-white text-[15px] font-extrabold disabled:opacity-50" style="min-height:50px">{{ reauthState.busy ? '확인 중...' : '확인' }}</button>
      </div>
    </form>
  </div>
</Teleport>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'
import { reauthState, submitReauth, cancelReauth } from '../../composables/useReauth'

const pw = ref('')
const inputEl = ref(null)
watch(() => reauthState.open, (o) => {
  if (o) { pw.value = ''; nextTick(() => inputEl.value?.focus()) } else { pw.value = '' }   // 닫히면 입력한 비밀번호를 바로 지운다
})
</script>
