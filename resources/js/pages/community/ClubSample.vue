<template>
<div class="min-h-screen">
  <div class="page-main px-4 py-5 pb-28">
    <PageHeader title="샘플 동호회" icon="users" chip="bg-teal-50 text-teal-600" to="/clubs" />

    <!-- 샘플 안내 -->
    <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 mb-4 text-xs text-amber-800 leading-relaxed">
      <b>샘플 동호회</b>예요. 실제 동호회가 아니고, 동호회를 만들면 이런 화면이 생겨요. 아래 탭을 눌러 둘러보세요.
    </div>

    <!-- 동호회 헤더 -->
    <div class="card p-5 mb-4">
      <div class="flex items-center gap-4">
        <div class="icon-chip w-16 h-16 bg-teal-50 text-teal-600"><AppIcon name="users" :size="32" /></div>
        <div class="min-w-0">
          <h1 class="text-lg font-black text-ink truncate">한인 러닝 크루 (샘플)</h1>
          <div class="text-xs text-ink-muted mt-0.5">운동 · 지역 · 회원 24명</div>
          <div class="flex gap-1.5 mt-2">
            <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-600 font-semibold">지역</span>
            <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-ink-muted font-semibold">가입 승인제</span>
          </div>
        </div>
      </div>
      <p class="text-sm text-ink-light mt-4 leading-relaxed">매주 화/목 저녁 공원에서 같이 뛰어요. 초보부터 환영하고, 페이스는 각자 편한 속도로 해요.</p>
      <div class="mt-4 rounded-xl bg-gray-50 p-3 text-xs text-ink-muted leading-relaxed">
        <div class="font-bold text-ink mb-1">모임 규칙</div>
        1. 야간엔 안전 조끼를 입어요<br>2. 서로의 속도를 존중해요<br>3. 광고·홍보 글은 올리지 않아요
      </div>
    </div>

    <!-- 탭 -->
    <div class="flex gap-1.5 mb-3 overflow-x-auto scrollbar-hide">
      <button v-for="t in tabs" :key="t.key" @click="tab = t.key"
        class="px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap transition-colors"
        :class="tab === t.key ? 'bg-amber-400 text-white' : 'bg-white border border-line text-ink-muted hover:bg-amber-50'">{{ t.label }}</button>
    </div>

    <!-- 게시판 -->
    <div v-if="tab === 'board'" class="space-y-2.5">
      <div class="flex gap-1.5 flex-wrap text-[11px]">
        <span v-for="b in boards" :key="b" class="px-2.5 py-1 rounded-full bg-gray-100 text-ink-light font-semibold">{{ b }}</span>
      </div>
      <div v-for="p in posts" :key="p.title" class="card p-4">
        <div class="flex items-center gap-1.5 mb-1">
          <span v-if="p.notice" class="text-[11px] bg-red-500 text-white font-bold px-1.5 py-0.5 rounded">공지</span>
          <span class="text-sm font-bold text-ink">{{ p.title }}</span>
        </div>
        <div class="text-xs text-ink-muted leading-relaxed">{{ p.body }}</div>
        <div class="flex items-center gap-3 text-[11px] text-ink-faint mt-2">
          <span>{{ p.by }}</span><span>{{ p.when }}</span>
          <span class="inline-flex items-center gap-0.5"><AppIcon name="message-circle" :size="11" />{{ p.comments }}</span>
        </div>
      </div>
    </div>

    <!-- 채팅 -->
    <div v-else-if="tab === 'chat'" class="card p-4">
      <div class="text-xs font-bold text-ink mb-3 flex items-center gap-1.5"><AppIcon name="message-circle" :size="14" class="text-violet-500" />단체 채팅방</div>
      <div class="space-y-2.5 bg-gray-50/70 rounded-xl p-3">
        <div v-for="m in chat" :key="m.text" class="flex" :class="m.me ? 'justify-end' : 'justify-start'">
          <div class="max-w-[80%]">
            <div v-if="!m.me" class="text-[11px] text-ink-muted mb-0.5">{{ m.by }}</div>
            <div class="px-3 py-2 rounded-2xl text-sm" :class="m.me ? 'bg-rose-500 text-white' : 'bg-white border border-line text-ink'">{{ m.text }}</div>
          </div>
        </div>
      </div>
      <p class="text-xs text-ink-muted mt-3">모임장이나 운영진이 채팅방을 만들면, 회원들이 실시간으로 대화할 수 있어요.</p>
    </div>

    <!-- 멤버 -->
    <div v-else-if="tab === 'members'" class="card overflow-hidden divide-y divide-gray-50">
      <div v-for="m in members" :key="m.name" class="flex items-center gap-3 px-4 py-3">
        <div class="w-9 h-9 rounded-full bg-teal-100 text-teal-700 flex items-center justify-center font-bold text-sm">{{ m.name[0] }}</div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-semibold text-ink">{{ m.name }}</div>
          <div class="text-[11px] text-ink-faint">가입 {{ m.since }}</div>
        </div>
        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full" :class="m.grade === '모임장' ? 'bg-amber-100 text-amber-700' : m.grade === '운영진' ? 'bg-blue-50 text-blue-600' : 'bg-gray-100 text-ink-muted'">{{ m.grade }}</span>
      </div>
    </div>

    <!-- 운영 -->
    <div v-else class="space-y-2.5">
      <div v-for="a in adminTools" :key="a.title" class="card p-4 flex items-start gap-3">
        <span class="icon-chip w-9 h-9 flex-shrink-0" :class="a.chip"><AppIcon :name="a.icon" :size="18" /></span>
        <div>
          <div class="text-sm font-bold text-ink">{{ a.title }}</div>
          <div class="text-xs text-ink-muted mt-0.5 leading-relaxed">{{ a.desc }}</div>
        </div>
      </div>
      <p class="text-[11px] text-ink-faint px-1">운영 메뉴는 모임장과 운영진에게만 보여요.</p>
    </div>
  </div>

  <!-- 하단 고정 안내 -->
  <div class="fixed left-0 right-0 bottom-0 z-40 bg-white/95 backdrop-blur border-t border-line">
    <div class="page-main px-4 py-3 flex items-center justify-between gap-3">
      <div class="text-xs text-ink-muted leading-snug">마음에 드세요?<br><b class="text-ink">우리 동네 동호회를 직접 만들어 보세요.</b></div>
      <RouterLink to="/clubs/create" class="btn-primary flex-shrink-0"><AppIcon name="plus" :size="15" />동호회 만들기</RouterLink>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref } from 'vue'
import AppIcon from '../../components/AppIcon.vue'
import PageHeader from '../../components/PageHeader.vue'

const tab = ref('board')
const tabs = [
  { key: 'board', label: '게시판' },
  { key: 'chat', label: '채팅방' },
  { key: 'members', label: '멤버' },
  { key: 'admin', label: '운영(모임장)' },
]

const boards = ['전체', '공지사항', '자유 게시판', '모임 후기', '사진 갤러리']
const posts = [
  { notice: true, title: '이번 주 목요일 러닝 코스 안내', body: '저녁 6시 공원 정문에서 모여요. 5K 코스로 뛰고 끝나고 간단히 식사해요.', by: '모임장 지민', when: '오늘', comments: 6 },
  { title: '지난 주 러닝 후기와 사진', body: '날씨가 좋아서 다들 기록이 좋았어요! 사진 갤러리에도 올렸어요.', by: '현우', when: '어제', comments: 4 },
  { title: '러닝화 추천 부탁드려요', body: '발볼이 넓은 편인데 괜찮은 러닝화 있을까요?', by: '수진', when: '2일 전', comments: 9 },
]
const chat = [
  { by: '지민', text: '내일 6시 정문에서 봬요!' },
  { by: '현우', text: '저는 조금 늦을 것 같아요. 먼저 출발하셔도 돼요' },
  { me: true, text: '넵, 5K 천천히 뛰고 있을게요 🙂' },
]
const members = [
  { name: '지민', grade: '모임장', since: '6개월 전' },
  { name: '현우', grade: '운영진', since: '5개월 전' },
  { name: '수진', grade: '회원', since: '2개월 전' },
  { name: '태호', grade: '회원', since: '3주 전' },
]
const adminTools = [
  { icon: 'user-plus', title: '가입 신청 승인/거절', desc: '가입 신청이 오면 알림을 받고, 한 번에 승인하거나 거절해요.', chip: 'bg-emerald-50 text-emerald-600' },
  { icon: 'list', title: '게시판 만들기', desc: '공지, 후기, 사진처럼 필요한 게시판을 추가하고 정리해요.', chip: 'bg-blue-50 text-blue-600' },
  { icon: 'users', title: '멤버 등급 관리', desc: '믿을 수 있는 회원을 운영진으로 올리고, 필요하면 내보낼 수 있어요.', chip: 'bg-violet-50 text-violet-600' },
  { icon: 'message-circle', title: '단체 채팅방 열기', desc: '버튼 한 번으로 회원 전용 채팅방을 만들어요.', chip: 'bg-rose-50 text-rose-600' },
  { icon: 'megaphone', title: '상위 노출(홍보)', desc: '더 많은 이웃에게 우리 동호회를 알려요.', chip: 'bg-amber-50 text-amber-600' },
]
</script>
