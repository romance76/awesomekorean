<template>
  <div>
  <div class="card p-6 mb-4">
    <div class="flex items-center justify-between mb-5">
      <h2 class="text-base font-semibold text-ink">시스템 공지사항</h2>
      <button @click="showAnnouncementForm = !showAnnouncementForm" class="btn-secondary text-sm">
        {{ showAnnouncementForm ? '취소' : '+ 새 공지 추가' }}
      </button>
    </div>

    <!-- New Announcement Form -->
    <div v-if="showAnnouncementForm" class="border border-amber-100 bg-amber-50 rounded-xl p-5 mb-5">
      <h3 class="text-sm font-semibold text-ink-light mb-4">새 공지사항</h3>
      <div class="grid grid-cols-1 gap-4">
        <div>
          <label class="input-label">제목</label>
          <input v-model="newAnnouncement.title" type="text" class="input-field" placeholder="공지사항 제목" />
        </div>
        <div>
          <label class="input-label">내용</label>
          <textarea v-model="newAnnouncement.content" rows="3" class="input-field resize-none" placeholder="공지사항 내용..."></textarea>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div>
            <label class="input-label">시작 날짜</label>
            <input v-model="newAnnouncement.start_date" type="date" class="input-field" />
          </div>
          <div>
            <label class="input-label">종료 날짜</label>
            <input v-model="newAnnouncement.end_date" type="date" class="input-field" />
          </div>
          <div>
            <label class="input-label">표시 위치</label>
            <select v-model="newAnnouncement.display_position" class="input-field">
              <option value="all">전체</option>
              <option value="home">홈</option>
              <option value="specific">특정 메뉴</option>
            </select>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <ToggleRow v-model="newAnnouncement.active" label="활성화" desc="" />
        </div>
      </div>
      <div class="mt-4 flex justify-end gap-2">
        <button @click="showAnnouncementForm = false" class="btn-secondary">취소</button>
        <button @click="addAnnouncement" class="btn-primary">추가</button>
      </div>
    </div>

    <!-- Announcements List -->
    <div v-if="notifications.announcements.length" class="space-y-2">
      <div
        v-for="(ann, ai) in notifications.announcements"
        :key="ai"
        class="border border-gray-100 rounded-xl p-4"
      >
        <div v-if="editingAnnouncement === ai">
          <!-- Edit mode -->
          <div class="grid grid-cols-1 gap-3">
            <input v-model="ann.title" type="text" class="input-field text-sm" />
            <textarea v-model="ann.content" rows="2" class="input-field resize-none text-sm"></textarea>
            <div class="grid grid-cols-3 gap-2">
              <input v-model="ann.start_date" type="date" class="input-field text-xs" />
              <input v-model="ann.end_date" type="date" class="input-field text-xs" />
              <select v-model="ann.display_position" class="input-field text-xs">
                <option value="all">전체</option>
                <option value="home">홈</option>
                <option value="specific">특정 메뉴</option>
              </select>
            </div>
            <div class="flex justify-end gap-2">
              <button @click="editingAnnouncement = null" class="btn-secondary text-xs">취소</button>
              <button @click="editingAnnouncement = null" class="btn-primary text-xs">저장</button>
            </div>
          </div>
        </div>
        <div v-else class="flex items-start justify-between">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <span :class="['inline-block w-2 h-2 rounded-full', ann.active ? 'bg-green-400' : 'bg-gray-300']"></span>
              <span class="text-sm font-medium text-ink truncate">{{ ann.title }}</span>
              <span class="badge-gray !text-[11px]">{{ positionLabel(ann.display_position) }}</span>
            </div>
            <p class="text-xs text-ink-muted truncate">{{ ann.content }}</p>
            <p class="text-xs text-ink-faint mt-1">{{ ann.start_date }} ~ {{ ann.end_date }}</p>
          </div>
          <div class="flex items-center gap-2 ml-3 flex-shrink-0">
            <button @click="editingAnnouncement = ai" class="text-xs text-amber-600 hover:text-amber-700 transition-colors">수정</button>
            <button @click="removeAnnouncement(ai)" class="text-xs text-red-400 hover:text-red-600 transition-colors">삭제</button>
          </div>
        </div>
      </div>
    </div>
    <div v-else class="text-sm text-ink-muted py-6 text-center">등록된 공지사항이 없습니다.</div>
  </div>

  <!-- 이메일 알림 설정 -->
  <div class="card p-6 mb-4">
    <h2 class="text-base font-semibold text-ink mb-5">이메일 알림 설정</h2>
    <ToggleRow v-model="notifications.email.enabled" label="알림 이메일 활성화" desc="관리자에게 이메일 알림을 전송합니다" class="mb-4" />
    <div :class="['space-y-3 transition-opacity', notifications.email.enabled ? 'opacity-100' : 'opacity-40 pointer-events-none']">
      <div>
        <label class="input-label">신규 가입 알림 수신 이메일</label>
        <input v-model="notifications.email.new_user" type="email" class="input-field" placeholder="admin@awesomekorean.com" />
      </div>
      <div>
        <label class="input-label">신고 접수 알림 수신 이메일</label>
        <input v-model="notifications.email.report" type="email" class="input-field" placeholder="admin@awesomekorean.com" />
      </div>
      <div>
        <label class="input-label">결제 알림 수신 이메일</label>
        <input v-model="notifications.email.payment" type="email" class="input-field" placeholder="billing@awesomekorean.com" />
      </div>
    </div>
  </div>

  <!-- 푸시 알림 설정 -->
  <div class="card p-6 mb-4">
    <h2 class="text-base font-semibold text-ink mb-5">푸시 알림 설정 (VAPID)</h2>
    <div class="space-y-4">
      <div>
        <label class="input-label">VAPID Public Key</label>
        <div class="flex gap-2">
          <input v-model="notifications.push.vapid_public" type="text" class="input-field flex-1 font-mono text-xs" placeholder="BFHtE3h..." readonly />
        </div>
      </div>
      <div>
        <label class="input-label">VAPID Private Key</label>
        <input
          v-model="notifications.push.vapid_private"
          :type="showVapidPrivate ? 'text' : 'password'"
          class="input-field font-mono text-xs"
          placeholder="••••••••"
          readonly
        />
      </div>
      <div class="flex items-center gap-3">
        <button @click="generateVapidKeys" :disabled="generatingKeys" class="btn-secondary flex items-center gap-2">
          <span v-if="generatingKeys" class="inline-block w-4 h-4 border-2 border-gray-400 border-t-transparent rounded-full animate-spin"></span>
          키 생성
        </button>
        <button @click="showVapidPrivate = !showVapidPrivate" class="text-sm text-ink-muted hover:text-ink transition-colors">
          {{ showVapidPrivate ? '숨기기' : '보기' }}
        </button>
        <p class="text-xs text-amber-600">주의: 키를 재생성하면 기존 구독이 모두 해제됩니다.</p>
      </div>
    </div>
  </div>

  <!-- SEO 설정 -->
  <div class="card p-6 mb-4">
    <h2 class="text-base font-semibold text-ink mb-5">SEO 설정</h2>
    <div class="space-y-4">
      <div>
        <label class="input-label">메타 타이틀 기본값</label>
        <input v-model="seo.meta_title" type="text" class="input-field" placeholder="AwesomeKorean - 미국 한인 커뮤니티" />
        <p class="text-xs text-ink-faint mt-1">페이지별 타이틀이 없을 때 사용되는 기본 타이틀</p>
      </div>
      <div>
        <label class="input-label">메타 설명 기본값</label>
        <textarea v-model="seo.meta_description" rows="2" class="input-field resize-none" placeholder="미국 한인 커뮤니티 플랫폼 AwesomeKorean에서 다양한 정보를 나누세요."></textarea>
        <p class="text-xs text-ink-faint mt-1">검색 결과에 표시되는 사이트 설명 (150자 이내 권장)</p>
      </div>
      <div>
        <label class="input-label">Open Graph 이미지 URL</label>
        <div class="flex items-center gap-3">
          <input v-model="seo.og_image" type="text" class="input-field flex-1" placeholder="https://awesomekorean.com/images/og-image.jpg" />
          <div v-if="seo.og_image" class="w-16 h-10 border border-gray-100 rounded-lg overflow-hidden flex-shrink-0">
            <img :src="seo.og_image" alt="OG Preview" class="w-full h-full object-cover" @error="() => {}" />
          </div>
        </div>
        <p class="text-xs text-ink-faint mt-1">SNS 공유 시 표시되는 대표 이미지 (1200x630px 권장)</p>
      </div>

      <div class="border-t border-gray-100 pt-4">
        <h3 class="text-sm font-semibold text-ink-muted mb-3">사이트 인증</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="input-label">Google Site Verification</label>
            <input v-model="seo.google_verification" type="text" class="input-field" placeholder="google-site-verification=..." />
          </div>
          <div>
            <label class="input-label">Naver Site Verification</label>
            <input v-model="seo.naver_verification" type="text" class="input-field" placeholder="naver-site-verification=..." />
          </div>
        </div>
      </div>

      <div class="border-t border-gray-100 pt-4">
        <label class="input-label">robots.txt 편집</label>
        <textarea v-model="seo.robots_txt" rows="8" class="input-field resize-none font-mono text-xs" placeholder="User-agent: *&#10;Allow: /&#10;Sitemap: https://awesomekorean.com/sitemap.xml"></textarea>
        <p class="text-xs text-ink-faint mt-1">검색 엔진 크롤러 접근 규칙을 설정합니다</p>
      </div>
    </div>

    <div class="mt-4 flex justify-end">
      <button @click="saveSeo" :disabled="saving" class="btn-primary">
        <span v-if="saving" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></span>
        SEO 설정 저장
      </button>
    </div>
  </div>

  <div class="flex justify-end">
    <button @click="saveNotifications" :disabled="saving" class="btn-primary">
      <span v-if="saving" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></span>
      알림 설정 저장
    </button>
  </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'
import ToggleRow from './ToggleRow.js'

// 알림 탭(시스템 공지·이메일·푸시 VAPID·SEO) — 값(notifications, seo)과 저장 API 호출은 SiteSettings.vue 가 맡는다.
const props = defineProps({
  notifications: { type: Object, required: true },
  seo: { type: Object, required: true },
  saving: { type: Boolean, default: false },
})
const emit = defineEmits(['save-notifications', 'save-seo', 'toast'])
const notifications = props.notifications
const seo = props.seo
const saveSeo = () => emit('save-seo')
const saveNotifications = () => emit('save-notifications')
const showToast = (message, type = 'success') => emit('toast', message, type)

const generatingKeys = ref(false)
const showVapidPrivate = ref(false)
const showAnnouncementForm = ref(false)
const editingAnnouncement = ref(null)

const newAnnouncement = reactive({
  title: '',
  content: '',
  start_date: '',
  end_date: '',
  display_position: 'all',
  active: true,
})

// ─── Announcement helpers ─────────────────────────────────────────────────────
function addAnnouncement() {
  if (!newAnnouncement.title.trim()) {
    showToast('제목을 입력해주세요.', 'error')
    return
  }
  notifications.announcements.unshift({ ...newAnnouncement })
  Object.assign(newAnnouncement, {
    title: '', content: '', start_date: '', end_date: '',
    display_position: 'all', active: true,
  })
  showAnnouncementForm.value = false
}

function removeAnnouncement(index) {
  if (confirm('이 공지사항을 삭제하시겠습니까?')) {
    notifications.announcements.splice(index, 1)
  }
}

function positionLabel(pos) {
  const map = { all: '전체', home: '홈', specific: '특정 메뉴' }
  return map[pos] || pos
}

// ─── VAPID Key Generation ─────────────────────────────────────────────────────
async function generateVapidKeys() {
  generatingKeys.value = true
  try {
    const { data } = await axios.post('/api/admin/settings/generate-vapid')
    notifications.push.vapid_public = data.public_key
    notifications.push.vapid_private = data.private_key
    showToast('VAPID 키가 생성되었습니다.')
  } catch {
    // Simulate key generation for demo
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/'
    const randomStr = (n) => Array.from({ length: n }, () => chars[Math.floor(Math.random() * chars.length)]).join('')
    notifications.push.vapid_public = 'B' + randomStr(86) + '='
    notifications.push.vapid_private = randomStr(43) + '='
    showToast('VAPID 키가 생성되었습니다 (데모).')
  } finally {
    generatingKeys.value = false
  }
}
</script>

<style scoped>
/* 사이트 설정 화면과 동일한 소프트 입력 스타일 (SiteSettings.vue 와 같은 값) */
.input-field {
  @apply w-full bg-[#F4F6F8] border-[1.5px] border-transparent rounded-xl px-3 py-2 text-sm text-ink placeholder:text-ink-faint outline-none transition-all duration-150 focus:bg-white focus:border-amber-400 focus:ring-4 focus:ring-amber-400/10;
}
</style>
