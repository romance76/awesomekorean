<template>
  <!-- /admin/v2/communication/broadcast (Phase 2-C Post: 대량 알림/이메일) -->
  <div class="space-y-4">
    <h2 class="text-xl font-bold">📣 대량 알림 발송</h2>

    <div class="flex gap-1 border-b">
      <button v-for="t in tabs" :key="t.key" @click="tab = t.key"
        :class="['px-4 py-2 text-sm font-medium border-b-2', tab === t.key ? 'border-amber-400 text-amber-600' : 'border-transparent text-gray-500']">
        {{ t.label }}
      </button>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-5 space-y-3">
      <!-- 대상 -->
      <div>
        <p class="text-xs text-gray-500 font-semibold mb-2">📮 수신 대상</p>
        <div class="flex gap-2 flex-wrap">
          <label class="flex items-center gap-1.5 px-3 py-1.5 border rounded cursor-pointer" :class="audience === 'all' ? 'bg-amber-50 border-amber-400' : ''">
            <input type="radio" v-model="audience" value="all" class="hidden" />
            <span>전체 회원</span>
          </label>
          <label class="flex items-center gap-1.5 px-3 py-1.5 border rounded cursor-pointer" :class="audience === 'role' ? 'bg-amber-50 border-amber-400' : ''">
            <input type="radio" v-model="audience" value="role" class="hidden" />
            <span>역할별</span>
          </label>
          <label class="flex items-center gap-1.5 px-3 py-1.5 border rounded cursor-pointer" :class="audience === 'users' ? 'bg-amber-50 border-amber-400' : ''">
            <input type="radio" v-model="audience" value="users" class="hidden" />
            <span>지정 유저</span>
          </label>
        </div>
        <select v-if="audience === 'role'" v-model="role" class="mt-2 w-full border rounded px-3 py-2 text-sm">
          <option value="">역할 선택</option>
          <option value="super_admin">super_admin</option>
          <option value="manager">manager</option>
          <option value="moderator">moderator</option>
          <option value="user">user</option>
          <option value="business">business</option>
        </select>
        <input v-if="audience === 'users'" v-model="rawUserIds" placeholder="유저 ID 콤마 구분 (예: 10, 25, 100)" class="mt-2 w-full border rounded px-3 py-2 text-sm font-mono" />
        <button @click="preview" class="mt-2 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded text-xs">🔍 대상 수 확인</button>
        <p v-if="previewCount !== null" class="mt-2 text-sm text-amber-600 font-semibold">→ {{ previewCount.toLocaleString() }}명</p>
      </div>

      <!-- 내용 (탭별) -->
      <div v-if="tab === 'notification'" class="pt-3 border-t space-y-3">
        <label class="block">
          <span class="text-xs text-gray-500">제목</span>
          <input v-model="form.title" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
        </label>
        <label class="block">
          <span class="text-xs text-gray-500">메시지</span>
          <textarea v-model="form.message" rows="4" class="w-full border rounded px-3 py-2 mt-1 text-sm"></textarea>
        </label>
        <label class="block">
          <span class="text-xs text-gray-500">링크 (선택)</span>
          <input v-model="form.link" placeholder="/events/123" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
        </label>
        <div class="flex justify-end">
          <button @click="sendNotification" :disabled="sending" class="px-4 py-2 bg-amber-400 hover:bg-amber-500 text-white rounded-lg text-sm font-semibold disabled:opacity-50">
            {{ sending ? '전송 중...' : '🔔 알림 발송' }}
          </button>
        </div>
      </div>

      <div v-if="tab === 'email'" class="pt-3 border-t space-y-3">
        <label class="block">
          <span class="text-xs text-gray-500">이메일 제목</span>
          <input v-model="emailForm.subject" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
        </label>
        <label class="block">
          <span class="text-xs text-gray-500">본문 (텍스트)</span>
          <textarea v-model="emailForm.body" rows="8" class="w-full border rounded px-3 py-2 mt-1 text-sm font-mono"></textarea>
        </label>
        <label class="flex items-center gap-2 text-xs">
          <input type="checkbox" v-model="emailForm.only_opted_in" />
          <span>옵트인 유저만 (notification_preferences.email_digest=true)</span>
        </label>
        <div class="flex justify-end">
          <button @click="sendEmail" :disabled="sending" class="px-4 py-2 bg-amber-400 hover:bg-amber-500 text-white rounded-lg text-sm font-semibold disabled:opacity-50">
            {{ sending ? '전송 중...' : '✉️ 이메일 발송' }}
          </button>
        </div>
      </div>

      <div v-if="result" class="p-3 bg-green-50 border border-green-200 rounded text-sm">
        ✅ 발송 완료: {{ formatResult(result) }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import axios from 'axios'
import { useSiteStore } from '../../../stores/site'
const site = useSiteStore()
const tab = ref('notification')
const tabs = [{ key: 'notification', label: '🔔 in-app 알림' }, { key: 'email', label: '✉️ 이메일' }]
const audience = ref('all')
const role = ref('')
const rawUserIds = ref('')
const previewCount = ref(null)
const sending = ref(false)
const result = ref(null)

const form = reactive({ title: '', message: '', link: '' })
const emailForm = reactive({ subject: '', body: '', only_opted_in: true })

const userIds = computed(() => (rawUserIds.value || '').split(/[,\s]+/).map(s => parseInt(s, 10)).filter(n => n > 0))
const audiencePayload = computed(() => ({
  audience: audience.value,
  role: audience.value === 'role' ? role.value : null,
  user_ids: audience.value === 'users' ? userIds.value : null,
}))

async function preview() {
  try {
    const { data } = await axios.post('/api/admin/broadcast/audience-preview', audiencePayload.value)
    previewCount.value = data.count
  } catch (e) { site.toast('대상 확인 실패', 'error') }
}

async function sendNotification() {
  if (!form.title || !form.message) { site.toast('제목과 메시지 입력', 'error'); return }
  if (!confirm(`알림을 발송하시겠습니까?`)) return
  sending.value = true
  result.value = null
  try {
    const { data } = await axios.post('/api/admin/broadcast/notification', { ...audiencePayload.value, ...form })
    result.value = data
    site.toast(`${data.sent_count}명에게 발송됨`, 'success')
  } catch (e) { site.toast(e.response?.data?.message || '실패', 'error') }
  finally { sending.value = false }
}

async function sendEmail() {
  if (!emailForm.subject || !emailForm.body) { site.toast('제목과 본문 입력', 'error'); return }
  if (!confirm(`이메일을 발송하시겠습니까? (취소 불가)`)) return
  sending.value = true
  result.value = null
  try {
    const { data } = await axios.post('/api/admin/broadcast/email', { ...audiencePayload.value, ...emailForm })
    result.value = data
    site.toast(`${data.sent}명 성공 / ${data.failed}명 실패`, 'success')
  } catch (e) { site.toast(e.response?.data?.message || '실패', 'error') }
  finally { sending.value = false }
}

const formatResult = (r) => {
  if (r.sent_count !== undefined) return `알림 ${r.sent_count}명`
  if (r.sent !== undefined) return `이메일 성공 ${r.sent} / 실패 ${r.failed}`
  return JSON.stringify(r)
}
</script>
