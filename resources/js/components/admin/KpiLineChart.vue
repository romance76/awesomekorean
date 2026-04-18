<template>
  <!-- Phase 2-C Post-완료: KPI 라인 차트 (Chart.js) -->
  <div class="relative" :style="{ height: height + 'px' }">
    <Line v-if="mounted" :data="chartData" :options="chartOptions" />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler } from 'chart.js'
import { Line } from 'vue-chartjs'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler)

const props = defineProps({
  series: { type: Array, required: true },         // [{date, dau, new_users, posts, ...}]
  metrics: { type: Array, default: () => [
    { key: 'dau', label: 'DAU', color: '#f59e0b' },
    { key: 'new_users', label: '신규', color: '#3b82f6' },
    { key: 'posts_count', label: '게시글', color: '#10b981' },
  ]},
  height: { type: Number, default: 300 },
})

const mounted = ref(false)
onMounted(() => { mounted.value = true })

const chartData = computed(() => ({
  labels: props.series.map(r => r.date),
  datasets: props.metrics.map(m => ({
    label: m.label,
    data: props.series.map(r => Number(r[m.key] ?? 0)),
    borderColor: m.color,
    backgroundColor: m.color + '20',
    tension: 0.3,
    fill: true,
    pointRadius: 2,
    pointHoverRadius: 5,
  })),
}))

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'top', labels: { font: { size: 11 } } },
    tooltip: { mode: 'index', intersect: false },
  },
  scales: {
    x: { ticks: { font: { size: 10 } } },
    y: { beginAtZero: true, ticks: { font: { size: 10 } } },
  },
}))
</script>
