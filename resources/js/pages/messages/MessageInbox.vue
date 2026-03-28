<template>
  <div class="min-h-screen bg-gray-50 pb-16">
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-6 rounded-2xl">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-xl font-black">💬 쪽지함</h1>
            <p class="text-blue-100 text-sm mt-0.5">받은 쪽지와 보낸 쪽지</p>
          </div>
          <button @click="showCompose = true" class="bg-white text-blue-600 px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-50">+ 쪽지 쓰기</button>
        </div>
      </div>
    </div>
    <div class="max-w-[1200px] mx-auto px-4 py-4">

    <!-- 쪽지 작성 모달 -->
    <div v-if="showCompose" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4">
      <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md">
        <h3 class="font-bold text-gray-800 mb-4">쪽지 쓰기</h3>
        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">받는 사람 (username)</label>
            <input v-model="composeForm.username" type="text" placeholder="username 입력"
              class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">내용</label>
            <textarea v-model="composeForm.content" rows="5" placeholder="쪽지 내용"
              class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 resize-none"></textarea>
          </div>
          <div v-if="composeError" class="text-red-600 text-sm">{{ composeError }}</div>
          <div class="flex justify-end space-x-2">
            <button @click="showCompose = false; composeError = ''" class="px-4 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50">취소</button>
            <button @click="sendMessage" :disabled="sending" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50">
              {{ sending ? '전송 중...' : '전송' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 쪽지 목록 -->
    <div v-if="loading" class="text-center py-16 text-gray-400">불러오는 중...</div>
    <div v-else-if="messages.length === 0" class="text-center py-16 text-gray-400">
      <p class="text-4xl mb-3">📭</p>
      <p>받은 쪽지가 없습니다.</p>
    </div>
    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div @click="viewMessage(msg)" v-for="msg in messages" :key="msg.id"
        :class="['px-5 py-4 border-b border-gray-50 last:border-0 cursor-pointer hover:bg-gray-50 transition', !msg.is_read && 'bg-red-50']">
        <div class="flex items-start justify-between">
          <div class="flex items-start space-x-3 flex-1 min-w-0">
            <div class="w-9 h-9 bg-red-100 rounded-full flex items-center justify-center text-red-600 font-bold flex-shrink-0">
              {{ msg.sender?.name?.[0] }}
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center space-x-2">
                <span class="font-medium text-gray-800 text-sm">{{ msg.sender?.name }}</span>
                <span v-if="!msg.is_read" class="w-2 h-2 bg-red-500 rounded-full"></span>
              </div>
              <p class="text-sm text-gray-600 truncate mt-0.5">{{ msg.content }}</p>
            </div>
          </div>
          <span class="text-xs text-gray-400 flex-shrink-0 ml-3">{{ formatDate(msg.created_at) }}</span>
        </div>
      </div>
    </div>

    <!-- 쪽지 상세 모달 -->
    <div v-if="selectedMsg" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4">
      <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-gray-800">쪽지 상세</h3>
          <button @click="selectedMsg = null" class="text-gray-400 hover:text-gray-600 text-xl">×</button>
        </div>
        <div class="flex items-center space-x-2 mb-4">
          <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center text-red-600 font-bold text-sm">
            {{ selectedMsg.sender?.name?.[0] }}
          </div>
          <div>
            <p class="font-medium text-gray-800 text-sm">{{ selectedMsg.sender?.name }}</p>
            <p class="text-xs text-gray-400">{{ formatDate(selectedMsg.created_at) }}</p>
          </div>
        </div>
        <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-700 whitespace-pre-wrap leading-relaxed mb-4">{{ selectedMsg.content }}</div>
        <div class="flex justify-end">
          <button @click="selectedMsg = null" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm hover:bg-gray-200">닫기</button>
        </div>
      </div>
    </div>
    </div><!-- /max-w-[1200px] -->
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const messages = ref([]);
const loading = ref(true);
const showCompose = ref(false);
const sending = ref(false);
const composeError = ref('');
const selectedMsg = ref(null);
const composeForm = ref({ username: '', content: '' });

async function sendMessage() {
  if (!composeForm.value.username.trim() || !composeForm.value.content.trim()) {
    composeError.value = '받는 사람과 내용을 입력하세요.'; return;
  }
  sending.value = true; composeError.value = '';
  try {
    // username으로 user_id 찾기 (프로필 API 활용)
    const { data: profile } = await axios.get(`/api/profile/${composeForm.value.username}`);
    await axios.post('/api/messages', { receiver_id: profile.id, content: composeForm.value.content });
    showCompose.value = false;
    composeForm.value = { username: '', content: '' };
    alert('쪽지를 보냈습니다.');
  } catch(e) {
    composeError.value = e.response?.data?.message || '오류가 발생했습니다.';
  } finally { sending.value = false; }
}

async function viewMessage(msg) {
  const { data } = await axios.get(`/api/messages/${msg.id}`);
  selectedMsg.value = data;
  msg.is_read = true;
}

function formatDate(d) {
  const date = new Date(d);
  const now = new Date();
  const diff = (now - date) / 1000;
  if (diff < 86400) return `${Math.floor(diff/3600)}시간 전`;
  return date.toLocaleDateString('ko-KR');
}

onMounted(async () => {
  const { data } = await axios.get('/api/messages/inbox');
  messages.value = data.data;
  loading.value = false;
});
</script>
