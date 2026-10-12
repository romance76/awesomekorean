<template>
  <div class="space-y-4">
    <div class="card p-4 text-sm text-ink-light leading-relaxed">
      <div class="font-semibold text-ink mb-1">사이트 공식 SNS 계정</div>
      MONEY 글 등을 인스타그램·스레드에 자동으로 올릴 때 쓰는 계정 정보예요. 앱 시크릿·액세스 토큰은 <b>암호화</b>해서 저장하고, 저장 뒤에는 끝 4자리만 보여요.
      <b>계정 비밀번호는 저장하지 않아요</b>(자동 게시는 Meta 액세스 토큰으로만 해요). 저장할 때 관리자 비밀번호를 한 번 더 물어봐요.
      <div class="text-xs text-ink-faint mt-1">자동 게시 기능은 계획서(qa/sns_autopost_plan.md) 확인 후 만들어요. 지금은 "자동 게시"를 켜도 아직 올라가지 않아요.</div>
    </div>

    <div v-if="loading" class="card p-6 text-center text-ink-muted text-sm">불러오는 중…</div>
    <div v-else-if="loadError" class="card p-6 text-center text-red-500 text-sm">{{ loadError }}</div>

    <div v-for="a in accounts" :key="a.platform" class="card p-5">
      <div class="flex items-center justify-between gap-2 mb-4 flex-wrap">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white font-black" :style="a.platform === 'instagram' ? 'background:linear-gradient(135deg,#F58529,#DD2A7B,#8134AF)' : 'background:#111'">
            {{ a.platform === 'instagram' ? 'IG' : '@' }}
          </div>
          <div>
            <h2 class="text-base font-semibold text-ink">{{ meta[a.platform].name }}</h2>
            <p class="text-xs text-ink-faint">마지막 저장: {{ a.updated_at || '—' }}</p>
          </div>
        </div>
        <div class="flex gap-1.5">
          <span :class="a.access_token_masked ? 'badge-green' : 'badge-red'">{{ a.access_token_masked ? '토큰 저장됨' : '토큰 없음' }}</span>
          <span v-if="expiryNote(a)" :class="expiryNote(a).cls">{{ expiryNote(a).text }}</span>
        </div>
      </div>

      <div class="grid sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-ink-light mb-1">계정 아이디</label>
          <input v-model="a.handle" type="text" class="input-soft w-full" placeholder="@awesomekorean" />
        </div>
        <div>
          <label class="block text-xs font-bold text-ink-light mb-1">프로필 주소</label>
          <input v-model="a.profile_url" type="url" class="input-soft w-full" :placeholder="meta[a.platform].urlPh" />
          <p v-if="a.platform === 'instagram'" class="text-[11px] text-ink-faint mt-1">저장하면 사이트 아래쪽 인스타그램 링크도 이 주소로 바뀌어요.</p>
        </div>
        <div>
          <label class="block text-xs font-bold text-ink-light mb-1">계정 이메일 (참고용)</label>
          <input v-model="a.login_email" type="email" class="input-soft w-full" placeholder="awesomekoreanusa@…" />
        </div>
        <div>
          <label class="block text-xs font-bold text-ink-light mb-1">{{ meta[a.platform].idLabel }}</label>
          <input v-model="a.account_id" type="text" class="input-soft w-full font-mono text-sm" placeholder="숫자 ID" />
        </div>
        <div v-if="a.platform === 'instagram'">
          <label class="block text-xs font-bold text-ink-light mb-1">연결된 페이스북 페이지 ID</label>
          <input v-model="a.page_id" type="text" class="input-soft w-full font-mono text-sm" placeholder="숫자 ID" />
        </div>
        <div>
          <label class="block text-xs font-bold text-ink-light mb-1">Meta 앱 ID</label>
          <input v-model="a.app_id" type="text" class="input-soft w-full font-mono text-sm" placeholder="developers.facebook.com 의 앱 ID" />
        </div>
        <div>
          <label class="block text-xs font-bold text-ink-light mb-1">앱 시크릿 <span class="text-ink-faint font-normal">{{ a.app_secret_masked ? '(저장됨 ' + a.app_secret_masked + ')' : '' }}</span></label>
          <input v-model="a.app_secret" type="password" autocomplete="new-password" class="input-soft w-full font-mono text-sm" :placeholder="a.app_secret_masked ? '바꿀 때만 입력' : '앱 시크릿'" />
          <label v-if="a.app_secret_masked" class="text-[11px] text-ink-faint inline-flex items-center gap-1 mt-1"><input type="checkbox" v-model="a.clear_app_secret" /> 저장된 값 지우기</label>
        </div>
        <div class="sm:col-span-2">
          <label class="block text-xs font-bold text-ink-light mb-1">액세스 토큰 (장기, 약 60일) <span class="text-ink-faint font-normal">{{ a.access_token_masked ? '(저장됨 ' + a.access_token_masked + ')' : '' }}</span></label>
          <input v-model="a.access_token" type="password" autocomplete="new-password" class="input-soft w-full font-mono text-sm" :placeholder="a.access_token_masked ? '바꿀 때만 입력' : '액세스 토큰'" />
          <label v-if="a.access_token_masked" class="text-[11px] text-ink-faint inline-flex items-center gap-1 mt-1"><input type="checkbox" v-model="a.clear_access_token" /> 저장된 값 지우기</label>
        </div>
        <div>
          <label class="block text-xs font-bold text-ink-light mb-1">토큰 만료일</label>
          <input v-model="a.token_expires_at" type="date" class="input-soft w-full" />
        </div>
        <div class="flex flex-col justify-end gap-2 text-sm">
          <label class="inline-flex items-center gap-2"><input type="checkbox" v-model="a.is_active" /> 이 계정 사용</label>
          <label class="inline-flex items-center gap-2"><input type="checkbox" v-model="a.autopost_enabled" /> 자동 게시 (기능 완성 후 동작)</label>
        </div>
        <div class="sm:col-span-2">
          <label class="block text-xs font-bold text-ink-light mb-1">메모</label>
          <textarea v-model="a.notes" rows="2" class="input-soft w-full text-sm" placeholder="예) 2단계 인증은 Kay 휴대폰, 페이지 관리자: …"></textarea>
        </div>
      </div>

      <div class="flex justify-end mt-4">
        <button @click="save(a)" :disabled="saving === a.platform" class="btn-primary px-5 py-2 text-sm font-bold">
          {{ saving === a.platform ? '저장 중…' : meta[a.platform].name + ' 저장' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const emit = defineEmits(['toast'])
const meta = {
  instagram: { name: '인스타그램', urlPh: 'https://www.instagram.com/아이디', idLabel: '인스타 비즈니스 계정 ID' },
  threads: { name: '스레드', urlPh: 'https://www.threads.net/@아이디', idLabel: '스레드 사용자 ID' },
}
const accounts = ref([])
const loading = ref(true)
const loadError = ref('')
const saving = ref('')

function blank(a) { return { ...a, app_secret: '', access_token: '', clear_app_secret: false, clear_access_token: false } }

async function load() {
  loading.value = true; loadError.value = ''
  try {
    const { data } = await axios.get('/api/admin/settings/social-accounts')
    accounts.value = (data.data || []).map(blank)
  } catch (e) {
    loadError.value = e.response?.status === 403 ? '최고관리자만 볼 수 있어요.' : '불러오지 못했어요. 새로고침해 주세요.'
  }
  loading.value = false
}

function expiryNote(a) {
  if (!a.token_expires_at) return null
  const days = Math.ceil((new Date(a.token_expires_at) - new Date()) / 86400000)
  if (days < 0) return { text: '토큰 만료됨', cls: 'badge-red' }
  if (days <= 10) return { text: `만료 ${days}일 남음`, cls: 'badge-red' }
  return { text: `만료 ${days}일 남음`, cls: 'badge-primary' }
}

async function save(a) {
  saving.value = a.platform
  const body = {
    handle: a.handle || '', profile_url: a.profile_url || '', login_email: a.login_email || '',
    account_id: a.account_id || '', page_id: a.page_id || '', app_id: a.app_id || '',
    app_secret: a.app_secret || '', access_token: a.access_token || '',
    clear_app_secret: !!a.clear_app_secret, clear_access_token: !!a.clear_access_token,
    token_expires_at: a.token_expires_at || null, notes: a.notes || '',
    is_active: !!a.is_active, autopost_enabled: !!a.autopost_enabled,
  }
  try {
    const { data } = await axios.put(`/api/admin/settings/social-accounts/${a.platform}`, body)
    const i = accounts.value.findIndex(x => x.platform === a.platform)
    if (i >= 0 && data.data) accounts.value[i] = blank(data.data)
    emit('toast', data.message || '저장되었습니다')
  } catch (e) {
    const errs = e.response?.data?.errors
    emit('toast', errs ? Object.values(errs)[0][0] : (e.response?.data?.message || '저장하지 못했어요'), 'error')
  }
  saving.value = ''
}

onMounted(load)
</script>
