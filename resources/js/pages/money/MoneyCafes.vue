<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <MoneyHead :crumbs="[{ label: '분야별 카페 안내' }]" />
    <p class="text-[15px] text-ink-light mb-4">카페마다 다루는 이야기를 모았어요. 가입 없이 누구나 들어가 보고 답글을 달 수 있어요.</p>

    <!-- 바로가기 -->
    <div class="flex gap-2 overflow-x-auto scrollbar-hide pb-1 mb-5">
      <a v-for="c in cafes" :key="c.slug" :href="`#cafe-${c.slug}`" class="shrink-0 rounded-full border border-line bg-white px-3.5 py-1.5 text-sm font-bold text-ink hover:border-emerald-300">{{ c.icon }} {{ c.name }}</a>
    </div>

    <div v-if="loading" class="py-10 text-center text-ink-muted">불러오는 중...</div>
    <div v-else class="space-y-4">
      <section v-for="c in cafes" :key="c.slug" :id="`cafe-${c.slug}`" class="card p-5 md:p-6 scroll-mt-40">
        <div class="flex items-start gap-3">
          <div class="text-3xl leading-none">{{ c.icon }}</div>
          <div class="min-w-0 flex-1">
            <h2 class="text-lg font-bold text-ink">{{ c.name }} 카페</h2>
            <div class="text-sm text-ink-muted">{{ c.summary }} · 글 {{ c.post_count }}개</div>
          </div>
          <RouterLink :to="`/money/cafe/${c.slug}`" class="shrink-0 rounded-xl bg-emerald-600 text-white text-sm font-bold px-4 py-2.5 hover:bg-emerald-700">들어가기 →</RouterLink>
        </div>
        <div class="money-intro mt-4 text-[15px] text-ink-light leading-relaxed" v-html="c.intro"></div>
      </section>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import MoneyHead from '../../components/money/MoneyHead.vue'

const cafes = ref([]); const loading = ref(true)
onMounted(async () => {
  try { const { data } = await axios.get('/api/money/cafes'); cafes.value = data.data || [] } catch {}
  loading.value = false
})
</script>
