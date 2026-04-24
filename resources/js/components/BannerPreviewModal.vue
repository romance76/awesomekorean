<template>
<div class="bp-overlay" @click.self="$emit('close')">
  <div class="bp-modal">
    <!-- 헤더 -->
    <div class="bp-head">
      <div>
        <h2 class="bp-title">🔍 샘플 배너 위치 확인</h2>
        <p class="bp-sub">광고주에게 보여줄 배너 위치·크기·가격 레이아웃 미리보기</p>
      </div>
      <button @click="$emit('close')" class="bp-close">✕</button>
    </div>

    <!-- 뷰 전환 탭 -->
    <div class="bp-tabs">
      <button @click="view = 'desktop'" :class="['bp-tab', view==='desktop' && 'active']">🖥️ 데스크톱</button>
      <button @click="view = 'mobile'" :class="['bp-tab', view==='mobile' && 'active']">📱 모바일</button>
    </div>

    <!-- ═════════════ 데스크톱 뷰 ═════════════ -->
    <div v-if="view === 'desktop'" class="bp-body">
      <div class="desktop-mock">
        <!-- 상단 네비 -->
        <div class="mock-nav">🌐 NavBar · 검색 · 로그인</div>

        <!-- 히어로 -->
        <div class="mock-hero">
          <div>히어로 슬라이드 (280×자동)<br/><span class="hero-meta">메인 브랜딩 / 이벤트 / 공지</span></div>
        </div>

        <!-- 3-column 레이아웃 -->
        <div class="mock-3col">
          <!-- 좌측 사이드바 -->
          <div class="mock-side mock-side-left">
            <div class="side-label">좌측 사이드바</div>

            <!-- Left Slot 1 프리미엄 -->
            <div class="slot slot-premium" @click="selectSlot('left-1')" :class="{highlight: highlighted==='left-1'}">
              <div class="slot-tag">🥇 슬롯 1 · 프리미엄</div>
              <div class="slot-size">200×150</div>
              <div class="slot-price">최고 입찰가 확정 · 1개</div>
            </div>
            <!-- Left Slot 2 스탠다드 -->
            <div class="slot slot-standard" @click="selectSlot('left-2')" :class="{highlight: highlighted==='left-2'}">
              <div class="slot-tag">🥈 슬롯 2 · 스탠다드</div>
              <div class="slot-size">200×150</div>
              <div class="slot-price">Top 중 랜덤 · 최대 2개</div>
            </div>
            <!-- Left Slot 3 이코노미 -->
            <div class="slot slot-economy" @click="selectSlot('left-3')" :class="{highlight: highlighted==='left-3'}">
              <div class="slot-tag">🥉 슬롯 3 · 이코노미</div>
              <div class="slot-size">200×150</div>
              <div class="slot-price">Top 중 랜덤 · 최대 5개</div>
            </div>

            <div class="side-filler">카테고리 · 인기게시판 · 트렌딩 등</div>
          </div>

          <!-- 중앙 콘텐츠 -->
          <div class="mock-main">
            <div class="main-label">중앙 콘텐츠 영역</div>
            <div class="main-block">공지 · 2×2 섹션 박스</div>
            <div class="main-block">최신글 · 구인구직 · 장터 · 이벤트</div>
            <div class="main-block">최신 부동산 카드 갤러리</div>
          </div>

          <!-- 우측 사이드바 -->
          <div class="mock-side mock-side-right">
            <div class="side-label">우측 사이드바</div>
            <div class="side-filler">날씨 / 환율</div>

            <!-- Right Slot 1 프리미엄 -->
            <div class="slot slot-premium" @click="selectSlot('right-1')" :class="{highlight: highlighted==='right-1'}">
              <div class="slot-tag">🥇 슬롯 1 · 프리미엄</div>
              <div class="slot-size">300×250</div>
              <div class="slot-price">최고 입찰가 확정 · 1개</div>
            </div>
            <!-- Right Slot 3 이코노미 -->
            <div class="slot slot-economy" @click="selectSlot('right-3')" :class="{highlight: highlighted==='right-3'}">
              <div class="slot-tag">🥉 슬롯 3 · 이코노미</div>
              <div class="slot-size">300×250</div>
              <div class="slot-price">Top 중 랜덤 · 최대 3개</div>
            </div>

            <div class="side-filler">즐겨찾기 / 가입 CTA</div>
          </div>
        </div>

        <div class="mock-footer">BottomNav · Footer</div>
      </div>

      <!-- 정보 패널 -->
      <div class="info-panel">
        <div class="info-grid">
          <div class="info-card info-left">
            <div class="info-head">🏠 좌측 사이드바 (3 슬롯)</div>
            <ul>
              <li><strong class="tag-gold">프리미엄</strong> · 최고 입찰가 <strong>확정</strong> (노출 보장)</li>
              <li><strong class="tag-silver">스탠다드</strong> · Top 후보 중 <strong>랜덤</strong> 회전 (최대 2)</li>
              <li><strong class="tag-bronze">이코노미</strong> · Top 후보 중 <strong>랜덤</strong> 회전 (최대 5)</li>
              <li>이미지 권장: <code>200×150</code> PNG/JPG</li>
            </ul>
          </div>
          <div class="info-card info-right">
            <div class="info-head">🏠 우측 사이드바 (2 슬롯)</div>
            <ul>
              <li><strong class="tag-gold">프리미엄</strong> · 최고 입찰가 확정</li>
              <li><strong class="tag-bronze">이코노미</strong> · Top 후보 중 랜덤 (최대 3)</li>
              <li>이미지 권장: <code>300×250</code> PNG/JPG</li>
            </ul>
          </div>
          <div class="info-card info-rule">
            <div class="info-head">📐 낙찰 규칙</div>
            <ul>
              <li><strong>bid_amount</strong> 입찰 금액 기준 정렬</li>
              <li>슬롯 1 = 최고가 1명 확정 배치</li>
              <li>슬롯 2/3 = Top N 풀에서 가중 랜덤 회전</li>
              <li>30분 Redis 캐시 · 임프레션·클릭 트래킹</li>
              <li>지역 필터: state / county / city</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- ═════════════ 모바일 뷰 ═════════════ -->
    <div v-else class="bp-body">
      <div class="mobile-wrap">
        <div class="mobile-mock">
          <div class="mock-nav">🌐 NavBar</div>
          <div class="mock-hero mock-hero-sm">
            <div>히어로 슬라이드 (180px)</div>
          </div>

          <!-- 모바일 배너 1 -->
          <div class="slot slot-premium mobile-slot" @click="selectSlot('m-1')" :class="{highlight: highlighted==='m-1'}">
            <div class="slot-tag">🥇 슬롯 1 · 프리미엄 · 모바일 상단</div>
            <div class="slot-size">320×80 (풀폭)</div>
            <div class="slot-price">최고 입찰가 확정</div>
          </div>

          <!-- 카테고리 그리드 mock -->
          <div class="cat-grid">
            <span v-for="c in 9" :key="c" class="cat-cell"></span>
          </div>

          <div class="main-block">공지 · 최신글 · 구인 · 장터</div>
          <div class="main-block">최신 부동산 카드</div>

          <!-- 모바일 배너 2 -->
          <div class="slot slot-random mobile-slot" @click="selectSlot('m-2')" :class="{highlight: highlighted==='m-2'}">
            <div class="slot-tag">🎲 슬롯 2/3 · 랜덤 · 콘텐츠 중간</div>
            <div class="slot-size">320×80 (풀폭)</div>
            <div class="slot-price">Top 가중 랜덤 (슬롯 2=60% · 슬롯 3=40%)</div>
          </div>

          <div class="main-block">날씨 / 환율</div>
          <div class="main-block">즐겨찾기</div>
          <div class="mock-footer">BottomNav</div>
        </div>

        <div class="info-panel" style="margin-left:24px;flex:1">
          <div class="info-card">
            <div class="info-head">📱 모바일 배너 규칙</div>
            <ul>
              <li><strong>2개 위치</strong> · 히어로 직하 + 콘텐츠 중간</li>
              <li>위: <strong class="tag-gold">프리미엄</strong> (최고가 확정, position=mobile + slot_number=1)</li>
              <li>중간: <strong>가중 랜덤</strong> (slot_number 2/3)</li>
              <li>이미지 권장: <code>720×180</code> (2x) · <code>320×80</code> 랜더</li>
              <li>10분 Redis 캐시로 회전 안정</li>
            </ul>
          </div>
          <div class="info-card">
            <div class="info-head">💡 관리 화면과 연결</div>
            <ul>
              <li>광고 신청 페이지에서 <code>position=mobile</code> 선택 가능</li>
              <li>슬롯 번호 1 = 프리미엄 (최고 단가)</li>
              <li>슬롯 2/3 = 가중 랜덤 (중간 단가)</li>
              <li>광고주는 <strong>기간·지역·슬롯</strong> 조합으로 입찰</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div class="bp-foot">
      <span class="bp-hint">💡 각 슬롯을 클릭하면 하이라이트됩니다. 광고주 미팅 시 이 화면을 그대로 공유하세요.</span>
      <button @click="$emit('close')" class="bp-done">확인</button>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref } from 'vue'
defineEmits(['close'])
const view = ref('desktop')
const highlighted = ref(null)
function selectSlot(key) { highlighted.value = highlighted.value === key ? null : key }
</script>

<style scoped>
.bp-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 20px; overflow-y: auto; }
.bp-modal { background: #fff; border-radius: 20px; width: 100%; max-width: 1100px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.4); }
.bp-head { display: flex; justify-content: space-between; align-items: center; padding: 18px 22px; border-bottom: 1px solid #e5e7eb; }
.bp-title { font-size: 18px; font-weight: 900; color: #1f2937; margin: 0; }
.bp-sub { font-size: 11px; color: #6b7280; margin-top: 2px; }
.bp-close { background: #f3f4f6; border: none; width: 32px; height: 32px; border-radius: 50%; font-size: 14px; cursor: pointer; }
.bp-close:hover { background: #e5e7eb; }

.bp-tabs { display: flex; gap: 6px; padding: 10px 22px 0; border-bottom: 1px solid #e5e7eb; }
.bp-tab { background: transparent; border: none; padding: 10px 18px; font-size: 13px; font-weight: 700; color: #6b7280; cursor: pointer; border-bottom: 3px solid transparent; }
.bp-tab.active { color: #7c3aed; border-color: #7c3aed; }

.bp-body { padding: 22px; overflow-y: auto; flex: 1; }

/* ═════ 데스크톱 mock ═════ */
.desktop-mock { background: linear-gradient(180deg, #fafafa, #f3f4f6); border-radius: 14px; padding: 16px; border: 1px solid #e5e7eb; }
.mock-nav, .mock-footer { background: #1f2937; color: #fff; font-size: 11px; text-align: center; padding: 8px; border-radius: 6px; margin-bottom: 12px; }
.mock-footer { margin-top: 12px; margin-bottom: 0; }
.mock-hero { background: linear-gradient(135deg, #f59e0b, #ea580c); color: #fff; text-align: center; padding: 30px 12px; border-radius: 10px; margin-bottom: 12px; font-weight: 800; }
.mock-hero-sm { padding: 20px 12px; }
.hero-meta { font-size: 11px; opacity: 0.8; font-weight: 500; }

.mock-3col { display: grid; grid-template-columns: 1fr 2fr 1fr; gap: 10px; }
.mock-side { background: rgba(255,255,255,0.7); border-radius: 8px; padding: 10px; display: flex; flex-direction: column; gap: 8px; }
.side-label { font-size: 10px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; padding-bottom: 4px; border-bottom: 1px dashed #d1d5db; }
.side-filler { background: #f3f4f6; color: #9ca3af; font-size: 10px; padding: 16px 8px; border-radius: 6px; text-align: center; font-style: italic; }

.mock-main { background: #fff; border-radius: 8px; padding: 10px; display: flex; flex-direction: column; gap: 8px; }
.main-label { font-size: 10px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; padding-bottom: 4px; border-bottom: 1px dashed #d1d5db; }
.main-block { background: #f9fafb; color: #9ca3af; padding: 14px 10px; border-radius: 6px; text-align: center; font-size: 11px; border: 1px dashed #e5e7eb; }

/* ═════ Slot 카드 ═════ */
.slot { position: relative; border-radius: 8px; padding: 12px 10px; color: #fff; cursor: pointer; transition: all 0.15s; text-align: center; box-shadow: 0 2px 6px rgba(0,0,0,0.15); }
.slot:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.25); }
.slot.highlight { outline: 3px solid #fbbf24; outline-offset: 2px; transform: translateY(-3px); box-shadow: 0 8px 24px rgba(251,191,36,0.5); }
.slot-premium { background: linear-gradient(135deg, #eab308, #ca8a04); }
.slot-standard { background: linear-gradient(135deg, #64748b, #475569); }
.slot-economy { background: linear-gradient(135deg, #c2410c, #9a3412); }
.slot-random { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
.slot-tag { font-size: 11px; font-weight: 900; margin-bottom: 3px; }
.slot-size { font-size: 10px; opacity: 0.9; font-family: monospace; }
.slot-price { font-size: 9px; opacity: 0.85; margin-top: 3px; }

/* ═════ 정보 패널 ═════ */
.info-panel { margin-top: 18px; }
.info-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
.info-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; }
.info-head { font-size: 13px; font-weight: 900; color: #1f2937; margin-bottom: 8px; }
.info-card ul { margin: 0; padding-left: 16px; font-size: 11px; color: #4b5563; line-height: 1.7; }
.info-card code { background: #f3f4f6; padding: 1px 4px; border-radius: 3px; font-size: 10px; }
.tag-gold { color: #ca8a04; font-weight: 900; }
.tag-silver { color: #64748b; font-weight: 900; }
.tag-bronze { color: #9a3412; font-weight: 900; }

/* ═════ 모바일 mock ═════ */
.mobile-wrap { display: flex; flex-direction: row; gap: 16px; flex-wrap: wrap; }
.mobile-mock { width: 320px; border: 8px solid #1f2937; border-radius: 24px; background: #f3f4f6; padding: 10px; flex-shrink: 0; display: flex; flex-direction: column; gap: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
.mobile-slot { margin: 4px 0; }
.cat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; }
.cat-cell { background: linear-gradient(135deg, #a855f7, #ec4899); aspect-ratio: 5/4; border-radius: 6px; }
.cat-cell:nth-child(2) { background: linear-gradient(135deg, #f59e0b, #d97706); }
.cat-cell:nth-child(3) { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.cat-cell:nth-child(4) { background: linear-gradient(135deg, #10b981, #047857); }
.cat-cell:nth-child(5) { background: linear-gradient(135deg, #6366f1, #4338ca); }
.cat-cell:nth-child(6) { background: linear-gradient(135deg, #ef4444, #dc2626); }
.cat-cell:nth-child(7) { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
.cat-cell:nth-child(8) { background: linear-gradient(135deg, #ec4899, #be185d); }
.cat-cell:nth-child(9) { background: linear-gradient(135deg, #64748b, #334155); }

/* ═════ 푸터 ═════ */
.bp-foot { padding: 14px 22px; border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; gap: 10px; }
.bp-hint { font-size: 11px; color: #6b7280; }
.bp-done { background: #7c3aed; color: #fff; padding: 10px 24px; border: none; border-radius: 10px; font-weight: 800; font-size: 13px; cursor: pointer; }
.bp-done:hover { background: #6d28d9; }

@media (max-width: 900px) {
  .mock-3col { grid-template-columns: 1fr; }
  .info-grid { grid-template-columns: 1fr; }
  .mobile-wrap { flex-direction: column; }
}
</style>
