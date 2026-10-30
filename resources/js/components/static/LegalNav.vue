<template>
<nav aria-label="약관 및 안내" class="mb-5 -mx-1 flex flex-wrap items-center gap-x-1 gap-y-1 border-y border-gray-200 py-2 text-[13px]">
  <RouterLink v-for="l in links" :key="l.to" :to="l.to" class="px-2.5 py-1 rounded-lg whitespace-nowrap transition-colors" :class="route.path === l.to ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-muted hover:text-ink'">{{ l.label }}</RouterLink>
</nav>
</template>

<script setup>
import { RouterLink, useRoute } from 'vue-router'

// 약관·방침·법적 고지 페이지들이 같이 쓰는 윗줄 메뉴 (소개/이용약관/개인정보/이메일 무단수집거부/책임의 한계와 법적고지/문의)
const route = useRoute()
const links = [
  { to: '/about', label: '사이트 소개' },
  { to: '/terms', label: '이용약관' },
  { to: '/privacy', label: '개인정보처리방침' },
  { to: '/email-policy', label: '이메일 무단수집거부' },
  { to: '/legal', label: '책임의 한계와 법적고지' },
  { to: '/contact', label: '문의하기' },
]
</script>
