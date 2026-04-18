<template>
  <!-- /admin/v2/users/:id/point-history (Phase 2-C Post) -->
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">💰 유저 #{{ userId }} 포인트 이력</h2>
      <router-link to="/admin/v2/users/point-ops" class="text-sm text-amber-600 hover:text-amber-800">← 운영 도구</router-link>
    </div>

    <DataTable
      :rows="rows"
      :columns="columns"
      :loading="loading"
      :page-size="30"
      exportable
      empty-text="포인트 이력 없음"
    >
      <template #cell-amount="{ value }">
        <span :class="['font-bold', value >= 0 ? 'text-green-600' : 'text-red-500']">
          {{ value >= 0 ? '+' : '' }}{{ Number(value).toLocaleString() }}
        </span>
      </template>
      <template #cell-created_at="{ value }">
        {{ fmtDate(value) }}
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import DataTable from '../../../components/admin/DataTable.vue'

const route = useRoute()
const userId = computed(() => route.params.id || route.query.id)
const rows = ref([])
const loading = ref(true)
const fmtDate = (s) => s ? new Date(s).toLocaleString('ko-KR') : ''

const columns = [
  { key: 'created_at', label: '일시', sortable: true },
  { key: 'amount', label: '금액', sortable: true, class: 'text-right' },
  { key: 'reason', label: '사유' },
  { key: 'balance', label: '잔액', sortable: true, class: 'text-right font-mono' },
]

onMounted(async () => {
  if (!userId.value) return
  try {
    const { data } = await axios.get(`/api/admin/users/${userId.value}/point-history`)
    rows.value = data.data || []
  } finally { loading.value = false }
})
</script>
