<template>
  <div class="min-h-screen bg-gray-50 pb-24">

    <!-- 헤더 배너 (rounded card style) -->
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 py-6 rounded-2xl relative">
        <div class="flex items-center gap-4">
          <!-- 아바타 -->
          <div class="relative flex-shrink-0">
            <div class="w-20 h-20 rounded-full bg-white/20 border-4 border-white/50 overflow-hidden flex items-center justify-center text-3xl font-black text-white shadow-lg">
              <img v-if="avatarPreview || auth.user?.avatar" :src="avatarPreview || auth.user?.avatar" class="w-full h-full object-cover" />
              <span v-else class="text-white">{{ (auth.user?.name || '?')[0]?.toUpperCase() }}</span>
            </div>
            <label class="absolute bottom-0 right-0 w-7 h-7 bg-white rounded-full flex items-center justify-center cursor-pointer shadow-md hover:bg-gray-100 transition">
              <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
              <input type="file" accept="image/*" class="hidden" @change="onAvatarSelect" />
            </label>
          </div>
          <div class="flex-1 min-w-0">
            <h1 class="text-white font-black text-xl truncate">{{ auth.user?.name }}</h1>
            <p class="text-blue-200 text-sm">@{{ auth.user?.username }}</p>
            <div class="flex items-center gap-2 mt-1">
              <span class="bg-white/20 text-white text-xs px-2 py-0.5 rounded-full font-medium">{{ auth.user?.level || '씨앗' }}</span>
              <span class="text-yellow-300 text-xs font-bold">{{ (auth.user?.points_total ?? 0).toLocaleString() }}P</span>
            </div>
          </div>
        </div>

        <!-- 업로드 중 표시 -->
        <div v-if="avatarUploading" class="absolute inset-0 bg-black/40 flex items-center justify-center rounded-2xl">
          <div class="text-white text-sm font-bold">사진 업로드 중...</div>
        </div>
      </div>
    </div>

    <!-- 통계 카드 -->
    <div class="max-w-[1200px] mx-auto px-4 mt-3">
      <div class="bg-white rounded-2xl shadow-lg p-4 grid grid-cols-5 gap-2 text-center mb-5">
        <div>
          <div class="text-xl font-black text-blue-600">{{ stats.posts }}</div>
          <div class="text-xs text-gray-500">게시글</div>
        </div>
        <div>
          <div class="text-xl font-black text-green-600">{{ stats.comments }}</div>
          <div class="text-xs text-gray-500">댓글</div>
        </div>
        <div>
          <div class="text-xl font-black text-red-500">{{ stats.shorts || 0 }}</div>
          <div class="text-xs text-gray-500">숏츠</div>
        </div>
        <div>
          <div class="text-xl font-black text-purple-600">{{ (auth.user?.points_total ?? 0).toLocaleString() }}</div>
          <div class="text-xs text-gray-500">포인트</div>
        </div>
        <div>
          <div class="text-xl font-black text-orange-600">{{ stats.likes }}</div>
          <div class="text-xs text-gray-500">좋아요</div>
        </div>
      </div>
    </div>

    <!-- 탭 -->
    <div class="max-w-[1200px] mx-auto px-4">
      <div class="flex bg-white rounded-2xl shadow-sm mb-4 overflow-x-auto">
        <button v-for="tab in tabs" :key="tab.id"
          @click="activeTab = tab.id"
          class="flex-shrink-0 flex-1 py-3 text-xs font-semibold transition whitespace-nowrap px-2"
          :class="activeTab === tab.id
            ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50/50'
            : 'text-gray-500 hover:text-gray-700'">
          {{ tab.icon }} {{ tab.label }}
        </button>
      </div>

      <!-- ── 프로필 탭 ── -->
      <div v-if="activeTab === 'profile'" class="space-y-4">
        <div class="bg-white rounded-2xl shadow-sm p-5 space-y-4">
          <h2 class="font-bold text-gray-800 text-sm">기본 정보</h2>

          <div>
            <label class="block text-xs text-gray-500 mb-1">이름 <span class="text-red-400">*</span></label>
            <input v-model="profileForm.name" type="text" maxlength="50"
              class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">자기소개</label>
            <textarea v-model="profileForm.bio" rows="3" maxlength="300" placeholder="간단히 소개해 주세요"
              class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400 resize-none" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">지역</label>
            <select v-model="profileForm.region"
              class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400">
              <option value="">선택하세요</option>
              <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
            </select>
          </div>

          <div v-if="profileMsg" class="text-sm rounded-xl p-3" :class="profileMsg.ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'">
            {{ profileMsg.text }}
          </div>
          <button @click="saveProfile" :disabled="profileSaving"
            class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 disabled:opacity-50 transition">
            {{ profileSaving ? '저장 중...' : '프로필 저장' }}
          </button>
        </div>

        <!-- 비밀번호 변경 -->
        <div class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
          <h2 class="font-bold text-gray-800 text-sm">비밀번호 변경</h2>
          <input v-model="pwForm.current" type="password" placeholder="현재 비밀번호"
            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400" />
          <input v-model="pwForm.password" type="password" placeholder="새 비밀번호 (8자 이상)"
            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400" />
          <input v-model="pwForm.password_confirmation" type="password" placeholder="새 비밀번호 확인"
            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400" />
          <div v-if="pwMsg" class="text-sm rounded-xl p-3" :class="pwMsg.ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'">{{ pwMsg.text }}</div>
          <button @click="changePassword" :disabled="pwSaving"
            class="w-full bg-gray-700 text-white font-bold py-3 rounded-xl hover:bg-gray-800 disabled:opacity-50 transition">
            {{ pwSaving ? '변경 중...' : '비밀번호 변경' }}
          </button>
        </div>

        <!-- 거리 설정 -->
        <div class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
          <h2 class="font-bold text-gray-800 text-sm">📍 기본 검색 거리</h2>
          <p class="text-xs text-gray-500">모든 페이지에서 이 거리를 기준으로 검색됩니다</p>
          <select v-model="defaultRadius" @change="saveRadius"
            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm">
            <option :value="5">5마일</option>
            <option :value="10">10마일</option>
            <option :value="20">20마일</option>
            <option :value="30">30마일 (기본)</option>
            <option :value="50">50마일</option>
            <option :value="100">100마일</option>
            <option :value="0">미국 전체</option>
          </select>
        </div>
      </div>

      <!-- ── 매칭 프로필 탭 ── -->
      <div v-if="activeTab === 'match'" class="space-y-4">
        <div class="bg-gradient-to-r from-pink-50 to-rose-50 border border-pink-200 rounded-2xl p-4 flex items-center gap-3">
          <span class="text-3xl">💝</span>
          <div>
            <p class="font-bold text-gray-800 text-sm">매칭 프로필</p>
            <p class="text-xs text-gray-500">이성 교제 · 친구 · 네트워킹을 위한 프로필입니다</p>
          </div>
          <span v-if="matchProfile?.verified" class="ml-auto bg-green-100 text-green-600 text-xs px-2 py-1 rounded-full font-bold">✓ 인증</span>
        </div>

        <!-- 매칭 사진 (최대 6장) -->
        <div class="bg-white rounded-2xl shadow-sm p-5 mb-4">
          <h2 class="font-bold text-gray-800 text-sm mb-3">📸 매칭 사진</h2>
          <p class="text-xs text-gray-500 mb-3">3장까지 무료, 추가 3장은 50P가 필요합니다</p>
          <div class="grid grid-cols-3 gap-3">
            <div v-for="i in 6" :key="i" class="relative">
              <!-- Has photo -->
              <div v-if="matchPhotos[i-1]" class="aspect-square rounded-xl overflow-hidden relative group">
                <img :src="matchPhotos[i-1]" class="w-full h-full object-cover" />
                <button @click="removeMatchPhoto(i-1)" class="absolute top-1 right-1 w-6 h-6 bg-red-500 text-white rounded-full text-xs opacity-0 group-hover:opacity-100 transition">✕</button>
              </div>
              <!-- Empty slot (free) -->
              <label v-else-if="i <= 3" class="aspect-square rounded-xl border-2 border-dashed border-gray-300 flex flex-col items-center justify-center cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition">
                <span class="text-2xl text-gray-400">+</span>
                <span class="text-xs text-gray-400 mt-1">사진 {{ i }}</span>
                <input type="file" accept="image/*" class="hidden" @change="uploadMatchPhoto($event, i-1)" />
              </label>
              <!-- Locked slot (needs points) -->
              <div v-else class="aspect-square rounded-xl border-2 border-dashed border-gray-200 flex flex-col items-center justify-center bg-gray-50">
                <span class="text-xl">🔒</span>
                <span class="text-xs text-gray-400 mt-1">+50P</span>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-5 space-y-4">
          <div>
            <label class="block text-xs text-gray-500 mb-1">매칭용 닉네임 <span class="text-red-400">*</span></label>
            <input v-model="matchForm.nickname" type="text" maxlength="30" placeholder="상대방에게 보여질 이름"
              class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-pink-400" />
          </div>

          <div>
            <label class="block text-xs text-gray-500 mb-2">성별 <span class="text-red-400">*</span></label>
            <div class="grid grid-cols-3 gap-2">
              <button v-for="g in [{v:'male',l:'남성 👨'},{v:'female',l:'여성 👩'},{v:'other',l:'기타'}]" :key="g.v"
                @click="matchForm.gender = g.v"
                class="py-3 rounded-xl font-semibold text-sm transition"
                :class="matchForm.gender === g.v ? 'bg-pink-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                {{ g.l }}
              </button>
            </div>
          </div>

          <div>
            <label class="block text-xs text-gray-500 mb-1">출생연도</label>
            <input v-model="matchForm.birth_year" type="number" min="1950" :max="new Date().getFullYear()-18" placeholder="예: 1990"
              class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-pink-400" />
          </div>

          <div>
            <label class="block text-xs text-gray-500 mb-1">지역</label>
            <select v-model="matchForm.region"
              class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-pink-400">
              <option value="">선택하세요</option>
              <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs text-gray-500 mb-1">자기소개</label>
            <textarea v-model="matchForm.bio" rows="3" maxlength="300" placeholder="본인을 소개해 주세요"
              class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-pink-400 resize-none" />
          </div>

          <div>
            <label class="block text-xs text-gray-500 mb-2">관심사 (최대 5개)</label>
            <div class="flex flex-wrap gap-2 mb-2">
              <button v-for="tag in interestOptions" :key="tag"
                @click="toggleInterest(tag)"
                class="px-3 py-1.5 rounded-full text-xs font-semibold transition"
                :class="matchForm.interests.includes(tag)
                  ? 'bg-pink-500 text-white'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                {{ tag }}
              </button>
            </div>
          </div>

          <div>
            <label class="block text-xs text-gray-500 mb-2">상대방 나이 범위</label>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <p class="text-xs text-gray-400 mb-1">최소</p>
                <input v-model="matchForm.age_range_min" type="number" min="18" max="80"
                  class="w-full border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none" />
              </div>
              <div>
                <p class="text-xs text-gray-400 mb-1">최대</p>
                <input v-model="matchForm.age_range_max" type="number" min="18" max="80"
                  class="w-full border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none" />
              </div>
            </div>
          </div>

          <div v-if="matchMsg" class="text-sm rounded-xl p-3" :class="matchMsg.ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'">
            {{ matchMsg.text }}
          </div>
          <button @click="saveMatch" :disabled="matchSaving"
            class="w-full bg-pink-500 text-white font-bold py-3 rounded-xl hover:bg-pink-600 disabled:opacity-50 transition">
            {{ matchSaving ? '저장 중...' : '매칭 프로필 저장' }}
          </button>

          <router-link to="/match/browse"
            class="block w-full text-center bg-rose-50 text-rose-600 font-bold py-3 rounded-xl hover:bg-rose-100 transition">
            💝 매칭 탐색 시작하기
          </router-link>
        </div>
      </div>

      <!-- ── 활동 탭 ── -->
      <div v-if="activeTab === 'activity'" class="space-y-4">
        <!-- 내 게시글 -->
        <div class="bg-white rounded-2xl shadow-sm p-5">
          <div class="flex items-center justify-between mb-3">
            <h2 class="font-bold text-gray-800 text-sm">📝 내 게시글</h2>
            <router-link to="/community" class="text-blue-600 text-xs">더보기</router-link>
          </div>
          <div v-if="loadingActivity" class="text-center py-4 text-gray-400 text-sm">불러오는 중...</div>
          <div v-else-if="!myPosts.length" class="text-center py-4 text-gray-400 text-sm">게시글이 없습니다</div>
          <div v-else class="space-y-2">
            <router-link v-for="post in myPosts.slice(0,5)" :key="post.id"
              :to="`/community/post/${post.id}`"
              class="flex items-center gap-3 p-2 rounded-xl hover:bg-gray-50 transition">
              <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-800 truncate font-medium">{{ post.title }}</p>
                <p class="text-xs text-gray-400">{{ formatDate(post.created_at) }} · 👁 {{ post.view_count }}</p>
              </div>
              <span class="text-xs text-gray-300">→</span>
            </router-link>
          </div>
        </div>

        <!-- 내 댓글 -->
        <div class="bg-white rounded-2xl shadow-sm p-5">
          <h2 class="font-bold text-gray-800 text-sm mb-3">💬 내 댓글</h2>
          <div v-if="loadingActivity" class="text-center py-4 text-gray-400 text-sm">불러오는 중...</div>
          <div v-else-if="!myComments.length" class="text-center py-4 text-gray-400 text-sm">댓글이 없습니다</div>
          <div v-else class="space-y-2">
            <div v-for="c in myComments.slice(0,5)" :key="c.id" class="p-2 border-b border-gray-100 last:border-0">
              <p class="text-sm text-gray-700 line-clamp-2">{{ c.content }}</p>
              <p class="text-xs text-gray-400 mt-1">{{ c.post?.title }} · {{ formatDate(c.created_at) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- ── 포인트 탭 ── -->
      <div v-if="activeTab === 'points'" class="space-y-4">
        <!-- 요약 -->
        <div class="grid grid-cols-2 gap-3">
          <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-5 text-white">
            <p class="text-blue-100 text-xs mb-1">보유 포인트</p>
            <p class="text-3xl font-black">{{ (auth.user?.points_total ?? 0).toLocaleString() }}</p>
            <p class="text-blue-200 text-xs">P</p>
          </div>
          <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl p-5 text-white">
            <p class="text-purple-100 text-xs mb-1">등급</p>
            <p class="text-2xl font-black">{{ auth.user?.level || '씨앗' }}</p>
            <p class="text-purple-200 text-xs">{{ levelDesc }}</p>
          </div>
        </div>

        <!-- 출석체크 -->
        <button @click="doCheckin" :disabled="checkedIn || checkingIn"
          class="w-full py-4 rounded-2xl font-bold text-sm transition flex items-center justify-center gap-2"
          :class="checkedIn ? 'bg-gray-100 text-gray-400' : 'bg-yellow-400 text-yellow-900 hover:bg-yellow-300'">
          <span>{{ checkedIn ? '✅ 오늘 출석 완료' : '📅 출석 체크 (+10P)' }}</span>
        </button>

        <!-- 포인트 내역 -->
        <div class="bg-white rounded-2xl shadow-sm p-5">
          <h2 class="font-bold text-gray-800 text-sm mb-3">포인트 내역</h2>
          <div v-if="loadingPoints" class="text-center py-4 text-gray-400 text-sm">불러오는 중...</div>
          <div v-else-if="!pointLogs.length" class="text-center py-4 text-gray-400 text-sm">내역이 없습니다</div>
          <div v-else class="space-y-2">
            <div v-for="log in pointLogs" :key="log.id"
              class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
              <div>
                <p class="text-sm text-gray-700 font-medium">{{ log.description || log.memo || '포인트 변동' }}</p>
                <p class="text-xs text-gray-400">{{ formatDate(log.created_at) }}</p>
              </div>
              <span class="font-bold text-sm" :class="log.type === 'earn' ? 'text-green-600' : 'text-red-500'">
                {{ log.type === 'earn' ? '+' : '-' }}{{ log.amount.toLocaleString() }}P
              </span>
            </div>
          </div>

          <div class="mt-4 flex gap-2">
            <router-link to="/points"
              class="flex-1 text-center bg-blue-50 text-blue-600 text-sm font-bold py-3 rounded-xl hover:bg-blue-100">
              상세 내역 보기
            </router-link>
            <router-link to="/games/shop"
              class="flex-1 text-center bg-purple-50 text-purple-600 text-sm font-bold py-3 rounded-xl hover:bg-purple-100">
              포인트샵 →
            </router-link>
          </div>
        </div>
      </div>

      <!-- ── 결제 탭 ── -->
      <div v-if="activeTab === 'payment'" class="space-y-4">
        <div class="bg-white rounded-2xl shadow-sm p-6 text-center">
          <div class="text-5xl mb-3">💳</div>
          <h2 class="font-bold text-gray-800 mb-2">결제 정보</h2>
          <p class="text-gray-500 text-sm mb-6">신용카드, PayPal 등 결제 수단을 등록하고<br>프리미엄 서비스를 이용하세요</p>

          <!-- 플랜 카드들 -->
          <div class="grid grid-cols-1 gap-3 text-left mb-6">
            <div class="border-2 border-gray-200 rounded-2xl p-4">
              <div class="flex items-center justify-between mb-2">
                <span class="font-bold text-gray-800">무료 플랜</span>
                <span class="text-green-600 font-bold">현재</span>
              </div>
              <ul class="text-xs text-gray-500 space-y-1">
                <li>✅ 커뮤니티, 구인구직, 업소록 이용</li>
                <li>✅ 기본 채팅</li>
                <li>✅ 게임 (기본)</li>
                <li>❌ 매칭 무제한 좋아요</li>
                <li>❌ 광고 제거</li>
              </ul>
            </div>
            <div class="border-2 border-blue-400 rounded-2xl p-4 bg-blue-50/50 relative">
              <div class="absolute -top-2 right-4 bg-blue-600 text-white text-xs px-2 py-0.5 rounded-full font-bold">추천</div>
              <div class="flex items-center justify-between mb-2">
                <span class="font-bold text-blue-700">프리미엄</span>
                <span class="font-black text-blue-700">$9.99/월</span>
              </div>
              <ul class="text-xs text-gray-600 space-y-1">
                <li>✅ 무료 플랜 모든 기능</li>
                <li>✅ 매칭 무제한</li>
                <li>✅ 광고 없음</li>
                <li>✅ 매월 500P 지급</li>
                <li>✅ 우선 고객지원</li>
              </ul>
            </div>
          </div>

          <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-3 text-left mb-4">
            <p class="text-yellow-800 font-bold text-sm">🚧 준비 중</p>
            <p class="text-yellow-700 text-xs mt-0.5">결제 시스템은 현재 개발 중입니다. 출시 시 알려드리겠습니다.</p>
          </div>

          <button disabled class="w-full bg-gray-200 text-gray-400 font-bold py-3 rounded-xl cursor-not-allowed">
            곧 출시 예정
          </button>
        </div>

        <!-- 영수증 -->
        <div class="bg-white rounded-2xl shadow-sm p-5">
          <h2 class="font-bold text-gray-800 text-sm mb-3">💰 결제 내역</h2>
          <div class="text-center py-6 text-gray-400 text-sm">결제 내역이 없습니다</div>
        </div>
      </div>

      <!-- ── 관리 탭 ── -->
      <div v-if="activeTab === 'manage'" class="space-y-4">
        <!-- Sub-tabs for content types -->
        <div class="flex gap-2 overflow-x-auto pb-2">
          <button v-for="st in manageTabs" :key="st.id"
            @click="manageSubTab = st.id"
            class="flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold transition"
            :class="manageSubTab === st.id ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border'">
            {{ st.icon }} {{ st.label }}
          </button>
        </div>

        <!-- 숏츠 그리드 뷰 -->
        <div v-if="manageSubTab === 'shorts'" class="bg-white rounded-2xl shadow-sm p-4">
          <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold text-gray-800 text-sm">내가 공유한 숏츠</h3>
            <RouterLink to="/shorts/upload" class="text-xs bg-red-500 text-white px-3 py-1 rounded-full hover:bg-red-600">+ 새 숏츠</RouterLink>
          </div>
          <div v-if="manageLoading" class="text-center py-8 text-gray-400">불러오는 중...</div>
          <div v-else-if="manageItems.length === 0" class="text-center py-8">
            <div class="text-4xl mb-2">📱</div>
            <p class="text-gray-400 text-sm">아직 공유한 숏츠가 없습니다</p>
            <RouterLink to="/shorts/upload" class="inline-block mt-3 bg-red-500 text-white px-4 py-2 rounded-xl text-sm font-bold">숏츠 공유하기</RouterLink>
          </div>
          <div v-else class="grid grid-cols-3 gap-2">
            <div v-for="item in manageItems" :key="item.id"
              class="relative aspect-[9/16] rounded-lg overflow-hidden bg-black cursor-pointer group">
              <img v-if="item.thumbnail" :src="item.thumbnail" class="w-full h-full object-cover opacity-80" />
              <div v-else class="w-full h-full flex items-center justify-center text-2xl">
                {{ item.platform === 'youtube' ? '▶️' : item.platform === 'tiktok' ? '🎵' : '📸' }}
              </div>
              <!-- 오버레이 -->
              <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-2">
                <a :href="item.url" target="_blank" class="text-white text-xs bg-white/20 px-2 py-1 rounded">원본</a>
                <button @click="deleteManageItem(item.id)" class="text-red-400 text-xs bg-white/20 px-2 py-1 rounded">삭제</button>
              </div>
              <!-- 좋아요 수 -->
              <div class="absolute bottom-1 left-1 text-white text-[10px] flex items-center gap-0.5">
                <svg class="w-3 h-3 fill-current text-red-400" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                {{ item.like_count }}
              </div>
            </div>
          </div>
        </div>

        <!-- Content list (non-clubs, non-shorts) -->
        <div v-if="manageSubTab !== 'clubs' && manageSubTab !== 'shorts'" class="bg-white rounded-2xl shadow-sm p-5">
          <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold text-gray-800 text-sm">
              {{ manageTabs.find(t=>t.id===manageSubTab)?.icon }} {{ manageTabs.find(t=>t.id===manageSubTab)?.label }}
            </h3>
          </div>
          <div v-if="manageLoading" class="text-center py-8 text-gray-400">불러오는 중...</div>
          <div v-else-if="manageItems.length === 0" class="text-center py-8 text-gray-400">항목이 없습니다</div>
          <div v-else class="space-y-3">
            <div v-for="item in manageItems" :key="item.id"
              class="flex items-center gap-3 p-3 border border-gray-100 rounded-xl hover:bg-gray-50">
              <div class="flex-1 min-w-0">
                <p class="font-semibold text-gray-800 text-sm truncate">{{ item.title || item.name || item.content }}</p>
                <div class="flex items-center gap-2 mt-0.5">
                  <p class="text-xs text-gray-400">{{ formatDate(item.created_at) }}</p>
                  <span v-if="item.view_count" class="text-xs text-gray-300">조회 {{ item.view_count }}</span>
                  <span v-if="item.like_count" class="text-xs text-red-400">♥ {{ item.like_count }}</span>
                </div>
              </div>
              <div class="flex gap-2 flex-shrink-0">
                <button class="text-xs text-blue-600 hover:underline">수정</button>
                <button @click="deleteManageItem(item.id)" class="text-xs text-red-500 hover:underline">삭제</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Club management -->
        <div v-if="manageSubTab === 'clubs'" class="space-y-4">
          <div v-if="clubsLoading" class="bg-white rounded-2xl shadow-sm p-5 text-center py-8 text-gray-400">불러오는 중...</div>
          <div v-else-if="myClubs.length === 0" class="bg-white rounded-2xl shadow-sm p-5 text-center py-8">
            <div class="text-4xl mb-2">👥</div>
            <p class="text-gray-400 text-sm">가입한 동호회가 없습니다</p>
            <router-link to="/clubs" class="inline-block mt-3 bg-blue-600 text-white px-4 py-2 rounded-xl text-sm font-bold hover:bg-blue-700">동호회 둘러보기</router-link>
          </div>

          <div v-else v-for="club in myClubs" :key="club.id" class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <!-- Club header -->
            <div class="p-4 flex items-center gap-3 cursor-pointer hover:bg-gray-50 transition" @click="toggleClubExpand(club)">
              <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">
                {{ club.icon || '👥' }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <p class="font-bold text-gray-800 text-sm truncate">{{ club.name }}</p>
                  <span v-if="club.role === 'owner'" class="text-[10px] bg-yellow-100 text-yellow-700 px-1.5 py-0.5 rounded-full font-bold">방장</span>
                  <span v-else-if="club.role === 'admin'" class="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full font-bold">관리자</span>
                  <span v-else class="text-[10px] bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full font-bold">멤버</span>
                </div>
                <p class="text-xs text-gray-400">멤버 {{ club.member_count || 0 }}명</p>
              </div>
              <svg class="w-5 h-5 text-gray-400 transition" :class="expandedClub === club.id ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </div>

            <!-- Expanded management panel (owner/admin only) -->
            <div v-if="expandedClub === club.id && (club.role === 'owner' || club.role === 'admin')" class="border-t border-gray-100 p-4 space-y-4">

              <!-- Edit form -->
              <div class="bg-gray-50 rounded-xl p-4 space-y-3">
                <h3 class="font-bold text-gray-800 text-sm">&#x2699;&#xFE0F; 동호회 정보 수정</h3>
                <div>
                  <label class="block text-xs text-gray-500 mb-1">동호회 이름</label>
                  <input v-model="clubEditForms[club.id].name" type="text" maxlength="50"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400" />
                </div>
                <div>
                  <label class="block text-xs text-gray-500 mb-1">설명</label>
                  <textarea v-model="clubEditForms[club.id].description" rows="3" maxlength="500"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400 resize-none" />
                </div>
                <button @click="saveClubEdit(club.id)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-700">저장</button>
              </div>

              <!-- Pending requests -->
              <div v-if="clubPendingRequests[club.id]?.length" class="bg-yellow-50 rounded-xl p-4">
                <h3 class="font-bold text-gray-800 text-sm mb-3">&#x1F514; 가입 요청 ({{ clubPendingRequests[club.id].length }})</h3>
                <div class="space-y-2">
                  <div v-for="req in clubPendingRequests[club.id]" :key="req.id"
                    class="flex items-center gap-3 bg-white rounded-lg p-3">
                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-xs font-bold flex-shrink-0">
                      {{ req.user?.name?.[0] || '?' }}
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-semibold text-gray-800 truncate">{{ req.user?.name }}</p>
                      <p v-if="req.message" class="text-xs text-gray-500 truncate">{{ req.message }}</p>
                    </div>
                    <div class="flex gap-2 flex-shrink-0">
                      <button @click="approveRequest(club.id, req.id)" class="bg-green-500 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-green-600">승인</button>
                      <button @click="rejectRequest(club.id, req.id)" class="bg-red-500 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-red-600">거절</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Member management -->
              <div class="bg-gray-50 rounded-xl p-4">
                <h3 class="font-bold text-gray-800 text-sm mb-3">&#x1F465; 멤버 관리</h3>
                <div v-if="!clubMembers[club.id]?.length" class="text-center py-4 text-gray-400 text-sm">멤버가 없습니다</div>
                <div v-else class="space-y-2">
                  <div v-for="member in clubMembers[club.id]" :key="member.id"
                    class="flex items-center gap-3 bg-white rounded-lg p-3">
                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-xs font-bold flex-shrink-0">
                      {{ member.user?.name?.[0] || member.name?.[0] || '?' }}
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-semibold text-gray-800 truncate">{{ member.user?.name || member.name }}</p>
                    </div>
                    <select v-if="member.role !== 'owner'" @change="changeMemberRole(club.id, member.id, $event.target.value)"
                      :value="member.role"
                      class="border border-gray-200 rounded-lg px-2 py-1 text-xs bg-white">
                      <option value="member">멤버</option>
                      <option value="admin">관리자</option>
                    </select>
                    <span v-else class="text-xs text-yellow-600 font-bold">방장</span>
                    <button v-if="member.role !== 'owner'" @click="kickMember(club.id, member.id)"
                      class="text-xs text-red-500 hover:text-red-700 font-bold flex-shrink-0">강퇴</button>
                  </div>
                </div>
              </div>

              <!-- Actions -->
              <div class="flex gap-2">
                <router-link :to="`/clubs/${club.id}`" class="flex-1 bg-blue-50 text-blue-600 text-center py-2.5 rounded-xl text-sm font-bold hover:bg-blue-100">&#x1F4DD; 공지 작성</router-link>
                <button v-if="club.role === 'owner'" @click="deleteClub(club.id)" class="flex-1 bg-red-50 text-red-500 py-2.5 rounded-xl text-sm font-bold hover:bg-red-100">&#x1F5D1;&#xFE0F; 동호회 삭제</button>
              </div>
            </div>

            <!-- Expanded panel for regular members -->
            <div v-else-if="expandedClub === club.id && club.role === 'member'" class="border-t border-gray-100 p-4">
              <div class="flex gap-2">
                <router-link :to="`/clubs/${club.id}`" class="flex-1 bg-blue-50 text-blue-600 text-center py-2.5 rounded-xl text-sm font-bold hover:bg-blue-100">동호회 보기</router-link>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /max-w-[1200px] -->
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useRoute } from 'vue-router'
import axios from 'axios'

const auth = useAuthStore()
const route = useRoute()

// ── 탭 ────────────────────────────────────────────────────────────────────────
const activeTab = ref(route.query.tab || 'profile')
const tabs = [
  { id: 'profile',  icon: '👤', label: '프로필' },
  { id: 'match',    icon: '💝', label: '매칭' },
  { id: 'activity', icon: '📝', label: '활동' },
  { id: 'points',   icon: '⭐', label: '포인트' },
  { id: 'payment',  icon: '💳', label: '결제' },
  { id: 'manage',   icon: '⚙️', label: '관리' },
]

const regions = [
  'Los Angeles', 'New York', 'Atlanta', 'Chicago', 'Seattle',
  'Dallas', 'Houston', 'San Francisco', 'Boston', 'Miami',
  'Washington DC', 'Las Vegas', 'Phoenix', 'San Diego', 'Denver',
  'Portland', 'Nashville', 'Austin', 'New Jersey', 'Virginia',
]

const interestOptions = [
  '여행', '요리', '운동', '음악', '독서', '영화', '게임', '사진',
  '등산', '쇼핑', '커피', '봉사', '재테크', '언어공부', '육아', '반려동물',
]

// ── 아바타 ────────────────────────────────────────────────────────────────────
const avatarPreview = ref('')
const avatarUploading = ref(false)

async function onAvatarSelect(e) {
  const file = e.target.files[0]
  if (!file) return
  if (file.size > 3 * 1024 * 1024) { alert('3MB 이하 이미지만 가능합니다'); return }

  // 미리보기
  const reader = new FileReader()
  reader.onload = ev => { avatarPreview.value = ev.target.result }
  reader.readAsDataURL(file)

  // 업로드
  avatarUploading.value = true
  try {
    const fd = new FormData()
    fd.append('avatar', file)
    fd.append('name', auth.user?.name || '')
    const { data } = await axios.post('/api/profile/avatar', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    auth.user = { ...auth.user, avatar: data.avatar }
    avatarPreview.value = data.avatar
  } catch (e) {
    alert('사진 업로드 실패: ' + (e.response?.data?.message || e.message))
    avatarPreview.value = ''
  } finally {
    avatarUploading.value = false
  }
}

// ── 프로필 ────────────────────────────────────────────────────────────────────
const profileForm = ref({ name: '', bio: '', region: '' })
const profileSaving = ref(false)
const profileMsg    = ref(null)

const pwForm = ref({ current: '', password: '', password_confirmation: '' })
const pwSaving = ref(false)
const pwMsg    = ref(null)

async function saveProfile() {
  profileSaving.value = true
  profileMsg.value    = null
  try {
    const { data } = await axios.put('/api/profile', profileForm.value)
    auth.user = { ...auth.user, ...data.user }
    profileMsg.value = { ok: true, text: '✅ 프로필이 저장되었습니다!' }
  } catch (e) {
    profileMsg.value = { ok: false, text: e.response?.data?.message || '저장 실패' }
  } finally {
    profileSaving.value = false
    setTimeout(() => profileMsg.value = null, 3000)
  }
}

async function changePassword() {
  if (pwForm.value.password !== pwForm.value.password_confirmation) {
    pwMsg.value = { ok: false, text: '비밀번호가 일치하지 않습니다' }; return
  }
  pwSaving.value = true; pwMsg.value = null
  try {
    await axios.post('/api/profile/password', pwForm.value)
    pwMsg.value = { ok: true, text: '✅ 비밀번호가 변경되었습니다!' }
    pwForm.value = { current: '', password: '', password_confirmation: '' }
  } catch (e) {
    pwMsg.value = { ok: false, text: e.response?.data?.message || '변경 실패' }
  } finally {
    pwSaving.value = false
    setTimeout(() => pwMsg.value = null, 3000)
  }
}

// ── 매칭 프로필 ───────────────────────────────────────────────────────────────
const matchProfile = ref(null)
const matchForm    = ref({ nickname: '', gender: 'male', birth_year: '', region: '', bio: '', interests: [], age_range_min: 20, age_range_max: 50, visibility: 'public' })
const matchSaving  = ref(false)
const matchMsg     = ref(null)
const matchPhotos  = ref([])

async function uploadMatchPhoto(e, idx) {
  const file = e.target.files[0]
  if (!file) return
  const fd = new FormData()
  fd.append('photo', file)
  fd.append('index', idx)
  try {
    const { data } = await axios.post('/api/match/photos', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    matchPhotos.value[idx] = data.url
  } catch (e) {
    alert('사진 업로드 실패')
  }
}

function removeMatchPhoto(idx) {
  matchPhotos.value.splice(idx, 1)
}

function toggleInterest(tag) {
  const idx = matchForm.value.interests.indexOf(tag)
  if (idx >= 0) matchForm.value.interests.splice(idx, 1)
  else if (matchForm.value.interests.length < 5) matchForm.value.interests.push(tag)
}

async function saveMatch() {
  if (!matchForm.value.nickname.trim()) {
    matchMsg.value = { ok: false, text: '닉네임을 입력해주세요' }; return
  }
  matchSaving.value = true; matchMsg.value = null
  try {
    const { data } = await axios.post('/api/match/profile', matchForm.value)
    matchProfile.value = data
    matchMsg.value = { ok: true, text: '✅ 매칭 프로필이 저장되었습니다!' }
  } catch (e) {
    matchMsg.value = { ok: false, text: e.response?.data?.message || '저장 실패' }
  } finally {
    matchSaving.value = false
    setTimeout(() => matchMsg.value = null, 3000)
  }
}

// ── 활동 ──────────────────────────────────────────────────────────────────────
const loadingActivity = ref(false)
const myPosts    = ref([])
const myComments = ref([])
const stats      = ref({ posts: 0, comments: 0, likes: 0 })

async function loadActivity() {
  if (myPosts.value.length) return
  loadingActivity.value = true
  try {
    const [postsRes, commentsRes] = await Promise.all([
      axios.get('/api/profile/me/posts'),
      axios.get('/api/profile/me/comments'),
    ])
    myPosts.value    = postsRes.data.data || postsRes.data
    myComments.value = commentsRes.data.data || commentsRes.data
    stats.value.posts    = myPosts.value.length
    stats.value.comments = myComments.value.length
    // 숏츠 카운트 로드
    try {
      const { data: sh } = await axios.get('/api/shorts/my?page=1')
      stats.value.shorts = sh.total || (sh.data?.length ?? 0)
    } catch {}
  } catch (e) {}
  loadingActivity.value = false
}

// ── 포인트 ────────────────────────────────────────────────────────────────────
const loadingPoints = ref(false)
const pointLogs     = ref([])
const checkedIn     = ref(false)
const checkingIn    = ref(false)

const levelDesc = computed(() => {
  const map = { '씨앗': '500P 달성 시 새싹', '새싹': '2000P 달성 시 나무', '나무': '5000P 달성 시 숲', '숲': '상위 레벨', '참나무': '최고 레벨' }
  return map[auth.user?.level] || ''
})

async function loadPoints() {
  if (pointLogs.value.length) return
  loadingPoints.value = true
  try {
    const [histRes, balRes] = await Promise.allSettled([
      axios.get('/api/points/history'),
      axios.get('/api/points/balance'),
    ])
    if (histRes.status === 'fulfilled') pointLogs.value = histRes.value.data.data || histRes.value.data || []
    if (balRes.status === 'fulfilled') checkedIn.value = balRes.value.data.checked_in_today ?? false
  } catch (e) {}
  loadingPoints.value = false
}

async function doCheckin() {
  checkingIn.value = true
  try {
    const { data } = await axios.post('/api/points/checkin')
    checkedIn.value = true
    auth.user = { ...auth.user, points_total: (auth.user?.points_total ?? 0) + (data.points ?? 10) }
    pointLogs.value.unshift({ type: 'earn', amount: data.points ?? 10, description: '출석 체크', created_at: new Date().toISOString() })
  } catch (e) {
    if (e.response?.status === 409) checkedIn.value = true
  } finally {
    checkingIn.value = false
  }
}

function formatDate(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleDateString('ko-KR', { month: 'short', day: 'numeric' })
}

// ── 관리 탭 ──────────────────────────────────────────────────────────────────
const manageSubTab = ref('posts')
const manageTabs = [
  { id: 'posts',    icon: '📝', label: '게시글' },
  { id: 'comments', icon: '💬', label: '댓글' },
  { id: 'jobs',     icon: '💼', label: '구인구직' },
  { id: 'market',   icon: '🛒', label: '중고장터' },
  { id: 'shorts',   icon: '📱', label: '내 숏츠' },
  { id: 'news',     icon: '📰', label: '공유뉴스' },
  { id: 'bookmarks',icon: '🔖', label: '북마크' },
  { id: 'clubs',    icon: '👥', label: '내 동호회' },
]
const manageItems = ref([])
const manageLoading = ref(false)

async function loadManageItems() {
  if (manageSubTab.value === 'clubs') { loadMyClubs(); return }
  manageLoading.value = true
  const endpoints = {
    posts:     '/api/profile/me/posts',
    comments:  '/api/profile/me/comments',
    jobs:      '/api/jobs?mine=1',
    market:    '/api/market?mine=1',
    shorts:    '/api/shorts/my',
    news:      '/api/news?mine=1',
    bookmarks: '/api/bookmarks',
  }
  try {
    const { data } = await axios.get(endpoints[manageSubTab.value])
    manageItems.value = data.data || data || []
  } catch { manageItems.value = [] }
  manageLoading.value = false
}

// ── 동호회 관리 ─────────────────────────────────────────────────────────────
const myClubs = ref([])
const clubsLoading = ref(false)
const expandedClub = ref(null)
const clubEditForms = ref({})
const clubMembers = ref({})
const clubPendingRequests = ref({})

async function loadMyClubs() {
  clubsLoading.value = true
  try {
    const { data } = await axios.get('/api/clubs/my')
    myClubs.value = data.data || data || []
  } catch { myClubs.value = [] }
  clubsLoading.value = false
}

function toggleClubExpand(club) {
  if (expandedClub.value === club.id) { expandedClub.value = null; return }
  expandedClub.value = club.id
  if (!clubEditForms.value[club.id]) {
    clubEditForms.value[club.id] = { name: club.name, description: club.description || '' }
  }
  loadClubMembers(club.id)
  loadClubPending(club.id)
}

async function loadClubMembers(clubId) {
  try {
    const { data } = await axios.get(`/api/clubs/${clubId}/members`)
    clubMembers.value[clubId] = data.data || data || []
  } catch { clubMembers.value[clubId] = [] }
}

async function loadClubPending(clubId) {
  try {
    const { data } = await axios.get(`/api/clubs/${clubId}/requests`)
    clubPendingRequests.value[clubId] = data.data || data || []
  } catch { clubPendingRequests.value[clubId] = [] }
}

async function saveClubEdit(clubId) {
  try {
    await axios.put(`/api/clubs/${clubId}`, clubEditForms.value[clubId])
    const club = myClubs.value.find(c => c.id === clubId)
    if (club) { club.name = clubEditForms.value[clubId].name; club.description = clubEditForms.value[clubId].description }
    alert('저장되었습니다')
  } catch (e) { alert('저장 실패: ' + (e.response?.data?.message || e.message)) }
}

async function changeMemberRole(clubId, memberId, role) {
  try {
    await axios.put(`/api/clubs/${clubId}/members/${memberId}`, { role })
    const members = clubMembers.value[clubId]
    const m = members?.find(x => x.id === memberId)
    if (m) m.role = role
  } catch { alert('역할 변경 실패') }
}

async function kickMember(clubId, memberId) {
  if (!confirm('이 회원을 강퇴하시겠습니까?')) return
  try {
    await axios.delete(`/api/clubs/${clubId}/members/${memberId}`)
    clubMembers.value[clubId] = (clubMembers.value[clubId] || []).filter(m => m.id !== memberId)
  } catch { alert('강퇴 실패') }
}

async function approveRequest(clubId, requestId) {
  try {
    await axios.post(`/api/clubs/${clubId}/requests/${requestId}/approve`)
    clubPendingRequests.value[clubId] = (clubPendingRequests.value[clubId] || []).filter(r => r.id !== requestId)
    loadClubMembers(clubId)
  } catch { alert('승인 실패') }
}

async function rejectRequest(clubId, requestId) {
  try {
    await axios.post(`/api/clubs/${clubId}/requests/${requestId}/reject`)
    clubPendingRequests.value[clubId] = (clubPendingRequests.value[clubId] || []).filter(r => r.id !== requestId)
  } catch { alert('거절 실패') }
}

async function deleteClub(clubId) {
  if (!confirm('정말로 이 동호회를 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.')) return
  if (!confirm('마지막 확인: 동호회와 모든 데이터가 영구 삭제됩니다. 계속하시겠습니까?')) return
  try {
    await axios.delete(`/api/clubs/${clubId}`)
    myClubs.value = myClubs.value.filter(c => c.id !== clubId)
    expandedClub.value = null
  } catch { alert('삭제 실패') }
}

async function deleteManageItem(id) {
  if (!confirm('삭제할까요?')) return
  const endpoints = {
    posts: `/api/posts/${id}`,
    comments: `/api/comments/${id}`,
    jobs: `/api/jobs/${id}`,
    market: `/api/market/${id}`,
    bookmarks: `/api/bookmarks/${id}`,
  }
  try {
    await axios.delete(endpoints[manageSubTab.value])
    manageItems.value = manageItems.value.filter(i => i.id !== id)
  } catch (e) { alert('삭제 실패') }
}

// ── 거리 설정 ────────────────────────────────────────────────────────────────
const defaultRadius = ref(auth.user?.default_radius || 30)
async function saveRadius() {
  try {
    await axios.put('/api/profile', { default_radius: defaultRadius.value })
    auth.user = { ...auth.user, default_radius: defaultRadius.value }
  } catch {}
}

// ── 탭 변경 시 데이터 로드 ────────────────────────────────────────────────────
import { watch } from 'vue'
watch(manageSubTab, loadManageItems)
watch(activeTab, (tab) => {
  if (tab === 'activity') loadActivity()
  if (tab === 'points')   loadPoints()
  if (tab === 'manage')   loadManageItems()
})

// ── 초기화 ────────────────────────────────────────────────────────────────────
onMounted(async () => {
  const u = auth.user
  profileForm.value = { name: u?.name || '', bio: u?.bio || '', region: u?.region || '' }

  // 매칭 프로필 로드
  try {
    const { data } = await axios.get('/api/match/profile')
    if (data) {
      matchProfile.value = data
      matchForm.value = {
        nickname:       data.nickname || '',
        gender:         data.gender || 'male',
        birth_year:     data.birth_year || '',
        region:         data.region || '',
        bio:            data.bio || '',
        interests:      Array.isArray(data.interests) ? data.interests : [],
        age_range_min:  data.age_range_min || 20,
        age_range_max:  data.age_range_max || 50,
        visibility:     data.visibility || 'public',
      }
    }
  } catch (e) {}

  // 통계 & 포인트 미리 로드
  loadActivity()
  loadPoints()
})
</script>
