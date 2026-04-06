<template>
<div>
  <h1 class="text-xl font-black text-gray-800 mb-5">⚙️ 사이트 설정</h1>

  <!-- 탭 -->
  <div class="flex gap-1 mb-4 border-b">
    <button v-for="tab in tabs" :key="tab.key" @click="activeTab=tab.key"
      class="px-4 py-2 text-sm font-bold border-b-2 transition"
      :class="activeTab===tab.key ? 'border-amber-500 text-amber-700' : 'border-transparent text-gray-400 hover:text-gray-600'">{{ tab.label }}</button>
  </div>

  <!-- 사이트 기본 정보 -->
  <div v-if="activeTab==='general'" class="space-y-4">
    <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
      <h3 class="font-bold text-gray-800 text-sm border-b pb-2">🏢 사이트 정보</h3>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="text-xs font-semibold text-gray-600">사이트 이름</label><input v-model="form.site_name" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm outline-none focus:ring-2 focus:ring-amber-400" /></div>
        <div><label class="text-xs font-semibold text-gray-600">사이트 설명</label><input v-model="form.site_description" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm outline-none focus:ring-2 focus:ring-amber-400" /></div>
        <div><label class="text-xs font-semibold text-gray-600">연락처 이메일</label><input v-model="form.contact_email" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm outline-none focus:ring-2 focus:ring-amber-400" /></div>
        <div><label class="text-xs font-semibold text-gray-600">대표 전화</label><input v-model="form.contact_phone" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm outline-none focus:ring-2 focus:ring-amber-400" /></div>
      </div>
      <div><label class="text-xs font-semibold text-gray-600">회사 주소</label><input v-model="form.company_address" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm outline-none focus:ring-2 focus:ring-amber-400" /></div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
      <h3 class="font-bold text-gray-800 text-sm border-b pb-2">📋 게시판 관리</h3>
      <div class="text-xs text-gray-500 mb-2">게시판을 추가/삭제/관리할 수 있습니다</div>
      <RouterLink to="/admin/boards" class="inline-block bg-amber-100 text-amber-700 font-bold px-4 py-2 rounded-lg text-sm hover:bg-amber-200">📋 게시판 관리 →</RouterLink>
    </div>
  </div>

  <!-- API 키 / RSS 설정 -->
  <div v-if="activeTab==='api'" class="space-y-4">
    <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
      <h3 class="font-bold text-gray-800 text-sm border-b pb-2">🔑 API 키 관리</h3>
      <div v-for="api in apiKeys" :key="api.key" class="flex items-center gap-3">
        <label class="text-xs font-semibold text-gray-600 w-40">{{ api.label }}</label>
        <input v-model="form[api.key]" :type="api.show ? 'text' : 'password'" class="flex-1 border rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-amber-400 font-mono" />
        <button @click="api.show=!api.show" class="text-xs text-gray-400">{{ api.show ? '숨기기' : '보기' }}</button>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
      <h3 class="font-bold text-gray-800 text-sm border-b pb-2">📡 RSS 뉴스 피드</h3>
      <div class="text-xs text-gray-500">뉴스 RSS 피드는 하루 3회 자동 수집됩니다 (06:00/12:00/18:00)</div>
      <div class="text-xs text-gray-500">숏츠는 매일 03:00에 YouTube API로 수집됩니다</div>
      <div class="grid grid-cols-2 gap-2 mt-2 text-xs">
        <div class="bg-gray-50 rounded-lg p-2">📰 뉴스 소스: USCIS, 한겨레, 연합뉴스 등</div>
        <div class="bg-gray-50 rounded-lg p-2">📱 숏츠: YouTube API (한인 관련 22개 검색어)</div>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
      <h3 class="font-bold text-gray-800 text-sm border-b pb-2">💳 Stripe 결제</h3>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="text-xs font-semibold text-gray-600">Stripe Public Key</label><input v-model="form.stripe_key" type="password" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm font-mono outline-none focus:ring-2 focus:ring-amber-400" /></div>
        <div><label class="text-xs font-semibold text-gray-600">Stripe Secret Key</label><input v-model="form.stripe_secret" type="password" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm font-mono outline-none focus:ring-2 focus:ring-amber-400" /></div>
      </div>
    </div>
  </div>

  <!-- 페이지 관리 (어바웃/회사소개 등) -->
  <div v-if="activeTab==='pages'" class="space-y-4">
    <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
      <h3 class="font-bold text-gray-800 text-sm border-b pb-2">📄 회사 소개 (About)</h3>
      <textarea v-model="form.about_page" rows="8" class="w-full border rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-amber-400 resize-none" placeholder="회사 소개 내용을 입력하세요..."></textarea>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
      <h3 class="font-bold text-gray-800 text-sm border-b pb-2">📜 이용약관</h3>
      <textarea v-model="form.terms_page" rows="6" class="w-full border rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-amber-400 resize-none" placeholder="이용약관 내용..."></textarea>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
      <h3 class="font-bold text-gray-800 text-sm border-b pb-2">🔒 개인정보 처리방침</h3>
      <textarea v-model="form.privacy_page" rows="6" class="w-full border rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-amber-400 resize-none" placeholder="개인정보 처리방침..."></textarea>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
      <h3 class="font-bold text-gray-800 text-sm border-b pb-2">🦶 푸터 내용</h3>
      <textarea v-model="form.footer_text" rows="3" class="w-full border rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-amber-400 resize-none" placeholder="© 2026 SomeKorean. All rights reserved."></textarea>
    </div>
  </div>

  <!-- 디자인 -->
  <div v-if="activeTab==='design'" class="space-y-4">
    <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
      <h3 class="font-bold text-gray-800 text-sm border-b pb-2">🎨 디자인 설정</h3>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="text-xs font-semibold text-gray-600">메인 색상</label><input v-model="form.primary_color" type="color" class="w-full h-10 border rounded-lg mt-1" /></div>
        <div><label class="text-xs font-semibold text-gray-600">로고 URL</label><input v-model="form.logo_url" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm outline-none focus:ring-2 focus:ring-amber-400" /></div>
      </div>
    </div>
  </div>

  <!-- 저장 버튼 -->
  <div class="mt-4 flex items-center gap-3">
    <button @click="save" class="bg-amber-400 text-amber-900 font-bold px-8 py-2.5 rounded-lg text-sm hover:bg-amber-500">💾 설정 저장</button>
    <span v-if="msg" class="text-green-600 text-sm font-semibold">{{ msg }}</span>
  </div>
</div>
</template>
<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'

const activeTab = ref('general')
const tabs = [
  { key: 'general', label: '🏢 기본 정보' },
  { key: 'api', label: '🔑 API / RSS' },
  { key: 'pages', label: '📄 페이지 관리' },
  { key: 'design', label: '🎨 디자인' },
]

const form = ref({
  site_name: 'SomeKorean',
  site_description: '미국 한인 커뮤니티',
  contact_email: 'info@somekorean.com',
  contact_phone: '',
  company_address: '',
  primary_color: '#F59E0B',
  logo_url: '/images/logo_00.jpg',
  youtube_api_key: '',
  stripe_key: '',
  stripe_secret: '',
  about_page: '',
  terms_page: '',
  privacy_page: '',
  footer_text: '© 2026 SomeKorean. All rights reserved.',
})

const apiKeys = ref([
  { key: 'youtube_api_key', label: 'YouTube API Key', show: false },
])

const msg = ref('')

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/admin/settings')
    const s = data.data || {}
    Object.keys(form.value).forEach(k => { if (s[k] !== undefined) form.value[k] = s[k] })
  } catch {}
})

async function save() {
  try {
    await axios.put('/api/admin/settings', form.value)
    msg.value = '✅ 설정이 저장되었습니다!'
  } catch { msg.value = '❌ 저장 실패' }
  setTimeout(() => msg.value = '', 3000)
}
</script>
