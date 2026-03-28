<template>
  <div class="min-h-screen bg-gray-50 pb-16">
    <div class="max-w-[1200px] mx-auto px-4 py-6">
      <div v-if="loading" class="text-center py-20 text-gray-400">불러오는 중...</div>
      <template v-else-if="biz">

        <!-- 비즈니스 헤더 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
          <div class="flex items-start justify-between mb-3">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded">{{ biz.category }}</span>
              <span v-if="biz.is_verified" class="text-xs bg-green-50 text-green-600 px-2 py-1 rounded">✓ 인증</span>
            </div>
            <div class="flex items-center gap-3">
              <!-- 북마크 -->
              <button @click="toggleBookmark"
                class="flex items-center gap-1 px-3 py-1.5 rounded-lg border text-sm transition"
                :class="bookmarked ? 'bg-red-50 border-red-200 text-red-600' : 'bg-white border-gray-200 text-gray-500 hover:border-red-300'">
                <span>{{ bookmarked ? '★' : '☆' }}</span>
                <span class="hidden sm:inline">{{ bookmarked ? '저장됨' : '북마크' }}</span>
              </button>
              <!-- 공유 -->
              <button @click="shareBusiness"
                class="flex items-center gap-1 px-3 py-1.5 rounded-lg border border-gray-200 text-sm text-gray-500 hover:border-blue-300 hover:text-blue-600 transition">
                <span>📤</span>
                <span class="hidden sm:inline">공유</span>
              </button>
            </div>
          </div>

          <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ biz.name }}</h1>

          <!-- 별점 -->
          <div class="flex items-center gap-2 mb-3">
            <div class="flex text-yellow-400">
              <span v-for="i in 5" :key="i" class="text-lg">{{ i <= Math.round(Number(biz.rating_avg)) ? '★' : '☆' }}</span>
            </div>
            <span class="font-bold text-gray-800">{{ Number(biz.rating_avg).toFixed(1) }}</span>
            <span class="text-sm text-gray-400">(리뷰 {{ biz.review_count }}개)</span>
          </div>

          <p v-if="biz.description" class="text-gray-600 text-sm leading-relaxed">{{ biz.description }}</p>
        </div>

        <!-- 사진 갤러리 -->
        <div v-if="biz.photos && biz.photos.length" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
          <h3 class="font-semibold text-gray-800 mb-3">📷 사진</h3>
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
            <div v-for="(photo, idx) in biz.photos" :key="idx"
              class="aspect-square rounded-lg overflow-hidden cursor-pointer hover:opacity-90 transition"
              @click="openPhoto(idx)">
              <img :src="photo.url || photo" :alt="`${biz.name} 사진 ${idx + 1}`"
                class="w-full h-full object-cover" />
            </div>
          </div>
          <!-- 사진 모달 -->
          <div v-if="photoModal !== null" class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4" @click="photoModal = null">
            <button class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300" @click="photoModal = null">&times;</button>
            <button v-if="photoModal > 0" class="absolute left-4 text-white text-4xl hover:text-gray-300" @click.stop="photoModal--">&lsaquo;</button>
            <button v-if="photoModal < biz.photos.length - 1" class="absolute right-4 text-white text-4xl hover:text-gray-300" @click.stop="photoModal++">&rsaquo;</button>
            <img :src="biz.photos[photoModal]?.url || biz.photos[photoModal]" class="max-w-full max-h-[80vh] rounded-lg" @click.stop />
          </div>
        </div>

        <!-- 정보 섹션: 연락처 + 영업시간 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

          <!-- 연락처 & 주소 -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-800 mb-4">📋 업소 정보</h3>
            <div class="space-y-3 text-sm">
              <div v-if="biz.address" class="flex items-start gap-2">
                <span class="text-gray-400 mt-0.5">📍</span>
                <span class="text-gray-700">{{ biz.address }}</span>
              </div>
              <div v-if="biz.phone" class="flex items-center gap-2">
                <span class="text-gray-400">📞</span>
                <a :href="`tel:${biz.phone}`" class="text-red-600 hover:underline font-medium">{{ biz.phone }}</a>
              </div>
              <div v-if="biz.website" class="flex items-center gap-2">
                <span class="text-gray-400">🌐</span>
                <a :href="biz.website" target="_blank" rel="noopener" class="text-red-600 hover:underline truncate">{{ biz.website }}</a>
              </div>
              <div v-if="biz.email" class="flex items-center gap-2">
                <span class="text-gray-400">✉️</span>
                <a :href="`mailto:${biz.email}`" class="text-red-600 hover:underline">{{ biz.email }}</a>
              </div>
            </div>
            <!-- 길찾기 버튼 -->
            <a v-if="biz.address"
              :href="`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(biz.address)}`"
              target="_blank" rel="noopener"
              class="mt-4 inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2.5 rounded-lg text-sm font-semibold hover:bg-blue-700 transition">
              🧭 길찾기
            </a>
          </div>

          <!-- 영업시간 -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-800 mb-4">🕐 영업시간</h3>
            <table v-if="parsedHours" class="w-full text-sm">
              <tbody>
                <tr v-for="day in weekDays" :key="day.key"
                  class="border-b border-gray-50 last:border-0"
                  :class="{ 'bg-red-50/50': isToday(day.key) }">
                  <td class="py-2.5 pr-4 font-medium text-gray-700 whitespace-nowrap">
                    <span v-if="isToday(day.key)" class="inline-block w-1.5 h-1.5 bg-red-500 rounded-full mr-1.5"></span>
                    {{ day.label }}
                  </td>
                  <td class="py-2.5 text-gray-600">
                    {{ parsedHours[day.key] || '휴무' }}
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-else class="text-sm text-gray-400">영업시간 정보가 없습니다.</p>
          </div>
        </div>

        <!-- 구글 맵 -->
        <div v-if="biz.address" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
          <iframe
            :src="`https://maps.google.com/maps?q=${encodeURIComponent(biz.address)}&output=embed`"
            width="100%" height="300" style="border:0;" allowfullscreen loading="lazy"
            referrerpolicy="no-referrer-when-downgrade">
          </iframe>
        </div>

        <!-- 리뷰 섹션 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">💬 리뷰 {{ biz.reviews?.length || 0 }}개</h3>
            <div class="flex items-center gap-2">
              <div class="flex text-yellow-400 text-lg">
                <span v-for="i in 5" :key="i">{{ i <= Math.round(Number(biz.rating_avg)) ? '★' : '☆' }}</span>
              </div>
              <span class="font-bold text-gray-800">{{ Number(biz.rating_avg).toFixed(1) }}</span>
            </div>
          </div>

          <!-- 리뷰 리스트 -->
          <div v-if="biz.reviews?.length">
            <div v-for="review in biz.reviews" :key="review.id" class="px-5 py-4 border-b border-gray-50 last:border-0">
              <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-600">
                    {{ (review.user?.name || review.user_name || '익명')[0] }}
                  </div>
                  <div>
                    <span class="text-sm font-medium text-gray-700">{{ review.user?.name || review.user_name || '익명' }}</span>
                    <div class="flex text-yellow-400 text-xs">
                      <span v-for="i in 5" :key="i">{{ i <= review.rating ? '★' : '☆' }}</span>
                    </div>
                  </div>
                </div>
                <span v-if="review.created_at" class="text-xs text-gray-400">{{ formatDate(review.created_at) }}</span>
              </div>
              <p v-if="review.content" class="text-sm text-gray-600 leading-relaxed">{{ review.content }}</p>
            </div>
          </div>
          <div v-else class="px-5 py-10 text-center text-gray-400 text-sm">
            아직 리뷰가 없습니다. 첫 번째 리뷰를 작성해보세요!
          </div>

          <!-- 리뷰 작성 -->
          <div v-if="authStore.isLoggedIn" class="px-5 py-4 bg-gray-50 border-t border-gray-100">
            <h4 class="text-sm font-semibold text-gray-700 mb-2">리뷰 작성</h4>
            <div class="flex items-center gap-1 mb-2">
              <button v-for="i in 5" :key="i" @click="reviewForm.rating = i"
                :class="['text-2xl transition-colors', i <= reviewForm.rating ? 'text-yellow-400' : 'text-gray-300 hover:text-yellow-300']">★</button>
              <span v-if="reviewForm.rating" class="ml-2 text-sm text-gray-500">{{ ratingLabels[reviewForm.rating - 1] }}</span>
            </div>
            <textarea v-model="reviewForm.content" rows="3" placeholder="이 업소에 대한 솔직한 리뷰를 남겨주세요..."
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 resize-none mb-2"></textarea>
            <div class="flex justify-end">
              <button @click="submitReview" :disabled="!reviewForm.rating || submitting"
                class="bg-red-600 text-white px-5 py-2 rounded-lg text-sm font-semibold hover:bg-red-700 disabled:opacity-40 transition">
                {{ submitting ? '등록 중...' : '리뷰 등록' }}
              </button>
            </div>
          </div>
          <div v-else class="px-5 py-4 bg-gray-50 border-t border-gray-100 text-center">
            <p class="text-sm text-gray-500">리뷰를 작성하려면 <router-link to="/auth/login" class="text-red-600 hover:underline font-medium">로그인</router-link>이 필요합니다.</p>
          </div>
        </div>

        <router-link to="/directory" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-red-600 transition">
          ← 업소 목록으로
        </router-link>
      </template>

      <div v-else class="text-center py-20">
        <p class="text-4xl mb-3">😢</p>
        <p class="text-gray-400">업소 정보를 찾을 수 없습니다.</p>
        <router-link to="/directory" class="mt-3 inline-block text-red-600 text-sm hover:underline">목록으로 돌아가기</router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import axios from 'axios';

const route = useRoute();
const authStore = useAuthStore();
const biz = ref(null);
const loading = ref(true);
const bookmarked = ref(false);
const photoModal = ref(null);
const submitting = ref(false);
const reviewForm = ref({ rating: 0, content: '' });

const ratingLabels = ['별로예요', '그저 그래요', '보통이에요', '좋아요', '최고예요'];

const weekDays = [
  { key: 'mon', label: '월요일' },
  { key: 'tue', label: '화요일' },
  { key: 'wed', label: '수요일' },
  { key: 'thu', label: '목요일' },
  { key: 'fri', label: '금요일' },
  { key: 'sat', label: '토요일' },
  { key: 'sun', label: '일요일' },
];

const dayIndexMap = { sun: 0, mon: 1, tue: 2, wed: 3, thu: 4, fri: 5, sat: 6 };

function isToday(dayKey) {
  return new Date().getDay() === dayIndexMap[dayKey];
}

const parsedHours = computed(() => {
  if (!biz.value?.hours) return null;
  try {
    const h = typeof biz.value.hours === 'string' ? JSON.parse(biz.value.hours) : biz.value.hours;
    return h;
  } catch {
    return null;
  }
});

function openPhoto(idx) {
  photoModal.value = idx;
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return `${d.getFullYear()}.${String(d.getMonth() + 1).padStart(2, '0')}.${String(d.getDate()).padStart(2, '0')}`;
}

async function toggleBookmark() {
  if (!authStore.isLoggedIn) {
    alert('로그인이 필요합니다.');
    return;
  }
  try {
    const { data } = await axios.post(`/api/businesses/${biz.value.id}/bookmark`);
    bookmarked.value = data.bookmarked;
  } catch {
    bookmarked.value = !bookmarked.value;
  }
}

function shareBusiness() {
  const url = window.location.href;
  if (navigator.share) {
    navigator.share({ title: biz.value.name, url });
  } else if (navigator.clipboard) {
    navigator.clipboard.writeText(url);
    alert('링크가 복사되었습니다.');
  }
}

async function submitReview() {
  if (submitting.value) return;
  submitting.value = true;
  try {
    await axios.post(`/api/businesses/${biz.value.id}/review`, reviewForm.value);
    const { data } = await axios.get(`/api/businesses/${biz.value.id}`);
    biz.value = data;
    reviewForm.value = { rating: 0, content: '' };
  } catch (e) {
    alert(e.response?.data?.message || '오류가 발생했습니다.');
  }
  submitting.value = false;
}

onMounted(async () => {
  try {
    const { data } = await axios.get(`/api/businesses/${route.params.id}`);
    biz.value = data;
    bookmarked.value = data.is_bookmarked || false;
  } catch {}
  loading.value = false;
});
</script>
