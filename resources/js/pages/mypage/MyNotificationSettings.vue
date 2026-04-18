<template>
  <div class="bg-white rounded-xl shadow-sm p-5">
    <h3 class="font-bold mb-4">🔔 알림 설정</h3>
    <div class="space-y-3">
      <template v-for="group in groups" :key="group.label">
        <p class="text-xs text-gray-500 font-semibold mt-2">{{ group.label }}</p>
        <label v-for="item in group.items" :key="item.key" class="flex items-center justify-between py-2 border-b last:border-0">
          <div class="flex-1">
            <p class="text-sm">{{ item.label }}</p>
            <p class="text-xs text-gray-400">{{ item.description }}</p>
          </div>
          <input type="checkbox" v-model="prefs[item.key]" class="w-4 h-4 accent-amber-400" />
        </label>
      </template>
      <div class="pt-3 flex justify-end">
        <button @click="save" :disabled="saving" class="px-4 py-2 bg-amber-400 hover:bg-amber-500 text-white rounded-lg text-sm font-semibold disabled:opacity-50">
          {{ saving ? '저장 중...' : '💾 저장' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue'
import axios from 'axios'
import { useSiteStore } from '../../stores/site'
const site = useSiteStore()
const saving = ref(false)
const prefs = reactive({
  notify_like: true, notify_comment: true, notify_friend_request: true, notify_message: true,
  notify_event: true, notify_sos: true,
  email_digest: false, email_marketing: false,
  push_enabled: true, push_quiet_night: false,
})
const groups = [
  { label: '활동 알림', items: [
    { key: 'notify_like', label: '좋아요', description: '내 글/댓글에 좋아요' },
    { key: 'notify_comment', label: '댓글', description: '내 글에 새 댓글' },
    { key: 'notify_friend_request', label: '친구 요청', description: '새 친구 요청' },
    { key: 'notify_message', label: '쪽지', description: '새 쪽지 수신' },
    { key: 'notify_event', label: '이벤트', description: '관심 이벤트 업데이트' },
    { key: 'notify_sos', label: '안심 SOS', description: '보호대상 SOS 긴급 알림 (권장)' },
  ]},
  { label: '이메일', items: [
    { key: 'email_digest', label: '주간 요약', description: '매주 월요일 활동 요약' },
    { key: 'email_marketing', label: '마케팅', description: '이벤트·혜택 안내' },
  ]},
  { label: '푸시', items: [
    { key: 'push_enabled', label: '푸시 알림', description: '브라우저·앱 푸시 (Firebase 활성 시)' },
    { key: 'push_quiet_night', label: '방해 금지 (22:00~08:00)', description: '야간 푸시 억제' },
  ]},
]

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/user/notification-preferences').catch(() => ({ data: null }))
    const p = data?.data
    if (p) Object.keys(prefs).forEach(k => { if (p[k] !== undefined) prefs[k] = !!p[k] })
  } catch {}
})

async function save() {
  saving.value = true
  try {
    await axios.put('/api/user/notification-preferences', prefs).catch(() => axios.put('/api/user/profile', prefs))
    site.toast('알림 설정이 저장되었습니다', 'success')
  } catch (e) {
    site.toast('저장 실패 (API 미구현 가능)', 'error')
  } finally { saving.value = false }
}
</script>
