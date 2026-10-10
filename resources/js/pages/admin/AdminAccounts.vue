<template>
<div class="space-y-4 pb-6 max-w-5xl">
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="mail" :size="20" /></span>
    계정·이메일 현황
  </h1>
  <p class="text-sm text-ink-muted leading-relaxed">사이트 운영에 연결된 이메일을 모아 보여주고, 각각 어디에 쓰이는지 알려드려요. 비밀번호·API 키 값은 이 화면에 나오지 않습니다.</p>
  <div v-if="loading" class="text-center text-ink-muted py-12">불러오는 중...</div>
  <div v-else-if="error" class="text-center text-red-600 py-12">{{ error }}</div>
  <template v-else>
    <section v-for="g in groups" :key="g.key" class="card p-4 space-y-3">
      <div>
        <h2 class="font-bold text-ink text-base">{{ g.title }}</h2>
        <p class="text-[13px] text-ink-muted mt-0.5">{{ g.desc }}</p>
      </div>
      <div v-for="(r, i) in g.rows" :key="i" class="rounded-xl border border-gray-100 p-3">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="text-[11px] px-2 py-0.5 rounded-full font-bold" :class="r.status === 'ok' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">{{ r.status === 'ok' ? '정상' : '확인 필요' }}</span>
          <span class="font-bold text-ink break-all">{{ r.email || '(주소 없음)' }}</span>
        </div>
        <div class="text-[13px] text-ink-light mt-1">{{ r.title }}</div>
        <ul v-if="r.used_in?.length" class="mt-1.5 text-[13px] text-ink list-disc pl-5 space-y-0.5">
          <li v-for="u in r.used_in" :key="u">{{ u }}</li>
        </ul>
        <div v-if="r.note" class="mt-1.5 text-[13px] text-amber-800 bg-amber-50 rounded-lg px-2.5 py-1.5 break-words">{{ r.note }}</div>
      </div>
    </section>

    <section class="card p-4 space-y-3">
      <div>
        <h2 class="font-bold text-ink text-base">운영진 로그인 계정</h2>
        <p class="text-[13px] text-ink-muted mt-0.5">관리자 화면에 들어올 수 있는 계정이에요. 사용하지 않는 계정은 정리하는 게 안전해요.</p>
      </div>
      <div v-for="s in mainStaff" :key="s.id" class="rounded-xl border border-gray-100 p-3 flex items-center gap-2 flex-wrap">
        <span class="text-[11px] px-2 py-0.5 rounded-full font-bold bg-blue-50 text-blue-700">{{ roleLabel(s.role) }}</span>
        <span class="font-bold text-ink break-all">{{ s.email }}</span>
        <span class="text-[13px] text-ink-light">{{ s.name }}</span>
        <span v-if="s.invalid" class="text-[11px] px-2 py-0.5 rounded-full font-bold bg-red-100 text-red-700">주소 오타 의심</span>
        <span v-if="s.is_test" class="text-[11px] px-2 py-0.5 rounded-full font-bold bg-gray-100 text-ink-light">테스트 계정</span>
        <span v-if="!s.verified" class="text-[11px] px-2 py-0.5 rounded-full font-bold bg-amber-100 text-amber-700">이메일 미인증</span>
        <span class="text-[12px] text-ink-faint ml-auto">{{ s.last_login_at ? '마지막 로그인 ' + fmt(s.last_login_at) : '로그인 기록 없음' }}</span>
      </div>
      <div v-if="testStaff.length" class="rounded-xl bg-gray-50 p-3">
        <button @click="showTest = !showTest" class="text-[13px] font-bold text-ink-light underline">테스트 운영진 {{ testStaff.length }}명 {{ showTest ? '접기' : '보기' }}</button>
        <div v-if="showTest" class="mt-2 space-y-1 text-[13px] text-ink-light">
          <div v-for="s in testStaff" :key="s.id" class="break-all">{{ roleLabel(s.role) }} · {{ s.email }}</div>
        </div>
      </div>
    </section>

    <section v-if="data.service_accounts.length" class="card p-4 space-y-3">
      <div>
        <h2 class="font-bold text-ink text-base">서비스 계정 (구글·Firebase)</h2>
        <p class="text-[13px] text-ink-muted mt-0.5">사람이 아니라 서버가 대신 로그인하는 계정이에요. 메일함은 없고 주소 모양만 이메일이에요.</p>
      </div>
      <div v-for="(s, i) in data.service_accounts" :key="i" class="rounded-xl border border-gray-100 p-3">
        <div class="font-bold text-ink break-all">{{ s.email }}</div>
        <div class="text-[13px] text-ink-light mt-1">{{ s.title }}</div>
        <ul class="mt-1.5 text-[13px] text-ink list-disc pl-5"><li v-for="u in s.used_in" :key="u">{{ u }}</li></ul>
      </div>
    </section>

    <section class="card p-4 space-y-3">
      <div>
        <h2 class="font-bold text-ink text-base">연동 서비스 (API 키 관리에 등록된 것)</h2>
        <p class="text-[13px] text-ink-muted mt-0.5">각 서비스가 어디에 쓰이는지만 보여드려요. 키를 바꾸려면 "설정 → API 키 관리"에서 하세요.</p>
      </div>
      <div class="divide-y divide-gray-50">
        <div v-for="s in data.services" :key="s.service" class="py-2 flex items-start gap-2">
          <span class="mt-1.5 w-2 h-2 rounded-full shrink-0" :class="s.active ? 'bg-emerald-500' : 'bg-gray-300'"></span>
          <div class="min-w-0">
            <div class="text-[14px] font-bold text-ink">{{ s.label }} <span class="text-[11px] font-normal text-ink-faint">{{ s.active ? '사용 중' : '꺼짐' }}</span></div>
            <div class="text-[13px] text-ink-muted break-words">{{ s.used_in }}</div>
          </div>
        </div>
      </div>
    </section>

    <section class="card p-4 space-y-3">
      <div>
        <h2 class="font-bold text-ink text-base">직접 적어 두는 계정 메모</h2>
        <p class="text-[13px] text-ink-muted mt-0.5">서버가 알 수 없는 것(도메인 업체, 서버 업체, GitHub 등 어떤 이메일로 가입했는지)을 적어 두세요. 비밀번호는 절대 적지 마세요.</p>
      </div>
      <div v-for="(m, i) in memos" :key="i" class="rounded-xl border border-gray-100 p-3 space-y-2">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
          <input v-model="m.label" maxlength="80" placeholder="서비스 이름 (예: DigitalOcean)" class="input-soft" />
          <input v-model="m.email" maxlength="190" type="email" placeholder="가입한 이메일" class="input-soft" />
        </div>
        <div class="flex gap-2">
          <input v-model="m.note" maxlength="300" placeholder="메모 (예: 서버 호스팅 / 결제 카드 변경 시 여기서)" class="input-soft flex-1" />
          <button @click="memos.splice(i, 1)" class="px-3 text-sm font-bold text-red-600 rounded-lg hover:bg-red-50" aria-label="이 줄 지우기">삭제</button>
        </div>
      </div>
      <div class="flex flex-wrap gap-2 items-center">
        <button @click="memos.push({ label: '', email: '', note: '' })" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-bold text-ink-light">+ 줄 추가</button>
        <button @click="addTemplates" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-bold text-ink-light">자주 쓰는 항목 채우기</button>
        <button @click="saveMemos" :disabled="saving" class="px-5 py-2 rounded-xl bg-amber-500 text-white text-sm font-bold disabled:opacity-50 ml-auto">{{ saving ? '저장 중...' : '메모 저장' }}</button>
      </div>
      <p v-if="saveMsg" class="text-sm" :class="saveOk ? 'text-emerald-700' : 'text-red-600'">{{ saveMsg }}</p>
    </section>
    <p class="text-[12px] text-ink-faint">조회 시각 {{ fmt(data.generated_at) }}</p>
  </template>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const loading = ref(true), error = ref('')
const data = ref({ rows: [], staff: [], service_accounts: [], services: [], memos: [], generated_at: '' })
const memos = ref([])
const showTest = ref(false)
const saving = ref(false), saveMsg = ref(''), saveOk = ref(false)

const groups = computed(() => [
  { key: 'sender', title: '메일 발신 주소', desc: '회원에게 가는 메일의 보낸 사람이에요.' },
  { key: 'contact', title: '사이트에 공개된 연락처', desc: '회원이 문의할 때 보는 주소예요.' },
  { key: 'notify', title: '알림을 받는 주소', desc: '시스템이 알림을 보내는 주소예요.' },
].map(g => ({ ...g, rows: data.value.rows.filter(r => r.group === g.key) })).filter(g => g.rows.length))

const testStaff = computed(() => data.value.staff.filter(s => s.is_test && s.role === 'moderator'))
const mainStaff = computed(() => data.value.staff.filter(s => !(s.is_test && s.role === 'moderator')))
const roleLabel = r => ({ super_admin: '최고 관리자', admin: '관리자', moderator: '운영자' }[r] || r)

function fmt(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString('ko-KR', { timeZone: 'America/New_York', hour12: false }) + ' (애틀랜타)'
}

const TEMPLATES = [
  ['도메인 업체 (awesomekorean.com)', '도메인 갱신·DNS 관리'],
  ['DigitalOcean (서버)', '서버 호스팅·결제'],
  ['GitHub (코드 저장소)', '코드 저장·자동 배포'],
  ['Resend (메일 발송)', '메일 발송 서비스 로그인'],
  ['Google Cloud / Firebase', 'API·푸시 알림 프로젝트'],
  ['Google AdSense / Analytics', '광고 수익·방문 분석'],
]
function addTemplates() {
  TEMPLATES.forEach(([label, note]) => { if (!memos.value.some(m => m.label === label)) memos.value.push({ label, email: '', note }) })
}

async function load() {
  try {
    const { data: r } = await axios.get('/api/admin/accounts-overview')
    data.value = r.data
    memos.value = (r.data.memos || []).map(m => ({ ...m }))
  } catch (e) { error.value = e.response?.data?.message || '불러오지 못했어요.' }
  loading.value = false
}

async function saveMemos() {
  saving.value = true; saveMsg.value = ''
  try {
    const list = memos.value.filter(m => m.label.trim() || m.email.trim() || m.note.trim()).map(m => ({ ...m, label: m.label.trim() || '(이름 없음)' }))
    const { data: r } = await axios.post('/api/admin/accounts-overview/memos', { memos: list })
    memos.value = r.data.map(m => ({ ...m }))
    saveOk.value = true; saveMsg.value = '저장했어요.'
  } catch (e) {
    saveOk.value = false
    saveMsg.value = e.response?.data?.message || '저장하지 못했어요. 이메일 형식을 확인해 주세요.'
  }
  saving.value = false
}
onMounted(load)
</script>
