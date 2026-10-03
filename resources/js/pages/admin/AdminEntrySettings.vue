<template>
<div>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-2">
    <span class="icon-chip w-9 h-9 bg-violet-50 text-violet-600"><AppIcon name="ticket" :size="20" /></span>
    Entry 설정
  </h1>
  <p class="text-sm text-ink-muted mb-6">Sweepstakes 응모권(Entry) 설정입니다. Point와 완전히 분리된 시스템이며, 저장하면 즉시 반영됩니다.</p>

  <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
  <div v-else class="space-y-6">
    <div class="card overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-50 font-bold text-sm flex items-center gap-1.5 bg-violet-50 text-violet-800">
        <AppIcon name="ticket" :size="14" /> 기본 설정
      </div>
      <div class="divide-y divide-gray-50">
        <div v-for="item in items" :key="item.key" class="px-5 py-3 flex items-center gap-4">
          <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-ink">{{ item.label }}</div>
            <div class="text-[11px] text-ink-faint">{{ item.key }} {{ item.description ? '— ' + item.description : '' }}</div>
          </div>
          <input v-model="item.value" class="input-soft !w-40 !px-3 !py-1.5 text-sm text-right font-mono" />
        </div>
      </div>
    </div>

    <div class="flex items-center gap-3">
      <button @click="save" :disabled="saving" class="btn-primary !px-6 !py-2.5">
        {{ saving ? '저장중...' : '전체 저장' }}
      </button>
      <span v-if="msg" class="text-sm" :class="msgOk?'text-green-600':'text-red-500'">{{ msg }}</span>
    </div>

    <!-- 사용자별 Entry 조정 -->
    <div class="card p-5">
      <div class="font-bold text-sm text-ink mb-3 flex items-center gap-2">
        <span class="icon-chip w-7 h-7 bg-violet-50 text-violet-600"><AppIcon name="edit" :size="14" /></span>
        사용자 Entry 수동 조정
      </div>
      <div class="flex flex-wrap items-end gap-3">
        <div>
          <label class="text-xs text-ink-muted block mb-1">User ID</label>
          <input v-model="adjustForm.user_id" type="number" class="input-soft !w-28 !py-1.5 text-sm" />
        </div>
        <div>
          <label class="text-xs text-ink-muted block mb-1">증감 (음수 가능)</label>
          <input v-model="adjustForm.amount" type="number" class="input-soft !w-28 !py-1.5 text-sm" />
        </div>
        <div class="flex-1 min-w-[200px]">
          <label class="text-xs text-ink-muted block mb-1">사유</label>
          <input v-model="adjustForm.description" type="text" class="input-soft w-full !py-1.5 text-sm" placeholder="예: 이벤트 보상, 오지급 정정" />
        </div>
        <button @click="adjustEntries" :disabled="adjusting" class="btn-secondary !px-5 !py-2">지급/차감</button>
      </div>
      <div v-if="adjustMsg" class="text-sm mt-2" :class="adjustOk?'text-green-600':'text-red-500'">{{ adjustMsg }}</div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const loading = ref(true)
const saving = ref(false)
const msg = ref('')
const msgOk = ref(false)
const items = ref([])

const adjusting = ref(false)
const adjustMsg = ref('')
const adjustOk = ref(false)
const adjustForm = ref({ user_id: '', amount: '', description: '' })

async function load() {
  try {
    const { data } = await axios.get('/api/admin/entry-settings')
    items.value = data.data || []
  } catch {}
  loading.value = false
}

async function save() {
  saving.value = true; msg.value = ''
  try {
    await axios.post('/api/admin/entry-settings', { settings: items.value.map(i => ({ key: i.key, value: i.value })) })
    msg.value = '저장되었습니다!'; msgOk.value = true
  } catch (e) {
    msg.value = e.response?.data?.message || '저장 실패'; msgOk.value = false
  }
  saving.value = false
}

async function adjustEntries() {
  adjusting.value = true; adjustMsg.value = ''
  try {
    await axios.post('/api/admin/entries/adjust', {
      user_id: adjustForm.value.user_id,
      amount: adjustForm.value.amount,
      description: adjustForm.value.description,
    })
    adjustMsg.value = '처리되었습니다'; adjustOk.value = true
    adjustForm.value = { user_id: '', amount: '', description: '' }
  } catch (e) {
    adjustMsg.value = e.response?.data?.message || '처리 실패'; adjustOk.value = false
  }
  adjusting.value = false
}

onMounted(load)
</script>
