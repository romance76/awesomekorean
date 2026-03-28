<template>
  <div class="min-h-screen bg-gradient-to-b from-pink-100 to-white pb-24">
    <div class="px-4 py-4 flex items-center gap-3">
      <button @click="$router.back()" class="text-gray-500 text-xl">←</button>
      <h1 class="font-bold text-gray-800 text-lg">매칭 탐색</h1>
      <span class="ml-auto text-gray-400 text-sm">{{ profiles.length }}명</span>
    </div>

    <!-- 카드 없음 -->
    <div v-if="!loading && profiles.length === 0" class="text-center py-16 px-4">
      <p class="text-5xl mb-4">💫</p>
      <p class="font-bold text-gray-700 text-xl mb-2">주변에 더 이상 카드가 없어요</p>
      <p class="text-gray-400 text-sm mb-6">나중에 다시 확인해보세요!</p>
      <button @click="loadProfiles" class="bg-pink-500 text-white px-8 py-3 rounded-xl font-semibold">새로고침</button>
    </div>

    <!-- 로딩 -->
    <div v-else-if="loading" class="flex items-center justify-center py-24">
      <p class="text-gray-400">불러오는 중...</p>
    </div>

    <!-- 카드 -->
    <div v-else class="px-4 relative" style="min-height: 480px;">
      <div
        v-for="(profile, idx) in profiles"
        :key="profile.id"
        v-show="idx === currentIdx"
        class="bg-white rounded-3xl shadow-xl overflow-hidden"
      >
        <!-- 사진 영역 -->
        <div class="bg-gradient-to-br from-pink-300 to-rose-400 h-72 flex items-center justify-center">
          <img v-if="profile.photos?.[0]" :src="profile.photos[0]" class="w-full h-full object-cover" />
          <p v-else class="text-8xl">{{ profile.gender === 'male' ? '👨' : '👩' }}</p>
        </div>

        <!-- 정보 -->
        <div class="p-5">
          <div class="flex items-start justify-between mb-3">
            <div>
              <h2 class="text-2xl font-black text-gray-800">{{ profile.nickname }}, {{ profile.age }}</h2>
              <p class="text-gray-500 text-sm">📍 {{ profile.user?.region || profile.region }}</p>
            </div>
            <span v-if="profile.verified" class="bg-green-100 text-green-600 text-xs px-2 py-1 rounded-full font-bold">✓ 인증</span>
          </div>
          <p class="text-gray-600 text-sm mb-4 line-clamp-3">{{ profile.bio || '자기소개가 없습니다.' }}</p>

          <!-- 관심사 -->
          <div v-if="profile.interests?.length" class="flex flex-wrap gap-2 mb-4">
            <span v-for="tag in profile.interests" :key="tag" class="bg-pink-50 text-pink-600 text-xs px-3 py-1 rounded-full">
              {{ tag }}
            </span>
          </div>

          <!-- 버튼 -->
          <div class="grid grid-cols-2 gap-3">
            <button @click="pass" class="bg-gray-100 text-gray-500 py-4 rounded-xl text-2xl font-bold">✕</button>
            <button @click="like(profile.user_id)" :disabled="liking" class="bg-pink-500 text-white py-4 rounded-xl text-2xl font-bold disabled:opacity-50">❤️</button>
          </div>
        </div>
      </div>
    </div>

    <!-- 매칭 팝업 -->
    <div v-if="matchAlert" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50" @click="matchAlert = false">
      <div class="bg-white rounded-3xl p-8 mx-6 text-center shadow-2xl">
        <p class="text-6xl mb-4">💑</p>
        <h2 class="text-2xl font-black text-pink-600 mb-2">매칭되었습니다!</h2>
        <p class="text-gray-600 mb-6">서로 좋아요를 눌렀어요! 이제 대화를 시작해보세요.</p>
        <button @click="matchAlert = false" class="w-full bg-pink-500 text-white py-3 rounded-xl font-bold">확인</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const profiles   = ref([])
const currentIdx = ref(0)
const loading    = ref(true)
const liking     = ref(false)
const matchAlert = ref(false)

async function loadProfiles() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/match/browse')
    profiles.value = data
    currentIdx.value = 0
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function pass() {
  currentIdx.value++
  if (currentIdx.value >= profiles.value.length) {
    profiles.value = []
  }
}

async function like(userId) {
  liking.value = true
  try {
    const { data } = await axios.post(`/api/match/like/${userId}`)
    if (data.is_match) matchAlert.value = true
    currentIdx.value++
    if (currentIdx.value >= profiles.value.length) profiles.value = []
  } catch (e) {
    alert(e?.response?.data?.message || '오류')
  } finally {
    liking.value = false
  }
}

onMounted(loadProfiles)
</script>
