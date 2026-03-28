<template>
  <div class="max-w-[1200px] mx-auto px-4 py-6">
    <div v-if="loading" class="text-center py-20 text-gray-400">불러오는 중...</div>
    <template v-else-if="profile">
      <!-- 프로필 헤더 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
        <div class="flex items-center space-x-4">
          <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
            <img v-if="profile.avatar" :src="profile.avatar" :alt="profile.name" class="w-full h-full object-cover rounded-full" />
            <span v-else class="text-2xl font-bold text-red-600">{{ profile.name?.[0] }}</span>
          </div>
          <div class="flex-1">
            <div class="flex items-center space-x-2 mb-1">
              <h1 class="text-lg font-bold text-gray-900">{{ profile.name }}</h1>
              <span class="text-xs bg-red-50 text-red-600 px-2 py-0.5 rounded font-medium">{{ profile.level }}</span>
            </div>
            <p class="text-sm text-gray-500">@{{ profile.username }}</p>
            <p v-if="profile.region" class="text-xs text-gray-400 mt-1">📍 {{ profile.region }}</p>
          </div>
          <div v-if="isMyProfile" class="flex-shrink-0">
            <button @click="showEdit = !showEdit" class="text-sm text-red-600 border border-red-200 px-3 py-1.5 rounded-lg hover:bg-red-50">
              프로필 수정
            </button>
          </div>
        </div>
        <p v-if="profile.bio" class="text-sm text-gray-600 mt-3 leading-relaxed">{{ profile.bio }}</p>
        <div class="flex space-x-6 mt-4 pt-4 border-t border-gray-100 text-center">
          <div><p class="font-bold text-gray-800">{{ profile.post_count }}</p><p class="text-xs text-gray-400">게시글</p></div>
          <div><p class="font-bold text-gray-800">{{ profile.comment_count }}</p><p class="text-xs text-gray-400">댓글</p></div>
          <div><p class="font-bold text-gray-800">{{ profile.points?.toLocaleString() }}</p><p class="text-xs text-gray-400">포인트</p></div>
        </div>
      </div>

      <!-- 프로필 수정 폼 (본인만) -->
      <div v-if="showEdit && isMyProfile" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
        <h2 class="font-semibold text-gray-800 mb-4">프로필 수정</h2>
        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">이름</label>
            <input v-model="editForm.name" type="text" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">자기소개</label>
            <textarea v-model="editForm.bio" rows="3" maxlength="300"
              class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 resize-none"></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">지역</label>
            <select v-model="editForm.region" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
              <option value="">선택</option>
              <option>Atlanta</option><option>New York</option><option>Los Angeles</option>
              <option>Dallas</option><option>Chicago</option>
            </select>
          </div>
          <div v-if="editError" class="text-red-600 text-sm">{{ editError }}</div>
          <div class="flex justify-end space-x-2">
            <button @click="showEdit = false" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">취소</button>
            <button @click="saveProfile" :disabled="saving" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50">
              {{ saving ? '저장 중...' : '저장' }}
            </button>
          </div>
        </div>
      </div>

      <!-- 최근 게시글 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
          <h2 class="font-semibold text-gray-800">최근 게시글</h2>
        </div>
        <div v-if="posts.length === 0" class="text-center py-8 text-gray-400 text-sm">게시글이 없습니다.</div>
        <ul v-else>
          <li v-for="post in posts" :key="post.id" class="px-5 py-3 border-b border-gray-50 last:border-0">
            <router-link :to="`/community/post/${post.id}`" class="flex items-center justify-between hover:text-red-600">
              <span class="text-sm text-gray-800 truncate flex-1">{{ post.title }}</span>
              <span class="text-xs text-gray-400 ml-3 flex-shrink-0">{{ formatDate(post.created_at) }}</span>
            </router-link>
          </li>
        </ul>
      </div>
    </template>
    <div v-else class="text-center py-20 text-gray-400">사용자를 찾을 수 없습니다.</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import axios from 'axios';

const route = useRoute();
const authStore = useAuthStore();
const profile = ref(null);
const posts = ref([]);
const loading = ref(true);
const showEdit = ref(false);
const saving = ref(false);
const editError = ref('');
const editForm = ref({ name: '', bio: '', region: '' });

const isMyProfile = computed(() =>
  authStore.user && (authStore.user.username === route.params.username)
);

async function saveProfile() {
  saving.value = true; editError.value = '';
  try {
    const { data } = await axios.put('/api/profile', editForm.value);
    profile.value.name = data.user.name;
    profile.value.bio = data.user.bio;
    profile.value.region = data.user.region;
    showEdit.value = false;
    await authStore.fetchMe();
  } catch(e) {
    editError.value = e.response?.data?.message || '오류가 발생했습니다.';
  } finally { saving.value = false; }
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('ko-KR');
}

onMounted(async () => {
  try {
    const [profileRes, postsRes] = await Promise.all([
      axios.get(`/api/profile/${route.params.username}`),
      isMyProfile.value ? axios.get('/api/profile/me/posts') : Promise.resolve({ data: { data: [] } }),
    ]);
    profile.value = profileRes.data;
    posts.value = postsRes.data.data || [];
    editForm.value = { name: profile.value.name, bio: profile.value.bio || '', region: profile.value.region || '' };
  } catch { profile.value = null; }
  loading.value = false;
});
</script>
