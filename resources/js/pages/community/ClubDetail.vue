<template>
  <div class="min-h-screen bg-gray-50 pb-16">
    <div class="max-w-[1200px] mx-auto px-4 py-6">
      <div v-if="loading" class="text-center py-20 text-gray-400">불러오는 중...</div>

      <template v-else-if="club">
        <!-- 클럽 헤더 배너 -->
        <div class="rounded-2xl overflow-hidden mb-4 shadow-sm">
          <div class="h-40 sm:h-52 relative"
            :style="{ background: `linear-gradient(135deg, ${club.gradient_from || '#6366F1'}, ${club.gradient_to || '#8B5CF6'})` }">
            <div class="absolute inset-0 bg-black/10"></div>
            <div class="absolute bottom-0 left-0 right-0 p-5 bg-gradient-to-t from-black/40 to-transparent">
              <div class="flex items-end gap-4">
                <div class="w-16 h-16 rounded-full border-4 border-white flex items-center justify-center text-2xl font-bold text-white shadow-lg flex-shrink-0"
                  :style="{ background: `linear-gradient(135deg, ${club.gradient_from || '#6366F1'}, ${club.gradient_to || '#8B5CF6'})` }">
                  {{ club.emoji || '👥' }}
                </div>
                <div class="text-white pb-1">
                  <h1 class="text-xl sm:text-2xl font-black">{{ club.name }}</h1>
                  <div class="flex items-center gap-2 text-sm text-white/80 mt-0.5">
                    <span class="bg-white/20 backdrop-blur-sm px-2 py-0.5 rounded-full text-xs">{{ club.category }}</span>
                    <span>👤 {{ club.member_count || members.length }}명</span>
                    <span v-if="club.is_approval" class="bg-white/20 backdrop-blur-sm px-2 py-0.5 rounded-full text-xs">🔒 승인제</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- 클럽 설명 + 가입 버튼 -->
          <div class="bg-white p-5">
            <div class="flex items-start justify-between gap-4">
              <p class="text-sm text-gray-600 leading-relaxed flex-1">{{ club.description }}</p>
              <div class="flex-shrink-0">
                <button v-if="!isMember" @click="joinClub"
                  class="bg-gradient-to-r from-pink-500 to-red-500 text-white px-5 py-2.5 rounded-lg text-sm font-bold hover:from-pink-600 hover:to-red-600 transition shadow-sm">
                  {{ club.is_approval ? '가입 신청' : '가입하기' }}
                </button>
                <button v-else-if="!isOwner" @click="leaveClub"
                  class="bg-gray-100 text-gray-600 px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-gray-200 transition">
                  탈퇴하기
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- 메인 콘텐츠: 게시판 + 사이드바 -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

          <!-- 왼쪽: 게시판 -->
          <div class="lg:col-span-2 space-y-4">

            <!-- 공지사항 -->
            <div v-if="notices.length" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
              <div class="px-5 py-3 border-b border-gray-100 bg-yellow-50/50">
                <h3 class="font-semibold text-gray-800 text-sm">📌 공지사항</h3>
              </div>
              <div v-for="post in notices" :key="post.id" class="px-5 py-3 border-b border-gray-50 last:border-0 hover:bg-gray-50 cursor-pointer"
                @click="openPost(post)">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <span class="text-xs bg-red-100 text-red-600 px-1.5 py-0.5 rounded font-medium">공지</span>
                    <span class="text-sm text-gray-800 font-medium">{{ post.title }}</span>
                  </div>
                  <span class="text-xs text-gray-400">{{ formatDate(post.created_at) }}</span>
                </div>
              </div>
            </div>

            <!-- 글쓰기 버튼 -->
            <div v-if="isMember" class="flex justify-end">
              <button @click="showWriteForm = !showWriteForm"
                class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700 transition flex items-center gap-1">
                ✏️ 글쓰기
              </button>
            </div>

            <!-- 글쓰기 폼 -->
            <div v-if="showWriteForm && isMember" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
              <h3 class="font-semibold text-gray-800 mb-3">새 글 작성</h3>
              <input v-model="postForm.title" type="text" placeholder="제목을 입력하세요"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm mb-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
              <textarea v-model="postForm.content" rows="4" placeholder="내용을 입력하세요"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
              <div class="flex justify-end gap-2">
                <button @click="showWriteForm = false"
                  class="px-4 py-2 rounded-lg text-sm text-gray-500 hover:bg-gray-100 transition">취소</button>
                <button @click="submitPost" :disabled="!postForm.title.trim()"
                  class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700 disabled:opacity-40 transition">등록</button>
              </div>
            </div>

            <!-- 게시글 목록 -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
              <div class="px-5 py-3 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800 text-sm">📝 게시판</h3>
              </div>
              <div v-if="posts.length">
                <div v-for="post in posts" :key="post.id"
                  class="px-5 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50 cursor-pointer transition"
                  @click="openPost(post)">
                  <div class="flex items-start justify-between mb-1">
                    <h4 class="text-sm font-medium text-gray-800">{{ post.title }}</h4>
                    <span class="text-xs text-gray-400 flex-shrink-0 ml-2">{{ formatDate(post.created_at) }}</span>
                  </div>
                  <p class="text-xs text-gray-500 line-clamp-2">{{ post.content }}</p>
                  <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
                    <span>{{ post.user?.name || '익명' }}</span>
                    <span>💬 {{ post.comment_count || 0 }}</span>
                    <span>👀 {{ post.view_count || 0 }}</span>
                  </div>
                </div>
              </div>
              <div v-else class="px-5 py-10 text-center text-gray-400 text-sm">
                아직 게시글이 없습니다.
              </div>
            </div>

            <!-- 글 상세 모달 -->
            <div v-if="selectedPost" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click="selectedPost = null">
              <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[80vh] overflow-y-auto" @click.stop>
                <div class="p-5 border-b border-gray-100">
                  <div class="flex items-start justify-between">
                    <div>
                      <span v-if="selectedPost.is_notice" class="text-xs bg-red-100 text-red-600 px-1.5 py-0.5 rounded font-medium mr-2">공지</span>
                      <h2 class="text-lg font-bold text-gray-900 inline">{{ selectedPost.title }}</h2>
                    </div>
                    <button @click="selectedPost = null" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                  </div>
                  <div class="flex items-center gap-2 mt-2 text-xs text-gray-400">
                    <span>{{ selectedPost.user?.name }}</span>
                    <span>{{ formatDate(selectedPost.created_at) }}</span>
                  </div>
                </div>
                <div class="p-5">
                  <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{{ selectedPost.content }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- 오른쪽 사이드바: 회원 목록 -->
          <div class="space-y-4">
            <!-- 회원 목록 -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
              <div class="px-5 py-3 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800 text-sm">👥 회원 ({{ members.length }}명)</h3>
              </div>
              <div class="max-h-[400px] overflow-y-auto">
                <div v-for="member in members" :key="member.id"
                  class="px-5 py-3 border-b border-gray-50 last:border-0 flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-600">
                      {{ (member.user?.name || member.name || '?')[0] }}
                    </div>
                    <span class="text-sm text-gray-700">{{ member.user?.name || member.name }}</span>
                  </div>
                  <span :class="roleBadgeClass(member.role)">{{ roleName(member.role) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 관리자 패널 -->
        <div v-if="isAdmin" class="mt-6">
          <button @click="showAdmin = !showAdmin"
            class="w-full bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-4 flex items-center justify-between hover:bg-gray-50 transition">
            <span class="font-semibold text-gray-800">⚙️ 동호회 관리</span>
            <span class="text-gray-400 text-lg transition-transform" :class="{ 'rotate-180': showAdmin }">▼</span>
          </button>

          <div v-if="showAdmin" class="mt-2 space-y-4">
            <!-- 동호회 정보 수정 -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
              <h4 class="font-semibold text-gray-800 mb-3">동호회 정보 수정</h4>
              <div class="space-y-3">
                <div>
                  <label class="text-xs text-gray-500 mb-1 block">이름</label>
                  <input v-model="editForm.name" type="text"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
                <div>
                  <label class="text-xs text-gray-500 mb-1 block">설명</label>
                  <textarea v-model="editForm.description" rows="3"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                </div>
                <div>
                  <label class="text-xs text-gray-500 mb-1 block">카테고리</label>
                  <select v-model="editForm.category"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                  </select>
                </div>
                <button @click="updateClub"
                  class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700 transition">저장</button>
              </div>
            </div>

            <!-- 회원 관리 -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
              <h4 class="font-semibold text-gray-800 mb-3">회원 관리</h4>
              <div class="space-y-2">
                <div v-for="member in members" :key="member.id"
                  class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                  <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-600">
                      {{ (member.user?.name || member.name || '?')[0] }}
                    </div>
                    <span class="text-sm text-gray-700">{{ member.user?.name || member.name }}</span>
                  </div>
                  <div v-if="member.role !== 'owner'" class="flex items-center gap-2">
                    <select :value="member.role" @change="changeMemberRole(member, $event.target.value)"
                      class="border border-gray-200 rounded-lg px-2 py-1 text-xs bg-white focus:outline-none">
                      <option value="member">회원</option>
                      <option value="admin">운영자</option>
                    </select>
                    <button @click="kickMember(member)"
                      class="text-red-500 hover:text-red-700 text-xs font-medium px-2 py-1 hover:bg-red-50 rounded transition">
                      강퇴
                    </button>
                  </div>
                  <span v-else class="text-xs text-blue-600 font-medium">방장</span>
                </div>
              </div>
            </div>

            <!-- 가입 대기 (승인제) -->
            <div v-if="club.is_approval && pendingRequests.length" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
              <h4 class="font-semibold text-gray-800 mb-3">가입 대기 ({{ pendingRequests.length }}명)</h4>
              <div class="space-y-2">
                <div v-for="req in pendingRequests" :key="req.id"
                  class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                  <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-600">
                      {{ (req.user?.name || '?')[0] }}
                    </div>
                    <div>
                      <span class="text-sm text-gray-700">{{ req.user?.name }}</span>
                      <p v-if="req.message" class="text-xs text-gray-400">{{ req.message }}</p>
                    </div>
                  </div>
                  <div class="flex gap-1">
                    <button @click="handleRequest(req, 'approve')"
                      class="bg-green-500 text-white px-3 py-1 rounded-lg text-xs font-medium hover:bg-green-600 transition">승인</button>
                    <button @click="handleRequest(req, 'reject')"
                      class="bg-gray-200 text-gray-600 px-3 py-1 rounded-lg text-xs font-medium hover:bg-gray-300 transition">거절</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- 공지 작성 -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
              <h4 class="font-semibold text-gray-800 mb-3">📌 공지 작성</h4>
              <input v-model="noticeForm.title" type="text" placeholder="공지 제목"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm mb-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
              <textarea v-model="noticeForm.content" rows="3" placeholder="공지 내용"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
              <button @click="submitNotice" :disabled="!noticeForm.title.trim()"
                class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-red-700 disabled:opacity-40 transition">공지 등록</button>
            </div>

            <!-- 동호회 삭제 -->
            <div class="bg-white rounded-xl shadow-sm border border-red-200 p-5">
              <h4 class="font-semibold text-red-600 mb-2">동호회 삭제</h4>
              <p class="text-xs text-gray-500 mb-3">동호회를 삭제하면 모든 데이터가 영구적으로 삭제됩니다. 이 작업은 되돌릴 수 없습니다.</p>
              <button @click="deleteClub"
                class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-red-700 transition">동호회 삭제</button>
            </div>
          </div>
        </div>

        <router-link to="/clubs" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-blue-600 transition mt-6 block">
          ← 동호회 목록으로
        </router-link>
      </template>

      <div v-else class="text-center py-20">
        <p class="text-4xl mb-3">😢</p>
        <p class="text-gray-400">동호회를 찾을 수 없습니다.</p>
        <router-link to="/clubs" class="mt-3 inline-block text-blue-600 text-sm hover:underline">목록으로 돌아가기</router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import axios from 'axios';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const loading = ref(true);
const club = ref(null);
const members = ref([]);
const posts = ref([]);
const notices = ref([]);
const pendingRequests = ref([]);
const showAdmin = ref(false);
const showWriteForm = ref(false);
const selectedPost = ref(null);

const postForm = ref({ title: '', content: '' });
const noticeForm = ref({ title: '', content: '' });
const editForm = ref({ name: '', description: '', category: '' });

const categories = ['스포츠', '음식/요리', '육아/교육', '취미/여가', '종교', '비즈니스', '기타'];

const isMember = computed(() => {
  if (!authStore.user) return false;
  return members.value.some(m => (m.user_id || m.user?.id) === authStore.user.id);
});

const isOwner = computed(() => {
  if (!authStore.user || !club.value) return false;
  return club.value.user_id === authStore.user.id;
});

const isAdmin = computed(() => {
  if (!authStore.user) return false;
  if (isOwner.value) return true;
  const me = members.value.find(m => (m.user_id || m.user?.id) === authStore.user.id);
  return me?.role === 'admin' || me?.role === 'owner';
});

function roleName(role) {
  const map = { owner: '방장', admin: '운영자', member: '회원' };
  return map[role] || '회원';
}

function roleBadgeClass(role) {
  const base = 'text-[10px] px-2 py-0.5 rounded-full font-medium';
  if (role === 'owner') return `${base} bg-blue-100 text-blue-700`;
  if (role === 'admin') return `${base} bg-purple-100 text-purple-700`;
  return `${base} bg-gray-100 text-gray-500`;
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return `${d.getFullYear()}.${String(d.getMonth() + 1).padStart(2, '0')}.${String(d.getDate()).padStart(2, '0')}`;
}

function openPost(post) {
  selectedPost.value = post;
}

async function joinClub() {
  if (!authStore.isLoggedIn) {
    alert('로그인이 필요합니다.');
    return;
  }
  try {
    await axios.post(`/api/clubs/${club.value.id}/join`);
    await fetchClub();
    alert(club.value.is_approval ? '가입 신청이 완료되었습니다. 승인을 기다려주세요.' : '가입되었습니다!');
  } catch (e) {
    alert(e.response?.data?.message || '오류가 발생했습니다.');
  }
}

async function leaveClub() {
  if (!confirm('정말로 동호회를 탈퇴하시겠습니까?')) return;
  try {
    await axios.post(`/api/clubs/${club.value.id}/leave`);
    await fetchClub();
  } catch (e) {
    alert(e.response?.data?.message || '오류가 발생했습니다.');
  }
}

async function submitPost() {
  try {
    await axios.post(`/api/clubs/${club.value.id}/posts`, postForm.value);
    postForm.value = { title: '', content: '' };
    showWriteForm.value = false;
    await fetchPosts();
  } catch (e) {
    alert(e.response?.data?.message || '오류가 발생했습니다.');
  }
}

async function submitNotice() {
  try {
    await axios.post(`/api/clubs/${club.value.id}/posts`, { ...noticeForm.value, is_notice: true });
    noticeForm.value = { title: '', content: '' };
    await fetchPosts();
  } catch (e) {
    alert(e.response?.data?.message || '오류가 발생했습니다.');
  }
}

async function updateClub() {
  try {
    await axios.put(`/api/clubs/${club.value.id}`, editForm.value);
    await fetchClub();
    alert('저장되었습니다.');
  } catch (e) {
    alert(e.response?.data?.message || '오류가 발생했습니다.');
  }
}

async function changeMemberRole(member, newRole) {
  try {
    await axios.put(`/api/clubs/${club.value.id}/members/${member.id}`, { role: newRole });
    await fetchMembers();
  } catch (e) {
    alert(e.response?.data?.message || '오류가 발생했습니다.');
  }
}

async function kickMember(member) {
  const name = member.user?.name || member.name;
  if (!confirm(`${name}님을 강퇴하시겠습니까?`)) return;
  try {
    await axios.delete(`/api/clubs/${club.value.id}/members/${member.id}`);
    await fetchMembers();
  } catch (e) {
    alert(e.response?.data?.message || '오류가 발생했습니다.');
  }
}

async function handleRequest(req, action) {
  try {
    await axios.post(`/api/clubs/${club.value.id}/requests/${req.id}`, { action });
    pendingRequests.value = pendingRequests.value.filter(r => r.id !== req.id);
    if (action === 'approve') await fetchMembers();
  } catch (e) {
    alert(e.response?.data?.message || '오류가 발생했습니다.');
  }
}

async function deleteClub() {
  if (!confirm('정말로 동호회를 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.')) return;
  if (!confirm('모든 게시글과 회원 데이터가 삭제됩니다. 계속하시겠습니까?')) return;
  try {
    await axios.delete(`/api/clubs/${club.value.id}`);
    router.push('/clubs');
  } catch (e) {
    alert(e.response?.data?.message || '오류가 발생했습니다.');
  }
}

async function fetchClub() {
  const { data } = await axios.get(`/api/clubs/${route.params.id}`);
  club.value = data;
  editForm.value = { name: data.name, description: data.description, category: data.category };
}

async function fetchMembers() {
  try {
    const { data } = await axios.get(`/api/clubs/${route.params.id}/members`);
    members.value = data;
  } catch {}
}

async function fetchPosts() {
  try {
    const { data } = await axios.get(`/api/clubs/${route.params.id}/posts`);
    notices.value = (data || []).filter(p => p.is_notice);
    posts.value = (data || []).filter(p => !p.is_notice);
  } catch {}
}

async function fetchPendingRequests() {
  try {
    const { data } = await axios.get(`/api/clubs/${route.params.id}/requests`);
    pendingRequests.value = data || [];
  } catch {}
}

onMounted(async () => {
  try {
    await fetchClub();
    await Promise.all([fetchMembers(), fetchPosts()]);
    if (isAdmin.value && club.value?.is_approval) {
      await fetchPendingRequests();
    }
  } catch {}
  loading.value = false;
});
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
