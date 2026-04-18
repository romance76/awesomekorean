<template>
  <!-- /admin/v2/users/point-ops (Phase 2-C Post: 포인트 대량 지급/회수 + 강제 비번 리셋) -->
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">💰 유저 포인트·보안 운영</h2>
    </div>

    <!-- 탭 -->
    <div class="flex gap-1 border-b">
      <button v-for="t in tabs" :key="t.key" @click="tab = t.key"
        :class="['px-4 py-2 text-sm font-medium border-b-2', tab === t.key ? 'border-amber-400 text-amber-600' : 'border-transparent text-gray-500']">
        {{ t.label }}
      </button>
    </div>

    <!-- 1: 대량 지급 -->
    <div v-if="tab === 'grant'" class="bg-white rounded-xl shadow-sm p-5 space-y-3">
      <h3 class="font-semibold">🎁 대량 포인트 지급</h3>
      <p class="text-xs text-gray-500">최대 500명까지 동시 지급 가능. 이벤트·보상 지급에 사용.</p>

      <label class="block">
        <span class="text-xs text-gray-500">대상 유저 ID (콤마 구분)</span>
        <input v-model="grantForm.raw_ids" placeholder="예: 10, 25, 100" class="w-full border rounded px-3 py-2 mt-1 text-sm font-mono" />
      </label>
      <div class="grid grid-cols-2 gap-3">
        <label class="block">
          <span class="text-xs text-gray-500">1인당 포인트</span>
          <input v-model.number="grantForm.amount" type="number" min="1" max="100000" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
        </label>
        <label class="block">
          <span class="text-xs text-gray-500">사유</span>
          <input v-model="grantForm.reason" placeholder="예: 4월 이벤트 참여 보상" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
        </label>
      </div>
      <p v-if="grantIds.length" class="text-sm text-amber-600">
        → {{ grantIds.length }}명 × {{ grantForm.amount || 0 }}P = <strong>{{ (grantIds.length * (grantForm.amount || 0)).toLocaleString() }}P</strong> 지급 예정
      </p>
      <div class="flex justify-end">
        <button @click="doGrant" :disabled="!canGrant || processing" class="px-4 py-2 bg-amber-400 hover:bg-amber-500 text-white rounded-lg text-sm font-semibold disabled:opacity-50">
          {{ processing ? '처리 중...' : '대량 지급' }}
        </button>
      </div>
      <div v-if="grantResult" class="p-3 bg-green-50 border border-green-200 rounded text-sm">
        ✅ {{ grantResult.granted_count }}명에게 총 {{ grantResult.total_points?.toLocaleString() }}P 지급 완료
      </div>
    </div>

    <!-- 2: 단일 회수 -->
    <div v-if="tab === 'revoke'" class="bg-white rounded-xl shadow-sm p-5 space-y-3">
      <h3 class="font-semibold text-red-600">💸 포인트 회수 (Kay 요청)</h3>
      <p class="text-xs text-gray-500">오지급·부정 획득 포인트 회수. 잔액 이하로 차감됩니다.</p>

      <div class="grid grid-cols-3 gap-3">
        <label class="block">
          <span class="text-xs text-gray-500">유저 ID</span>
          <input v-model.number="revokeForm.user_id" type="number" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
        </label>
        <label class="block">
          <span class="text-xs text-gray-500">회수 포인트</span>
          <input v-model.number="revokeForm.amount" type="number" min="1" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
        </label>
        <label class="block">
          <span class="text-xs text-gray-500">사유</span>
          <input v-model="revokeForm.reason" placeholder="예: 중복 지급 회수" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
        </label>
      </div>
      <label class="flex items-center gap-2 text-xs">
        <input type="checkbox" v-model="revokeForm.force" />
        <span>잔액보다 많아도 잔액까지 전부 차감 (force)</span>
      </label>
      <div class="flex justify-end">
        <button @click="doRevoke" :disabled="processing" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-semibold disabled:opacity-50">
          {{ processing ? '처리 중...' : '회수' }}
        </button>
      </div>
      <div v-if="revokeResult" class="p-3 bg-amber-50 border border-amber-200 rounded text-sm">
        ✅ 회수 {{ revokeResult.revoked?.toLocaleString() }}P · 이전 {{ revokeResult.before?.toLocaleString() }}P → 이후 {{ revokeResult.after?.toLocaleString() }}P
      </div>
    </div>

    <!-- 3: 강제 비번 리셋 -->
    <div v-if="tab === 'pwreset'" class="bg-white rounded-xl shadow-sm p-5 space-y-3">
      <h3 class="font-semibold text-orange-600">🔐 강제 비밀번호 리셋</h3>
      <p class="text-xs text-gray-500">보안 사고 대응 시 사용. 임시 비밀번호 반환 + 유저의 모든 활성 세션 즉시 종료.</p>

      <label class="block">
        <span class="text-xs text-gray-500">유저 ID</span>
        <input v-model.number="pwForm.user_id" type="number" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
      </label>
      <div class="flex justify-end">
        <button @click="doPwReset" :disabled="processing" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-sm font-semibold disabled:opacity-50">
          {{ processing ? '처리 중...' : '강제 리셋' }}
        </button>
      </div>
      <div v-if="pwResult" class="p-4 bg-red-50 border-2 border-red-300 rounded">
        <p class="text-sm font-bold text-red-700 mb-2">⚠️ 이 비밀번호는 한 번만 표시됩니다.</p>
        <p class="text-xs text-gray-600 mb-2">안전한 경로로 유저에게 전달 후 재설정을 유도하세요.</p>
        <div class="bg-white p-3 font-mono text-lg text-center rounded border-2 border-red-200 select-all">
          {{ pwResult.temp_password }}
        </div>
        <button @click="copyPwd" class="mt-2 text-xs text-amber-600 hover:text-amber-800">📋 클립보드 복사</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import axios from 'axios'
import { useSiteStore } from '../../../stores/site'

const site = useSiteStore()
const tab = ref('grant')
const tabs = [
  { key: 'grant',   label: '🎁 대량 지급' },
  { key: 'revoke',  label: '💸 회수' },
  { key: 'pwreset', label: '🔐 비번 리셋' },
]
const processing = ref(false)

const grantForm = reactive({ raw_ids: '', amount: 100, reason: '' })
const grantIds = computed(() => {
  return (grantForm.raw_ids || '')
    .split(/[,\s]+/)
    .map(s => parseInt(s, 10))
    .filter(n => Number.isInteger(n) && n > 0)
})
const canGrant = computed(() => grantIds.value.length > 0 && grantForm.amount > 0 && grantForm.reason.trim())
const grantResult = ref(null)

const revokeForm = reactive({ user_id: null, amount: 0, reason: '', force: false })
const revokeResult = ref(null)

const pwForm = reactive({ user_id: null })
const pwResult = ref(null)

async function doGrant() {
  grantResult.value = null
  if (!confirm(`정말 ${grantIds.value.length}명에게 각 ${grantForm.amount}P 지급하시겠습니까?`)) return
  processing.value = true
  try {
    const { data } = await axios.post('/api/admin/users/bulk-grant-points', {
      user_ids: grantIds.value, amount: grantForm.amount, reason: grantForm.reason,
    })
    grantResult.value = data
    site.toast('지급 완료', 'success')
  } catch (e) {
    site.toast(e.response?.data?.message || '지급 실패', 'error')
  } finally { processing.value = false }
}

async function doRevoke() {
  if (!revokeForm.user_id || !revokeForm.amount || !revokeForm.reason) { site.toast('모든 필드 입력 필요', 'error'); return }
  if (!confirm(`유저 #${revokeForm.user_id} 에게서 ${revokeForm.amount}P 회수하시겠습니까?`)) return
  processing.value = true
  revokeResult.value = null
  try {
    const { data } = await axios.post(`/api/admin/users/${revokeForm.user_id}/revoke-points`, {
      amount: revokeForm.amount, reason: revokeForm.reason, force: revokeForm.force,
    })
    revokeResult.value = data
    site.toast('회수 완료', 'success')
  } catch (e) {
    site.toast(e.response?.data?.message || '회수 실패', 'error')
  } finally { processing.value = false }
}

async function doPwReset() {
  if (!pwForm.user_id) { site.toast('유저 ID 입력', 'error'); return }
  if (!confirm(`유저 #${pwForm.user_id} 의 비밀번호를 강제 리셋하고 모든 세션을 종료합니다. 계속?`)) return
  processing.value = true
  pwResult.value = null
  try {
    const { data } = await axios.post(`/api/admin/users/${pwForm.user_id}/force-password-reset`)
    pwResult.value = data.data
    site.toast('리셋 완료', 'success')
  } catch (e) {
    site.toast(e.response?.data?.message || '리셋 실패', 'error')
  } finally { processing.value = false }
}

function copyPwd() {
  if (!pwResult.value) return
  navigator.clipboard.writeText(pwResult.value.temp_password)
  site.toast('클립보드에 복사됨', 'success')
}
</script>
