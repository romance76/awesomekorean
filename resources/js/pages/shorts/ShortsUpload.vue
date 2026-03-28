<template>
  <div class="max-w-lg mx-auto px-4 py-6">
    <div class="flex items-center gap-3 mb-6">
      <RouterLink to="/shorts" class="text-gray-500 hover:text-gray-700">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
      </RouterLink>
      <h1 class="text-xl font-bold">숏츠 공유하기</h1>
    </div>

    <!-- 미리보기 -->
    <div v-if="preview" class="mb-5 rounded-xl overflow-hidden bg-black aspect-[9/16] max-h-80 flex items-center justify-center relative">
      <img v-if="preview.thumbnail" :src="preview.thumbnail" class="w-full h-full object-cover opacity-70" />
      <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-white">
        <div class="text-3xl">{{ platformEmoji(preview.platform) }}</div>
        <div class="text-sm font-semibold">{{ preview.platform.toUpperCase() }}</div>
        <div class="text-xs opacity-70">미리보기</div>
      </div>
    </div>

    <form @submit.prevent="submit" class="space-y-4">
      <!-- URL 입력 -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">영상 링크 *</label>
        <div class="flex gap-2">
          <input v-model="form.url" type="url" placeholder="YouTube, TikTok, Instagram 링크를 붙여넣으세요"
            class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"
            @blur="parsePreview" required />
          <button type="button" @click="parsePreview"
            class="px-3 py-2 bg-gray-100 rounded-lg text-sm text-gray-600 hover:bg-gray-200 whitespace-nowrap">
            미리보기
          </button>
        </div>
        <p class="text-xs text-gray-400 mt-1">지원: YouTube Shorts, TikTok, Instagram Reels</p>
      </div>

      <!-- 지원 플랫폼 안내 -->
      <div class="flex gap-3 text-center">
        <div v-for="p in platforms" :key="p.name"
          :class="preview?.platform === p.key ? 'border-red-400 bg-red-50' : 'border-gray-200'"
          class="flex-1 border rounded-lg p-2 transition-colors">
          <div class="text-xl mb-0.5">{{ p.emoji }}</div>
          <div class="text-xs text-gray-600 font-medium">{{ p.name }}</div>
        </div>
      </div>

      <!-- 제목 -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">제목</label>
        <input v-model="form.title" type="text" placeholder="간단한 설명을 입력하세요" maxlength="100"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400" />
        <div class="text-right text-xs text-gray-400 mt-0.5">{{ form.title.length }}/100</div>
      </div>

      <!-- 설명 -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">설명 (선택)</label>
        <textarea v-model="form.description" rows="2" placeholder="내용을 설명해주세요..." maxlength="300"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 resize-none"></textarea>
      </div>

      <!-- 태그 -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">태그 선택 (최대 5개)</label>
        <div class="flex flex-wrap gap-2">
          <button v-for="tag in allTags" :key="tag" type="button"
            @click="toggleTag(tag)"
            :class="form.tags.includes(tag)
              ? 'bg-red-500 text-white border-red-500'
              : 'bg-white text-gray-600 border-gray-300'"
            class="border rounded-full px-3 py-1 text-xs transition-all hover:border-red-400">
            #{{ tag }}
          </button>
        </div>
      </div>

      <!-- 제출 -->
      <button type="submit" :disabled="!form.url || submitting"
        class="w-full bg-red-500 text-white font-bold py-3 rounded-xl hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
        {{ submitting ? '업로드 중...' : '🚀 공유하기' }}
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router   = useRouter();
const submitting = ref(false);
const preview    = ref(null);

const form = reactive({ url: '', title: '', description: '', tags: [] });
const allTags = ['요리','여행','뷰티','운동','육아','K-POP','게임','뉴스','재미','음식','패션','생활정보','부동산','자동차','애완동물'];

const platforms = [
  { key: 'youtube',   name: 'YouTube',   emoji: '▶️' },
  { key: 'tiktok',    name: 'TikTok',    emoji: '🎵' },
  { key: 'instagram', name: 'Instagram', emoji: '📸' },
];

function platformEmoji(p) {
  return { youtube: '▶️', tiktok: '🎵', instagram: '📸' }[p] || '🔗';
}

async function parsePreview() {
  if (!form.url) return;
  // 간단한 클라이언트 파싱
  let plat = 'other', thumb = null;
  let ytMatch = form.url.match(/(?:youtube\.com\/shorts\/|youtu\.be\/|youtube\.com\/watch\?v=)([a-zA-Z0-9_-]+)/);
  if (ytMatch) {
    plat  = 'youtube';
    thumb = `https://img.youtube.com/vi/${ytMatch[1]}/hqdefault.jpg`;
  } else if (form.url.includes('tiktok.com')) {
    plat = 'tiktok';
  } else if (form.url.includes('instagram.com')) {
    plat = 'instagram';
  }
  preview.value = { platform: plat, thumbnail: thumb };
}

function toggleTag(tag) {
  const i = form.tags.indexOf(tag);
  if (i >= 0) form.tags.splice(i, 1);
  else if (form.tags.length < 5) form.tags.push(tag);
}

async function submit() {
  submitting.value = true;
  try {
    await axios.post('/api/shorts', form);
    router.push('/shorts');
  } catch (e) {
    alert(e.response?.data?.message || '오류가 발생했습니다.');
  }
  submitting.value = false;
}
</script>
