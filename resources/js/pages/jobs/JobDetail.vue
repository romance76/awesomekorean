<template>
  <div class="max-w-[1200px] mx-auto px-4 py-6">
    <div v-if="loading" class="text-center py-20 text-gray-400">불러오는 중...</div>
    <template v-else-if="job">
      <!-- 상단 네비게이션 -->
      <div class="flex items-center justify-between mb-4">
        <router-link to="/jobs" class="flex items-center gap-1 text-sm text-gray-500 hover:text-red-600">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          뒤로
        </router-link>
        <div class="flex items-center gap-2">
          <button @click="shareJob" class="flex items-center gap-1 text-sm text-gray-500 hover:text-red-600 px-3 py-1.5 rounded-lg border border-gray-200 hover:border-red-200 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
            공유
          </button>
          <button @click="toggleBookmark" :class="['flex items-center gap-1 text-sm px-3 py-1.5 rounded-lg border transition', job.is_bookmarked ? 'text-yellow-600 border-yellow-300 bg-yellow-50' : 'text-gray-500 border-gray-200 hover:border-yellow-300']">
            <svg class="w-4 h-4" :fill="job.is_bookmarked ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
            북마크
          </button>
        </div>
      </div>

      <!-- 메인 정보 카드 -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
        <div class="flex items-start justify-between mb-4">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-2">
              <span v-if="job.job_type" class="text-xs bg-blue-50 text-blue-600 px-2 py-1 rounded font-medium">{{ job.job_type }}</span>
              <span v-if="job.is_urgent" class="text-xs bg-red-50 text-red-600 px-2 py-1 rounded font-medium">🔥 급구</span>
            </div>
            <h1 class="text-xl font-bold text-gray-900">{{ job.title }}</h1>
          </div>
          <div v-if="canEdit" class="flex space-x-2 ml-4">
            <router-link :to="`/jobs/write?edit=${job.id}`" class="text-xs text-gray-400 hover:text-gray-600">수정</router-link>
            <button @click="deleteJob" class="text-xs text-red-400 hover:text-red-600">삭제</button>
          </div>
        </div>

        <!-- 회사/연락처 정보 그리드 -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 bg-gray-50 rounded-lg p-4 mb-5 text-sm">
          <div><span class="text-gray-500">회사명</span><p class="font-medium text-gray-800 mt-0.5">{{ job.company_name || '비공개' }}</p></div>
          <div><span class="text-gray-500">지역</span><p class="font-medium text-gray-800 mt-0.5">{{ job.region || '-' }}</p></div>
          <div><span class="text-gray-500">급여</span><p class="font-medium text-green-600 mt-0.5">{{ job.salary_range || '협의' }}</p></div>
          <div><span class="text-gray-500">마감일</span><p class="font-medium text-gray-800 mt-0.5">{{ job.deadline || '상시' }}</p></div>
          <div v-if="job.work_hours"><span class="text-gray-500">근무시간</span><p class="font-medium text-gray-800 mt-0.5">{{ job.work_hours }}</p></div>
          <div v-if="job.benefits"><span class="text-gray-500">복리후생</span><p class="font-medium text-gray-800 mt-0.5">{{ job.benefits }}</p></div>
        </div>

        <!-- 사진 갤러리 -->
        <div v-if="job.images?.length" class="mb-5">
          <h3 class="text-sm font-semibold text-gray-700 mb-2">사진</h3>
          <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-thin">
            <div v-for="(img, idx) in job.images" :key="idx" class="flex-shrink-0 w-40 h-28 rounded-lg overflow-hidden bg-gray-100 cursor-pointer" @click="openGallery(idx)">
              <img :src="img" :alt="`사진 ${idx + 1}`" class="w-full h-full object-cover hover:scale-105 transition" />
            </div>
          </div>
        </div>

        <!-- 본문 -->
        <div class="prose prose-sm max-w-none text-gray-800 whitespace-pre-wrap leading-relaxed mb-6">{{ job.content }}</div>

        <!-- 연락처 정보 -->
        <div class="border-t border-gray-100 pt-4 space-y-2 text-sm">
          <h3 class="font-semibold text-gray-700 mb-2">연락처 정보</h3>
          <div v-if="job.contact_email" class="flex items-center space-x-2 text-gray-600">
            <span>📧</span><span>{{ job.contact_email }}</span>
          </div>
          <div v-if="job.contact_phone" class="flex items-center space-x-2 text-gray-600">
            <span>📞</span><span>{{ job.contact_phone }}</span>
          </div>
          <div v-if="job.contact_kakao" class="flex items-center space-x-2 text-gray-600">
            <span>💬</span><span>카카오톡: {{ job.contact_kakao }}</span>
          </div>
        </div>

        <!-- 첨부파일 -->
        <div v-if="job.attachments?.length" class="border-t border-gray-100 pt-4 mt-4">
          <h3 class="text-sm font-semibold text-gray-700 mb-2">첨부파일</h3>
          <div class="space-y-2">
            <a v-for="file in job.attachments" :key="file.url" :href="file.url" target="_blank" download
              class="flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800 p-2 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              {{ file.name || '파일 다운로드' }}
            </a>
          </div>
        </div>

        <!-- 외부 링크 -->
        <div v-if="job.external_url" class="border-t border-gray-100 pt-4 mt-4">
          <a :href="job.external_url" target="_blank" rel="noopener"
            class="inline-flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800 font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            외부 사이트에서 보기
          </a>
        </div>
      </div>

      <!-- 지도 -->
      <div v-if="job.latitude && job.longitude" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
        <div class="px-5 py-3 border-b border-gray-100">
          <h3 class="font-semibold text-gray-800 text-sm">📍 근무지 위치</h3>
        </div>
        <iframe
          :src="`https://maps.google.com/maps?q=${job.latitude},${job.longitude}&output=embed`"
          class="w-full h-[300px] border-0"
          allowfullscreen
          loading="lazy"
        ></iframe>
        <div v-if="job.address" class="px-5 py-3 text-sm text-gray-600">{{ job.address }}</div>
      </div>

      <!-- 좋아요 / 연락하기 버튼 -->
      <div class="flex justify-center gap-3 mb-6">
        <button @click="toggleLike"
          :class="['flex items-center space-x-2 px-6 py-2.5 rounded-full border-2 transition font-medium text-sm',
            job.is_liked ? 'border-red-500 bg-red-50 text-red-600' : 'border-gray-200 text-gray-500 hover:border-red-300']">
          <span>{{ job.is_liked ? '❤️' : '🤍' }}</span>
          <span>추천 {{ job.like_count || 0 }}</span>
        </button>
        <a v-if="job.contact_email" :href="`mailto:${job.contact_email}`"
          class="flex items-center space-x-2 px-6 py-2.5 rounded-full bg-red-600 text-white font-medium text-sm hover:bg-red-700 transition">
          <span>📧</span>
          <span>연락하기</span>
        </a>
        <a v-else-if="job.contact_phone" :href="`tel:${job.contact_phone}`"
          class="flex items-center space-x-2 px-6 py-2.5 rounded-full bg-red-600 text-white font-medium text-sm hover:bg-red-700 transition">
          <span>📞</span>
          <span>연락하기</span>
        </a>
      </div>

      <!-- 관련 채용공고 -->
      <div v-if="relatedJobs.length" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
        <div class="px-5 py-3 border-b border-gray-100">
          <h3 class="font-semibold text-gray-800 text-sm">관련 채용공고</h3>
        </div>
        <div class="divide-y divide-gray-50">
          <router-link v-for="rj in relatedJobs" :key="rj.id" :to="`/jobs/${rj.id}`"
            class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition">
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-800 truncate">{{ rj.title }}</p>
              <p class="text-xs text-gray-500 mt-0.5">{{ rj.company_name || '비공개' }} · {{ rj.region || '-' }}</p>
            </div>
            <span class="text-xs text-green-600 font-medium ml-3 flex-shrink-0">{{ rj.salary_range || '협의' }}</span>
          </router-link>
        </div>
      </div>

      <div class="flex items-center justify-between">
        <router-link to="/jobs" class="text-sm text-gray-500 hover:text-red-600">← 목록으로</router-link>
        <span class="text-xs text-gray-400">{{ formatDate(job.created_at) }} 등록</span>
      </div>
    </template>
    <div v-else class="text-center py-20 text-gray-400">공고를 찾을 수 없습니다.</div>

    <!-- 이미지 갤러리 모달 -->
    <div v-if="galleryOpen" class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center" @click.self="galleryOpen = false">
      <button @click="galleryOpen = false" class="absolute top-4 right-4 text-white text-2xl hover:text-gray-300">&times;</button>
      <button v-if="galleryIndex > 0" @click="galleryIndex--" class="absolute left-4 text-white text-3xl hover:text-gray-300">&lsaquo;</button>
      <img :src="job.images[galleryIndex]" class="max-w-[90vw] max-h-[85vh] object-contain rounded-lg" />
      <button v-if="galleryIndex < job.images.length - 1" @click="galleryIndex++" class="absolute right-4 text-white text-3xl hover:text-gray-300">&rsaquo;</button>
      <div class="absolute bottom-4 text-white text-sm">{{ galleryIndex + 1 }} / {{ job.images.length }}</div>
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
const job = ref(null);
const loading = ref(true);
const relatedJobs = ref([]);
const galleryOpen = ref(false);
const galleryIndex = ref(0);

const canEdit = computed(() =>
  authStore.user && (authStore.user.id === job.value?.user_id || authStore.user.is_admin)
);

function openGallery(idx) {
  galleryIndex.value = idx;
  galleryOpen.value = true;
}

async function toggleLike() {
  if (!authStore.isLoggedIn) { router.push('/auth/login'); return; }
  try {
    const { data } = await axios.post(`/api/jobs/${job.value.id}/like`);
    job.value.is_liked = data.liked;
    job.value.like_count = data.like_count;
  } catch {}
}

async function toggleBookmark() {
  if (!authStore.isLoggedIn) { router.push('/auth/login'); return; }
  try {
    const { data } = await axios.post(`/api/jobs/${job.value.id}/bookmark`);
    job.value.is_bookmarked = data.bookmarked;
  } catch {}
}

function shareJob() {
  const url = window.location.href;
  if (navigator.share) {
    navigator.share({ title: job.value.title, url });
  } else if (navigator.clipboard) {
    navigator.clipboard.writeText(url);
    alert('링크가 복사되었습니다.');
  }
}

async function deleteJob() {
  if (!confirm('공고를 삭제하시겠습니까?')) return;
  await axios.delete(`/api/jobs/${job.value.id}`);
  router.push('/jobs');
}

async function loadRelatedJobs() {
  try {
    const { data } = await axios.get('/api/jobs', {
      params: { category: job.value.category || job.value.job_type, per_page: 5 }
    });
    relatedJobs.value = (data.data || data).filter(j => j.id !== job.value.id).slice(0, 4);
  } catch {}
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
    const { data } = await axios.get(`/api/jobs/${route.params.id}`);
    job.value = data;
    loadRelatedJobs();
  } catch { job.value = null; }
  loading.value = false;
});
</script>
