<template>
<div>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
    <span class="icon-chip w-9 h-9 bg-blue-50 text-blue-600"><AppIcon name="edit" :size="20" /></span>
    콘텐츠 관리
  </h1>

  <!-- 검색 + 필터 -->
  <div class="card p-3 mb-4">
    <div class="flex flex-wrap gap-2">
      <select v-model="boardFilter" @change="load()" class="input-soft w-auto px-3 py-1.5 text-xs">
        <option value="">전체 게시판</option>
        <option v-for="b in boards" :key="b.id" :value="b.id">{{ b.name }}</option>
      </select>
      <form @submit.prevent="load()" class="flex-1 flex gap-1.5 min-w-[150px]">
        <input v-model="search" type="text" placeholder="제목/작성자 검색..." class="flex-1 input-soft px-3 py-1.5" />
        <button type="submit" class="btn-primary px-3 py-1.5 text-xs">검색</button>
      </form>
    </div>
    <div class="text-[11px] text-ink-faint mt-1">전체 {{ totalPosts }}건</div>
  </div>

  <div class="flex gap-4">
    <!-- 왼쪽: 목록 -->
    <div :class="activePost ? 'w-1/2' : 'w-full'">
      <div v-if="loading" class="text-center py-8 text-ink-muted">로딩중...</div>
      <div v-else class="card overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-100"><tr>
            <th class="px-2 py-2 text-left text-xs text-ink-muted w-8">#</th>
            <th class="px-2 py-2 text-left text-xs text-ink-muted">제목</th>
            <th v-if="!activePost" class="px-2 py-2 text-left text-xs text-ink-muted">게시판</th>
            <th class="px-2 py-2 text-left text-xs text-ink-muted">작성자</th>
            <th class="px-2 py-2 text-xs text-ink-muted"><AppIcon name="message-circle" :size="13" class="mx-auto" /></th>
            <th class="px-2 py-2 text-xs text-ink-muted"><AppIcon name="eye" :size="13" class="mx-auto" /></th>
            <th class="px-2 py-2 text-xs text-ink-muted">날짜</th>
            <th class="px-2 py-2 text-xs text-ink-muted">관리</th>
          </tr></thead>
          <tbody>
            <tr v-for="p in posts" :key="p.id"
              class="border-b border-gray-50 last:border-0 hover:bg-amber-50/40 cursor-pointer transition-colors"
              :class="[p.is_hidden ? 'opacity-40 bg-red-50/30' : '', activePost?.id===p.id ? 'bg-amber-50 border-l-2 border-l-amber-500' : '']"
              @click="openPost(p)">
              <td class="px-2 py-2 text-xs text-ink-faint">{{ p.id }}</td>
              <td class="px-2 py-2 max-w-[200px]">
                <div class="truncate text-sm font-medium text-ink">
                  <span v-if="p.is_pinned" class="inline-flex align-middle text-amber-500 mr-1"><AppIcon name="bookmark" :size="12" :filled="true" /></span>
                  {{ p.title }}
                </div>
              </td>
              <td v-if="!activePost" class="px-2 py-2"><span class="badge-primary !text-[11px]">{{ p.board?.name || '-' }}</span></td>
              <td class="px-2 py-2">
                <button @click.stop="openUserModal(p.user)" class="text-xs text-blue-600 hover:underline">{{ p.user?.name }}</button>
              </td>
              <td class="px-2 py-2 text-center text-xs text-ink-muted">{{ p.comment_count }}</td>
              <td class="px-2 py-2 text-center text-xs text-ink-faint">{{ p.view_count }}</td>
              <td class="px-2 py-2 text-[11px] text-ink-faint">{{ p.created_at?.slice(5,10) }}</td>
              <td class="px-2 py-2 text-center space-x-1" @click.stop>
                <button @click="pinPost(p)" class="transition-colors" :class="p.is_pinned?'text-amber-500':'text-gray-300 hover:text-amber-500'" title="고정"><AppIcon name="bookmark" :size="14" :filled="p.is_pinned" /></button>
                <button @click="hidePost(p)" class="transition-colors" :class="p.is_hidden?'text-green-500':'text-gray-300 hover:text-red-500'" :title="p.is_hidden ? '보이기' : '숨기기'"><AppIcon :name="p.is_hidden ? 'eye' : 'x'" :size="14" /></button>
                <button @click="deletePost(p)" class="text-gray-300 hover:text-red-600 transition-colors" title="삭제"><AppIcon name="trash" :size="14" /></button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
        <button v-for="pg in Math.min(lastPage, 10)" :key="pg" @click="load(pg)"
          class="w-8 h-8 rounded-lg text-sm transition-colors" :class="pg===page?'bg-amber-400 text-white font-bold':'text-ink-muted hover:bg-gray-100'">{{ pg }}</button>
      </div>
    </div>

    <!-- 오른쪽: 인라인 게시글 뷰 -->
    <div v-if="activePost" class="w-1/2">
      <div class="card overflow-hidden sticky top-4">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between bg-amber-50">
          <span class="font-bold text-sm text-amber-700">게시글 상세</span>
          <button @click="activePost=null" class="text-ink-muted hover:text-ink transition-colors"><AppIcon name="x" :size="18" /></button>
        </div>
        <div class="px-4 py-3">
          <div class="flex items-center gap-2 mb-2">
            <span class="badge-primary !text-[11px]">{{ activePost.board?.name || '게시판' }}</span>
            <span v-if="activePost.is_pinned" class="badge-blue !text-[11px]">고정</span>
            <span v-if="activePost.is_hidden" class="badge-red !text-[11px]">숨김</span>
          </div>
          <h2 class="text-lg font-bold text-ink">{{ activePost.title }}</h2>
          <div class="flex items-center gap-3 mt-2 text-xs text-ink-faint">
            <button @click="openUserModal(activePost.user)" class="text-blue-600 hover:underline font-semibold">{{ activePost.user?.name }}</button>
            <span>{{ activePost.user?.email }}</span>
            <span>{{ activePost.created_at?.slice(0,10) }}</span>
            <span>{{ activePost.view_count }}회</span>
            <span>❤️ {{ activePost.like_count }}</span>
          </div>
        </div>
        <div class="px-4 py-4 border-t border-gray-100 text-sm text-ink-light leading-relaxed whitespace-pre-wrap max-h-[400px] overflow-y-auto">{{ activePost.content }}</div>

        <!-- 댓글 -->
        <div v-if="activePost.comments?.length" class="px-4 py-3 border-t border-gray-100">
          <div class="flex items-center gap-1.5 font-bold text-xs text-ink mb-2"><AppIcon name="message-circle" :size="13" />댓글 {{ activePost.comments.length }}개</div>
          <div v-for="c in activePost.comments" :key="c.id" class="py-2 border-b border-gray-50 last:border-0">
            <div class="flex items-center gap-2">
              <button @click="openUserModal(c.user)" class="text-xs text-blue-600 hover:underline font-semibold">{{ c.user?.name }}</button>
              <span class="text-[11px] text-ink-faint">{{ c.created_at?.slice(0,10) }}</span>
              <button @click="deleteComment(c.id)" class="text-[11px] text-red-400 hover:text-red-600 ml-auto transition-colors">삭제</button>
            </div>
            <div class="text-xs text-ink-light mt-0.5">{{ c.content }}</div>
          </div>
        </div>

        <!-- 관리 버튼 -->
        <div class="px-4 py-3 border-t border-gray-100 flex gap-2">
          <button @click="pinPost(activePost)" class="inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded-lg transition-colors" :class="activePost.is_pinned?'bg-amber-100 text-amber-700':'bg-gray-100 text-ink-light'">
            <AppIcon name="bookmark" :size="13" :filled="activePost.is_pinned" />{{ activePost.is_pinned ? '고정 해제' : '고정' }}
          </button>
          <button @click="hidePost(activePost)" class="inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded-lg transition-colors" :class="activePost.is_hidden?'bg-green-100 text-green-700':'bg-red-100 text-red-700'">
            <AppIcon :name="activePost.is_hidden ? 'eye' : 'x'" :size="13" />{{ activePost.is_hidden ? '보이기' : '숨기기' }}
          </button>
          <button @click="deletePost(activePost); activePost=null" class="inline-flex items-center gap-1 text-xs bg-red-100 text-red-700 px-3 py-1.5 rounded-lg hover:bg-red-200 transition-colors"><AppIcon name="trash" :size="13" />삭제</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══ 회원 상세 모달 ═══ -->
  <div v-if="userModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="userModal=null">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-amber-50 sticky top-0">
        <span class="flex items-center gap-2 font-bold text-ink"><span class="icon-chip w-7 h-7 bg-white text-amber-600"><AppIcon name="user" :size="15" /></span>회원 상세 정보</span>
        <button @click="userModal=null" class="text-ink-muted hover:text-ink transition-colors"><AppIcon name="x" :size="20" /></button>
      </div>

      <div v-if="userLoading" class="py-12 text-center text-ink-muted">로딩중...</div>
      <div v-else-if="userData" class="p-5">
        <!-- 탭 -->
        <div class="flex gap-1 mb-4 border-b border-gray-100">
          <button v-for="t in userTabs" :key="t.key" @click="userTab=t.key"
            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors"
            :class="userTab===t.key?'border-amber-500 text-amber-700':'border-transparent text-ink-muted hover:text-ink-light'">{{ t.label }}</button>
        </div>

        <!-- 기본 정보 (수정 가능) -->
        <div v-show="userTab==='info'">
          <div class="grid grid-cols-2 gap-3">
            <div><label class="input-label !text-xs !mb-0.5">이름</label><input v-model="userData.user.name" class="input-soft px-3 py-1.5" /></div>
            <div><label class="input-label !text-xs !mb-0.5">닉네임</label><input v-model="userData.user.nickname" class="input-soft px-3 py-1.5" /></div>
            <div><label class="input-label !text-xs !mb-0.5">이메일</label><input v-model="userData.user.email" class="input-soft px-3 py-1.5" /></div>
            <div><label class="input-label !text-xs !mb-0.5">전화번호</label><input v-model="userData.user.phone" class="input-soft px-3 py-1.5" /></div>
            <div><label class="input-label !text-xs !mb-0.5">도시</label><input v-model="userData.user.city" class="input-soft px-3 py-1.5" /></div>
            <div><label class="input-label !text-xs !mb-0.5">주</label><input v-model="userData.user.state" class="input-soft px-3 py-1.5" /></div>
            <div><label class="input-label !text-xs !mb-0.5">역할</label>
              <select v-model="userData.user.role" class="input-soft px-3 py-1.5">
                <option value="user">일반회원</option><option value="admin">관리자</option><option value="super_admin">슈퍼관리자</option>
              </select>
            </div>
            <div><label class="input-label !text-xs !mb-0.5">상태</label>
              <select v-model="userData.user.is_banned" class="input-soft px-3 py-1.5">
                <option :value="false">정상</option><option :value="true">정지</option>
              </select>
            </div>
            <div><label class="input-label !text-xs !mb-0.5">포인트</label><input v-model.number="userData.user.points" type="number" class="input-soft px-3 py-1.5" /></div>
            <div><label class="input-label !text-xs !mb-0.5">게임포인트</label><input v-model.number="userData.user.game_points" type="number" class="input-soft px-3 py-1.5" /></div>
          </div>
          <div class="mt-2"><label class="input-label !text-xs !mb-0.5">소개</label><textarea v-model="userData.user.bio" rows="2" class="input-soft px-3 py-1.5"></textarea></div>
          <div class="mt-3 flex items-center gap-3 text-xs text-ink-faint">
            <span>가입일: {{ userData.user.created_at?.slice(0,10) }}</span>
            <span>최근 로그인: {{ userData.user.last_login_at?.slice(0,10) || '없음' }}</span>
            <span>로그인 {{ userData.user.login_count || 0 }}회</span>
          </div>
          <button @click="saveUser" class="btn-primary mt-4 px-5 py-2">저장하기</button>
        </div>

        <!-- 결제 내역 -->
        <div v-show="userTab==='payments'">
          <div v-if="!userData.payments?.length" class="py-6 text-center text-ink-muted text-sm">결제 내역 없음</div>
          <table v-else class="w-full text-sm"><thead class="bg-gray-50"><tr>
            <th class="px-2 py-1.5 text-xs text-left text-ink-muted">날짜</th><th class="px-2 py-1.5 text-xs text-left text-ink-muted">금액</th><th class="px-2 py-1.5 text-xs text-left text-ink-muted">포인트</th><th class="px-2 py-1.5 text-xs text-left text-ink-muted">상태</th>
          </tr></thead><tbody>
            <tr v-for="pay in userData.payments" :key="pay.id" class="border-b border-gray-50"><td class="px-2 py-1.5 text-xs">{{ pay.created_at?.slice(0,10) }}</td><td class="px-2 py-1.5 text-xs">${{ pay.amount }}</td><td class="px-2 py-1.5 text-xs text-amber-600 font-bold">+{{ pay.points }}P</td><td class="px-2 py-1.5 text-xs">{{ pay.status }}</td></tr>
          </tbody></table>
        </div>

        <!-- 포인트 내역 -->
        <div v-show="userTab==='points'">
          <div v-if="!userData.points?.length" class="py-6 text-center text-ink-muted text-sm">포인트 내역 없음</div>
          <table v-else class="w-full text-sm"><thead class="bg-gray-50"><tr>
            <th class="px-2 py-1.5 text-xs text-left text-ink-muted">날짜</th><th class="px-2 py-1.5 text-xs text-left text-ink-muted">사유</th><th class="px-2 py-1.5 text-xs text-right text-ink-muted">포인트</th>
          </tr></thead><tbody>
            <tr v-for="pt in userData.points" :key="pt.id" class="border-b border-gray-50"><td class="px-2 py-1.5 text-xs">{{ pt.created_at?.slice(0,10) }}</td><td class="px-2 py-1.5 text-xs">{{ pt.reason }}</td><td class="px-2 py-1.5 text-xs text-right font-bold" :class="pt.amount>0?'text-green-600':'text-red-600'">{{ pt.amount>0?'+':'' }}{{ pt.amount }}P</td></tr>
          </tbody></table>
        </div>

        <!-- 게시글 -->
        <div v-show="userTab==='posts'">
          <div v-if="!userData.posts?.length" class="py-6 text-center text-ink-muted text-sm">작성한 글 없음</div>
          <div v-for="post in userData.posts" :key="post.id" class="py-2 border-b border-gray-50 flex items-center justify-between">
            <div><div class="text-sm font-medium text-ink">{{ post.title }}</div><div class="text-[11px] text-ink-faint">{{ post.created_at?.slice(0,10) }} · {{ post.view_count }}회</div></div>
            <button @click="openPost(post); userModal=null" class="text-xs text-amber-600 hover:underline">보기</button>
          </div>
        </div>

        <!-- 댓글 -->
        <div v-show="userTab==='comments'">
          <div v-if="!userData.comments?.length" class="py-6 text-center text-ink-muted text-sm">작성한 댓글 없음</div>
          <div v-for="c in userData.comments" :key="c.id" class="py-2 border-b border-gray-50">
            <div class="text-sm text-ink-light">{{ c.content }}</div>
            <div class="text-[11px] text-ink-faint mt-0.5">{{ c.created_at?.slice(0,10) }}</div>
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
import AppIcon from '../../components/AppIcon.vue'
const posts = ref([]); const boards = ref([]); const loading = ref(true)
const page = ref(1); const lastPage = ref(1); const totalPosts = ref(0)
const search = ref(''); const boardFilter = ref('')
const activePost = ref(null)

// 회원 모달
const userModal = ref(null)
const userData = ref(null)
const userLoading = ref(false)
const userTab = ref('info')
const userTabs = [
  { key: 'info', label: '기본정보' },
  { key: 'payments', label: '결제내역' },
  { key: 'points', label: '포인트' },
  { key: 'posts', label: '게시글' },
  { key: 'comments', label: '댓글' },
]

async function load(p=1) {
  loading.value=true; page.value=p
  const params = { page: p }
  if (search.value) params.search = search.value
  if (boardFilter.value) params.board_id = boardFilter.value
  try {
    const { data } = await axios.get('/api/admin/posts', { params })
    posts.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
    totalPosts.value = data.data?.total || 0
  } catch {}
  loading.value = false
}

async function openPost(p) {
  try {
    const { data } = await axios.get(`/api/admin/posts/${p.id}/detail`)
    activePost.value = data.data
  } catch { activePost.value = p }
}

async function openUserModal(user) {
  if (!user?.id) return
  userModal.value = user; userLoading.value = true; userData.value = null; userTab.value = 'info'
  try {
    const { data } = await axios.get(`/api/admin/users/${user.id}/detail`)
    userData.value = data.data
  } catch {}
  userLoading.value = false
}

async function saveUser() {
  if (!userData.value?.user) return
  try {
    await axios.put(`/api/admin/users/${userData.value.user.id}`, userData.value.user)
    alert('저장되었습니다!')
  } catch (e) { alert(e.response?.data?.message || '저장 실패') }
}

async function deleteComment(id) {
  if (!confirm('댓글 삭제?')) return
  try { await axios.delete(`/api/comments/${id}`); if (activePost.value?.comments) activePost.value.comments = activePost.value.comments.filter(c=>c.id!==id) } catch {}
}

async function pinPost(p) { try { await axios.post(`/api/admin/posts/${p.id}/pin`); p.is_pinned=!p.is_pinned } catch {} }
async function hidePost(p) { try { await axios.post(`/api/admin/posts/${p.id}/hide`); p.is_hidden=!p.is_hidden } catch {} }
async function deletePost(p) { if(!confirm('삭제?'))return; try { await axios.delete(`/api/admin/posts/${p.id}`); posts.value=posts.value.filter(x=>x.id!==p.id) } catch {} }

onMounted(async () => {
  try { const { data } = await axios.get('/api/admin/boards'); boards.value = data.data || [] } catch {}
  load()
})
</script>
