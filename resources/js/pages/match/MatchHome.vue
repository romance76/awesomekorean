<template>
  <div class="min-h-screen bg-gray-50 pb-16">
    <!-- Header -->
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-6 rounded-2xl">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-xl font-black">💝 소개팅 · Senior Dating</h1>
            <p class="text-blue-100 text-sm mt-0.5">오늘의 인연을 만나보세요 — Find your special someone</p>
          </div>
          <button @click="$router.push('/dashboard?tab=match')" class="bg-white/20 hover:bg-white/30 text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
            내 프로필 ✏️
          </button>
        </div>

        <!-- Stats bar -->
        <div class="mt-4 grid grid-cols-3 gap-3">
          <div class="bg-white/15 rounded-xl p-3 text-center">
            <p class="text-xl font-black">{{ totalProfiles }}</p>
            <p class="text-blue-100 text-xs mt-0.5">새 추천</p>
          </div>
          <div class="bg-white/15 rounded-xl p-3 text-center">
            <p class="text-xl font-black">{{ likedCount }}</p>
            <p class="text-blue-100 text-xs mt-0.5">좋아요 받음</p>
          </div>
          <div class="bg-white/15 rounded-xl p-3 text-center">
            <p class="text-xl font-black">{{ matchCount }}</p>
            <p class="text-blue-100 text-xs mt-0.5">매칭됨</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white border-b sticky top-[84px] z-10">
      <div class="max-w-[1200px] mx-auto flex overflow-x-auto">
        <button v-for="tab in tabs" :key="tab.id"
          @click="activeTab = tab.id"
          :class="activeTab === tab.id
            ? 'border-b-2 border-pink-500 text-pink-600 font-bold'
            : 'text-gray-500 hover:text-gray-700'"
          class="flex-shrink-0 px-5 py-3 text-sm flex items-center gap-1.5 transition">
          {{ tab.icon }} {{ tab.label }}
          <span v-if="tab.id === 'liked' && likedCount > 0"
            class="bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full leading-none">{{ likedCount }}</span>
          <span v-if="tab.id === 'matches' && matchCount > 0"
            class="bg-pink-500 text-white text-xs px-1.5 py-0.5 rounded-full leading-none">{{ matchCount }}</span>
        </button>
      </div>
    </div>

    <div class="max-w-[1200px] mx-auto">

      <!-- === 오늘의 추천 탭 === -->
      <div v-if="activeTab === 'picks'">
        <!-- No profile: setup prompt -->
        <div v-if="!myProfile && !profileLoading" class="mx-4 mt-8 bg-white rounded-2xl shadow-sm border border-pink-100 p-8 text-center">
          <p class="text-5xl mb-4">💝</p>
          <h3 class="font-black text-gray-800 text-xl mb-2">매칭 프로필을 만들어보세요</h3>
          <p class="text-gray-500 text-sm mb-6">닉네임, 성별, 나이, 관심사를 등록하면 딱 맞는 인연을 추천해드려요!</p>
          <button @click="$router.push('/dashboard?tab=match')" class="bg-pink-500 text-white px-8 py-3 rounded-xl font-bold hover:bg-pink-600">
            프로필 만들기 →
          </button>
        </div>

        <!-- Loading -->
        <div v-else-if="loading" class="flex justify-center py-24">
          <div class="text-center">
            <p class="text-4xl mb-3 animate-pulse">💝</p>
            <p class="text-gray-400">인연을 찾고 있어요...</p>
          </div>
        </div>

        <!-- No results -->
        <div v-else-if="profiles.length === 0" class="text-center py-20 px-4">
          <p class="text-5xl mb-4">💫</p>
          <p class="font-bold text-gray-700 text-lg mb-2">오늘의 추천이 없어요</p>
          <p class="text-gray-400 text-sm mb-6">나중에 다시 확인해보세요!</p>
          <button @click="loadProfiles" class="bg-pink-500 text-white px-8 py-3 rounded-xl font-semibold">새로고침</button>
        </div>

        <!-- Profile cards -->
        <div v-else class="px-4 pt-5">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="profile in visibleProfiles" :key="profile.id"
              class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition">
              <!-- Card image area -->
              <div class="relative h-44 flex items-center justify-center" :class="cardBg(profile)">
                <!-- Match % badge -->
                <div class="absolute top-3 left-3 bg-white/90 backdrop-blur text-amber-600 text-xs font-bold px-2.5 py-1 rounded-full flex items-center gap-1 shadow-sm">
                  ⭐ {{ matchPercent(profile) }}% 매칭
                </div>
                <!-- Verified badge -->
                <div v-if="profile.verified" class="absolute top-3 right-3 bg-green-500 text-white text-xs px-2 py-0.5 rounded-full font-semibold">
                  ✓ Verified
                </div>
                <!-- Avatar -->
                <img v-if="profile.user?.avatar" :src="profile.user.avatar" class="w-full h-full object-cover" />
                <span v-else class="text-7xl drop-shadow">
                  {{ profile.gender === 'male' ? '👨' : profile.gender === 'female' ? '👩' : '🧑' }}
                </span>
              </div>

              <!-- Card body -->
              <div class="p-4">
                <div class="mb-2">
                  <h3 class="font-black text-gray-800 text-lg leading-tight">
                    {{ profile.nickname }}, {{ profile.age }}
                  </h3>
                  <p class="text-gray-500 text-xs mt-0.5 flex items-center gap-1">
                    <span>📍</span>{{ profile.user?.region || profile.region || '지역 미상' }}
                  </p>
                </div>

                <!-- Interests tags -->
                <div class="flex flex-wrap gap-1 mb-3">
                  <span v-for="tag in (profile.interests || []).slice(0, 4)" :key="tag"
                    class="bg-pink-50 text-pink-600 text-xs px-2 py-0.5 rounded-full font-medium">{{ tag }}</span>
                  <span v-if="(profile.interests || []).length > 4" class="text-gray-400 text-xs self-center">
                    +{{ profile.interests.length - 4 }}
                  </span>
                </div>

                <!-- Bio -->
                <p class="text-gray-500 text-sm mb-4 line-clamp-2 leading-relaxed">
                  {{ profile.bio || '아직 자기소개가 없습니다.' }}
                </p>

                <!-- Action buttons -->
                <div class="flex items-center gap-2">
                  <!-- Pass -->
                  <button @click="pass(profile.id)"
                    class="w-10 h-10 rounded-full border-2 border-gray-200 text-gray-400 hover:border-red-300 hover:text-red-400 flex items-center justify-center font-bold text-sm flex-shrink-0 transition">
                    ✕
                  </button>
                  <!-- Like / Coffee -->
                  <button @click="like(profile.user_id, profile.id)" :disabled="liking === profile.id"
                    class="flex-1 bg-gradient-to-r from-pink-500 to-rose-500 text-white py-2.5 rounded-xl font-bold flex items-center justify-center gap-1.5 hover:from-pink-600 hover:to-rose-600 disabled:opacity-60 transition text-sm">
                    <span v-if="liking === profile.id" class="animate-pulse">...</span>
                    <span v-else>☕ Coffee?</span>
                  </button>
                  <!-- Bookmark -->
                  <button class="w-10 h-10 rounded-full border-2 border-gray-200 text-gray-400 hover:border-blue-300 hover:text-blue-500 flex items-center justify-center flex-shrink-0 transition text-sm">
                    🔖
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Load more -->
          <div v-if="profiles.length > visibleCount" class="text-center py-6">
            <button @click="visibleCount += 9" class="bg-white border border-gray-200 text-gray-600 px-6 py-2.5 rounded-xl font-semibold hover:bg-gray-50 text-sm">
              더 보기 ({{ profiles.length - visibleCount }}명)
            </button>
          </div>
        </div>

        <!-- Virtual Date Room banner -->
        <div v-if="!loading && profiles.length > 0" class="mx-4 mt-6 mb-4 bg-gradient-to-r from-purple-500 to-indigo-600 text-white rounded-2xl p-5 flex items-center justify-between shadow-md">
          <div>
            <h3 class="font-bold text-lg">💻 가상 데이트룸</h3>
            <p class="text-purple-200 text-sm mt-0.5">매칭 후 화상채팅으로 편하게 대화해보세요!</p>
            <p class="text-purple-200 text-xs mt-0.5">Virtual Date Room — easier than Zoom!</p>
          </div>
          <button class="bg-white text-purple-600 font-bold px-4 py-2 rounded-xl text-sm flex-shrink-0 hover:bg-purple-50 transition">
            시작하기
          </button>
        </div>
      </div>

      <!-- === 좋아요 받음 탭 === -->
      <div v-else-if="activeTab === 'liked'" class="px-4 pt-5">
        <div v-if="likesLoading" class="text-center py-16 text-gray-400">불러오는 중...</div>
        <div v-else-if="receivedLikes.length === 0" class="text-center py-20">
          <p class="text-5xl mb-3">💌</p>
          <p class="font-bold text-gray-700 text-lg mb-2">아직 좋아요가 없어요</p>
          <p class="text-gray-400 text-sm">프로필을 완성하면 더 많은 관심을 받을 수 있어요</p>
        </div>
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="item in receivedLikes" :key="item.id"
            class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center gap-4">
            <div class="w-14 h-14 rounded-full bg-pink-100 flex items-center justify-center text-2xl flex-shrink-0 overflow-hidden">
              <img v-if="item.user?.avatar" :src="item.user.avatar" class="w-full h-full object-cover" />
              <span v-else>{{ item.user?.gender === 'male' ? '👨' : '👩' }}</span>
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-bold text-gray-800">{{ item.user?.name || '알 수 없음' }}</p>
              <p class="text-gray-500 text-xs">{{ timeAgo(item.created_at) }}</p>
              <p v-if="item.is_match" class="text-pink-600 text-xs font-bold mt-0.5">💑 서로 매칭됨!</p>
            </div>
            <button v-if="!item.is_match" @click="like(item.user_id, null)"
              class="bg-pink-500 text-white text-xs px-3 py-2 rounded-xl font-bold hover:bg-pink-600 flex-shrink-0">
              ☕ Coffee?
            </button>
          </div>
        </div>
      </div>

      <!-- === 매칭됨 탭 === -->
      <div v-else-if="activeTab === 'matches'" class="px-4 pt-5">
        <div v-if="matchesLoading" class="text-center py-16 text-gray-400">불러오는 중...</div>
        <div v-else-if="myMatches.length === 0" class="text-center py-20">
          <p class="text-5xl mb-3">💑</p>
          <p class="font-bold text-gray-700 text-lg mb-2">아직 매칭된 분이 없어요</p>
          <p class="text-gray-400 text-sm mb-6">오늘의 추천에서 커피☕를 보내보세요!</p>
          <button @click="activeTab = 'picks'" class="bg-pink-500 text-white px-6 py-2.5 rounded-xl font-bold">추천 보러가기</button>
        </div>
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="item in myMatches" :key="item.id"
            class="bg-white rounded-2xl shadow-sm border border-pink-100 overflow-hidden hover:shadow-md transition">
            <!-- Pink top bar -->
            <div class="h-2 bg-gradient-to-r from-pink-400 to-rose-400"></div>
            <div class="p-4 flex items-center gap-4">
              <div class="w-14 h-14 rounded-full bg-pink-50 flex items-center justify-center text-2xl flex-shrink-0 overflow-hidden border-2 border-pink-200">
                <img v-if="item.liked_user?.avatar" :src="item.liked_user.avatar" class="w-full h-full object-cover" />
                <span v-else>💑</span>
              </div>
              <div class="flex-1 min-w-0">
                <p class="font-bold text-gray-800">{{ item.liked_user?.name || '알 수 없음' }}</p>
                <p class="text-pink-500 text-xs font-semibold">💑 서로 매칭됨!</p>
                <p class="text-gray-400 text-xs">{{ timeAgo(item.created_at) }}</p>
              </div>
              <RouterLink :to="`/messages`"
                class="bg-pink-500 text-white text-xs px-3 py-2 rounded-xl font-bold hover:bg-pink-600 flex-shrink-0">
                메시지
              </RouterLink>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- 매칭 팝업 -->
    <Transition name="fade">
      <div v-if="matchAlert" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 px-4" @click="matchAlert = false">
        <div class="bg-white rounded-3xl p-8 max-w-sm w-full text-center shadow-2xl" @click.stop>
          <p class="text-6xl mb-4">💑</p>
          <h2 class="text-2xl font-black text-pink-600 mb-2">매칭되었습니다!</h2>
          <p class="text-gray-600 mb-6">서로 커피☕를 보냈어요!<br>이제 메시지를 보내보세요.</p>
          <div class="flex gap-3">
            <button @click="matchAlert = false" class="flex-1 border border-gray-200 text-gray-600 py-3 rounded-xl font-semibold">닫기</button>
            <RouterLink to="/messages" class="flex-1 bg-pink-500 text-white py-3 rounded-xl font-bold text-center">메시지 보내기</RouterLink>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

// Tabs
const activeTab = ref('picks')
const tabs = [
  { id: 'picks',   icon: '💝', label: '오늘의 추천' },
  { id: 'liked',   icon: '💌', label: '좋아요 받음' },
  { id: 'matches', icon: '💑', label: '매칭됨' },
]

// My profile
const myProfile     = ref(null)
const profileLoading = ref(true)

// Browse profiles
const profiles    = ref([])
const loading     = ref(true)
const liking      = ref(null)
const matchAlert  = ref(false)
const passedIds   = ref(new Set())
const visibleCount = ref(9)

const visibleProfiles = computed(() =>
  profiles.value.filter(p => !passedIds.value.has(p.id)).slice(0, visibleCount.value)
)
const totalProfiles = computed(() => profiles.value.filter(p => !passedIds.value.has(p.id)).length)

// Likes & Matches
const receivedLikes  = ref([])
const myMatches      = ref([])
const likesLoading   = ref(false)
const matchesLoading = ref(false)
const likedCount     = ref(0)
const matchCount     = ref(0)

// Card background by gender
function cardBg(profile) {
  if (profile.gender === 'male')   return 'bg-gradient-to-br from-blue-100 to-indigo-200'
  if (profile.gender === 'female') return 'bg-gradient-to-br from-pink-100 to-rose-200'
  return 'bg-gradient-to-br from-purple-100 to-violet-200'
}

// Calculate match % based on shared interests
function matchPercent(profile) {
  if (!myProfile.value?.interests?.length || !profile.interests?.length) {
    // Pseudo-random but stable per profile id
    return 70 + (profile.id % 27)
  }
  const myInterests = new Set(myProfile.value.interests)
  const shared = (profile.interests || []).filter(i => myInterests.has(i)).length
  const total  = new Set([...myProfile.value.interests, ...(profile.interests || [])]).size
  const base   = total > 0 ? Math.round((shared / total) * 40) : 0
  return Math.min(99, 60 + base + (profile.id % 15))
}

function pass(profileId) {
  passedIds.value.add(profileId)
}

async function like(userId, profileId) {
  if (!userId) return
  liking.value = profileId || userId
  try {
    const { data } = await axios.post(`/api/match/like/${userId}`)
    if (profileId) passedIds.value.add(profileId)
    if (data.is_match) {
      matchAlert.value = true
      matchCount.value++
    }
  } catch (e) {
    const msg = e?.response?.data?.message
    if (msg && msg !== '이미 좋아요 했습니다.') alert(msg)
  } finally {
    liking.value = null
  }
}

function timeAgo(dateStr) {
  if (!dateStr) return ''
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1)  return '방금 전'
  if (mins < 60) return `${mins}분 전`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours}시간 전`
  return `${Math.floor(hours / 24)}일 전`
}

async function loadProfiles() {
  loading.value = true
  passedIds.value = new Set()
  visibleCount.value = 9
  try {
    const { data } = await axios.get('/api/match/browse')
    profiles.value = data.map(p => ({
      ...p,
      age: p.birth_year ? new Date().getFullYear() - p.birth_year : null,
    }))
  } catch (e) {
    console.error('browse error:', e?.response?.data || e)
  } finally {
    loading.value = false
  }
}

async function loadLikes() {
  likesLoading.value = true
  try {
    const { data } = await axios.get('/api/match/likes?type=received')
    receivedLikes.value = data
    likedCount.value = data.length
  } catch (e) { } finally {
    likesLoading.value = false
  }
}

async function loadMatches() {
  matchesLoading.value = true
  try {
    const { data } = await axios.get('/api/match/matches')
    myMatches.value = data
    matchCount.value = data.length
  } catch (e) { } finally {
    matchesLoading.value = false
  }
}

onMounted(async () => {
  // Load my profile
  try {
    const { data } = await axios.get('/api/match/profile')
    if (data?.id) myProfile.value = data
  } catch (e) { } finally {
    profileLoading.value = false
  }
  // Load everything in parallel
  await Promise.all([loadProfiles(), loadLikes(), loadMatches()])
})
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
