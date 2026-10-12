<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <MoneyHead :crumbs="[{ label: '머니 가이드' }]" />
    <!-- 폭: 바깥은 다른 MONEY 화면과 같은 큰 폭(7xl) — MONEY 제목 줄 위치가 화면마다 흔들리지 않게. 글은 읽기 좋은 폭으로 가운데 -->
    <div class="max-w-4xl mx-auto">
    <p v-if="notFound" class="card p-8 text-center text-ink-muted">가이드를 찾을 수 없어요. <RouterLink to="/money" class="text-emerald-700 font-bold underline">MONEY 홈으로</RouterLink></p>
    <article v-else-if="g">
      <div class="text-[12px] font-extrabold tracking-[2px] text-emerald-700">MONEY GUIDE · 설명 페이지</div>
      <h2 class="text-[26px] md:text-[30px] font-black text-ink mt-2">{{ g.icon }} {{ g.title }}</h2>
      <div class="text-sm text-ink-muted mt-1">{{ g.summary }} · 마지막 수정 {{ String(g.updated_at || '').slice(0, 10) }}</div>
      <div class="card p-5 md:p-7 mt-5 money-intro text-[16px] text-ink leading-relaxed" v-html="g.content"></div>
      <div class="mt-4 rounded-2xl bg-[#f2fbf6] px-4 py-3 text-sm font-bold text-[#1d5a43]">☕ 더 궁금하면 <RouterLink to="/money/cafes" class="underline">분야별 카페</RouterLink>에서 질문해 보세요.</div>
      <p class="mt-6 text-[12.5px] text-ink-muted">{{ DISCLAIMER }}</p>
    </article>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import MoneyHead from '../../components/money/MoneyHead.vue'
import { DISCLAIMER } from '../../components/money/moneyText'

const route = useRoute()
const g = ref(null); const notFound = ref(false)
async function load() {
  notFound.value = false; g.value = null
  try { const { data } = await axios.get(`/api/money/guides/${route.params.slug}`); g.value = data.data } catch { notFound.value = true }
}
watch(() => route.params.slug, (s) => { if (s) load() })
onMounted(load)
</script>
