<template>
  <div class="card p-6">
    <h2 class="text-base font-semibold text-ink mb-6">회사 정보</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <div>
        <label class="input-label">사이트명</label>
        <input v-model="company.site_name" type="text" class="input-field" placeholder="AwesomeKorean" />
      </div>
      <div>
        <label class="input-label">사이트 부제목</label>
        <input v-model="company.site_subtitle" type="text" class="input-field" placeholder="미국 한인 커뮤니티" />
      </div>
      <div>
        <label class="input-label">회사명</label>
        <input v-model="company.company_name" type="text" class="input-field" placeholder="AwesomeKorean Inc." />
      </div>
      <div>
        <label class="input-label">대표자명</label>
        <input v-model="company.ceo_name" type="text" class="input-field" placeholder="홍길동" />
      </div>
      <div>
        <label class="input-label">사업자등록번호</label>
        <input v-model="company.business_number" type="text" class="input-field" placeholder="123-45-67890" />
      </div>
      <div>
        <label class="input-label">주소</label>
        <input v-model="company.address" type="text" class="input-field" placeholder="Los Angeles, CA, USA" />
      </div>
      <div>
        <label class="input-label">전화번호</label>
        <input v-model="company.phone" type="text" class="input-field" placeholder="+1-213-000-0000" />
      </div>
      <div>
        <label class="input-label">이메일</label>
        <input v-model="company.email" type="email" class="input-field" placeholder="admin@awesomekorean.com" />
      </div>
      <div>
        <label class="input-label">설립일</label>
        <input v-model="company.founded_date" type="date" class="input-field" />
      </div>
      <div>
        <label class="input-label">메타 키워드</label>
        <input v-model="company.meta_keywords" type="text" class="input-field" placeholder="한인, 커뮤니티, 미국, LA" />
      </div>
    </div>

    <!-- 로고 업로드 — 올리면 네비게이션 바, 로그인/가입 화면, 이메일 템플릿 등
         로고가 들어가는 모든 곳에 바로 반영됨 (public/images/logo.png 자체를 교체) -->
    <div class="mt-5">
      <label class="input-label">사이트 로고</label>
      <div class="flex items-center gap-3">
        <div class="w-16 h-16 border border-gray-100 rounded-xl overflow-hidden flex items-center justify-center bg-gray-50 flex-shrink-0">
          <img v-if="company.logo_url" :src="company.logo_url" alt="Logo Preview" class="w-full h-full object-contain" @error="logoError = true" />
          <span v-else class="text-xs text-ink-faint">미리보기</span>
        </div>
        <div class="flex-1">
          <input ref="logoFileInput" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="onLogoFileSelected" />
          <button type="button" @click="logoFileInput.click()" :disabled="uploadingLogo" class="btn-secondary px-4 py-2 text-sm">
            {{ uploadingLogo ? '업로드 중...' : '로고 이미지 업로드' }}
          </button>
          <p class="text-xs text-ink-faint mt-1.5">PNG/JPG/WEBP, 최대 4MB. 업로드하면 즉시 전체 화면에 반영됩니다.</p>
        </div>
      </div>
    </div>

    <!-- 다크 배경용 로고 업로드 — 푸터처럼 어두운 배경에서 기본 로고가
         안 보일 때를 대비한 흰색/밝은 톤 버전. 안 올리면 텍스트로 폴백. -->
    <div class="mt-5">
      <label class="input-label">로고 (다크 배경용, 흰색 버전)</label>
      <div class="flex items-center gap-3">
        <div class="w-16 h-16 rounded-xl overflow-hidden flex items-center justify-center bg-slate-800 flex-shrink-0">
          <img v-if="company.logo_dark_url" :src="company.logo_dark_url" alt="Dark Logo Preview" class="w-full h-full object-contain" />
          <span v-else class="text-xs text-gray-400">미리보기</span>
        </div>
        <div class="flex-1">
          <input ref="logoDarkFileInput" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="onLogoDarkFileSelected" />
          <button type="button" @click="logoDarkFileInput.click()" :disabled="uploadingLogoDark" class="btn-secondary px-4 py-2 text-sm">
            {{ uploadingLogoDark ? '업로드 중...' : '다크 배경용 로고 업로드' }}
          </button>
          <p class="text-xs text-ink-faint mt-1.5">PNG/JPG/WEBP, 최대 4MB. 푸터 등 어두운 배경 섹션에 반영됩니다 (안 올리면 텍스트로 대체 표시).</p>
        </div>
      </div>
    </div>

    <!-- 앱 아이콘 업로드 — 핸드폰 홈 화면에 "바로가기"로 추가할 때 쓰이는
         정사각형 아이콘. 로고(가로형 워드마크)와는 별개로 관리. -->
    <div class="mt-5">
      <label class="input-label">앱 아이콘 (홈 화면 바로가기용)</label>
      <div class="flex items-center gap-3">
        <div class="w-16 h-16 border border-gray-100 rounded-xl overflow-hidden flex items-center justify-center bg-gray-50 flex-shrink-0">
          <img v-if="company.app_icon_url" :src="company.app_icon_url" alt="App Icon Preview" class="w-full h-full object-contain" />
          <span v-else class="text-xs text-ink-faint">미리보기</span>
        </div>
        <div class="flex-1">
          <input ref="appIconFileInput" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="onAppIconFileSelected" />
          <button type="button" @click="appIconFileInput.click()" :disabled="uploadingAppIcon" class="btn-secondary px-4 py-2 text-sm">
            {{ uploadingAppIcon ? '업로드 중...' : '앱 아이콘 업로드' }}
          </button>
          <p class="text-xs text-ink-faint mt-1.5">정사각형 이미지 권장 (PNG/JPG/WEBP, 최대 4MB). iOS/안드로이드 홈 화면 바로가기 아이콘에 반영됩니다.</p>
        </div>
      </div>
    </div>

    <!-- 파비콘 업로드 — 브라우저 탭에 표시되는 작은 아이콘 -->
    <div class="mt-5">
      <label class="input-label">파비콘 (브라우저 탭 아이콘)</label>
      <div class="flex items-center gap-3">
        <div class="w-16 h-16 border border-gray-100 rounded-xl overflow-hidden flex items-center justify-center bg-gray-50 flex-shrink-0">
          <img v-if="company.favicon_url" :src="company.favicon_url" alt="Favicon Preview" class="w-full h-full object-contain" />
          <span v-else class="text-xs text-ink-faint">미리보기</span>
        </div>
        <div class="flex-1">
          <input ref="faviconFileInput" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="onFaviconFileSelected" />
          <button type="button" @click="faviconFileInput.click()" :disabled="uploadingFavicon" class="btn-secondary px-4 py-2 text-sm">
            {{ uploadingFavicon ? '업로드 중...' : '파비콘 업로드' }}
          </button>
          <p class="text-xs text-ink-faint mt-1.5">정사각형 이미지 권장 (PNG/JPG/WEBP, 최대 2MB). 브라우저 탭/즐겨찾기에 반영됩니다.</p>
        </div>
      </div>
    </div>

    <!-- Meta Description -->
    <div class="mt-5">
      <label class="input-label">메타 설명 (SEO)</label>
      <textarea v-model="company.meta_description" rows="3" class="input-field resize-none" placeholder="미국 한인 커뮤니티 플랫폼..."></textarea>
    </div>

    <div class="mt-6 flex justify-end">
      <button @click="saveCompany" :disabled="saving" class="btn-primary">
        <span v-if="saving" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></span>
        저장하기
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'

// 회사 정보 탭 — 값(company)은 SiteSettings.vue 가 불러오고 저장한다. 여기서는 입력 화면과 로고·아이콘 업로드만 맡는다.
const props = defineProps({
  company: { type: Object, required: true },
  saving: { type: Boolean, default: false },
})
const emit = defineEmits(['save', 'toast'])
const company = props.company
const logoError = ref(false)
const saveCompany = () => emit('save')
const showToast = (message, type = 'success') => emit('toast', message, type)

const logoFileInput = ref(null)
const uploadingLogo = ref(false)

async function onLogoFileSelected(e) {
  const file = e.target.files?.[0]
  if (!file) return
  uploadingLogo.value = true
  try {
    const formData = new FormData()
    formData.append('logo', file)
    const { data } = await axios.post('/api/admin/settings/logo', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    company.logo_url = data.data?.url || company.logo_url
    showToast('로고가 업로드되었습니다 — 네비게이션 바, 로그인 화면, 이메일 등에 바로 반영됩니다')
  } catch (e) {
    alert(e.response?.data?.message || '로고 업로드 실패')
  } finally {
    uploadingLogo.value = false
    e.target.value = ''
  }
}

const logoDarkFileInput = ref(null)
const uploadingLogoDark = ref(false)

async function onLogoDarkFileSelected(e) {
  const file = e.target.files?.[0]
  if (!file) return
  uploadingLogoDark.value = true
  try {
    const formData = new FormData()
    formData.append('logo', file)
    const { data } = await axios.post('/api/admin/settings/logo-dark', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    company.logo_dark_url = data.data?.url || company.logo_dark_url
    showToast('다크 배경용 로고가 업로드되었습니다 — 푸터 등에 바로 반영됩니다')
  } catch (e) {
    alert(e.response?.data?.message || '다크 배경용 로고 업로드 실패')
  } finally {
    uploadingLogoDark.value = false
    e.target.value = ''
  }
}

const appIconFileInput = ref(null)
const uploadingAppIcon = ref(false)

async function onAppIconFileSelected(e) {
  const file = e.target.files?.[0]
  if (!file) return
  uploadingAppIcon.value = true
  try {
    const formData = new FormData()
    formData.append('icon', file)
    const { data } = await axios.post('/api/admin/settings/app-icon', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    company.app_icon_url = data.data?.url || company.app_icon_url
    showToast('앱 아이콘이 업로드되었습니다 — 핸드폰 홈 화면 바로가기에 반영됩니다')
  } catch (e) {
    alert(e.response?.data?.message || '앱 아이콘 업로드 실패')
  } finally {
    uploadingAppIcon.value = false
    e.target.value = ''
  }
}

const faviconFileInput = ref(null)
const uploadingFavicon = ref(false)

async function onFaviconFileSelected(e) {
  const file = e.target.files?.[0]
  if (!file) return
  uploadingFavicon.value = true
  try {
    const formData = new FormData()
    formData.append('favicon', file)
    const { data } = await axios.post('/api/admin/settings/favicon', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    company.favicon_url = data.data?.url || company.favicon_url
    showToast('파비콘이 업로드되었습니다 — 브라우저 탭에 바로 반영됩니다')
  } catch (e) {
    alert(e.response?.data?.message || '파비콘 업로드 실패')
  } finally {
    uploadingFavicon.value = false
    e.target.value = ''
  }
}
</script>

<style scoped>
/* 사이트 설정 화면과 동일한 소프트 입력 스타일 (SiteSettings.vue 와 같은 값) */
.input-field {
  @apply w-full bg-[#F4F6F8] border-[1.5px] border-transparent rounded-xl px-3 py-2 text-sm text-ink placeholder:text-ink-faint outline-none transition-all duration-150 focus:bg-white focus:border-amber-400 focus:ring-4 focus:ring-amber-400/10;
}
</style>
