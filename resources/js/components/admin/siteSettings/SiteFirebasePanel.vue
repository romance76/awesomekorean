<template>
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
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import AppIcon from '../../AppIcon.vue'

// Firebase 푸시 알림 설정 — 값(firebase)·불러오기·저장은 SiteSettings.vue 가 맡고, 여기서는 화면과
// 서비스 계정 파일 올리기·테스트 알림만 맡는다. (파일을 올리면 부모가 다시 불러오도록 'reload' 를 보낸다)
const props = defineProps({
  firebase: { type: Object, required: true },
  firebaseStatus: { type: Boolean, default: false },
  savingFirebase: { type: Boolean, default: false },
})
const emit = defineEmits(['save', 'reload'])
const firebase = props.firebase
const saveFirebase = () => emit('save')

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
    emit('reload')
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
</script>

<style scoped>
/* 사이트 설정 화면과 동일한 소프트 입력 스타일 (SiteSettings.vue 와 같은 값) */
.input-field {
  @apply w-full bg-[#F4F6F8] border-[1.5px] border-transparent rounded-xl px-3 py-2 text-sm text-ink placeholder:text-ink-faint outline-none transition-all duration-150 focus:bg-white focus:border-amber-400 focus:ring-4 focus:ring-amber-400/10;
}
</style>
