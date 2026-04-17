<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- Title link (like ClubList) -->
    <router-link to="/clubs" class="text-xl font-black text-gray-800 mb-3 inline-block hover:text-amber-600 transition">
      👥 동호회
    </router-link>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-20">
      <div class="inline-block w-8 h-8 border-4 border-amber-400 border-t-transparent rounded-full animate-spin"></div>
      <p class="text-sm text-gray-400 mt-3">로딩중...</p>
    </div>

    <!-- Main content -->
    <div v-else-if="club" class="grid grid-cols-12 gap-4">
      <!-- Left sidebar: Category list (like ClubList) -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 카테고리</div>
          <router-link v-for="c in clubCategories" :key="c.value"
            :to="c.value ? `/clubs?category=${c.value}` : '/clubs'"
            class="w-full text-left px-3 py-2 text-xs transition block"
            :class="club.category === c.value ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">
            {{ c.label }}
          </router-link>
          <router-link v-if="auth.isLoggedIn" to="/clubs?fav=1"
            class="block w-full text-left px-3 py-2 text-xs transition border-t text-gray-600 hover:bg-red-50/50">
            🔖 내 북마크
          </router-link>
          <template v-if="auth.isLoggedIn && myClubs.length">
            <div class="px-3 py-2.5 border-t border-b font-bold text-xs text-amber-900 mt-1">👤 내 동호회</div>
            <router-link v-for="mc in myClubs" :key="mc.id" :to="`/clubs/${mc.id}`"
              class="block w-full text-left px-3 py-2 text-xs transition truncate"
              :class="mc.id === club.id ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">
              {{ mc.name }}
            </router-link>
          </template>
          <AdSlot page="clubs" position="left" :maxSlots="1" />
        </div>
      </div>

      <!-- Center content -->
      <div class="col-span-12 lg:col-span-7">
        <!-- Club header / banner -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
          <!-- Cover image -->
          <div class="h-36 sm:h-48 bg-gradient-to-r from-amber-400 to-orange-400 relative">
            <img v-if="club.cover_image" :src="coverImageUrl" class="w-full h-full object-cover"
              @error="$event.target.style.display='none'" />
          </div>
          <div class="px-5 pb-5 -mt-8 relative">
            <div class="flex items-end gap-4">
              <!-- Club avatar -->
              <div class="w-16 h-16 bg-white rounded-xl shadow-lg flex items-center justify-center text-3xl border-2 border-white overflow-hidden flex-shrink-0">
                <img v-if="club.image" :src="imageUrl" class="w-full h-full object-cover" @error="$event.target.parentElement.innerHTML = categoryEmoji" />
                <span v-else>{{ categoryEmoji }}</span>
              </div>
              <div class="flex-1 min-w-0 pt-8">
                <div class="flex items-center gap-2">
                  <h1 class="text-lg font-black text-gray-900 truncate flex-1">{{ club.name }}</h1>
                  <BookmarkToggle v-if="auth.isLoggedIn" :active="clubFavorited" @toggle="toggleClubFav" size="lg" class="flex-shrink-0" />
                </div>
                <div class="flex items-center gap-3 mt-1 text-xs text-gray-400 flex-wrap">
                  <span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 font-semibold">{{ categoryLabel }}</span>
                  <span :class="club.type === 'online' ? 'text-blue-600' : 'text-green-600'" class="font-medium">
                    {{ club.type === 'online' ? '온라인' : '지역 모임' }}
                  </span>
                  <span v-if="club.city">{{ club.city }}, {{ club.state }}</span>
                  <span>{{ club.member_count || 0 }}명</span>
                  <span v-if="club.max_members > 0" class="text-gray-300">/ {{ club.max_members }}명</span>
                </div>
              </div>
            </div>

            <!-- Description: collapsible for members, full for non-members -->
            <div v-if="club.description" class="mt-3">
              <template v-if="isMember">
                <div class="relative">
                  <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line"
                    :class="{ 'line-clamp-2': !descExpanded }">{{ club.description }}</p>
                  <button @click="descExpanded = !descExpanded"
                    class="text-xs text-amber-600 hover:text-amber-700 font-semibold mt-1 inline-block">
                    {{ descExpanded ? '접기' : '더보기' }}
                  </button>
                </div>
              </template>
              <template v-else>
                <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line">{{ club.description }}</p>
              </template>
            </div>

            <!-- Action buttons -->
            <div class="flex items-center gap-2 mt-4 flex-wrap">
              <template v-if="auth.isLoggedIn">
                <button v-if="myStatus === 'pending'" disabled
                  class="bg-gray-200 text-gray-500 font-bold px-6 py-2.5 rounded-xl text-sm cursor-not-allowed">
                  ⏳ 승인 대기 중
                </button>
                <button v-else-if="!isMember" @click="joinClub"
                  class="bg-amber-400 text-amber-900 font-bold px-6 py-2.5 rounded-xl text-sm hover:bg-amber-500 shadow-md shadow-amber-200 transition">
                  가입 신청
                </button>
                <button v-else-if="!isOwner" @click="leaveClub"
                  class="bg-gray-100 text-gray-600 font-semibold px-5 py-2 rounded-xl text-sm hover:bg-gray-200 transition">
                  탈퇴하기
                </button>
                <span v-if="isMember" class="px-3 py-1.5 rounded-full text-xs font-bold"
                  :class="gradeStyle(myGrade)">
                  {{ gradeLabel(myGrade) }}
                </span>
              </template>
              <template v-else>
                <RouterLink to="/login" class="bg-amber-400 text-amber-900 font-bold px-6 py-2.5 rounded-xl text-sm hover:bg-amber-500 shadow-md shadow-amber-200 transition">
                  로그인 후 가입
                </RouterLink>
              </template>
            </div>
          </div>
        </div>

        <!-- Rules section (show for non-members, or if club has rules) -->
        <div v-if="club.rules && (!isMember || activeTab === 'board')" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
          <div class="px-5 py-3 border-b bg-amber-50/50">
            <h3 class="text-sm font-bold text-amber-900 flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
              동호회 규칙
            </h3>
          </div>
          <div class="px-5 py-4 text-sm text-gray-600 leading-relaxed whitespace-pre-line">{{ club.rules }}</div>
        </div>

        <!-- Horizontal tab buttons (inside center content) -->
        <div v-if="isMember" class="flex items-center gap-1 mb-4 bg-white rounded-xl shadow-sm border border-gray-100 px-3 py-2 overflow-x-auto">
          <button @click="activeTab = 'board'"
            class="px-4 py-2 rounded-lg text-xs font-bold transition whitespace-nowrap"
            :class="activeTab === 'board' ? 'bg-amber-400 text-amber-900' : 'text-gray-500 hover:bg-amber-50'">
            게시판
          </button>
          <button @click="activeTab = 'members'; loadMembers()"
            class="px-4 py-2 rounded-lg text-xs font-bold transition whitespace-nowrap"
            :class="activeTab === 'members' ? 'bg-amber-400 text-amber-900' : 'text-gray-500 hover:bg-amber-50'">
            회원목록
          </button>
          <button v-if="isAdmin" @click="activeTab = 'settings'; loadPendingMembers()"
            class="px-4 py-2 rounded-lg text-xs font-bold transition whitespace-nowrap relative"
            :class="activeTab === 'settings' ? 'bg-amber-400 text-amber-900' : 'text-gray-500 hover:bg-amber-50'">
            설정
            <span v-if="pendingMembers.length" class="absolute -top-1 -right-1 bg-red-500 text-white text-[9px] font-bold w-4 h-4 rounded-full flex items-center justify-center">{{ pendingMembers.length }}</span>
          </button>
          <button v-if="chatRoomId" @click="chatStore.openRoom({ id: chatRoomId, name: club.name + ' 채팅', type: 'club' })"
            class="px-4 py-2 rounded-lg text-xs font-bold transition whitespace-nowrap ml-auto bg-amber-100 text-amber-700 hover:bg-amber-200">
            💬 채팅
          </button>
        </div>

        <!-- ====== NON-MEMBER VIEW: Recent posts preview ====== -->
        <div v-if="!isMember" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="px-5 py-3 border-b font-bold text-sm text-amber-900 flex items-center justify-between">
            <span>최근 게시글</span>
          </div>
          <div v-if="previewPosts.length">
            <div v-for="post in previewPosts" :key="post.id" class="px-5 py-3 border-b last:border-0 hover:bg-amber-50/30 transition">
              <div class="text-sm font-medium text-gray-800">{{ post.title }}</div>
              <div class="text-xs text-gray-400 mt-0.5 flex items-center gap-2">
                <UserName :userId="post.user?.id" :name="post.user?.name" />
                <span>{{ formatDate(post.created_at) }}</span>
                <span v-if="post.comment_count" class="text-amber-600">{{ post.comment_count }}개 댓글</span>
              </div>
            </div>
          </div>
          <div v-else class="px-5 py-8 text-center text-sm text-gray-400">
            아직 게시글이 없습니다. 가입하고 첫 글을 작성해보세요!
          </div>
        </div>

        <!-- ====== MEMBER VIEW: Board Tab ====== -->
        <div v-if="isMember && activeTab === 'board'" class="space-y-4">
          <!-- Board selector -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-3 border-b flex items-center justify-between flex-wrap gap-2">
              <div class="flex items-center gap-2 flex-wrap">
                <button @click="selectedBoard = null; loadPosts()"
                  class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
                  :class="!selectedBoard ? 'bg-amber-400 text-amber-900' : 'bg-gray-100 text-gray-500 hover:bg-amber-50'">
                  전체
                </button>
                <button v-for="board in boards" :key="board.id"
                  @click="selectedBoard = board; loadPosts()"
                  class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
                  :class="selectedBoard?.id === board.id ? 'bg-amber-400 text-amber-900' : 'bg-gray-100 text-gray-500 hover:bg-amber-50'">
                  {{ board.name }}
                </button>
              </div>
              <button @click="showWritePost = !showWritePost"
                class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500 transition flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                글쓰기
              </button>
            </div>

            <!-- Write post form (inline) -->
            <div v-if="showWritePost" class="px-5 py-4 bg-amber-50/50 border-b space-y-3">
              <select v-model="newPost.board_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-amber-400">
                <option value="" disabled>게시판 선택</option>
                <option v-for="b in boards" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
              <input v-model="newPost.title" type="text" placeholder="제목을 입력하세요"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-amber-400" />
              <textarea v-model="newPost.content" rows="4" placeholder="내용을 입력하세요..."
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none resize-none focus:ring-2 focus:ring-amber-400"></textarea>
              <!-- Image upload -->
              <div>
                <label class="flex items-center gap-2 cursor-pointer text-xs text-gray-500 hover:text-amber-700 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  사진 첨부
                  <input type="file" multiple accept="image/*" class="hidden" @change="handlePostImages" />
                </label>
                <div v-if="postImagePreviews.length" class="flex flex-wrap gap-2 mt-2">
                  <div v-for="(preview, idx) in postImagePreviews" :key="idx" class="relative group">
                    <img :src="preview" class="w-16 h-16 object-cover rounded-lg border border-gray-200" />
                    <button @click="removePostImage(idx)" type="button"
                      class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                      &times;
                    </button>
                  </div>
                </div>
              </div>
              <div class="flex gap-2">
                <button @click="submitPost" :disabled="postSubmitting"
                  class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-xs hover:bg-amber-500 disabled:opacity-50 transition">
                  {{ postSubmitting ? '등록 중...' : '등록하기' }}
                </button>
                <button @click="showWritePost = false" class="text-gray-500 px-4 py-2 text-xs">취소</button>
              </div>
              <div v-if="postError" class="text-red-500 text-xs">{{ postError }}</div>
            </div>

            <!-- Posts list (single-line BBS style) -->
            <div v-if="postsLoading" class="py-8 text-center text-sm text-gray-400">
              <div class="inline-block w-5 h-5 border-2 border-amber-400 border-t-transparent rounded-full animate-spin"></div>
            </div>
            <div v-else-if="posts.length">
              <!-- BBS header row -->
              <div class="hidden sm:flex items-center px-5 py-2 border-b bg-gray-50 text-[10px] font-bold text-gray-400 uppercase">
                <span class="flex-1">제목</span>
                <span class="w-20 text-center">작성자</span>
                <span class="w-16 text-center">날짜</span>
                <span class="w-10 text-center">댓글</span>
                <span class="w-12"></span>
              </div>
              <div v-for="post in posts" :key="post.id">
                <!-- Post row (single line) -->
                <div @click="togglePost(post)"
                  class="flex items-center px-5 py-2.5 border-b cursor-pointer hover:bg-amber-50/30 transition"
                  :class="{ 'bg-amber-50/50': expandedPost === post.id }">
                  <div class="flex-1 min-w-0 flex items-center gap-2">
                    <span v-if="post.board_name" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-500 flex-shrink-0">{{ post.board_name }}</span>
                    <span class="text-sm font-medium text-gray-800 truncate">{{ post.title }}</span>
                  </div>
                  <span class="hidden sm:block w-20 text-center text-xs text-gray-500 truncate flex-shrink-0">
                    <UserName :userId="post.user?.id" :name="post.user?.name" />
                  </span>
                  <span class="hidden sm:block w-16 text-center text-[11px] text-gray-400 flex-shrink-0">{{ formatDate(post.created_at) }}</span>
                  <span class="hidden sm:block w-10 text-center text-xs flex-shrink-0"
                    :class="post.comment_count ? 'text-amber-600 font-semibold' : 'text-gray-300'">
                    {{ post.comment_count || 0 }}
                  </span>
                  <div class="w-12 flex items-center justify-end gap-1 flex-shrink-0">
                    <button v-if="canDeletePost(post)" @click.stop="deletePost(post)"
                      class="text-gray-300 hover:text-red-500 p-0.5 rounded hover:bg-red-50 transition"
                      title="삭제">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                    <svg class="w-3.5 h-3.5 text-gray-300 transition-transform" :class="{ 'rotate-180': expandedPost === post.id }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                  </div>
                </div>

                <!-- Expanded post content (inline) -->
                <div v-if="expandedPost === post.id" class="px-5 py-4 bg-gray-50 border-b">
                  <!-- Mobile meta (shown only on mobile since header row is hidden) -->
                  <div class="sm:hidden text-xs text-gray-400 mb-2 flex items-center gap-2">
                    <UserName :userId="post.user?.id" :name="post.user?.name" />
                    <span>{{ formatDate(post.created_at) }}</span>
                    <span v-if="post.comment_count" class="text-amber-600 font-semibold">{{ post.comment_count }}개 댓글</span>
                  </div>
                  <div class="text-sm text-gray-700 leading-relaxed whitespace-pre-line mb-4">{{ post.content }}</div>
                  <!-- Post images -->
                  <div v-if="post.images && post.images.length" class="flex flex-wrap gap-2 mb-4">
                    <a v-for="(img, idx) in post.images" :key="idx"
                      :href="img.url || `/storage/${img.path}`" target="_blank"
                      class="block w-20 h-20 rounded-lg overflow-hidden border border-gray-200 hover:border-amber-400 transition flex-shrink-0">
                      <img :src="img.url || `/storage/${img.path}`" class="w-full h-full object-cover" @error="$event.target.parentElement.style.display='none'" />
                    </a>
                  </div>
                  <CommentSection :type="'club_post'" :typeId="post.id" />
                </div>
              </div>
            </div>
            <div v-else class="px-5 py-8 text-center text-sm text-gray-400">게시글이 없습니다</div>

            <!-- Posts pagination -->
            <div v-if="postsMeta.last_page > 1" class="px-4 py-3 border-t">
              <Pagination :page="postsPage" :lastPage="postsMeta.last_page" @page="onPostPage" />
            </div>
          </div>
        </div>

        <!-- ====== MEMBER VIEW: Members Tab ====== -->
        <div v-if="isMember && activeTab === 'members'">
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-3 border-b font-bold text-sm text-amber-900 flex items-center justify-between">
              <span>회원 목록 ({{ members.length }}명)</span>
            </div>
            <div v-if="membersLoading" class="py-8 text-center text-sm text-gray-400">
              <div class="inline-block w-5 h-5 border-2 border-amber-400 border-t-transparent rounded-full animate-spin"></div>
            </div>
            <div v-else class="divide-y">
              <div v-for="member in members" :key="member.id"
                class="px-5 py-3 flex items-center gap-3 hover:bg-gray-50 transition">
                <!-- Avatar -->
                <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center text-amber-700 font-bold text-sm flex-shrink-0 overflow-hidden">
                  <img v-if="member.user?.profile_photo" :src="member.user.profile_photo" class="w-full h-full object-cover"
                    @error="$event.target.style.display='none'" />
                  <span v-else>{{ (member.user?.name || '?').charAt(0) }}</span>
                </div>
                <!-- Info -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2">
                    <UserName :userId="member.user?.id" :name="member.user?.name" class="text-sm font-semibold text-gray-800" />
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="gradeStyle(member.grade)">
                      {{ gradeLabel(member.grade) }}
                    </span>
                  </div>
                  <div class="text-xs text-gray-400">가입일: {{ formatDate(member.created_at) }}</div>
                </div>
                <!-- Admin actions -->
                <div v-if="isAdmin && member.user?.id !== club.user_id" class="flex items-center gap-2 flex-shrink-0">
                  <select :value="member.grade" @change="changeGrade(member, $event.target.value)"
                    class="border border-gray-200 rounded-lg px-2 py-1 text-xs outline-none focus:ring-2 focus:ring-amber-400">
                    <option value="member">일반</option>
                    <option value="admin">관리자</option>
                  </select>
                  <button @click="kickMember(member)"
                    class="text-red-500 hover:text-red-700 text-xs font-bold px-2 py-1 rounded hover:bg-red-50 transition">
                    강퇴
                  </button>
                </div>
              </div>
            </div>
            <div v-if="!membersLoading && !members.length" class="px-5 py-8 text-center text-sm text-gray-400">
              아직 회원이 없습니다
            </div>
          </div>
        </div>

        <!-- ====== ADMIN VIEW: Settings Tab ====== -->
        <div v-if="isMember && isAdmin && activeTab === 'settings'" class="space-y-4">
          <!-- Club settings -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-3 border-b bg-amber-50">
              <h3 class="text-sm font-bold text-amber-900">동호회 관리</h3>
            </div>
            <div class="p-5 space-y-3">
              <RouterLink :to="`/clubs/${club.id}/edit`"
                class="w-full flex items-center gap-3 px-4 py-3 rounded-xl border border-gray-200 hover:border-amber-400 hover:bg-amber-50/50 transition">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                <div>
                  <div class="text-sm font-semibold text-gray-800">동호회 정보 수정</div>
                  <div class="text-xs text-gray-400">이름, 소개, 규칙, 이미지 등 변경</div>
                </div>
              </RouterLink>
            </div>
          </div>

          <!-- Board management -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-3 border-b bg-amber-50 flex items-center justify-between">
              <h3 class="text-sm font-bold text-amber-900">게시판 관리</h3>
              <button @click="showAddBoard = !showAddBoard"
                class="text-xs font-bold text-amber-700 hover:text-amber-900 transition">
                + 게시판 추가
              </button>
            </div>
            <div class="p-5 space-y-3">
              <!-- Add board form -->
              <div v-if="showAddBoard" class="flex gap-2 mb-3">
                <input v-model="newBoardName" type="text" placeholder="게시판 이름 (예: 자유게시판)"
                  class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-amber-400" />
                <button @click="createBoard" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-xs hover:bg-amber-500 transition">
                  추가
                </button>
              </div>

              <!-- Board list -->
              <div v-for="board in boards" :key="board.id"
                class="flex items-center justify-between px-4 py-3 rounded-xl border border-gray-100 hover:border-amber-200 transition">
                <div v-if="editingBoard?.id === board.id" class="flex-1 flex gap-2">
                  <input v-model="editingBoard.name" type="text"
                    class="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-amber-400" />
                  <button @click="updateBoard" class="text-amber-700 font-bold text-xs px-2">저장</button>
                  <button @click="editingBoard = null" class="text-gray-400 text-xs px-2">취소</button>
                </div>
                <div v-else class="flex items-center gap-2">
                  <span class="text-sm font-medium text-gray-700">{{ board.name }}</span>
                </div>
                <div v-if="editingBoard?.id !== board.id" class="flex items-center gap-1">
                  <button @click="editingBoard = { ...board }" class="text-gray-400 hover:text-amber-600 text-xs px-2 py-1">수정</button>
                  <button @click="deleteBoard(board)" class="text-gray-400 hover:text-red-500 text-xs px-2 py-1">삭제</button>
                </div>
              </div>

              <div v-if="!boards.length" class="text-center text-sm text-gray-400 py-4">
                아직 게시판이 없습니다. 게시판을 추가해주세요.
              </div>
            </div>
          </div>

          <!-- Pending members -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-3 border-b bg-amber-50 flex items-center justify-between">
              <h3 class="text-sm font-bold text-amber-900">가입 승인 대기</h3>
              <button @click="loadPendingMembers" class="text-xs text-amber-700 hover:text-amber-900 font-bold transition">
                새로고침
              </button>
            </div>
            <div v-if="pendingMembersLoading" class="py-6 text-center">
              <div class="inline-block w-5 h-5 border-2 border-amber-400 border-t-transparent rounded-full animate-spin"></div>
            </div>
            <div v-else-if="pendingMembers.length" class="divide-y">
              <div v-for="pm in pendingMembers" :key="pm.id" class="px-5 py-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-amber-100 flex items-center justify-center text-amber-700 font-bold text-sm flex-shrink-0 overflow-hidden">
                  <img v-if="pm.user?.profile_photo" :src="pm.user.profile_photo" class="w-full h-full object-cover" @error="$event.target.style.display='none'" />
                  <span v-else>{{ (pm.user?.name || '?').charAt(0) }}</span>
                </div>
                <div class="flex-1 min-w-0">
                  <UserName :userId="pm.user?.id" :name="pm.user?.name" class="text-sm font-semibold text-gray-800" />
                  <div class="text-xs text-gray-400">{{ formatDate(pm.created_at) }}</div>
                </div>
                <div class="flex items-center gap-1.5 flex-shrink-0">
                  <button @click="approveMember(pm)"
                    class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500 transition">
                    승인
                  </button>
                  <button @click="rejectMember(pm)"
                    class="bg-gray-100 text-gray-600 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-red-50 hover:text-red-600 transition">
                    거절
                  </button>
                </div>
              </div>
            </div>
            <div v-else class="px-5 py-6 text-center text-sm text-gray-400">
              대기 중인 가입 신청이 없습니다
            </div>
          </div>

          <!-- Chat room -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-3 border-b bg-amber-50">
              <h3 class="text-sm font-bold text-amber-900">채팅방</h3>
            </div>
            <div class="p-5 space-y-2">
              <button v-if="chatRoomId" @click="chatStore.openRoom({ id: chatRoomId, name: club.name + ' 채팅', type: 'club' })"
                class="w-full flex items-center gap-3 px-4 py-3 rounded-xl border border-amber-300 bg-amber-50 hover:bg-amber-100 transition">
                <span class="text-xl">💬</span>
                <div>
                  <div class="text-sm font-semibold text-amber-800">채팅방 열기</div>
                  <div class="text-xs text-amber-600">동호회 채팅방이 있습니다</div>
                </div>
              </button>
              <button v-else @click="createChatRoom"
                class="w-full flex items-center gap-3 px-4 py-3 rounded-xl border border-gray-200 hover:border-amber-400 hover:bg-amber-50/50 transition">
                <span class="text-xl">➕</span>
                <div>
                  <div class="text-sm font-semibold text-gray-800">채팅방 만들기</div>
                  <div class="text-xs text-gray-400">회원들이 실시간으로 대화할 수 있는 채팅방</div>
                </div>
              </button>
            </div>
          </div>
        </div>

        <!-- General comment section (for club page itself, non-member view) -->
        <CommentSection v-if="!isMember && club.id" :type="'club'" :typeId="club.id" class="mt-4" />
      </div>

      <!-- Right sidebar -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block">
        <!-- Club info card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-3 sticky top-20">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">동호회 정보</div>
          <div class="p-3 space-y-2">
            <div class="flex items-center justify-between text-xs">
              <span class="text-gray-400">카테고리</span>
              <span class="font-semibold text-gray-700">{{ categoryLabel }}</span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="text-gray-400">유형</span>
              <span class="font-semibold" :class="club.type === 'online' ? 'text-blue-600' : 'text-green-600'">
                {{ club.type === 'online' ? '온라인' : '지역' }}
              </span>
            </div>
            <div v-if="club.city" class="flex items-center justify-between text-xs">
              <span class="text-gray-400">위치</span>
              <span class="font-semibold text-gray-700">{{ club.city }}, {{ club.state }}</span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="text-gray-400">회원</span>
              <span class="font-semibold text-gray-700">
                {{ club.member_count || 0 }}명
                <span v-if="club.max_members > 0" class="text-gray-400 font-normal">/ {{ club.max_members }}</span>
              </span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="text-gray-400">공개</span>
              <span class="font-semibold" :class="club.is_public ? 'text-green-600' : 'text-red-500'">
                {{ club.is_public ? '공개' : '비공개' }}
              </span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="text-gray-400">개설일</span>
              <span class="font-semibold text-gray-700">{{ formatDate(club.created_at) }}</span>
            </div>
            <div v-if="club.owner" class="flex items-center justify-between text-xs">
              <span class="text-gray-400">운영자</span>
              <UserName :userId="club.owner?.id || club.user_id" :name="club.owner?.name || '운영자'" class="font-semibold text-gray-700" />
            </div>
          </div>
        </div>

        <SidebarWidgets mode="detail" :currentCategory="club?.category || ''" api-url="/api/clubs" detail-path="/clubs/" :current-id="club.id"
          label="동호회" />

        <AdSlot page="clubs" position="right" :maxSlots="2" class="mt-3" />
      </div>
    </div>

    <!-- Not found -->
    <div v-else-if="!loading" class="text-center py-20">
      <div class="text-5xl mb-4">404</div>
      <p class="text-gray-500 mb-4">동호회를 찾을 수 없습니다</p>
      <RouterLink to="/clubs" class="text-amber-600 hover:text-amber-700 font-semibold text-sm">동호회 목록으로</RouterLink>
    </div>
  </div>

  <!-- 동호회 채팅 자동 열기 (글로벌 팝업 사용) -->
</div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import CommentSection from '../../components/CommentSection.vue'
import AdSlot from '../../components/AdSlot.vue'
import Pagination from '../../components/Pagination.vue'
import { useChatStore } from '../../stores/chat'
import axios from 'axios'
import BookmarkToggle from '../../components/BookmarkToggle.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const chatStore = useChatStore()
const siteStore = useSiteStore()

// Club data
const club = ref(null)
const loading = ref(true)
const clubFavorited = ref(false)
const isMember = ref(false)
const myGrade = ref('member')
const myStatus = ref(null)
const myClubs = ref([])
const chatRoomId = ref(null)
const showChatOnCreate = ref(false)

// Description accordion
const descExpanded = ref(false)

// Tabs
const activeTab = ref('board')

// Boards
const boards = ref([])

// Posts
const posts = ref([])
const previewPosts = ref([])
const postsLoading = ref(false)
const postsPage = ref(1)
const postsMeta = ref({ last_page: 1 })
const selectedBoard = ref(null)
const expandedPost = ref(null)

// Write post
const showWritePost = ref(false)
const newPost = ref({ board_id: '', title: '', content: '' })
const postSubmitting = ref(false)
const postError = ref('')

// Members
const members = ref([])
const membersLoading = ref(false)

// Pending members
const pendingMembers = ref([])
const pendingMembersLoading = ref(false)

// Post images
const postImages = ref([])
const postImagePreviews = ref([])

// Board management
const showAddBoard = ref(false)
const newBoardName = ref('')
const editingBoard = ref(null)

// Category list (same as ClubList)
const clubCategories = [
  { value: '', label: '전체' },
  { value: '등산', label: '🥾 등산' },
  { value: '스포츠', label: '⚽ 스포츠' },
  { value: '요리', label: '🍳 요리' },
  { value: '게임', label: '🎮 게임' },
  { value: '여행', label: '✈️ 여행' },
  { value: '온라인', label: '🌐 온라인' },
  { value: '기타', label: '📋 기타' },
]

// Category mapping
const categoryMap = {
  hiking: { label: '등산', emoji: '🏔' },
  golf: { label: '골프', emoji: '⛳' },
  tennis: { label: '테니스', emoji: '🎾' },
  bowling: { label: '볼링', emoji: '🎳' },
  books: { label: '독서', emoji: '📚' },
  cooking: { label: '요리', emoji: '🍳' },
  photo: { label: '사진', emoji: '📷' },
  music: { label: '음악', emoji: '🎵' },
  fitness: { label: '운동', emoji: '💪' },
  movie: { label: '영화', emoji: '🎬' },
  gaming: { label: '게임', emoji: '🎮' },
  travel: { label: '여행', emoji: '✈' },
  fishing: { label: '낚시', emoji: '🎣' },
  sports: { label: '운동', emoji: '⚽' },
  tech: { label: '기술', emoji: '💻' },
  finance: { label: '재테크', emoji: '💰' },
  parenting: { label: '육아', emoji: '👶' },
  etc: { label: '기타', emoji: '👥' },
}

const categoryLabel = computed(() => categoryMap[club.value?.category]?.label || club.value?.category || '')
const categoryEmoji = computed(() => categoryMap[club.value?.category]?.emoji || '👥')

const isOwner = computed(() => auth.user?.id === club.value?.user_id)
const isAdmin = computed(() => isOwner.value || myGrade.value === 'admin')

const imageUrl = computed(() => {
  if (!club.value?.image) return null
  return club.value.image.startsWith('http') ? club.value.image : `/storage/${club.value.image}`
})

const coverImageUrl = computed(() => {
  if (!club.value?.cover_image) return null
  return club.value.cover_image.startsWith('http') ? club.value.cover_image : `/storage/${club.value.cover_image}`
})

// Format helpers
function formatDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  const now = new Date()
  const diff = now - d
  if (diff < 60000) return '방금 전'
  if (diff < 3600000) return Math.floor(diff / 60000) + '분 전'
  if (diff < 86400000) return Math.floor(diff / 3600000) + '시간 전'
  if (diff < 604800000) return Math.floor(diff / 86400000) + '일 전'
  return d.toLocaleDateString('ko-KR')
}

function gradeLabel(grade) {
  const map = { owner: '운영자', admin: '관리자', member: '회원' }
  return map[grade] || '회원'
}

function gradeStyle(grade) {
  const map = {
    owner: 'bg-amber-100 text-amber-800',
    admin: 'bg-blue-100 text-blue-800',
    member: 'bg-gray-100 text-gray-600',
  }
  return map[grade] || 'bg-gray-100 text-gray-600'
}

// Data loading
async function loadClub() {
  loading.value = true
  try {
    const { data } = await axios.get(`/api/clubs/${route.params.id}`)
    club.value = data.data
    isMember.value = !!data.is_member
    myGrade.value = data.my_grade || 'member'
    myStatus.value = data.my_status || null
    chatRoomId.value = data.chat_room_id || null

    // If owner, ensure admin
    if (auth.user?.id === club.value?.user_id) {
      myGrade.value = 'owner'
    }
    // 내 동호회 로드
    if (auth.isLoggedIn) {
      try { const { data: mc } = await axios.get('/api/my-clubs'); myClubs.value = mc.data || [] } catch {}
    }
  } catch (err) {
    club.value = null
    if (err.response?.status === 404) router.replace('/404')
  }
  loading.value = false
  // 하트 체크
  if (auth.isLoggedIn && club.value) {
    try {
      const { data: bData } = await axios.get('/api/bookmarks/check', { params: { type: 'App\\Models\\Club', ids: club.value.id } })
      clubFavorited.value = (bData.data || []).includes(club.value.id)
    } catch {}
  }
}

async function toggleClubFav() {
  if (!auth.isLoggedIn || !club.value) return
  try {
    const { data } = await axios.post('/api/bookmarks', { bookmarkable_type: 'App\\Models\\Club', bookmarkable_id: club.value.id })
    clubFavorited.value = data.bookmarked
  } catch {}
}

async function loadBoards() {
  try {
    const { data } = await axios.get(`/api/clubs/${route.params.id}/boards`)
    boards.value = data.data || data || []
  } catch {
    boards.value = []
  }
}

async function loadPosts() {
  postsLoading.value = true
  try {
    const params = { page: postsPage.value }
    if (selectedBoard.value) params.board_id = selectedBoard.value.id
    const url = selectedBoard.value
      ? `/api/clubs/${route.params.id}/boards/${selectedBoard.value.id}/posts`
      : `/api/clubs/${route.params.id}/posts`
    const { data } = await axios.get(url, { params: selectedBoard.value ? { page: postsPage.value } : params })
    const result = data.data
    if (result?.data) {
      posts.value = result.data
      postsMeta.value = { last_page: result.last_page || 1 }
    } else {
      posts.value = Array.isArray(result) ? result : []
      postsMeta.value = { last_page: 1 }
    }
  } catch {
    posts.value = []
  }
  postsLoading.value = false
}

async function loadPreviewPosts() {
  try {
    const { data } = await axios.get(`/api/clubs/${route.params.id}/posts`, { params: { limit: 3 } })
    const result = data.data
    previewPosts.value = result?.data || (Array.isArray(result) ? result.slice(0, 3) : [])
  } catch {
    previewPosts.value = []
  }
}

async function loadMembers() {
  membersLoading.value = true
  try {
    const { data } = await axios.get(`/api/clubs/${route.params.id}/members`)
    members.value = data.data || data || []
  } catch {
    members.value = []
  }
  membersLoading.value = false
}

// Pagination handler
function onPostPage(page) {
  postsPage.value = page
  loadPosts()
}

// Club actions
async function joinClub() {
  try {
    const { data } = await axios.post(`/api/clubs/${club.value.id}/join`)
    if (data.status === 'pending') {
      myStatus.value = 'pending'
      siteStore.toast(data.message || '가입 신청이 접수되었습니다. 관리자 승인을 기다려주세요.', 'info')
    } else {
      isMember.value = true
      myGrade.value = 'member'
      myStatus.value = 'approved'
      club.value.member_count = (club.value.member_count || 0) + 1
      siteStore.toast(data.message || '가입되었습니다!', 'success')
      // Reload boards and posts
      await Promise.all([loadBoards(), loadPosts()])
    }
  } catch (e) {
    siteStore.toast(e.response?.data?.message || '가입에 실패했습니다', 'error')
  }
}

async function leaveClub() {
  if (!confirm('정말 탈퇴하시겠습니까?')) return
  try {
    await axios.post(`/api/clubs/${club.value.id}/leave`)
    isMember.value = false
    myGrade.value = 'member'
    club.value.member_count = Math.max(0, (club.value.member_count || 1) - 1)
    siteStore.toast('탈퇴되었습니다', 'info')
  } catch (e) {
    siteStore.toast(e.response?.data?.message || '실패했습니다', 'error')
  }
}

// Post actions
function togglePost(post) {
  expandedPost.value = expandedPost.value === post.id ? null : post.id
}

async function submitPost() {
  if (!newPost.value.board_id) { postError.value = '게시판을 선택해주세요'; return }
  if (!newPost.value.title.trim()) { postError.value = '제목을 입력해주세요'; return }
  if (!newPost.value.content.trim()) { postError.value = '내용을 입력해주세요'; return }

  postSubmitting.value = true
  postError.value = ''

  try {
    const formData = new FormData()
    formData.append('board_id', newPost.value.board_id)
    formData.append('title', newPost.value.title)
    formData.append('content', newPost.value.content)
    postImages.value.forEach(file => formData.append('images[]', file))

    await axios.post(`/api/clubs/${club.value.id}/posts`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    siteStore.toast('게시글이 등록되었습니다', 'success')
    newPost.value = { board_id: '', title: '', content: '' }
    postImages.value = []
    postImagePreviews.value = []
    showWritePost.value = false
    postsPage.value = 1
    await loadPosts()
  } catch (e) {
    postError.value = e.response?.data?.message || '등록에 실패했습니다'
  }
  postSubmitting.value = false
}

// Member management
async function changeGrade(member, newGrade) {
  try {
    await axios.put(`/api/clubs/${club.value.id}/members/${member.user?.id || member.user_id}`, { grade: newGrade })
    member.grade = newGrade
    siteStore.toast('등급이 변경되었습니다', 'success')
  } catch (e) {
    siteStore.toast(e.response?.data?.message || '변경 실패', 'error')
  }
}

async function kickMember(member) {
  const name = member.user?.name || '이 회원'
  if (!confirm(`${name}님을 강퇴하시겠습니까?`)) return
  try {
    await axios.delete(`/api/clubs/${club.value.id}/members/${member.user?.id || member.user_id}`)
    members.value = members.value.filter(m => m.id !== member.id)
    club.value.member_count = Math.max(0, (club.value.member_count || 1) - 1)
    siteStore.toast('강퇴되었습니다', 'success')
  } catch (e) {
    siteStore.toast(e.response?.data?.message || '실패했습니다', 'error')
  }
}

// Board management
async function createBoard() {
  if (!newBoardName.value.trim()) return
  try {
    const { data } = await axios.post(`/api/clubs/${club.value.id}/boards`, { name: newBoardName.value.trim() })
    boards.value.push(data.data || data)
    newBoardName.value = ''
    showAddBoard.value = false
    siteStore.toast('게시판이 추가되었습니다', 'success')
  } catch (e) {
    siteStore.toast(e.response?.data?.message || '추가 실패', 'error')
  }
}

async function updateBoard() {
  if (!editingBoard.value || !editingBoard.value.name.trim()) return
  try {
    await axios.put(`/api/clubs/${club.value.id}/boards/${editingBoard.value.id}`, { name: editingBoard.value.name.trim() })
    const idx = boards.value.findIndex(b => b.id === editingBoard.value.id)
    if (idx !== -1) boards.value[idx].name = editingBoard.value.name.trim()
    editingBoard.value = null
    siteStore.toast('수정되었습니다', 'success')
  } catch (e) {
    siteStore.toast(e.response?.data?.message || '수정 실패', 'error')
  }
}

async function deleteBoard(board) {
  if (!confirm(`"${board.name}" 게시판을 삭제하시겠습니까? 게시글도 함께 삭제됩니다.`)) return
  try {
    await axios.delete(`/api/clubs/${club.value.id}/boards/${board.id}`)
    boards.value = boards.value.filter(b => b.id !== board.id)
    if (selectedBoard.value?.id === board.id) {
      selectedBoard.value = null
      loadPosts()
    }
    siteStore.toast('삭제되었습니다', 'success')
  } catch (e) {
    siteStore.toast(e.response?.data?.message || '삭제 실패', 'error')
  }
}

// Post image handling
function handlePostImages(e) {
  const files = Array.from(e.target.files)
  files.forEach(file => {
    postImages.value.push(file)
    const reader = new FileReader()
    reader.onload = (ev) => postImagePreviews.value.push(ev.target.result)
    reader.readAsDataURL(file)
  })
  e.target.value = ''
}

function removePostImage(idx) {
  postImages.value.splice(idx, 1)
  postImagePreviews.value.splice(idx, 1)
}

// Post deletion
function canDeletePost(post) {
  if (!auth.user) return false
  if (post.user_id === auth.user.id) return true
  if (post.user?.id === auth.user.id) return true
  return isAdmin.value
}

async function deletePost(post) {
  if (!confirm('이 게시글을 삭제하시겠습니까?')) return
  try {
    await axios.delete(`/api/clubs/posts/${post.id}`)
    posts.value = posts.value.filter(p => p.id !== post.id)
    if (expandedPost.value === post.id) expandedPost.value = null
    siteStore.toast('게시글이 삭제되었습니다', 'success')
  } catch (e) {
    siteStore.toast(e.response?.data?.message || '삭제에 실패했습니다', 'error')
  }
}

// Pending members management
async function loadPendingMembers() {
  pendingMembersLoading.value = true
  try {
    const { data } = await axios.get(`/api/clubs/${route.params.id}/pending-members`)
    pendingMembers.value = data.data || data || []
  } catch {
    pendingMembers.value = []
  }
  pendingMembersLoading.value = false
}

async function approveMember(pm) {
  try {
    await axios.post(`/api/clubs/${club.value.id}/members/${pm.user_id || pm.user?.id}/approve`)
    pendingMembers.value = pendingMembers.value.filter(p => p.id !== pm.id)
    club.value.member_count = (club.value.member_count || 0) + 1
    siteStore.toast(`${pm.user?.name || '회원'}님을 승인했습니다`, 'success')
  } catch (e) {
    siteStore.toast(e.response?.data?.message || '승인에 실패했습니다', 'error')
  }
}

async function rejectMember(pm) {
  if (!confirm(`${pm.user?.name || '이 회원'}의 가입 신청을 거절하시겠습니까?`)) return
  try {
    await axios.post(`/api/clubs/${club.value.id}/members/${pm.user_id || pm.user?.id}/reject`)
    pendingMembers.value = pendingMembers.value.filter(p => p.id !== pm.id)
    siteStore.toast('가입 신청을 거절했습니다', 'info')
  } catch (e) {
    siteStore.toast(e.response?.data?.message || '거절에 실패했습니다', 'error')
  }
}

// Chat room
async function createChatRoom() {
  try {
    const { data } = await axios.post(`/api/clubs/${club.value.id}/chatroom`)
    const roomId = data.data?.id || data.id
    if (roomId) {
      chatRoomId.value = roomId
      chatStore.openRoom({ id: roomId, name: club.value.name + ' 채팅', type: 'club' })
    }
  } catch (e) {
    alert(e.response?.data?.message || '채팅방 생성 실패')
  }
}

// Initialize
onMounted(async () => {
  await loadClub()
  if (club.value) {
    await loadBoards()
    if (isMember.value) {
      await loadPosts()
    } else {
      await loadPreviewPosts()
    }
    // Auto-load pending members for admin
    if (isAdmin.value) {
      loadPendingMembers()
    }
  }
})

// Watch route changes (same component, different ID)
watch(() => route.params.id, async (newId, oldId) => {
  if (newId && newId !== oldId) {
    activeTab.value = 'board'
    expandedPost.value = null
    postsPage.value = 1
    selectedBoard.value = null
    descExpanded.value = false
    await loadClub()
    if (club.value) {
      await loadBoards()
      if (isMember.value) {
        await loadPosts()
      } else {
        await loadPreviewPosts()
      }
    }
  }
})
</script>
