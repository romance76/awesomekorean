<template>
<div class="ev" :data-size="size" :class="{ hc }">
  <!-- 조절 막대 -->
  <div class="ev-bar">
    <div class="ev-bar-title">큰 글씨로 보기 · 샘플 <span class="ev-tag">테스트 페이지</span></div>
    <div class="ev-seg" role="group" aria-label="글씨 크기">
      <button v-for="o in sizes" :key="o.v" @click="size = o.v" :class="{ on: size === o.v }" :aria-pressed="size === o.v">{{ o.l }}</button>
    </div>
    <label class="ev-check"><input type="checkbox" v-model="hc" /> 글씨 진하게·선명하게</label>
    <div class="ev-note">아래 두 화면(글 목록 · 글 읽기)이 크기 변수 하나로 같이 커지고 작아집니다. 실제 사이트도 이 방식으로 바꿉니다.</div>
  </div>

  <div class="ev-grid">
    <!-- 샘플 1: 글 목록 -->
    <section class="ev-phone">
      <header class="ev-head"><span class="ev-h-title">자유게시판</span></header>
      <div class="ev-tabs">
        <span class="on">전체</span><span>정보</span><span>질문</span><span>잡담</span>
      </div>
      <ul class="ev-list">
        <li v-for="p in posts" :key="p.id">
          <div class="ev-p-title">{{ p.title }} <span class="ev-cmt">[{{ p.c }}]</span></div>
          <div class="ev-p-meta">{{ p.who }} · {{ p.when }} · 조회 {{ p.v }}</div>
        </li>
      </ul>
      <nav class="ev-nav">
        <a v-for="n in navs" :key="n.l" :class="{ on: n.on }"><AppIcon :name="n.i" :size="22" /><span>{{ n.l }}</span></a>
      </nav>
    </section>

    <!-- 샘플 2: 글 읽기 -->
    <section class="ev-phone">
      <header class="ev-head"><span class="ev-back">‹ 뒤로</span><span class="ev-h-title">글 보기</span></header>
      <article class="ev-detail">
        <h2 class="ev-d-title">애틀랜타 한인 마트, 이번 주 세일 정리해요</h2>
        <div class="ev-d-meta">부산아줌마97 · 10월 10일 오전 10:28 · 조회 128</div>
        <p class="ev-d-body">이번 주 둘루스 한인 마트에서 채소와 고기류를 많이 할인합니다. 배추 한 포기 2달러, 삼겹살 파운드당 5달러 99센트예요. 토요일 오전이 가장 한산하니 참고하세요.</p>
        <div class="ev-actions">
          <button class="ev-btn ghost">♡ 좋아요 12</button>
          <button class="ev-btn ghost">공유</button>
          <button class="ev-btn ghost">신고</button>
        </div>
        <div class="ev-cmt-box">
          <div class="ev-c-head">댓글 3개</div>
          <div class="ev-c"><b>이민5년차15</b><span>저도 토요일에 다녀왔어요. 사람 정말 없네요!</span></div>
          <div class="ev-c"><b>시애틀댁</b><span>정보 감사합니다. 다음 주에도 올려주세요.</span></div>
        </div>
        <div class="ev-write"><input class="ev-input" placeholder="댓글을 입력해 주세요" /><button class="ev-btn">등록</button></div>
      </article>
    </section>

    <!-- 샘플 3: 채팅방 목록 -->
    <section class="ev-phone">
      <header class="ev-head"><span class="ev-h-title">채팅</span></header>
      <ul class="ev-list">
        <li v-for="r in rooms" :key="r.n" class="ev-room">
          <div class="ev-av">{{ r.n[0] }}</div>
          <div class="ev-room-body">
            <div class="ev-room-top"><span class="ev-p-title">{{ r.n }}</span><span class="ev-p-meta ev-when">{{ r.t }}</span></div>
            <div class="ev-room-last">{{ r.last }}</div>
          </div>
          <span v-if="r.u" class="ev-badge">{{ r.u }}</span>
        </li>
      </ul>
    </section>

    <!-- 샘플 4: 채팅방 안 (글쓰기) -->
    <section class="ev-phone ev-chat">
      <header class="ev-head"><span class="ev-back">‹</span><span class="ev-h-title">애틀랜타 한인 모임</span></header>
      <div class="ev-msgs">
        <div class="ev-m"><div class="ev-m-name">이민5년차15</div><div class="ev-bubble">좋은 한인 교회 추천 부탁드려요</div><div class="ev-m-time">오전 9:41</div></div>
        <div class="ev-m"><div class="ev-m-name">부산아줌마97</div><div class="ev-bubble">방금 이사 왔는데 한인 마트 어디 있나요?</div><div class="ev-m-time">오전 10:28</div></div>
        <div class="ev-m me"><div class="ev-bubble mine">둘루스에 큰 마트 두 곳 있어요. 주차도 편해요.</div><div class="ev-m-time">오전 10:30</div></div>
      </div>
      <div class="ev-compose">
        <div class="ev-compose-box">
          <div class="ev-compose-ph">메시지 입력...</div>
          <div class="ev-compose-row">
            <span class="ev-ic"><AppIcon name="smile" :size="24" /></span>
            <span class="ev-ic"><AppIcon name="paperclip" :size="24" /></span>
            <span class="ev-send"><AppIcon name="send" :size="22" /></span>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>
</template>

<script setup>
import { ref } from 'vue'
import AppIcon from '../components/AppIcon.vue'

const size = ref('md')
const hc = ref(false)
const sizes = [{ v: 'md', l: '보통' }, { v: 'lg', l: '크게' }, { v: 'xl', l: '아주 크게' }]
const posts = [
  { id: 1, title: '애틀랜타 한인 마트, 이번 주 세일 정리해요', c: 3, who: '부산아줌마97', when: '10:28', v: 128 },
  { id: 2, title: '좋은 한인 교회 추천 부탁드려요', c: 12, who: '이민5년차15', when: '09:41', v: 342 },
  { id: 3, title: '둘루스 근처 소아과 괜찮은 곳 아시나요?', c: 7, who: '두아이맘', when: '어제', v: 210 },
  { id: 4, title: '운전면허 갱신 온라인으로 하는 방법', c: 5, who: '조지아사랑', when: '어제', v: 489 },
]
const rooms = [
  { n: '애틀랜타 한인 모임', t: '10:30', last: '둘루스에 큰 마트 두 곳 있어요. 주차도 편해요.', u: 3 },
  { n: '조지아 맛집 탐방', t: '09:12', last: '이번 주말에 삼겹살집 가실 분 계신가요?', u: 12 },
  { n: '신앙생활 나눔방', t: '어제', last: '주일 예배 후에 점심 함께해요', u: 0 },
  { n: '초보 이민자 도움방', t: '어제', last: '운전면허 필기시험 한국어도 되나요?', u: 5 },
]
const navs = [
  { l: '홈', i: 'home', on: true }, { l: '커뮤니티', i: 'message-circle' }, { l: '장터', i: 'shopping-bag' },
  { l: '채팅', i: 'message-square' }, { l: '내정보', i: 'user' },
]
</script>

<style scoped>
/* ── 크기 변수: 이 값 하나로 글씨·버튼·간격이 같이 변합니다 ── */
.ev {
  --s: 1;                         /* 크기 배율 */
  --fs-meta: calc(12px * var(--s));
  --fs-body: calc(15px * var(--s));
  --fs-title: calc(17px * var(--s));
  --fs-head: calc(20px * var(--s));
  --btn-h: calc(40px * var(--s)); /* 버튼 최소 높이 */
  --gap: calc(12px * var(--s));
  --lh: 1.5;
  --c-ink: #374151; --c-sub: #9ca3af; --c-line: #eef0f3; --c-bg: #f6f7f9;
  --brand: #FC226B;
  background: var(--c-bg); min-height: 100vh; padding: 12px 12px 40px; color: var(--c-ink);
  font-family: inherit; box-sizing: border-box;
}
.ev[data-size='lg'] { --s: 1.25; --lh: 1.6; }
.ev[data-size='xl'] { --s: 1.5; --lh: 1.65; }
/* 선명하게: 연한 회색을 진하게 */
.ev.hc { --c-ink: #111827; --c-sub: #4b5563; --c-line: #cfd4db; }

.ev-bar { background: #fff; border-radius: 16px; padding: 14px; margin-bottom: 14px; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
.ev-bar-title { font-weight: 800; font-size: 16px; color: #111; margin-bottom: 10px; }
.ev-tag { font-size: 11px; font-weight: 700; background: #fff1f5; color: var(--brand); padding: 2px 8px; border-radius: 99px; margin-left: 6px; }
.ev-seg { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; background: #eceef1; padding: 4px; border-radius: 14px; }
.ev-seg button { min-height: 48px; border-radius: 10px; font-size: 16px; font-weight: 700; color: #4b5563; background: transparent; }
.ev-seg button.on { background: #fff; color: var(--brand); box-shadow: 0 1px 3px rgba(0,0,0,.12); }
.ev-check { display: flex; align-items: center; gap: 8px; margin-top: 12px; font-size: 16px; font-weight: 600; color: #111; min-height: 44px; }
.ev-check input { width: 22px; height: 22px; accent-color: var(--brand); }
.ev-note { margin-top: 6px; font-size: 13px; color: #6b7280; line-height: 1.5; }

.ev-grid { display: grid; grid-template-columns: 1fr; gap: 16px; }
@media (min-width: 900px) { .ev-grid { grid-template-columns: repeat(2, 420px); justify-content: center; align-items: start; } }

.ev-phone { background: #fff; border-radius: 22px; overflow: hidden; box-shadow: 0 6px 24px rgba(0,0,0,.10); border: 1px solid var(--c-line); max-width: 460px; width: 100%; margin: 0 auto; }
.ev-head { display: flex; align-items: center; gap: var(--gap); padding: var(--gap) calc(var(--gap) * 1.3); border-bottom: 1px solid var(--c-line); min-height: calc(52px * var(--s)); }
.ev-h-title { font-size: var(--fs-head); font-weight: 800; color: var(--c-ink); }
.ev-back { font-size: var(--fs-body); font-weight: 700; color: var(--brand); }
.ev-tabs { display: flex; gap: calc(8px * var(--s)); padding: var(--gap) calc(var(--gap) * 1.3) 0; overflow: hidden; }
.ev-tabs span { padding: calc(6px * var(--s)) calc(14px * var(--s)); border-radius: 99px; font-size: var(--fs-body); font-weight: 700; background: var(--c-bg); color: var(--c-sub); white-space: nowrap; }
.ev-tabs span.on { background: var(--brand); color: #fff; }
.ev-list { list-style: none; margin: 0; padding: 0; }
.ev-list li { padding: calc(14px * var(--s)) calc(var(--gap) * 1.3); border-bottom: 1px solid var(--c-line); }
.ev-p-title { font-size: var(--fs-title); font-weight: 700; color: var(--c-ink); line-height: var(--lh); }
.ev-cmt { color: var(--brand); font-weight: 800; font-size: var(--fs-body); }
.ev-p-meta { margin-top: calc(4px * var(--s)); font-size: var(--fs-meta); color: var(--c-sub); }

.ev-nav { display: grid; grid-template-columns: repeat(5, 1fr); border-top: 1px solid var(--c-line); background: #fff; }
.ev-nav a { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: calc(2px * var(--s)); min-height: calc(60px * var(--s)); color: var(--c-sub); font-size: calc(10.5px * var(--s)); font-weight: 700; }
.ev-nav a.on { color: var(--brand); }

.ev-detail { padding: calc(var(--gap) * 1.3); }
.ev-d-title { font-size: calc(22px * var(--s)); font-weight: 800; line-height: 1.35; color: var(--c-ink); margin: 0; }
.ev-d-meta { margin-top: calc(8px * var(--s)); font-size: var(--fs-meta); color: var(--c-sub); }
.ev-d-body { margin: calc(16px * var(--s)) 0; font-size: var(--fs-body); line-height: var(--lh); color: var(--c-ink); }
.ev-actions { display: flex; gap: calc(8px * var(--s)); flex-wrap: wrap; }
.ev-btn { min-height: var(--btn-h); padding: 0 calc(16px * var(--s)); border-radius: 99px; background: var(--brand); color: #fff; font-size: var(--fs-body); font-weight: 800; }
.ev-btn.ghost { background: var(--c-bg); color: var(--c-ink); border: 1px solid var(--c-line); }
.ev-cmt-box { margin-top: calc(18px * var(--s)); border-top: 1px solid var(--c-line); padding-top: var(--gap); }
.ev-c-head { font-size: var(--fs-title); font-weight: 800; color: var(--c-ink); margin-bottom: calc(8px * var(--s)); }
.ev-c { display: flex; flex-direction: column; gap: 2px; padding: calc(8px * var(--s)) 0; border-bottom: 1px solid var(--c-line); }
.ev-c b { font-size: var(--fs-body); color: var(--c-ink); }
.ev-c span { font-size: var(--fs-body); line-height: var(--lh); color: var(--c-ink); }
.ev-write { display: flex; gap: calc(8px * var(--s)); margin-top: var(--gap); }
.ev-input { flex: 1; min-width: 0; min-height: var(--btn-h); padding: 0 calc(14px * var(--s)); border-radius: 99px; border: 1.5px solid var(--c-line); background: var(--c-bg); font-size: var(--fs-body); color: var(--c-ink); }

.ev-room { display: flex; align-items: center; gap: calc(12px * var(--s)); }
.ev-av { flex: none; width: calc(48px * var(--s)); height: calc(48px * var(--s)); border-radius: 50%; background: #fff1f5; color: var(--brand); display: grid; place-items: center; font-weight: 800; font-size: var(--fs-title); }
.ev-room-body { flex: 1; min-width: 0; }
.ev-room-top { display: flex; justify-content: space-between; gap: 8px; align-items: baseline; }
.ev-when { margin: 0; flex: none; }
.ev-room-last { font-size: var(--fs-body); color: var(--c-sub); line-height: var(--lh); margin-top: calc(2px * var(--s)); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.ev-badge { flex: none; min-width: calc(24px * var(--s)); height: calc(24px * var(--s)); padding: 0 calc(7px * var(--s)); border-radius: 99px; background: var(--brand); color: #fff; font-size: var(--fs-meta); font-weight: 800; display: grid; place-items: center; }
.ev-chat { display: flex; flex-direction: column; }
.ev-msgs { padding: calc(14px * var(--s)); background: var(--c-bg); display: flex; flex-direction: column; gap: calc(14px * var(--s)); }
.ev-m { display: flex; flex-direction: column; align-items: flex-start; gap: calc(3px * var(--s)); }
.ev-m.me { align-items: flex-end; }
.ev-m-name { font-size: var(--fs-meta); color: var(--c-sub); font-weight: 700; }
.ev-bubble { max-width: 85%; background: #fff; border-radius: calc(16px * var(--s)); padding: calc(10px * var(--s)) calc(14px * var(--s)); font-size: var(--fs-body); line-height: var(--lh); color: var(--c-ink); box-shadow: 0 1px 2px rgba(0,0,0,.06); }
.ev-bubble.mine { background: var(--brand); color: #fff; }
.ev-m-time { font-size: calc(11px * var(--s)); color: var(--c-sub); }
.ev-compose { padding: calc(10px * var(--s)); background: #fff; border-top: 1px solid var(--c-line); }
.ev-compose-box { border: 1.5px solid #f4a0bc; border-radius: calc(22px * var(--s)); padding: calc(12px * var(--s)) calc(14px * var(--s)); }
.ev-compose-ph { font-size: var(--fs-body); color: var(--c-sub); min-height: calc(28px * var(--s)); }
.ev-compose-row { display: flex; align-items: center; gap: calc(10px * var(--s)); margin-top: calc(6px * var(--s)); }
.ev-ic { width: calc(44px * var(--s)); height: calc(44px * var(--s)); display: grid; place-items: center; color: var(--c-sub); }
.ev-send { margin-left: auto; width: calc(52px * var(--s)); height: calc(52px * var(--s)); border-radius: 50%; background: var(--brand); color: #fff; display: grid; place-items: center; }
</style>
