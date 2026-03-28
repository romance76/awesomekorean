<template>
  <div class="max-w-[1200px] mx-auto px-4 py-6">
    <h1 class="text-xl font-bold text-gray-800 mb-5">{{ editId ? '게시글 수정' : '글쓰기' }}</h1>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
      <div class="space-y-5">
        <!-- 게시판 선택 (눈에 띄게) -->
        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
          <label class="block text-sm font-bold text-gray-700 mb-2">게시판 선택 *</label>
          <select v-model="form.board_id" class="w-full border border-gray-300 rounded-lg px-3 py-3 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-red-500 bg-white">
            <option value="">게시판을 선택하세요</option>
            <option v-for="b in boards" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
        </div>

        <!-- 제목 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">제목 *</label>
          <input v-model="form.title" type="text" placeholder="제목을 입력하세요" maxlength="200"
            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
        </div>

        <!-- 내용 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">내용 *</label>
          <textarea v-model="form.content" rows="12" placeholder="내용을 입력하세요"
            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 resize-none"></textarea>
        </div>

        <!-- 사진 업로드 (최대 3장) -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">사진 첨부 (최대 3장)</label>
          <div class="grid grid-cols-4 sm:grid-cols-5 lg:grid-cols-6 gap-2">
            <div v-for="i in 3" :key="i" class="relative">
              <div v-if="photos[i-1]" class="h-24 rounded-xl overflow-hidden relative group">
                <img :src="photos[i-1].preview" class="w-full h-full object-cover" />
                <button @click="removePhoto(i-1)" class="absolute top-1 right-1 w-6 h-6 bg-red-500 text-white rounded-full text-xs opacity-0 group-hover:opacity-100 transition-opacity">✕</button>
              </div>
              <label v-else class="h-24 rounded-xl border-2 border-dashed border-gray-300 flex flex-col items-center justify-center cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition-colors">
                <span class="text-2xl text-gray-400">+</span>
                <span class="text-xs text-gray-400 mt-1">사진 {{ i }}</span>
                <input type="file" accept="image/*" class="hidden" @change="addPhoto($event, i-1)" />
              </label>
            </div>
          </div>
        </div>

        <!-- 옵션 -->
        <div class="flex flex-col gap-3">
          <!-- 익명 -->
          <div class="flex items-center space-x-2">
            <input v-model="form.is_anonymous" type="checkbox" id="anon" class="rounded text-red-600 w-4 h-4" />
            <label for="anon" class="text-sm text-gray-600">익명으로 작성</label>
          </div>

          <!-- 질문하기 -->
          <div class="flex items-center space-x-2">
            <input v-model="form.is_question" type="checkbox" id="question" class="rounded text-blue-600 w-4 h-4" />
            <label for="question" class="text-sm text-gray-600">질문하기 (Q&A)</label>
          </div>
        </div>

        <!-- 포인트 보상 (질문일 때만) -->
        <div v-if="form.is_question" class="bg-blue-50 border border-blue-100 rounded-xl p-4">
          <label class="block text-sm font-bold text-blue-700 mb-2">채택 시 지급 포인트</label>
          <p class="text-xs text-blue-500 mb-3">답변이 채택되면 답변자에게 해당 포인트가 지급됩니다.</p>
          <div class="flex items-center gap-3">
            <div class="relative flex-1 max-w-[200px]">
              <input v-model="form.reward_points" type="number" min="0" max="1000" step="10" placeholder="0"
                class="w-full border border-blue-200 rounded-lg px-3 py-2.5 text-sm font-bold text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white" />
              <span class="absolute right-3 top-1/2 -translate-y-1/2 text-blue-500 text-sm font-medium">P</span>
            </div>
            <div class="flex gap-2">
              <button type="button" @click="form.reward_points = 10" class="px-3 py-1.5 text-xs rounded-lg border border-blue-200 text-blue-600 hover:bg-blue-100">10P</button>
              <button type="button" @click="form.reward_points = 50" class="px-3 py-1.5 text-xs rounded-lg border border-blue-200 text-blue-600 hover:bg-blue-100">50P</button>
              <button type="button" @click="form.reward_points = 100" class="px-3 py-1.5 text-xs rounded-lg border border-blue-200 text-blue-600 hover:bg-blue-100">100P</button>
            </div>
          </div>
        </div>

        <!-- 에러 -->
        <div v-if="error" class="text-red-600 text-sm bg-red-50 p-3 rounded-lg">{{ error }}</div>

        <!-- 버튼 -->
        <div class="flex justify-end space-x-3">
          <button @click="$router.back()" class="px-5 py-2.5 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50">취소</button>
          <button @click="submit" :disabled="loading" class="px-5 py-2.5 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50">
            {{ loading ? '저장 중...' : (editId ? '수정 완료' : '등록') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const router = useRouter();
const editId = ref(route.query.edit || null);
const boards = ref([]);
const loading = ref(false);
const error = ref('');

const form = ref({
  board_id: route.query.board_id || '',
  title: '', content: '', is_anonymous: false,
  is_question: false, reward_points: 0
});

// Photos (max 3)
const photos = reactive([]);
function addPhoto(event, index) {
  const file = event.target.files[0];
  if (!file) return;
  photos[index] = { file, preview: URL.createObjectURL(file) };
}
function removePhoto(index) {
  if (photos[index]?.preview) URL.revokeObjectURL(photos[index].preview);
  photos[index] = null;
}

async function submit() {
  if (!form.value.board_id) { error.value = '게시판을 선택하세요.'; return; }
  if (!form.value.title.trim()) { error.value = '제목을 입력하세요.'; return; }
  if (!form.value.content.trim()) { error.value = '내용을 입력하세요.'; return; }
  loading.value = true; error.value = '';
  try {
    const fd = new FormData();
    fd.append('board_id', form.value.board_id);
    fd.append('title', form.value.title);
    fd.append('content', form.value.content);
    fd.append('is_anonymous', form.value.is_anonymous ? '1' : '0');
    fd.append('is_question', form.value.is_question ? '1' : '0');
    if (form.value.is_question && form.value.reward_points > 0) {
      fd.append('reward_points', form.value.reward_points);
    }
    // Append photos
    photos.forEach((photo) => {
      if (photo?.file) fd.append('photos[]', photo.file);
    });

    if (editId.value) {
      fd.append('_method', 'PUT');
      await axios.post(`/api/posts/${editId.value}`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      router.push(`/community/post/${editId.value}`);
    } else {
      const { data } = await axios.post('/api/posts', fd, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      router.push(`/community/post/${data.post.id}`);
    }
  } catch(e) {
    error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || '오류가 발생했습니다.';
  } finally { loading.value = false; }
}

onMounted(async () => {
  const { data } = await axios.get('/api/boards');
  boards.value = data;
  if (editId.value) {
    const { data: post } = await axios.get(`/api/posts/${editId.value}`);
    form.value.board_id = post.board_id;
    form.value.title = post.title;
    form.value.content = post.content;
    form.value.is_question = !!post.is_question;
    form.value.reward_points = post.reward_points || 0;
  }
});
</script>
