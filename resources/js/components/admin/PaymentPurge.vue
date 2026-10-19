<template>
<div v-if="isSuper" class="inline-block">
  <button type="button" @click="openIt" class="px-3 py-1.5 rounded-lg text-xs font-bold border border-gray-200 text-ink-light hover:bg-gray-50">🧹 시험 결제 정리</button>

  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[80] bg-black/45 flex items-center justify-center p-3" @click.self="close">
      <div class="bg-white rounded-2xl w-full max-w-3xl max-h-[92vh] overflow-y-auto p-5 space-y-4" role="dialog" aria-modal="true" aria-label="시험 결제 정리">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-lg font-bold text-ink">시험 결제 정리</h2>
            <p class="text-xs text-ink-muted mt-0.5">지울 결제를 고르면, 같이 사라지는 기록을 먼저 보여 드려요. 지우기 전에 백업이 서버에 저장돼요.</p>
          </div>
          <button type="button" @click="close" class="shrink-0 w-9 h-9 rounded-full bg-gray-100 text-ink font-bold" aria-label="닫기">✕</button>
        </div>

        <!-- 1단계: 고르기 -->
        <div v-if="step === 'pick'">
          <div v-if="loading" class="py-10 text-center text-sm text-ink-muted">불러오는 중...</div>
          <template v-else>
            <div class="flex items-center justify-between text-xs text-ink-muted mb-2">
              <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" :checked="allChecked" @change="toggleAll($event.target.checked)" /> 전체 선택 (지울 수 있는 것만)</label>
              <span>{{ picked.size }}건 선택</span>
            </div>
            <div class="border border-gray-100 rounded-xl divide-y divide-gray-100 max-h-[50vh] overflow-y-auto">
              <label v-for="p in rows" :key="p.id" class="flex items-center gap-3 px-3 py-2 text-sm" :class="p.status === 'authorized' ? 'opacity-60' : 'cursor-pointer hover:bg-gray-50'">
                <input type="checkbox" :disabled="p.status === 'authorized'" :checked="picked.has(p.id)" @change="toggle(p.id, $event.target.checked)" />
                <span class="w-10 text-ink-muted tabular-nums">#{{ p.id }}</span>
                <span class="w-24 font-bold tabular-nums text-ink">${{ Number(p.amount).toFixed(2) }}</span>
                <span class="w-28 text-xs text-ink-light truncate">{{ p.kind }}</span>
                <span class="w-24 text-xs">{{ statusText(p.status) }}</span>
                <span class="flex-1 min-w-0 text-xs text-ink-muted truncate">{{ p.user?.email }}</span>
                <span class="text-[11px] text-ink-faint">{{ (p.created_at || '').slice(0, 10) }}</span>
              </label>
            </div>
            <p class="text-[11px] text-ink-faint mt-2">"카드 보류(미청구)" 결제는 고를 수 없어요. 먼저 광고를 거절·취소해 보류를 푼 뒤에 지울 수 있어요.</p>
            <div class="flex justify-end gap-2 mt-3">
              <button type="button" @click="close" class="px-4 py-2 rounded-xl bg-gray-100 text-sm font-bold">닫기</button>
              <button type="button" @click="doPreview" :disabled="!picked.size || busy" class="btn-primary !px-5 !py-2 text-sm disabled:opacity-50">{{ busy ? '확인 중...' : '미리보기 (아직 지우지 않아요)' }}</button>
            </div>
          </template>
        </div>

        <!-- 2단계: 미리보기·확인 -->
        <div v-else-if="step === 'preview'" class="space-y-3">
          <div v-for="r in plan" :key="r.id" class="border rounded-xl p-3 text-sm" :class="r.blocked ? 'border-red-200 bg-red-50' : 'border-gray-100'">
            <div class="flex items-center gap-2 flex-wrap">
              <b class="text-ink">#{{ r.id }}</b>
              <span v-if="!r.missing" class="font-bold tabular-nums">${{ Number(r.amount).toFixed(2) }}</span>
              <span v-if="!r.missing" class="text-xs text-ink-light">{{ r.kind }} · {{ statusText(r.status) }}</span>
              <span v-if="r.stripe" class="text-[11px] font-mono text-ink-faint">{{ r.stripe }}</span>
            </div>
            <div v-if="r.user" class="text-xs text-ink-muted">{{ r.user }}</div>
            <ul v-if="r.links?.length" class="mt-1.5 text-xs text-ink-light space-y-0.5 list-disc pl-5">
              <li v-for="(l, i) in r.links" :key="i"><b>{{ l.type }}</b> #{{ l.id }} {{ l.label }} — <span class="text-ink-muted">{{ l.note }}</span></li>
            </ul>
            <p v-else-if="!r.missing" class="mt-1 text-xs text-ink-faint">연결된 기록 없음 — 결제만 지워요</p>
            <p v-for="(w, i) in r.warn" :key="'w' + i" class="mt-1 text-xs text-amber-700">⚠ {{ w }}</p>
            <p v-if="r.blocked" class="mt-1 text-xs font-bold text-red-600">🚫 {{ r.blocked }}</p>
          </div>

          <label class="flex items-start gap-2 text-sm text-ink-light cursor-pointer">
            <input type="checkbox" v-model="deleteLinked" class="mt-0.5" />
            <span>연결된 전단·배너 광고, 공동구매, 광고 잔액 기록도 <b>같이 삭제</b>하고 상위노출 표시를 풀어요 <span class="text-ink-faint">(끄면 결제 번호만 떼어 내고 글은 그대로 둬요)</span></span>
          </label>

          <div v-if="!blockedCount" class="bg-gray-50 rounded-xl p-3 space-y-2">
            <p class="text-xs text-ink-muted">되돌릴 수 없는 작업이에요. 지우기 전에 서버에 백업 파일이 저장돼요. Stripe 쪽 결제와 올려 둔 사진 파일은 그대로 남아요.</p>
            <label class="block text-xs font-bold text-ink-muted">확인: 아래 칸에 <b class="text-red-600">삭제</b> 라고 입력하세요</label>
            <input v-model="confirmText" type="text" class="input-soft text-sm" placeholder="삭제" autocomplete="off" />
          </div>
          <p v-else class="text-sm font-bold text-red-600">막힌 결제가 있어요. 뒤로 가서 그 결제를 빼거나, 안내대로 먼저 처리해 주세요.</p>

          <div class="flex justify-between gap-2">
            <button type="button" @click="step = 'pick'" :disabled="busy" class="px-4 py-2 rounded-xl bg-gray-100 text-sm font-bold">뒤로</button>
            <button type="button" @click="doPurge" :disabled="busy || blockedCount > 0 || confirmText.trim() !== '삭제'" class="px-5 py-2 rounded-xl bg-red-500 text-white text-sm font-bold disabled:opacity-40">{{ busy ? '지우는 중...' : plan.length + '건 삭제하기' }}</button>
          </div>
        </div>

        <!-- 3단계: 결과 -->
        <div v-else-if="step === 'done'" class="space-y-3">
          <p class="text-sm font-bold text-green-700">{{ result.message }}</p>
          <ul class="text-xs text-ink-light list-disc pl-5 space-y-0.5">
            <li v-if="result.data?.invoices">인보이스 {{ result.data.invoices }}장 삭제</li>
            <li v-if="result.data?.flyers">전단 광고 {{ result.data.flyers }}건 · 배너 {{ result.data.banners }}건 · 공동구매 {{ result.data.group_buys }}건 정리</li>
            <li class="break-all">백업 파일(서버): <span class="font-mono">{{ result.data?.backup }}</span></li>
          </ul>
          <div class="flex justify-end"><button type="button" @click="close" class="btn-primary !px-5 !py-2 text-sm">닫기</button></div>
        </div>

        <p v-if="err" class="text-sm text-red-600 whitespace-pre-line">{{ err }}</p>
      </div>
    </div>
  </Teleport>
</div>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../../stores/auth'

// 시험 결제 정리 — 최고관리자 전용(서버도 같은 검사). 고르기 → 미리보기 → "삭제" 입력 → 삭제(재확인 비밀번호 창이 뜰 수 있어요)
const emit = defineEmits(['done'])
const auth = useAuthStore()
const isSuper = computed(() => auth.user?.role === 'super_admin')

const open = ref(false)
const step = ref('pick')          // pick | preview | done
const loading = ref(false)
const busy = ref(false)
const err = ref('')
const rows = ref([])
const picked = ref(new Set())
const plan = ref([])
const deleteLinked = ref(true)
const confirmText = ref('')
const result = ref({})

const STATUS = { completed: '완료', pending: '대기', refunded: '환불됨', cancelled: '취소됨', authorized: '카드 보류(미청구)', captured: '청구됨', released: '보류 해제' }
const statusText = s => STATUS[s] || s
const selectable = computed(() => rows.value.filter(p => p.status !== 'authorized'))
const allChecked = computed(() => selectable.value.length > 0 && selectable.value.every(p => picked.value.has(p.id)))
const blockedCount = computed(() => plan.value.filter(r => r.blocked).length)

function toggle(id, on) { const n = new Set(picked.value); on ? n.add(id) : n.delete(id); picked.value = n }
function toggleAll(on) { picked.value = on ? new Set(selectable.value.map(p => p.id)) : new Set() }

async function openIt() {
  open.value = true; step.value = 'pick'; err.value = ''; picked.value = new Set(); confirmText.value = ''; deleteLinked.value = true
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/payments', { params: { per_page: 100 } })
    rows.value = data.data?.data || []
  } catch { err.value = '결제 목록을 불러오지 못했어요.' }
  loading.value = false
}
function close() { if (!busy.value) open.value = false }

async function doPreview() {
  busy.value = true; err.value = ''
  try {
    const { data } = await axios.post('/api/admin/payments/purge/preview', { ids: [...picked.value] })
    plan.value = data.data; step.value = 'preview'; confirmText.value = ''
  } catch (e) { err.value = e.response?.data?.message || '미리보기를 만들지 못했어요.' }
  busy.value = false
}

async function doPurge() {
  busy.value = true; err.value = ''
  try {
    const { data } = await axios.post('/api/admin/payments/purge', { ids: plan.value.map(r => r.id), confirm: confirmText.value.trim(), delete_linked: deleteLinked.value })
    result.value = data; step.value = 'done'; emit('done')
  } catch (e) {
    if (e.response?.status !== 428) err.value = e.response?.data?.message || '삭제하지 못했어요. 아무것도 지워지지 않았어요.'
  }
  busy.value = false
}
</script>
