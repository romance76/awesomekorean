<template>
  <div class="space-y-4">
    <div class="bg-white rounded-xl shadow-sm p-5">
      <h3 class="font-bold mb-4">🛡️ 개인정보 설정</h3>
      <div class="space-y-3">
        <label class="flex items-center justify-between py-2 border-b">
          <div>
            <p class="text-sm">친구 요청 허용</p>
            <p class="text-xs text-gray-400">다른 유저가 친구 요청을 보낼 수 있는지</p>
          </div>
          <input type="checkbox" v-model="form.allow_friend_request" class="w-4 h-4 accent-amber-400" />
        </label>
        <label class="flex items-center justify-between py-2 border-b">
          <div>
            <p class="text-sm">쪽지 수신 허용</p>
            <p class="text-xs text-gray-400">친구가 아닌 유저의 쪽지 수신</p>
          </div>
          <input type="checkbox" v-model="form.allow_messages" class="w-4 h-4 accent-amber-400" />
        </label>
        <label class="flex items-center justify-between py-2 border-b">
          <div>
            <p class="text-sm">안심서비스 참여</p>
            <p class="text-xs text-gray-400">보호자·보호대상 연결 허용</p>
          </div>
          <input type="checkbox" v-model="form.allow_elder_service" class="w-4 h-4 accent-amber-400" />
        </label>
        <label class="block py-2">
          <span class="text-sm">프로필 공개 범위</span>
          <select v-model="form.profile_visibility" class="w-full border rounded px-3 py-2 mt-1 text-sm">
            <option value="public">전체 공개</option>
            <option value="friends">친구만</option>
            <option value="private">비공개</option>
          </select>
        </label>
        <div class="pt-3 flex justify-end">
          <button @click="save" :disabled="saving" class="px-4 py-2 bg-amber-400 hover:bg-amber-500 text-white rounded-lg text-sm font-semibold disabled:opacity-50">
            {{ saving ? '저장 중...' : '💾 저장' }}
          </button>
        </div>
      </div>
    </div>

    <!-- GDPR 데이터 내보내기 -->
    <div class="bg-white rounded-xl shadow-sm p-5">
      <h3 class="font-bold mb-2">📦 내 데이터 내보내기</h3>
      <p class="text-xs text-gray-500 mb-3">내가 제공한 모든 개인정보를 JSON 으로 다운로드 (GDPR 준수).</p>
      <button @click="exportData" class="px-3 py-1.5 bg-gray-700 hover:bg-gray-800 text-white rounded text-sm">📥 데이터 요청</button>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
const auth = useAuthStore()
const site = useSiteStore()
const saving = ref(false)
const form = reactive({
  allow_friend_request: true, allow_messages: true, allow_elder_service: false,
  profile_visibility: 'public',
})

onMounted(async () => {
  if (!auth.user?.id) await auth.fetchUser?.()
  const u = auth.user || {}
  Object.keys(form).forEach(k => { if (u[k] !== undefined && u[k] !== null) form[k] = u[k] })
})

async function save() {
  saving.value = true
  try {
    await axios.put('/api/user/profile', form)
    site.toast('설정이 저장되었습니다', 'success')
    await auth.fetchUser?.()
  } catch (e) {
    site.toast('저장 실패', 'error')
  } finally { saving.value = false }
}

function exportData() {
  const u = auth.user || {}
  const blob = new Blob([JSON.stringify(u, null, 2)], { type: 'application/json' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `my-data-${Date.now()}.json`
  a.click()
  URL.revokeObjectURL(url)
  site.toast('데이터가 다운로드되었습니다', 'success')
}
</script>
