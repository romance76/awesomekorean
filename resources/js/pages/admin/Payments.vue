<template>
<div>
  <h1 class="text-xl font-black text-gray-800 mb-4">💳 오더/결제 관리</h1>

  <!-- 필터 -->
  <div class="flex gap-2 mb-4 flex-wrap">
    <select v-model="filterStatus" @change="load" class="border rounded-lg px-3 py-1.5 text-sm">
      <option value="">전체 상태</option>
      <option value="completed">완료</option>
      <option value="pending">대기</option>
      <option value="refunded">환불됨</option>
      <option value="cancelled">취소됨</option>
    </select>
    <input v-model="searchQ" @keyup.enter="load" type="text" placeholder="이름/이메일 검색..." class="border rounded-lg px-3 py-1.5 text-sm" />
    <button @click="load" class="bg-amber-400 text-amber-900 font-bold px-4 py-1.5 rounded-lg text-sm">검색</button>
  </div>

  <!-- 통계 -->
  <div class="grid grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-xl border p-3 text-center">
      <div class="text-xs text-gray-500">총 매출</div>
      <div class="text-lg font-black text-green-600">${{ stats.totalRevenue }}</div>
    </div>
    <div class="bg-white rounded-xl border p-3 text-center">
      <div class="text-xs text-gray-500">총 주문</div>
      <div class="text-lg font-black text-blue-600">{{ stats.totalOrders }}건</div>
    </div>
    <div class="bg-white rounded-xl border p-3 text-center">
      <div class="text-xs text-gray-500">환불</div>
      <div class="text-lg font-black text-red-600">{{ stats.totalRefunds }}건</div>
    </div>
    <div class="bg-white rounded-xl border p-3 text-center">
      <div class="text-xs text-gray-500">이번 달</div>
      <div class="text-lg font-black text-amber-600">${{ stats.monthRevenue }}</div>
    </div>
  </div>

  <div v-if="loading" class="text-center py-8 text-gray-400">로딩중...</div>
  <div v-else-if="!items.length" class="text-center py-8 text-gray-400">결제 내역 없음</div>
  <div v-else class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 border-b"><tr>
        <th class="px-3 py-2 text-left text-xs text-gray-500">주문번호</th>
        <th class="px-3 py-2 text-left text-xs text-gray-500">유저</th>
        <th class="px-3 py-2 text-left text-xs text-gray-500">금액</th>
        <th class="px-3 py-2 text-left text-xs text-gray-500">포인트</th>
        <th class="px-3 py-2 text-left text-xs text-gray-500">상태</th>
        <th class="px-3 py-2 text-left text-xs text-gray-500">날짜</th>
        <th class="px-3 py-2 text-left text-xs text-gray-500">관리</th>
      </tr></thead>
      <tbody>
        <tr v-for="item in items" :key="item.id" class="border-b last:border-0 hover:bg-amber-50/30">
          <td class="px-3 py-2.5 font-mono text-xs text-gray-600">#{{ item.id }}</td>
          <td class="px-3 py-2.5">
            <div class="text-sm text-gray-800">{{ item.user?.name || '-' }}</div>
            <div class="text-[10px] text-gray-400">{{ item.user?.email }}</div>
          </td>
          <td class="px-3 py-2.5 text-amber-600 font-bold">${{ Number(item.amount).toFixed(2) }}</td>
          <td class="px-3 py-2.5 font-bold text-blue-600">{{ item.points_purchased?.toLocaleString() }}P</td>
          <td class="px-3 py-2.5">
            <span class="text-xs px-2 py-0.5 rounded-full font-bold" :class="{
              'bg-green-100 text-green-700': item.status==='completed',
              'bg-amber-100 text-amber-700': item.status==='pending',
              'bg-red-100 text-red-700': item.status==='refunded',
              'bg-gray-200 text-gray-500': item.status==='cancelled'
            }">{{ statusLabel(item.status) }}</span>
          </td>
          <td class="px-3 py-2.5 text-xs text-gray-400">{{ formatDate(item.created_at) }}</td>
          <td class="px-3 py-2.5">
            <div class="flex gap-1">
              <button @click="showDetail(item)" class="text-xs text-blue-600 hover:underline">상세</button>
              <button v-if="item.status==='completed'" @click="refundOrder(item)" class="text-xs text-red-600 hover:underline">환불</button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- 상세/인보이스 모달 -->
  <div v-if="detailItem" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="detailItem=null">
    <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden">
      <!-- 인보이스 헤더 -->
      <div class="bg-gradient-to-r from-amber-400 to-amber-500 px-6 py-4 text-white">
        <div class="flex justify-between items-start">
          <div>
            <div class="text-xs opacity-80">INVOICE</div>
            <div class="text-2xl font-black">#{{ detailItem.id }}</div>
          </div>
          <div class="text-right">
            <div class="font-bold">AwesomeKorean</div>
            <div class="text-xs opacity-80">awesomekorean.com</div>
          </div>
        </div>
      </div>

      <div class="px-6 py-4 space-y-4">
        <!-- 구매자 정보 -->
        <div class="flex justify-between">
          <div>
            <div class="text-xs text-gray-500">구매자</div>
            <div class="font-bold text-sm">{{ detailItem.user?.name }}</div>
            <div class="text-xs text-gray-400">{{ detailItem.user?.email }}</div>
          </div>
          <div class="text-right">
            <div class="text-xs text-gray-500">주문일</div>
            <div class="text-sm">{{ formatDate(detailItem.created_at) }}</div>
          </div>
        </div>

        <!-- 주문 내용 -->
        <table class="w-full text-sm border-t">
          <thead><tr class="border-b bg-gray-50">
            <th class="py-2 px-3 text-left text-xs">상품</th>
            <th class="py-2 px-3 text-right text-xs">포인트</th>
            <th class="py-2 px-3 text-right text-xs">금액</th>
          </tr></thead>
          <tbody>
            <tr class="border-b">
              <td class="py-2 px-3">포인트 구매</td>
              <td class="py-2 px-3 text-right font-bold text-blue-600">{{ detailItem.points_purchased?.toLocaleString() }}P</td>
              <td class="py-2 px-3 text-right font-bold">${{ Number(detailItem.amount).toFixed(2) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="font-bold">
              <td class="py-2 px-3">합계</td>
              <td class="py-2 px-3 text-right text-blue-600">{{ detailItem.points_purchased?.toLocaleString() }}P</td>
              <td class="py-2 px-3 text-right text-amber-600">${{ Number(detailItem.amount).toFixed(2) }}</td>
            </tr>
          </tfoot>
        </table>

        <!-- 상태 -->
        <div class="flex items-center justify-between bg-gray-50 rounded-lg px-4 py-2">
          <span class="text-xs text-gray-500">결제 상태</span>
          <span class="text-xs px-2 py-0.5 rounded-full font-bold" :class="{
            'bg-green-100 text-green-700': detailItem.status==='completed',
            'bg-red-100 text-red-700': detailItem.status==='refunded',
            'bg-gray-200 text-gray-500': detailItem.status==='cancelled'
          }">{{ statusLabel(detailItem.status) }}</span>
        </div>

        <div v-if="detailItem.stripe_payment_id" class="text-xs text-gray-400">
          Stripe ID: {{ detailItem.stripe_payment_id }}
        </div>
      </div>

      <!-- 액션 -->
      <div class="px-6 py-3 border-t flex gap-2 justify-end">
        <button @click="printInvoice" class="text-xs bg-gray-100 text-gray-700 font-bold px-4 py-2 rounded-lg hover:bg-gray-200">🖨️ 인쇄</button>
        <button v-if="detailItem.status==='completed'" @click="refundOrder(detailItem)" class="text-xs bg-red-500 text-white font-bold px-4 py-2 rounded-lg hover:bg-red-600">💰 환불</button>
        <button @click="detailItem=null" class="text-xs bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg">닫기</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'

const items = ref([])
const loading = ref(true)
const filterStatus = ref('')
const searchQ = ref('')
const detailItem = ref(null)
const stats = reactive({ totalRevenue: 0, totalOrders: 0, totalRefunds: 0, monthRevenue: 0 })

function statusLabel(s) { return { completed:'완료', pending:'대기', refunded:'환불됨', cancelled:'취소됨' }[s] || s }
function formatDate(dt) { return dt ? new Date(dt).toLocaleDateString('ko-KR') + ' ' + new Date(dt).toLocaleTimeString('ko-KR', {hour:'2-digit',minute:'2-digit'}) : '' }

async function load() {
  loading.value = true
  try {
    const params = {}
    if (filterStatus.value) params.status = filterStatus.value
    if (searchQ.value) params.search = searchQ.value
    const { data } = await axios.get('/api/admin/payments', { params })
    items.value = data.data?.data || data.data || []

    // 통계 계산
    const all = items.value
    stats.totalRevenue = all.filter(i => i.status === 'completed').reduce((s, i) => s + Number(i.amount), 0).toFixed(2)
    stats.totalOrders = all.length
    stats.totalRefunds = all.filter(i => i.status === 'refunded').length
    const thisMonth = new Date().getMonth()
    stats.monthRevenue = all.filter(i => i.status === 'completed' && new Date(i.created_at).getMonth() === thisMonth).reduce((s, i) => s + Number(i.amount), 0).toFixed(2)
  } catch {}
  loading.value = false
}

function showDetail(item) { detailItem.value = item }

async function refundOrder(item) {
  if (!confirm(`주문 #${item.id} 환불하시겠습니까?\n${item.points_purchased}P가 회수되고 $${item.amount}가 환불됩니다.`)) return
  try {
    const { data } = await axios.post(`/api/admin/payments/${item.id}/refund`)
    alert(data.message || '환불 완료')
    item.status = 'refunded'
    detailItem.value = null
    load()
  } catch (e) { alert(e.response?.data?.message || '환불 실패') }
}

function printInvoice() { window.print() }

onMounted(load)
</script>
