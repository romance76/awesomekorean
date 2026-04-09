<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-4xl mx-auto px-4 py-5">
    <h1 class="text-xl font-black text-gray-800 mb-4">📋 내 대시보드</h1>

    <!-- 포인트 카드 -->
    <div class="bg-gradient-to-r from-amber-400 via-orange-400 to-amber-500 rounded-2xl p-5 mb-5 text-amber-900">
      <div class="text-sm opacity-80">내 포인트</div>
      <div class="text-3xl font-black">{{ (auth.user?.points || 0).toLocaleString() }}P</div>
    </div>

    <!-- 탭 -->
    <div class="flex gap-1 mb-4 bg-white rounded-xl p-1 shadow-sm border overflow-x-auto scrollbar-hide">
      <button v-for="t in tabs" :key="t.key" @click="switchTab(t.key)"
        class="flex-shrink-0 text-xs font-bold py-2 px-3 rounded-lg transition whitespace-nowrap"
        :class="tab===t.key ? 'bg-amber-400 text-amber-900' : 'text-gray-500 hover:bg-gray-50'">{{ t.icon }} {{ t.label }}</button>
    </div>

    <!-- ═══ 프로필 탭 ═══ -->
    <div v-if="tab==='profile'" class="space-y-4">
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <h2 class="font-bold text-gray-800 mb-4">📝 프로필 수정</h2>
        <div class="flex items-center gap-4 mb-5">
          <div class="w-16 h-16 rounded-full bg-amber-400 text-white flex items-center justify-center text-2xl font-bold">{{ (auth.user?.name||'?')[0] }}</div>
          <label class="cursor-pointer text-sm text-amber-600 font-bold hover:text-amber-800">
            📷 사진 변경<input type="file" accept="image/*" @change="uploadAvatar" class="hidden" />
          </label>
          <span v-if="avatarMsg" class="text-xs text-green-600">{{ avatarMsg }}</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
          <div><label class="text-xs font-bold text-gray-600 mb-1 block">이름</label><input v-model="pf.name" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
          <div><label class="text-xs font-bold text-gray-600 mb-1 block">닉네임</label><input v-model="pf.nickname" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
        </div>
        <div class="mb-3"><label class="text-xs font-bold text-gray-600 mb-1 block">소개</label><textarea v-model="pf.bio" rows="3" placeholder="자기소개를 적어주세요" class="w-full border rounded-lg px-3 py-2 text-sm resize-none"></textarea></div>
        <div class="mb-3"><label class="text-xs font-bold text-gray-600 mb-1 block">전화번호</label><input v-model="pf.phone" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
        <div class="grid grid-cols-3 gap-3 mb-3">
          <div><label class="text-xs font-bold text-gray-600 mb-1 block">도시</label><input v-model="pf.city" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
          <div><label class="text-xs font-bold text-gray-600 mb-1 block">주</label><input v-model="pf.state" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
          <div><label class="text-xs font-bold text-gray-600 mb-1 block">우편번호</label><input v-model="pf.zipcode" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
        </div>
        <div class="mb-3">
          <label class="text-xs font-bold text-gray-600 mb-1 block">친구 요청 허용</label>
          <div class="flex gap-4"><label class="text-sm"><input type="radio" v-model="pf.allow_friend_request" :value="true" /> 수락</label><label class="text-sm"><input type="radio" v-model="pf.allow_friend_request" :value="false" /> 거절</label></div>
        </div>
        <div class="mb-4">
          <label class="text-xs font-bold text-gray-600 mb-1 block">언어</label>
          <select v-model="pf.language" class="border rounded-lg px-3 py-2 text-sm"><option value="ko">한국어</option><option value="en">English</option></select>
        </div>
        <div v-if="pfMsg" class="text-sm mb-2" :class="pfMsgType==='success'?'text-green-600':'text-red-500'">{{ pfMsg }}</div>
        <button @click="saveProfile" :disabled="pfSaving" class="bg-amber-400 text-amber-900 font-bold px-6 py-2 rounded-lg hover:bg-amber-500 disabled:opacity-50">{{ pfSaving ? '저장중...' : '저장하기' }}</button>
      </div>
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <h2 class="font-bold text-gray-800 mb-4">🔒 비밀번호 변경</h2>
        <div class="space-y-3 max-w-sm">
          <input v-model="pw.current_password" type="password" placeholder="현재 비밀번호" class="w-full border rounded-lg px-3 py-2 text-sm" />
          <input v-model="pw.password" type="password" placeholder="새 비밀번호" class="w-full border rounded-lg px-3 py-2 text-sm" />
          <input v-model="pw.password_confirmation" type="password" placeholder="새 비밀번호 확인" class="w-full border rounded-lg px-3 py-2 text-sm" />
        </div>
        <div v-if="pwMsg" class="text-sm mt-2" :class="pwMsgType==='success'?'text-green-600':'text-red-500'">{{ pwMsg }}</div>
        <button @click="changePw" :disabled="pwSaving" class="mt-3 bg-gray-700 text-white font-bold px-6 py-2 rounded-lg hover:bg-gray-800 disabled:opacity-50">{{ pwSaving ? '변경중...' : '변경하기' }}</button>
      </div>
    </div>

    <!-- ═══ 포인트 탭 ═══ -->
    <div v-else-if="tab==='points'" class="space-y-4">
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-bold text-gray-800">💰 포인트</h2>
          <button @click="dailySpin" :disabled="spun" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm hover:bg-amber-500 disabled:opacity-50 disabled:cursor-not-allowed">
            {{ spun ? '✅ 오늘 완료' : '🎰 출석 체크' }}
          </button>
        </div>
        <div v-if="spinResult" class="text-center text-sm font-bold text-green-600 mb-4">🎉 {{ spinResult }}P 적립!</div>
        <div class="text-3xl font-black text-amber-600 mb-4">{{ ptBalance.toLocaleString() }}P</div>
        <h3 class="font-bold text-gray-700 text-sm mb-2">📋 적립/사용 내역</h3>
        <div v-if="!ptHistory.length" class="text-sm text-gray-400 py-4 text-center">내역이 없습니다</div>
        <div v-else class="space-y-1 max-h-80 overflow-y-auto">
          <div v-for="h in ptHistory" :key="h.id" class="flex items-center justify-between py-2 border-b last:border-0 text-sm">
            <div><div class="text-gray-800">{{ h.reason }}</div><div class="text-[10px] text-gray-400">{{ fmtDate(h.created_at) }}</div></div>
            <span :class="h.amount>0?'text-green-600':'text-red-500'" class="font-bold">{{ h.amount>0?'+':'' }}{{ h.amount }}P</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ 쪽지 탭 ═══ -->
    <div v-else-if="tab==='messages'" class="space-y-4">
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-bold text-gray-800">✉️ 쪽지</h2>
          <span v-if="msgUnread" class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">{{ msgUnread }}</span>
        </div>
        <div class="flex gap-1 mb-3 bg-gray-50 rounded-lg p-1">
          <button @click="msgTab='received'; loadMessages()" class="flex-1 text-xs font-bold py-1.5 rounded-lg" :class="msgTab==='received'?'bg-amber-400 text-amber-900':'text-gray-500'">📥 받은 쪽지</button>
          <button @click="msgTab='sent'; loadMessages()" class="flex-1 text-xs font-bold py-1.5 rounded-lg" :class="msgTab==='sent'?'bg-blue-500 text-white':'text-gray-500'">📤 보낸 쪽지</button>
        </div>
        <div v-if="!msgList.length" class="text-sm text-gray-400 py-6 text-center">{{ msgTab==='received'?'받은 쪽지가 없습니다':'보낸 쪽지가 없습니다' }}</div>
        <div v-else class="space-y-0 max-h-96 overflow-y-auto">
          <div v-for="m in msgList" :key="m.id" @click="openMsg(m)" class="flex items-center gap-2 px-3 py-2.5 border-b last:border-0 cursor-pointer hover:bg-amber-50/50" :class="msgTab==='received'&&!m.is_read?'bg-amber-50':''">
            <span v-if="msgTab==='received'&&!m.is_read" class="w-2 h-2 bg-amber-500 rounded-full flex-shrink-0"></span>
            <div class="w-7 h-7 bg-amber-100 rounded-full flex items-center justify-center text-[11px] font-bold text-amber-700 flex-shrink-0">{{ ((msgTab==='received'?m.sender?.name:m.receiver?.name)||'?')[0] }}</div>
            <div class="min-w-0 flex-1">
              <div class="text-xs font-bold text-gray-800 truncate">{{ msgTab==='received'?m.sender?.name:m.receiver?.name }}</div>
              <div class="text-[11px] text-gray-500 truncate">{{ m.content }}</div>
            </div>
            <span class="text-[10px] text-gray-400 flex-shrink-0">{{ fmtDate(m.created_at) }}</span>
          </div>
        </div>
      </div>
      <div v-if="activeMsg" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="activeMsg=null">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
          <div class="bg-gradient-to-r from-amber-400 to-orange-400 px-5 py-3 flex items-center justify-between">
            <span class="text-sm font-bold text-amber-900">{{ msgTab==='received'?activeMsg.sender?.name:activeMsg.receiver?.name }}</span>
            <button @click="activeMsg=null" class="text-amber-900/50 hover:text-amber-900">✕</button>
          </div>
          <div class="p-5">
            <div class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed mb-4">{{ activeMsg.content }}</div>
            <div class="text-[10px] text-gray-400 mb-3">{{ fmtDate(activeMsg.created_at) }}</div>
            <div v-if="msgTab==='received'" class="flex gap-2">
              <button @click="replyMsg(activeMsg)" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm flex-1 hover:bg-amber-500">↩️ 답장</button>
              <button @click="activeMsg=null" class="text-gray-500 px-4 py-2 text-sm">닫기</button>
            </div>
          </div>
        </div>
      </div>
      <div v-if="replyTarget" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="replyTarget=null">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
          <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-5 py-3 flex items-center justify-between">
            <span class="text-sm font-bold text-white">{{ replyTarget.name }}님에게 답장</span>
            <button @click="replyTarget=null" class="text-white/50 hover:text-white">✕</button>
          </div>
          <div class="p-5">
            <textarea v-model="replyText" rows="5" maxlength="500" placeholder="내용을 입력하세요..." class="w-full border rounded-lg p-3 text-sm resize-none"></textarea>
            <div class="flex justify-between items-center mt-3">
              <span class="text-[10px] text-gray-400">{{ replyText.length }}/500</span>
              <button @click="sendReply" :disabled="replySending||!replyText.trim()" class="bg-blue-500 text-white font-bold px-4 py-2 rounded-lg text-sm hover:bg-blue-600 disabled:opacity-50">{{ replySending?'전송중...':'보내기' }}</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ 내 글 탭 ═══ -->
    <div v-else-if="tab==='posts'" class="space-y-4">
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <h2 class="font-bold text-gray-800 mb-4">📄 내가 쓴 글</h2>
        <div v-if="!myPosts.length" class="text-sm text-gray-400 py-6 text-center">작성한 글이 없습니다</div>
        <div v-else class="space-y-2">
          <RouterLink v-for="p in myPosts" :key="p.id" :to="'/community/post/'+p.id" class="block border rounded-lg p-3 hover:bg-amber-50/50 transition">
            <div class="text-sm font-bold text-gray-800 truncate">{{ p.title }}</div>
            <div class="flex items-center gap-2 mt-1">
              <span class="text-[10px] text-gray-400">{{ fmtDate(p.created_at) }}</span>
              <span class="text-[10px] text-amber-600">💬 {{ p.comments_count||0 }}</span>
              <span class="text-[10px] text-red-400">❤️ {{ p.likes_count||0 }}</span>
              <span class="text-[10px] text-gray-400">👁 {{ p.views||0 }}</span>
            </div>
          </RouterLink>
        </div>
      </div>
    </div>

    <!-- ═══ 북마크 탭 ═══ -->
    <div v-else-if="tab==='bookmarks'" class="space-y-4">
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <h2 class="font-bold text-gray-800 mb-4">🔖 북마크</h2>
        <div v-if="!bookmarks.length" class="text-sm text-gray-400 py-6 text-center">저장한 북마크가 없습니다</div>
        <div v-else class="space-y-2">
          <div v-for="b in bookmarks" :key="b.id" class="border rounded-lg p-3">
            <div class="text-sm font-bold text-gray-800">{{ b.bookmarkable?.title || b.bookmarkable_type + ' #' + b.bookmarkable_id }}</div>
            <div class="text-[10px] text-gray-400 mt-1">{{ fmtDate(b.created_at) }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ 안심서비스 탭 ═══ -->
    <div v-else-if="tab==='elder'" class="space-y-4">
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <h2 class="font-bold text-gray-800 mb-4">🛡️ 안심서비스 설정</h2>
        <div class="space-y-3 max-w-lg">
          <div><label class="text-xs font-bold text-gray-600 mb-1 block">보호자 이메일</label><input v-model="elder.guardian_email" type="email" placeholder="보호자 이메일" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
          <div><label class="text-xs font-bold text-gray-600 mb-1 block">보호자 전화번호</label><input v-model="elder.guardian_phone" placeholder="보호자 전화번호" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
          <div>
            <label class="text-xs font-bold text-gray-600 mb-1 block">체크인 알림 간격</label>
            <select v-model="elder.checkin_interval" class="border rounded-lg px-3 py-2 text-sm">
              <option :value="12">12시간</option><option :value="24">24시간</option><option :value="48">48시간</option>
            </select>
          </div>
          <div><label class="text-xs font-bold text-gray-600 mb-1 block">건강 메모</label><textarea v-model="elder.health_notes" rows="3" placeholder="복용 약물, 건강 상태 등" class="w-full border rounded-lg px-3 py-2 text-sm resize-none"></textarea></div>
          <div><label class="text-xs font-bold text-gray-600 mb-1 block">긴급 연락처 (SOS)</label><input v-model="elder.sos_contacts" placeholder="예: 911, 가족번호" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
        </div>
        <div v-if="elderMsg" class="text-sm mt-2" :class="elderMsgType==='success'?'text-green-600':'text-red-500'">{{ elderMsg }}</div>
        <button @click="saveElder" :disabled="elderSaving" class="mt-4 bg-green-600 text-white font-bold px-6 py-2 rounded-lg hover:bg-green-700 disabled:opacity-50">{{ elderSaving?'저장중...':'저장하기' }}</button>
      </div>
    </div>

    <!-- ═══ 결제 탭 ═══ -->
    <div v-else-if="tab==='payments'" class="space-y-4">
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <h2 class="font-bold text-gray-800 mb-4">💳 결제 내역</h2>
        <div v-if="!payments.length" class="text-sm text-gray-400 py-6 text-center">결제 내역이 없습니다</div>
        <div v-else class="space-y-2">
          <div v-for="p in payments" :key="p.id" class="flex items-center justify-between border rounded-lg p-3">
            <div>
              <div class="text-sm font-bold text-gray-800">${{ p.amount }} → {{ p.points_purchased?.toLocaleString() }}P</div>
              <div class="text-[10px] text-gray-400">{{ fmtDate(p.created_at) }}</div>
            </div>
            <span class="text-[10px] px-2 py-0.5 rounded-full font-bold" :class="p.status==='completed'?'bg-green-100 text-green-700':'bg-gray-100 text-gray-500'">{{ p.status==='completed'?'완료':'대기' }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 계정 관리 -->
    <div class="bg-white rounded-xl shadow-sm border p-5 mt-5">
      <h2 class="font-bold text-gray-700 mb-3">⚙️ 계정 관리</h2>
      <div class="flex gap-3">
        <button @click="handleLogout" class="bg-gray-100 text-gray-700 font-bold px-4 py-2 rounded-lg text-sm hover:bg-gray-200">🚪 로그아웃</button>
        <button @click="deleteAccount" class="text-red-400 text-sm hover:text-red-600">⚠️ 회원 탈퇴</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const tab = ref(route.query.tab || 'profile')

const tabs = [
  { key: 'profile', icon: '📝', label: '프로필' },
  { key: 'points', icon: '💰', label: '포인트' },
  { key: 'messages', icon: '✉️', label: '쪽지' },
  { key: 'posts', icon: '📄', label: '내 글' },
  { key: 'bookmarks', icon: '🔖', label: '북마크' },
  { key: 'elder', icon: '🛡️', label: '안심' },
  { key: 'payments', icon: '💳', label: '결제' },
]

const loaded = reactive({})

function switchTab(key) {
  tab.value = key
  if (!loaded[key]) { loadTab(key); loaded[key] = true }
}

function fmtDate(dt) {
  if (!dt) return ''
  const h = Math.floor((Date.now() - new Date(dt).getTime()) / 3600000)
  if (h < 1) return '방금'
  if (h < 24) return h + '시간 전'
  return Math.floor(h / 24) + '일 전'
}

// ─── 프로필 ───
const pf = reactive({ name: '', nickname: '', bio: '', phone: '', city: '', state: '', zipcode: '', language: 'ko', allow_friend_request: true })
const pfMsg = ref(''); const pfMsgType = ref(''); const pfSaving = ref(false); const avatarMsg = ref('')
const pw = reactive({ current_password: '', password: '', password_confirmation: '' })
const pwMsg = ref(''); const pwMsgType = ref(''); const pwSaving = ref(false)

function loadProfile() {
  const u = auth.user
  if (u) Object.assign(pf, { name: u.name, nickname: u.nickname, bio: u.bio, phone: u.phone, city: u.city, state: u.state, zipcode: u.zipcode, language: u.language || 'ko', allow_friend_request: u.allow_friend_request !== false })
}
async function saveProfile() {
  pfSaving.value = true; pfMsg.value = ''
  try { await axios.put('/api/user/profile', pf); await auth.fetchUser(); pfMsg.value = '저장되었습니다!'; pfMsgType.value = 'success' }
  catch (e) { pfMsg.value = e.response?.data?.message || '저장 실패'; pfMsgType.value = 'error' }
  pfSaving.value = false
}
async function uploadAvatar(e) {
  const file = e.target.files[0]; if (!file) return
  const fd = new FormData(); fd.append('avatar', file)
  try { await axios.post('/api/user/avatar', fd); await auth.fetchUser(); avatarMsg.value = '변경됨!' } catch { avatarMsg.value = '실패' }
}
async function changePw() {
  if (pw.password !== pw.password_confirmation) { pwMsg.value = '비밀번호가 일치하지 않습니다'; pwMsgType.value = 'error'; return }
  pwSaving.value = true; pwMsg.value = ''
  try { await axios.post('/api/change-password', pw); pwMsg.value = '변경되었습니다!'; pwMsgType.value = 'success'; pw.current_password = ''; pw.password = ''; pw.password_confirmation = '' }
  catch (e) { pwMsg.value = e.response?.data?.message || '변경 실패'; pwMsgType.value = 'error' }
  pwSaving.value = false
}

// ─── 포인트 ───
const ptBalance = ref(0); const ptHistory = ref([]); const spun = ref(false); const spinResult = ref(null)
async function loadPoints() {
  try { const { data } = await axios.get('/api/points/balance'); ptBalance.value = data.balance || data.points || 0; spun.value = data.daily_spin_done || false } catch {}
  try { const { data } = await axios.get('/api/points/history'); ptHistory.value = data.data?.data || data.data || [] } catch {}
}
async function dailySpin() {
  try { const { data } = await axios.post('/api/points/daily-spin'); spinResult.value = data.points || data.amount; spun.value = true; ptBalance.value += (data.points || data.amount || 0); await auth.fetchUser() }
  catch (e) { alert(e.response?.data?.message || '실패') }
}

// ─── 쪽지 ───
const msgTab = ref('received'); const msgList = ref([]); const msgUnread = ref(0); const activeMsg = ref(null)
const replyTarget = ref(null); const replyText = ref(''); const replySending = ref(false)
async function loadMessages() {
  try { const { data } = await axios.get('/api/messages', { params: { tab: msgTab.value } }); msgList.value = data.data?.data || data.data || []; msgUnread.value = data.unread_count || 0 } catch {}
}
async function openMsg(m) {
  activeMsg.value = m
  if (msgTab.value === 'received' && !m.is_read) { m.is_read = true; msgUnread.value = Math.max(0, msgUnread.value - 1); axios.post(`/api/messages/${m.id}/read`).catch(() => {}) }
}
function replyMsg(m) { activeMsg.value = null; replyTarget.value = { id: m.sender_id || m.sender?.id, name: m.sender?.name }; replyText.value = '' }
async function sendReply() {
  if (!replyText.value.trim()) return; replySending.value = true
  try { await axios.post('/api/messages', { receiver_id: replyTarget.value.id, content: replyText.value.trim() }); replyTarget.value = null; replyText.value = '' }
  catch (e) { alert(e.response?.data?.message || '전송 실패') }
  replySending.value = false
}

// ─── 내 글 ───
const myPosts = ref([])
async function loadPosts() { try { const { data } = await axios.get(`/api/users/${auth.user?.id}/posts`); myPosts.value = data.data?.data || data.data || [] } catch {} }

// ─── 북마크 ───
const bookmarks = ref([])
async function loadBookmarks() { try { const { data } = await axios.get('/api/bookmarks'); bookmarks.value = data.data?.data || data.data || [] } catch {} }

// ─── 안심서비스 ───
const elder = reactive({ guardian_email: '', guardian_phone: '', checkin_interval: 24, health_notes: '', sos_contacts: '' })
const elderMsg = ref(''); const elderMsgType = ref(''); const elderSaving = ref(false)
async function loadElder() { try { const { data } = await axios.get('/api/elder/settings'); if (data.data) Object.assign(elder, data.data) } catch {} }
async function saveElder() {
  elderSaving.value = true; elderMsg.value = ''
  try { await axios.put('/api/elder/settings', elder); elderMsg.value = '저장되었습니다!'; elderMsgType.value = 'success' }
  catch (e) { elderMsg.value = e.response?.data?.message || '저장 실패'; elderMsgType.value = 'error' }
  elderSaving.value = false
}

// ─── 결제 ───
const payments = ref([])
async function loadPayments() { try { const { data } = await axios.get('/api/payments/history'); payments.value = data.data?.data || data.data || [] } catch {} }

// ─── 탭 로딩 ───
function loadTab(key) {
  const loaders = { profile: loadProfile, points: loadPoints, messages: loadMessages, posts: loadPosts, bookmarks: loadBookmarks, elder: loadElder, payments: loadPayments }
  if (loaders[key]) loaders[key]()
}

// ─── 계정 ───
async function handleLogout() { await auth.logout(); router.push('/login') }
async function deleteAccount() {
  const c = prompt('정말 탈퇴하시겠습니까? "탈퇴합니다"를 입력하세요.')
  if (c !== '탈퇴합니다') return
  try { await axios.delete('/api/user/delete'); await auth.logout(); router.push('/') } catch (e) { alert(e.response?.data?.message || '실패') }
}

onMounted(() => {
  loadProfile(); loaded.profile = true
  // URL 쿼리로 탭 지정된 경우 해당 탭 로드
  if (tab.value !== 'profile') { loadTab(tab.value); loaded[tab.value] = true }
})
</script>
