<template>
<div class="max-w-md mx-auto px-4 py-16 text-center">
  <div class="icon-chip w-14 h-14 mx-auto bg-amber-50 text-amber-600 mb-4"><AppIcon name="mail" :size="26" /></div>
  <h1 class="text-xl font-black text-ink">메일 수신을 껐어요</h1>
  <p class="text-sm text-ink-light mt-2 leading-relaxed">앞으로 어썸코리안의 소식·광고 메일은 보내지 않아요.<br />(로그인·인증·비밀번호 재설정처럼 꼭 필요한 안내 메일은 계속 갈 수 있어요.)</p>
  <p class="text-sm text-ink-muted mt-4">마음이 바뀌면 <RouterLink to="/dashboard" class="text-amber-700 font-bold underline">마이페이지</RouterLink>의 "메일 수신 설정"에서 다시 켤 수 있어요.</p>
  <RouterLink to="/" class="btn-primary inline-block px-6 py-2 mt-6 text-sm">홈으로</RouterLink>
</div>
</template>

<script setup>
import { RouterLink } from 'vue-router'
import AppIcon from '../../components/AppIcon.vue'
</script>
