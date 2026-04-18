<template>
  <!-- /mypage/payments (Phase 2-C Post) -->
  <div class="bg-white rounded-xl shadow-sm p-4">
    <h3 class="font-bold mb-3">💳 결제 내역</h3>

    <div v-if="loading" class="p-6 text-center text-sm text-gray-400">로딩 중...</div>
    <div v-else-if="!items.length" class="p-10 text-center text-sm text-gray-500">
      <p class="text-3xl mb-2">🧾</p>
      <p>결제 내역이 없습니다.</p>
    </div>
    <ul v-else class="divide-y">
      <li v-for="p in items" :key="p.id" class="py-3 flex items-center justify-between gap-2">
        <div class="flex-1 min-w-0">
          <p class="font-semibold text-sm">{{ p.description || p.product_name || '포인트 구매' }}</p>
          <p class="text-xs text-gray-500 mt-0.5">
            <span :class="statusBadge(p.status)">{{ statusLabel(p.status) }}</span>
            <span class="ml-2">{{ fmtDate(p.created_at) }}</span>
          </p>
        </div>
        <div class="text-right">
          <p class="font-bold">${{ Number(p.amount).toFixed(2) }}</p>
          <button @click="invoice(p)" class="text-xs text-amber-600 hover:text-amber-800 mt-1">📄 영수증</button>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const items = ref([])
const loading = ref(true)
const fmtDate = (s) => s ? new Date(s).toLocaleString('ko-KR') : ''

const statusBadge = (s) => {
  const m = {
    completed: 'bg-green-100 text-green-700',
    pending: 'bg-yellow-100 text-yellow-700',
    failed: 'bg-red-100 text-red-700',
    refunded: 'bg-gray-100 text-gray-500',
  }
  return 'px-2 py-0.5 rounded text-xs ' + (m[s] || 'bg-gray-100 text-gray-500')
}
const statusLabel = (s) => ({completed:'결제완료',pending:'대기중',failed:'실패',refunded:'환불됨'}[s] || s)

function invoice(p) {
  // 간이 인보이스 HTML 팝업 (PDF 백엔드 생성은 차후)
  const w = window.open('', '_blank', 'width=600,height=800')
  w.document.write(`
    <html><head><title>Invoice #${p.id}</title>
    <style>body{font-family:sans-serif;padding:40px;color:#333}h1{color:#f59e0b}table{width:100%;border-collapse:collapse;margin-top:20px}td,th{border-bottom:1px solid #eee;padding:8px;text-align:left}.total{font-size:24px;font-weight:bold;color:#f59e0b;text-align:right;margin-top:20px}</style>
    </head><body>
    <h1>SomeKorean</h1>
    <h2>영수증 #${p.id}</h2>
    <p><strong>날짜:</strong> ${fmtDate(p.created_at)}</p>
    <p><strong>상태:</strong> ${statusLabel(p.status)}</p>
    <table>
      <tr><th>항목</th><th style="text-align:right">금액</th></tr>
      <tr><td>${p.description || p.product_name || '포인트 구매'}</td><td style="text-align:right">$${Number(p.amount).toFixed(2)}</td></tr>
    </table>
    <div class="total">합계 $${Number(p.amount).toFixed(2)}</div>
    <p style="margin-top:40px;font-size:12px;color:#999">문의: admin@somekorean.com</p>
    <script>window.print()</` + `script>
    </body></html>
  `)
}

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/payments/history?per_page=50')
    items.value = data?.data?.data || data?.data || []
  } finally { loading.value = false }
})
</script>
