<template>
<div class="space-y-4 pb-6 max-w-2xl">
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="shield" :size="20" /></span>
    2단계 인증
  </h1>
  <p class="text-sm text-ink-muted leading-relaxed">
    비밀번호가 새어 나가도 관리자 계정이 뚫리지 않게, 로그인할 때 <b>폰의 인증 앱(구글 OTP·Authy 등)에 나오는 6자리 코드</b>를 한 번 더 확인해요.
    내 계정에만 적용되고, 켜기 전까지는 지금처럼 로그인돼요.
  </p>

  <div v-if="loading" class="text-center text-ink-muted py-12">불러오는 중...</div>
  <div v-else-if="loadError" class="text-center text-red-600 py-12">{{ loadError }}</div>

  <template v-else>
    <!-- 새 복구 코드 (켜기/재발급 직후 한 번만 보임) -->
    <section v-if="recoveryCodes.length" class="card p-4 space-y-3 border-2 border-amber-300">
      <h2 class="font-bold text-ink text-base">복구 코드를 지금 저장하세요</h2>
      <p class="text-[13px] text-ink-light leading-relaxed">
        폰을 잃어버렸을 때 로그인할 수 있는 <b>1회용 코드</b>예요. 이 화면을 벗어나면 <b>다시 볼 수 없어요.</b>
        비밀번호 관리자나 종이에 적어 폰과 다른 곳에 보관하세요.
      </p>
      <div class="grid grid-cols-2 gap-2 font-mono text-[15px] text-ink">
        <div v-for="c in recoveryCodes" :key="c" class="rounded-lg bg-gray-50 px-3 py-2 text-center select-all">{{ c }}</div>
      </div>
      <div class="flex gap-2">
        <button type="button" class="btn-primary flex-1 min-h-[44px]" @click="copyCodes">{{ copied ? '복사했어요' : '전체 복사' }}</button>
        <button type="button" class="flex-1 min-h-[44px] rounded-xl border border-gray-200 text-ink-light font-bold text-sm" @click="recoveryCodes = []">저장했어요</button>
      </div>
    </section>

    <!-- 켜져 있음 -->
    <section v-if="status.enabled" class="card p-4 space-y-3">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-[12px] px-2.5 py-1 rounded-full font-bold bg-emerald-100 text-emerald-700">켜져 있어요</span>
        <span class="text-[13px] text-ink-light">남은 복구 코드 {{ status.recovery_left }}개</span>
      </div>
      <p v-if="status.recovery_left <= 2" class="text-[13px] text-amber-700 font-medium">복구 코드가 얼마 남지 않았어요. 아래에서 새로 받아 두세요.</p>

      <div class="rounded-xl border border-gray-100 p-3 space-y-2">
        <h3 class="font-bold text-ink text-sm">복구 코드 새로 받기</h3>
        <p class="text-[12.5px] text-ink-muted">지금 인증 앱의 코드를 입력하면 새 코드 8개가 나오고, 예전 코드는 모두 못 써요.</p>
        <div class="flex gap-2">
          <input v-model="regenCode" inputmode="numeric" autocomplete="one-time-code" maxlength="20" placeholder="6자리 코드" class="input-soft flex-1" />
          <button type="button" :disabled="busy || !regenCode.trim()" class="btn-primary min-h-[44px] px-4 disabled:opacity-50" @click="regenerate">받기</button>
        </div>
      </div>

      <div class="rounded-xl border border-red-100 p-3 space-y-2">
        <h3 class="font-bold text-red-700 text-sm">2단계 인증 끄기</h3>
        <p class="text-[12.5px] text-ink-muted">끄면 비밀번호만으로 로그인돼요. 비밀번호와 인증 코드(또는 복구 코드)를 모두 확인해요.</p>
        <input v-model="offPassword" type="password" autocomplete="current-password" placeholder="비밀번호" class="input-soft w-full" />
        <input v-model="offCode" inputmode="numeric" autocomplete="one-time-code" maxlength="20" placeholder="6자리 코드 또는 복구 코드" class="input-soft w-full" />
        <button type="button" :disabled="busy || !offPassword || !offCode.trim()" class="w-full min-h-[44px] rounded-xl bg-red-50 text-red-700 font-bold text-sm disabled:opacity-50" @click="disable">2단계 인증 끄기</button>
      </div>
    </section>

    <!-- 꺼져 있음: 1단계 시작 -->
    <section v-else-if="!setup" class="card p-4 space-y-3">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-[12px] px-2.5 py-1 rounded-full font-bold bg-gray-100 text-ink-light">꺼져 있어요</span>
      </div>
      <ol class="text-[13px] text-ink-light leading-relaxed list-decimal pl-5 space-y-1">
        <li>폰에 인증 앱(구글 OTP, Authy, Microsoft Authenticator 중 아무거나)을 설치해요.</li>
        <li>아래 "켜기 시작"을 눌러 나오는 키를 앱에 등록해요.</li>
        <li>앱에 나오는 6자리 코드를 입력하면 켜지고, 복구 코드를 받아요.</li>
      </ol>
      <button type="button" :disabled="busy" class="btn-primary w-full min-h-[48px] disabled:opacity-50" @click="startSetup">켜기 시작</button>
    </section>

    <!-- 꺼져 있음: 키 등록 + 코드 확인 -->
    <section v-else class="card p-4 space-y-3">
      <h2 class="font-bold text-ink text-base">인증 앱에 등록하세요</h2>
      <p class="text-[13px] text-ink-light leading-relaxed">
        <b>폰으로 보고 있다면</b> 아래 "인증 앱에서 열기"를 누르면 앱이 열리면서 자동으로 등록돼요.
        <b>컴퓨터로 보고 있다면</b> 폰 앱에서 "키 직접 입력"을 고르고 아래 키를 넣어요(유형: 시간 기준).
      </p>
      <a :href="setup.otpauth_url" class="block text-center w-full min-h-[44px] leading-[44px] rounded-xl bg-amber-50 text-amber-800 font-bold text-sm">인증 앱에서 열기</a>
      <div class="rounded-xl bg-gray-50 p-3">
        <div class="text-[12px] text-ink-muted mb-1">비밀키 (남에게 보여 주지 마세요)</div>
        <div class="font-mono text-[15px] text-ink break-all select-all tracking-wider">{{ pretty(setup.secret) }}</div>
      </div>
      <div>
        <label class="input-label">앱에 나온 6자리 코드</label>
        <div class="flex gap-2">
          <input v-model="confirmCode" inputmode="numeric" autocomplete="one-time-code" maxlength="10" placeholder="123456" class="input-soft flex-1 tracking-widest" @keyup.enter="confirm" />
          <button type="button" :disabled="busy || !confirmCode.trim()" class="btn-primary min-h-[44px] px-4 disabled:opacity-50" @click="confirm">켜기</button>
        </div>
      </div>
      <button type="button" class="text-[13px] text-ink-muted underline" @click="setup = null">취소</button>
    </section>

    <p v-if="msg" class="text-[13.5px] font-bold break-words" :class="msgError ? 'text-red-600' : 'text-emerald-700'" role="status">{{ msg }}</p>

    <section class="card p-4 text-[12.5px] text-ink-muted leading-relaxed space-y-1">
      <div class="font-bold text-ink text-[13px]">알아 두세요</div>
      <div>· 2단계 인증을 켠 계정은 구글·아마존 소셜 로그인으로 들어올 수 없고, 이메일·비밀번호 + 코드로만 로그인해요.</div>
      <div>· 폰과 복구 코드를 모두 잃었다면 다른 최고관리자가 해제해 주거나, 서버에서 <span class="font-mono">php artisan user:2fa-reset 이메일</span> 로 풀 수 있어요.</div>
      <div>· 폰의 시계가 틀리면 코드가 안 맞아요. 폰의 날짜·시간을 "자동"으로 두세요.</div>
    </section>
  </template>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const loading = ref(true), loadError = ref('')
const status = ref({ enabled: false, pending: false, recovery_left: 0 })
const setup = ref(null)              // { secret, otpauth_url } — 켜는 중일 때만
const confirmCode = ref(''), regenCode = ref(''), offPassword = ref(''), offCode = ref('')
const recoveryCodes = ref([]), copied = ref(false)
const busy = ref(false), msg = ref(''), msgError = ref(false)

const say = (t, err = false) => { msg.value = t; msgError.value = err }
const errText = (e) => e.response?.data?.errors ? Object.values(e.response.data.errors)[0]?.[0] : (e.response?.data?.message || '처리하지 못했어요. 다시 시도해 주세요.')
// 비밀키를 4글자씩 끊어 보여 준다(앱에 직접 입력할 때 읽기 쉽게)
const pretty = (s) => (s || '').replace(/(.{4})/g, '$1 ').trim()

// silent: 켜기·끄기 뒤에 상태만 새로 읽을 때는 화면을 "불러오는 중"으로 바꾸지 않는다(복구 코드가 사라져 보이지 않게)
async function load(silent = false) {
  if (!silent) { loading.value = true; loadError.value = '' }
  try { status.value = (await axios.get('/api/me/2fa')).data.data }
  catch (e) { if (!silent) loadError.value = e.response?.status === 403 ? '관리자·운영자만 쓸 수 있어요.' : errText(e) }
  loading.value = false
}

async function run(fn) {
  if (busy.value) return
  busy.value = true; say('')
  try { await fn() } catch (e) { say(errText(e), true) }
  busy.value = false
}

const startSetup = () => run(async () => { setup.value = (await axios.post('/api/me/2fa/setup')).data.data; confirmCode.value = '' })
const confirm = () => run(async () => {
  const d = (await axios.post('/api/me/2fa/confirm', { code: confirmCode.value.trim() })).data.data
  recoveryCodes.value = d.recovery_codes; setup.value = null; confirmCode.value = ''
  say('✓ 2단계 인증을 켰어요. 다음 로그인부터 코드를 물어봐요.')
  await load(true)
})
const regenerate = () => run(async () => {
  recoveryCodes.value = (await axios.post('/api/me/2fa/recovery', { code: regenCode.value.trim() })).data.data.recovery_codes
  regenCode.value = ''; say('✓ 새 복구 코드를 만들었어요. 예전 코드는 못 써요.')
  await load(true)
})
const disable = () => run(async () => {
  await axios.post('/api/me/2fa/disable', { password: offPassword.value, code: offCode.value.trim() })
  offPassword.value = ''; offCode.value = ''; recoveryCodes.value = []; say('2단계 인증을 껐어요.')
  await load(true)
})
async function copyCodes() {
  try { await navigator.clipboard.writeText(recoveryCodes.value.join('\n')); copied.value = true; setTimeout(() => { copied.value = false }, 2500) }
  catch { say('복사하지 못했어요. 코드를 길게 눌러 직접 복사해 주세요.', true) }
}

onMounted(load)
</script>
