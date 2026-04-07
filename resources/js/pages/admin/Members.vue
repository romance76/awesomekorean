<template>
<div>
  <h1 class="text-xl font-black text-gray-800 mb-4">👥 회원관리</h1>

  <!-- 검색 + 필터 -->
  <div class="bg-white rounded-xl shadow-sm border p-3 mb-4">
    <div class="flex flex-wrap gap-2">
      <select v-model="roleFilter" @change="loadUsers()" class="border rounded-lg px-2 py-1.5 text-xs outline-none">
        <option value="">전체 회원</option><option value="super_admin">슈퍼관리자</option><option value="admin">관리자</option><option value="moderator">운영자</option><option value="business">기업회원</option><option value="user">일반</option>
      </select>
      <select v-model="statusFilter" @change="loadUsers()" class="border rounded-lg px-2 py-1.5 text-xs outline-none">
        <option value="">전체 상태</option><option value="active">활동</option><option value="banned">정지</option>
      </select>
      <form @submit.prevent="loadUsers()" class="flex-1 flex gap-1 min-w-[150px]">
        <input v-model="search" type="text" placeholder="이름/이메일/닉네임 검색..." class="flex-1 border rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-amber-400" />
        <button type="submit" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs">검색</button>
      </form>
    </div>
    <div class="text-[10px] text-gray-400 mt-1">전체 {{ totalUsers }}명</div>
  </div>

  <div v-if="loading" class="text-center py-8 text-gray-400">로딩중...</div>
  <div v-else class="flex gap-4">
    <!-- 왼쪽: 회원 목록 -->
    <div :class="activeUser ? 'w-2/5' : 'w-full'">
      <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b"><tr>
            <th class="px-3 py-2 text-left text-xs text-gray-500 w-8">#</th>
            <th class="px-3 py-2 text-left text-xs text-gray-500">이름</th>
            <th v-if="!activeUser" class="px-3 py-2 text-left text-xs text-gray-500">이메일</th>
            <th class="px-3 py-2 text-xs text-gray-500">역할</th>
            <th class="px-3 py-2 text-xs text-gray-500">포인트</th>
            <th class="px-3 py-2 text-xs text-gray-500">상태</th>
            <th class="px-3 py-2 text-xs text-gray-500">가입</th>
          </tr></thead>
          <tbody>
            <tr v-for="u in users" :key="u.id"
              class="border-b last:border-0 hover:bg-amber-50/30 cursor-pointer transition"
              :class="activeUser?.id===u.id ? 'bg-amber-50 border-l-2 border-l-amber-500' : ''"
              @click="openUser(u)">
              <td class="px-3 py-2 text-xs text-gray-400">{{ u.id }}</td>
              <td class="px-3 py-2.5">
                <div class="flex items-center gap-2">
                  <div class="w-7 h-7 rounded-full bg-amber-100 flex items-center justify-center text-xs font-bold text-amber-700">{{ (u.name||'?')[0] }}</div>
                  <div>
                    <div class="text-sm font-medium text-gray-800">{{ u.name }}</div>
                    <div v-if="activeUser" class="text-[10px] text-gray-400">{{ u.email }}</div>
                  </div>
                </div>
              </td>
              <td v-if="!activeUser" class="px-3 py-2.5 text-xs text-gray-500">{{ u.email }}</td>
              <td class="px-3 py-2.5 text-center"><span class="text-[10px] px-2 py-0.5 rounded-full font-bold" :class="roleClass(u.role)">{{ roleLabel(u.role) }}</span></td>
              <td class="px-3 py-2.5 text-center text-xs text-amber-600 font-semibold">{{ u.points||0 }}</td>
              <td class="px-3 py-2.5 text-center"><span :class="u.is_banned?'text-red-500':'text-green-500'" class="text-[10px] font-bold">{{ u.is_banned ? '정지' : '활동' }}</span></td>
              <td class="px-3 py-2.5 text-[10px] text-gray-400">{{ u.created_at?.slice(2,10) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
        <button v-for="pg in Math.min(lastPage, 10)" :key="pg" @click="loadUsers(pg)"
          class="px-3 py-1 rounded text-sm" :class="pg===page?'bg-amber-400 text-amber-900 font-bold':'bg-white border text-gray-600'">{{ pg }}</button>
      </div>
    </div>

    <!-- 오른쪽: 회원 상세 인라인 뷰 -->
    <div v-if="activeUser" class="w-3/5">
      <div class="bg-white rounded-xl shadow-sm border overflow-hidden sticky top-4">
        <!-- 헤더 -->
        <div class="px-4 py-3 border-b flex items-center justify-between bg-amber-50">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-amber-400 text-white flex items-center justify-center text-lg font-bold">{{ (userData?.user?.name||'?')[0] }}</div>
            <div>
              <div class="font-bold text-gray-900">{{ userData?.user?.name }}</div>
              <div class="text-[10px] text-gray-500">{{ userData?.user?.email }} · ID: {{ userData?.user?.id }}</div>
            </div>
          </div>
          <button @click="activeUser=null" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
        </div>

        <!-- 서브메뉴 탭 -->
        <div class="flex border-b overflow-x-auto">
          <button v-for="t in userTabs" :key="t.key" @click="userTab=t.key"
            class="px-4 py-2.5 text-xs font-semibold border-b-2 -mb-px transition whitespace-nowrap"
            :class="userTab===t.key?'border-amber-500 text-amber-700':'border-transparent text-gray-500 hover:text-gray-700'">{{ t.label }}</button>
        </div>

        <div v-if="userLoading" class="py-8 text-center text-gray-400">로딩중...</div>
        <div v-else-if="userData" class="max-h-[70vh] overflow-y-auto">

          <!-- 기본 정보 -->
          <div v-show="userTab==='info'" class="p-4">
            <div class="grid grid-cols-2 gap-3">
              <div><label class="text-[10px] text-gray-500">이름</label><input v-model="userData.user.name" class="w-full border rounded px-2 py-1.5 text-sm mt-0.5" /></div>
              <div><label class="text-[10px] text-gray-500">닉네임</label><input v-model="userData.user.nickname" class="w-full border rounded px-2 py-1.5 text-sm mt-0.5" /></div>
              <div><label class="text-[10px] text-gray-500">이메일</label><input v-model="userData.user.email" class="w-full border rounded px-2 py-1.5 text-sm mt-0.5" /></div>
              <div><label class="text-[10px] text-gray-500">전화</label><input v-model="userData.user.phone" class="w-full border rounded px-2 py-1.5 text-sm mt-0.5" /></div>
              <div><label class="text-[10px] text-gray-500">도시</label><input v-model="userData.user.city" class="w-full border rounded px-2 py-1.5 text-sm mt-0.5" /></div>
              <div><label class="text-[10px] text-gray-500">주</label><input v-model="userData.user.state" class="w-full border rounded px-2 py-1.5 text-sm mt-0.5" /></div>
              <div><label class="text-[10px] text-gray-500">역할</label>
                <select v-model="userData.user.role" class="w-full border rounded px-2 py-1.5 text-sm mt-0.5">
                  <option value="user">일반회원</option>
                  <option value="business">기업회원</option>
                  <option value="moderator">운영자</option>
                  <option value="admin">관리자</option>
                  <option value="super_admin">슈퍼관리자</option>
                </select></div>
              <div><label class="text-[10px] text-gray-500">상태</label>
                <select v-model="userData.user.is_banned" class="w-full border rounded px-2 py-1.5 text-sm mt-0.5"><option :value="false">활동</option><option :value="true">정지</option></select></div>
            </div>
            <div class="mt-2"><label class="text-[10px] text-gray-500">소개</label><textarea v-model="userData.user.bio" rows="2" class="w-full border rounded px-2 py-1.5 text-sm mt-0.5 resize-none"></textarea></div>
            <div class="mt-2 flex items-center gap-4 text-[10px] text-gray-400">
              <span>가입: {{ userData.user.created_at?.slice(0,10) }}</span>
              <span>최근: {{ userData.user.last_login_at?.slice(0,10) || '없음' }}</span>
              <span>로그인 {{ userData.user.login_count || 0 }}회</span>
            </div>
            <button @click="saveUser" class="mt-3 bg-amber-400 text-amber-900 font-bold px-5 py-2 rounded-lg text-sm hover:bg-amber-500">저장</button>
          </div>

          <!-- 포인트 -->
          <div v-show="userTab==='points'" class="p-4">
            <div class="flex items-center gap-3 mb-3">
              <div class="bg-amber-50 rounded-lg px-4 py-2">
                <div class="text-xl font-black text-amber-600">{{ userData.user.points||0 }}P</div>
                <div class="text-[10px] text-gray-400">보유 포인트</div>
              </div>
              <div class="bg-blue-50 rounded-lg px-4 py-2">
                <div class="text-xl font-black text-blue-600">{{ userData.user.game_points||0 }}P</div>
                <div class="text-[10px] text-gray-400">게임 포인트</div>
              </div>
              <div class="flex gap-2 ml-auto">
                <input v-model.number="addPoints" type="number" placeholder="포인트" class="border rounded px-2 py-1 text-sm w-20" />
                <button @click="givePoints" class="bg-green-500 text-white px-3 py-1 rounded text-xs font-bold">지급</button>
              </div>
            </div>
            <div v-if="!userData.points?.length" class="py-4 text-center text-gray-400 text-sm">포인트 내역 없음</div>
            <div v-for="pt in userData.points" :key="pt.id" class="py-2 border-b flex justify-between text-xs">
              <span class="text-gray-500">{{ pt.created_at?.slice(0,10) }}</span>
              <span>{{ pt.reason }}</span>
              <span :class="pt.amount>0?'text-green-600':'text-red-600'" class="font-bold">{{ pt.amount>0?'+':'' }}{{ pt.amount }}P</span>
            </div>
          </div>

          <!-- 결제 -->
          <div v-show="userTab==='payments'" class="p-4">
            <div v-if="!userData.payments?.length" class="py-4 text-center text-gray-400 text-sm">결제 내역 없음</div>
            <div v-for="p in userData.payments" :key="p.id" class="py-2 border-b flex justify-between text-xs">
              <span>{{ p.created_at?.slice(0,10) }}</span><span>${{ p.amount }}</span><span class="text-amber-600 font-bold">+{{ p.points }}P</span><span>{{ p.status }}</span>
            </div>
          </div>

          <!-- 게시글 -->
          <div v-show="userTab==='posts'" class="p-4">
            <div v-if="!userData.posts?.length" class="py-4 text-center text-gray-400 text-sm">게시글 없음</div>
            <div v-for="post in userData.posts" :key="post.id" class="py-2 border-b">
              <div class="text-sm font-medium text-gray-800">{{ post.title }}</div>
              <div class="text-[10px] text-gray-400">{{ post.created_at?.slice(0,10) }} · 조회 {{ post.view_count }} · ❤️ {{ post.like_count }}</div>
            </div>
          </div>

          <!-- 구인/장터 -->
          <div v-show="userTab==='activity'" class="p-4">
            <div v-if="userData.jobs?.length" class="mb-4">
              <div class="text-xs font-bold text-gray-700 mb-2">💼 구인 {{ userData.jobs.length }}건</div>
              <div v-for="j in userData.jobs" :key="j.id" class="py-1.5 border-b text-xs"><span class="font-medium text-gray-800">{{ j.title }}</span> <span class="text-gray-400">· {{ j.company }}</span></div>
            </div>
            <div v-if="userData.market?.length">
              <div class="text-xs font-bold text-gray-700 mb-2">🛒 장터 {{ userData.market.length }}건</div>
              <div v-for="m in userData.market" :key="m.id" class="py-1.5 border-b text-xs"><span class="font-medium text-gray-800">{{ m.title }}</span> <span class="text-amber-600">${{ m.price }}</span></div>
            </div>
            <div v-if="!userData.jobs?.length && !userData.market?.length" class="py-4 text-center text-gray-400 text-sm">활동 내역 없음</div>
          </div>

          <!-- 댓글 -->
          <div v-show="userTab==='comments'" class="p-4">
            <div v-if="!userData.comments?.length" class="py-4 text-center text-gray-400 text-sm">댓글 없음</div>
            <div v-for="c in userData.comments" :key="c.id" class="py-2 border-b">
              <div class="text-sm text-gray-700">{{ c.content }}</div>
              <div class="text-[10px] text-gray-400">{{ c.created_at?.slice(0,10) }}</div>
            </div>
          </div>

          <!-- 관리 -->
          <div v-show="userTab==='manage'" class="p-4 space-y-4">
            <!-- 권한 설정 -->
            <div>
              <h3 class="text-sm font-bold text-gray-800 mb-2">🔑 권한 설정</h3>
              <div class="grid grid-cols-1 gap-2">
                <label v-for="r in roleOptions" :key="r.value"
                  class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition"
                  :class="userData.user.role===r.value ? 'border-amber-400 bg-amber-50' : 'border-gray-200 hover:bg-gray-50'"
                  @click="userData.user.role=r.value">
                  <div class="w-8 h-8 rounded-lg flex items-center justify-center text-lg" :class="r.bgClass">{{ r.icon }}</div>
                  <div class="flex-1">
                    <div class="text-sm font-semibold text-gray-800">{{ r.label }}</div>
                    <div class="text-[10px] text-gray-400">{{ r.desc }}</div>
                  </div>
                  <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center" :class="userData.user.role===r.value ? 'border-amber-500 bg-amber-500' : 'border-gray-300'">
                    <div v-if="userData.user.role===r.value" class="w-2 h-2 rounded-full bg-white"></div>
                  </div>
                </label>
              </div>
              <button @click="saveUser" class="mt-3 bg-amber-400 text-amber-900 font-bold px-5 py-2 rounded-lg text-sm hover:bg-amber-500">권한 저장</button>
            </div>

            <!-- 계정 정지 -->
            <div class="border-t pt-4">
              <h3 class="text-sm font-bold text-gray-800 mb-2">🚫 계정 관리</h3>
              <div class="flex gap-2 items-end">
                <div class="flex-1">
                  <label class="text-xs text-gray-500">정지 사유</label>
                  <input v-model="banReason" type="text" placeholder="정지 사유 입력" class="w-full border rounded px-2 py-1.5 text-sm mt-0.5" />
                </div>
                <button v-if="!userData.user.is_banned" @click="banCurrentUser" class="bg-red-500 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-red-600 flex-shrink-0">🚫 정지</button>
                <button v-else @click="unbanCurrentUser" class="bg-green-500 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-green-600 flex-shrink-0">✅ 해제</button>
              </div>
              <div v-if="userData.user.is_banned" class="mt-2 text-sm text-red-500 bg-red-50 rounded-lg px-3 py-2">현재 정지됨 — 사유: {{ userData.user.ban_reason || '없음' }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const users = ref([]); const loading = ref(true)
const search = ref(''); const roleFilter = ref(''); const statusFilter = ref('')
const page = ref(1); const lastPage = ref(1); const totalUsers = ref(0)

const activeUser = ref(null); const userData = ref(null); const userLoading = ref(false)
const userTab = ref('info'); const banReason = ref(''); const addPoints = ref(0)

const roleOptions = [
  { value: 'super_admin', label: '슈퍼관리자', icon: '👑', bgClass: 'bg-red-100', desc: '모든 권한 (사이트 설정, 회원 관리, 콘텐츠 삭제)' },
  { value: 'admin', label: '관리자', icon: '🔧', bgClass: 'bg-purple-100', desc: '콘텐츠 관리, 회원 정지, 신고 처리' },
  { value: 'moderator', label: '운영자', icon: '🛡️', bgClass: 'bg-blue-100', desc: '게시글/댓글 관리, 신고 처리' },
  { value: 'business', label: '기업회원', icon: '🏢', bgClass: 'bg-green-100', desc: '업소록 등록, 구인 등록, 프로모션 가능' },
  { value: 'user', label: '일반회원', icon: '👤', bgClass: 'bg-gray-100', desc: '기본 회원 (글쓰기, 댓글, 게임 참여)' },
]

function roleLabel(role) {
  return { super_admin:'슈퍼관리자', admin:'관리자', moderator:'운영자', business:'기업회원', user:'일반' }[role] || role
}
function roleClass(role) {
  return { super_admin:'bg-red-100 text-red-700', admin:'bg-purple-100 text-purple-700', moderator:'bg-blue-100 text-blue-700', business:'bg-green-100 text-green-700', user:'bg-gray-100 text-gray-600' }[role] || 'bg-gray-100 text-gray-600'
}

const userTabs = [
  { key: 'info', label: '기본정보' },
  { key: 'points', label: '포인트' },
  { key: 'payments', label: '결제' },
  { key: 'posts', label: '게시글' },
  { key: 'activity', label: '구인/장터' },
  { key: 'comments', label: '댓글' },
  { key: 'manage', label: '관리' },
]

async function loadUsers(p = 1) {
  loading.value = true; page.value = p
  const params = { page: p }
  if (search.value) params.search = search.value
  if (roleFilter.value) params.role = roleFilter.value
  try {
    const { data } = await axios.get('/api/admin/users', { params })
    users.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
    totalUsers.value = data.data?.total || 0
  } catch {}
  loading.value = false
}

async function openUser(u) {
  activeUser.value = u; userLoading.value = true; userData.value = null; userTab.value = 'info'
  try { const { data } = await axios.get(`/api/admin/users/${u.id}/detail`); userData.value = data.data } catch {}
  userLoading.value = false
}

async function saveUser() {
  if (!userData.value?.user) return
  try {
    await axios.put(`/api/admin/users/${userData.value.user.id}`, userData.value.user)
    // 목록도 업데이트
    const u = users.value.find(x => x.id === userData.value.user.id)
    if (u) Object.assign(u, userData.value.user)
    alert('저장되었습니다!')
  } catch (e) { alert(e.response?.data?.message || '저장 실패') }
}

async function givePoints() {
  if (!addPoints.value || !userData.value?.user) return
  try {
    // 포인트 직접 수정
    userData.value.user.points = (userData.value.user.points || 0) + addPoints.value
    await axios.put(`/api/admin/users/${userData.value.user.id}`, { points: userData.value.user.points })
    addPoints.value = 0
    alert('포인트 지급!')
  } catch {}
}

async function banCurrentUser() {
  const reason = banReason.value || '관리자 정지'
  try {
    await axios.post(`/api/admin/users/${userData.value.user.id}/ban`, { reason })
    userData.value.user.is_banned = true; userData.value.user.ban_reason = reason
    const u = users.value.find(x => x.id === userData.value.user.id)
    if (u) u.is_banned = true
  } catch {}
}

async function unbanCurrentUser() {
  try {
    await axios.post(`/api/admin/users/${userData.value.user.id}/unban`)
    userData.value.user.is_banned = false; userData.value.user.ban_reason = null
    const u = users.value.find(x => x.id === userData.value.user.id)
    if (u) u.is_banned = false
  } catch {}
}

onMounted(() => loadUsers())
</script>
