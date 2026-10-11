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

      <!-- Firebase Cloud Messaging 설정 -->
      <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="flex items-center gap-2 text-lg font-bold text-ink"><span class="icon-chip w-8 h-8 bg-amber-50 text-amber-500"><AppIcon name="bell" :size="16" /></span>Firebase Push Notification</h3>
          <span :class="firebaseStatus ? 'badge-green' : 'badge-primary'">
            {{ firebaseStatus ? '연결됨' : '미설정' }}
          </span>
        </div>
        <p class="text-xs text-ink-faint mb-4">안심통화 수신 알림, 백그라운드 푸시, 벨소리에 사용됩니다.</p>

        <div class="space-y-3">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="input-label !text-xs">API Key</label>
              <input v-model="firebase.apiKey" type="text" class="input-field font-mono" placeholder="AIzaSy..." />
            </div>
            <div>
              <label class="input-label !text-xs">Project ID</label>
              <input v-model="firebase.projectId" type="text" class="input-field font-mono" placeholder="awesomekorean-xxxxx" />
            </div>
            <div>
              <label class="input-label !text-xs">Messaging Sender ID</label>
              <input v-model="firebase.messagingSenderId" type="text" class="input-field font-mono" placeholder="123456789" />
            </div>
            <div>
              <label class="input-label !text-xs">App ID</label>
              <input v-model="firebase.appId" type="text" class="input-field font-mono" placeholder="1:123:web:abc..." />
            </div>
            <div>
              <label class="input-label !text-xs">Auth Domain</label>
              <input v-model="firebase.authDomain" type="text" class="input-field font-mono" placeholder="xxx.firebaseapp.com" />
            </div>
            <div>
              <label class="input-label !text-xs">Storage Bucket</label>
              <input v-model="firebase.storageBucket" type="text" class="input-field font-mono" placeholder="xxx.appspot.com" />
            </div>
          </div>
          <div>
            <label class="input-label !text-xs">VAPID Key (웹 푸시 인증서)</label>
            <input v-model="firebase.vapidKey" type="text" class="input-field font-mono" placeholder="BA93MT..." />
          </div>
          <div>
            <label class="input-label !text-xs">서비스 계정 JSON (백엔드용 — 서버에 직접 업로드)</label>
            <div class="flex items-center gap-2">
              <code class="text-xs bg-gray-100 px-3 py-2 rounded-lg flex-1 font-mono text-ink-light">{{ firebase.credentialsPath || '/storage/app/firebase-service-account.json' }}</code>
              <span :class="firebase.credentialsExists ? 'text-green-600' : 'text-red-500'" class="text-xs font-medium">{{ firebase.credentialsExists ? '파일 존재' : '파일 없음' }}</span>
            </div>
            <div class="flex items-center gap-2 mt-2 flex-wrap">
              <input ref="credFileInput" type="file" accept=".json,application/json" class="hidden" @change="uploadFirebaseCredentials" />
              <button type="button" @click="credFileInput.click()" :disabled="uploadingCred" class="px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-bold text-ink-light hover:bg-gray-50 disabled:opacity-50">
                {{ uploadingCred ? '올리는 중...' : '📤 서비스 계정 JSON 업로드' }}
              </button>
              <span v-if="firebase.credentialsExists && firebase.credentialsProject" class="text-[11px] text-ink-muted">파일의 프로젝트: <b>{{ firebase.credentialsProject }}</b></span>
              <span v-if="firebase.credentialsProject && firebase.projectId && firebase.credentialsProject !== firebase.projectId" class="text-[11px] text-red-500 font-bold">⚠️ 위 Project ID({{ firebase.projectId }})와 달라요</span>
            </div>
            <p v-if="credMsg" class="text-[11px] mt-1" :class="credOk ? 'text-green-600' : 'text-red-500'">{{ credMsg }}</p>
            <p class="text-[11px] text-ink-faint mt-1">Firebase 콘솔 → 프로젝트 설정 → 서비스 계정 → "새 비공개 키 생성"으로 받은 파일이에요. 비공개 키라서 서버 안쪽 폴더에만 저장되고 화면에는 표시되지 않아요. (최고 관리자만 업로드 가능)</p>
          </div>
          <button @click="saveFirebase" :disabled="savingFirebase" class="btn-primary w-full mt-2">
            {{ savingFirebase ? '저장 중...' : 'Firebase 설정 저장' }}
          </button>
          <p class="text-[11px] text-ink-faint mt-2 leading-relaxed">저장하면 사이트를 다시 만들지 않아도 바로 적용돼요. 회원이 사이트를 열 때 브라우저가 "알림 허용"을 물어보고, 허용하면 새 쪽지·1:1 대화·안심 통화 알림이 휴대폰/PC 알림으로 와요. (한 계정당 가장 최근에 허용한 기기 1대로 보내요)</p>
          <button @click="testFirebasePush" :disabled="testingPush" class="w-full mt-2 py-2.5 rounded-xl border border-gray-200 text-sm font-bold text-ink-light hover:bg-gray-50 disabled:opacity-50">
            {{ testingPush ? '보내는 중...' : '🔔 내 기기로 테스트 알림 보내기' }}
          </button>
          <p v-if="pushTestMsg" class="text-xs mt-2 leading-relaxed" :class="pushTestOk ? 'text-green-600' : 'text-red-500'">{{ pushTestMsg }}</p>
        </div>
      </div>

      <!-- 기존 API 키 관리 -->
      <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="flex items-center gap-2 text-lg font-bold text-ink"><span class="icon-chip w-8 h-8 bg-amber-50 text-amber-600"><AppIcon name="key" :size="16" /></span>API 키 관리</h3>
          <div class="flex gap-2">
            <button @click="runMailTestSend" class="btn-secondary px-4 py-2 text-sm">테스트 메일 보내기</button>
            <button @click="openAddAnalytics" class="btn-secondary px-4 py-2 text-sm">+ 구글 Analytics</button>
            <button @click="showAddApiKey = true" class="btn-primary px-4 py-2">+ 새 API 키</button>
          </div>
        </div>

        <div class="space-y-3">
          <div v-for="key in apiKeys" :key="key.id" class="border border-gray-100 rounded-xl p-4 flex items-center justify-between">
            <div class="flex-1">
              <div class="flex items-center gap-2">
                <span class="text-sm font-bold text-ink">{{ key.name }}</span>
                <span class="badge-blue !text-[11px]">{{ key.service }}</span>
                <span :class="key.is_active ? 'badge-green' : 'badge-red'" class="!text-[11px]">{{ key.is_active ? '활성' : '비활성' }}</span>
              </div>
              <p v-if="key.description" class="text-xs text-ink-muted mt-1">{{ key.description }}</p>
              <div class="flex items-center gap-2 mt-2">
                <code class="text-xs bg-gray-100 px-2 py-1 rounded-lg font-mono text-ink-light">{{ key.showFull ? key.fullKey : key.masked_key }}</code>
                <button @click="toggleReveal(key)" class="text-xs text-amber-600 hover:underline transition-colors">{{ key.showFull ? '숨기기' : '보기' }}</button>
                <button @click="copyKey(key)" class="text-xs text-ink-muted hover:underline transition-colors">복사</button>
              </div>
            </div>
            <div class="flex gap-2">
              <button @click="openEditApiKey(key)" class="text-xs text-blue-600 hover:underline">수정</button>
              <button @click="toggleApiKeyActive(key)" :class="key.is_active ? 'text-amber-600' : 'text-green-600'" class="text-xs hover:underline">{{ key.is_active ? '비활성화' : '활성화' }}</button>
              <button @click="deleteApiKey(key.id)" class="text-xs text-red-600 hover:underline">삭제</button>
            </div>
          </div>
          <div v-if="!apiKeys.length" class="text-center py-8 text-ink-muted text-sm">등록된 API 키가 없습니다</div>
        </div>
      </div>

      <!-- 새 API 키 추가 모달 -->
      <div v-if="showAddApiKey" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="showAddApiKey = false">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md">
          <h3 class="flex items-center gap-2 font-bold text-lg text-ink mb-4"><span class="icon-chip w-8 h-8 bg-amber-50 text-amber-600"><AppIcon name="key" :size="16" /></span>새 API 키 등록</h3>
          <div class="space-y-3">
            <div>
              <label class="input-label !text-xs">서비스 이름</label>
              <input v-model="newApiKey.name" placeholder="예: YouTube Data API v3" class="input-field" />
            </div>
            <div>
              <label class="input-label !text-xs">서비스 코드</label>
              <input v-model="newApiKey.service" placeholder="예: youtube, stripe, openai" class="input-field" />
            </div>
            <div>
              <label class="input-label !text-xs">API 키</label>
              <input v-model="newApiKey.api_key" placeholder="키 입력..." class="input-field font-mono" />
            </div>
            <div>
              <label class="input-label !text-xs">설명 (선택)</label>
              <input v-model="newApiKey.description" placeholder="용도 설명" class="input-field" />
            </div>
            <div class="flex gap-2 pt-2">
              <button @click="showAddApiKey = false" class="btn-secondary flex-1 py-2">취소</button>
              <button @click="saveApiKey" class="btn-primary flex-1 py-2">등록</button>
            </div>
          </div>
        </div>
      </div>

      <!-- API 키 수정 모달 -->
      <div v-if="showEditApiKey" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="showEditApiKey = false">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md">
          <h3 class="flex items-center gap-2 font-bold text-lg text-ink mb-4"><span class="icon-chip w-8 h-8 bg-amber-50 text-amber-600"><AppIcon name="key" :size="16" /></span>API 키 수정</h3>
          <div class="space-y-3">
            <div>
              <label class="input-label !text-xs">서비스 이름</label>
              <input v-model="editApiKey.name" class="input-field" />
            </div>
            <div>
              <label class="input-label !text-xs">서비스 코드</label>
              <input v-model="editApiKey.service" class="input-field" />
            </div>
            <div>
              <label class="input-label !text-xs">API 키</label>
              <input v-model="editApiKey.api_key" placeholder="변경하려면 입력 (비워두면 기존 값 유지)" class="input-field font-mono" />
            </div>
            <div>
              <label class="input-label !text-xs">설명 (선택)</label>
              <input v-model="editApiKey.description" class="input-field" />
            </div>
            <div class="flex gap-2 pt-2">
              <button @click="showEditApiKey = false" class="btn-secondary flex-1 py-2">취소</button>
              <button @click="saveEditApiKey" class="btn-primary flex-1 py-2">저장</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch, computed, inject } from 'vue'

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
import SitePaymentTab from '../../components/admin/siteSettings/SitePaymentTab.vue'
import SiteNotifyTab from '../../components/admin/siteSettings/SiteNotifyTab.vue'
import { menuIcon, menuChipColor } from '../../utils/menuIcons'
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

const credFileInput = ref(null)
const uploadingCred = ref(false)
const credMsg = ref('')
const credOk = ref(false)
async function uploadFirebaseCredentials(e) {
  const file = e.target.files?.[0]; e.target.value = ''
  if (!file) return
  uploadingCred.value = true; credMsg.value = ''
  try {
    const fd = new FormData(); fd.append('file', file)
    const { data } = await axios.post('/api/admin/firebase/credentials', fd)
    credMsg.value = data.message; credOk.value = true
    await loadFirebase()
  } catch (er) {
    credMsg.value = er.response?.data?.message || (er.response?.status === 403 ? '최고 관리자만 업로드할 수 있어요' : '업로드하지 못했어요'); credOk.value = false
  }
  uploadingCred.value = false
}

const testingPush = ref(false)
const pushTestMsg = ref('')
const pushTestOk = ref(false)
async function testFirebasePush() {
  testingPush.value = true; pushTestMsg.value = ''
  try {
    const { data } = await axios.post('/api/admin/firebase/test')
    pushTestMsg.value = data.message; pushTestOk.value = !!data.success
  } catch (e) {
    pushTestMsg.value = e.response?.data?.message || '테스트 알림을 보내지 못했어요'; pushTestOk.value = false
  }
  testingPush.value = false
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

// ─── API 키 관리 ──────────────────────────────────────────────────────────────
const apiKeys = ref([])
const showAddApiKey = ref(false)
const newApiKey = ref({ name: '', service: '', api_key: '', description: '' })
const showEditApiKey = ref(false)
const editApiKey = ref({ id: null, name: '', service: '', api_key: '', description: '' })

async function loadApiKeys() {
  try {
    const { data } = await axios.get('/api/admin/api-keys')
    const list = data.data || data || []
    apiKeys.value = list.map(k => ({ ...k, showFull: false, fullKey: '' }))
  } catch (e) { console.error('loadApiKeys error:', e) }
}

// 구글 Analytics(GA4) 측정 ID — service 코드가 google_analytics 인 키가 있으면(활성일 때)
// 모든 공개 페이지에 방문 통계 추적 코드가 자동으로 들어간다. 비활성화/삭제하면 빠짐.
function openAddAnalytics() {
  newApiKey.value = { name: '구글 Analytics 측정 ID', service: 'google_analytics', api_key: '', description: '방문 통계(GA4). 측정 ID는 G- 로 시작합니다 (예: G-ABC123DEF4)' }
  showAddApiKey.value = true
}

async function saveApiKey() {
  try {
    await axios.post('/api/admin/api-keys', newApiKey.value)
    showAddApiKey.value = false
    newApiKey.value = { name: '', service: '', api_key: '', description: '' }
    loadApiKeys()
  } catch (e) { alert(e.response?.data?.message || '등록 실패') }
}

// api_key는 목록에 마스킹된 값만 내려오므로 빈 칸으로 시작 — 비워두고 저장하면
// 백엔드가 그 필드를 건드리지 않고 기존 값을 그대로 유지함
function openEditApiKey(key) {
  editApiKey.value = { id: key.id, name: key.name, service: key.service, api_key: '', description: key.description || '' }
  showEditApiKey.value = true
}

async function saveEditApiKey() {
  try {
    const payload = { name: editApiKey.value.name, service: editApiKey.value.service, description: editApiKey.value.description }
    if (editApiKey.value.api_key) payload.api_key = editApiKey.value.api_key
    await axios.put('/api/admin/api-keys/' + editApiKey.value.id, payload)
    showEditApiKey.value = false
    loadApiKeys()
  } catch (e) { alert(e.response?.data?.message || '수정 실패') }
}

async function deleteApiKey(id) {
  if (!confirm('정말 삭제하시겠습니까?')) return
  await axios.delete('/api/admin/api-keys/' + id)
  loadApiKeys()
}

async function toggleApiKeyActive(key) {
  await axios.put('/api/admin/api-keys/' + key.id, { is_active: !key.is_active })
  loadApiKeys()
}

async function runMailTestSend() {
  try {
    const { data } = await axios.post('/api/admin/system/mail-test-send')
    alert(data.message)
  } catch (e) { alert('발송 실패 — 실제 에러: ' + (e.response?.data?.message || e.message)) }
}

async function toggleReveal(key) {
  if (!key.showFull) {
    const { data } = await axios.get('/api/admin/api-keys/' + key.id + '/reveal')
    key.fullKey = data.data?.key || data.key || key.api_key || ''
  }
  key.showFull = !key.showFull
}

function copyKey(key) {
  const text = key.showFull ? key.fullKey : key.masked_key
  navigator.clipboard.writeText(key.fullKey || text)
  alert('복사되었습니다!')
}

onMounted(loadSettings)
onMounted(loadMenus)
onMounted(loadApiKeys)
onMounted(loadFirebase)

watch(() => activeTab.value, (tab) => {
  if (tab === 'api') loadApiKeys()
})

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
