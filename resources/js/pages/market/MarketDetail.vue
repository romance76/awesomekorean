<template>
  <div class="max-w-[1200px] mx-auto px-4 py-6">
    <div v-if="loading" class="text-center py-20 text-gray-400">불러오는 중...</div>
    <template v-else-if="item">
      <!-- 상단 네비게이션 -->
      <div class="flex items-center justify-between mb-4">
        <router-link to="/market" class="flex items-center gap-1 text-sm text-gray-500 hover:text-red-600">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          뒤로
        </router-link>
        <div class="flex items-center gap-2">
          <button @click="shareItem" class="flex items-center gap-1 text-sm text-gray-500 hover:text-red-600 px-3 py-1.5 rounded-lg border border-gray-200 hover:border-red-200 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
            공유
          </button>
          <button @click="toggleBookmark" :class="['flex items-center gap-1 text-sm px-3 py-1.5 rounded-lg border transition', item.is_bookmarked ? 'text-yellow-600 border-yellow-300 bg-yellow-50' : 'text-gray-500 border-gray-200 hover:border-yellow-300']">
            <svg class="w-4 h-4" :fill="item.is_bookmarked ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
            북마크
          </button>
        </div>
      </div>

      <!-- 이미지 갤러리 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4 relative">
        <!-- SOLD 배지 -->
        <div v-if="item.status === 'sold'" class="absolute top-4 left-4 z-10 bg-gray-900/80 text-white px-4 py-2 rounded-lg font-bold text-lg">
          판매완료
        </div>
        <!-- 메인 이미지 -->
        <div class="aspect-video bg-gray-100 flex items-center justify-center cursor-pointer" @click="item.images?.length > 0 && openGallery(selectedImageIndex)">
          <img v-if="item.images?.[selectedImageIndex]" :src="item.images[selectedImageIndex]" :alt="item.title" class="w-full h-full object-contain" />
          <span v-else class="text-6xl">📦</span>
        </div>
        <!-- 썸네일 -->
        <div v-if="item.images?.length > 1" class="flex gap-2 p-3 overflow-x-auto">
          <div v-for="(img, idx) in item.images" :key="idx"
            @click="selectedImageIndex = idx"
            :class="['w-16 h-16 rounded-lg overflow-hidden flex-shrink-0 cursor-pointer border-2 transition',
              idx === selectedImageIndex ? 'border-red-500' : 'border-transparent hover:border-gray-300']">
            <img :src="img" class="w-full h-full object-cover" />
          </div>
        </div>
      </div>

      <!-- 상품 정보 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
        <div class="flex items-start justify-between mb-3">
          <div class="flex items-center gap-2">
            <span class="text-xs text-gray-400 bg-gray-100 px-2 py-1 rounded">{{ item.category }}</span>
            <span v-if="item.condition" class="text-xs text-blue-600 bg-blue-50 px-2 py-1 rounded">{{ item.condition }}</span>
            <span v-if="item.status === 'sold'" class="text-xs text-white bg-gray-600 px-2 py-1 rounded font-medium">판매완료</span>
            <span v-else-if="item.status === 'reserved'" class="text-xs text-orange-600 bg-orange-50 px-2 py-1 rounded font-medium">예약중</span>
          </div>
          <div v-if="canEdit" class="flex space-x-2">
            <router-link :to="`/market/write?edit=${item.id}`" class="text-xs text-gray-400 hover:text-gray-600">수정</router-link>
            <button @click="deleteItem" class="text-xs text-red-400 hover:text-red-600">삭제</button>
          </div>
        </div>
        <h1 class="text-xl font-bold text-gray-900 mb-2">{{ item.title }}</h1>
        <p class="text-2xl font-bold text-red-600 mb-4">
          <template v-if="Number(item.price) === 0">무료 나눔</template>
          <template v-else>${{ Number(item.price).toLocaleString() }}</template>
          <span v-if="item.price_negotiable" class="text-sm text-gray-400 font-normal ml-2">가격 협의 가능</span>
        </p>
        <div class="flex items-center gap-4 text-xs text-gray-500 mb-4">
          <span>👁 조회 {{ item.view_count || 0 }}</span>
          <span>❤️ 관심 {{ item.like_count || 0 }}</span>
          <span>{{ formatDate(item.created_at) }}</span>
        </div>
        <div class="prose prose-sm max-w-none text-gray-700 whitespace-pre-wrap leading-relaxed">{{ item.description }}</div>
      </div>

      <!-- 판매자 정보 카드 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">판매자 정보</h3>
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center text-red-600 font-bold text-xl flex-shrink-0">
            {{ item.user?.name?.[0] || '?' }}
          </div>
          <div class="flex-1">
            <p class="font-semibold text-gray-900">{{ item.user?.name }}</p>
            <div class="flex items-center gap-3 mt-1 text-xs text-gray-500">
              <span>📍 {{ item.region || '지역 미상' }}</span>
              <span v-if="item.user?.rating" class="flex items-center gap-0.5">
                <span class="text-yellow-500">★</span> {{ item.user.rating }}
              </span>
              <span v-if="item.user?.market_count != null">판매 {{ item.user.market_count }}건</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 지도 -->
      <div v-if="item.latitude && item.longitude" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
        <div class="px-5 py-3 border-b border-gray-100">
          <h3 class="font-semibold text-gray-800 text-sm">📍 거래 희망 위치</h3>
        </div>
        <iframe
          :src="`https://maps.google.com/maps?q=${item.latitude},${item.longitude}&output=embed`"
          class="w-full h-[300px] border-0"
          allowfullscreen
          loading="lazy"
        ></iframe>
        <div v-if="item.address" class="px-5 py-3 text-sm text-gray-600">{{ item.address }}</div>
      </div>

      <!-- 좋아요 / 연락하기 버튼 -->
      <div class="flex justify-center gap-3 mb-6">
        <button @click="toggleLike"
          :class="['flex items-center space-x-2 px-6 py-2.5 rounded-full border-2 transition font-medium text-sm',
            item.is_liked ? 'border-red-500 bg-red-50 text-red-600' : 'border-gray-200 text-gray-500 hover:border-red-300']">
          <span>{{ item.is_liked ? '❤️' : '🤍' }}</span>
          <span>관심 {{ item.like_count || 0 }}</span>
        </button>
        <button v-if="authStore.isLoggedIn && !canEdit && item.status !== 'sold'"
          @click="sendMessage"
          class="flex items-center space-x-2 px-6 py-2.5 rounded-full bg-red-600 text-white font-medium text-sm hover:bg-red-700 transition">
          <span>💬</span>
          <span>연락하기</span>
        </button>
      </div>

      <!-- 댓글/문의 섹션 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
        <div class="px-5 py-3 border-b border-gray-100">
          <h3 class="font-semibold text-gray-800 text-sm">💬 댓글/문의 {{ item.comments?.length || 0 }}개</h3>
        </div>
        <ul v-if="item.comments?.length">
          <li v-for="comment in item.comments" :key="comment.id" class="px-5 py-3.5 border-b border-gray-50 last:border-0">
            <div class="flex items-start justify-between">
              <div class="flex items-start space-x-2 flex-1">
                <div class="w-7 h-7 bg-gray-100 rounded-full flex items-center justify-center text-gray-500 text-xs font-bold flex-shrink-0 mt-0.5">
                  {{ (comment.user?.name || comment.user_name || '익명')[0] }}
                </div>
                <div class="flex-1">
                  <div class="flex items-center space-x-2 mb-1">
                    <span class="text-sm font-medium text-gray-800">{{ comment.user?.name || comment.user_name || '익명' }}</span>
                    <span v-if="comment.user_id === item.user_id" class="text-xs bg-red-50 text-red-500 px-1.5 py-0.5 rounded">판매자</span>
                    <span class="text-xs text-gray-400">{{ formatDate(comment.created_at) }}</span>
                  </div>
                  <p class="text-sm text-gray-700">{{ comment.content }}</p>
                </div>
              </div>
              <button v-if="authStore.user?.id === comment.user?.id || authStore.user?.is_admin"
                @click="deleteComment(comment.id)" class="text-xs text-gray-300 hover:text-red-400 ml-2">삭제</button>
            </div>
          </li>
        </ul>
        <div v-else class="px-5 py-8 text-center text-sm text-gray-400">아직 문의가 없습니다.</div>

        <!-- 댓글 입력 -->
        <div v-if="authStore.isLoggedIn" class="px-5 py-3 bg-gray-50 border-t border-gray-100">
          <div class="flex space-x-2">
            <input v-model="commentText" @keyup.enter.ctrl="submitComment" type="text" placeholder="댓글을 입력하세요 (Ctrl+Enter)"
              class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
            <button @click="submitComment" :disabled="!commentText.trim()"
              class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-40">등록</button>
          </div>
        </div>
        <div v-else class="px-5 py-3 bg-gray-50 border-t border-gray-100 text-center">
          <router-link to="/auth/login" class="text-red-600 text-sm hover:underline">로그인 후 댓글을 작성할 수 있습니다</router-link>
        </div>
      </div>

      <router-link to="/market" class="text-sm text-gray-500 hover:text-red-600">← 목록으로</router-link>
    </template>
    <div v-else class="text-center py-20 text-gray-400">물품을 찾을 수 없습니다.</div>

    <!-- 이미지 갤러리 모달 -->
    <div v-if="galleryOpen" class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center" @click.self="galleryOpen = false">
      <button @click="galleryOpen = false" class="absolute top-4 right-4 text-white text-2xl hover:text-gray-300">&times;</button>
      <button v-if="galleryIndex > 0" @click="galleryIndex--" class="absolute left-4 text-white text-3xl hover:text-gray-300">&lsaquo;</button>
      <img :src="item.images[galleryIndex]" class="max-w-[90vw] max-h-[85vh] object-contain rounded-lg" />
      <button v-if="galleryIndex < item.images.length - 1" @click="galleryIndex++" class="absolute right-4 text-white text-3xl hover:text-gray-300">&rsaquo;</button>
      <div class="absolute bottom-4 text-white text-sm">{{ galleryIndex + 1 }} / {{ item.images.length }}</div>
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
const item = ref(null);
const loading = ref(true);
const commentText = ref('');
const selectedImageIndex = ref(0);
const galleryOpen = ref(false);
const galleryIndex = ref(0);

const canEdit = computed(() =>
  authStore.user && (authStore.user.id === item.value?.user_id || authStore.user.is_admin)
);

function openGallery(idx) {
  galleryIndex.value = idx;
  galleryOpen.value = true;
}

async function toggleLike() {
  if (!authStore.isLoggedIn) { router.push('/auth/login'); return; }
  try {
    const { data } = await axios.post(`/api/market/${item.value.id}/like`);
    item.value.is_liked = data.liked;
    item.value.like_count = data.like_count;
  } catch {}
}

async function toggleBookmark() {
  if (!authStore.isLoggedIn) { router.push('/auth/login'); return; }
  try {
    const { data } = await axios.post(`/api/market/${item.value.id}/bookmark`);
    item.value.is_bookmarked = data.bookmarked;
  } catch {}
}

function shareItem() {
  const url = window.location.href;
  if (navigator.share) {
    navigator.share({ title: item.value.title, url });
  } else if (navigator.clipboard) {
    navigator.clipboard.writeText(url);
    alert('링크가 복사되었습니다.');
  }
}

async function sendMessage() {
  router.push(`/messages?to=${item.value.user_id}&item=${item.value.id}`);
}

async function submitComment() {
  if (!commentText.value.trim()) return;
  try {
    const { data } = await axios.post(`/api/market/${item.value.id}/comments`, { content: commentText.value });
    if (!item.value.comments) item.value.comments = [];
    item.value.comments.push(data.comment);
    commentText.value = '';
  } catch (e) {
    alert(e.response?.data?.message || '댓글 등록 실패');
  }
}

async function deleteComment(id) {
  if (!confirm('댓글을 삭제하시겠습니까?')) return;
  try {
    await axios.delete(`/api/comments/${id}`);
    item.value.comments = item.value.comments.filter(c => c.id !== id);
  } catch {}
}

async function deleteItem() {
  if (!confirm('삭제하시겠습니까?')) return;
  await axios.delete(`/api/market/${item.value.id}`);
  router.push('/market');
}

function formatDate(d) {
  const date = new Date(d);
  const now = new Date();
  const diff = (now - date) / 1000;
  if (diff < 60) return '방금 전';
  if (diff < 3600) return `${Math.floor(diff/60)}분 전`;
  if (diff < 86400) return `${Math.floor(diff/3600)}시간 전`;
  return date.toLocaleDateString('ko-KR');
}

onMounted(async () => {
  try {
    const { data } = await axios.get(`/api/market/${route.params.id}`);
    item.value = data;
  } catch { item.value = null; }
  loading.value = false;
});
</script>
