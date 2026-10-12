<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <MoneyHead :crumbs="[{ label: '분야별 카페' }]" />

    <section class="rounded-[26px] border border-[#deeee6] px-6 py-8 md:px-10 md:py-10" style="background:linear-gradient(125deg,#e9f7f0,#fff 62%)">
      <div class="text-[12px] font-black tracking-[2px] text-[#008b67]">MONEY CAFES</div>
      <h2 class="text-[28px] md:text-[40px] font-black text-[#182b28] leading-tight mt-2">돈 이야기, 분야별로 모였어요</h2>
      <p class="text-[15px] md:text-[16px] text-ink-light mt-3 max-w-2xl">벌기·아끼기·불리기부터 집·보험·세금·은퇴까지. 가입 없이 누구나 들어가 읽고 답글을 달 수 있어요. 관심 가는 카페를 눌러 보세요.</p>
    </section>

    <div v-if="loading" class="py-10 text-center text-ink-muted">불러오는 중...</div>
    <div v-else class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-6">
      <RouterLink v-for="c in cafes" :key="c.slug" :to="`/money/cafe/${c.slug}`" class="mcs-card group"
        :style="{ borderColor: th(c).line }">
        <div class="mcs-top" :style="{ background: `linear-gradient(125deg, ${th(c).from}, ${th(c).art})` }">
          <span class="mcs-emoji">{{ artOf(c).main }}</span>
          <span class="mcs-mini mcs-mini-a">{{ artOf(c).a }}</span>
          <span class="mcs-mini mcs-mini-b">{{ artOf(c).b }}</span>
          <span v-if="c.post_count" class="absolute left-4 top-4 text-[12px] font-bold rounded-full bg-white/85 px-2.5 py-1" :style="{ color: th(c).ink }">글 {{ c.post_count }}개</span>
        </div>
        <div class="p-5 flex flex-col flex-1">
          <div class="text-[20px] font-black text-ink">{{ c.name }}</div>
          <div class="text-[15px] font-semibold text-ink-light leading-snug mt-1">{{ c.headline || c.summary }}</div>
          <div v-if="c.categories?.length" class="flex flex-wrap gap-1.5 mt-3">
            <span v-for="k in c.categories.slice(0, 4)" :key="k.id" class="text-[12px] font-semibold rounded-full px-2.5 py-1" :style="{ background: th(c).soft, color: th(c).ink }">{{ k.icon }} {{ k.name }}</span>
            <span v-if="c.categories.length > 4" class="text-[12px] font-semibold text-ink-faint px-1 py-1">+{{ c.categories.length - 4 }}</span>
          </div>
          <div class="mt-auto pt-4 text-[14px] font-bold" :style="{ color: th(c).accent }">카페 들어가기 <span class="inline-block transition-transform group-hover:translate-x-1">→</span></div>
        </div>
      </RouterLink>
    </div>

    <p class="mt-6 text-[12.5px] text-ink-muted">{{ DISCLAIMER }}</p>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import MoneyHead from '../../components/money/MoneyHead.vue'
import { DISCLAIMER } from '../../components/money/moneyText'
import { themeOf, artOf } from '../../components/money/moneyThemes'

const cafes = ref([]); const loading = ref(true)
const th = (c) => themeOf(c.theme)
onMounted(async () => {
  try { const { data } = await axios.get('/api/money/cafes'); cafes.value = data.data || [] } catch {}
  loading.value = false
})
</script>

<style scoped>
.mcs-card { display: flex; flex-direction: column; background: #fff; border: 1px solid; border-radius: 22px; overflow: hidden; transition: transform .15s, box-shadow .15s; }
.mcs-card:hover { transform: translateY(-3px); box-shadow: 0 12px 28px rgba(0,0,0,.08); }
.mcs-top { position: relative; height: 150px; display: grid; place-items: center; overflow: hidden; }
.mcs-emoji { font-size: 74px; line-height: 1; filter: drop-shadow(0 10px 10px rgba(0,0,0,.12)); transition: transform .2s; }
.mcs-card:hover .mcs-emoji { transform: scale(1.08) rotate(-4deg); }
.mcs-mini { position: absolute; font-size: 24px; }
.mcs-mini-a { right: 22%; top: 18%; }
.mcs-mini-b { left: 24%; bottom: 16%; }
</style>
