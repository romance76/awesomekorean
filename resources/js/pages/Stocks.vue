<template>
<div class="max-w-4xl mx-auto px-4 py-6">
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-1">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="trending-up" :size="20" /></span>
    증권
  </h1>
  <p class="text-sm text-ink-muted mb-5">15분마다 갱신되는 주요 지수와 관심종목 시세예요.</p>

  <div class="card overflow-hidden mb-6">
    <div class="px-4 py-3 border-b border-line font-bold text-sm text-ink">주요 지수</div>
    <table class="w-full text-sm">
      <thead>
        <tr class="text-left text-ink-muted text-xs border-b border-line">
          <th class="px-4 py-2 font-semibold">지수</th>
          <th class="px-4 py-2 font-semibold text-right">현재가</th>
          <th class="px-4 py-2 font-semibold text-right">전일대비</th>
          <th class="px-4 py-2 font-semibold text-right hidden sm:table-cell">등락률</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="q in indices" :key="q.symbol" class="border-b border-line last:border-0">
          <td class="px-4 py-3 font-semibold text-ink">{{ q.name }}</td>
          <td class="px-4 py-3 text-right tabular-nums text-ink">{{ Number(q.price).toLocaleString(undefined, {maximumFractionDigits: 2}) }}</td>
          <td class="px-4 py-3 text-right tabular-nums font-bold" :class="Number(q.change_pct) >= 0 ? 'text-[#E8442E]' : 'text-blue-500'">
            {{ Number(q.change) >= 0 ? '▲' : '▼' }} {{ Math.abs(Number(q.change)).toLocaleString(undefined, {maximumFractionDigits: 2}) }}
          </td>
          <td class="px-4 py-3 text-right tabular-nums font-bold hidden sm:table-cell" :class="Number(q.change_pct) >= 0 ? 'text-[#E8442E]' : 'text-blue-500'">
            {{ Number(q.change_pct) >= 0 ? '▲' : '▼' }} {{ Math.abs(Number(q.change_pct)).toFixed(2) }}%
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="card overflow-hidden">
    <div class="px-4 py-3 border-b border-line font-bold text-sm text-ink">관심 종목</div>
    <table class="w-full text-sm">
      <thead>
        <tr class="text-left text-ink-muted text-xs border-b border-line">
          <th class="px-4 py-2 font-semibold">종목명</th>
          <th class="px-4 py-2 font-semibold text-right">현재가</th>
          <th class="px-4 py-2 font-semibold text-right">등락률</th>
          <th class="px-4 py-2 font-semibold text-right hidden sm:table-cell">거래량</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="q in watchlist" :key="q.symbol" class="border-b border-line last:border-0">
          <td class="px-4 py-3 font-semibold text-ink">{{ q.name }}</td>
          <td class="px-4 py-3 text-right tabular-nums text-ink">{{ Number(q.price).toLocaleString() }}</td>
          <td class="px-4 py-3 text-right tabular-nums font-bold" :class="Number(q.change_pct) >= 0 ? 'text-[#E8442E]' : 'text-blue-500'">
            {{ Number(q.change_pct) >= 0 ? '▲' : '▼' }} {{ Math.abs(Number(q.change_pct)).toFixed(2) }}%
          </td>
          <td class="px-4 py-3 text-right tabular-nums text-ink-muted hidden sm:table-cell">{{ q.volume ? Number(q.volume).toLocaleString() : '-' }}</td>
        </tr>
      </tbody>
    </table>
  </div>

  <div v-if="!loading && !indices.length && !watchlist.length" class="py-16 text-center">
    <p class="text-sm text-ink-muted">시세 데이터를 불러올 수 없어요. 잠시 후 다시 시도해주세요.</p>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../components/AppIcon.vue'

const indices = ref([])
const watchlist = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/market-quotes')
    indices.value = data.data?.indices || []
    watchlist.value = data.data?.watchlist || []
  } catch {}
  loading.value = false
})
</script>
