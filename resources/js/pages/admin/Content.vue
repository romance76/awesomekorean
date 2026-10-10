<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <form @submit.prevent="load()" class="flex gap-2">
    <label class="flex-1 min-w-0 flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 min-h-[48px] text-ink-muted">
      <AppIcon name="search" :size="18" />
      <input v-model="search" type="search" placeholder="제목·작성자 검색" autocomplete="off" aria-label="게시글 검색" class="w-full min-w-0 bg-transparent outline-none text-ink" />
    </label>
    <button type="submit" class="shrink-0 min-h-[48px] px-4 rounded-xl bg-amber-500 text-white text-[15px] font-bold">검색</button>
  </form>
  <div class="flex gap-2 overflow-x-auto scrollbar-hide" role="group" aria-label="게시판 선택">
    <button @click="pickBoard('')" :aria-pressed="boardFilter === ''" class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px]" :class="boardFilter === '' ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200 font-medium'">전체</button>
    <button v-for="b in boards" :key="b.id" @click="pickBoard(b.id)" :aria-pressed="boardFilter === b.id" class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px]" :class="boardFilter === b.id ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200 font-medium'">{{ b.name }}</button>
  </div>
  <div class="text-[14px] text-ink-muted px-0.5">전체 {{ totalPosts.toLocaleString() }}건</div>

  <div v-if="loading" class="text-center py-10 text-ink-muted text-[15px]">불러오는 중...</div>
  <div v-else-if="!posts.length" class="text-center py-12 text-ink-muted text-[15px]">게시글이 없어요.</div>
  <div v-else class="space-y-2">
    <button v-for="p in posts" :key="p.id" @click="openPost(p)" class="w-full text-left bg-white border border-gray-100 rounded-2xl p-3.5 min-h-[76px] active:bg-amber-50" :class="p.is_hidden ? 'opacity-60' : ''">
      <span class="flex items-center gap-1.5 flex-wrap">
        <span class="text-[12px] font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded">{{ p.board?.name || '-' }}</span>
        <span v-if="p.is_pinned" class="text-[12px] font-bold text-red-600 bg-red-50 px-1.5 py-0.5 rounded">고정</span>
        <span v-if="p.is_hidden" class="text-[12px] font-bold text-gray-600 bg-gray-100 px-1.5 py-0.5 rounded">숨김</span>
      </span>
      <span class="block text-[16px] font-bold text-ink leading-snug break-words line-clamp-2 mt-1">{{ p.title }}</span>
      <span class="flex items-center gap-x-3 gap-y-1 flex-wrap mt-1.5 text-[13px] text-ink-muted">
        <span>{{ p.user?.name || '-' }}</span><span>{{ p.created_at?.slice(0, 10) }}</span><span>💬 {{ p.comment_count || 0 }}</span><span>👁 {{ p.view_count || 0 }}</span>
      </span>
    </button>
    <div v-if="lastPage > 1" class="flex items-center justify-between gap-2 pt-2">
      <button @click="load(page - 1)" :disabled="page <= 1" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">이전</button>
      <span class="text-[14px] text-ink-muted tabular-nums">{{ page }} / {{ lastPage }}</span>
      <button @click="load(page + 1)" :disabled="page >= lastPage" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">다음</button>
    </div>
  </div>

  <Teleport to="body">
    <!-- 게시글 상세 (전체 화면) -->
    <div v-if="activePost" class="alv-m fixed inset-0 z-[60] bg-white flex flex-col" role="dialog" aria-modal="true" aria-label="게시글 상세">
      <div class="shrink-0 flex items-center gap-2 px-2 border-b border-gray-100" :style="{ paddingTop: 'env(safe-area-inset-top, 0px)' }">
        <button @click="closePost" class="min-h-[52px] min-w-[52px] grid place-items-center text-[22px]" aria-label="목록으로">←</button>
        <div class="flex-1 min-w-0 text-[16px] font-bold text-ink truncate">게시글 상세</div>
      </div>
      <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
        <div class="flex items-center gap-1.5 flex-wrap">
          <span class="text-[12px] font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded">{{ activePost.board?.name || '게시판' }}</span>
          <span v-if="activePost.is_pinned" class="text-[12px] font-bold text-red-600 bg-red-50 px-1.5 py-0.5 rounded">고정</span>
          <span v-if="activePost.is_hidden" class="text-[12px] font-bold text-gray-600 bg-gray-100 px-1.5 py-0.5 rounded">숨김</span>
        </div>
        <h2 class="text-[19px] font-bold text-ink leading-snug break-words">{{ activePost.title }}</h2>
        <div class="flex items-center gap-x-3 gap-y-1 flex-wrap text-[13px] text-ink-muted">
          <button v-if="activePost.user?.id" @click="goUser(activePost.user)" class="min-h-[40px] text-blue-600 font-bold text-[14px]">{{ activePost.user?.name }}</button>
          <span>{{ activePost.created_at?.slice(0, 10) }}</span><span>👁 {{ activePost.view_count || 0 }}</span><span>❤️ {{ activePost.like_count || 0 }}</span>
        </div>
        <div class="text-[15px] text-ink-light leading-relaxed whitespace-pre-wrap break-words bg-gray-50 rounded-2xl p-3.5">{{ activePost.content }}</div>
        <div v-if="activePost.comments?.length">
          <div class="text-[15px] font-bold text-ink mb-2">💬 댓글 {{ activePost.comments.length }}개</div>
          <div v-for="c in activePost.comments" :key="c.id" class="border-b border-gray-100 last:border-0 py-2.5">
            <div class="flex items-center gap-2">
              <span class="text-[14px] font-bold text-ink">{{ c.user?.name }}</span>
              <span class="text-[12px] text-ink-faint">{{ c.created_at?.slice(0, 10) }}</span>
              <button @click="ask({ kind: 'comment', c })" class="ml-auto min-h-[44px] px-3 text-[14px] font-bold text-red-500">삭제</button>
            </div>
            <div class="text-[14px] text-ink-light break-words">{{ c.content }}</div>
          </div>
        </div>
      </div>
      <div class="shrink-0 grid grid-cols-3 gap-2 px-4 pt-3 border-t border-gray-100" :style="{ paddingBottom: 'calc(12px + env(safe-area-inset-bottom, 0px))' }">
        <button @click="pinPost(activePost)" class="min-h-[50px] rounded-xl text-[15px] font-bold" :class="activePost.is_pinned ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-ink'">{{ activePost.is_pinned ? '고정 해제' : '고정' }}</button>
        <button @click="hidePost(activePost)" class="min-h-[50px] rounded-xl text-[15px] font-bold" :class="activePost.is_hidden ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-ink'">{{ activePost.is_hidden ? '보이기' : '숨기기' }}</button>
        <button @click="ask({ kind: 'post', p: activePost })" class="min-h-[50px] rounded-xl bg-red-50 text-red-600 text-[15px] font-bold">삭제</button>
      </div>
    </div>

    <!-- 삭제 확인 시트 -->
    <div v-if="sheet" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="sheet = null">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2" role="dialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-1">{{ sheet.kind === 'post' ? '게시글을 삭제할까요?' : '댓글을 삭제할까요?' }}</div>
        <p class="text-[15px] text-ink-light mb-3 break-words">{{ sheet.kind === 'post' ? sheet.p.title : sheet.c.content }}</p>
        <button @click="doDelete" :disabled="busy" class="w-full min-h-[52px] rounded-xl bg-red-500 text-white text-[16px] font-bold disabled:opacity-40">{{ busy ? '삭제 중...' : '삭제하기' }}</button>
        <button @click="sheet = null" :disabled="busy" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
      </div>
    </div>
    <div v-if="toast" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg" :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
    <span class="icon-chip w-9 h-9 bg-blue-50 text-blue-600"><AppIcon name="edit" :size="20" /></span>
    콘텐츠 관리
  </h1>

  <!-- 검색 + 필터 -->
  <div class="card p-3 mb-4">
    <div class="flex flex-wrap gap-2">
      <select v-model="boardFilter" @change="load()" class="input-soft w-auto px-3 py-1.5 text-xs">
        <option value="">전체 게시판</option>
        <option v-for="b in boards" :key="b.id" :value="b.id">{{ b.name }}</option>
      </select>
      <form @submit.prevent="load()" class="flex-1 flex gap-1.5 min-w-[150px]">
        <input v-model="search" type="text" placeholder="제목/작성자 검색..." class="flex-1 input-soft px-3 py-1.5" />
        <button type="submit" class="btn-primary px-3 py-1.5 text-xs">검색</button>
      </form>
    </div>
    <div class="text-[11px] text-ink-faint mt-1">전체 {{ totalPosts }}건</div>
  </div>

  <div class="flex gap-4">
    <!-- 왼쪽: 목록 -->
    <div :class="activePost ? 'w-1/2' : 'w-full'">
      <div v-if="loading" class="text-center py-8 text-ink-muted">로딩중...</div>
      <div v-else class="card overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-100"><tr>
            <SortTh class="px-2 py-2 text-left text-xs text-ink-muted w-8" k="id" :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">#</SortTh>
            <SortTh class="px-2 py-2 text-left text-xs text-ink-muted" k="title" :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">제목</SortTh>
            <SortTh v-if="!activePost" class="px-2 py-2 text-left text-xs text-ink-muted" k="board" :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">게시판</SortTh>
            <SortTh class="px-2 py-2 text-left text-xs text-ink-muted" k="author" :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">작성자</SortTh>
            <SortTh class="px-2 py-2 text-xs text-ink-muted" k="comments" :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort"><AppIcon name="message-circle" :size="13" class="mx-auto" /></SortTh>
            <SortTh class="px-2 py-2 text-xs text-ink-muted" k="views" :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort"><AppIcon name="eye" :size="13" class="mx-auto" /></SortTh>
            <SortTh class="px-2 py-2 text-xs text-ink-muted" k="created_at" :sort-key="sortKey" :sort-dir="sortDir" @sort="toggleSort">날짜</SortTh>
            <th class="px-2 py-2 text-xs text-ink-muted">관리</th>
          </tr></thead>
          <tbody>
            <tr v-for="p in posts" :key="p.id"
              class="border-b border-gray-50 last:border-0 hover:bg-amber-50/40 cursor-pointer transition-colors"
              :class="[p.is_hidden ? 'opacity-40 bg-red-50/30' : '', activePost?.id===p.id ? 'bg-amber-50 border-l-2 border-l-amber-500' : '']"
              @click="openPost(p)">
              <td class="px-2 py-2 text-xs text-ink-faint">{{ p.id }}</td>
              <td class="px-2 py-2 max-w-[200px]">
                <div class="truncate text-sm font-medium text-ink">
                  <span v-if="p.is_pinned" class="inline-flex align-middle text-amber-500 mr-1"><AppIcon name="bookmark" :size="12" :filled="true" /></span>
                  {{ p.title }}
                </div>
              </td>
              <td v-if="!activePost" class="px-2 py-2"><span class="badge-primary !text-[11px]">{{ p.board?.name || '-' }}</span></td>
              <td class="px-2 py-2">
                <button @click.stop="openUserModal(p.user)" class="text-xs text-blue-600 hover:underline">{{ p.user?.name }}</button>
              </td>
              <td class="px-2 py-2 text-center text-xs text-ink-muted">{{ p.comment_count }}</td>
              <td class="px-2 py-2 text-center text-xs text-ink-faint">{{ p.view_count }}</td>
              <td class="px-2 py-2 text-[11px] text-ink-faint">{{ p.created_at?.slice(5,10) }}</td>
              <td class="px-2 py-2 text-center space-x-1" @click.stop>
                <button @click="pinPost(p)" class="transition-colors" :class="p.is_pinned?'text-amber-500':'text-gray-300 hover:text-amber-500'" title="고정"><AppIcon name="bookmark" :size="14" :filled="p.is_pinned" /></button>
                <button @click="hidePost(p)" class="transition-colors" :class="p.is_hidden?'text-green-500':'text-gray-300 hover:text-red-500'" :title="p.is_hidden ? '보이기' : '숨기기'"><AppIcon :name="p.is_hidden ? 'eye' : 'x'" :size="14" /></button>
                <button @click="deletePost(p)" class="text-gray-300 hover:text-red-600 transition-colors" title="삭제"><AppIcon name="trash" :size="14" /></button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
        <button v-for="pg in Math.min(lastPage, 10)" :key="pg" @click="load(pg)"
          class="w-8 h-8 rounded-lg text-sm transition-colors" :class="pg===page?'bg-amber-400 text-white font-bold':'text-ink-muted hover:bg-gray-100'">{{ pg }}</button>
      </div>
    </div>

    <!-- 오른쪽: 인라인 게시글 뷰 -->
    <div v-if="activePost" class="w-1/2">
      <div class="card overflow-hidden sticky top-4">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between bg-amber-50">
          <span class="font-bold text-sm text-amber-700">게시글 상세</span>
          <button @click="activePost=null" class="text-ink-muted hover:text-ink transition-colors"><AppIcon name="x" :size="18" /></button>
        </div>
        <div class="px-4 py-3">
          <div class="flex items-center gap-2 mb-2">
            <span class="badge-primary !text-[11px]">{{ activePost.board?.name || '게시판' }}</span>
            <span v-if="activePost.is_pinned" class="badge-blue !text-[11px]">고정</span>
            <span v-if="activePost.is_hidden" class="badge-red !text-[11px]">숨김</span>
          </div>
          <h2 class="text-lg font-bold text-ink">{{ activePost.title }}</h2>
          <div class="flex items-center gap-3 mt-2 text-xs text-ink-faint">
            <button @click="openUserModal(activePost.user)" class="text-blue-600 hover:underline font-semibold">{{ activePost.user?.name }}</button>
            <span>{{ activePost.user?.email }}</span>
            <span>{{ activePost.created_at?.slice(0,10) }}</span>
            <span>{{ activePost.view_count }}회</span>
            <span>❤️ {{ activePost.like_count }}</span>
          </div>
        </div>
        <div class="px-4 py-4 border-t border-gray-100 text-sm text-ink-light leading-relaxed whitespace-pre-wrap max-h-[400px] overflow-y-auto">{{ activePost.content }}</div>

        <!-- 댓글 -->
        <div v-if="activePost.comments?.length" class="px-4 py-3 border-t border-gray-100">
          <div class="flex items-center gap-1.5 font-bold text-xs text-ink mb-2"><AppIcon name="message-circle" :size="13" />댓글 {{ activePost.comments.length }}개</div>
          <div v-for="c in activePost.comments" :key="c.id" class="py-2 border-b border-gray-50 last:border-0">
            <div class="flex items-center gap-2">
              <button @click="openUserModal(c.user)" class="text-xs text-blue-600 hover:underline font-semibold">{{ c.user?.name }}</button>
              <span class="text-[11px] text-ink-faint">{{ c.created_at?.slice(0,10) }}</span>
              <button @click="deleteComment(c.id)" class="text-[11px] text-red-400 hover:text-red-600 ml-auto transition-colors">삭제</button>
            </div>
            <div class="text-xs text-ink-light mt-0.5">{{ c.content }}</div>
          </div>
        </div>

        <!-- 관리 버튼 -->
        <div class="px-4 py-3 border-t border-gray-100 flex gap-2">
          <button @click="pinPost(activePost)" class="inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded-lg transition-colors" :class="activePost.is_pinned?'bg-amber-100 text-amber-700':'bg-gray-100 text-ink-light'">
            <AppIcon name="bookmark" :size="13" :filled="activePost.is_pinned" />{{ activePost.is_pinned ? '고정 해제' : '고정' }}
          </button>
          <button @click="hidePost(activePost)" class="inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded-lg transition-colors" :class="activePost.is_hidden?'bg-green-100 text-green-700':'bg-red-100 text-red-700'">
            <AppIcon :name="activePost.is_hidden ? 'eye' : 'x'" :size="13" />{{ activePost.is_hidden ? '보이기' : '숨기기' }}
          </button>
          <button @click="deletePost(activePost); activePost=null" class="inline-flex items-center gap-1 text-xs bg-red-100 text-red-700 px-3 py-1.5 rounded-lg hover:bg-red-200 transition-colors"><AppIcon name="trash" :size="13" />삭제</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══ 회원 상세 모달 ═══ -->
  <div v-if="userModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="userModal=null">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-amber-50 sticky top-0">
        <span class="flex items-center gap-2 font-bold text-ink"><span class="icon-chip w-7 h-7 bg-white text-amber-600"><AppIcon name="user" :size="15" /></span>회원 상세 정보</span>
        <button @click="userModal=null" class="text-ink-muted hover:text-ink transition-colors"><AppIcon name="x" :size="20" /></button>
      </div>

      <div v-if="userLoading" class="py-12 text-center text-ink-muted">로딩중...</div>
      <div v-else-if="userData" class="p-5">
        <!-- 탭 -->
        <div class="flex gap-1 mb-4 border-b border-gray-100">
          <button v-for="t in userTabs" :key="t.key" @click="userTab=t.key"
            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors"
            :class="userTab===t.key?'border-amber-500 text-amber-700':'border-transparent text-ink-muted hover:text-ink-light'">{{ t.label }}</button>
        </div>

        <!-- 기본 정보 (수정 가능) -->
        <div v-show="userTab==='info'">
          <div class="grid grid-cols-2 gap-3">
            <div><label class="input-label !text-xs !mb-0.5">이름</label><input v-model="userData.user.name" class="input-soft px-3 py-1.5" /></div>
            <div><label class="input-label !text-xs !mb-0.5">닉네임</label><input v-model="userData.user.nickname" class="input-soft px-3 py-1.5" /></div>
            <div><label class="input-label !text-xs !mb-0.5">이메일</label><input v-model="userData.user.email" class="input-soft px-3 py-1.5" /></div>
            <div><label class="input-label !text-xs !mb-0.5">전화번호</label><input v-model="userData.user.phone" class="input-soft px-3 py-1.5" /></div>
            <div><label class="input-label !text-xs !mb-0.5">도시</label><input v-model="userData.user.city" class="input-soft px-3 py-1.5" /></div>
            <div><label class="input-label !text-xs !mb-0.5">주</label><input v-model="userData.user.state" class="input-soft px-3 py-1.5" /></div>
            <div><label class="input-label !text-xs !mb-0.5">역할</label>
              <select v-model="userData.user.role" class="input-soft px-3 py-1.5">
                <option value="user">일반회원</option><option value="business">기업회원</option><option value="moderator">운영자</option><option value="admin">관리자</option><option value="super_admin">슈퍼관리자</option>
              </select>
            </div>
            <div><label class="input-label !text-xs !mb-0.5">상태</label>
              <div class="flex items-center gap-2 px-1 py-1">
                <span class="text-sm font-bold" :class="userData.user.is_banned ? 'text-red-600' : 'text-green-700'">{{ userData.user.is_banned ? '정지' : '정상' }}</span>
                <button type="button" @click="toggleBan" class="text-xs font-bold px-2 py-1 rounded-lg border" :class="userData.user.is_banned ? 'border-gray-300 text-gray-700' : 'border-red-300 text-red-600'">{{ userData.user.is_banned ? '정지 해제' : '정지' }}</button>
              </div>
            </div>
            <div><label class="input-label !text-xs !mb-0.5">포인트</label><input v-model.number="userData.user.points" type="number" class="input-soft px-3 py-1.5" /></div>
            <div><label class="input-label !text-xs !mb-0.5">게임포인트</label><input v-model.number="userData.user.game_points" type="number" class="input-soft px-3 py-1.5" /></div>
          </div>
          <div class="mt-2"><label class="input-label !text-xs !mb-0.5">소개</label><textarea v-model="userData.user.bio" rows="2" class="input-soft px-3 py-1.5"></textarea></div>
          <div class="mt-3 flex items-center gap-3 text-xs text-ink-faint">
            <span>가입일: {{ userData.user.created_at?.slice(0,10) }}</span>
            <span>최근 로그인: {{ userData.user.last_login_at?.slice(0,10) || '없음' }}</span>
            <span>로그인 {{ userData.user.login_count || 0 }}회</span>
          </div>
          <button @click="saveUser" class="btn-primary mt-4 px-5 py-2">저장하기</button>
        </div>

        <!-- 결제 내역 -->
        <div v-show="userTab==='payments'">
          <div v-if="!userData.payments?.length" class="py-6 text-center text-ink-muted text-sm">결제 내역 없음</div>
          <table v-else class="w-full text-sm"><thead class="bg-gray-50"><tr>
            <th class="px-2 py-1.5 text-xs text-left text-ink-muted">날짜</th><th class="px-2 py-1.5 text-xs text-left text-ink-muted">금액</th><th class="px-2 py-1.5 text-xs text-left text-ink-muted">포인트</th><th class="px-2 py-1.5 text-xs text-left text-ink-muted">상태</th>
          </tr></thead><tbody>
            <tr v-for="pay in userData.payments" :key="pay.id" class="border-b border-gray-50"><td class="px-2 py-1.5 text-xs">{{ pay.created_at?.slice(0,10) }}</td><td class="px-2 py-1.5 text-xs">${{ pay.amount }}</td><td class="px-2 py-1.5 text-xs text-amber-600 font-bold">+{{ pay.points }}P</td><td class="px-2 py-1.5 text-xs">{{ pay.status }}</td></tr>
          </tbody></table>
        </div>

        <!-- 포인트 내역 -->
        <div v-show="userTab==='points'">
          <div v-if="!userData.points?.length" class="py-6 text-center text-ink-muted text-sm">포인트 내역 없음</div>
          <table v-else class="w-full text-sm"><thead class="bg-gray-50"><tr>
            <th class="px-2 py-1.5 text-xs text-left text-ink-muted">날짜</th><th class="px-2 py-1.5 text-xs text-left text-ink-muted">사유</th><th class="px-2 py-1.5 text-xs text-right text-ink-muted">포인트</th>
          </tr></thead><tbody>
            <tr v-for="pt in userData.points" :key="pt.id" class="border-b border-gray-50"><td class="px-2 py-1.5 text-xs">{{ pt.created_at?.slice(0,10) }}</td><td class="px-2 py-1.5 text-xs">{{ pt.reason }}</td><td class="px-2 py-1.5 text-xs text-right font-bold" :class="pt.amount>0?'text-green-600':'text-red-600'">{{ pt.amount>0?'+':'' }}{{ pt.amount }}P</td></tr>
          </tbody></table>
        </div>

        <!-- 게시글 -->
        <div v-show="userTab==='posts'">
          <div v-if="!userData.posts?.length" class="py-6 text-center text-ink-muted text-sm">작성한 글 없음</div>
          <div v-for="post in userData.posts" :key="post.id" class="py-2 border-b border-gray-50 flex items-center justify-between">
            <div><div class="text-sm font-medium text-ink">{{ post.title }}</div><div class="text-[11px] text-ink-faint">{{ post.created_at?.slice(0,10) }} · {{ post.view_count }}회</div></div>
            <button @click="openPost(post); userModal=null" class="text-xs text-amber-600 hover:underline">보기</button>
          </div>
        </div>

        <!-- 댓글 -->
        <div v-show="userTab==='comments'">
          <div v-if="!userData.comments?.length" class="py-6 text-center text-ink-muted text-sm">작성한 댓글 없음</div>
          <div v-for="c in userData.comments" :key="c.id" class="py-2 border-b border-gray-50">
            <div class="text-sm text-ink-light">{{ c.content }}</div>
            <div class="text-[11px] text-ink-faint mt-0.5">{{ c.created_at?.slice(0,10) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, inject } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import SortTh from '../../components/SortTh.vue'
import { useAdminSort } from '../../composables/useAdminSort'
// 표 머리글을 눌러 정렬 (기본: 번호 내림차순 = 가장 마지막 번호가 위)
const { sortKey, sortDir, toggleSort, sortParams } = useAdminSort(() => load(1))

const posts = ref([]); const boards = ref([]); const loading = ref(true)
const page = ref(1); const lastPage = ref(1); const totalPosts = ref(0)
const search = ref(''); const boardFilter = ref('')
const activePost = ref(null)

// 관리자 휴대폰 화면이면 카드 목록 + 전체 화면 상세 + 확인 시트로 보여 줌 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const router = useRouter()
const toast = ref(null)
let toastTimer = null
function say(text, error = false) { toast.value = { text, error }; clearTimeout(toastTimer); toastTimer = setTimeout(() => { toast.value = null }, 3000) }
const busy = ref(false)
const sheet = ref(null)   // { kind: 'post', p } | { kind: 'comment', c }
function ask(s) { sheet.value = s }
function pickBoard(id) { boardFilter.value = id; load() }
function goUser(u) { if (u?.id) router.push({ path: '/admin/members', query: { user: String(u.id) } }) }
// 상세 화면: 뒤로가기 버튼으로 목록에 돌아오도록 history 한 칸 사용
let pushedDetail = false
function onPopState() { if (pushedDetail && !history.state?.contentDetail) { pushedDetail = false; activePost.value = null; sheet.value = null } }
function closePost() { if (pushedDetail) history.back(); else activePost.value = null }
window.addEventListener('popstate', onPopState)
async function doDelete() {
  if (busy.value || !sheet.value) return
  busy.value = true
  try {
    if (sheet.value.kind === 'post') {
      const p = sheet.value.p
      await axios.delete(`/api/admin/posts/${p.id}`)
      posts.value = posts.value.filter(x => x.id !== p.id); totalPosts.value = Math.max(0, totalPosts.value - 1)
      sheet.value = null; closePost(); say('삭제했어요')
    } else {
      const c = sheet.value.c
      await axios.delete(`/api/comments/${c.id}`)
      if (activePost.value?.comments) activePost.value.comments = activePost.value.comments.filter(x => x.id !== c.id)
      sheet.value = null; say('댓글을 삭제했어요')
    }
  } catch (e) { say(e.response?.data?.message || '삭제하지 못했어요', true) }
  finally { busy.value = false }
}
watch(() => isMobile.value && (!!activePost.value || !!sheet.value), locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = ''; clearTimeout(toastTimer); window.removeEventListener('popstate', onPopState) })

// 회원 모달
const userModal = ref(null)
const userData = ref(null)
const userLoading = ref(false)
const userTab = ref('info')
const userTabs = [
  { key: 'info', label: '기본정보' },
  { key: 'payments', label: '결제내역' },
  { key: 'points', label: '포인트' },
  { key: 'posts', label: '게시글' },
  { key: 'comments', label: '댓글' },
]

async function load(p=1) {
  loading.value=true; page.value=p
  const params = { page: p, ...sortParams.value }
  if (search.value) params.search = search.value
  if (boardFilter.value) params.board_id = boardFilter.value
  try {
    const { data } = await axios.get('/api/admin/posts', { params })
    posts.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
    totalPosts.value = data.data?.total || 0
  } catch {}
  loading.value = false
}

async function openPost(p) {
  try {
    const { data } = await axios.get(`/api/admin/posts/${p.id}/detail`)
    activePost.value = data.data
  } catch { activePost.value = p }
  if (isMobile.value && !pushedDetail) { history.pushState({ ...(history.state || {}), contentDetail: true }, ''); pushedDetail = true }
}

async function openUserModal(user) {
  if (!user?.id) return
  userModal.value = user; userLoading.value = true; userData.value = null; userTab.value = 'info'
  try {
    const { data } = await axios.get(`/api/admin/users/${user.id}/detail`)
    userData.value = data.data
  } catch {}
  userLoading.value = false
}

// 정지·해제는 전용 API 로만 (회원 수정 저장으로는 정지 칸이 바뀌지 않음) — 사유를 함께 남긴다
async function toggleBan() {
  const u = userData.value?.user
  if (!u) return
  try {
    if (u.is_banned) {
      if (!confirm(`${u.name}님의 정지를 해제할까요?`)) return
      await axios.post(`/api/admin/users/${u.id}/unban`)
      u.is_banned = false; u.ban_reason = null
    } else {
      const reason = prompt('정지 사유를 입력하세요', '관리자 정지')
      if (reason === null) return
      await axios.post(`/api/admin/users/${u.id}/ban`, { reason: reason.trim() || '관리자 정지' })
      u.is_banned = true; u.ban_reason = reason.trim() || '관리자 정지'
    }
  } catch (e) { alert(e.response?.data?.message || '처리하지 못했어요') }
}

async function saveUser() {
  if (!userData.value?.user) return
  try {
    await axios.put(`/api/admin/users/${userData.value.user.id}`, userData.value.user)
    alert('저장되었습니다!')
  } catch (e) { alert(e.response?.data?.message || '저장 실패') }
}

async function deleteComment(id) {
  if (!confirm('댓글 삭제?')) return
  try { await axios.delete(`/api/comments/${id}`); if (activePost.value?.comments) activePost.value.comments = activePost.value.comments.filter(c=>c.id!==id) } catch {}
}

function syncRow(p, k) { const r = posts.value.find(x => x.id === p.id); if (r && r !== p) r[k] = p[k] }
async function pinPost(p) { try { await axios.post(`/api/admin/posts/${p.id}/pin`); p.is_pinned=!p.is_pinned; syncRow(p, 'is_pinned') } catch (e) { if (isMobile.value) say(e.response?.data?.message || '처리하지 못했어요', true) } }
async function hidePost(p) { try { await axios.post(`/api/admin/posts/${p.id}/hide`); p.is_hidden=!p.is_hidden; syncRow(p, 'is_hidden') } catch (e) { if (isMobile.value) say(e.response?.data?.message || '처리하지 못했어요', true) } }
async function deletePost(p) { if(!confirm('삭제?'))return; try { await axios.delete(`/api/admin/posts/${p.id}`); posts.value=posts.value.filter(x=>x.id!==p.id) } catch {} }

onMounted(async () => {
  try { const { data } = await axios.get('/api/admin/boards'); boards.value = data.data || [] } catch {}
  load()
})
</script>

<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
