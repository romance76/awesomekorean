<template>
<div class="max-w-md mx-auto px-4 py-14 text-center">
  <div class="icon-chip w-14 h-14 mx-auto bg-amber-50 text-amber-600 mb-4"><AppIcon name="mail" :size="26" /></div>

  <template v-if="!done">
    <h1 class="text-xl font-black text-ink">메일 수신 거부 · Unsubscribe</h1>
    <p v-if="email" class="text-sm text-ink-muted mt-2 break-all">{{ email }}</p>

    <label class="mt-6 flex items-start gap-3 text-left card p-4 cursor-pointer">
      <input v-model="agree" type="checkbox" class="mt-1 w-5 h-5 accent-amber-600 shrink-0" />
      <span class="text-sm text-ink leading-relaxed">
        이 주소로 어썸코리안의 소개·광고 메일을 더 이상 받지 않겠습니다.<br />
        <span class="text-ink-light">I do not want to receive Awesome Korean emails at this address anymore.</span>
      </span>
    </label>

    <button class="btn-primary w-full py-3 mt-4 text-sm font-bold" :disabled="!agree || busy || isSample" @click="submit">
      {{ busy ? '처리 중… / Working…' : '확인 · Confirm' }}
    </button>
    <p v-if="err" class="text-sm text-red-600 mt-3">{{ err }}</p>
    <p v-if="isSample" class="text-xs text-amber-700 bg-amber-50 rounded-lg px-3 py-2 mt-4">시험용 링크예요 — 실제로 처리되지 않아요. / This is a test link — nothing will be changed.</p>
  </template>

  <template v-else>
    <h1 class="text-xl font-black text-ink">수신 거부가 완료되었습니다</h1>
    <p class="text-sm text-ink-light mt-2 leading-relaxed">앞으로 이 주소로 어썸코리안의 소개·광고 메일을 보내지 않습니다.<br />불편을 드려 죄송합니다.</p>
    <hr class="my-6 border-gray-100" />
    <h2 class="text-lg font-black text-ink">You have been unsubscribed</h2>
    <p class="text-sm text-ink-light mt-2 leading-relaxed">We will not send Awesome Korean emails to this address again.<br />Sorry for the trouble.</p>
    <RouterLink to="/" class="btn-primary inline-block px-6 py-2 mt-6 text-sm">홈으로 / Home</RouterLink>
  </template>
</div>
</template>

<script setup>
import { ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const route = useRoute()
const email = route.query.email || ''
const isSample = route.query.sample === '1'
const agree = ref(false)
const busy = ref(false)
const done = ref(false)
const err = ref('')

async function submit() {
  if (!agree.value || busy.value || isSample) return
  busy.value = true; err.value = ''
  try {
    // 메일 속 링크의 서명(signature)을 그대로 붙여 보낸다 — 서버가 서명이 맞는 주소만 처리
    await axios.post('/api/email/outreach-unsubscribe' + window.location.search)
    done.value = true
  } catch {
    err.value = '링크가 올바르지 않거나 처리하지 못했어요. 메일의 버튼으로 다시 들어와 주세요. / The link is invalid or could not be processed.'
  } finally { busy.value = false }
}
</script>
