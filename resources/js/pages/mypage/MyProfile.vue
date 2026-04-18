<template>
  <!-- /mypage/profile (Phase 2-C 묶음 3) -->
  <div class="space-y-4">
    <div class="bg-white rounded-xl shadow-sm p-5">
      <h3 class="font-bold text-lg mb-4">👤 프로필</h3>

      <!-- 아바타 -->
      <div class="flex items-center gap-4 mb-5 pb-4 border-b">
        <img :src="form.avatar || '/images/default-avatar.png'" @error="$event.target.src='/images/default-avatar.png'" class="w-20 h-20 rounded-full border object-cover bg-gray-100" />
        <div>
          <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="uploadAvatar" />
          <button @click="$refs.fileInput.click()" class="px-3 py-1.5 bg-amber-400 hover:bg-amber-500 text-white rounded text-sm font-semibold">사진 변경</button>
          <p class="text-xs text-gray-500 mt-1">최대 10MB · JPG/PNG</p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <label class="block">
          <span class="text-xs text-gray-500">이름</span>
          <input v-model="form.name" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm" />
        </label>
        <label class="block">
          <span class="text-xs text-gray-500">닉네임</span>
          <input v-model="form.nickname" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm" />
        </label>
        <label class="block md:col-span-2">
          <span class="text-xs text-gray-500">소개</span>
          <textarea v-model="form.bio" rows="2" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm"></textarea>
        </label>
        <label class="block">
          <span class="text-xs text-gray-500">전화</span>
          <input v-model="form.phone" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm" />
        </label>
        <label class="block">
          <span class="text-xs text-gray-500">도시</span>
          <input v-model="form.city" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm" />
        </label>
        <label class="block">
          <span class="text-xs text-gray-500">주</span>
          <input v-model="form.state" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm" />
        </label>
        <label class="block">
          <span class="text-xs text-gray-500">우편번호</span>
          <input v-model="form.zipcode" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm" />
        </label>
        <label class="block">
          <span class="text-xs text-gray-500">기본 검색 반경 (마일)</span>
          <select v-model.number="form.default_radius" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm">
            <option :value="10">10 마일</option>
            <option :value="30">30 마일</option>
            <option :value="50">50 마일</option>
            <option :value="100">100 마일</option>
          </select>
        </label>
        <label class="block">
          <span class="text-xs text-gray-500">언어</span>
          <select v-model="form.language" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm">
            <option value="ko">한국어</option>
            <option value="en">English</option>
          </select>
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" v-model="form.allow_friend_request" />
          친구 요청 허용
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" v-model="form.allow_messages" />
          쪽지 수신 허용
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" v-model="form.allow_elder_service" />
          안심서비스 참여
        </label>
      </div>

      <div class="mt-5 pt-4 border-t flex justify-end gap-2">
        <button @click="save" :disabled="saving" class="px-4 py-2 bg-amber-400 hover:bg-amber-500 text-white rounded-lg text-sm font-semibold disabled:opacity-50">
          {{ saving ? '저장 중...' : '💾 저장' }}
        </button>
      </div>
    </div>

    <!-- 비밀번호 변경 -->
    <div class="bg-white rounded-xl shadow-sm p-5">
      <h3 class="font-bold mb-3">🔒 비밀번호 변경</h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <input v-model="pwd.current_password" type="password" placeholder="현재 비밀번호" class="border rounded px-3 py-2 text-sm" />
        <input v-model="pwd.new_password" type="password" placeholder="새 비밀번호 (8자 이상)" class="border rounded px-3 py-2 text-sm" />
        <input v-model="pwd.new_password_confirmation" type="password" placeholder="새 비밀번호 확인" class="border rounded px-3 py-2 text-sm" />
      </div>
      <button @click="changePassword" class="mt-3 px-4 py-2 bg-gray-700 hover:bg-gray-800 text-white rounded-lg text-sm font-semibold">변경</button>
    </div>

    <!-- 계정 삭제 -->
    <div class="bg-red-50 border border-red-200 rounded-xl p-5">
      <h3 class="font-bold text-red-700 mb-2">⚠️ 계정 삭제</h3>
      <p class="text-xs text-red-600 mb-3">삭제 후 복구 불가. 작성한 글·댓글·친구·포인트 모두 영구 삭제됩니다.</p>
      <button @click="deleteAccount" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-semibold">계정 삭제</button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const site = useSiteStore()
const router = useRouter()
const saving = ref(false)

const form = reactive({
  name: '', nickname: '', bio: '', phone: '', avatar: '',
  city: '', state: '', zipcode: '', default_radius: 30,
  language: 'ko',
  allow_friend_request: true, allow_messages: true, allow_elder_service: false,
})
const pwd = reactive({ current_password: '', new_password: '', new_password_confirmation: '' })

onMounted(async () => {
  await auth.fetchUser?.()
  const u = auth.user || {}
  Object.keys(form).forEach(k => { if (u[k] !== undefined && u[k] !== null) form[k] = u[k] })
})

async function save() {
  saving.value = true
  try {
    await axios.put('/api/user/profile', form)
    site.toast('프로필이 저장되었습니다', 'success')
    await auth.fetchUser?.()
  } catch (e) {
    site.toast(e.response?.data?.message || '저장 실패', 'error')
  } finally {
    saving.value = false
  }
}

async function uploadAvatar(e) {
  const file = e.target.files?.[0]
  if (!file) return
  if (file.size > 10 * 1024 * 1024) { site.toast('10MB 이하만 업로드 가능', 'error'); return }
  const fd = new FormData()
  fd.append('avatar', file)
  try {
    const { data } = await axios.post('/api/user/avatar', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    form.avatar = data.data?.avatar || data.avatar
    site.toast('프로필 사진이 변경되었습니다', 'success')
    await auth.fetchUser?.()
  } catch (er) {
    site.toast('업로드 실패', 'error')
  }
}

async function changePassword() {
  if (!pwd.current_password || !pwd.new_password) { site.toast('비밀번호를 입력하세요', 'error'); return }
  if (pwd.new_password !== pwd.new_password_confirmation) { site.toast('새 비밀번호 불일치', 'error'); return }
  try {
    await axios.post('/api/change-password', pwd)
    Object.keys(pwd).forEach(k => pwd[k] = '')
    site.toast('비밀번호가 변경되었습니다', 'success')
  } catch (e) {
    site.toast(e.response?.data?.message || '변경 실패', 'error')
  }
}

async function deleteAccount() {
  const confirmText = prompt('계정을 완전히 삭제합니다. 계속하려면 "DELETE" 를 입력하세요.')
  if (confirmText !== 'DELETE') return
  if (!confirm('정말 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.')) return
  try {
    await axios.delete('/api/user/delete')
    await auth.logout()
    router.push('/login')
  } catch (e) {
    site.toast('삭제 실패', 'error')
  }
}
</script>
