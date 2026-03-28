<template>
  <div class="shorts-wrap bg-black min-h-screen" @wheel.passive="onWheel" @touchstart.passive="onTouchStart" @touchend.passive="onTouchEnd">
    <!-- 상단 헤더 -->
    <div class="fixed top-0 left-0 right-0 z-50 flex items-center justify-between px-4 pt-3 pb-2">
      <h1 class="text-white font-bold text-lg drop-shadow">📱 숏츠</h1>
      <div class="flex gap-2">
        <button @click="showInterests = true"
          class="text-white/80 bg-white/10 backdrop-blur rounded-full px-3 py-1 text-xs hover:bg-white/20">
          관심설정
        </button>
        <RouterLink v-if="auth.isLoggedIn" to="/shorts/upload"
          class="bg-red-500 text-white rounded-full px-3 py-1 text-xs font-bold hover:bg-red-600">
          + 공유
        </RouterLink>
      </div>
    </div>

    <!-- 피드 슬라이드 컨테이너 -->
    <div ref="slider" class="shorts-slider" :style="{ transform: `translateY(calc(-${currentIndex * 100}vh + ${dragOffset}px))` }">
      <div v-for="(short, idx) in shorts" :key="short.id"
        class="short-slide"
        :class="{ active: currentIndex === idx }">

        <!-- 썸네일 / 임베드 -->
        <div class="w-full h-full relative flex items-center justify-center bg-black">
          <!-- YouTube 임베드 -->
          <template v-if="short.platform === 'youtube'">
            <iframe v-if="currentIndex === idx"
              :src="short.embed_url"
              class="w-full h-full"
              frameborder="0"
              allow="autoplay; encrypted-media; gyroscope; picture-in-picture"
              allowfullscreen>
            </iframe>
            <div v-else class="w-full h-full relative">
              <img v-if="short.thumbnail" :src="short.thumbnail" class="w-full h-full object-cover opacity-60" />
              <div class="absolute inset-0 flex items-center justify-center">
                <svg class="w-16 h-16 text-white opacity-80" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M8 5v14l11-7z"/>
                </svg>
              </div>
            </div>
          </template>

          <!-- TikTok / Instagram 임베드 -->
          <template v-else-if="short.platform === 'tiktok' || short.platform === 'instagram'">
            <iframe v-if="currentIndex === idx"
              :src="short.embed_url"
              class="w-full h-full max-w-sm mx-auto"
              frameborder="0"
              scrolling="no"
              allowfullscreen
              allow="encrypted-media">
            </iframe>
            <div v-else class="w-full h-full flex items-center justify-center">
              <div class="text-center text-white/50">
                <div class="text-4xl mb-2">{{ short.platform === 'tiktok' ? '🎵' : '📸' }}</div>
                <p class="text-sm">{{ short.platform === 'tiktok' ? 'TikTok' : 'Instagram' }}</p>
              </div>
            </div>
          </template>

          <!-- 기타 -->
          <template v-else>
            <a :href="short.url" target="_blank" rel="noopener"
              class="flex flex-col items-center justify-center text-white gap-3 p-8 text-center">
              <svg class="w-12 h-12 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
              </svg>
              <span class="text-sm opacity-80 break-all">{{ short.url }}</span>
            </a>
          </template>

          <!-- 그라디언트 오버레이 (하단) -->
          <div class="absolute bottom-0 left-0 right-0 h-40 bg-gradient-to-t from-black/80 to-transparent pointer-events-none"></div>
        </div>

        <!-- 콘텐츠 정보 (하단) -->
        <div class="absolute bottom-0 left-0 right-0 px-4 pb-6 pointer-events-none">
          <div class="flex items-end gap-3">
            <!-- 텍스트 영역 -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <div class="w-7 h-7 rounded-full bg-gray-600 overflow-hidden flex-shrink-0">
                  <img v-if="short.user?.avatar" :src="short.user.avatar" class="w-full h-full object-cover" />
                  <span v-else class="w-full h-full flex items-center justify-center text-white text-xs font-bold">
                    {{ (short.user?.username || '?')[0].toUpperCase() }}
                  </span>
                </div>
                <span class="text-white text-sm font-semibold drop-shadow">@{{ short.user?.username }}</span>
                <span class="text-white/50 text-xs">
                  {{ platformIcon(short.platform) }}
                </span>
              </div>
              <p v-if="short.title" class="text-white text-sm font-medium mb-1 drop-shadow truncate">{{ short.title }}</p>
              <p v-if="short.description" class="text-white/80 text-xs mb-2 line-clamp-2">{{ short.description }}</p>
              <div v-if="short.tags?.length" class="flex flex-wrap gap-1">
                <span v-for="tag in short.tags" :key="tag"
                  class="text-xs text-white/70 bg-white/10 rounded-full px-2 py-0.5">#{{ tag }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- 오른쪽 액션 버튼 -->
        <div class="absolute right-3 bottom-20 flex flex-col items-center gap-5">
          <!-- 좋아요 -->
          <button @click="toggleLike(short)" class="flex flex-col items-center gap-1 pointer-events-auto">
            <div :class="short.liked ? 'text-red-400' : 'text-white'"
              class="w-10 h-10 rounded-full bg-black/30 flex items-center justify-center transition-transform active:scale-90">
              <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
              </svg>
            </div>
            <span class="text-white text-xs drop-shadow">{{ short.like_count }}</span>
          </button>

          <!-- 공유 -->
          <button @click="shareShort(short)" class="flex flex-col items-center gap-1 pointer-events-auto">
            <div class="w-10 h-10 rounded-full bg-black/30 flex items-center justify-center text-white">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
              </svg>
            </div>
            <span class="text-white text-xs drop-shadow">공유</span>
          </button>

          <!-- 원본 링크 -->
          <a :href="short.url" target="_blank" rel="noopener"
            class="flex flex-col items-center gap-1 pointer-events-auto">
            <div class="w-10 h-10 rounded-full bg-black/30 flex items-center justify-center text-white">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
              </svg>
            </div>
            <span class="text-white text-xs drop-shadow">원본</span>
          </a>
        </div>

        <!-- 네비게이션 인디케이터 -->
        <div class="absolute left-3 bottom-1/2 translate-y-1/2 flex flex-col gap-1">
          <div v-for="(_, i) in Math.min(shorts.length, 7)" :key="i"
            :class="i === currentIndex % 7 ? 'bg-white h-5' : 'bg-white/30 h-2'"
            class="w-1 rounded-full transition-all duration-200">
          </div>
        </div>
      </div>

      <!-- 로딩 슬라이드 -->
      <div v-if="loading" class="short-slide flex items-center justify-center bg-gray-900">
        <div class="animate-spin rounded-full h-10 w-10 border-4 border-white border-t-transparent"></div>
      </div>
    </div>

    <!-- 상하 스크롤 힌트 -->
    <div v-if="currentIndex === 0 && shorts.length > 0" class="fixed bottom-8 left-1/2 -translate-x-1/2 z-40 animate-bounce">
      <svg class="w-6 h-6 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
      </svg>
    </div>

    <!-- 관심 태그 설정 모달 -->
    <div v-if="showInterests" class="fixed inset-0 z-[100] bg-black/70 flex items-end">
      <div class="w-full bg-gray-900 rounded-t-2xl p-5">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-white font-bold text-lg">관심 태그 설정</h3>
          <button @click="showInterests = false" class="text-white/60 hover:text-white">✕</button>
        </div>
        <p class="text-white/60 text-sm mb-4">선택한 태그의 콘텐츠를 우선 보여드립니다</p>
        <div class="flex flex-wrap gap-2 mb-5">
          <button v-for="tag in allTags" :key="tag"
            @click="toggleTag(tag)"
            :class="selectedTags.includes(tag)
              ? 'bg-red-500 text-white border-red-500'
              : 'bg-transparent text-white/70 border-white/30'"
            class="border rounded-full px-4 py-1.5 text-sm transition-all">
            #{{ tag }}
          </button>
        </div>
        <button @click="saveInterests"
          class="w-full bg-red-500 text-white font-bold py-3 rounded-xl hover:bg-red-600">
          저장하기
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '../../stores/auth';
import axios from 'axios';

const auth       = useAuthStore();
const shorts     = ref([]);
const loading    = ref(false);
const currentIndex = ref(0);
const page       = ref(1);
const hasMore    = ref(true);
const dragOffset = ref(0);
const touchStartY = ref(0);
const showInterests = ref(false);
const selectedTags  = ref([]);

const allTags = ['요리','여행','뷰티','운동','육아','K-POP','게임','뉴스','재미','음식','패션','생활정보','부동산','자동차','애완동물'];

async function loadShorts() {
  if (loading.value || !hasMore.value) return;
  loading.value = true;
  try {
    const { data } = await axios.get('/api/shorts/feed', { params: { page: page.value } });
    if (data.data.length === 0) { hasMore.value = false; }
    else {
      shorts.value.push(...data.data);
      page.value++;
    }
  } catch {}
  loading.value = false;
}

function onWheel(e) {
  if (e.deltaY > 30)       goNext();
  else if (e.deltaY < -30) goPrev();
}

function onTouchStart(e) {
  touchStartY.value = e.touches[0].clientY;
}

function onTouchEnd(e) {
  const diff = touchStartY.value - e.changedTouches[0].clientY;
  if (diff > 50)       goNext();
  else if (diff < -50) goPrev();
}

function goNext() {
  if (currentIndex.value < shorts.value.length - 1) {
    currentIndex.value++;
    recordView();
    if (currentIndex.value >= shorts.value.length - 3) loadShorts();
  }
}

function goPrev() {
  if (currentIndex.value > 0) currentIndex.value--;
}

function recordView() {
  const s = shorts.value[currentIndex.value];
  if (s) axios.post(`/api/shorts/${s.id}/view`).catch(() => {});
}

async function toggleLike(short) {
  if (!auth.isLoggedIn) { alert('로그인이 필요합니다.'); return; }
  try {
    const { data } = await axios.post(`/api/shorts/${short.id}/like`);
    short.liked      = data.liked;
    short.like_count = data.like_count;
  } catch {}
}

function shareShort(short) {
  if (navigator.share) {
    navigator.share({ title: short.title || 'SomeKorean 숏츠', url: short.url });
  } else {
    navigator.clipboard?.writeText(short.url);
    alert('링크가 복사되었습니다!');
  }
}

function platformIcon(p) {
  return { youtube: '▶ YouTube', tiktok: '🎵 TikTok', instagram: '📸 Instagram' }[p] || '🔗';
}

function toggleTag(tag) {
  const i = selectedTags.value.indexOf(tag);
  if (i >= 0) selectedTags.value.splice(i, 1);
  else if (selectedTags.value.length < 10) selectedTags.value.push(tag);
}

async function saveInterests() {
  if (!auth.isLoggedIn) { showInterests.value = false; return; }
  try {
    await axios.post('/api/shorts/interests', { tags: selectedTags.value });
    showInterests.value = false;
    // 피드 새로고침
    shorts.value = []; page.value = 1; hasMore.value = true; currentIndex.value = 0;
    loadShorts();
  } catch {}
}

onMounted(async () => {
  document.body.style.overflow = 'hidden';
  await loadShorts();
  // 관심 태그 로드
  if (auth.isLoggedIn) {
    try {
      const { data } = await axios.get('/api/shorts/interests');
      selectedTags.value = data.tags || [];
    } catch {}
  }
});

onUnmounted(() => {
  document.body.style.overflow = '';
});
</script>

<style scoped>
.shorts-wrap { position: fixed; top: 0; left: 0; right: 0; bottom: 0; overflow: hidden; }
.shorts-slider {
  position: absolute; top: 0; left: 0; right: 0;
  transition: transform 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}
.short-slide {
  width: 100vw; height: 100vh;
  position: relative; overflow: hidden;
  display: flex; align-items: center; justify-content: center;
  background: #111;
}
.short-slide iframe { width: 100%; height: 100%; border: none; }
</style>
