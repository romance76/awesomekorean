# AwesomeKorean 포커 토너먼트 플랫폼 — 프로젝트 스펙 v1.0

> **목적**: awesomekorean.com에 통합되는 텍사스 홀덤 토너먼트 시스템
> **기술 스택**: Laravel (백엔드) + Vue 3 (프론트엔드) + WebSocket (실시간) + MySQL
> **프로토타입 참조**: `tournament-poker.jsx` (React 기반 싱글플레이어 프로토타입 — 게임 로직, UI, AI 등 참고)

---

## 1. 시스템 아키텍처 개요

```
┌─────────────────────────────────────────────────────────┐
│                   awesomekorean.com                         │
│  ┌──────────┐  ┌──────────┐  ┌────────────────────────┐ │
│  │ 포인트    │  │ 회원     │  │ 포커 플랫폼            │ │
│  │ 시스템    │←→│ 시스템   │←→│                        │ │
│  │ (기존)    │  │ (기존)   │  │  로비 → 대기실 → 게임  │ │
│  └──────────┘  └──────────┘  └────────────────────────┘ │
│                                        ↕                │
│                              ┌────────────────────┐     │
│                              │ WebSocket Server   │     │
│                              │ (Laravel Reverb 등)│     │
│                              └────────────────────┘     │
└─────────────────────────────────────────────────────────┘
```

### 핵심 모듈

| 모듈 | 역할 |
|------|------|
| **포인트 ↔ 게임머니 환전** | AwesomeKorean 포인트를 게임칩으로 전환/역전환 |
| **로비 시스템** | 대기실, 토너먼트 목록, 플레이어 리스트 |
| **토너먼트 엔진** | 자동 생성, 참가, 블라인드 관리, 테이블 배분 |
| **게임 엔진** | 9인 홀덤 로직, 타이머, 사이드팟, 핸드 평가 |
| **멀티플레이어** | WebSocket 실시간 통신 |
| **관전 모드** | 읽기 전용 게임 뷰 |
| **솔로 연습** | AI 대전 (기존 프로토타입 기반) |

---

## 2. 화면 구성 (UI/UX)

### 2.1 메인 진입 화면 (`/poker`)

```
┌──────────────────────────────────────────────────┐
│  AwesomeKorean 포커                    내 포인트: ○○ │
│  ┌────────────────────────────────────────────┐  │
│  │         포인트 → 게임머니 환전              │  │
│  │  [입력: 포인트 금액]  [환전 버튼]           │  │
│  │  내 게임머니 잔고: ○○○ 칩                   │  │
│  └────────────────────────────────────────────┘  │
│                                                   │
│  ┌─ 토너먼트 목록 ──────────────────────────────┐ │
│  │ 🏆 18:00 데일리 $500  | 23/50명 | 참가 버튼  │ │
│  │ 🏆 21:00 하이롤러 $2K | 8/18명  | 참가 버튼  │ │
│  │ ⏳ 다음 토너먼트: 23:30 (자동 생성 예정)     │ │
│  └───────────────────────────────────────────────┘ │
│                                                   │
│  ┌─ 대기실 ──────────────────────────────────────┐ │
│  │ 현재 온라인: 47명                              │ │
│  │ 😎 김철수 (15,000칩) | 🎮 유나 (8,500칩) | …  │ │
│  └───────────────────────────────────────────────┘ │
│                                                   │
│  [🎮 AI 연습 모드]  [📚 룰 & 튜토리얼]           │
└──────────────────────────────────────────────────┘
```

### 2.2 게임 화면 레이아웃

```
┌────────────────────────────────────────────────────────────┐
│ LEVEL 3 │ 25/50 │ 12:45 │ 남은 67/90명      [모니터] [💡] │
├──────────────────────────────────┬─────────────────────────┤
│                                  │ 🏆 TOURNAMENT           │
│                                  │ LEVEL 3 · 25/50 (A5)   │
│      ┌─────── 포커 테이블 ──────┐│     12:45              │
│      │  (9인 타원형 테이블)      ││ REMAINING: 67/90       │
│      │  커뮤니티 카드 + 팟       ││ AVG: 20,149           │
│      │  각 좌석 아바타/카드/칩    ││ MY STACK: 18,500      │
│      │                          ││ RANK: ~23위            │
│      │                          ││ BOUNTIES: 2 ($100)     │
│      └──────────────────────────┘│                         │
│                                  │ PRIZE: $36,000          │
│  ┌─ 폴드카드 ─┐ ┌─ 코칭 ──────┐│ 1st: $9,000 / 13명     │
│  │ 카드+이유   │ │ 승률/추천    ││─────────────────────────│
│  └────────────┘ └─────────────┘││ 💬 채팅                  │
│                                  ││ 유나: 콜~ ㅋㅋ          │
│  [폴드] [콜 50] [올인]          ││ 대니얼: 레이즈!          │
│  ═══════○══════ [레이즈 150]    ││ [채팅 입력]     [전송]  │
├──────────────────────────────────┴─────────────────────────┤
│            ⏱️ 내 턴 — 남은 시간: 18초  [타임뱅크 +30초]    │
└────────────────────────────────────────────────────────────┘
```

핵심: **테이블은 왼쪽 70%**, **토너먼트 모니터 + 채팅은 오른쪽 30%** 항상 고정 표시.

### 2.3 관전 모드

- 게임 화면과 동일하지만 액션 버튼 없음
- 모든 플레이어 카드는 가려진 상태 (쇼다운 시만 공개)
- 채팅은 관전자끼리만 가능 (플레이어에게 안 보임)
- 상단에 "👁️ 관전 중" 표시

---

## 3. 게임 엔진 상세

### 3.1 텍사스 홀덤 규칙 (정식)

#### 게임 흐름
1. **딜러 버튼** 매 핸드 시계방향 이동
2. **블라인드** 포스팅 (SB + BB)
3. **앤티** (블라인드 레벨에 따라)
4. **카드 배분** 각 2장 (홀카드)
5. **프리플랍 베팅** → **플랍** (3장) → **턴** (1장) → **리버** (1장)
6. **쇼다운** 또는 마지막 1명 남으면 승리

#### 베팅 규칙 (No Limit)
- **최소 레이즈**: 이전 레이즈 금액 이상 (첫 레이즈는 BB 이상)
- **최소 베팅**: 1 BB
- **최대 베팅**: 올인 (보유 칩 전부)
- **레이즈 캡**: 없음 (No Limit)

#### 사이드팟 규칙
- 올인 플레이어의 칩 초과분은 사이드팟으로 분리
- 여러 올인 시 멀티 사이드팟 생성
- 각 팟에 참여한 플레이어만 해당 팟 수령 가능

#### 핸드 랭킹 (높은 순)
1. 로열 플러시 (A-K-Q-J-10 같은 무늬)
2. 스트레이트 플러시
3. 포카드
4. 풀하우스
5. 플러시
6. 스트레이트
7. 트리플
8. 투페어
9. 원페어
10. 하이카드

### 3.2 액션 타이머 시스템

```
┌─────────────────────────────────────────┐
│         ⏱️ 액션 타이머                   │
│                                          │
│  기본 시간: 25초 (설정 가능)              │
│  15초 이하: 노란색 경고                   │
│   5초 이하: 빨간색 + 경고음               │
│   0초: 자동 체크/폴드                     │
│                                          │
│  타임아웃 시:                             │
│   - 베팅 의무 없음 → 자동 체크            │
│   - 베팅 의무 있음 → 자동 폴드            │
│   - 3회 연속 타임아웃 → 자동 sit-out      │
│                                          │
│  시각적 표현:                             │
│   - 아바타 주변 원형 프로그레스 바         │
│   - 숫자 카운트다운 (아바타 위)           │
└─────────────────────────────────────────┘
```

### 3.3 타임뱅크 (Time Bank) 시스템

```
┌─────────────────────────────────────────┐
│         ⏰ 타임뱅크                       │
│                                          │
│  초기 지급: 30초 (토너먼트 시작 시)       │
│  추가 획득 방법:                          │
│   - 매 10핸드마다 +15초 자동 충전         │
│   - 바운티 획득 시 +10초 보너스           │
│   - 블라인드 레벨업 시 +10초              │
│  최대 누적: 120초                         │
│                                          │
│  사용 방법:                               │
│   - 기본 시간(25초) 만료 시 자동 시작     │
│   - 또는 [타임뱅크 +30초] 버튼 클릭       │
│   - 타임뱅크도 소진 시 자동 체크/폴드     │
│                                          │
│  UI 표시:                                │
│   - 기본 타이머 옆에 "⏰ 45초" 잔량 표시  │
│   - 사용 중에는 타이머 색상 파란색 변경   │
│   - 소진 시 "타임뱅크 없음" 경고          │
└─────────────────────────────────────────┘
```

### 3.4 AI 엔진 (솔로 모드용)

**프로토타입 기존 구현 참조 (`tournament-poker.jsx`)**:

- 8가지 AI 성격: tight-aggressive, loose-aggressive, tight-passive, loose-passive, maniac, balanced, nit, tricky
- 프리플랍 핸드 평가 + 포스트플랍 핸드 평가
- 스택 사이즈 기반 푸시/폴드 로직
- 포지션별 행동 조절
- 블러프 확률
- 테이블 토크 (40% 확률로 상황별 채팅)

### 3.5 토너먼트 구조

#### 블라인드 스케줄 (15분 레벨)

| 레벨 | SB | BB | 앤티 | 시간 |
|------|----|----|------|------|
| 1 | 10 | 20 | 0 | 15분 |
| 2 | 15 | 30 | 0 | 15분 |
| 3 | 25 | 50 | 5 | 15분 |
| 4 | 50 | 100 | 10 | 15분 |
| 5 | 75 | 150 | 15 | 12분 |
| 6 | 100 | 200 | 25 | 12분 |
| 7 | 150 | 300 | 25 | 12분 |
| 8 | 200 | 400 | 50 | 10분 |
| 9 | 300 | 600 | 75 | 10분 |
| 10 | 400 | 800 | 100 | 10분 |
| 11+ | 계속 증가 | | | 10분 |

#### 테이블 밸런싱
- 테이블 간 인원 차이 2명 이상 시 밸런싱
- 탈락자 발생 시 다른 테이블에서 이동
- 이동 시 "T#3에서 이동" 표시 + 인사 채팅
- 파이널 테이블 (9명 이하) 시 단일 테이블로 합체

#### 바운티 시스템
- 바이인의 10%가 바운티 금액
- 플레이어를 탈락시킨 사람이 바운티 획득
- 바운티 획득 시 타임뱅크 +10초 보너스
- 최종 결과에 바운티 수익 별도 표시

#### 상금 구조 (입상 = 참가자의 15%)
| 순위 | 상금 비율 |
|------|----------|
| 1위 | 25% |
| 2위 | 16% |
| 3위 | 11% |
| 4~6위 | 각 7% |
| 7~10위 | 각 4% |
| 11위~ | 나머지 분배 |

---

## 4. 포인트 ↔ 게임머니 시스템

### 4.1 환전 규칙

```
AwesomeKorean 포인트 → 게임 칩
- 환전 비율: 1 포인트 = 1 칩 (또는 관리자 설정 가능)
- 최소 환전: 1,000 포인트
- 최대 환전: 일일 100,000 포인트 (한도 설정)
- 환전 수수료: 0% (또는 관리자 설정)

게임 칩 → AwesomeKorean 포인트 (역환전)
- 토너먼트 상금만 역환전 가능
- 프리롤/연습 모드 칩은 역환전 불가
- 역환전 시 수수료 5% (플랫폼 수익)
```

### 4.2 DB 테이블 설계

```sql
-- 게임 머니 관련
CREATE TABLE poker_wallets (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT REFERENCES users(id),
    chip_balance BIGINT DEFAULT 0,
    total_deposited BIGINT DEFAULT 0,    -- 총 환전한 포인트
    total_withdrawn BIGINT DEFAULT 0,    -- 총 역환전한 포인트
    total_won BIGINT DEFAULT 0,          -- 총 상금
    total_bounties BIGINT DEFAULT 0,     -- 총 바운티
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE poker_transactions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT REFERENCES users(id),
    type ENUM('deposit', 'withdraw', 'tournament_buyin', 'tournament_prize', 'bounty'),
    amount BIGINT,
    balance_after BIGINT,
    reference_id BIGINT NULL,            -- tournament_id 등
    description VARCHAR(255),
    created_at TIMESTAMP
);
```

---

## 5. 토너먼트 시스템

### 5.1 자동 토너먼트 생성

```
┌─────────────────────────────────────────┐
│    토너먼트 자동 생성 로직               │
│                                          │
│  1. 현재 토너먼트 종료 감지              │
│  2. 다음 토너먼트 시간 계산              │
│     - 현재 시각 + 랜덤(30분 ~ 2시간)    │
│     - 피크 시간(19~23시): 더 자주        │
│     - 새벽(2~8시): 덜 자주               │
│  3. 토너먼트 유형 랜덤 선택              │
│     - 프리롤 (무료, 보너스 상금)         │
│     - 마이크로 ($100~$500 바이인)        │
│     - 레귤러 ($500~$2,000)              │
│     - 하이롤러 ($2,000~$10,000)          │
│  4. 참가 모집 시작 (로비에 표시)         │
│  5. 시작 시간 도래 시:                   │
│     - 최소 인원(9명) 미달 → 10분 연장   │
│     - 연장 후에도 미달 → 취소 + 환불    │
│     - 충족 시 → 테이블 배분 후 시작     │
│  6. 최대 인원: 설정값 (기본 90명)        │
│  7. Late registration: 레벨 3까지 가능   │
└─────────────────────────────────────────┘
```

### 5.2 DB 테이블

```sql
CREATE TABLE tournaments (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),
    type ENUM('freeroll', 'micro', 'regular', 'high_roller'),
    status ENUM('scheduled', 'registering', 'running', 'finished', 'cancelled'),
    buy_in INT DEFAULT 0,
    starting_chips INT DEFAULT 15000,
    min_players INT DEFAULT 9,
    max_players INT DEFAULT 90,
    current_players INT DEFAULT 0,
    start_time DATETIME,
    actual_start_time DATETIME NULL,
    end_time DATETIME NULL,
    blind_level INT DEFAULT 0,
    blind_timer_seconds INT DEFAULT 900,
    prize_pool BIGINT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE tournament_entries (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tournament_id BIGINT REFERENCES tournaments(id),
    user_id BIGINT REFERENCES users(id),
    seat_number INT,
    table_number INT,
    chips INT,
    status ENUM('registered', 'playing', 'eliminated', 'winner'),
    finish_position INT NULL,
    prize_amount BIGINT DEFAULT 0,
    bounties_collected INT DEFAULT 0,
    bounty_earnings BIGINT DEFAULT 0,
    registered_at TIMESTAMP,
    eliminated_at TIMESTAMP NULL
);

CREATE TABLE tournament_tables (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tournament_id BIGINT REFERENCES tournaments(id),
    table_number INT,
    status ENUM('active', 'closed'),
    dealer_seat INT DEFAULT 0,
    small_blind INT,
    big_blind INT,
    ante INT DEFAULT 0,
    pot BIGINT DEFAULT 0,
    community_cards JSON,            -- ["Ah","Kd","Qs","Jc","10s"]
    stage ENUM('waiting', 'preflop', 'flop', 'turn', 'river', 'showdown'),
    current_actor_seat INT NULL,
    action_deadline DATETIME NULL,   -- 액션 타이머
    created_at TIMESTAMP
);

CREATE TABLE tournament_hands (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    table_id BIGINT REFERENCES tournament_tables(id),
    hand_number INT,
    community_cards JSON,
    pot BIGINT,
    winner_user_id BIGINT,
    winner_hand_name VARCHAR(50),
    created_at TIMESTAMP
);

CREATE TABLE tournament_actions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    hand_id BIGINT REFERENCES tournament_hands(id),
    user_id BIGINT REFERENCES users(id),
    action ENUM('fold', 'check', 'call', 'raise', 'allin', 'timeout_fold', 'timeout_check'),
    amount BIGINT DEFAULT 0,
    stage ENUM('preflop', 'flop', 'turn', 'river'),
    time_taken_seconds INT,          -- 액션에 걸린 시간
    used_timebank BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP
);

-- 타임뱅크
CREATE TABLE player_timebanks (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tournament_id BIGINT REFERENCES tournaments(id),
    user_id BIGINT REFERENCES users(id),
    remaining_seconds INT DEFAULT 30,
    last_recharged_at TIMESTAMP
);

-- 관전
CREATE TABLE tournament_spectators (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tournament_id BIGINT REFERENCES tournaments(id),
    table_id BIGINT REFERENCES tournament_tables(id),
    user_id BIGINT REFERENCES users(id),
    joined_at TIMESTAMP
);

-- 채팅
CREATE TABLE game_chat (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tournament_id BIGINT REFERENCES tournaments(id),
    table_id BIGINT REFERENCES tournament_tables(id),
    user_id BIGINT REFERENCES users(id),
    message VARCHAR(500),
    is_spectator BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP
);
```

---

## 6. WebSocket 이벤트 설계

### 6.1 서버 → 클라이언트

```javascript
// 게임 상태 업데이트
'game.state' → { seats, community, pot, stage, dealerIdx, currentActor, timer }

// 특정 플레이어 액션
'game.action' → { seatIdx, action, amount, playerName, emoji }

// 카드 배분 (해당 플레이어에게만 홀카드 전송)
'game.deal' → { yourCards: [{rank, suit}, ...] }

// 커뮤니티 카드
'game.community' → { cards: [...], stage }

// 쇼다운 결과
'game.showdown' → { results: [{seat, cards, handName, isWinner}], pot }

// 타이머
'game.timer' → { seatIdx, secondsRemaining, isTimebank }

// 플레이어 탈락
'game.elimination' → { playerName, eliminatedBy, bountyAmount, remaining }

// 테이블 이동
'game.table_move' → { playerName, fromTable, toTable }

// 블라인드 레벨 변경
'game.blind_up' → { level, sb, bb, ante, nextLevel }

// 토너먼트 종료
'game.tournament_end' → { finalResults: [{rank, name, prize}] }

// 채팅 메시지
'chat.message' → { userName, message, isSpectator, timestamp }
```

### 6.2 클라이언트 → 서버

```javascript
// 플레이어 액션
'player.action' → { action: 'fold|check|call|raise|allin', amount }

// 타임뱅크 사용
'player.use_timebank' → {}

// 채팅
'player.chat' → { message }

// 토너먼트 참가
'tournament.register' → { tournamentId }

// 관전 시작
'tournament.spectate' → { tournamentId, tableId }
```

---

## 7. 프론트엔드 컴포넌트 구조

```
src/
├── views/
│   ├── PokerLobby.vue              ← 로비 (토너먼트 목록, 대기실, 환전)
│   ├── PokerGame.vue               ← 게임 화면 (메인)
│   ├── PokerTutorial.vue           ← 룰 & 튜토리얼
│   └── PokerPractice.vue           ← AI 솔로 연습 모드
│
├── components/poker/
│   ├── PokerTable.vue              ← 타원형 테이블 + 9좌석
│   ├── PlayerSeat.vue              ← 아바타, 카드, 칩, 포지션, 채팅버블
│   ├── PokerCard.vue               ← 카드 디자인 (SVG 수트)
│   ├── CommunityCards.vue          ← 커뮤니티 카드 5장
│   ├── PotDisplay.vue              ← 팟 + 칩 시각화
│   ├── ActionPanel.vue             ← 폴드/체크/콜/레이즈/올인 버튼
│   ├── ActionTimer.vue             ← 원형 카운트다운 + 타임뱅크
│   ├── TournamentMonitor.vue       ← 오른쪽 고정 모니터 패널
│   ├── ChatPanel.vue               ← 채팅 (모니터 하단)
│   ├── CoachingPanel.vue           ← 승률/추천/용어 툴팁 (솔로 모드용)
│   ├── FoldReveal.vue              ← 폴드 카드 공개 + 이유 (솔로 모드용)
│   ├── HandRankLabel.vue           ← 쇼다운 핸드 이름 라벨
│   ├── ChipStack.vue               ← 칩 스택 시각화
│   ├── SuitIcon.vue                ← SVG 수트 아이콘 (♠♥♦♣)
│   ├── DealerButton.vue            ← 딜러 칩
│   └── TermTooltip.vue             ← 포커 용어 툴팁
│
├── components/lobby/
│   ├── TournamentList.vue          ← 토너먼트 목록
│   ├── TournamentCard.vue          ← 개별 토너먼트 카드
│   ├── PlayerList.vue              ← 온라인 플레이어 목록
│   ├── PointExchange.vue           ← 포인트 ↔ 칩 환전
│   └── LobbyChat.vue              ← 로비 채팅
│
├── composables/
│   ├── usePokerEngine.js           ← 게임 로직 (핸드 평가, 덱, 셔플)
│   ├── useAI.js                    ← AI 엔진 (솔로 모드)
│   ├── useWebSocket.js             ← WebSocket 연결 관리
│   ├── useTimer.js                 ← 액션 타이머 + 타임뱅크
│   ├── useTournament.js            ← 토너먼트 상태 관리
│   └── useCoaching.js              ← 코칭 시스템 (승률, 추천)
│
├── stores/
│   ├── pokerStore.js               ← 게임 상태 (Pinia)
│   ├── tournamentStore.js          ← 토너먼트 상태
│   ├── walletStore.js              ← 게임머니 잔고
│   └── chatStore.js                ← 채팅 메시지
│
└── utils/
    ├── handEvaluator.js            ← 핸드 평가 알고리즘
    ├── equityCalculator.js         ← 승률 계산
    ├── pokerTerms.js               ← 포커 용어 사전 (한/영)
    └── chipFormatter.js            ← 칩 숫자 포맷 (15K 등)
```

---

## 8. 백엔드 구조 (Laravel)

```
app/
├── Http/Controllers/
│   ├── PokerLobbyController.php     ← 로비 API
│   ├── TournamentController.php     ← 토너먼트 CRUD
│   ├── PokerWalletController.php    ← 환전 API
│   └── PokerGameController.php      ← 게임 액션 API
│
├── Services/
│   ├── TournamentService.php        ← 토너먼트 엔진
│   ├── GameEngine.php               ← 홀덤 게임 로직
│   ├── HandEvaluator.php            ← 핸드 평가
│   ├── BlindScheduler.php           ← 블라인드 레벨 관리
│   ├── TableBalancer.php            ← 테이블 밸런싱
│   ├── PrizeDistributor.php         ← 상금 배분
│   └── TournamentScheduler.php      ← 자동 토너먼트 생성
│
├── Events/ (WebSocket)
│   ├── GameStateUpdated.php
│   ├── PlayerActed.php
│   ├── CardsDealt.php
│   ├── PlayerEliminated.php
│   ├── BlindLevelChanged.php
│   ├── TournamentEnded.php
│   └── ChatMessageSent.php
│
├── Jobs/
│   ├── RunTournamentRound.php       ← 토너먼트 라운드 처리
│   ├── ProcessActionTimeout.php     ← 타임아웃 처리
│   ├── ScheduleNextTournament.php   ← 다음 토너먼트 생성
│   └── BalanceTables.php            ← 테이블 밸런싱
│
├── Models/
│   ├── Tournament.php
│   ├── TournamentEntry.php
│   ├── TournamentTable.php
│   ├── TournamentHand.php
│   ├── TournamentAction.php
│   ├── PokerWallet.php
│   ├── PokerTransaction.php
│   ├── PlayerTimebank.php
│   └── GameChat.php
│
└── Console/Commands/
    ├── TournamentTick.php           ← 매초 실행 (타이머, 블라인드)
    └── AutoCreateTournament.php     ← 토너먼트 자동 생성
```

---

## 9. API 엔드포인트

```
# 로비
GET    /api/poker/lobby                  ← 토너먼트 목록 + 온라인 플레이어
GET    /api/poker/tournaments            ← 토너먼트 목록 (필터링)
POST   /api/poker/tournaments/{id}/register  ← 참가 등록
DELETE /api/poker/tournaments/{id}/register  ← 참가 취소

# 환전
GET    /api/poker/wallet                 ← 내 게임머니 잔고
POST   /api/poker/wallet/deposit         ← 포인트 → 칩
POST   /api/poker/wallet/withdraw        ← 칩 → 포인트

# 게임
GET    /api/poker/game/{tableId}         ← 현재 테이블 상태
POST   /api/poker/game/{tableId}/action  ← 액션 (폴드/체크/콜/레이즈/올인)
POST   /api/poker/game/{tableId}/timebank ← 타임뱅크 사용

# 관전
GET    /api/poker/spectate/{tournamentId}  ← 관전 가능 테이블 목록
POST   /api/poker/spectate/{tableId}       ← 관전 시작

# 채팅
GET    /api/poker/chat/{tableId}         ← 채팅 히스토리
POST   /api/poker/chat/{tableId}         ← 메시지 전송

# 통계
GET    /api/poker/stats/me               ← 내 통계 (승률, 바운티, 수익)
GET    /api/poker/leaderboard            ← 리더보드
```

---

## 10. 개발 순서 (권장)

### Phase 1: 솔로 모드 완성 (1~2주)
- [ ] 현재 프로토타입(`tournament-poker.jsx`)을 Vue 3로 포팅
- [ ] 레이아웃 변경: 테이블 왼쪽 70% + 모니터/채팅 오른쪽 30%
- [ ] 액션 타이머 (25초 + 시각적 카운트다운)
- [ ] 타임뱅크 시스템
- [ ] 사이드팟 정확한 처리
- [ ] SVG 카드 디자인 (♠♥♦♣ 동일 크기)
- [ ] 코칭 시스템 (승률%, 팟오즈, 추천 액션)
- [ ] AI 테이블 토크
- [ ] 바운티 시스템
- [ ] 테이블 밸런싱 (다른 테이블에서 이동 표시)
- [ ] 포커 용어 툴팁 (마우스 오버 시 설명)
- [ ] 룰 & 튜토리얼 페이지

### Phase 2: 백엔드 기초 (2~3주)
- [ ] DB 마이그레이션 (위 SQL 스키마)
- [ ] 포인트 ↔ 게임머니 환전 API
- [ ] 토너먼트 CRUD
- [ ] 게임 엔진 (서버 사이드 핸드 평가)
- [ ] 토너먼트 자동 생성 스케줄러

### Phase 3: 멀티플레이어 (3~4주)
- [ ] WebSocket 서버 설정 (Laravel Reverb 또는 Pusher)
- [ ] 실시간 게임 상태 동기화
- [ ] 액션 타이머 서버 사이드 처리
- [ ] 테이블 밸런싱 (멀티 테이블)
- [ ] 사이드팟 서버 로직
- [ ] 관전 모드

### Phase 4: 로비 & 통합 (1~2주)
- [ ] 로비 UI (토너먼트 목록, 대기실)
- [ ] 참가 등록/취소
- [ ] 채팅 시스템 (테이블 내 + 관전자)
- [ ] 리더보드
- [ ] awesomekorean.com 메뉴 통합

### Phase 5: 테스트 & 런칭 (1~2주)
- [ ] 부하 테스트 (동시 접속 100+)
- [ ] 보안 감사 (칩 조작 방지, 레이스 컨디션)
- [ ] 모바일 반응형 UI
- [ ] 베타 테스트 (내부 회원)
- [ ] 프로덕션 배포

---

## 11. 프로토타입에서 가져올 코드

현재 `tournament-poker.jsx`에서 재사용 가능한 핵심 로직:

| 기능 | 위치 | 참고 |
|------|------|------|
| 카드 덱 생성/셔플 | `createDeck()`, `shuffle()` | 그대로 사용 |
| 핸드 평가 (5장) | `eval5()`, `evalHand()` | 서버+클라이언트 양쪽 |
| 프리플랍 핸드 강도 | `preflopStr()` | AI + 코칭용 |
| AI 의사결정 | `aiAct()` | 솔로 모드용 |
| 승률 추정 | `estimateEquity()` | 코칭 패널용 |
| 추천 액션 | `getRecommendation()` | 코칭용 |
| 폴드 이유 분석 | `getFoldReason()` | 학습 모드용 |
| 포커 용어 사전 | `TERMS` 객체 | 툴팁용 |
| SVG 수트 아이콘 | `SuitIcon` 컴포넌트 | 카드 렌더링 |
| AI 채팅 | `CHAT_LINES`, `getChat()` | 솔로 모드용 |
| 블라인드 스케줄 | `BLIND_SCHEDULE` | 토너먼트 설정 |
| AI 프로필 | `AI_PROFILES` | 솔로 모드 NPC |

---

## 12. 보안 고려사항

- **서버 사이드 검증**: 모든 게임 액션은 서버에서 유효성 검증
- **카드 암호화**: 홀카드는 해당 플레이어에게만 WebSocket으로 전송
- **칩 조작 방지**: 칩 잔고 변경은 서버에서만 처리
- **레이스 컨디션**: 액션 처리 시 DB 트랜잭션 + 낙관적 잠금
- **CSRF/XSS**: Laravel 기본 보호 활용
- **Rate Limiting**: 액션 API에 속도 제한
- **환전 감사 로그**: 모든 포인트 ↔ 칩 거래 기록

---

## 13. 참고 사항

- **호스팅**: DigitalOcean (IP: 68.183.60.70) — 기존 AwesomeKorean 인프라
- **GitHub**: github.com/romance76/awesomekorean
- **결제 통합**: CardPointe/Fiserv (기존 연동) — 직접 현금 결제는 Phase 2+
- **모바일**: 반응형 우선, 향후 앱 래핑 가능

---

*이 스펙은 Claude Code에게 전달하여 단계별 구현에 활용합니다.*
*프로토타입 파일 `tournament-poker.jsx`를 함께 전달하세요.*
