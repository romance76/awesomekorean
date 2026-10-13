<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <MoneyHead />

    <!-- 첫 안내 — 차분하게, 공식 자료 기반이라는 점을 먼저 -->
    <section class="rounded-2xl border border-line bg-white px-5 py-5 md:px-7 md:py-6">
      <h2 class="text-[22px] md:text-[26px] font-black text-ink leading-snug">미국 생활 돈 정보</h2>
      <p class="text-[15px] text-ink-light mt-1.5 leading-relaxed">IRS·SSA·CFPB 같은 <b class="text-ink">공식 자료</b>를 바탕으로 정리했어요. 가입 없이 읽을 수 있어요.</p>
    </section>

    <!-- 분야 -->
    <section class="mt-5">
      <h3 class="text-[17px] font-bold text-ink mb-2.5">분야를 골라 보세요</h3>
      <div v-if="loading" class="py-10 text-center text-ink-muted">불러오는 중...</div>
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-2.5">
        <RouterLink v-for="c in cafes" :key="c.slug" :to="`/money/cafe/${c.slug}`"
          class="flex items-center gap-3 rounded-xl border bg-white px-3.5 py-3 hover:shadow-card transition-shadow"
          :style="{ borderColor: themeOf(c.theme).line }">
          <span class="icon-chip w-10 h-10 shrink-0" :style="{ background: themeOf(c.theme).soft, color: themeOf(c.theme).accent }">
            <AppIcon :name="cafeIcon(c.slug)" :size="20" />
          </span>
          <span class="min-w-0">
            <span class="block font-bold text-ink text-[15px] leading-snug">{{ c.name }}</span>
            <span class="block text-[12.5px] text-ink-muted mt-0.5 leading-snug line-clamp-1">{{ c.headline || c.summary }}</span>
            <span class="block text-[12px] text-ink-faint mt-0.5">글 {{ c.post_count }}개</span>
          </span>
        </RouterLink>
      </div>
    </section>

    <!-- 처음이라면 (가이드) -->
    <section v-if="guides.length" id="money-guides" class="mt-7">
      <div class="flex items-end justify-between gap-3 mb-2">
        <h3 class="text-[17px] font-bold text-ink">처음이라면 먼저 읽어 보세요</h3>
        <button v-if="guides.length > SHOW" type="button" @click="all = !all" class="text-[13px] font-bold text-emerald-700">{{ all ? '접기' : `전체 ${guides.length}편 보기` }}</button>
      </div>
      <div class="rounded-xl border border-line bg-white divide-y divide-gray-100 overflow-hidden">
        <RouterLink v-for="g in shown" :key="g.slug" :to="`/money/guide/${g.slug}`" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50">
          <span class="min-w-0 flex-1">
            <span class="block text-[15px] font-semibold text-ink leading-snug truncate">{{ g.title }}</span>
            <span v-if="g.summary" class="block text-[12.5px] text-ink-muted truncate">{{ g.summary }}</span>
          </span>
          <AppIcon name="chevron-right" :size="16" class="text-ink-faint" />
        </RouterLink>
      </div>
    </section>
    <div v-else id="money-guides"></div>

    <p class="mt-8 pt-4 border-t border-line text-[12.5px] text-ink-muted">{{ DISCLAIMER }}</p>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import MoneyHead from '../../components/money/MoneyHead.vue'
import AppIcon from '../../components/AppIcon.vue'
import { DISCLAIMER } from '../../components/money/moneyText'
import { themeOf, cafeIcon } from '../../components/money/moneyThemes'

const SHOW = 6
const cafes = ref([]); const guides = ref([]); const loading = ref(true); const all = ref(false)
const shown = computed(() => (all.value ? guides.value : guides.value.slice(0, SHOW)))
onMounted(async () => {
  try {
    const { data } = await axios.get('/api/money/hub')
    // 글이 아직 하나도 없는 카페는 허브에서 숨긴다 (막다른 길 방지)
    cafes.value = (data.data?.cafes || []).filter(c => (c.post_count || 0) > 0)
    guides.value = data.data?.guides || []
  } catch {}
  loading.value = false
})
</script>
