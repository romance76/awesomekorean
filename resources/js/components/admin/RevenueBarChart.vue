<template>
  <!-- Phase 2-C Post-완료: 매출 바 차트 -->
  <div class="relative" :style="{ height: height + 'px' }">
    <Bar v-if="mounted" :data="chartData" :options="chartOptions" />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend } from 'chart.js'
import { Bar } from 'vue-chartjs'

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

const props = defineProps({
  series: { type: Array, required: true },
  height: { type: Number, default: 280 },
})

const mounted = ref(false)
onMounted(() => { mounted.value = true })

const chartData = computed(() => ({
  labels: props.series.map(r => r.date),
  datasets: [{
    label: '일별 매출 ($)',
    data: props.series.map(r => Number(r.revenue_usd ?? 0)),
    backgroundColor: '#f59e0b',
    borderColor: '#d97706',
    borderWidth: 1,
  }],
}))

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: { callbacks: { label: (ctx) => '$ ' + Number(ctx.raw).toFixed(2) } },
  },
  scales: {
    x: { ticks: { font: { size: 10 } } },
    y: { beginAtZero: true, ticks: { font: { size: 10 }, callback: (v) => '$' + v } },
  },
}))
</script>
