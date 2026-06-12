<template>
<div class="min-h-screen">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="edit" :size="20" /></span>
      프로필 수정
    </h1>
    <div v-if="auth.user" class="card p-5 space-y-4">
      <!-- 프로필 사진 -->
      <div class="flex items-center gap-4">
        <div class="w-16 h-16 rounded-full bg-amber-500 text-white flex items-center justify-center text-2xl font-black overflow-hidden">
          <img v-if="auth.user.avatar_url" :src="auth.user.avatar_url" class="w-full h-full object-cover" @error="e=>e.target.style.display='none'" />
          <span v-else>{{ (auth.user.name || '?')[0] }}</span>
        </div>
        <div>
          <label class="btn-soft text-xs px-3 py-1.5 cursor-pointer">
            <AppIcon name="camera" :size="13" /> 사진 변경
            <input type="file" accept="image/*" @change="uploadAvatar" class="hidden" />
          </label>
          <div v-if="avatarMsg" class="text-xs mt-1" :class="avatarMsg.includes('성공')?'text-emerald-600':'text-red-500'">{{ avatarMsg }}</div>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="input-label">이름</label><input v-model="form.name" type="text" class="input-soft" /></div>
        <div><label class="input-label">닉네임</label><input v-model="form.nickname" type="text" class="input-soft" /></div>
      </div>
      <div><label class="input-label">소개</label><textarea v-model="form.bio" rows="3" placeholder="자기소개를 적어주세요" class="input-soft"></textarea></div>
      <div><label class="input-label">전화번호</label><input v-model="form.phone" type="text" class="input-soft" /></div>
      <div class="grid grid-cols-3 gap-3">
        <div><label class="input-label">도시</label><input v-model="form.city" type="text" class="input-soft" /></div>
        <div><label class="input-label">주</label><input v-model="form.state" type="text" maxlength="2" class="input-soft" /></div>
        <div><label class="input-label">우편번호</label><input v-model="form.zipcode" type="text" class="input-soft" /></div>
      </div>
      <div>
        <label class="input-label flex items-center gap-1"><AppIcon name="map-pin" :size="13" /> 기본 검색 반경</label>
        <select v-model="form.default_radius" class="input-soft">
          <option :value="10">10마일 이내</option>
          <option :value="30">30마일 이내</option>
          <option :value="50">50마일 이내</option>
          <option :value="100">100마일 이내</option>
        </select>
        <p class="text-xs text-ink-faint mt-1">구인구직, 중고장터, 부동산 등 위치 기반 게시판의 기본 검색 범위입니다</p>
      </div>
      <div>
        <label class="input-label">친구 요청 허용</label>
        <div class="flex gap-4 mt-1">
          <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" v-model="form.allow_friend_request" :value="true" class="text-amber-500" /><span class="text-sm text-ink-light">수락</span></label>
          <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" v-model="form.allow_friend_request" :value="false" class="text-amber-500" /><span class="text-sm text-ink-light">거절</span></label>
        </div>
      </div>
      <div>
        <label class="input-label">언어</label>
        <select v-model="form.language" class="input-soft">
          <option value="ko">한국어</option><option value="en">English</option>
        </select>
      </div>
      <div v-if="msg" class="text-sm" :class="msgType==='success'?'text-emerald-600':'text-red-500'">{{ msg }}</div>
      <button @click="save" :disabled="saving" class="btn-primary px-6">{{ saving ? '저장 중...' : '저장하기' }}</button>
    </div>

    <!-- 비밀번호 변경 -->
    <h2 class="flex items-center gap-2.5 text-lg font-bold text-ink mt-6 mb-3">
      <span class="icon-chip w-8 h-8 bg-blue-50 text-blue-600"><AppIcon name="lock" :size="17" /></span>
      비밀번호 변경
    </h2>
    <div class="card p-5 space-y-4">
      <div><label class="input-label">현재 비밀번호</label><input v-model="pwForm.current_password" type="password" class="input-soft" /></div>
      <div><label class="input-label">새 비밀번호</label><input v-model="pwForm.password" type="password" minlength="6" class="input-soft" /></div>
      <div><label class="input-label">새 비밀번호 확인</label><input v-model="pwForm.password_confirmation" type="password" class="input-soft" /></div>
      <div v-if="pwMsg" class="text-sm" :class="pwMsgType==='success'?'text-emerald-600':'text-red-500'">{{ pwMsg }}</div>
      <button @click="changePw" :disabled="pwSaving" class="btn-primary px-6">{{ pwSaving ? '변경 중...' : '비밀번호 변경' }}</button>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
const auth = useAuthStore()
const form = reactive({ name:'',nickname:'',bio:'',phone:'',city:'',state:'',zipcode:'',default_radius:30,language:'ko',allow_friend_request:true })
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
