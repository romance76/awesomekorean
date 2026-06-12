<template>
<div>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-2">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="coins" :size="20" /></span>
    포인트 설정
  </h1>
  <p class="text-sm text-ink-muted mb-6">모든 포인트 수치를 여기서 수정할 수 있습니다. 저장하면 즉시 반영됩니다.</p>

  <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
  <div v-else class="space-y-6">
    <div v-for="(items, cat) in grouped" :key="cat" class="card overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-50 font-bold text-sm flex items-center gap-1.5" :class="catStyles[cat]?.bg || 'bg-gray-50 text-ink'">
        <AppIcon :name="catStyles[cat]?.icon || 'list'" :size="14" /> {{ catStyles[cat]?.label || cat }}
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
const grouped = ref({})
const allItems = ref([])

const catStyles = {
  earn: { icon: 'gift', label: '포인트 적립', bg: 'bg-green-50 text-green-800' },
  spend: { icon: 'coins', label: '포인트 사용 (차감)', bg: 'bg-red-50 text-red-800' },
  image: { icon: 'image', label: '이미지 업로드', bg: 'bg-blue-50 text-blue-800' },
  spam: { icon: 'shield', label: '스팸 방지 (중고장터)', bg: 'bg-orange-50 text-orange-800' },
  auction: { icon: 'store', label: '업소록 옥션', bg: 'bg-purple-50 text-purple-800' },
  package: { icon: 'wallet', label: '구매 패키지 (가격|포인트|보너스)', bg: 'bg-amber-50 text-amber-800' },
  promotion: { icon: 'flame', label: '상위노출 슬롯/가격', bg: 'bg-indigo-50 text-indigo-800' },
}

async function load() {
  try {
    const { data } = await axios.get('/api/admin/point-settings')
    grouped.value = data.data || {}
    allItems.value = Object.values(data.data || {}).flat()
  } catch {}
  loading.value = false
}

async function save() {
  saving.value = true; msg.value = ''
  try {
    await axios.post('/api/admin/point-settings', { settings: allItems.value.map(i => ({ key: i.key, value: i.value })) })
    msg.value = '저장되었습니다!'; msgOk.value = true
  } catch (e) {
    msg.value = e.response?.data?.message || '저장 실패'; msgOk.value = false
  }
  saving.value = false
}

onMounted(load)
</script>
