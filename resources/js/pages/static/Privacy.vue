<template>
<div class="min-h-screen">
  <div class="max-w-3xl mx-auto px-4 py-8">
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-6">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="shield" :size="20" /></span>
      개인정보처리방침
    </h1>
    <div v-if="content" class="card p-6 text-sm text-ink-light leading-relaxed whitespace-pre-wrap">{{ content }}</div>
    <div v-else class="card p-6 text-sm text-ink-light leading-relaxed space-y-3">
      <h2 class="font-bold text-base text-ink">1. 수집하는 개인정보</h2>
      <p>서비스는 회원가입, 서비스 이용 등을 위해 아래와 같은 개인정보를 수집합니다: 이름, 이메일, 닉네임, 위치정보(선택)</p>
      <h2 class="font-bold text-base text-ink">2. 개인정보의 이용목적</h2>
      <p>회원관리, 서비스 제공, 콘텐츠 제공, 마케팅 활용 등의 목적으로 사용합니다.</p>
      <h2 class="font-bold text-base text-ink">3. 개인정보의 보유기간</h2>
      <p>회원 탈퇴 시 즉시 파기합니다. 단, 관련 법령에 의해 보존할 필요가 있는 경우 해당 법령에서 정한 기간 동안 보존합니다.</p>
      <p class="text-ink-muted mt-4">최종 수정일: 2026-04-06</p>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useSiteStore } from '../../stores/site'
import AppIcon from '../../components/AppIcon.vue'
const content = ref('')
onMounted(async () => {
  const site = useSiteStore()
  await site.load()
  content.value = site.getSetting('privacy_page', '')
})
</script>
