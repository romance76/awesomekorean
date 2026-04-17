<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">

    <div class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 지역 채팅방 목록 (모바일: 채팅방 선택 전에만 표시) -->
      <div class="col-span-12 lg:col-span-3" :class="{ 'hidden': activeRoom && isMobile }">
        <div class="flex items-center justify-between mb-4">
          <h1 class="text-xl font-black text-gray-800">💬 채팅</h1>
          <button @click="showCreate = true" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm hover:bg-amber-500">+ 새 채팅</button>
        </div>
        <!-- 타입 필터 탭 (Issue #21) -->
        <div class="flex gap-1 mb-2 bg-white rounded-lg border border-gray-100 p-1 text-[11px]">
          <button v-for="t in roomFilterTabs" :key="t.value"
            @click="roomFilter = t.value"
            :class="['flex-1 py-1.5 rounded-md font-semibold transition',
              roomFilter===t.value ? 'bg-amber-400 text-amber-900' : 'text-gray-500 hover:bg-amber-50']">
            {{ t.icon }} {{ t.label }} <span class="opacity-60">{{ filterCount(t.value) }}</span>
          </button>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">{{ currentFilterTitle }}</div>
          <div v-if="loading" class="py-4 text-center text-xs text-gray-400">로딩중...</div>
          <button v-for="room in filteredRooms" :key="room.id" @click="selectRoom(room)"
            class="w-full text-left px-3 py-2.5 border-b last:border-0 transition text-xs"
            :class="activeRoom?.id === room.id ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">
            <div class="flex items-center justify-between gap-1">
              <span class="flex-shrink-0 text-[11px]">{{ roomTypeIcon(room.type) }}</span>
              <span class="truncate flex-1">{{ roomDisplayName(room) }}</span>
              <!-- 한번도 안들어간 방: NEW -->
              <span v-if="room.is_new" class="text-[11px] bg-red-500 text-white font-bold px-1 py-0.5 rounded flex-shrink-0">NEW</span>
              <!-- 미읽음 있음: (N) 또는 300+ -->
              <span v-else-if="room.unread_count > 0" class="text-[11px] bg-amber-500 text-white font-bold px-1.5 py-0.5 rounded flex-shrink-0">
                {{ room.unread_count > 300 ? '300+' : room.unread_count }}
              </span>
              <span v-else-if="room.messages?.length" class="w-2 h-2 bg-green-400 rounded-full flex-shrink-0"></span>
            </div>
          </button>
          <div v-if="!filteredRooms.length && !loading" class="px-3 py-4 text-xs text-gray-400 text-center">채팅방 없음</div>
        </div>
      </div>

      <!-- 메인: 채팅 창 -->
      <!-- 모바일: fixed 전체화면 / PC: 그리드 내 -->
      <div v-if="!activeRoom && !isMobile" class="col-span-12 lg:col-span-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
          <div class="text-4xl mb-3">💬</div>
          <div class="text-gray-500 font-semibold">채팅방을 선택해주세요</div>
          <div class="text-xs text-gray-400 mt-1">왼쪽에서 지역 채팅방을 클릭하세요</div>
        </div>
      </div>

      <div v-if="activeRoom" :class="isMobile ? 'fixed left-0 right-0 top-0 bg-white flex flex-col' : 'col-span-12 lg:col-span-6'"
        :style="isMobile ? 'bottom: 56px; z-index: 40;' : ''">
        <div :class="isMobile ? 'flex flex-col h-full overflow-hidden relative' : 'bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex flex-col relative'" :style="isMobile ? '' : 'height: 70vh'">
          <!-- 채팅방 헤더 -->
          <div class="px-4 py-3 border-b bg-amber-50 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-2">
              <button @click="goBackToList" class="lg:hidden text-amber-700 text-lg font-bold mr-1 px-2 py-1 -ml-2" aria-label="뒤로가기">←</button>
              <div class="font-bold text-sm text-amber-900 truncate">{{ roomDisplayName(activeRoom) }}</div>
            </div>
            <div class="flex items-center gap-2">
              <button @click="openMsgSearch" class="text-amber-700 hover:text-amber-900 text-base" title="메시지 검색">🔍</button>
              <span class="text-[10px] text-green-600 bg-green-100 px-2 py-0.5 rounded-full">🟢 공개</span>
            </div>
          </div>

          <!-- 메시지 검색 팝업 -->
          <div v-if="msgSearchOpen" class="border-b bg-white px-3 py-2 flex items-center gap-2 flex-shrink-0">
            <input ref="msgSearchInput" v-model="msgSearchQ" @input="runMsgSearch" @keydown.enter.prevent="navMsgSearch(1)"
              type="text" placeholder="메시지에서 검색..."
              class="flex-1 border rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-amber-400" />
            <span class="text-[10px] text-gray-400 whitespace-nowrap">
              {{ msgSearchResults.length ? (msgSearchIdx+1) + '/' + msgSearchResults.length : '0' }}
            </span>
            <button @click="navMsgSearch(-1)" :disabled="!msgSearchResults.length" class="text-xs w-7 h-7 rounded hover:bg-gray-100 disabled:opacity-30" title="이전">↑</button>
            <button @click="navMsgSearch(1)" :disabled="!msgSearchResults.length" class="text-xs w-7 h-7 rounded hover:bg-gray-100 disabled:opacity-30" title="다음">↓</button>
            <button @click="closeMsgSearch" class="text-gray-500 hover:text-gray-800 text-lg px-1" title="닫기">×</button>
          </div>

          <!-- 📌 활성 공지 배너 -->
          <div v-if="pinnedAnnouncements.length" class="border-b bg-amber-50/80 px-4 py-2 space-y-1.5 flex-shrink-0 max-h-32 overflow-y-auto">
            <div v-for="a in pinnedAnnouncements" :key="'pin-'+a.id" class="flex items-start gap-2">
              <span class="text-amber-600 flex-shrink-0">📢</span>
              <div class="flex-1 min-w-0">
                <div class="text-xs text-amber-900 font-semibold break-words">{{ cleanAnnounceContent(a.content) }}</div>
                <div class="text-[11px] text-amber-600">⏰ {{ timeRemaining(a.pinned_until) }} 남음</div>
              </div>
            </div>
          </div>

          <!-- 메시지 영역 -->
          <div ref="msgArea" class="flex-1 overflow-y-auto px-4 py-3 space-y-3" @scroll="onMsgScroll">
            <template v-for="(msg, idx) in activeMessages" :key="msg.id">
              <!-- 날짜 구분선 -->
              <div v-if="showDateDivider(idx)" class="flex items-center justify-center py-2">
                <div class="h-px bg-gray-200 flex-1"></div>
                <span class="text-[10px] text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full mx-2 whitespace-nowrap">{{ dateLabel(msg.created_at) }}</span>
                <div class="h-px bg-gray-200 flex-1"></div>
              </div>
              <!-- 읽음 경계선 -->
              <div v-if="showUnreadDivider(idx)" class="flex items-center justify-center py-1" :id="'unread-marker-' + activeRoom?.id">
                <div class="h-px bg-red-200 flex-1"></div>
                <span class="text-[10px] text-red-600 bg-red-50 px-2 py-0.5 rounded-full mx-2 whitespace-nowrap font-bold">여기까지 읽음</span>
                <div class="h-px bg-red-200 flex-1"></div>
              </div>
            <div :id="'msg-' + msg.id"
              class="flex" :class="[
                msg.user_id === auth.user?.id ? 'justify-end' : 'justify-start',
                msgSearchResults[msgSearchIdx]?.id === msg.id ? 'ring-2 ring-amber-400 rounded-lg' : '',
              ]">
              <div class="max-w-[75%]">
                <div v-if="msg.user_id !== auth.user?.id" class="text-[10px] mb-0.5 flex items-center gap-1">
                  <span v-if="isAdminUser(msg.user)" class="bg-red-500 text-white px-1.5 py-0.5 rounded-full font-bold">👑 관리자</span>
                  <span v-else class="text-gray-400">{{ msg.user?.nickname || msg.user?.name }}</span>
                </div>
                <!-- 이미지 메시지 -->
                <div v-if="msg.type === 'image' && msg.file_url" class="rounded-xl overflow-hidden max-w-[160px]"
                  :class="isAdminUser(msg.user) ? 'border-2 border-red-300' : ''">
                  <img :src="msg.file_url" @click="lightboxSrc = msg.file_url"
                    class="block w-full h-auto cursor-pointer hover:opacity-90 transition" />
                  <div v-if="msg.content" class="px-2 py-1 text-xs bg-gray-50">{{ msg.content }}</div>
                </div>
                <!-- 압축파일(file) 메시지 -->
                <a v-else-if="msg.type === 'file' && msg.file_url" :href="msg.file_url" target="_blank" download
                  class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm bg-blue-50 border border-blue-200 hover:bg-blue-100 transition no-underline"
                  :class="msg.user_id === auth.user?.id ? 'text-amber-900' : 'text-gray-800'">
                  <span class="text-xl">📦</span>
                  <div class="flex-1 min-w-0">
                    <div class="text-xs font-semibold truncate">{{ msg.content || '파일' }}</div>
                    <div class="text-[10px] text-blue-600">📥 다운로드</div>
                  </div>
                </a>
                <!-- 일반 텍스트 메시지 -->
                <div v-else class="px-3 py-2 rounded-xl text-sm"
                  :class="[
                    msg.user_id === auth.user?.id ? 'bg-amber-400 text-amber-900' : (isAdminUser(msg.user) ? 'bg-red-50 text-red-900 border border-red-200' : 'bg-gray-100 text-gray-800')
                  ]">
                  {{ msg.content }}
                </div>
                <div class="text-[11px] text-gray-300 mt-0.5" :class="msg.user_id === auth.user?.id ? 'text-right' : ''">
                  {{ formatTime(msg.created_at) }}
                </div>
              </div>
            </div>
            </template>
            <div v-if="!activeMessages.length" class="text-center py-8 text-sm text-gray-400">첫 메시지를 보내보세요! 👋</div>
          </div>
          <!-- 자동스크롤 일시정지 알림 -->
          <div v-if="autoScrollPaused && activeMessages.length" class="absolute bottom-20 right-6 z-10">
            <button @click="scrollToBottomForce" class="bg-amber-500 text-white text-xs font-bold px-3 py-1.5 rounded-full shadow-lg hover:bg-amber-600">
              ↓ 최신 메시지로 {{ pausedNewCount ? '('+pausedNewCount+')' : '' }}
            </button>
          </div>

          <!-- 선택된 파일 미리보기 (다중) -->
          <div v-if="selectedFiles.length" class="border-t px-3 py-2 bg-blue-50 flex-shrink-0">
            <div class="flex gap-2 overflow-x-auto">
              <div v-for="(item, idx) in selectedFiles" :key="idx"
                class="flex-shrink-0 relative group">
                <div v-if="item.type === 'image'" class="w-14 h-14 rounded overflow-hidden border border-blue-300 bg-white">
                  <img :src="item.preview" class="w-full h-full object-cover" />
                </div>
                <div v-else class="w-14 h-14 rounded border border-blue-300 bg-white flex flex-col items-center justify-center p-1">
                  <span class="text-lg">📦</span>
                  <span class="text-[10px] text-gray-600 truncate w-full text-center">{{ item.file.name.split('.').pop().toUpperCase() }}</span>
                </div>
                <button @click="removeSelectedFile(idx)"
                  class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-4 h-4 text-[11px] flex items-center justify-center font-bold opacity-0 group-hover:opacity-100 transition">✕</button>
                <div class="text-[10px] text-gray-500 text-center mt-0.5 max-w-[56px] truncate" :title="item.file.name">{{ formatSize(item.file.size) }}</div>
              </div>
            </div>
            <div class="text-[11px] text-blue-700 mt-1">
              📎 {{ selectedFiles.length }}개 선택됨 · 이미지는 자동 압축됨
              <button @click="clearFiles" class="ml-2 text-red-500 font-bold">모두 취소</button>
            </div>
          </div>

          <!-- 입력 (아이폰 safe-area 대응) -->
          <div class="border-t px-3 py-2 flex-shrink-0" style="padding-bottom: max(0.5rem, env(safe-area-inset-bottom));">
            <form @submit.prevent="sendMsg" class="flex gap-2 items-center">
              <label class="bg-gray-100 text-gray-600 w-9 h-9 flex items-center justify-center rounded-full text-sm hover:bg-gray-200 cursor-pointer flex-shrink-0" :class="!auth.isLoggedIn ? 'opacity-50 cursor-not-allowed' : ''" title="이미지·압축파일 첨부">
                📎
                <input type="file" accept="image/*,.zip,.rar,.7z,.tar,.gz,.tgz,application/zip,application/x-rar-compressed,application/x-7z-compressed,application/gzip" multiple @change="onSelectFiles" class="hidden" :disabled="!auth.isLoggedIn" />
              </label>
              <input v-model="newMsg" type="text" :placeholder="auth.isLoggedIn ? '메시지 입력...' : '로그인 후 참여 가능'" :disabled="!auth.isLoggedIn"
                class="flex-1 min-w-0 border rounded-full px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 outline-none disabled:bg-gray-100" />
              <button type="submit" :disabled="(!newMsg.trim() && !selectedFiles.length) || !auth.isLoggedIn || sending"
                class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-full text-sm hover:bg-amber-500 disabled:opacity-50 flex-shrink-0 whitespace-nowrap">{{ sending ? '...' : '전송' }}</button>
            </form>
          </div>
        </div>
      </div>

      <!-- 오른쪽: 참가자 목록 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block space-y-3">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="px-3 py-2 border-b font-bold text-xs text-amber-900 flex items-center justify-between">
            <span>👥 참가자 {{ participants.length ? '(' + participants.length + ')' : '' }}</span>
            <button v-if="activeRoom" @click="loadParticipants" class="text-[10px] text-amber-600 hover:text-amber-800" title="새로고침">🔄</button>
          </div>
          <!-- 참가자 검색 -->
          <div v-if="activeRoom && participants.length > 3" class="px-2 py-1.5 border-b bg-gray-50">
            <input v-model="partSearch" type="text" placeholder="이름 검색..."
              class="w-full border rounded px-2 py-1 text-[11px] outline-none focus:ring-1 focus:ring-amber-400" />
          </div>
          <div v-if="!activeRoom" class="px-3 py-4 text-xs text-gray-400 text-center">채팅방을 선택하세요</div>
          <div v-else-if="participantsLoading" class="px-3 py-4 text-xs text-gray-400 text-center">로딩중...</div>
          <div v-else-if="!filteredParticipants.length" class="px-3 py-4 text-xs text-gray-400 text-center">
            {{ partSearch ? '검색 결과 없음' : '참가자가 없습니다' }}
          </div>
          <div v-else class="max-h-[550px] overflow-y-auto">
            <!-- 내 카드 (상단 고정) -->
            <template v-for="u in filteredParticipants" :key="u.id">
              <div v-if="u.id === auth.user?.id" class="px-2 py-1.5 border-b bg-amber-50/60 flex items-center gap-1.5">
                <img v-if="u.avatar" :src="u.avatar" class="w-6 h-6 rounded-full object-cover flex-shrink-0" />
                <div v-else class="w-6 h-6 rounded-full bg-amber-200 flex items-center justify-center text-[10px] font-bold text-amber-700 flex-shrink-0">{{ (u.nickname || u.name || '?')[0] }}</div>
                <span class="text-[11px] font-bold text-amber-900 truncate flex-1">{{ u.nickname || u.name }}</span>
                <span class="text-[11px] bg-amber-300 text-amber-900 px-1 rounded">나</span>
              </div>
              <!-- 다른 사람: 한 줄 컴팩트 레이아웃 -->
              <div v-else class="px-2 py-1.5 border-b hover:bg-amber-50/40 flex items-center gap-1.5">
                <!-- 아이콘 작게 -->
                <img v-if="u.avatar" :src="u.avatar" class="w-5 h-5 rounded-full object-cover flex-shrink-0" @error="e=>e.target.style.display='none'" />
                <div v-else class="w-5 h-5 rounded-full bg-gray-200 flex items-center justify-center text-[11px] font-bold text-gray-600 flex-shrink-0">{{ (u.nickname || u.name || '?')[0] }}</div>
                <!-- 아이디 -->
                <span class="text-[11px] font-semibold text-gray-800 truncate flex-1 min-w-0">
                  {{ u.nickname || u.name }}
                  <span v-if="u.role === 'admin' || u.role === 'super_admin'" class="text-[11px] bg-red-500 text-white px-0.5 rounded ml-0.5">👑</span>
                </span>
                <!-- 사는 곳 (축약) -->
                <span class="text-[10px] text-gray-400 truncate max-w-[60px]">{{ u.city || '' }}</span>
                <!-- 친구/쪽지/신고 버튼 (컴팩트) -->
                <button @click="openPartAction('friend', u)" :disabled="!u.allow_friend_request"
                  class="text-[11px] hover:bg-green-50 rounded px-1 py-0.5 disabled:opacity-30 disabled:cursor-not-allowed"
                  :title="u.allow_friend_request ? '친구 요청' : '친구 요청 차단됨'">👫</button>
                <button @click="openPartAction('message', u)" :disabled="!u.allow_messages"
                  class="text-[11px] hover:bg-blue-50 rounded px-1 py-0.5 disabled:opacity-30 disabled:cursor-not-allowed"
                  :title="u.allow_messages ? '쪽지' : '쪽지 차단됨'">✉️</button>
                <button @click="openPartAction('report', u)"
                  class="text-[11px] rounded px-1 py-0.5 hover:bg-red-50"
                  :style="u.is_reported ? '' : 'filter: grayscale(100%); opacity: 0.4;'"
                  :title="u.is_reported ? '이미 신고한 유저' : '신고'">🚨</button>
              </div>
            </template>
          </div>
        </div>

        <!-- 간단 참가자 액션 모달 -->
        <div v-if="partModal" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="partModal=null">
          <div class="bg-white rounded-xl w-full max-w-sm shadow-xl p-4 space-y-3">
            <div class="flex items-center gap-2">
              <img v-if="partModal.user.avatar" :src="partModal.user.avatar" class="w-10 h-10 rounded-full object-cover" />
              <div v-else class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center font-bold text-amber-700">{{ (partModal.user.nickname || partModal.user.name || '?')[0] }}</div>
              <div>
                <div class="font-bold text-gray-800 text-sm">{{ partModal.user.nickname || partModal.user.name }}</div>
                <div class="text-[10px] text-gray-400">{{ partModal.user.city }}{{ partModal.user.state ? ', '+partModal.user.state : '' }}</div>
              </div>
            </div>
            <h3 class="font-bold text-sm text-gray-800">
              <span v-if="partModal.type === 'friend'">👫 친구 요청 보내기</span>
              <span v-else-if="partModal.type === 'message'">✉️ 쪽지 보내기</span>
              <span v-else>🚨 신고</span>
            </h3>
            <textarea v-if="partModal.type === 'message'" v-model="partInput" rows="4" maxlength="500" placeholder="쪽지 내용..." class="w-full border rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-amber-400 resize-none"></textarea>
            <div v-else-if="partModal.type === 'friend'">
              <input v-model="partInput" maxlength="100" placeholder="인사말 (선택)" class="w-full border rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-amber-400" />
            </div>
            <div v-else>
              <select v-model="partReportReason" class="w-full border rounded-lg px-3 py-2 text-sm mb-2">
                <option value="spam">스팸/광고</option>
                <option value="abuse">욕설/비방</option>
                <option value="inappropriate">부적절한 내용</option>
                <option value="harassment">괴롭힘</option>
                <option value="other">기타</option>
              </select>
              <textarea v-model="partInput" rows="3" maxlength="500" placeholder="신고 상세 (선택)" class="w-full border rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-red-400 resize-none"></textarea>
            </div>
            <div v-if="partMsg" class="text-xs" :class="partMsgOk ? 'text-green-600' : 'text-red-500'">{{ partMsg }}</div>
            <div class="flex gap-2 justify-end">
              <button @click="partModal=null" class="text-gray-500 text-xs px-4 py-2">취소</button>
              <button @click="submitPartAction" :disabled="partSubmitting"
                class="font-bold px-4 py-2 rounded-lg text-xs text-white disabled:opacity-50"
                :class="partModal.type === 'report' ? 'bg-red-500 hover:bg-red-600' : partModal.type === 'friend' ? 'bg-green-500 hover:bg-green-600' : 'bg-blue-500 hover:bg-blue-600'">
                {{ partSubmitting ? '...' : (partModal.type === 'report' ? '신고 접수' : partModal.type === 'friend' ? '요청 보내기' : '쪽지 전송') }}
              </button>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3">
          <div class="font-bold text-xs text-gray-800 mb-2">📢 채팅 안내</div>
          <div class="text-[10px] text-gray-500 space-y-1">
            <div>• 누구나 참여할 수 있는 공개 채팅방입니다</div>
            <div>• 로그인 후 메시지를 보낼 수 있어요</div>
            <div>• 욕설/광고는 자동 차단됩니다</div>
          </div>
        </div>
      </div>
    </div>

    <!-- 🖼️ 이미지 라이트박스 -->
    <div v-if="lightboxSrc" class="fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4" @click="lightboxSrc = null">
      <img :src="lightboxSrc" class="max-w-full max-h-full object-contain" />
      <button class="absolute top-4 right-4 text-white text-3xl">✕</button>
    </div>

    <!-- 새 채팅 모달 (타입·참여자 선택 Issue #20) -->
    <div v-if="showCreate" class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center" @click.self="closeCreate">
      <div class="bg-white rounded-t-xl sm:rounded-xl w-full max-w-sm shadow-xl max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b px-5 py-3 flex items-center justify-between">
          <h3 class="font-bold text-gray-800">새 채팅</h3>
          <button @click="closeCreate" class="text-gray-400 text-xl leading-none">×</button>
        </div>

        <!-- 타입 선택 -->
        <div class="px-5 pt-4">
          <div class="grid grid-cols-3 gap-2 mb-4">
            <button v-for="t in createTypes" :key="t.value"
              @click="createType = t.value"
              :class="['py-2 px-2 rounded-lg text-xs font-semibold border transition',
                createType===t.value ? 'bg-amber-400 text-amber-900 border-amber-500' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50']">
              <div class="text-base mb-0.5">{{ t.icon }}</div>
              {{ t.label }}
            </button>
          </div>
        </div>

        <!-- DM: 친구 1명 선택 -->
        <div v-if="createType==='dm'" class="px-5 pb-4">
          <div class="text-xs font-semibold text-gray-600 mb-2">1:1 대화 상대</div>
          <input v-model="friendQuery" type="text" placeholder="친구 이름 검색..."
            class="w-full border rounded-lg px-3 py-2 text-sm mb-2 outline-none focus:ring-2 focus:ring-amber-400" />
          <div v-if="friendsLoading" class="text-xs text-gray-400 text-center py-4">로딩중...</div>
          <div v-else-if="!filteredFriends.length" class="text-xs text-gray-400 text-center py-4">
            {{ friendQuery ? '검색 결과 없음' : '친구가 없습니다. 친구부터 추가하세요.' }}
          </div>
          <div v-else class="max-h-48 overflow-y-auto border rounded-lg divide-y">
            <button v-for="f in filteredFriends" :key="f.id"
              @click="selectedFriendIds = [f.id]"
              :class="['w-full flex items-center gap-2 px-3 py-2 text-left text-sm',
                selectedFriendIds.includes(f.id) ? 'bg-amber-50 text-amber-900 font-semibold' : 'hover:bg-gray-50']">
              <img v-if="f.avatar" :src="f.avatar" class="w-7 h-7 rounded-full object-cover" />
              <div v-else class="w-7 h-7 rounded-full bg-amber-100 flex items-center justify-center text-xs font-bold text-amber-700">{{ (f.nickname || f.name || '?')[0] }}</div>
              <span class="flex-1 truncate">{{ f.nickname || f.name }}</span>
              <span v-if="selectedFriendIds.includes(f.id)" class="text-amber-500">✓</span>
            </button>
          </div>
        </div>

        <!-- 그룹: 이름 + 다중 선택 -->
        <div v-if="createType==='group'" class="px-5 pb-4 space-y-2">
          <div>
            <div class="text-xs font-semibold text-gray-600 mb-1">그룹 이름</div>
            <input v-model="newRoomName" type="text" placeholder="예: 애틀란타 한식 덕후들" maxlength="50"
              class="w-full border rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-amber-400" />
          </div>
          <div>
            <div class="text-xs font-semibold text-gray-600 mb-1 flex items-center justify-between">
              <span>초대할 친구 <span class="text-amber-600">{{ selectedFriendIds.length }}명</span></span>
            </div>
            <input v-model="friendQuery" type="text" placeholder="친구 이름 검색..."
              class="w-full border rounded-lg px-3 py-2 text-sm mb-2 outline-none focus:ring-2 focus:ring-amber-400" />
            <div v-if="friendsLoading" class="text-xs text-gray-400 text-center py-3">로딩중...</div>
            <div v-else-if="!filteredFriends.length" class="text-xs text-gray-400 text-center py-3">친구가 없습니다</div>
            <div v-else class="max-h-48 overflow-y-auto border rounded-lg divide-y">
              <label v-for="f in filteredFriends" :key="f.id"
                class="flex items-center gap-2 px-3 py-2 text-sm cursor-pointer hover:bg-gray-50">
                <input type="checkbox" :value="f.id" v-model="selectedFriendIds" class="accent-amber-500" />
                <img v-if="f.avatar" :src="f.avatar" class="w-6 h-6 rounded-full object-cover" />
                <div v-else class="w-6 h-6 rounded-full bg-amber-100 flex items-center justify-center text-[10px] font-bold text-amber-700">{{ (f.nickname || f.name || '?')[0] }}</div>
                <span class="flex-1 truncate">{{ f.nickname || f.name }}</span>
              </label>
            </div>
          </div>
        </div>

        <!-- 공개방 -->
        <div v-if="createType==='public'" class="px-5 pb-4 space-y-2">
          <div class="text-xs font-semibold text-gray-600 mb-1">공개 채팅방 이름</div>
          <input v-model="newRoomName" type="text" placeholder="예: 둘루스 한인 모임" maxlength="50"
            class="w-full border rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-amber-400" />
          <div class="text-[11px] text-gray-400">누구나 입장 가능한 공개방이 생성됩니다.</div>
        </div>

        <!-- 에러 / 버튼 -->
        <div class="px-5 pb-5">
          <div v-if="createError" class="text-xs text-red-500 mb-2">{{ createError }}</div>
          <div class="flex gap-2">
            <button @click="createRoom" :disabled="creating"
              class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm flex-1 hover:bg-amber-500 disabled:opacity-50">
              {{ creating ? '생성 중...' : '만들기' }}
            </button>
            <button @click="closeCreate" class="text-gray-500 px-4 py-2">취소</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import axios from 'axios'
import { compressImage, isImage, isArchive } from '../../utils/imageCompress'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const siteStore = useSiteStore()
const windowWidth = ref(window.innerWidth)
const isMobile = computed(() => windowWidth.value < 1024)
const rooms = ref([])
const activeRoom = ref(null)
const activeMessages = ref([])
const pinnedAnnouncements = ref([])
const loading = ref(true)
const showCreate = ref(false)
const newRoomName = ref('')
const newMsg = ref('')
const msgArea = ref(null)
const selectedFiles = ref([])   // [{file, preview, type}]
const sending = ref(false)
const lightboxSrc = ref(null)

function isAdminUser(u) {
  return u && ['admin', 'super_admin'].includes(u.role)
}

function cleanAnnounceContent(content) {
  if (!content) return ''
  return content.replace(/^📢\s*\[공지\]\s*/, '')
}

function timeRemaining(until) {
  if (!until) return '-'
  const diff = new Date(until) - new Date()
  if (diff <= 0) return '만료됨'
  const m = Math.floor(diff / 60000)
  if (m < 60) return `${m}분`
  const h = Math.floor(m / 60)
  const mm = m % 60
  if (h < 24) return mm > 0 ? `${h}시간 ${mm}분` : `${h}시간`
  const d = Math.floor(h / 24)
  const hh = h % 24
  return hh > 0 ? `${d}일 ${hh}시간` : `${d}일`
}

async function onSelectFiles(e) {
  const files = Array.from(e.target.files || [])
  e.target.value = '' // 같은 파일 재선택 가능
  if (!files.length) return

  const rejected = []
  for (const file of files) {
    // 이미지: 압축 (내부에서 자동으로 1600px, JPEG 0.8 품질)
    if (isImage(file)) {
      try {
        const compressed = await compressImage(file, { maxDim: 1600, quality: 0.8 })
        // 압축 후에도 10MB 초과면 거절
        if (compressed.size > 10 * 1024 * 1024) {
          rejected.push(file.name + ' (압축 후에도 10MB 초과)')
          continue
        }
        selectedFiles.value.push({
          file: compressed,
          preview: URL.createObjectURL(compressed),
          type: 'image',
          originalSize: file.size,
        })
      } catch (err) {
        rejected.push(file.name + ' (압축 실패)')
      }
      continue
    }
    // 압축파일만 허용
    if (isArchive(file)) {
      if (file.size > 10 * 1024 * 1024) {
        rejected.push(file.name + ' (10MB 초과)')
        continue
      }
      selectedFiles.value.push({
        file,
        preview: null,
        type: 'archive',
        originalSize: file.size,
      })
      continue
    }
    // 그 외(문서 등) 거부
    rejected.push(file.name + ' (이미지 또는 압축파일만 가능)')
  }

  if (rejected.length) {
    siteStore.toast('업로드 불가: ' + rejected.join(', '), 'error')
  }
}

function removeSelectedFile(idx) {
  const item = selectedFiles.value[idx]
  if (item?.preview) URL.revokeObjectURL(item.preview)
  selectedFiles.value.splice(idx, 1)
}

function clearFiles() {
  selectedFiles.value.forEach(f => { if (f.preview) URL.revokeObjectURL(f.preview) })
  selectedFiles.value = []
}

function formatSize(bytes) {
  if (!bytes) return ''
  if (bytes < 1024) return bytes + 'B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + 'KB'
  return (bytes / 1024 / 1024).toFixed(1) + 'MB'
}

// 눈팅용: 현재 선택 외 다른 방 3개 + 마지막 메시지
const peekRooms = computed(() => {
  return rooms.value
    .filter(r => r.id !== activeRoom.value?.id)
    .slice(0, 4)
    .map(r => ({
      ...r,
      lastMsg: r.messages?.[0] || null,
    }))
})

function formatTime(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  const now = new Date()
  const diff = now - d
  if (diff < 60000) return '방금'
  if (diff < 3600000) return Math.floor(diff / 60000) + '분 전'
  if (d.toDateString() === now.toDateString()) return d.toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' })
  return d.toLocaleDateString('ko-KR', { month: 'short', day: 'numeric' }) + ' ' + d.toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' })
}

let currentChannel = null

function unsubscribeChannel() {
  if (currentChannel && window.Echo) {
    try { window.Echo.leaveChannel('chat.' + currentChannel) } catch (e) {}
    currentChannel = null
  }
}

function subscribeToRoom(roomId) {
  unsubscribeChannel()
  if (!window.Echo) return
  currentChannel = roomId
  window.Echo.channel('chat.' + roomId)
    .listen('.message.sent', (payload) => {
      // 가드: 현재 활성 방의 메시지만 받음 (다른 방 채널 잔존 방지)
      if (Number(activeRoom.value?.id) !== Number(roomId)) return
      if (payload.chat_room_id && Number(payload.chat_room_id) !== Number(roomId)) return
      if (activeMessages.value.some(m => m.id === payload.id)) return
      activeMessages.value.push(payload)
      if (payload.type === 'system' && payload.pinned_until) {
        pinnedAnnouncements.value = [payload, ...pinnedAnnouncements.value.filter(p => p.id !== payload.id)]
      }
      // 사용자가 위로 스크롤 중(일시정지)이면 자동 스크롤 안함, 대신 카운터 증가
      if (autoScrollPaused.value) {
        pausedNewCount.value++
      } else {
        nextTick(() => {
          if (msgArea.value) msgArea.value.scrollTop = msgArea.value.scrollHeight
        })
      }
    })
}

let selectRoomSeq = 0
// ─── 날짜 구분선 + 읽음 마커 ───
const lastReadAt = ref(null) // 서버에서 받은 이 방 내 last_read_at (처음 로드 시점 것)

function sameDay(a, b) {
  if (!a || !b) return false
  const da = new Date(a), db = new Date(b)
  return da.getFullYear() === db.getFullYear() && da.getMonth() === db.getMonth() && da.getDate() === db.getDate()
}
function showDateDivider(idx) {
  if (idx === 0) return true
  const cur = activeMessages.value[idx]?.created_at
  const prev = activeMessages.value[idx - 1]?.created_at
  return !sameDay(cur, prev)
}
function dateLabel(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  const today = new Date(); today.setHours(0,0,0,0)
  const dd = new Date(d); dd.setHours(0,0,0,0)
  const diffDays = (today - dd) / 86400000
  if (diffDays === 0) return '오늘'
  if (diffDays === 1) return '어제'
  return `${d.getFullYear()}년 ${d.getMonth()+1}월 ${d.getDate()}일`
}
function showUnreadDivider(idx) {
  if (!lastReadAt.value) return false
  const cur = activeMessages.value[idx]
  const prev = activeMessages.value[idx - 1]
  if (!cur) return false
  // 본인 메시지는 경계선 스킵 (이미 읽은 것으로 간주)
  if (cur.user_id === auth.user?.id) return false
  const lr = new Date(lastReadAt.value).getTime()
  const curT = new Date(cur.created_at).getTime()
  const prevT = prev ? new Date(prev.created_at).getTime() : 0
  // cur 이 lastRead 이후 첫 메시지
  return curT > lr && prevT <= lr
}

// ─── 자동스크롤 일시정지 (텔레그램 스타일) ───
const autoScrollPaused = ref(false)
const pausedNewCount = ref(0)
function onMsgScroll(e) {
  const el = e.target
  const atBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 40
  if (atBottom) {
    autoScrollPaused.value = false
    pausedNewCount.value = 0
  } else {
    autoScrollPaused.value = true
  }
}
function scrollToBottomForce() {
  if (msgArea.value) msgArea.value.scrollTop = msgArea.value.scrollHeight
  autoScrollPaused.value = false
  pausedNewCount.value = 0
}

// ─── 메시지 검색 팝업 ───
const msgSearchOpen = ref(false)
const msgSearchInput = ref(null)
const msgSearchQ = ref('')
const msgSearchResults = ref([])
const msgSearchIdx = ref(0)
let msgSearchTimer = null
function openMsgSearch() {
  msgSearchOpen.value = true
  nextTick(() => msgSearchInput.value?.focus())
}
function closeMsgSearch() {
  msgSearchOpen.value = false
  msgSearchQ.value = ''
  msgSearchResults.value = []
  msgSearchIdx.value = 0
}
function runMsgSearch() {
  clearTimeout(msgSearchTimer)
  msgSearchTimer = setTimeout(async () => {
    const q = msgSearchQ.value.trim()
    if (!q || !activeRoom.value) { msgSearchResults.value = []; return }
    try {
      const { data } = await axios.get(`/api/chat/rooms/${activeRoom.value.id}/messages/search`, { params: { q } })
      msgSearchResults.value = data.data || []
      msgSearchIdx.value = 0
      scrollToSearchResult()
    } catch { msgSearchResults.value = [] }
  }, 300)
}
function navMsgSearch(dir) {
  if (!msgSearchResults.value.length) return
  const n = msgSearchResults.value.length
  msgSearchIdx.value = (msgSearchIdx.value + dir + n) % n
  scrollToSearchResult()
}
async function scrollToSearchResult() {
  const hit = msgSearchResults.value[msgSearchIdx.value]
  if (!hit) return
  // 화면에 보이는 메시지면 바로 스크롤, 아니면 해당 위치의 메시지를 로드해야 함
  const exists = activeMessages.value.find(m => m.id === hit.id)
  if (!exists) {
    // 간단 버전: 검색 결과를 activeMessages 에 병합 (중복 제거)
    const merged = [...activeMessages.value, ...msgSearchResults.value]
      .reduce((acc, m) => { if (!acc.find(x => x.id === m.id)) acc.push(m); return acc }, [])
      .sort((a, b) => new Date(a.created_at) - new Date(b.created_at))
    activeMessages.value = merged
  }
  await nextTick()
  const el = document.getElementById('msg-' + hit.id)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

// ─── 참가자 목록 ───
const participants = ref([])
const participantsLoading = ref(false)
const partSearch = ref('')
const filteredParticipants = computed(() => {
  const q = (partSearch.value || '').trim().toLowerCase()
  if (!q) return participants.value
  return participants.value.filter(u => {
    return (u.nickname || '').toLowerCase().includes(q) ||
           (u.name || '').toLowerCase().includes(q) ||
           (u.city || '').toLowerCase().includes(q)
  })
})
async function loadParticipants() {
  if (!activeRoom.value) return
  participantsLoading.value = true
  try {
    const { data } = await axios.get(`/api/chat/rooms/${activeRoom.value.id}/participants`)
    participants.value = data.data || []
  } catch {
    participants.value = []
  }
  participantsLoading.value = false
}

// 친구/쪽지/신고 모달
const partModal = ref(null) // { type: 'friend'|'message'|'report', user }
const partInput = ref('')
const partReportReason = ref('spam')
const partMsg = ref('')
const partMsgOk = ref(false)
const partSubmitting = ref(false)

function openPartAction(type, user) {
  partModal.value = { type, user }
  partInput.value = ''
  partReportReason.value = 'spam'
  partMsg.value = ''
  partMsgOk.value = false
}

async function submitPartAction() {
  if (!partModal.value) return
  const { type, user } = partModal.value
  partSubmitting.value = true
  partMsg.value = ''
  try {
    if (type === 'friend') {
      await axios.post(`/api/friends/request/${user.id}`, { message: partInput.value })
      partMsg.value = '✅ 친구 요청을 보냈습니다'; partMsgOk.value = true
    } else if (type === 'message') {
      if (!partInput.value.trim()) { partMsg.value = '내용을 입력해주세요'; partSubmitting.value = false; return }
      await axios.post('/api/messages', { receiver_id: user.id, content: partInput.value })
      partMsg.value = '✅ 쪽지를 보냈습니다'; partMsgOk.value = true
    } else if (type === 'report') {
      await axios.post('/api/reports', {
        reportable_type: 'App\\Models\\User',
        reportable_id: user.id,
        reason: partReportReason.value,
        content: partInput.value,
      })
      partMsg.value = '✅ 신고가 접수되었습니다'; partMsgOk.value = true
      // 참가자 목록에 is_reported 즉시 반영
      const p = participants.value.find(x => x.id === user.id)
      if (p) p.is_reported = true
    }
    setTimeout(() => { partModal.value = null }, 1200)
  } catch (e) {
    partMsg.value = e.response?.data?.message || '요청 실패'
    partMsgOk.value = false
  }
  partSubmitting.value = false
}

async function selectRoom(room, opts = {}) {
  const seq = ++selectRoomSeq
  // 이전 방에 markRead — 방 나갈 때 읽음 시각 저장
  const prevRoomId = activeRoom.value?.id
  if (prevRoomId && prevRoomId !== room.id) {
    try { axios.post(`/api/chat/rooms/${prevRoomId}/read`) } catch {}
    // 리스트의 미읽음 배지 즉시 0 처리
    const prev = rooms.value.find(r => r.id === prevRoomId)
    if (prev) { prev.unread_count = 0; prev.is_new = false; prev.has_entered = true }
  }

  // URL 동기화 (/chat/:id) — route 변화로 역진입 시엔 skipRoute=true 로 중복 push 방지
  if (!opts.skipRoute && String(route.params.id || '') !== String(room.id)) {
    router.push(`/chat/${room.id}`)
  }

  activeRoom.value = room
  activeMessages.value = []
  pinnedAnnouncements.value = []
  lastReadAt.value = null
  autoScrollPaused.value = false
  pausedNewCount.value = 0
  closeMsgSearch()
  clearFiles()
  subscribeToRoom(room.id)
  try {
    const { data } = await axios.get(`/api/chat/rooms/${room.id}/messages`, {
      params: { _ts: Date.now() },
    })
    if (seq !== selectRoomSeq || activeRoom.value?.id !== room.id) return
    const msgs = (data.data?.data || data.data || []).reverse()
    const rid = Number(room.id)
    activeMessages.value = msgs.filter(m => !m.chat_room_id || Number(m.chat_room_id) === rid)
    pinnedAnnouncements.value = data.pinned || []
    lastReadAt.value = data.last_read_at || null
    loadParticipants()

    await nextTick()
    const markerEl = document.getElementById('unread-marker-' + room.id)
    if (markerEl && lastReadAt.value) {
      markerEl.scrollIntoView({ block: 'center' })
    } else if (msgArea.value) {
      msgArea.value.scrollTop = msgArea.value.scrollHeight
    }
  } catch (e) {
    if (seq === selectRoomSeq) console.warn('[chat] failed to load messages for room', room.id, e?.message)
  }
}

async function sendMsg() {
  if ((!newMsg.value.trim() && !selectedFiles.value.length) || !auth.isLoggedIn || !activeRoom.value) return
  sending.value = true
  try {
    const fd = new FormData()
    if (newMsg.value.trim()) fd.append('content', newMsg.value)
    selectedFiles.value.forEach(item => fd.append('files[]', item.file))
    const { data } = await axios.post(`/api/chat/rooms/${activeRoom.value.id}/messages`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    // 응답의 messages 배열이 있으면 여러 개 push, 없으면 data 하나
    const msgs = data.messages || (data.data ? [data.data] : [])
    msgs.forEach(m => {
      if (!activeMessages.value.some(x => x.id === m.id)) activeMessages.value.push(m)
    })
    newMsg.value = ''
    clearFiles()
    await nextTick()
    if (msgArea.value) msgArea.value.scrollTop = msgArea.value.scrollHeight
  } catch (e) {
    const err = e.response?.data
    const msg = err?.message || err?.errors?.['files.0']?.[0] || err?.errors?.files?.[0] || '전송 실패'
    siteStore.toast(msg, 'error')
  }
  sending.value = false
}

// ─── 타입 필터 (Issue #21) ───
const roomFilter = ref('all')
const roomFilterTabs = [
  { value: 'all',    label: '전체', icon: '' },
  { value: 'dm',     label: '쪽지', icon: '💌' },
  { value: 'group',  label: '그룹', icon: '👥' },
  { value: 'public', label: '공개', icon: '🌐' },
]
function matchesFilter(room, filter) {
  if (filter === 'all') return true
  if (filter === 'dm') return room.type === 'dm' || room.type === 'private'
  return room.type === filter
}
const filteredRooms = computed(() => rooms.value.filter(r => matchesFilter(r, roomFilter.value)))
function filterCount(filter) {
  if (filter === 'all') return ''
  const n = rooms.value.filter(r => matchesFilter(r, filter)).length
  return n ? `(${n})` : ''
}
const currentFilterTitle = computed(() => {
  if (roomFilter.value === 'dm') return '💌 1:1 쪽지'
  if (roomFilter.value === 'group') return '👥 그룹'
  if (roomFilter.value === 'public') return '🌐 공개 채팅방'
  return '💬 전체 채팅방'
})
function roomTypeIcon(type) {
  if (type === 'dm' || type === 'private') return '💌'
  if (type === 'group') return '👥'
  return '🌐'
}

// DM 방은 상대방 이름으로 라벨 표시 (Issue #22)
function roomDisplayName(room) {
  if (!room) return ''
  if (room.name) return room.name
  if (room.type === 'dm' || room.type === 'private') {
    const others = (room.participants || room.users || []).filter(u => u.id !== auth.user?.id)
    if (others.length) {
      return others.map(u => u.display_name || u.nickname || u.name).join(', ')
    }
  }
  return '채팅방'
}

// 뒤로가기: /chat 리스트로 복귀 (모바일에서만 보임)
function goBackToList() {
  // 읽음 처리
  if (activeRoom.value?.id) {
    try { axios.post(`/api/chat/rooms/${activeRoom.value.id}/read`) } catch {}
  }
  activeRoom.value = null
  activeMessages.value = []
  unsubscribeChannel()
  router.push('/chat')
}

// ─── 새 채팅 모달 (Issue #20) ───
const createTypes = [
  { value: 'dm',     label: '1:1 쪽지',  icon: '💌' },
  { value: 'group',  label: '그룹',      icon: '👥' },
  { value: 'public', label: '공개방',    icon: '🌐' },
]
const createType = ref('dm')
const friendList = ref([])
const friendsLoading = ref(false)
const friendQuery = ref('')
const selectedFriendIds = ref([])
const createError = ref('')
const creating = ref(false)

const filteredFriends = computed(() => {
  const q = friendQuery.value.trim().toLowerCase()
  const accepted = friendList.value.filter(f => f.status === 'accepted')
  if (!q) return accepted
  return accepted.filter(f =>
    (f.nickname || '').toLowerCase().includes(q) ||
    (f.name || '').toLowerCase().includes(q)
  )
})

async function loadFriends() {
  if (friendList.value.length) return
  friendsLoading.value = true
  try {
    const { data } = await axios.get('/api/friends', { params: { status: 'accepted' } })
    // API 응답 구조: [{ friend: {...user}, status, ... }] — 친구 객체로 평탄화
    friendList.value = (data.data || []).map(x => ({
      id: x.friend?.id,
      name: x.friend?.name,
      nickname: x.friend?.nickname,
      avatar: x.friend?.avatar,
      status: x.status,
    })).filter(f => f.id)
  } catch {}
  friendsLoading.value = false
}

function closeCreate() {
  showCreate.value = false
  createError.value = ''
  creating.value = false
}

// 모달 열릴 때 친구 로드
watch(showCreate, (v) => { if (v) { loadFriends(); resetCreateForm() } })

function resetCreateForm() {
  createType.value = 'dm'
  newRoomName.value = ''
  friendQuery.value = ''
  selectedFriendIds.value = []
  createError.value = ''
}

async function createRoom() {
  createError.value = ''
  // 검증
  if (createType.value === 'dm') {
    if (!selectedFriendIds.value.length) { createError.value = '상대를 선택하세요'; return }
  } else {
    if (!newRoomName.value.trim()) { createError.value = '방 이름을 입력하세요'; return }
    if (createType.value === 'group' && !selectedFriendIds.value.length) {
      createError.value = '초대할 친구를 최소 1명 선택하세요'; return
    }
  }
  creating.value = true
  try {
    const payload = {
      type: createType.value,
      name: newRoomName.value.trim() || null,
      user_ids: selectedFriendIds.value,
    }
    const { data } = await axios.post('/api/chat/rooms', payload)
    const room = data.data
    // 목록에 없으면 상단 추가
    if (!rooms.value.find(r => Number(r.id) === Number(room.id))) {
      rooms.value.unshift(room)
    }
    closeCreate()
    selectRoom(room)
  } catch (e) {
    createError.value = e.response?.data?.message || '생성 실패'
  }
  creating.value = false
}

const onResize = () => { windowWidth.value = window.innerWidth }

// 라우트의 :id 에 해당하는 방을 열거나, 없으면 기본 선택
async function restoreFromRoute() {
  const routeId = Number(route.params.id)
  if (routeId) {
    let room = rooms.value.find(r => Number(r.id) === routeId)
    if (!room) {
      // 목록에 없으면 개별 조회 (DM/그룹/참여방)
      try {
        const { data } = await axios.get(`/api/chat/rooms/${routeId}`)
        room = data.data || data
      } catch (e) {
        if (e.response?.status === 404) {
          siteStore.toast('채팅방을 찾을 수 없습니다', 'error')
          router.replace('/chat')
          return
        }
      }
    }
    if (room) return selectRoom(room, { skipRoute: true })
  }
  // :id 없으면: PC 에서만 첫 번째 방 자동 선택
  if (rooms.value.length && !isMobile.value) selectRoom(rooms.value[0])
}

// URL /chat/:id 변경 감지 — 브라우저 뒤/앞 또는 외부 push 반영
watch(() => route.params.id, async (newId) => {
  if (!newId) {
    // /chat 로 돌아오면 active 정리
    if (activeRoom.value) {
      activeRoom.value = null
      activeMessages.value = []
      unsubscribeChannel()
    }
    return
  }
  if (String(activeRoom.value?.id) === String(newId)) return
  const room = rooms.value.find(r => Number(r.id) === Number(newId))
  if (room) selectRoom(room, { skipRoute: true })
  else {
    try {
      const { data } = await axios.get(`/api/chat/rooms/${newId}`)
      selectRoom(data.data || data, { skipRoute: true })
    } catch (e) {
      if (e.response?.status === 404) {
        siteStore.toast('채팅방을 찾을 수 없습니다', 'error')
        router.replace('/chat')
      }
    }
  }
})

onMounted(async () => {
  window.addEventListener('resize', onResize)
  try {
    const { data } = await axios.get('/api/chat/rooms')
    rooms.value = data.data || []
    await restoreFromRoute()
  } catch {}
  loading.value = false
})

onUnmounted(() => {
  // 페이지 떠날 때 현재 방 읽음 표시
  if (activeRoom.value?.id) {
    try { axios.post(`/api/chat/rooms/${activeRoom.value.id}/read`) } catch {}
  }
  unsubscribeChannel()
  window.removeEventListener('resize', onResize)
})
</script>
