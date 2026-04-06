<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <h1 class="text-xl font-black text-gray-800 mb-4">✏️ 프로필 수정</h1>
    <div v-if="auth.user" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
      <!-- 프로필 사진 -->
      <div class="flex items-center gap-4">
        <div class="w-16 h-16 rounded-full bg-amber-500 text-white flex items-center justify-center text-2xl font-black overflow-hidden">
          <img v-if="auth.user.avatar_url" :src="auth.user.avatar_url" class="w-full h-full object-cover" @error="e=>e.target.style.display='none'" />
          <span v-else>{{ (auth.user.name || '?')[0] }}</span>
        </div>
        <div>
          <label class="text-xs bg-amber-100 text-amber-700 px-3 py-1.5 rounded-lg cursor-pointer hover:bg-amber-200 font-semibold">
            📷 사진 변경
            <input type="file" accept="image/*" @change="uploadAvatar" class="hidden" />
          </label>
          <div v-if="avatarMsg" class="text-xs mt-1" :class="avatarMsg.includes('성공')?'text-green-600':'text-red-500'">{{ avatarMsg }}</div>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-semibold text-gray-700">이름</label><input v-model="form.name" type="text" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
        <div><label class="text-sm font-semibold text-gray-700">닉네임</label><input v-model="form.nickname" type="text" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      </div>
      <div><label class="text-sm font-semibold text-gray-700">소개</label><textarea v-model="form.bio" rows="3" placeholder="자기소개를 적어주세요" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none resize-none"></textarea></div>
      <div><label class="text-sm font-semibold text-gray-700">전화번호</label><input v-model="form.phone" type="text" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      <div class="grid grid-cols-3 gap-3">
        <div><label class="text-sm font-semibold text-gray-700">도시</label><input v-model="form.city" type="text" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
        <div><label class="text-sm font-semibold text-gray-700">주</label><input v-model="form.state" type="text" maxlength="2" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
        <div><label class="text-sm font-semibold text-gray-700">우편번호</label><input v-model="form.zipcode" type="text" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      </div>
      <div>
        <label class="text-sm font-semibold text-gray-700">언어</label>
        <select v-model="form.language" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none">
          <option value="ko">한국어</option><option value="en">English</option>
        </select>
      </div>
      <div v-if="msg" class="text-sm" :class="msgType==='success'?'text-green-600':'text-red-500'">{{ msg }}</div>
      <button @click="save" :disabled="saving" class="bg-amber-400 text-amber-900 font-bold px-6 py-2.5 rounded-lg hover:bg-amber-500 disabled:opacity-50">{{ saving ? '저장 중...' : '저장하기' }}</button>
    </div>

    <!-- 비밀번호 변경 -->
    <h2 class="text-lg font-bold text-gray-800 mt-6 mb-3">🔒 비밀번호 변경</h2>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
      <div><label class="text-sm font-semibold text-gray-700">현재 비밀번호</label><input v-model="pwForm.current_password" type="password" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      <div><label class="text-sm font-semibold text-gray-700">새 비밀번호</label><input v-model="pwForm.password" type="password" minlength="6" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      <div><label class="text-sm font-semibold text-gray-700">새 비밀번호 확인</label><input v-model="pwForm.password_confirmation" type="password" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      <div v-if="pwMsg" class="text-sm" :class="pwMsgType==='success'?'text-green-600':'text-red-500'">{{ pwMsg }}</div>
      <button @click="changePw" :disabled="pwSaving" class="bg-gray-800 text-white font-bold px-6 py-2.5 rounded-lg hover:bg-gray-900 disabled:opacity-50">{{ pwSaving ? '변경 중...' : '비밀번호 변경' }}</button>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'
const auth = useAuthStore()
const form = reactive({ name:'',nickname:'',bio:'',phone:'',city:'',state:'',zipcode:'',language:'ko' })
const msg = ref('')
const msgType = ref('')
const saving = ref(false)
const avatarMsg = ref('')
async function uploadAvatar(e) {
  const file = e.target.files[0]; if (!file) return
  const fd = new FormData(); fd.append('avatar', file)
  try {
    await axios.post('/api/user/avatar', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    await auth.fetchUser()
    avatarMsg.value = '사진 변경 성공!'
  } catch (err) { avatarMsg.value = err.response?.data?.message || '업로드 실패' }
}

const pwForm = reactive({ current_password: '', password: '', password_confirmation: '' })
const pwMsg = ref('')
const pwMsgType = ref('')
const pwSaving = ref(false)

async function changePw() {
  if (!pwForm.current_password || !pwForm.password) { pwMsg.value = '모든 필드를 입력하세요'; pwMsgType.value = 'error'; return }
  if (pwForm.password !== pwForm.password_confirmation) { pwMsg.value = '새 비밀번호가 일치하지 않습니다'; pwMsgType.value = 'error'; return }
  pwSaving.value = true; pwMsg.value = ''
  try {
    await axios.post('/api/change-password', pwForm)
    pwMsg.value = '비밀번호가 변경되었습니다!'; pwMsgType.value = 'success'
    pwForm.current_password = ''; pwForm.password = ''; pwForm.password_confirmation = ''
  } catch (e) { pwMsg.value = e.response?.data?.message || '변경 실패'; pwMsgType.value = 'error' }
  pwSaving.value = false
}
async function save() {
  saving.value = true; msg.value = ''
  try {
    await axios.put('/api/user/profile', form)
    await auth.fetchUser()
    msg.value = '저장되었습니다!'; msgType.value = 'success'
  } catch (e) { msg.value = e.response?.data?.message || '저장 실패'; msgType.value = 'error' }
  saving.value = false
}
onMounted(() => {
  if (auth.user) {
    Object.keys(form).forEach(k => { if (auth.user[k]) form[k] = auth.user[k] })
  }
})
</script>
