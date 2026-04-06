<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-3xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-black text-gray-800 mb-6">이용약관</h1>
    <div v-if="content" class="bg-white rounded-xl shadow-sm border p-6 text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{{ content }}</div>
    <div v-else class="bg-white rounded-xl shadow-sm border p-6 text-sm text-gray-700 leading-relaxed space-y-3">
      <h2 class="font-bold text-base">제1조 (목적)</h2>
      <p>이 약관은 SomeKorean(이하 "서비스")이 제공하는 인터넷 관련 서비스의 이용조건 및 절차, 이용자와 서비스 간의 권리, 의무 등을 규정함을 목적으로 합니다.</p>
      <h2 class="font-bold text-base">제2조 (정의)</h2>
      <p>"이용자"란 서비스에 접속하여 이 약관에 따라 서비스가 제공하는 서비스를 이용하는 회원 및 비회원을 말합니다.</p>
      <h2 class="font-bold text-base">제3조 (약관의 효력)</h2>
      <p>이 약관은 서비스 화면에 게시하거나 기타의 방법으로 이용자에게 공지함으로써 효력이 발생합니다.</p>
      <p class="text-gray-400 mt-4">최종 수정일: 2026-04-06</p>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
const content = ref('')
onMounted(async () => {
  try { const { data } = await axios.get('/api/settings/public'); content.value = data.data?.terms_page || '' } catch {}
})
</script>
