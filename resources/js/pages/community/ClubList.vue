<template>
  <div class="min-h-screen bg-gray-50 pb-16">
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-5 rounded-2xl">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-xl font-black">👥 커뮤니티 동호회</h1>
            <p class="text-blue-100 text-sm mt-0.5">관심사가 같은 한인들과 함께하세요</p>
          </div>
          <button @click="showCreateModal = true" class="bg-white text-blue-600 px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-50 flex-shrink-0">
            + 동호회 만들기
          </button>
        </div>
      </div>
    </div>
    <!-- Category tabs -->
    <div class="max-w-[1200px] mx-auto px-4 mt-3">
      <div class="flex gap-2 overflow-x-auto pb-1" style="scrollbar-width:none">
        <button v-for="cat in clubCategories" :key="cat.value"
          @click="selectedClubCategory = cat.value"
          class="flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold transition"
          :class="selectedClubCategory === cat.value ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:border-blue-300'">
          {{ cat.label }}
        </button>
      </div>
    </div>
    <!-- Search bar -->
    <div class="max-w-[1200px] mx-auto px-4 mt-2">
      <div class="bg-white rounded-2xl shadow-sm p-3">
        <div class="flex items-center gap-2">
          <select v-model="radius" class="border border-gray-200 rounded-lg px-2 py-2 text-sm bg-white flex-shrink-0">
            <option :value="5">📍 5mi</option>
            <option :value="10">📍 10mi</option>
            <option :value="20">📍 20mi</option>
            <option :value="30">📍 30mi</option>
            <option :value="50">📍 50mi</option>
            <option :value="100">📍 100mi</option>
            <option :value="0">📍 전체</option>
          </select>
          <input v-model="search" type="text" placeholder="동호회 검색..."
            class="flex-1 min-w-[100px] border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400" />
          <button class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-700 flex-shrink-0">검색</button>
        </div>
      </div>
    </div>
    <!-- Content area -->
    <div class="max-w-[1200px] mx-auto px-4 py-4">

    <!-- 동호회 카드 그리드 -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="club in filteredClubs"
        :key="club.name"
        class="bg-white rounded-2xl border border-gray-100 overflow-hidden hover:shadow-lg transition-shadow cursor-pointer group"
      >
        <!-- 그라데이션 배너 -->
        <div
          class="h-28 relative"
          :style="{ background: `linear-gradient(135deg, ${club.gradientFrom}, ${club.gradientTo})` }"
        >
          <!-- 승인제 뱃지 -->
          <span
            v-if="club.isApproval"
            class="absolute top-3 right-3 bg-white/90 backdrop-blur-sm text-xs px-2 py-1 rounded-full font-medium text-gray-600"
          >
            🔒 승인제
          </span>
        </div>

        <!-- 아바타 + 내용 -->
        <div class="px-5 pb-5 -mt-8 relative">
          <!-- 클럽 아바타 -->
          <div
            class="w-16 h-16 rounded-full border-4 border-white flex items-center justify-center text-2xl font-bold text-white shadow-md mb-3"
            :style="{ background: `linear-gradient(135deg, ${club.gradientFrom}, ${club.gradientTo})` }"
          >
            {{ club.emoji }}
          </div>

          <h3 class="text-base font-bold text-gray-800 mb-1 group-hover:text-blue-600 transition">
            {{ club.name }}
          </h3>
          <span class="inline-block bg-gray-100 text-gray-500 text-[11px] px-2 py-0.5 rounded-full mb-2">
            {{ club.category }}
          </span>
          <p class="text-xs text-gray-400 line-clamp-1 mb-4">{{ club.description }}</p>

          <!-- 하단: 멤버 + 가입 -->
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <!-- 멤버 아바타 원 -->
              <div class="flex -space-x-2">
                <div
                  v-for="(c, i) in club.memberColors.slice(0, 3)"
                  :key="i"
                  class="w-7 h-7 rounded-full border-2 border-white flex items-center justify-center text-[10px] font-bold text-white"
                  :style="{ backgroundColor: c }"
                >
                  {{ club.memberInitials[i] }}
                </div>
              </div>
              <span class="text-[11px] text-gray-400 ml-2">{{ club.memberCount }}명</span>
            </div>
            <button
              class="bg-gradient-to-r from-pink-500 to-red-500 text-white text-xs font-semibold px-4 py-2 rounded-full hover:from-pink-600 hover:to-red-600 transition shadow-sm"
            >
              가입하기
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 빈 상태 -->
    <div v-if="filteredClubs.length === 0" class="text-center py-20">
      <p class="text-4xl mb-3">🔍</p>
      <p class="text-gray-400 text-sm">해당 카테고리의 동호회가 없습니다.</p>
      <button @click="showCreateModal = true" class="mt-3 text-blue-500 text-sm hover:underline">새로운 동호회 만들기 →</button>
    </div>
    </div><!-- /max-w-[1200px] -->

    <!-- 동호회 만들기 모달 -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="showCreateModal = false">
      <div class="fixed inset-0 bg-black/50" @click="showCreateModal = false"></div>
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md relative z-10 max-h-[90vh] overflow-y-auto">
        <div class="p-6">
          <div class="flex items-center justify-between mb-5">
            <h2 class="text-lg font-black text-gray-800">👥 동호회 만들기</h2>
            <button @click="showCreateModal = false" class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 hover:bg-gray-200 hover:text-gray-600">
              ✕
            </button>
          </div>

          <div class="space-y-4">
            <!-- 동호회 사진 -->
            <div class="mb-3">
              <label class="block text-xs text-gray-500 mb-1 font-semibold">동호회 사진/로고</label>
              <div class="flex items-center gap-3">
                <div class="w-20 h-20 rounded-xl overflow-hidden bg-gray-100 flex items-center justify-center border-2 border-dashed border-gray-300">
                  <img v-if="newClubPhotoPreview" :src="newClubPhotoPreview" class="w-full h-full object-cover" />
                  <span v-else class="text-2xl text-gray-400">📷</span>
                </div>
                <label class="bg-blue-50 text-blue-600 px-3 py-2 rounded-lg text-sm font-semibold cursor-pointer hover:bg-blue-100">
                  사진 선택
                  <input type="file" accept="image/*" class="hidden" @change="onClubPhotoSelect" />
                </label>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">동호회 이름 <span class="text-red-400">*</span></label>
              <input v-model="createForm.name" type="text" placeholder="예: 애틀랜타 한인 축구회" maxlength="50"
                class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400" />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">카테고리 <span class="text-red-400">*</span></label>
              <select v-model="createForm.category"
                class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400">
                <option value="">선택하세요</option>
                <option v-for="cat in clubCategories.filter(c => c.value !== 'all')" :key="cat.value" :value="cat.value">
                  {{ cat.label }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">소개 <span class="text-red-400">*</span></label>
              <textarea v-model="createForm.description" rows="3" placeholder="동호회에 대해 소개해 주세요"
                class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400 resize-none" />
            </div>

            <label class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl cursor-pointer">
              <input v-model="createForm.is_approval" type="checkbox" class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
              <div>
                <p class="text-sm font-medium text-gray-700">승인제로 운영</p>
                <p class="text-xs text-gray-400">가입 신청을 관리자가 승인해야 가입됩니다</p>
              </div>
            </label>

            <div v-if="createError" class="text-red-600 text-sm bg-red-50 p-3 rounded-xl">{{ createError }}</div>

            <div class="flex gap-3 pt-2">
              <button @click="showCreateModal = false"
                class="flex-1 px-4 py-3 border border-gray-300 text-gray-600 rounded-xl text-sm hover:bg-gray-50">
                취소
              </button>
              <button @click="submitCreateClub" :disabled="creating"
                class="flex-1 px-4 py-3 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-700 disabled:opacity-50 transition">
                {{ creating ? '만드는 중...' : '동호회 만들기' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const radius = ref(30);
const search = ref('');
const selectedClubCategory = ref('all');
const showCreateModal = ref(false);
const creating = ref(false);
const createError = ref('');

const newClubPhoto = ref(null);
const newClubPhotoPreview = ref('');

function onClubPhotoSelect(e) {
  const file = e.target.files[0];
  if (!file) return;
  newClubPhoto.value = file;
  const reader = new FileReader();
  reader.onload = ev => { newClubPhotoPreview.value = ev.target.result; };
  reader.readAsDataURL(file);
}

const createForm = ref({
  name: '',
  category: '',
  description: '',
  is_approval: false,
});

const clubCategories = [
  { value: 'all', label: '전체' },
  { value: '스포츠', label: '스포츠' },
  { value: '음식/요리', label: '음식/요리' },
  { value: '육아/교육', label: '육아/교육' },
  { value: '취미/여가', label: '취미/여가' },
  { value: '종교', label: '종교' },
  { value: '비즈니스', label: '비즈니스' },
  { value: '기타', label: '기타' },
];

const clubs = ref([
  {
    name: '조지아 맘스 모임',
    category: '육아/교육',
    emoji: '👶',
    description: '조지아 지역 한인 엄마들의 육아 정보 공유 및 모임',
    memberCount: 248,
    isApproval: true,
    gradientFrom: '#F472B6',
    gradientTo: '#A78BFA',
    memberColors: ['#F472B6', '#A78BFA', '#60A5FA'],
    memberInitials: ['김', '이', '박'],
  },
  {
    name: '애틀랜타 한인 축구회',
    category: '스포츠',
    emoji: '⚽',
    description: '매주 토요일 오전 축구 모임, 초보자도 환영합니다!',
    memberCount: 156,
    isApproval: false,
    gradientFrom: '#34D399',
    gradientTo: '#3B82F6',
    memberColors: ['#34D399', '#3B82F6', '#F59E0B'],
    memberInitials: ['정', '최', '한'],
  },
  {
    name: '한인 골프 동호회',
    category: '스포츠',
    emoji: '⛳',
    description: '함께 라운딩하며 친목을 다지는 골프 모임',
    memberCount: 89,
    isApproval: false,
    gradientFrom: '#10B981',
    gradientTo: '#059669',
    memberColors: ['#10B981', '#059669', '#6EE7B7'],
    memberInitials: ['유', '강', '조'],
  },
  {
    name: 'IT 개발자 네트워크',
    category: '비즈니스',
    emoji: '💻',
    description: '실리콘밸리부터 동부까지, 한인 개발자 커뮤니티',
    memberCount: 312,
    isApproval: false,
    gradientFrom: '#6366F1',
    gradientTo: '#8B5CF6',
    memberColors: ['#6366F1', '#8B5CF6', '#A78BFA'],
    memberInitials: ['서', '윤', '임'],
  },
  {
    name: '요리 연구회',
    category: '음식/요리',
    emoji: '🍳',
    description: '한식부터 양식까지, 요리 레시피 공유 및 쿡오프 모임',
    memberCount: 174,
    isApproval: false,
    gradientFrom: '#F59E0B',
    gradientTo: '#EF4444',
    memberColors: ['#F59E0B', '#EF4444', '#F97316'],
    memberInitials: ['안', '송', '배'],
  },
  {
    name: '북클럽 - 한국 문학',
    category: '취미/여가',
    emoji: '📚',
    description: '매월 한 권의 한국 문학 작품을 읽고 토론하는 모임',
    memberCount: 63,
    isApproval: true,
    gradientFrom: '#8B5CF6',
    gradientTo: '#EC4899',
    memberColors: ['#8B5CF6', '#EC4899', '#A78BFA'],
    memberInitials: ['문', '장', '권'],
  },
  {
    name: '등산 동호회',
    category: '스포츠',
    emoji: '🏔️',
    description: '주말마다 함께 산행하며 건강과 우정을 쌓아요',
    memberCount: 108,
    isApproval: false,
    gradientFrom: '#059669',
    gradientTo: '#0D9488',
    memberColors: ['#059669', '#0D9488', '#34D399'],
    memberInitials: ['오', '신', '황'],
  },
  {
    name: '한인 교회 찬양팀',
    category: '종교',
    emoji: '🎵',
    description: '찬양과 워십을 함께하는 한인 교회 음악 사역 모임',
    memberCount: 45,
    isApproval: true,
    gradientFrom: '#3B82F6',
    gradientTo: '#1D4ED8',
    memberColors: ['#3B82F6', '#1D4ED8', '#60A5FA'],
    memberInitials: ['홍', '노', '하'],
  },
]);

const filteredClubs = computed(() => {
  let result = clubs.value;
  if (selectedClubCategory.value !== 'all') {
    result = result.filter(c => c.category === selectedClubCategory.value);
  }
  if (search.value.trim()) {
    const q = search.value.trim().toLowerCase();
    result = result.filter(c => c.name.toLowerCase().includes(q) || c.description.toLowerCase().includes(q));
  }
  return result;
});

async function submitCreateClub() {
  createError.value = '';
  if (!createForm.value.name.trim()) { createError.value = '동호회 이름을 입력하세요.'; return; }
  if (!createForm.value.category) { createError.value = '카테고리를 선택하세요.'; return; }
  if (!createForm.value.description.trim()) { createError.value = '소개를 입력하세요.'; return; }

  creating.value = true;
  try {
    const fd = new FormData();
    fd.append('name', createForm.value.name);
    fd.append('category', createForm.value.category);
    fd.append('description', createForm.value.description);
    fd.append('is_approval', createForm.value.is_approval ? '1' : '0');
    if (newClubPhoto.value) fd.append('cover_image', newClubPhoto.value);
    const { data } = await axios.post('/api/clubs', fd, { headers: { 'Content-Type': 'multipart/form-data' } });

    // Add the new club to the local list
    const gradientColors = [
      { from: '#F472B6', to: '#A78BFA' },
      { from: '#34D399', to: '#3B82F6' },
      { from: '#6366F1', to: '#8B5CF6' },
      { from: '#F59E0B', to: '#EF4444' },
      { from: '#059669', to: '#0D9488' },
    ];
    const randomGradient = gradientColors[Math.floor(Math.random() * gradientColors.length)];

    clubs.value.unshift({
      name: createForm.value.name,
      category: createForm.value.category,
      emoji: '🆕',
      description: createForm.value.description,
      memberCount: 1,
      isApproval: createForm.value.is_approval,
      gradientFrom: randomGradient.from,
      gradientTo: randomGradient.to,
      memberColors: [randomGradient.from],
      memberInitials: ['나'],
    });

    // Reset form and close modal
    createForm.value = { name: '', category: '', description: '', is_approval: false };
    newClubPhoto.value = null;
    newClubPhotoPreview.value = '';
    showCreateModal.value = false;
  } catch (e) {
    createError.value = e.response?.data?.message || '동호회 생성에 실패했습니다. 다시 시도해 주세요.';
  } finally {
    creating.value = false;
  }
}
</script>

<style scoped>
.line-clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
