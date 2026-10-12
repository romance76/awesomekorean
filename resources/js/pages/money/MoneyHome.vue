<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <MoneyHead />

    <!-- 첫 안내 -->
    <section class="rounded-3xl p-6 md:p-9 text-white grid gap-6 md:grid-cols-[1.3fr_1fr]" style="background:linear-gradient(135deg,#0b3d2e,#0f6b4d)">
      <div>
        <div class="text-[12px] font-extrabold tracking-[2px] text-[#9ff0c9]">YOUR MONEY, YOUR OPPORTUNITY</div>
        <h2 class="text-[28px] md:text-[34px] leading-tight font-black mt-2 mb-3">미국 생활,<br>돈 되는 정보는<br>여기 다 모았다.</h2>
        <p class="text-[15px] text-[#d6f5e6] leading-relaxed mb-5">아마존 부업부터 마트 할인, 부동산, 보험, 투자, 세금까지.<br class="hidden md:block">미국에 사는 한국인을 위한 실용적인 머니 가이드.</p>
        <div class="flex flex-wrap gap-2">
          <RouterLink to="/money/cafes" class="rounded-xl bg-white text-[#0b3d2e] font-extrabold text-sm px-4 py-3">분야별 카페 둘러보기 →</RouterLink>
          <a href="#money-guides" class="rounded-xl border border-white/40 text-white font-extrabold text-sm px-4 py-3">머니 가이드 보기 ↓</a>
        </div>
      </div>
      <div class="rounded-2xl p-5 bg-white/10 border border-white/15">
        <div class="text-[12px] font-extrabold tracking-[2px] text-[#9ff0c9]">MY MONEY GUIDE</div>
        <div class="text-xl font-black mt-1.5">돈 관리, 한눈에</div>
        <RouterLink v-for="s in quick" :key="s.slug" :to="`/money/cafe/${s.slug}`" class="flex items-center gap-2.5 mt-2 rounded-xl bg-white/10 hover:bg-white/15 px-3.5 py-3 font-extrabold">{{ s.icon }} {{ s.name }}</RouterLink>
        <div class="mt-3 text-[13px] text-[#bdeed6]">💡 오늘도 하나씩 배우는 머니 팁 · 돈 되는 습관</div>
      </div>
    </section>

    <!-- 분야별 카페 -->
    <section class="mt-8">
      <div class="text-[12px] font-extrabold tracking-[2px] text-emerald-700">MONEY CAFES</div>
      <div class="flex items-end justify-between gap-3 flex-wrap">
        <h3 class="text-[22px] font-bold text-ink mt-1">분야별 머니 카페 <span class="text-sm font-medium text-ink-muted">— 가입 없이 누구나 보고, 답글로 소통해요</span></h3>
        <RouterLink to="/money/cafes" class="text-sm font-bold text-emerald-700">카페 전체 안내 →</RouterLink>
      </div>
      <div v-if="loading" class="py-10 text-center text-ink-muted">불러오는 중...</div>
      <div v-else class="mt-4 grid grid-cols-2 md:grid-cols-4 xl:grid-cols-6 gap-3">
        <RouterLink v-for="c in cafes" :key="c.slug" :to="`/money/cafe/${c.slug}`"
          class="rounded-2xl border bg-white p-4 hover:shadow-card hover:-translate-y-0.5 transition-all flex flex-col"
          :style="{ borderColor: themeOf(c.theme).line, background: `linear-gradient(160deg, ${themeOf(c.theme).from}, #fff 55%)` }">
          <div class="text-[34px] leading-none">{{ artOf(c).main }}</div>
          <div class="mt-2 font-bold text-ink">{{ c.name }}</div>
          <div class="text-[13px] text-ink-muted mt-1 leading-snug flex-1">{{ c.headline || c.summary }}</div>
          <div class="mt-3 flex justify-between text-[12px] text-ink-muted"><span>글 {{ c.post_count }}</span><span>새 글 <b :style="{ color: themeOf(c.theme).accent }">{{ c.new_count }}</b></span></div>
        </RouterLink>
      </div>
    </section>

    <!-- 머니 가이드 (설명 페이지) -->
    <section v-if="guides.length" id="money-guides" class="mt-10">
      <div class="text-[12px] font-extrabold tracking-[2px] text-emerald-700">MONEY GUIDE</div>
      <h3 class="text-[22px] font-bold text-ink mt-1">먼저 읽으면 좋은 돈 정보</h3>
      <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <RouterLink v-for="g in guides" :key="g.slug" :to="`/money/guide/${g.slug}`" class="rounded-2xl bg-[#f2fbf6] hover:bg-[#e7f7ee] p-4 transition-colors">
          <div class="text-2xl">{{ g.icon }}</div>
          <div class="mt-2 font-bold text-ink">{{ g.title }}</div>
          <div class="text-[13px] text-[#4d6b5d] mt-1">{{ g.summary }}</div>
        </RouterLink>
      </div>
    </section>
    <div v-else id="money-guides"></div>

    <!-- 많이 궁금해하는 주제 -->
    <section class="mt-10">
      <div class="text-[12px] font-extrabold tracking-[2px] text-emerald-700">POPULAR KEYWORDS</div>
      <h3 class="text-[22px] font-bold text-ink mt-1 mb-3">많이 궁금해하는 주제</h3>
      <div class="flex flex-wrap gap-2">
        <RouterLink v-for="k in keywords" :key="k.w" :to="`/money/cafe/${k.cafe}`" class="rounded-full border border-line bg-white px-4 py-2 text-sm font-bold text-ink hover:border-emerald-300">{{ k.w }} ↗</RouterLink>
      </div>
    </section>

    <p class="mt-10 pt-4 border-t border-line text-[12.5px] text-ink-muted">{{ DISCLAIMER }}</p>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import MoneyHead from '../../components/money/MoneyHead.vue'
import { DISCLAIMER } from '../../components/money/moneyText'
import { themeOf, artOf } from '../../components/money/moneyThemes'

const cafes = ref([]); const guides = ref([]); const loading = ref(true)
const quick = computed(() => cafes.value.filter(c => ['earn', 'save', 'grow'].includes(c.slug)))
const keywords = [
  { w: '아마존 부업', cafe: 'earn' }, { w: 'Etsy', cafe: 'earn' }, { w: '캐시백', cafe: 'cards' }, { w: '모기지', cafe: 'home' },
  { w: '보험료', cafe: 'insurance' }, { w: '1099 세금', cafe: 'tax' }, { w: 'IRA', cafe: 'retire' }, { w: '529 플랜', cafe: 'kids' },
  { w: '마일리지', cafe: 'cards' }, { w: '소셜연금', cafe: 'retire' },
]
onMounted(async () => {
  try {
    const { data } = await axios.get('/api/money/hub')
    cafes.value = data.data?.cafes || []
    guides.value = data.data?.guides || []
  } catch {}
  loading.value = false
})
</script>
