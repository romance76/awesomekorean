<template>
  <div :class="isMobile ? 'ss-m' : ''">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="settings" :size="20" /></span>
        사이트 설정
      </h1>
      <span class="text-xs text-ink-faint">마지막 로드: {{ lastLoaded }}</span>
    </div>

    <!-- 지금 사이트에 실제로 적용 중인 상태 (저장된 값 기준) — 어느 탭에서나 보이고, 점검 모드는 여기서 바로 끌 수 있다 -->
    <div v-if="!loading && live.maintenance_mode" class="mb-4 rounded-xl border-2 border-red-300 bg-red-50 p-4 flex flex-wrap items-center justify-between gap-3" role="alert">
      <div class="flex items-start gap-2.5 min-w-0">
        <AppIcon name="alert-circle" :size="22" class="text-red-600 flex-shrink-0 mt-0.5" />
        <div class="min-w-0">
          <p class="text-base font-bold text-red-700">현재 점검 중입니다</p>
          <p class="text-sm text-red-700/90 leading-relaxed">관리자·운영자 외에는 사이트를 쓸 수 없어요. 관리자 화면과 로그인은 계속 열려 있어요.</p>
        </div>
      </div>
      <button type="button" @click="turnOffMaintenance" :disabled="saving" class="btn-primary px-5 py-2.5 text-sm flex-shrink-0 w-full sm:w-auto">
        {{ saving ? '처리 중...' : '점검 끄기' }}
      </button>
    </div>
    <div v-if="!loading && !live.allow_signup" class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-3.5 flex items-start gap-2.5" role="status">
      <AppIcon name="info" :size="20" class="text-amber-600 flex-shrink-0 mt-0.5" />
      <p class="text-sm text-amber-800 leading-relaxed"><b>새 회원가입을 받지 않는 중이에요.</b> 이메일·구글·아마존 신규 가입이 모두 막혀 있고, 이미 가입한 분의 로그인은 그대로예요.</p>
    </div>
    <div v-if="!loading && !live.require_email_verify" class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-3.5 flex items-start gap-2.5" role="status">
      <AppIcon name="info" :size="20" class="text-amber-600 flex-shrink-0 mt-0.5" />
      <p class="text-sm text-amber-800 leading-relaxed"><b>이메일 인증 없이도 글쓰기가 가능한 상태예요.</b> 인증 메일은 계속 보내고, 인증 보너스와 "N번째 가입" 집계는 인증을 마친 분만 세요.</p>
    </div>

    <!-- Toast -->
    <Transition name="toast">
      <div v-if="toast.show" :class="[
        'fixed top-5 right-5 z-50 flex items-center gap-3 px-5 py-3 rounded-xl shadow-lg text-white text-sm font-medium',
        toast.type === 'success' ? 'bg-green-500' : 'bg-red-500'
      ]">
        <span>{{ toast.type === 'success' ? '✓' : '✕' }}</span>
        {{ toast.message }}
      </div>
    </Transition>

    <!-- Tab Navigation -->
    <div class="flex gap-1 mb-6 bg-gray-100 p-1 rounded-xl overflow-x-auto">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        @click="activeTab = tab.key"
        :class="[
          'flex-shrink-0 px-4 py-2 rounded-lg text-sm transition-all',
          activeTab === tab.key
            ? 'bg-white text-ink font-semibold shadow-sm'
            : 'text-ink-muted hover:text-ink-light'
        ]"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center items-center py-20">
      <div class="w-8 h-8 border-4 border-amber-400 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else>
      <!-- ========================= TAB 1: 회사 정보 ========================= -->
      <div v-show="activeTab === 'company'">
        <SiteCompanyTab :company="company" :saving="saving" @save="saveCompany" @toast="showToast" />
      </div>

      <!-- ========================= TAB 2: 사이트 설정 ========================= -->
      <div v-show="activeTab === 'site'">
        <SiteBasicTab :site="site" :saving="saving" @save="saveSite" />
      </div>

      <!-- ========================= TAB: 메뉴 관리 ========================= -->
      <div v-if="activeTab === 'menus'">
        <SiteMenusTab :menu-list="menuList" :menu-saving="menuSaving" :is-mobile="isMobile" @move="moveMenu" @reset="resetMenuOrder" @save="saveMenus" />
      </div>

      <!-- ========================= TAB: 메뉴 묶음 (메뉴 트리) ========================= -->
      <div v-if="activeTab === 'menuGroups'">
        <SiteMenuGroupsTab :is-mobile="isMobile" />
      </div>

      <!-- ========================= TAB 3: 푸터 편집 ========================= -->
      <div v-show="activeTab === 'footer'">
        <SiteFooterTab :footer="footer" :company="company" :saving="saving" @save="saveFooter" />
      </div>

      <!-- ========================= TAB 4: 약관 관리 ========================= -->
      <div v-show="activeTab === 'terms'">
        <SiteTermsTab :terms-content="termsContent" :terms-last-updated="termsLastUpdated" :terms-history="termsHistory" :saving="saving" @save="saveTerms" @toast="showToast" />
      </div>

      <!-- ========================= TAB 5: 결제/알림 설정 ========================= -->
      <div v-show="activeTab === 'notifications'">

        <SitePaymentTab :payment="payment" :saving="saving" :saving-stripe="savingStripe" @save-stripe="saveStripeKeys" @save-payment="savePayment" />
        <SiteNotifyTab :notifications="notifications" :seo="seo" :saving="saving" @save-notifications="saveNotifications" @save-seo="saveSeo" @toast="showToast" />
      </div>
    </div>

    <!-- API 키 관리 -->
    <div v-if="activeTab === 'api'" class="space-y-6">
      <SiteFirebasePanel :firebase="firebase" :firebase-status="firebaseStatus" :saving-firebase="savingFirebase" @save="saveFirebase" @reload="loadFirebase" />
      <SiteApiKeysPanel />
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, inject } from 'vue'

// 관리자 휴대폰 화면이면 입력칸/버튼을 손가락에 맞게 키워서 보여 줌 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import SiteCompanyTab from '../../components/admin/siteSettings/SiteCompanyTab.vue'
import SiteFooterTab from '../../components/admin/siteSettings/SiteFooterTab.vue'
import SiteTermsTab from '../../components/admin/siteSettings/SiteTermsTab.vue'
import SiteBasicTab from '../../components/admin/siteSettings/SiteBasicTab.vue'
import SiteMenusTab from '../../components/admin/siteSettings/SiteMenusTab.vue'
import SiteMenuGroupsTab from '../../components/admin/siteSettings/SiteMenuGroupsTab.vue'
import SitePaymentTab from '../../components/admin/siteSettings/SitePaymentTab.vue'
import SiteNotifyTab from '../../components/admin/siteSettings/SiteNotifyTab.vue'
import SiteFirebasePanel from '../../components/admin/siteSettings/SiteFirebasePanel.vue'
import SiteApiKeysPanel from '../../components/admin/siteSettings/SiteApiKeysPanel.vue'
import { useSiteStore } from '../../stores/site'
import { useModal } from '../../composables/useModal'
import { DEFAULT_FOOTER, FOOTER_VERSION, readSavedFooter } from '../../utils/footerDefaults'

// ─── State ────────────────────────────────────────────────────────────────────
const siteStore = useSiteStore()
const loading = ref(true)
const saving = ref(false)
const activeTab = ref('company')
const lastLoaded = ref('—')
const savingStripe = ref(false)

const tabs = [
  { key: 'company', label: '회사 정보' },
  { key: 'site', label: '사이트 설정' },
  { key: 'menus', label: '메뉴 관리' },
  { key: 'menuGroups', label: '메뉴 묶음' },
  { key: 'footer', label: '푸터 편집' },
  { key: 'terms', label: '약관 관리' },
  { key: 'notifications', label: '결제/알림 설정' },
  { key: 'api', label: '🔑 API 키 관리' },
]

// ─── Toast ────────────────────────────────────────────────────────────────────
const toast = reactive({ show: false, message: '', type: 'success' })
let toastTimer = null

function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.message = message
  toast.type = type
  toast.show = true
  toastTimer = setTimeout(() => { toast.show = false }, 3000)
}

// ─── Form Data ────────────────────────────────────────────────────────────────
const company = reactive({
  site_name: 'AwesomeKorean',
  site_subtitle: '미국 한인 커뮤니티',
  company_name: 'AwesomeKorean Inc.',
  ceo_name: '',
  business_number: '',
  address: 'Los Angeles, CA, USA',
  phone: '',
  email: 'admin@awesomekorean.com',
  founded_date: '',
  logo_url: '',
  logo_dark_url: '',
  app_icon_url: '',
  favicon_url: '',
  meta_description: '',
  meta_keywords: '',
})

const site = reactive({
  allow_signup: true,
  require_email_verify: true,
  auto_approve: false,
  allow_withdrawal: true,
  min_password_length: 8,
  max_upload_mb: 10,
  allowed_file_types: 'jpg,png,gif,pdf,doc',
  maintenance_mode: false,
  maintenance_reason: '',
  maintenance_until: '',
})

// 지금 사이트에 실제로 적용 중인 값 (서버에 저장된 기준 — 화면에서 스위치만 바꾸고 저장 안 한 상태는 포함하지 않음)
const live = reactive({ maintenance_mode: false, allow_signup: true, require_email_verify: true })
function syncLive(src) {
  live.maintenance_mode = !!src.maintenance_mode
  live.allow_signup = src.allow_signup !== false
  live.require_email_verify = src.require_email_verify !== false
}
// 서버는 켜기/끄기를 '1'/'0' 글자로 내려준다 — 그대로 두면 '0' 이 켜짐으로 보이므로 true/false 로 바꾼다
const SITE_BOOLS = ['allow_signup', 'require_email_verify', 'auto_approve', 'allow_withdrawal', 'maintenance_mode']
function normalizeSite(src) {
  const out = { ...src }
  for (const k of SITE_BOOLS) if (k in out) out[k] = out[k] === true || out[k] === 1 || out[k] === '1' || out[k] === 'true'
  for (const k of ['min_password_length', 'max_upload_mb']) {
    if (k in out) { const n = Number(out[k]); if (out[k] === '' || out[k] == null || !Number.isFinite(n)) delete out[k]; else out[k] = n }
  }
  for (const k of ['maintenance_reason', 'maintenance_until', 'allowed_file_types']) if (k in out && out[k] == null) out[k] = ''
  return out
}
const { showConfirm } = useModal()

// 실제 사이트 푸터와 같은 기본값에서 시작 (저장된 값이 있으면 아래 applySettings 가 덮어씀)
const footer = reactive(JSON.parse(JSON.stringify(DEFAULT_FOOTER)))

const termsContent = reactive({ terms: '', privacy: '' })
const termsLastUpdated = reactive({ terms: '', privacy: '' })
const termsHistory = reactive({
  terms: [],
  privacy: [],
})

const notifications = reactive({
  announcements: [],
  email: {
    enabled: true,
    new_user: 'admin@awesomekorean.com',
    report: 'admin@awesomekorean.com',
    payment: 'billing@awesomekorean.com',
  },
  push: {
    vapid_public: '',
    vapid_private: '',
  },
})

// ─── Payment Data ─────────────────────────────────────────────────────────────
const payment = reactive({
  stripe_connected: false,
  stripe_publishable_key: '',
  stripe_secret_key: '',
  stripe_webhook_secret: '',
  stripe_test_mode: true,
  mode: 'test',
  card_enabled: true,
  apple_pay_enabled: false,
  google_pay_enabled: false,
  min_amount: 1.00,
  currency: 'USD',
  this_month_total: 2340.00,
  this_month_success_rate: 92.5,
})

// ─── SEO Data ─────────────────────────────────────────────────────────────────
const seo = reactive({
  meta_title: 'AwesomeKorean - 미국 한인 커뮤니티',
  meta_description: '',
  og_image: '',
  google_verification: '',
  naver_verification: '',
  robots_txt: 'User-agent: *\nAllow: /\nSitemap: https://awesomekorean.com/sitemap.xml',
})

// ─── Load All Settings ────────────────────────────────────────────────────────
async function loadSettings() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/settings/all')
    // axios.get()의 data는 응답 바디 전체({success, data})이고, 실제
    // 설정값은 그 안의 data 한 겹 더 안쪽에 있음 — applySettings가 한
    // 겹 덜 벗긴 객체를 받아서 data.company/data.terms 등이 전부
    // undefined였던 게 약관 관리를 포함한 모든 탭이 항상 빈 채로
    // 보이던 진짜 원인.
    applySettings(data.data)
    lastLoaded.value = new Date().toLocaleTimeString('ko-KR')
  } catch {
    // Use hardcoded defaults — already set above
    lastLoaded.value = '기본값 (서버 연결 실패)'
    // Seed sample history
    termsHistory.terms = [
      { version: 3, date: '2024-03-01', summary: '제3조 수정' },
      { version: 2, date: '2024-01-15', summary: '전면 개정' },
      { version: 1, date: '2023-06-01', summary: '최초 등록' },
    ]
    termsHistory.privacy = [
      { version: 2, date: '2024-02-20', summary: '수집 항목 추가' },
      { version: 1, date: '2023-06-01', summary: '최초 등록' },
    ]
    termsLastUpdated.terms = '2024-03-01'
    termsLastUpdated.privacy = '2024-02-20'
  } finally {
    loading.value = false
  }
}

function applySettings(data) {
  if (data.company) Object.assign(company, data.company)
  if (data.site) { Object.assign(site, normalizeSite(data.site)); syncLive(site) }
  const savedFooter = readSavedFooter(data.footer)   // 예전 방식으로 저장된 값은 무시하고 실제 푸터(기본값)에서 시작
  if (savedFooter) {
    footer.columns = savedFooter.columns
    footer.tagline = savedFooter.tagline ?? footer.tagline
    footer.copyright = savedFooter.copyright ?? footer.copyright
    if (savedFooter.sns) Object.assign(footer.sns, savedFooter.sns)
    footer.additional_text = savedFooter.additional_text ?? ''
  }
  if (data.terms) {
    termsContent.terms = data.terms.terms?.content || ''
    termsContent.privacy = data.terms.privacy?.content || ''
    termsLastUpdated.terms = data.terms.terms?.updated_at || ''
    termsLastUpdated.privacy = data.terms.privacy?.updated_at || ''
    termsHistory.terms = data.terms.terms?.history || []
    termsHistory.privacy = data.terms.privacy?.history || []
  }
  if (data.notifications) {
    if (data.notifications.announcements) notifications.announcements = data.notifications.announcements
    if (data.notifications.email) Object.assign(notifications.email, data.notifications.email)
    if (data.notifications.push) Object.assign(notifications.push, data.notifications.push)
  }
  if (data.payment) Object.assign(payment, data.payment)
  // Stripe 키 로드 (site_settings에서 직접)
  if (data.stripe_publishable_key) payment.stripe_publishable_key = data.stripe_publishable_key
  if (data.stripe_secret_key) payment.stripe_secret_key = data.stripe_secret_key
  if (data.stripe_webhook_secret) payment.stripe_webhook_secret = data.stripe_webhook_secret
  if (data.stripe_test_mode !== undefined) payment.stripe_test_mode = data.stripe_test_mode === '1' || data.stripe_test_mode === true
  if (data.seo) Object.assign(seo, data.seo)
}

// ─── Save Functions ───────────────────────────────────────────────────────────
async function saveCompany() {
  saving.value = true
  try {
    await axios.post('/api/admin/settings/company', { ...company })
    showToast('회사 정보가 저장되었습니다.')
  } catch {
    showToast('저장에 실패했습니다.', 'error')
  } finally {
    saving.value = false
  }
}

// 서버가 돌려준 검사 오류(422)는 첫 줄을 그대로 보여 준다
function serverErrorText(e, fallback) {
  const errs = e?.response?.data?.errors
  const first = errs ? Object.values(errs).flat()[0] : null
  return first || e?.response?.data?.message || fallback
}

async function saveSite() {
  // 점검 모드를 새로 켜는 저장은 한 번 더 확인 (일반 회원이 사이트를 못 쓰게 되므로)
  if (site.maintenance_mode && !live.maintenance_mode) {
    const ok = await showConfirm('점검 모드를 켜면 관리자·운영자 외에는 지금 바로 사이트를 쓸 수 없어요. 관리자 화면과 로그인은 계속 열려 있고, 이 화면에서 언제든 끌 수 있어요. 켤까요?', '점검 모드 켜기')
    if (!ok) return
  }
  saving.value = true
  try {
    const { data } = await axios.post('/api/admin/settings/site', { ...site })
    // 서버가 알려 준 "지금 적용 중인 값"으로 상태 표시를 갱신
    syncLive({ ...live, ...(data?.data || {}) })
    siteStore.refreshFlags()   // 이 화면을 쓰는 동안의 사이트 상태(가입 허용·인증 필수 등)도 새 값으로
    showToast(live.maintenance_mode ? '저장했어요. 지금 점검 중이에요.' : '사이트 설정이 저장되었습니다.')
  } catch (e) {
    showToast(serverErrorText(e, '저장에 실패했습니다.'), 'error')
  } finally {
    saving.value = false
  }
}

// 상단 빨간 안내의 "점검 끄기" — 다른 칸은 건드리지 않고 점검 모드만 끈다
async function turnOffMaintenance() {
  saving.value = true
  try {
    const { data } = await axios.post('/api/admin/settings/site', { maintenance_mode: false })
    site.maintenance_mode = false
    syncLive({ ...live, ...(data?.data || {}), maintenance_mode: false })
    siteStore.refreshFlags()
    showToast('점검을 껐어요. 회원들이 다시 사이트를 쓸 수 있어요.')
  } catch (e) {
    showToast(serverErrorText(e, '점검을 끄지 못했어요. 잠시 뒤 다시 눌러 주세요.'), 'error')
  } finally {
    saving.value = false
  }
}

async function saveFooter() {
  saving.value = true
  try {
    await axios.post('/api/admin/settings/footer', {
      version: FOOTER_VERSION,
      tagline: footer.tagline,
      columns: footer.columns,
      copyright: footer.copyright,
      sns: { ...footer.sns },
      additional_text: footer.additional_text,
    })
    showToast('푸터 설정이 저장되었습니다.')
  } catch {
    showToast('저장에 실패했습니다.', 'error')
  } finally {
    saving.value = false
  }
}

async function saveTerms(type) {
  saving.value = true
  try {
    await axios.post(`/api/admin/settings/terms/${type}`, { content: termsContent[type] })
    termsLastUpdated[type] = new Date().toLocaleDateString('ko-KR')
    showToast(type === 'terms' ? '이용약관이 저장되었습니다.' : '개인정보처리방침이 저장되었습니다.')
  } catch {
    showToast('저장에 실패했습니다.', 'error')
  } finally {
    saving.value = false
  }
}

async function saveNotifications() {
  saving.value = true
  try {
    await axios.post('/api/admin/settings/notifications', {
      announcements: notifications.announcements,
      email: { ...notifications.email },
      push: { ...notifications.push },
    })
    showToast('알림 설정이 저장되었습니다.')
  } catch {
    showToast('저장에 실패했습니다.', 'error')
  } finally {
    saving.value = false
  }
}

async function saveStripeKeys() {
  savingStripe.value = true
  try {
    await axios.post('/api/admin/settings/stripe', {
      stripe_publishable_key: payment.stripe_publishable_key,
      stripe_secret_key: payment.stripe_secret_key,
      stripe_webhook_secret: payment.stripe_webhook_secret,
      stripe_test_mode: payment.stripe_test_mode,
    })
    showToast('Stripe 키가 저장되었습니다.')
  } catch {
    showToast('Stripe 키 저장에 실패했습니다.', 'error')
  } finally {
    savingStripe.value = false
  }
}

async function savePayment() {
  saving.value = true
  try {
    await axios.post('/api/admin/settings/payment-gateway', {
      min_amount: payment.min_amount,
      currency: payment.currency,
    })
    showToast('결제 설정이 저장되었습니다.')
  } catch {
    showToast('저장에 실패했습니다.', 'error')
  } finally {
    saving.value = false
  }
}

async function saveSeo() {
  saving.value = true
  try {
    await axios.post('/api/admin/settings/seo', { ...seo })
    showToast('SEO 설정이 저장되었습니다.')
  } catch {
    showToast('저장에 실패했습니다.', 'error')
  } finally {
    saving.value = false
  }
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────
// ─── Menu Management ─────────────────────────────────────────────────────────
const menuList = ref([])
const menuSaving = ref(false)

const allMenuDefs = [
  { key: 'home',      label: '홈',       label_en: 'Home',       icon: '🏠', path: '/' },
  { key: 'community', label: '커뮤니티', label_en: 'Community',  icon: '💬', path: '/community' },
  { key: 'qa',        label: 'Q&A',      label_en: 'Q&A',        icon: '❓', path: '/qa' },
  { key: 'jobs',      label: '구인구직', label_en: 'Jobs',       icon: '💼', path: '/jobs' },
  { key: 'market',    label: '중고장터', label_en: 'Market',     icon: '🛒', path: '/market', hasCardView: true },
  { key: 'directory', label: '업소록',   label_en: 'Directory',  icon: '🏪', path: '/directory', hasCardView: true },
  { key: 'realestate',label: '부동산',   label_en: 'Real Estate',icon: '🏠', path: '/realestate', hasCardView: true },
  { key: 'events',    label: '이벤트',   label_en: 'Events',     icon: '🎉', path: '/events', hasCardView: true },
  { key: 'flyers',    label: 'NEW',      label_en: 'NEW',        icon: '🆕', path: '/new' },
  { key: 'news',      label: '뉴스',     label_en: 'News',       icon: '📰', path: '/news' },
  { key: 'info',      label: '정보',     label_en: 'Info',       icon: '📘', path: '/info' },
  { key: 'recipes',   label: '레시피',   label_en: 'Recipes',    icon: '🍳', path: '/recipes', hasCardView: true },
  { key: 'clubs',     label: '동호회',   label_en: 'Clubs',      icon: '👥', path: '/clubs' },
  { key: 'games',     label: '게임',     label_en: 'Games',      icon: '🎮', path: '/games' },
  { key: 'shorts',    label: '숏츠',     label_en: 'Shorts',     icon: '📱', path: '/shorts' },
  { key: 'music',     label: '음악듣기', label_en: 'Music',      icon: '🎵', path: '/music' },
  { key: 'groupbuy',  label: '공동구매', label_en: 'Group Buy',  icon: '🤝', path: '/groupbuy' },
  { key: 'shopping',  label: '쇼핑',     label_en: 'Shopping',   icon: '🛍️', path: '/shopping' },
  { key: 'chat',      label: '채팅',     label_en: 'Chat',       icon: '💭', path: '/chat' },
  { key: 'friends',   label: '친구',     label_en: 'Friends',    icon: '👫', path: '/friends' },
  { key: 'elder',     label: '안심서비스',label_en: 'Elder Care', icon: '💙', path: '/elder' },
  { key: 'comms',     label: '안심 커뮤', label_en: 'Comms',      icon: '📞', path: '/comms' },
]

async function loadMenus() {
  try {
    const { data } = await axios.get('/api/admin/settings/menus')
    const saved = Array.isArray(data?.data || data) ? (data?.data || data) : []
    if (saved.length > 0) {
      const ordered = []
      saved.forEach(m => {
        const def = allMenuDefs.find(d => d.key === m.key)
        if (def) ordered.push({ ...def, ...m, enabled: m.enabled !== false, login_required: m.login_required || false, admin_only: m.admin_only || false, defaultView: m.defaultView || 'list', ads_enabled: m.ads_enabled || false, ads_type: m.ads_type || 'banner', order: m.order ?? 999 })
      })
      allMenuDefs.forEach(def => {
        if (!ordered.find(m => m.key === def.key)) {
          ordered.push({ ...def, enabled: true, login_required: false, admin_only: false, defaultView: 'list', ads_enabled: false, ads_type: 'banner', order: 999 })
        }
      })
      menuList.value = ordered.sort((a, b) => a.order - b.order)
    } else {
      menuList.value = allMenuDefs.map((d, i) => ({ ...d, enabled: true, login_required: false, admin_only: false, defaultView: 'list', ads_enabled: false, ads_type: 'banner', order: i }))
    }
  } catch {
    menuList.value = allMenuDefs.map((d, i) => ({ ...d, enabled: true, login_required: false, admin_only: false, defaultView: 'list', ads_enabled: false, ads_type: 'banner', order: i }))
  }
}

function moveMenu(idx, dir) {
  const newIdx = idx + dir
  if (newIdx < 0 || newIdx >= menuList.value.length) return
  const list = [...menuList.value]
  const [item] = list.splice(idx, 1)
  list.splice(newIdx, 0, item)
  menuList.value = list
}

function resetMenuOrder() {
  menuList.value = allMenuDefs.map((d, i) => ({ ...d, enabled: true, login_required: false, admin_only: false, ads_enabled: false, ads_type: 'banner', order: i }))
}

async function saveMenus() {
  menuSaving.value = true
  try {
    const menus = menuList.value.map((item, i) => ({
      key: item.key,
      label: item.label,
      label_en: item.label_en,
      icon: item.icon,
      path: item.path,
      enabled: item.enabled,
      order: i,
      login_required: item.login_required || false,
      admin_only: item.admin_only || false,
      defaultView: item.defaultView || 'list',
      hasCardView: item.hasCardView || false,
      ads_enabled: item.ads_enabled || false,
      ads_type: item.ads_type || 'banner',
    }))
    await axios.post('/api/admin/settings/menus/batch', { menus })
    // 같은 세션 내 광고 센터/가격 센터 등 다른 화면도 siteStore.menuConfig를
    // 읽으므로, 저장 직후 스토어에도 바로 반영 — 새로고침 없이 즉시 동기화.
    siteStore.updateMenuConfig(menus)
    showToast('메뉴 설정이 저장되었습니다.')
  } catch {
    showToast('저장 실패', 'error')
  } finally {
    menuSaving.value = false
  }
}

// ─── Firebase 설정 ───────────────────────────────────────────────────────────
const savingFirebase = ref(false)
const firebaseStatus = ref(false)
const firebase = reactive({
  apiKey: '',
  authDomain: '',
  projectId: '',
  storageBucket: '',
  messagingSenderId: '',
  appId: '',
  vapidKey: '',
  credentialsPath: '',
  credentialsExists: false,
})

async function loadFirebase() {
  try {
    const { data } = await axios.get('/api/admin/firebase')
    Object.assign(firebase, data)
    firebaseStatus.value = !!(data.apiKey && data.projectId && data.vapidKey && data.credentialsExists)
  } catch (e) { console.warn('loadFirebase:', e) }
}

async function saveFirebase() {
  savingFirebase.value = true
  try {
    await axios.post('/api/admin/firebase', {
      apiKey: firebase.apiKey,
      authDomain: firebase.authDomain,
      projectId: firebase.projectId,
      storageBucket: firebase.storageBucket,
      messagingSenderId: firebase.messagingSenderId,
      appId: firebase.appId,
      vapidKey: firebase.vapidKey,
    })
    showToast('Firebase 설정이 저장되었습니다')
    firebaseStatus.value = !!(firebase.apiKey && firebase.projectId && firebase.vapidKey)
  } catch (e) {
    showToast(e.response?.data?.message || 'Firebase 저장 실패', 'error')
  } finally {
    savingFirebase.value = false
  }
}

onMounted(loadSettings)
onMounted(loadMenus)
onMounted(loadFirebase)

</script>

<style scoped>
/* 리뉴얼: input-soft 와 동일한 소프트 입력 스타일 (이 파일 전용 별칭) */
.input-field {
  @apply w-full bg-[#F4F6F8] border-[1.5px] border-transparent rounded-xl px-3 py-2 text-sm text-ink placeholder:text-ink-faint outline-none transition-all duration-150 focus:bg-white focus:border-amber-400 focus:ring-4 focus:ring-amber-400/10;
}

/* btn-primary / btn-secondary 는 app.css 전역 공용 클래스를 사용 (로컬 정의 제거) */

.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(-10px) scale(0.95);
}
</style>

<style>
/* 휴대폰 관리자: 사이트 설정은 입력칸이 매우 많아서 화면을 새로 짜지 않고 크기·간격만 손가락에 맞게 조정 */
.ss-m { padding-bottom: 24px; }
.ss-m .card { padding: 14px !important; }
.ss-m input:not([type=checkbox]):not([type=radio]):not([type=range]):not([type=file]):not([type=color]), .ss-m select, .ss-m textarea { font-size: 16px; min-height: 46px; max-width: 100%; }
.ss-m input[type=checkbox], .ss-m input[type=radio] { width: 22px; height: 22px; }
.ss-m button { min-height: 44px; }
.ss-m button.w-7, .ss-m button.w-8 { min-width: 40px; min-height: 40px; }
.ss-m .grid:not(.ss-keep)[class*="grid-cols-2"], .ss-m .grid:not(.ss-keep)[class*="grid-cols-3"], .ss-m .grid:not(.ss-keep)[class*="grid-cols-4"] { grid-template-columns: minmax(0, 1fr); }
.ss-m .ss-keep.grid-cols-3 > div { padding: 10px 4px; }
.ss-m { overflow-x: hidden; }
.ss-m table { display: block; max-width: 100%; overflow-x: auto; }
.ss-m [class*="top-5"][class*="right-5"] { top: calc(70px + env(safe-area-inset-top, 0px)); right: 12px; left: 12px; }
.ss-m h2 { font-size: 17px; }
</style>
