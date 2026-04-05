<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <div class="max-w-3xl mx-auto px-4 py-6">
      <h1 class="text-2xl font-bold text-gray-900 mb-6">⚙️ 프로필 설정</h1>

      <!-- 프로필 사진 -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
        <h2 class="text-sm font-bold text-gray-800 mb-4">프로필 사진</h2>
        <div class="flex items-center gap-4">
          <div class="w-20 h-20 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-3xl text-white font-bold overflow-hidden">
            <img v-if="form.avatar" :src="form.avatar" class="w-full h-full object-cover" />
            <span v-else>{{ (form.name || '?')[0] }}</span>
          </div>
          <div>
            <input type="file" ref="avatarInput" accept="image/*" class="hidden" @change="onAvatarChange" />
            <button @click="$refsavatarInput.click()" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700 transition">사진 변경</button>
            <p class="text-xs text-gray-400 mt-1">JPG, PNG (최대 2MB)</p>
          </div>
        </div>
      </div>

      <!-- 기본 정보 -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
        <h2 class="text-sm font-bold text-gray-800 mb-4">👤 기본 정보</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs text-gray-500 mb-1">이름 <span class="text-red-500">*</span></label>
            <input v-model="form.name" type="text" maxlength="50" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">별명 (닉네임)</label>
            <input v-model="form.nickname" type="text" maxlength="50" placeholder="커뮤니티에서 표시될 별명" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none" />
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs text-gray-500 mb-1">자기소개</label>
            <textarea v-model="form.bio" rows="3" maxlength="500" placeholder="간단한 자기소개를 작성해주세요" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none resize-none"></textarea>
            <p class="text-xs text-gray-400 text-right mt-1">{{ (form.bio||'').length }}/500</p>
          </div>
        </div>
      </div>

      <!-- 연락처 -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
        <h2 class="text-sm font-bold text-gray-800 mb-4">📞 연락처</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs text-gray-500 mb-1">전화번호</label>
            <input v-model="form.phone" type="tel" placeholder="010-1234-5678" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">이메일</label>
            <input v-model="form.email" type="email" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-gray-50 text-gray-500" readonly />
            <p class="text-xs text-gray-400 mt-1">이메일은 변경할 수 없습니다</p>
          </div>
        </div>
      </div>

      <!-- 주소 -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
        <h2 class="text-sm font-bold text-gray-800 mb-4">📍 주소</h2>
        <div class="space-y-3">
          <div>
            <label class="block text-xs text-gray-500 mb-1">주소 1 (Address Line 1)</label>
            <input v-model="form.address" type="text" placeholder="123 Main St" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">주소 2 (Apt, Suite, Unit 등)</label>
            <input v-model="form.address2" type="text" placeholder="Apt 4B" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none" />
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="col-span-2 sm:col-span-1">
              <label class="block text-xs text-gray-500 mb-1">시티 (City)</label>
              <input v-model="form.city" type="text" placeholder="Los Angeles" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">스테이트</label>
              <select v-model="form.state" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                <option value="">선택</option>
                <option v-for="s in usStates" :key="s.code" :value="s.code">{{ s.code }} - {{ s.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">우편번호</label>
              <input v-model="form.zip_code" type="text" placeholder="90001" maxlength="10" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none" />
            </div>
          </div>
        </div>
      </div>

      <!-- 설정 -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
        <h2 class="text-sm font-bold text-gray-800 mb-4">🔧 설정</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs text-gray-500 mb-1">언어 (Language)</label>
            <select v-model="form.lang" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
              <option value="ko">한국어</option>
              <option value="en">English</option>
              <option value="both">한국어 + English</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">검색 범위 (거리)</label>
            <select v-model="form.default_radius" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
              <option :value="5">5 마일</option>
              <option :value="10">10 마일</option>
              <option :value="15">15 마일</option>
              <option :value="20">20 마일</option>
              <option :value="30">30 마일</option>
              <option :value="50">50 마일</option>
              <option :value="100">100 마일</option>
              <option :value="0">전국 (제한없음)</option>
            </select>
          </div>
        </div>
      </div>

      <!-- 결제 정보 -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
        <h2 class="text-sm font-bold text-gray-800 mb-4">💳 결제 정보</h2>
        <div v-if="form.payment_last4" class="flex items-center gap-3 bg-gray-50 rounded-lg px-4 py-3">
          <span class="text-lg">💳</span>
          <div>
            <p class="text-sm font-medium text-gray-800">{{ form.payment_method || 'Card' }} •••• {{ form.payment_last4 }}</p>
            <p class="text-xs text-gray-400">등록된 결제수단</p>
          </div>
        </div>
        <div v-else class="text-center py-6">
          <p class="text-sm text-gray-400 mb-2">등록된 결제수단이 없습니다</p>
          <button class="text-blue-600 text-sm font-semibold hover:underline" disabled>결제수단 등록 (준비중)</button>
        </div>
      </div>

      <!-- 비밀번호 변경 -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
        <h2 class="text-sm font-bold text-gray-800 mb-4">🔒 비밀번호 변경</h2>
        <div class="space-y-3 max-w-md">
          <div>
            <label class="block text-xs text-gray-500 mb-1">현재 비밀번호</label>
            <input v-model="pwForm.current_password" type="password" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">새 비밀번호</label>
            <input v-model="pwForm.password" type="password" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">새 비밀번호 확인</label>
            <input v-model="pwForm.password_confirmation" type="password" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none" />
          </div>
          <button @click="changePassword" :disabled="!pwForm.current_password || !pwForm.password"
            class="bg-gray-800 text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-gray-900 disabled:opacity-40 transition">
            비밀번호 변경
          </button>
        </div>
      </div>

      <!-- 저장 버튼 (고정) -->
      <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 p-4 z-40">
        <div class="max-w-3xl mx-auto flex gap-3">
          <router-link to="/profile" class="flex-1 bg-gray-100 text-gray-700 py-3 rounded-xl text-sm font-semibold text-center hover:bg-gray-200 transition">취소</router-link>
          <button @click="saveProfile" :disabled="saving"
            class="flex-1 bg-blue-600 text-white py-3 rounded-xl text-sm font-bold hover:bg-blue-700 disabled:opacity-50 transition">
            {{ saving ? '저장 중...' : '💾 저장하기' }}
          </button>
        </div>
      </div>

      <!-- 저장 성공 토스트 -->
      <div v-if="showToast" class="fixed top-20 right-4 bg-green-500 text-white px-5 py-3 rounded-xl shadow-lg text-sm font-semibold z-50 animate-bounce">
        ✅ 프로필이 저장되었습니다!
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const authStore = useAuthStore()
const saving = ref(false)
const showToast = ref(false)
const avatarInput = ref(null)

const form = ref({
  name: '', nickname: '', bio: '', phone: '', email: '',
  address: '', address2: '', city: '', state: '', zip_code: '',
  lang: 'ko', default_radius: 30, avatar: '',
  payment_method: '', payment_last4: '',
})

const pwForm = ref({ current_password: '', password: '', password_confirmation: '' })

const usStates = [
  { code: 'AL', name: 'Alabama' }, { code: 'AK', name: 'Alaska' }, { code: 'AZ', name: 'Arizona' },
  { code: 'AR', name: 'Arkansas' }, { code: 'CA', name: 'California' }, { code: 'CO', name: 'Colorado' },
  { code: 'CT', name: 'Connecticut' }, { code: 'DE', name: 'Delaware' }, { code: 'FL', name: 'Florida' },
  { code: 'GA', name: 'Georgia' }, { code: 'HI', name: 'Hawaii' }, { code: 'ID', name: 'Idaho' },
  { code: 'IL', name: 'Illinois' }, { code: 'IN', name: 'Indiana' }, { code: 'IA', name: 'Iowa' },
  { code: 'KS', name: 'Kansas' }, { code: 'KY', name: 'Kentucky' }, { code: 'LA', name: 'Louisiana' },
  { code: 'ME', name: 'Maine' }, { code: 'MD', name: 'Maryland' }, { code: 'MA', name: 'Massachusetts' },
  { code: 'MI', name: 'Michigan' }, { code: 'MN', name: 'Minnesota' }, { code: 'MS', name: 'Mississippi' },
  { code: 'MO', name: 'Missouri' }, { code: 'MT', name: 'Montana' }, { code: 'NE', name: 'Nebraska' },
  { code: 'NV', name: 'Nevada' }, { code: 'NH', name: 'New Hampshire' }, { code: 'NJ', name: 'New Jersey' },
  { code: 'NM', name: 'New Mexico' }, { code: 'NY', name: 'New York' }, { code: 'NC', name: 'North Carolina' },
  { code: 'ND', name: 'North Dakota' }, { code: 'OH', name: 'Ohio' }, { code: 'OK', name: 'Oklahoma' },
  { code: 'OR', name: 'Oregon' }, { code: 'PA', name: 'Pennsylvania' }, { code: 'RI', name: 'Rhode Island' },
  { code: 'SC', name: 'South Carolina' }, { code: 'SD', name: 'South Dakota' }, { code: 'TN', name: 'Tennessee' },
  { code: 'TX', name: 'Texas' }, { code: 'UT', name: 'Utah' }, { code: 'VT', name: 'Vermont' },
  { code: 'VA', name: 'Virginia' }, { code: 'WA', name: 'Washington' }, { code: 'WV', name: 'West Virginia' },
  { code: 'WI', name: 'Wisconsin' }, { code: 'WY', name: 'Wyoming' }, { code: 'DC', name: 'Washington DC' },
]

function getAuthHeaders() {
  const token = localStorage.getItem('sk_token')
  return token ? { Authorization: `Bearer ${token}` } : {}
}

async function loadProfile() {
  try {
    const { data } = await axios.get('/api/auth/me', { headers: getAuthHeaders() })
    const u = data.user || data
    form.value = {
      name: u.name || '', nickname: u.nickname || '', bio: u.bio || '',
      phone: u.phone || '', email: u.email || '',
      address: u.address || '', address2: u.address2 || '',
      city: u.city || '', state: u.state || '', zip_code: u.zip_code || '',
      lang: u.lang || 'ko', default_radius: u.default_radius || 30,
      avatar: u.avatar || '',
      payment_method: u.payment_method || '', payment_last4: u.payment_last4 || '',
    }
  } catch (e) {
    console.error('프로필 로딩 실패', e)
  }
}

async function saveProfile() {
  saving.value = true
  try {
    await axios.put('/api/profile', form.value, { headers: getAuthHeaders() })
    showToast.value = true
    setTimeout(() => showToast.value = false, 3000)
    authStore.fetchMe()
  } catch (e) {
    alert(e.response?.data?.message || '저장 실패')
  } finally {
    saving.value = false
  }
}

async function changePassword() {
  try {
    await axios.put('/api/profile/password', pwForm.value, { headers: getAuthHeaders() })
    alert('비밀번호가 변경되었습니다!')
    pwForm.value = { current_password: '', password: '', password_confirmation: '' }
  } catch (e) {
    alert(e.response?.data?.message || '비밀번호 변경 실패')
  }
}

async function onAvatarChange(e) {
  const file = e.target.files[0]
  if (!file) return
  if (file.size > 2 * 1024 * 1024) { alert('파일 크기는 2MB 이하여야 합니다'); return }
  const fd = new FormData()
  fd.append('avatar', file)
  try {
    const { data } = await axios.post('/api/profile/avatar', fd, {
      headers: { ...getAuthHeaders(), 'Content-Type': 'multipart/form-data' }
    })
    form.value.avatar = data.avatar_url || data.avatar || ''
    authStore.fetchMe()
  } catch (e) {
    alert('업로드 실패')
  }
}

onMounted(() => loadProfile())
</script>
