<template>
  <!-- 전체를 뷰포트 안에 고정 (페이지 스크롤 없음) -->
  <div class="chat-page-wrap bg-gray-50">

    <!-- ============================================================
         모바일: 채팅방 목록 (activeRoom 없을 때)
    ============================================================ -->
    <div v-if="!activeRoom" class="lg:hidden flex flex-col h-full">
      <!-- 헤더 -->
      <div class="px-4 pt-4 pb-2 flex-shrink-0">
        <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-5 rounded-2xl flex items-center justify-between">
          <div>
            <h1 class="text-xl font-black">💬 채팅방</h1>
            <p class="text-sm opacity-80 mt-0.5">실시간 커뮤니티 채팅</p>
          </div>
          <button @click="showCreateModal = true" class="bg-white text-blue-600 px-4 py-2 rounded-lg text-sm font-bold">+ 개설</button>
        </div>
      </div>
      <!-- 카테고리 -->
      <div class="px-4 py-2 flex gap-2 overflow-x-auto flex-shrink-0 scrollbar-hide">
        <button v-for="cat in categories" :key="cat.id" @click="activeCat = cat.id"
          class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold transition"
          :class="activeCat === cat.id ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'">
          {{ cat.icon }} {{ cat.label }}
        </button>
      </div>
      <!-- 채팅방 목록 (스크롤) -->
      <div class="flex-1 overflow-y-auto px-3 py-2 space-y-2">
        <div v-if="loading" class="text-center py-12 text-gray-400 text-sm">불러오는 중...</div>
        <div v-for="room in filteredRooms" :key="room.slug" @click="openRoom(room)"
          class="bg-white rounded-2xl overflow-hidden shadow-sm cursor-pointer border-2 border-transparent active:border-blue-400">
          <div class="h-14 flex items-center px-4 gap-3"
            :class="room.type==='regional' ? 'bg-gradient-to-r from-blue-400 to-cyan-400'
              : room.type==='theme' ? 'bg-gradient-to-r from-purple-400 to-pink-400'
              : 'bg-gradient-to-r from-green-400 to-emerald-400'">
            <span class="text-xl">{{ room.icon || '💬' }}</span>
            <div class="text-white flex-1 min-w-0">
              <p class="font-bold text-sm truncate">{{ room.name }}</p>
              <p class="text-white/70 text-xs truncate">{{ room.description }}</p>
            </div>
            <span v-if="getUnreadCount(room) > 0"
              class="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full flex-shrink-0">
              {{ getUnreadCount(room) > 300 ? '300+' : getUnreadCount(room) }}
            </span>
          </div>
          <div class="px-4 py-2 flex items-center justify-between">
            <span class="text-xs text-gray-400 flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-400"></span>실시간</span>
            <div class="flex items-center gap-2">
              <template v-if="isCreator(room)">
                <button @click.stop="renameRoom(room)" class="text-xs text-gray-400 hover:text-blue-500 px-1" title="이름 수정">✏️</button>
                <button @click.stop="destroyRoom(room)" class="text-xs text-red-400 hover:text-red-600 px-1" title="방 삭제">🗑️</button>
              </template>
              <span class="text-xs text-blue-500 font-semibold">입장 →</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ============================================================
         모바일: 채팅 전체화면
    ============================================================ -->
    <div v-if="activeRoom && isMobile" class="lg:hidden flex flex-col h-full">
      <!-- 헤더 -->
      <div class="bg-white shadow-sm px-4 py-3 flex items-center gap-3 flex-shrink-0 z-10">
        <button @click="closeRoom" class="text-gray-500">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
        </button>
        <span class="text-lg">{{ activeRoom.icon || '💬' }}</span>
        <div class="flex-1 min-w-0">
          <p class="font-bold text-gray-800 truncate text-sm">{{ activeRoom.name }}</p>
        </div>
        <button @click="showSearch = !showSearch" :class="showSearch ? 'text-blue-500' : 'text-gray-400'" class="p-1.5">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
          </svg>
        </button>
        <span class="w-2 h-2 rounded-full" :class="chatConnected ? 'bg-green-400' : 'bg-gray-300'"></span>
      </div>
      <!-- 검색 바 -->
      <div v-if="showSearch" class="bg-white border-b px-3 py-2 flex gap-2 flex-shrink-0">
        <input v-model="searchQuery" type="text" placeholder="채팅 내용 검색..."
          class="flex-1 border border-gray-200 rounded-full px-4 py-2 text-sm focus:outline-none focus:border-blue-400 bg-gray-50"
          @input="doSearch" />
        <button @click="showSearch=false;searchQuery='';searchResults=[]" class="text-gray-400 text-sm px-2">닫기</button>
      </div>
      <!-- 검색 결과 -->
      <div v-if="searchResults.length" class="bg-amber-50 border-b px-3 py-2 flex-shrink-0 max-h-40 overflow-y-auto">
        <p class="text-xs text-amber-700 font-medium mb-1">{{ searchResults.length }}개 검색됨</p>
        <div v-for="msg in searchResults" :key="msg.id" @click="scrollToMessage(msg.id)"
          class="py-1 px-2 rounded hover:bg-amber-100 cursor-pointer text-xs flex gap-2">
          <span class="text-blue-600 font-medium flex-shrink-0">{{ chatUserName(msg.user) }}</span>
          <span class="text-gray-600 truncate">{{ msg.message }}</span>
          <span class="text-gray-400 flex-shrink-0">{{ formatDate(msg.created_at) }}</span>
        </div>
      </div>
      <!-- 메시지 영역 -->
      <div ref="msgContainerMobile" class="flex-1 overflow-y-auto px-3 py-2 min-h-0 bg-gray-50" @click="activeMenu=null">
        <div v-if="chatLoading" class="text-center py-8 text-gray-400">
          <div class="animate-spin w-6 h-6 border-2 border-blue-400 border-t-transparent rounded-full mx-auto mb-2"></div>
          불러오는 중...
        </div>
        <div v-else-if="!filteredMessages.length" class="text-center py-16 text-gray-400">
          <p class="text-4xl mb-2">💬</p><p class="text-sm">첫 메시지를 남겨보세요!</p>
        </div>
        <template v-for="(group, dateLabel) in groupedMessages" :key="dateLabel">
          <div class="flex items-center gap-3 my-4">
            <div class="flex-1 h-px bg-gray-200"></div>
            <span class="text-xs text-gray-500 bg-white border border-gray-200 px-3 py-0.5 rounded-full shadow-sm">{{ dateLabel }}</span>
            <div class="flex-1 h-px bg-gray-200"></div>
          </div>
          <div v-for="msg in group" :key="msg.id" :id="`msg-${msg.id}`"
            class="flex gap-2 mb-2 transition-colors duration-500"
            :class="isMe(msg) ? 'justify-end' : 'justify-start'">
            <template v-if="!isMe(msg)">
              <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-xs font-bold text-blue-700 flex-shrink-0 mt-1 overflow-hidden">
                <img v-if="msg.user?.avatar" :src="msg.user.avatar" class="w-full h-full object-cover" />
                <span v-else>{{ chatUserName(msg.user)[0] }}</span>
              </div>
              <div class="max-w-[75%]">
                <p class="text-xs text-gray-500 mb-0.5 ml-1">{{ chatUserName(msg.user) }}</p>
                <div class="flex items-end gap-1">
                  <div class="bg-white rounded-2xl rounded-tl-none px-3 py-2 shadow-sm text-gray-800 text-sm leading-relaxed">
                    <img v-if="msg.file_type==='image'" :src="msg.file_url" class="max-w-full rounded-lg max-h-48 object-contain cursor-pointer" @click.stop="openImage(msg.file_url)" />
                    <a v-else-if="msg.file_url" :href="msg.file_url" target="_blank" class="flex items-center gap-1 text-blue-600 hover:underline text-sm">📎 {{ msg.file_name || '파일' }}</a>
                    <span v-else>{{ msg.message }}</span>
                  </div>
                  <span class="text-xs text-gray-400 whitespace-nowrap mb-1">{{ formatTime(msg.created_at) }}</span>
                  <button v-if="auth.isLoggedIn" @click.stop="toggleMenu(msg.id)" class="text-gray-300 hover:text-gray-500 mb-1 p-0.5 flex-shrink-0">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
                  </button>
                </div>
                <div v-if="activeMenu===msg.id" class="bg-white rounded-xl shadow-lg border text-sm overflow-hidden mt-1 w-36 z-30 relative">
                  <button @click.stop="blockUser(msg.user)" class="flex items-center gap-2 w-full px-3 py-2.5 hover:bg-gray-50 text-gray-700">🚫 차단하기</button>
                  <div class="h-px bg-gray-100"></div>
                  <button @click.stop="reportMsg(msg)" class="flex items-center gap-2 w-full px-3 py-2.5 hover:bg-red-50 text-red-600">🚩 신고하기</button>
                </div>
              </div>
            </template>
            <template v-else>
              <div class="max-w-[75%]">
                <div class="flex items-end gap-1 justify-end">
                  <span class="text-xs text-gray-400 whitespace-nowrap mb-1">{{ formatTime(msg.created_at) }}</span>
                  <div class="bg-blue-500 text-white rounded-2xl rounded-tr-none px-3 py-2 shadow-sm text-sm leading-relaxed">
                    <img v-if="msg.file_type==='image'" :src="msg.file_url" class="max-w-full rounded-lg max-h-48 object-contain cursor-pointer" @click.stop="openImage(msg.file_url)" />
                    <a v-else-if="msg.file_url" :href="msg.file_url" target="_blank" class="flex items-center gap-1 text-blue-100 hover:underline">📎 {{ msg.file_name || '파일' }}</a>
                    <span v-else>{{ msg.message }}</span>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </template>
      </div>
      <!-- 입력창 -->
      <div class="bg-white border-t px-3 py-2 flex gap-2 flex-shrink-0 items-center safe-bottom">
        <label :class="{'opacity-40 pointer-events-none': !auth.isLoggedIn}" class="p-2 text-gray-400 hover:text-blue-500 cursor-pointer flex-shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
          </svg>
          <input type="file" class="hidden" @change="attachFile" accept="image/*,.pdf,.doc,.docx,.zip,.txt" :disabled="!auth.isLoggedIn" />
        </label>
        <input v-model="chatInput" @keyup.enter="sendMessage" type="text"
          placeholder="메시지를 입력하세요..." maxlength="500" :disabled="!auth.isLoggedIn"
          class="flex-1 min-w-0 border border-gray-200 rounded-full px-4 py-2.5 text-base focus:outline-none focus:border-blue-400 bg-gray-50" />
        <button @click="auth.isLoggedIn ? sendMessage() : $router.push('/auth/login')"
          :disabled="auth.isLoggedIn && (!chatInput.trim() || chatSending)"
          class="bg-blue-500 text-white rounded-full px-4 py-2 text-sm font-semibold disabled:opacity-50 flex-shrink-0 whitespace-nowrap">
          {{ auth.isLoggedIn ? (chatSending ? '...' : '전송') : '로그인' }}
        </button>
      </div>
    </div>

    <!-- ============================================================
         데스크탑 전용: 분할 레이아웃 (lg+)
    ============================================================ -->
    <div class="hidden lg:flex flex-col h-full">
      <!-- 상단 배너 + 카테고리 (flex-shrink-0) -->
      <div class="max-w-[1200px] mx-auto w-full px-4 pt-4 pb-3 flex-shrink-0">
        <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-5 rounded-2xl mb-2 flex items-center justify-between">
          <div><h1 class="text-xl font-black">💬 채팅방</h1><p class="text-sm opacity-80 mt-0.5">실시간 커뮤니티 채팅</p></div>
          <button @click="showCreateModal = true" class="bg-white text-blue-600 px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-50">+ 채팅방 개설</button>
        </div>
        <div class="flex gap-2 overflow-x-auto scrollbar-hide">
          <button v-for="cat in categories" :key="cat.id" @click="activeCat = cat.id"
            class="flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold transition"
            :class="activeCat===cat.id ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:border-blue-300'">
            {{ cat.icon }} {{ cat.label }}
          </button>
        </div>
      </div>

      <!-- 메인 영역 (남은 높이 전부) -->
      <div class="flex gap-4 flex-1 min-h-0 max-w-[1200px] mx-auto w-full px-4 pb-4">

        <!-- 왼쪽: 채팅방 목록 (스크롤바 숨김) -->
        <div class="w-[340px] flex-shrink-0 overflow-y-auto scrollbar-hide space-y-2">
          <div v-if="loading" class="text-center py-12 text-gray-400">불러오는 중...</div>
          <div v-for="room in filteredRooms" :key="room.slug" @click="openRoom(room)"
            class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition cursor-pointer border-2"
            :class="activeRoom?.slug===room.slug ? 'border-blue-500' : 'border-transparent'">
            <div class="h-14 flex items-center px-4 gap-3"
              :class="room.type==='regional' ? 'bg-gradient-to-r from-blue-400 to-cyan-400'
                : room.type==='theme' ? 'bg-gradient-to-r from-purple-400 to-pink-400'
                : 'bg-gradient-to-r from-green-400 to-emerald-400'">
              <span class="text-xl">{{ room.icon || '💬' }}</span>
              <div class="text-white flex-1 min-w-0">
                <p class="font-bold text-sm truncate">{{ room.name }}</p>
                <p class="text-white/70 text-xs truncate">{{ room.description }}</p>
              </div>
              <span v-if="getUnreadCount(room) > 0" class="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full flex-shrink-0">
                {{ getUnreadCount(room) > 300 ? '300+' : getUnreadCount(room) }}
              </span>
            </div>
            <div class="px-4 py-2 flex items-center justify-between">
              <span class="text-xs text-gray-400 flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-400"></span>실시간</span>
              <span class="text-xs text-blue-500 font-semibold">입장 →</span>
            </div>
          </div>
          <div v-if="!loading && filteredRooms.length===0" class="text-center py-12 text-gray-400 text-sm">해당 카테고리의 채팅방이 없습니다</div>
        </div>

        <!-- 오른쪽: 채팅 패널 -->
        <div class="flex-1 min-w-0 bg-white rounded-2xl shadow-sm flex flex-col overflow-hidden">
          <!-- 채팅방 미선택 -->
          <div v-if="!activeRoom" class="flex-1 flex flex-col items-center justify-center text-gray-400">
            <p class="text-5xl mb-4">💬</p>
            <p class="font-semibold text-gray-600 mb-1">채팅방을 선택하세요</p>
            <p class="text-sm">왼쪽에서 채팅방을 클릭하면 여기에 대화가 표시됩니다</p>
          </div>

          <template v-else>
            <!-- 채팅 헤더 -->
            <div class="px-4 py-3 border-b flex items-center gap-3 flex-shrink-0">
              <span class="text-xl">{{ activeRoom.icon || '💬' }}</span>
              <div class="flex-1 min-w-0">
                <p class="font-bold text-gray-800 truncate text-sm">{{ activeRoom.name }}</p>
                <p class="text-xs text-gray-400 truncate">{{ activeRoom.description }}</p>
              </div>
              <button @click="showSearch=!showSearch" :class="showSearch?'text-blue-500':'text-gray-400'" class="p-1.5 rounded-full hover:bg-gray-100 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
              </button>
              <span class="flex items-center gap-1 text-xs" :class="chatConnected?'text-green-500':'text-gray-400'">
                <span class="w-2 h-2 rounded-full" :class="chatConnected?'bg-green-400 animate-pulse':'bg-gray-300'"></span>
                {{ chatConnected ? '실시간' : '연결 중' }}
              </span>
            </div>
            <!-- 검색 바 -->
            <div v-if="showSearch" class="bg-white border-b px-3 py-2 flex gap-2 flex-shrink-0">
              <div class="flex-1 relative">
                <input v-model="searchQuery" type="text" placeholder="채팅 내용 검색..."
                  class="w-full border border-gray-200 rounded-full pl-4 pr-8 py-2 text-sm focus:outline-none focus:border-blue-400 bg-gray-50"
                  @input="doSearch" autofocus />
                <button v-if="searchQuery" @click="searchQuery='';searchResults=[]" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">✕</button>
              </div>
              <button @click="showSearch=false;searchQuery='';searchResults=[]" class="text-sm text-gray-400 px-2">닫기</button>
            </div>
            <!-- 검색 결과 -->
            <div v-if="searchResults.length" class="bg-amber-50 border-b px-3 py-2 flex-shrink-0 max-h-44 overflow-y-auto">
              <p class="text-xs text-amber-700 font-medium mb-1">{{ searchResults.length }}개 검색됨</p>
              <div v-for="msg in searchResults" :key="msg.id" @click="scrollToMessage(msg.id)"
                class="flex items-center gap-2 py-1.5 px-2 rounded hover:bg-amber-100 cursor-pointer text-xs">
                <span class="font-medium text-blue-700 flex-shrink-0">{{ chatUserName(msg.user) }}</span>
                <span class="text-gray-600 truncate flex-1">{{ msg.message }}</span>
                <span class="text-gray-400 flex-shrink-0">{{ formatDate(msg.created_at) }}</span>
              </div>
            </div>
            <!-- 메시지 영역 -->
            <div ref="msgContainer" class="flex-1 overflow-y-auto px-4 py-3 min-h-0 bg-gray-50" @click="activeMenu=null">
              <div v-if="chatLoading" class="text-center py-8 text-gray-400 text-sm">
                <div class="animate-spin w-6 h-6 border-2 border-blue-400 border-t-transparent rounded-full mx-auto mb-2"></div>불러오는 중...
              </div>
              <div v-else-if="!filteredMessages.length" class="text-center py-12 text-gray-400">
                <p class="text-3xl mb-2">💬</p><p class="text-sm">첫 메시지를 남겨보세요!</p>
              </div>
              <template v-for="(group, dateLabel) in groupedMessages" :key="dateLabel">
                <div class="flex items-center gap-3 my-4">
                  <div class="flex-1 h-px bg-gray-200"></div>
                  <span class="text-xs text-gray-500 bg-white border border-gray-200 px-3 py-0.5 rounded-full shadow-sm">{{ dateLabel }}</span>
                  <div class="flex-1 h-px bg-gray-200"></div>
                </div>
                <div v-for="msg in group" :key="msg.id" :id="`msg-${msg.id}`"
                  class="flex gap-2 mb-2 transition-colors duration-500"
                  :class="isMe(msg)?'justify-end':'justify-start'">
                  <template v-if="!isMe(msg)">
                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-xs font-bold text-blue-700 flex-shrink-0 mt-1 overflow-hidden">
                      <img v-if="msg.user?.avatar" :src="msg.user.avatar" class="w-full h-full object-cover" />
                      <span v-else>{{ chatUserName(msg.user)[0] }}</span>
                    </div>
                    <div class="max-w-[70%]">
                      <p class="text-xs text-gray-500 mb-0.5 ml-1">{{ chatUserName(msg.user) }}</p>
                      <div class="flex items-end gap-1">
                        <div class="bg-white rounded-2xl rounded-tl-none px-3 py-2 shadow-sm text-gray-800 text-sm">
                          <img v-if="msg.file_type==='image'" :src="msg.file_url" class="max-w-full rounded-lg max-h-48 object-contain cursor-pointer" @click.stop="openImage(msg.file_url)" />
                          <a v-else-if="msg.file_url" :href="msg.file_url" target="_blank" class="flex items-center gap-1 text-blue-600 hover:underline">📎 {{ msg.file_name||'파일' }}</a>
                          <span v-else>{{ msg.message }}</span>
                        </div>
                        <span class="text-xs text-gray-400 whitespace-nowrap mb-1">{{ formatTime(msg.created_at) }}</span>
                        <button v-if="auth.isLoggedIn" @click.stop="toggleMenu(msg.id)" class="text-gray-300 hover:text-gray-500 mb-1 p-0.5 flex-shrink-0">
                          <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
                        </button>
                      </div>
                      <div v-if="activeMenu===msg.id" class="bg-white rounded-xl shadow-lg border text-sm overflow-hidden mt-1 w-36 z-30 relative">
                        <button @click.stop="blockUser(msg.user)" class="flex items-center gap-2 w-full px-3 py-2.5 hover:bg-gray-50 text-gray-700">🚫 차단하기</button>
                        <div class="h-px bg-gray-100"></div>
                        <button @click.stop="reportMsg(msg)" class="flex items-center gap-2 w-full px-3 py-2.5 hover:bg-red-50 text-red-600">🚩 신고하기</button>
                      </div>
                    </div>
                  </template>
                  <template v-else>
                    <div class="max-w-[70%]">
                      <div class="flex items-end gap-1 justify-end">
                        <span class="text-xs text-gray-400 whitespace-nowrap mb-1">{{ formatTime(msg.created_at) }}</span>
                        <div class="bg-blue-500 text-white rounded-2xl rounded-tr-none px-3 py-2 shadow-sm text-sm">
                          <img v-if="msg.file_type==='image'" :src="msg.file_url" class="max-w-full rounded-lg max-h-48 object-contain cursor-pointer" @click.stop="openImage(msg.file_url)" />
                          <a v-else-if="msg.file_url" :href="msg.file_url" target="_blank" class="flex items-center gap-1 text-blue-100 hover:underline">📎 {{ msg.file_name||'파일' }}</a>
                          <span v-else>{{ msg.message }}</span>
                        </div>
                      </div>
                    </div>
                  </template>
                </div>
              </template>
            </div>
            <!-- 입력창 -->
            <div class="border-t px-3 py-2 flex gap-2 flex-shrink-0 bg-white items-center">
              <label :class="{'opacity-40 pointer-events-none':!auth.isLoggedIn}" class="p-2 text-gray-400 hover:text-blue-500 cursor-pointer flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                </svg>
                <input type="file" class="hidden" @change="attachFile" accept="image/*,.pdf,.doc,.docx,.zip,.txt" :disabled="!auth.isLoggedIn" />
              </label>
              <input v-model="chatInput" @keyup.enter="sendMessage" type="text"
                placeholder="메시지를 입력하세요..." maxlength="500" :disabled="!auth.isLoggedIn"
                class="flex-1 min-w-0 border border-gray-200 rounded-full px-4 py-2.5 text-base focus:outline-none focus:border-blue-400 bg-gray-50" />
              <button @click="auth.isLoggedIn ? sendMessage() : $router.push('/auth/login')"
                :disabled="auth.isLoggedIn && (!chatInput.trim() || chatSending)"
                class="bg-blue-500 text-white rounded-full px-4 py-2 text-sm font-semibold disabled:opacity-50 flex-shrink-0 whitespace-nowrap">
                {{ auth.isLoggedIn ? (chatSending ? '...' : '전송') : '로그인' }}
              </button>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- 이미지 전체화면 -->
    <div v-if="lightboxUrl" class="fixed inset-0 bg-black/90 z-[100] flex items-center justify-center" @click="lightboxUrl=null">
      <img :src="lightboxUrl" class="max-w-full max-h-full object-contain rounded" />
    </div>

    <!-- 채팅방 개설 모달 -->
    <Transition name="fade">
      <div v-if="showCreateModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 px-4" @click.self="showCreateModal=false">
        <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl overflow-hidden">
          <div class="bg-gradient-to-r from-blue-600 to-blue-500 px-6 py-4 text-white">
            <h2 class="font-black text-lg">💬 채팅방 개설</h2>
          </div>
          <div class="p-5 space-y-4">
            <div>
              <label class="block text-xs text-gray-500 mb-1 font-semibold">채팅방 이름 *</label>
              <input v-model="newRoom.name" type="text" maxlength="30" placeholder="예: 주말 모임..."
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1 font-semibold">친구 초대</label>
              <input v-model="friendSearch" type="text" placeholder="친구 이름 검색..."
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 mb-2" @input="searchFriends" />
              <div v-if="selectedFriends.length" class="flex flex-wrap gap-1.5 mb-2">
                <span v-for="f in selectedFriends" :key="f.id"
                  class="bg-blue-100 text-blue-700 text-xs px-2.5 py-1 rounded-full flex items-center gap-1">
                  {{ f.name }}<button @click="removeFriend(f.id)" class="text-blue-400 hover:text-red-500 font-bold">×</button>
                </span>
              </div>
              <div v-if="friendResults.length" class="border border-gray-100 rounded-xl max-h-40 overflow-y-auto">
                <div v-for="f in friendResults" :key="f.id" @click="addFriend(f)"
                  class="flex items-center gap-2 px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0">
                  <div class="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center text-xs font-bold text-blue-600">{{ (f.name||'?')[0] }}</div>
                  <span class="text-sm">{{ f.name }}</span>
                  <span class="text-xs text-gray-400">@{{ f.username }}</span>
                </div>
              </div>
            </div>
            <div class="flex gap-3 pt-2">
              <button @click="showCreateModal=false" class="flex-1 border border-gray-200 text-gray-600 py-2.5 rounded-xl font-semibold text-sm">취소</button>
              <button @click="createRoom" :disabled="!newRoom.name.trim()||creating" class="flex-1 bg-blue-600 text-white py-2.5 rounded-xl font-bold text-sm disabled:opacity-50">
                {{ creating ? '생성 중...' : '개설하기' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const auth = useAuthStore()

const isMobile  = ref(window.innerWidth < 1024)
function onResize() { isMobile.value = window.innerWidth < 1024 }

const activeCat = ref('all')
const categories = [
  { id: 'all',      icon: '🌐', label: '전체' },
  { id: 'regional', icon: '📍', label: '지역별' },
  { id: 'theme',    icon: '🎨', label: '테마별' },
  { id: 'friend',   icon: '👥', label: '내 친구' },
]

const rooms   = ref([])
const loading = ref(true)

const filteredRooms = computed(() => {
  if (activeCat.value === 'all') return rooms.value
  return rooms.value.filter(r => r.type === activeCat.value)
})

// ── 안읽음 추적 ──
const lastReadIds  = ref({})
const unreadCounts = ref({})

function loadLastRead() {
  try { lastReadIds.value = JSON.parse(localStorage.getItem('chatLastRead') || '{}') } catch {}
}
function saveLastRead(slug, id) {
  lastReadIds.value[slug] = id
  localStorage.setItem('chatLastRead', JSON.stringify(lastReadIds.value))
}
function getUnreadCount(room) { return unreadCounts.value[room.slug] ?? 0 }
function computeUnread() {
  rooms.value.forEach(r => {
    const last = lastReadIds.value[r.slug] ?? 0
    unreadCounts.value[r.slug] = (r.last_message_id ?? 0) > last ? 1 : 0
  })
}

// ── 채팅 상태 ──
const activeRoom         = ref(null)
const chatMessages       = ref([])
const blockedIds         = ref([])
const chatInput          = ref('')
const chatSending        = ref(false)
const chatLoading        = ref(false)
const chatConnected      = ref(false)
const msgContainer       = ref(null)
const msgContainerMobile = ref(null)
const activeMenu         = ref(null)
const showSearch         = ref(false)
const searchQuery        = ref('')
const searchResults      = ref([])
const lightboxUrl        = ref(null)
let chatChannel          = null
let searchTimeout        = null

const filteredMessages = computed(() =>
  chatMessages.value.filter(m => !blockedIds.value.includes(m.user?.id ?? m.user_id))
)

const groupedMessages = computed(() => {
  const g = {}
  filteredMessages.value.forEach(m => {
    const k = formatDate(m.created_at)
    if (!g[k]) g[k] = []
    g[k].push(m)
  })
  return g
})

function formatDate(dt) {
  if (!dt) return ''
  const d = new Date(dt), t = new Date(), y = new Date(t)
  y.setDate(y.getDate() - 1)
  if (d.toDateString() === t.toDateString()) return '오늘'
  if (d.toDateString() === y.toDateString()) return '어제'
  return d.toLocaleDateString('ko-KR', { year: 'numeric', month: 'long', day: 'numeric', weekday: 'short' })
}
function formatTime(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' })
}
function chatUserName(u) { return u?.name || u?.username || '?' }
function isMe(msg) { return msg.user?.id === auth.user?.id || msg.user_id === auth.user?.id }
function openImage(url) { lightboxUrl.value = url }
function toggleMenu(id) { activeMenu.value = activeMenu.value === id ? null : id }

async function getContainer() {
  await nextTick()
  return isMobile.value ? msgContainerMobile.value : msgContainer.value
}
async function scrollBottom() {
  const el = await getContainer()
  if (el) el.scrollTop = el.scrollHeight
}
async function scrollToFirstUnread(lastId) {
  await nextTick()
  const first = chatMessages.value.find(m => m.id > lastId)
  if (first) {
    const el = document.getElementById(`msg-${first.id}`)
    if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); return }
  }
  await scrollBottom()
}
async function scrollToMessage(id) {
  searchResults.value = []; showSearch.value = false
  await nextTick()
  const el = document.getElementById(`msg-${id}`)
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'center' })
    el.style.backgroundColor = '#fef9c3'
    setTimeout(() => { el.style.backgroundColor = '' }, 2000)
  }
}
function doSearch() {
  clearTimeout(searchTimeout)
  if (!searchQuery.value || searchQuery.value.length < 2) { searchResults.value = []; return }
  searchTimeout = setTimeout(async () => {
    try {
      const { data } = await axios.get(`/api/chat/rooms/${activeRoom.value.slug}/search`, { params: { q: searchQuery.value } })
      searchResults.value = data
    } catch {}
  }, 300)
}

function isCreator(room) {
  return auth.user && room.created_by && room.created_by === auth.user.id
}

// 방장 채팅방 이름 수정
async function renameRoom(room) {
  const newName = prompt('새 채팅방 이름:', room.name)
  if (!newName || !newName.trim() || newName.trim() === room.name) return
  try {
    await axios.patch(`/api/chat/rooms/${room.slug}`, { name: newName.trim() })
    room.name = newName.trim()
  } catch (e) { alert(e?.response?.data?.message || '수정 실패') }
}

// 방장 채팅방 삭제
async function destroyRoom(room) {
  if (!confirm(`"${room.name}" 채팅방을 삭제하시겠습니까?`)) return
  try {
    await axios.delete(`/api/chat/rooms/${room.slug}`)
    rooms.value = rooms.value.filter(r => r.slug !== room.slug)
    if (activeRoom.value?.slug === room.slug) activeRoom.value = null
  } catch (e) { alert(e?.response?.data?.message || '삭제 실패') }
}

async function openRoom(room) {
  if (activeRoom.value?.slug === room.slug) return
  if (activeRoom.value && chatChannel) {
    saveLastRead(activeRoom.value.slug, chatMessages.value.at(-1)?.id ?? 0)
    window.Echo?.leaveChannel(`chat.${activeRoom.value.id}`)
    chatChannel = null
  }
  activeRoom.value = room
  chatMessages.value = []; blockedIds.value = []
  chatLoading.value = true; chatConnected.value = false
  showSearch.value = false; searchQuery.value = ''; searchResults.value = []; activeMenu.value = null
  const prevId = lastReadIds.value[room.slug] ?? 0

  try {
    const { data } = await axios.get(`/api/chat/rooms/${room.slug}`)
    if (data.room) activeRoom.value = { ...room, ...data.room }
    chatMessages.value = data.messages ?? []
    blockedIds.value   = data.blocked_ids ?? []
    unreadCounts.value[room.slug] = 0
    if (prevId > 0) await scrollToFirstUnread(prevId)
    else await scrollBottom()
    const lastId = chatMessages.value.at(-1)?.id ?? 0
    if (lastId) saveLastRead(room.slug, lastId)
  } catch (e) { console.error(e) }
  finally { chatLoading.value = false }

  if (window.Echo && activeRoom.value?.id) {
    chatChannel = window.Echo.channel(`chat.${activeRoom.value.id}`)
    chatChannel
      .listen('.message.sent', (msg) => {
        if (!chatMessages.value.some(m => m.id === msg.id)) {
          chatMessages.value.push(msg)
          saveLastRead(activeRoom.value.slug, msg.id)
          scrollBottom()
        }
      })
      .subscribed(() => { chatConnected.value = true })
      .error(() => { chatConnected.value = false })
  }
}

function closeRoom() {
  if (activeRoom.value) {
    saveLastRead(activeRoom.value.slug, chatMessages.value.at(-1)?.id ?? 0)
    if (chatChannel) { window.Echo?.leaveChannel(`chat.${activeRoom.value.id}`); chatChannel = null }
  }
  activeRoom.value = null
}

async function sendMessage() {
  if (!chatInput.value.trim() || chatSending.value || !activeRoom.value) return
  const text = chatInput.value.trim(); chatInput.value = ''; chatSending.value = true
  try {
    const sid = window.Echo?.socketId?.()
    const { data } = await axios.post(`/api/chat/rooms/${activeRoom.value.slug}/messages`,
      { message: text }, { headers: sid ? { 'X-Socket-ID': sid } : {} })
    chatMessages.value.push(data)
    saveLastRead(activeRoom.value.slug, data.id)
    await scrollBottom()
  } catch { chatInput.value = text }
  finally { chatSending.value = false }
}

async function attachFile(e) {
  const file = e.target.files[0]
  if (!file || !auth.isLoggedIn || !activeRoom.value) return
  const fd = new FormData(); fd.append('file', file)
  chatSending.value = true
  try {
    const sid = window.Echo?.socketId?.()
    const { data } = await axios.post(`/api/chat/rooms/${activeRoom.value.slug}/messages`, fd,
      { headers: sid ? { 'X-Socket-ID': sid } : {} })
    chatMessages.value.push(data)
    saveLastRead(activeRoom.value.slug, data.id)
    await scrollBottom()
  } catch { alert('파일 전송 실패. 10MB 이하여야 합니다.') }
  finally { chatSending.value = false; e.target.value = '' }
}

async function blockUser(user) {
  activeMenu.value = null
  if (!user?.id || !auth.isLoggedIn) return
  if (!confirm(`${chatUserName(user)}님을 차단하시겠습니까?`)) return
  try { await axios.post(`/api/chat/block/${user.id}`); blockedIds.value.push(user.id) } catch {}
}

async function reportMsg(msg) {
  activeMenu.value = null
  if (!auth.isLoggedIn) return
  try { await axios.post(`/api/chat/report/${msg.id}`); alert('신고가 접수되었습니다.') } catch {}
}

onMounted(async () => {
  loadLastRead(); window.addEventListener('resize', onResize)
  try {
    const { data } = await axios.get('/api/chat/rooms')
    rooms.value = data; computeUnread()
  } catch {} finally { loading.value = false }
})
onUnmounted(() => {
  window.removeEventListener('resize', onResize)
  if (activeRoom.value?.id) {
    saveLastRead(activeRoom.value.slug, chatMessages.value.at(-1)?.id ?? 0)
    window.Echo?.leaveChannel(`chat.${activeRoom.value.id}`)
  }
  clearTimeout(searchTimeout)
})

// ── 채팅방 개설 ──
const showCreateModal = ref(false), creating = ref(false)
const newRoom = ref({ name: '' })
const friendSearch = ref(''), friendResults = ref([]), selectedFriends = ref([]), friendSearching = ref(false)
let searchTimer = null
function searchFriends() {
  clearTimeout(searchTimer)
  if (!friendSearch.value.trim()) { friendResults.value = []; return }
  searchTimer = setTimeout(async () => {
    friendSearching.value = true
    try { const { data } = await axios.get('/api/friends/search', { params: { q: friendSearch.value } }); friendResults.value = data.filter(u => !selectedFriends.value.some(s => s.id === u.id)) }
    catch {} finally { friendSearching.value = false }
  }, 300)
}
function addFriend(f) { if (!selectedFriends.value.some(s => s.id === f.id)) selectedFriends.value.push(f); friendResults.value = friendResults.value.filter(r => r.id !== f.id); friendSearch.value = '' }
function removeFriend(id) { selectedFriends.value = selectedFriends.value.filter(f => f.id !== id) }
async function createRoom() {
  if (!newRoom.value.name.trim()) return; creating.value = true
  try {
    const { data } = await axios.post('/api/chat/rooms', { name: newRoom.value.name, type: 'friend', invite_users: selectedFriends.value.map(f => f.id) })
    for (const f of selectedFriends.value) { try { await axios.post('/api/messages', { recipient_id: f.id, content: `💬 "${newRoom.value.name}" 채팅방에 초대되었습니다!` }) } catch {} }
    if (data) { rooms.value.unshift(data); openRoom(data) }
    showCreateModal.value = false; newRoom.value = { name: '' }; selectedFriends.value = []
  } catch (e) { alert(e?.response?.data?.message || '채팅방 개설 실패') }
  finally { creating.value = false }
}
</script>

<style scoped>
.chat-page-wrap {
  min-height: calc(100vh - 48px);
  max-width: 1200px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
}
@media (min-width: 768px) {
  .chat-page-wrap { min-height: calc(100vh - 84px); }
}
/* 스크롤바 숨김 */
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
/* 모바일 safe area */
.safe-bottom { padding-bottom: max(0.5rem, env(safe-area-inset-bottom)); }
/* 트랜지션 */
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
