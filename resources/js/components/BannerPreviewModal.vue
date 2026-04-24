<template>
<div class="bp-overlay" @click.self="$emit('close')">
  <div class="bp-modal">
    <!-- 헤더 -->
    <div class="bp-head">
      <div>
        <h2 class="bp-title">🔍 샘플 배너 위치 확인</h2>
        <p class="bp-sub">페이지 유형별 · 디바이스별 배너 위치·크기·가격 레이아웃</p>
      </div>
      <button @click="$emit('close')" class="bp-close">✕</button>
    </div>

    <!-- 페이지 유형 선택 -->
    <div class="bp-page-type">
      <span class="pt-label">페이지 유형:</span>
      <button @click="pageType = 'main'" :class="['pt-btn', pageType==='main' && 'active']">🏠 메인 (홈)</button>
      <button @click="pageType = 'list'" :class="['pt-btn', pageType==='list' && 'active']">📋 리스트 (커뮤니티/장터/구인/부동산 등)</button>
      <button @click="pageType = 'detail'" :class="['pt-btn', pageType==='detail' && 'active']">📄 상세 (글·물품·매물·업소)</button>
    </div>

    <!-- 디바이스 탭 -->
    <div class="bp-tabs">
      <button @click="view = 'desktop'" :class="['bp-tab', view==='desktop' && 'active']">🖥️ 데스크톱</button>
      <button @click="view = 'mobile'" :class="['bp-tab', view==='mobile' && 'active']">📱 모바일</button>
    </div>

    <div class="bp-body">
      <!-- ════════════════════════════════════════════════════════════ -->
      <!-- 🏠 메인 (홈) — 데스크톱 -->
      <!-- ════════════════════════════════════════════════════════════ -->
      <div v-if="pageType === 'main' && view === 'desktop'" class="desktop-mock">
        <div class="mock-nav">🌐 NavBar · 검색 · 로그인</div>
        <div class="mock-hero">
          <div>히어로 슬라이드 (280×자동)<br/><span class="hero-meta">메인 브랜딩 / 이벤트 / 공지</span></div>
        </div>
        <div class="mock-3col">
          <div class="mock-side">
            <div class="side-label">좌측 사이드바</div>
            <div class="side-filler">📋 카테고리</div>
            <div class="side-filler">🔥 인기 게시판</div>
            <div class="side-filler">🏷️ 트렌딩 태그</div>
            <div class="side-filler">👥 실시간 활동</div>
            <div class="ad-stack">
              <div class="ad-stack-label">📢 좌측 광고 (위젯 아래, 나란히)</div>
              <div class="slot slot-premium" @click="selectSlot('left-1')" :class="{highlight: highlighted==='left-1'}">
                <div class="slot-tag">🥇 프리미엄 A · 200×150</div>
                <div class="slot-size">고정 독점 · 8,000P</div>
              </div>
              <div class="slot slot-standard" @click="selectSlot('left-2')" :class="{highlight: highlighted==='left-2'}">
                <div class="slot-tag">🥈 스탠다드 A · 200×150</div>
                <div class="slot-size">고정 독점 · 5,000P</div>
              </div>
            </div>
          </div>
          <div class="mock-main">
            <div class="main-label">중앙 콘텐츠</div>
            <div class="main-block">공지 · 2×2 섹션 박스</div>
            <div class="main-block">최신글 · 구인구직 · 장터 · 이벤트</div>
            <div class="main-block">최신 부동산 갤러리</div>
          </div>
          <div class="mock-side">
            <div class="side-label">우측 사이드바</div>
            <div class="side-filler">☀️ 날씨</div>
            <div class="side-filler">💱 환율</div>
            <div class="side-filler">⭐ 즐겨찾기</div>
            <div class="side-filler">🎁 가입 CTA</div>
            <div class="ad-stack">
              <div class="ad-stack-label">📢 우측 광고 (위젯 아래, 나란히)</div>
              <div class="slot slot-premium" @click="selectSlot('right-1')" :class="{highlight: highlighted==='right-1'}">
                <div class="slot-tag">🥇 프리미엄 B · 300×250</div>
                <div class="slot-size">고정 독점 · 10,000P</div>
              </div>
              <div class="slot slot-standard" @click="selectSlot('right-2')" :class="{highlight: highlighted==='right-2'}">
                <div class="slot-tag">🥈 스탠다드 B · 300×250</div>
                <div class="slot-size">고정 독점 · 7,000P</div>
              </div>
            </div>
          </div>
        </div>
        <div class="mock-footer">BottomNav · Footer</div>
      </div>

      <!-- 🏠 메인 (홈) — 모바일 -->
      <div v-else-if="pageType === 'main' && view === 'mobile'" class="mobile-wrap">
        <div class="mobile-mock">
          <div class="mock-nav">🌐 NavBar</div>
          <div class="mock-hero mock-hero-sm"><div>히어로 슬라이드 (180px)</div></div>
          <div class="slot slot-random mobile-slot" @click="selectSlot('m1-1')" :class="{highlight: highlighted==='m1-1'}">
            <div class="slot-tag">🎲 히어로 직하 · 4광고 랜덤</div>
            <div class="slot-size">프A 35% · 프B 35% · 스A 15% · 스B 15%</div>
          </div>
          <div class="cat-grid">
            <span v-for="c in 9" :key="c" class="cat-cell"></span>
          </div>
          <div class="main-block">공지 · 최신글 · 구인 · 장터</div>
          <div class="main-block">최신 부동산 카드</div>
          <div class="slot slot-text mobile-slot" @click="selectSlot('m1-2')" :class="{highlight: highlighted==='m1-2'}">
            <div class="slot-tag">📝 텍스트 인라인 · 콘텐츠 사이</div>
            <div class="slot-size">1,000P/월 · 상호+☎+설명</div>
          </div>
          <div class="main-block">날씨 / 환율 / 즐겨찾기</div>
          <div class="mock-footer">BottomNav</div>
        </div>
        <div class="info-side">
          <div class="info-card">
            <div class="info-head">🏠 메인 홈 배너</div>
            <ul>
              <li>모바일 <strong>2개 위치</strong>: 히어로 직하 + 중간</li>
              <li>위 = 프리미엄 (<code>slot_number=1</code>)</li>
              <li>중간 = 가중 랜덤 (<code>slot 2/3</code>)</li>
              <li>이미지: <code>720×180</code> (2x) → <code>320×80</code></li>
            </ul>
          </div>
        </div>
      </div>

      <!-- ════════════════════════════════════════════════════════════ -->
      <!-- 📋 리스트 (커뮤니티/장터/구인/부동산/업소록/동호회/이벤트/뉴스) — 데스크톱 -->
      <!-- ════════════════════════════════════════════════════════════ -->
      <div v-else-if="pageType === 'list' && view === 'desktop'" class="desktop-mock">
        <div class="mock-nav">🌐 NavBar · 검색 · 로그인</div>
        <div class="list-header">📋 페이지 제목 · 필터 바 (카테고리 / 지역 / 정렬)</div>
        <div class="mock-3col mock-3col-narrow">
          <div class="mock-side">
            <div class="side-label">좌측: 필터/카테고리</div>
            <div class="side-filler">📋 전체 · 카테고리 A/B/C</div>
            <div class="side-filler">📍 위치 · 가격 범위</div>
            <div class="side-filler">🔖 내 북마크</div>
            <div class="ad-stack">
              <div class="ad-stack-label">📢 좌측 광고 (카테고리 아래, 나란히)</div>
              <div class="slot slot-premium" @click="selectSlot('list-left-1')" :class="{highlight: highlighted==='list-left-1'}">
                <div class="slot-tag">🥇 프리미엄 A · 200×150</div>
                <div class="slot-size">고정 독점 · 8,000P</div>
              </div>
              <div class="slot slot-standard" @click="selectSlot('list-left-2')" :class="{highlight: highlighted==='list-left-2'}">
                <div class="slot-tag">🥈 스탠다드 A · 200×150</div>
                <div class="slot-size">고정 독점 · 5,000P</div>
              </div>
            </div>
          </div>
          <div class="mock-main">
            <div class="main-label">아이템 리스트</div>
            <div class="list-row">📝 아이템 1</div>
            <div class="list-row">📝 아이템 2</div>
            <div class="list-row">📝 아이템 3</div>
            <div class="list-row">📝 아이템 4</div>
            <div class="slot slot-text inline-ad" @click="selectSlot('list-inline')" :class="{highlight: highlighted==='list-inline'}">
              <div class="slot-tag">📝 텍스트 인라인 광고 · 리스트 중간</div>
              <div class="slot-size">상호 + ☎ + 한 줄 · 마퀴 애니 · <strong>1,000P/월~</strong></div>
            </div>
            <div class="list-row">📝 아이템 5</div>
            <div class="list-row">📝 아이템 6</div>
            <div class="list-row">📝 아이템 7 · · ·</div>
            <div class="side-filler">페이지네이션</div>
          </div>
          <div class="mock-side">
            <div class="side-label">우측: 추천/인기</div>
            <div class="side-filler">🔥 인기글 TOP 10</div>
            <div class="side-filler">📂 같은 카테고리</div>
            <div class="side-filler">⏱️ 최근 활동</div>
            <div class="ad-stack">
              <div class="ad-stack-label">📢 우측 광고 (위젯 아래, 나란히)</div>
              <div class="slot slot-premium" @click="selectSlot('list-right-1')" :class="{highlight: highlighted==='list-right-1'}">
                <div class="slot-tag">🥇 프리미엄 B · 300×250</div>
                <div class="slot-size">고정 독점 · 10,000P</div>
              </div>
              <div class="slot slot-standard" @click="selectSlot('list-right-2')" :class="{highlight: highlighted==='list-right-2'}">
                <div class="slot-tag">🥈 스탠다드 B · 300×250</div>
                <div class="slot-size">고정 독점 · 7,000P</div>
              </div>
            </div>
          </div>
        </div>
        <div class="mock-footer">Footer</div>
      </div>

      <!-- 📋 리스트 — 모바일 -->
      <div v-else-if="pageType === 'list' && view === 'mobile'" class="mobile-wrap">
        <div class="mobile-mock">
          <div class="mock-nav">🌐 NavBar</div>
          <div class="list-header sm">📋 제목 · 필터 칩 행</div>
          <div class="list-row">📝 아이템 1</div>
          <div class="list-row">📝 아이템 2</div>
          <div class="list-row">📝 아이템 3</div>
          <div class="list-row">📝 아이템 4</div>
          <div class="slot slot-random mobile-slot" @click="selectSlot('m2-1')" :class="{highlight: highlighted==='m2-1'}">
            <div class="slot-tag">🎲 5번째 ↔ 6번째 사이 · 4광고 랜덤</div>
            <div class="slot-size">프A 35% · 프B 35% · 스A 15% · 스B 15%</div>
          </div>
          <div class="list-row">📝 아이템 5</div>
          <div class="list-row">📝 아이템 6</div>
          <div class="list-row">📝 아이템 7</div>
          <div class="list-row">📝 아이템 8</div>
          <div class="list-row">📝 아이템 9</div>
          <div class="slot slot-text mobile-slot" @click="selectSlot('m2-2')" :class="{highlight: highlighted==='m2-2'}">
            <div class="slot-tag">📝 텍스트 인라인 · 페이지네이션 위</div>
            <div class="slot-size">1,000P/월 · 상호+☎+설명</div>
          </div>
          <div class="list-row">📝 · · ·</div>
          <div class="side-filler">페이지네이션</div>
          <div class="mock-footer">BottomNav</div>
        </div>
        <div class="info-side">
          <div class="info-card">
            <div class="info-head">📋 리스트 페이지 배너</div>
            <ul>
              <li>PC: 좌우 사이드바 슬롯 + <strong>리스트 중간 인라인</strong></li>
              <li>모바일: <strong>리스트 스크롤 중 인라인 2곳</strong> (5번째·10번째 뒤)</li>
              <li>대상: 커뮤니티·장터·구인·부동산·업소록·동호회·이벤트·뉴스</li>
              <li>PC 인라인: <code>728×90</code> · 모바일: <code>320×80</code></li>
            </ul>
          </div>
        </div>
      </div>

      <!-- ════════════════════════════════════════════════════════════ -->
      <!-- 📄 상세 (게시글·물품·매물·업소) — 데스크톱 (3-column) -->
      <!-- ════════════════════════════════════════════════════════════ -->
      <div v-else-if="pageType === 'detail' && view === 'desktop'" class="desktop-mock">
        <div class="mock-nav">🌐 NavBar · 검색 · 로그인</div>
        <div class="mock-3col mock-3col-narrow">
          <!-- 좌측: 카테고리 (리스트 페이지와 동일 패턴) -->
          <div class="mock-side">
            <div class="side-label">좌측: 카테고리/필터</div>
            <div class="side-filler">📋 전체 · 카테고리 A/B/C</div>
            <div class="side-filler">📍 내 위치 / 범위</div>
            <div class="side-filler">🔖 내 북마크</div>
            <div class="ad-stack">
              <div class="ad-stack-label">📢 좌측 광고 (카테고리 아래, 나란히)</div>
              <div class="slot slot-premium" @click="selectSlot('detail-left-1')" :class="{highlight: highlighted==='detail-left-1'}">
                <div class="slot-tag">🥇 프리미엄 A · 200×150</div>
                <div class="slot-size">고정 독점 · 8,000P</div>
              </div>
              <div class="slot slot-standard" @click="selectSlot('detail-left-2')" :class="{highlight: highlighted==='detail-left-2'}">
                <div class="slot-tag">🥈 스탠다드 A · 200×150</div>
                <div class="slot-size">고정 독점 · 5,000P</div>
              </div>
            </div>
          </div>
          <!-- 중앙: 본문 -->
          <div class="mock-main">
            <div class="main-label">본문 영역</div>
            <div class="main-block" style="padding:22px">🖼️ 이미지 갤러리 / 썸네일</div>
            <div class="main-block">제목 · 가격/급여 · 카테고리 · 위치 · 작성자</div>
            <div class="main-block" style="padding:32px">본문 내용 / 상세 설명</div>
            <div class="slot slot-text inline-ad" @click="selectSlot('detail-inline')" :class="{highlight: highlighted==='detail-inline'}">
              <div class="slot-tag">📝 텍스트 인라인 광고 · 본문 하단</div>
              <div class="slot-size">상호 + ☎ + 한 줄 · 마퀴 애니 · <strong>1,000P/월~</strong></div>
            </div>
            <div class="main-block">💬 댓글 / 리뷰 / 문의</div>
            <div class="main-block">◀ 이전글 · 다음글 ▶</div>
          </div>
          <!-- 우측: 추천/관련 -->
          <div class="mock-side">
            <div class="side-label">우측: 추천/관련</div>
            <div class="side-filler">📂 같은 카테고리</div>
            <div class="side-filler">🔥 인기 관련글</div>
            <div class="side-filler">👤 작성자 다른 글</div>
            <div class="side-filler">🔗 관련 링크</div>
            <div class="ad-stack">
              <div class="ad-stack-label">📢 우측 광고 (위젯 아래, 나란히)</div>
              <div class="slot slot-premium" @click="selectSlot('detail-right-1')" :class="{highlight: highlighted==='detail-right-1'}">
                <div class="slot-tag">🥇 프리미엄 B · 300×250</div>
                <div class="slot-size">고정 독점 · 10,000P</div>
              </div>
              <div class="slot slot-standard" @click="selectSlot('detail-right-2')" :class="{highlight: highlighted==='detail-right-2'}">
                <div class="slot-tag">🥈 스탠다드 B · 300×250</div>
                <div class="slot-size">고정 독점 · 7,000P</div>
              </div>
            </div>
          </div>
        </div>
        <div class="mock-footer">Footer</div>
      </div>

      <!-- 📄 상세 — 모바일 -->
      <div v-else-if="pageType === 'detail' && view === 'mobile'" class="mobile-wrap">
        <div class="mobile-mock">
          <div class="mock-nav">🌐 NavBar (← 뒤로)</div>
          <div class="main-block" style="padding:30px">🖼️ 이미지 갤러리</div>
          <div class="main-block">제목 · 가격 · 카테고리</div>
          <div class="main-block" style="padding:38px">본문 내용</div>
          <div class="main-block">👤 작성자 정보 · 연락/쪽지</div>
          <div class="main-block">💬 댓글 영역</div>
          <div class="slot slot-random mobile-slot" @click="selectSlot('m3-2')" :class="{highlight: highlighted==='m3-2'}">
            <div class="slot-tag">🎲 댓글 ↔ 페이지네이션 · 4광고 랜덤</div>
            <div class="slot-size">프A 35% · 프B 35% · 스A 15% · 스B 15%</div>
            <div class="slot-size" style="font-size:8px;opacity:0.85;margin-top:2px">텍스트 인라인은 상세에 없음</div>
          </div>
          <div class="main-block">◀ 이전 · 목록 · 다음 ▶</div>
          <div class="mock-footer">BottomNav</div>
        </div>
        <div class="info-side">
          <div class="info-card">
            <div class="info-head">📄 상세 페이지 배너</div>
            <ul>
              <li>PC: 우측 사이드바 2슬롯 + <strong>본문 하단 인라인</strong></li>
              <li>모바일: <strong>본문 후 + 댓글 후 2곳</strong></li>
              <li>체류 시간 긴 페이지 → 전환율 높음</li>
              <li>대상: 게시글·Q&A·장터·구인·부동산·업소·동호회 상세</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- 공통 하단: 규칙 요약 -->
    <div class="bp-rules">
      <div class="rule-item rule-gold">🥇 프리미엄 A (좌) · 8,000P · 데스크톱 독점 + 모바일 35%</div>
      <div class="rule-item rule-gold">🥇 프리미엄 B (우) · 10,000P · 데스크톱 독점 + 모바일 35%</div>
      <div class="rule-item rule-silver">🥈 스탠다드 A (좌) · 5,000P · 데스크톱 독점 + 모바일 15%</div>
      <div class="rule-item rule-silver">🥈 스탠다드 B (우) · 7,000P · 데스크톱 독점 + 모바일 15%</div>
      <div class="rule-item rule-text">📝 텍스트 인라인 · 1,000P · 리스트 페이지만 (상세 ×)</div>
    </div>

    <div class="bp-foot">
      <span class="bp-hint">💡 페이지 유형 + 디바이스 조합으로 6종 레이아웃. 광고주 미팅 시 이 화면 그대로 공유하세요.</span>
      <button @click="$emit('close')" class="bp-done">확인</button>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref } from 'vue'
defineEmits(['close'])
const pageType = ref('main') // 'main' | 'list' | 'detail'
const view = ref('desktop')
const highlighted = ref(null)
function selectSlot(key) { highlighted.value = highlighted.value === key ? null : key }
</script>

<style scoped>
.bp-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 20px; overflow-y: auto; }
.bp-modal { background: #fff; border-radius: 20px; width: 100%; max-width: 1200px; max-height: 92vh; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.4); }

.bp-head { display: flex; justify-content: space-between; align-items: center; padding: 16px 22px; border-bottom: 1px solid #e5e7eb; }
.bp-title { font-size: 18px; font-weight: 900; color: #1f2937; margin: 0; }
.bp-sub { font-size: 11px; color: #6b7280; margin-top: 2px; }
.bp-close { background: #f3f4f6; border: none; width: 30px; height: 30px; border-radius: 50%; font-size: 13px; cursor: pointer; }
.bp-close:hover { background: #e5e7eb; }

.bp-page-type { display: flex; align-items: center; gap: 6px; padding: 10px 22px; background: #faf5ff; border-bottom: 1px solid #e9d5ff; flex-wrap: wrap; }
.pt-label { font-size: 11px; font-weight: 700; color: #6b21a8; margin-right: 4px; }
.pt-btn { background: #fff; border: 1px solid #e5e7eb; padding: 7px 12px; font-size: 12px; font-weight: 700; color: #6b7280; cursor: pointer; border-radius: 10px; transition: all 0.15s; }
.pt-btn:hover { border-color: #a855f7; color: #7c3aed; }
.pt-btn.active { background: #7c3aed; color: #fff; border-color: #7c3aed; }

.bp-tabs { display: flex; gap: 4px; padding: 8px 22px 0; border-bottom: 1px solid #e5e7eb; }
.bp-tab { background: transparent; border: none; padding: 8px 16px; font-size: 12px; font-weight: 700; color: #6b7280; cursor: pointer; border-bottom: 3px solid transparent; }
.bp-tab.active { color: #7c3aed; border-color: #7c3aed; }

.bp-body { padding: 18px 22px; overflow-y: auto; flex: 1; }

/* 데스크톱 mock */
.desktop-mock { background: linear-gradient(180deg, #fafafa, #f3f4f6); border-radius: 12px; padding: 14px; border: 1px solid #e5e7eb; }
.mock-nav, .mock-footer { background: #1f2937; color: #fff; font-size: 10px; text-align: center; padding: 7px; border-radius: 5px; margin-bottom: 10px; }
.mock-footer { margin-top: 10px; margin-bottom: 0; }
.mock-hero { background: linear-gradient(135deg, #f59e0b, #ea580c); color: #fff; text-align: center; padding: 26px 12px; border-radius: 8px; margin-bottom: 10px; font-weight: 800; font-size: 13px; }
.mock-hero-sm { padding: 18px 12px; font-size: 12px; }
.hero-meta { font-size: 10px; opacity: 0.85; font-weight: 500; }

.list-header { background: #fef3c7; color: #78350f; padding: 10px; border-radius: 6px; text-align: center; font-size: 11px; font-weight: 700; margin-bottom: 10px; }
.list-header.sm { font-size: 10px; padding: 8px; margin-bottom: 8px; }

.mock-3col { display: grid; grid-template-columns: 1fr 2fr 1fr; gap: 8px; }
.mock-3col-narrow { grid-template-columns: 0.8fr 2.2fr 1fr; }

.mock-side { background: rgba(255,255,255,0.7); border-radius: 8px; padding: 10px; display: flex; flex-direction: column; gap: 7px; }
.side-label { font-size: 9px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; padding-bottom: 3px; border-bottom: 1px dashed #d1d5db; }
.side-filler { background: #f3f4f6; color: #9ca3af; font-size: 10px; padding: 12px 8px; border-radius: 5px; text-align: center; font-style: italic; }

.mock-main { background: #fff; border-radius: 8px; padding: 10px; display: flex; flex-direction: column; gap: 7px; }
.mock-main-wide { }
.main-label { font-size: 9px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; padding-bottom: 3px; border-bottom: 1px dashed #d1d5db; }
.main-block { background: #f9fafb; color: #9ca3af; padding: 12px 10px; border-radius: 5px; text-align: center; font-size: 10px; border: 1px dashed #e5e7eb; }

.list-row { background: #fff; border: 1px solid #e5e7eb; padding: 8px 10px; border-radius: 5px; font-size: 10px; color: #6b7280; }

/* Slot 카드 */
.slot { position: relative; border-radius: 7px; padding: 10px 8px; color: #fff; cursor: pointer; transition: all 0.15s; text-align: center; box-shadow: 0 2px 6px rgba(0,0,0,0.15); }
.slot:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.25); }
.slot.highlight { outline: 3px solid #fbbf24; outline-offset: 2px; transform: translateY(-3px); box-shadow: 0 8px 24px rgba(251,191,36,0.5); }
.slot-premium { background: linear-gradient(135deg, #eab308, #ca8a04); }
.slot-standard { background: linear-gradient(135deg, #64748b, #475569); }
.slot-economy { background: linear-gradient(135deg, #c2410c, #9a3412); }
.slot-random { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
.slot-text { background: linear-gradient(135deg, #c026d3, #a21caf); }
.slot-tag { font-size: 10px; font-weight: 900; margin-bottom: 2px; }
.slot-size { font-size: 9px; opacity: 0.9; font-family: monospace; }

.inline-ad { margin: 4px 0; }

/* 광고 블록 - 카테고리/위젯 아래 나란히 */
.ad-stack { margin-top: 8px; padding-top: 8px; border-top: 2px dashed #cbd5e1; display: flex; flex-direction: column; gap: 6px; }
.ad-stack-label { font-size: 9px; font-weight: 700; color: #7c3aed; text-align: center; text-transform: uppercase; letter-spacing: 0.3px; }

/* 모바일 mock */
.mobile-wrap { display: flex; flex-direction: row; gap: 14px; flex-wrap: wrap; align-items: flex-start; }
.mobile-mock { width: 300px; border: 7px solid #1f2937; border-radius: 22px; background: #f3f4f6; padding: 8px; flex-shrink: 0; display: flex; flex-direction: column; gap: 6px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
.mobile-slot { margin: 3px 0; }

.cat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; }
.cat-cell { background: linear-gradient(135deg, #a855f7, #ec4899); aspect-ratio: 5/4; border-radius: 5px; }
.cat-cell:nth-child(2) { background: linear-gradient(135deg, #f59e0b, #d97706); }
.cat-cell:nth-child(3) { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.cat-cell:nth-child(4) { background: linear-gradient(135deg, #10b981, #047857); }
.cat-cell:nth-child(5) { background: linear-gradient(135deg, #6366f1, #4338ca); }
.cat-cell:nth-child(6) { background: linear-gradient(135deg, #ef4444, #dc2626); }
.cat-cell:nth-child(7) { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
.cat-cell:nth-child(8) { background: linear-gradient(135deg, #ec4899, #be185d); }
.cat-cell:nth-child(9) { background: linear-gradient(135deg, #64748b, #334155); }

.info-side { flex: 1; min-width: 260px; display: flex; flex-direction: column; gap: 10px; }
.info-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; }
.info-head { font-size: 12px; font-weight: 900; color: #1f2937; margin-bottom: 6px; }
.info-card ul { margin: 0; padding-left: 16px; font-size: 11px; color: #4b5563; line-height: 1.6; }
.info-card code { background: #f3f4f6; padding: 1px 4px; border-radius: 3px; font-size: 10px; }

/* 규칙 요약 */
.bp-rules { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; padding: 10px 22px; background: #fafafa; border-top: 1px solid #e5e7eb; }
.rule-item { font-size: 10px; padding: 6px 8px; border-radius: 6px; text-align: center; }
.rule-gold { background: #fef3c7; color: #78350f; }
.rule-silver { background: #e5e7eb; color: #334155; }
.rule-bronze { background: #fed7aa; color: #7c2d12; }
.rule-random { background: #ede9fe; color: #5b21b6; }
.rule-text { background: #fdf4ff; color: #86198f; }

.bp-foot { padding: 12px 22px; border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; gap: 10px; }
.bp-hint { font-size: 11px; color: #6b7280; }
.bp-done { background: #7c3aed; color: #fff; padding: 9px 22px; border: none; border-radius: 9px; font-weight: 800; font-size: 12px; cursor: pointer; }
.bp-done:hover { background: #6d28d9; }

@media (max-width: 900px) {
  .mock-3col, .mock-3col-narrow { grid-template-columns: 1fr; }
  .mobile-wrap { flex-direction: column; }
  .bp-rules { grid-template-columns: 1fr 1fr; }
}
</style>
