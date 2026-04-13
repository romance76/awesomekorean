import { ref, computed } from 'vue';
import { createDeck, shuffle, evalHand, preflopStr, RV } from './useHandEvaluation';
import { AI_PROFILES, aiAct, getChat, getFoldReason, CHAT_LINES } from './useAI';
import { getCoach, POSITION_NAMES, POS_FULL, POS_TIP, TERMS, getTermForAction } from './useCoaching';
import { BLIND_SCHEDULE, getCurrentBlind, getNextBlind, fmtTime, fmtElapsed } from './useBlindSchedule';

export function usePokerGame() {
  // ─── Tournament Config ───
  const screen = ref('setup'); // setup | game | result
  const config = ref({ buyIn: 400, totalPlayers: 90, startChips: 15000 });

  // ─── Tournament State ───
  const blindLevel = ref(0);
  const totalRemaining = ref(0);
  const myRank = ref(1);
  const handNum = ref(0);
  const tourneyLog = ref([]);
  const myBounties = ref([]);
  const chatBubbles = ref({});

  // ─── Table State ───
  const seats = ref([]);
  const dealerIdx = ref(0);
  const community = ref([]);
  const pot = ref(0);
  const stage = ref('preflop');
  const actIdx = ref(-1);
  const lastAction = ref('');
  const isPlayerTurn = ref(false);
  const gameOver = ref(false);
  const showdown = ref(false);
  const raiseAmt = ref(0);
  const coachTips = ref(null);
  const showCoach = ref(true);
  const showMonitor = ref(false);
  const bustMsg = ref('');
  const handResults = ref(null);
  const tourneyOver = ref(false);
  const finalPlace = ref(0);
  const currentBetLevel = ref(0);
  const levelTimer = ref(0);
  const elapsedTime = ref(0);
  const foldReveals = ref([]);
  const termTip = ref(null);

  let deckArr = [];
  let actionTimeout = null;
  let timerInterval = null;
  let turnTimerInterval = null;

  // ── 턴 타이머 ──
  const turnTimer = ref(0);
  const turnTimerMax = ref(15); // 일반 15초, 스피드 10초

  // ─── Computed ───
  const bl = computed(() => getCurrentBlind(blindLevel.value));
  const nextBl = computed(() => getNextBlind(blindLevel.value));
  const paidSlots = computed(() => Math.max(1, Math.floor(config.value.totalPlayers * 0.15)));
  const prizePool = computed(() => config.value.buyIn * config.value.totalPlayers);

  const prizes = computed(() => {
    const ps = paidSlots.value;
    const p = [];
    if (ps >= 10) {
      p.push({ place: '1st', pct: 25 }, { place: '2nd', pct: 16 }, { place: '3rd', pct: 11 }, { place: '4~6th', pct: 7 }, { place: '7~10th', pct: 4 });
      const rem = 100 - 25 - 16 - 11 - 7 * 3 - 4 * 4;
      if (ps > 10) p.push({ place: `11~${ps}th`, pct: Math.max(1, rem / (ps - 10)) });
    } else {
      p.push({ place: '1st', pct: 50 }, { place: '2nd', pct: 30 }, { place: '3rd', pct: 20 });
    }
    return p;
  });

  // ─── Timer ───
  function startTimer() {
    stopTimer();
    timerInterval = setInterval(() => {
      if (screen.value !== 'game' || tourneyOver.value) return;
      levelTimer.value--;
      elapsedTime.value++;
      if (levelTimer.value <= 0) {
        blindLevel.value = Math.min(blindLevel.value + 1, BLIND_SCHEDULE.length - 1);
        const nb = getCurrentBlind(blindLevel.value);
        tourneyLog.value.push(`⬆️ 블라인드 레벨 ${blindLevel.value + 1}: ${nb.sb}/${nb.bb}${nb.ante > 0 ? ` (앤티 ${nb.ante})` : ''}`);
        levelTimer.value = nb.dur * 60;
      }
      // Background eliminations
      if (Math.random() < 0.03) {
        const localLive = seats.value.filter(x => !x.isOut).length;
        totalRemaining.value = Math.max(localLive, totalRemaining.value - 1);
      }
    }, 1000);
  }

  function stopTimer() {
    if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
  }

  // ─── INITIALIZE ───
  function startTournament() {
    const tableSize = 9;
    const profiles = shuffle([...AI_PROFILES, ...AI_PROFILES, ...AI_PROFILES]).slice(0, tableSize - 1);
    const newSeats = profiles.map((p, i) => ({
      id: i + 1, name: p.name, chips: config.value.startChips, cards: [], emoji: p.emoji,
      style: p.style, desc: p.desc, color: p.color || '#888',
      folded: false, bet: 0, isPlayer: false, isOut: false, allIn: false, showCards: false,
    }));
    const playerSeat = {
      id: 0, name: '나', chips: config.value.startChips, cards: [], emoji: '😎',
      style: 'player', desc: '플레이어', color: '#2196f3',
      folded: false, bet: 0, isPlayer: true, isOut: false, allIn: false, showCards: true,
    };
    newSeats.splice(4, 0, playerSeat);
    seats.value = newSeats;
    totalRemaining.value = config.value.totalPlayers;
    blindLevel.value = 0;
    handNum.value = 0;
    dealerIdx.value = 0;
    tourneyOver.value = false;
    finalPlace.value = 0;
    bustMsg.value = '';
    tourneyLog.value = [];
    myBounties.value = [];
    chatBubbles.value = {};
    levelTimer.value = BLIND_SCHEDULE[0].dur * 60;
    elapsedTime.value = 0;
    showMonitor.value = false;
    screen.value = 'game';
    startTimer();
    setTimeout(() => dealHand(newSeats, 0, 0), 500);
  }

  // ─── DEAL HAND ───
  function dealHand(currentSeats, dlrIdx, blLvl) {
    const s = currentSeats || seats.value;
    const active = s.filter(x => !x.isOut);
    if (active.length <= 1) { endTourney(active[0]?.isPlayer ? 1 : totalRemaining.value); return; }

    const deck = shuffle(createDeck());
    let di = 0;
    const newSeats = s.map(seat => {
      if (seat.isOut) return { ...seat, cards: [], folded: true, bet: 0, allIn: false, showCards: false };
      const cards = [deck[di], deck[di + 1]]; di += 2;
      return { ...seat, cards, folded: false, bet: 0, allIn: false, showCards: seat.isPlayer };
    });
    deckArr = deck.slice(di);

    const bLevel = getCurrentBlind(blLvl);
    let potVal = 0;
    const liveIdxs = newSeats.map((s, i) => s.isOut ? -1 : i).filter(i => i >= 0);
    const sbIdx = liveIdxs[(liveIdxs.indexOf(dlrIdx) + 1) % liveIdxs.length];
    const bbIdx = liveIdxs[(liveIdxs.indexOf(dlrIdx) + 2) % liveIdxs.length];

    // Antes
    if (bLevel.ante > 0) {
      newSeats.forEach((s, i) => {
        if (!s.isOut) { const a = Math.min(bLevel.ante, s.chips); newSeats[i] = { ...s, chips: s.chips - a }; potVal += a; }
      });
    }
    // SB
    const sbAmt = Math.min(bLevel.sb, newSeats[sbIdx].chips);
    newSeats[sbIdx] = { ...newSeats[sbIdx], chips: newSeats[sbIdx].chips - sbAmt, bet: sbAmt }; potVal += sbAmt;
    // BB
    const bbAmt = Math.min(bLevel.bb, newSeats[bbIdx].chips);
    newSeats[bbIdx] = { ...newSeats[bbIdx], chips: newSeats[bbIdx].chips - bbAmt, bet: bbAmt }; potVal += bbAmt;

    const utgIdx = liveIdxs[(liveIdxs.indexOf(bbIdx) + 1) % liveIdxs.length];

    seats.value = newSeats;
    community.value = [];
    pot.value = potVal;
    stage.value = 'preflop';
    currentBetLevel.value = bLevel.bb;
    showdown.value = false;
    gameOver.value = false;
    handResults.value = null;
    bustMsg.value = '';
    foldReveals.value = [];
    termTip.value = null;
    chatBubbles.value = {};
    handNum.value++;
    dealerIdx.value = dlrIdx;

    // Coaching
    const ps = newSeats.find(s => s.isPlayer);
    if (ps && !ps.isOut) {
      const posOffset = liveIdxs.indexOf(newSeats.indexOf(ps));
      const dlrOffset = liveIdxs.indexOf(dlrIdx);
      const relPos = (posOffset - dlrOffset + liveIdxs.length) % liveIdxs.length;
      coachTips.value = getCoach(ps.cards, [], 'preflop', potVal, ps.chips, relPos, bLevel, totalRemaining.value, paidSlots.value, liveIdxs.length, 0);
    }

    setTimeout(() => processAction(newSeats, utgIdx, potVal, bLevel.bb, 'preflop', [], dlrIdx, blLvl), 800);
  }

  // ─── ACTION LOOP ───
  function processAction(curSeats, seatIdx, curPot, curBetLvl, curStage, curComm, dlrIdx, blLvl) {
    const s = [...curSeats];
    const live = s.map((x, i) => (!x.isOut && !x.folded && !x.allIn) ? i : -1).filter(i => i >= 0);

    if (live.length <= 1) {
      const notFolded = s.filter(x => !x.isOut && !x.folded);
      if (notFolded.length <= 1) { resolveHand(s, curPot, curComm, dlrIdx, blLvl); return; }
      if (live.length <= 1) { runOutBoard(s, curPot, curComm, dlrIdx, blLvl); return; }
    }

    const seat = s[seatIdx];
    if (!seat || seat.isOut || seat.folded || seat.allIn) {
      const nextIdx = findNextActor(s, seatIdx);
      if (nextIdx === null || checkRoundComplete(s, curBetLvl)) {
        advanceStage(s, curPot, curBetLvl, curStage, curComm, dlrIdx, blLvl); return;
      }
      processAction(s, nextIdx, curPot, curBetLvl, curStage, curComm, dlrIdx, blLvl); return;
    }

    if (seat.isPlayer) {
      seats.value = s; pot.value = curPot; currentBetLevel.value = curBetLvl; actIdx.value = seatIdx;
      isPlayerTurn.value = true;
      // 턴 타이머 시작
      startTurnTimer(s, seatIdx, curPot, curBetLvl, curStage, curComm, dlrIdx, blLvl);
      const bLevel = getCurrentBlind(blLvl);
      raiseAmt.value = Math.min(Math.max(curBetLvl * 2, bLevel.bb * 2), seat.chips);
      // Update coaching
      const liveIdxs = s.map((x, i) => x.isOut ? -1 : i).filter(i => i >= 0);
      const posOff = liveIdxs.indexOf(seatIdx);
      const dlrOff = liveIdxs.indexOf(dlrIdx);
      const relPos = (posOff - dlrOff + liveIdxs.length) % liveIdxs.length;
      const playerToCall = Math.max(0, curBetLvl - seat.bet);
      coachTips.value = getCoach(seat.cards, curComm, curStage, curPot, seat.chips, relPos, bLevel, totalRemaining.value, paidSlots.value, liveIdxs.length, playerToCall);
      return;
    }

    // AI action
    actIdx.value = seatIdx; isPlayerTurn.value = false;
    const toCall = Math.max(0, curBetLvl - seat.bet);
    const bLevel = getCurrentBlind(blLvl);
    const dec = aiAct(seat.cards, curComm, curPot, toCall, seat.chips, seat.style, bLevel, live.length);
    let newBetLvl = curBetLvl, newPot = curPot;

    if (dec.action === 'fold') {
      s[seatIdx] = { ...s[seatIdx], folded: true, showCards: true };
      const liveIdxs3 = s.map((x, j) => x.isOut ? -1 : j).filter(j => j >= 0);
      const dlrOff3 = liveIdxs3.indexOf(dlrIdx);
      const sOff3 = liveIdxs3.indexOf(seatIdx);
      const relP3 = (sOff3 - dlrOff3 + liveIdxs3.length) % liveIdxs3.length;
      const reason = getFoldReason(seat.cards, curComm, toCall, seat.chips, seat.style, relP3, bLevel.bb);
      foldReveals.value = [...foldReveals.value, { name: seat.name, emoji: seat.emoji, cards: [...seat.cards], reason, posLabel: POSITION_NAMES[relP3] || '?' }];
      lastAction.value = `${seat.emoji} ${seat.name}: 폴드`;
      termTip.value = TERMS.fold;
      const chat = getChat('fold', seat.style);
      if (chat) chatBubbles.value = { ...chatBubbles.value, [seatIdx]: { text: chat, time: Date.now() } };
    } else if (dec.action === 'check') {
      lastAction.value = `${seat.emoji} ${seat.name}: 체크`;
      termTip.value = TERMS.check;
      const chat = getChat('check', seat.style);
      if (chat) chatBubbles.value = { ...chatBubbles.value, [seatIdx]: { text: chat, time: Date.now() } };
    } else if (dec.action === 'call') {
      const cost = Math.min(toCall, seat.chips);
      s[seatIdx] = { ...s[seatIdx], chips: seat.chips - cost, bet: seat.bet + cost, allIn: seat.chips - cost <= 0 };
      newPot += cost;
      lastAction.value = `${seat.emoji} ${seat.name}: 콜 (${cost})`;
      termTip.value = TERMS.call;
      const chat = getChat('call', seat.style);
      if (chat) chatBubbles.value = { ...chatBubbles.value, [seatIdx]: { text: chat, time: Date.now() } };
    } else if (dec.action === 'raise') {
      const amt = Math.floor(Math.min(dec.amount || curBetLvl * 2, seat.chips));
      const totalCost = Math.floor(Math.min(toCall + amt, seat.chips));
      s[seatIdx] = { ...s[seatIdx], chips: seat.chips - totalCost, bet: seat.bet + totalCost, allIn: seat.chips - totalCost <= 0 };
      newBetLvl = s[seatIdx].bet; newPot += totalCost;
      lastAction.value = `${seat.emoji} ${seat.name}: 레이즈 → ${newBetLvl}`;
      termTip.value = TERMS.raise;
      const chat = getChat('raise', seat.style);
      if (chat) chatBubbles.value = { ...chatBubbles.value, [seatIdx]: { text: chat, time: Date.now() } };
      s.forEach((other, oi) => { if (!other.isOut && !other.folded && oi !== seatIdx && Math.random() < 0.2) { const rc = getChat('react_raise'); if (rc) chatBubbles.value = { ...chatBubbles.value, [oi]: { text: rc, time: Date.now() + 200 } }; } });
    } else if (dec.action === 'allin') {
      const cost = seat.chips;
      s[seatIdx] = { ...s[seatIdx], chips: 0, bet: seat.bet + cost, allIn: true };
      if (s[seatIdx].bet > newBetLvl) newBetLvl = s[seatIdx].bet;
      newPot += cost;
      lastAction.value = `${seat.emoji} ${seat.name}: 올인! (${cost})`;
      termTip.value = TERMS.allin;
      const chat = getChat('allin', seat.style);
      if (chat) chatBubbles.value = { ...chatBubbles.value, [seatIdx]: { text: chat, time: Date.now() } };
      s.forEach((other, oi) => { if (!other.isOut && !other.folded && oi !== seatIdx && Math.random() < 0.35) { const rc = getChat('react_allin'); if (rc) chatBubbles.value = { ...chatBubbles.value, [oi]: { text: rc, time: Date.now() + 300 } }; } });
    }

    seats.value = [...s]; pot.value = newPot; currentBetLevel.value = newBetLvl;

    const nextIdx = findNextActor(s, seatIdx);
    actionTimeout = setTimeout(() => {
      if (nextIdx === null || checkRoundComplete(s, newBetLvl)) {
        advanceStage(s, newPot, newBetLvl, curStage, curComm, dlrIdx, blLvl);
      } else {
        processAction(s, nextIdx, newPot, newBetLvl, curStage, curComm, dlrIdx, blLvl);
      }
    }, 1200); // AI 턴 간격 (이전 600ms → 1200ms)
  }

  function startTurnTimer(s, seatIdx, curPot, curBetLvl, curStage, curComm, dlrIdx, blLvl) {
    stopTurnTimer();
    turnTimer.value = turnTimerMax.value;
    turnTimerInterval = setInterval(() => {
      turnTimer.value--;
      if (turnTimer.value <= 0) {
        stopTurnTimer();
        // 시간초과: 체크 가능하면 체크, 아니면 폴드
        const seat = s[seatIdx];
        const toCall = Math.max(0, curBetLvl - (seat?.bet || 0));
        if (toCall === 0) {
          doPlayerAction('check');
        } else {
          doPlayerAction('fold');
        }
      }
    }, 1000);
  }

  function stopTurnTimer() {
    if (turnTimerInterval) { clearInterval(turnTimerInterval); turnTimerInterval = null; }
    turnTimer.value = 0;
  }

  function findNextActor(s, fromIdx) {
    const total = s.length;
    for (let i = 1; i < total; i++) {
      const idx = (fromIdx + i) % total;
      if (!s[idx].isOut && !s[idx].folded && !s[idx].allIn) return idx;
    }
    return null;
  }

  function checkRoundComplete(s, betLvl) {
    const active = s.filter(x => !x.isOut && !x.folded && !x.allIn);
    return active.every(x => x.bet === betLvl);
  }

  // ─── ADVANCE STAGE ───
  function advanceStage(s, curPot, betLvl, curStage, curComm, dlrIdx, blLvl) {
    const newSeats = s.map(x => ({ ...x, bet: 0 }));
    const stages = ['preflop', 'flop', 'turn', 'river'];
    const si = stages.indexOf(curStage);

    const notFolded = newSeats.filter(x => !x.isOut && !x.folded);
    if (notFolded.length <= 1) { resolveHand(newSeats, curPot, curComm, dlrIdx, blLvl); return; }
    const canAct = notFolded.filter(x => !x.allIn);
    if (canAct.length <= 1 && si < 3) { runOutBoard(newSeats, curPot, curComm, dlrIdx, blLvl); return; }
    if (si >= 3) { resolveHand(newSeats, curPot, curComm, dlrIdx, blLvl); return; }

    let newComm = [...curComm];
    const d = [...deckArr];
    if (si === 0) { newComm = [d[0], d[1], d[2]]; deckArr = d.slice(3); }
    else { newComm.push(d[0]); deckArr = d.slice(1); }

    const nextStage = stages[si + 1];
    community.value = newComm; stage.value = nextStage; currentBetLevel.value = 0;
    seats.value = newSeats;

    const liveIdxs = newSeats.map((x, i) => (!x.isOut && !x.folded && !x.allIn) ? i : -1).filter(i => i >= 0);
    if (liveIdxs.length === 0) { resolveHand(newSeats, curPot, newComm, dlrIdx, blLvl); return; }
    const firstAct = liveIdxs.find(i => i > dlrIdx) || liveIdxs[0];

    setTimeout(() => processAction(newSeats, firstAct, curPot, 0, nextStage, newComm, dlrIdx, blLvl), 1000); // 카드 오픈 후 잠시 대기
  }

  function runOutBoard(s, curPot, curComm, dlrIdx, blLvl) {
    // 모든 참여자 카드 오픈 (올인 쇼다운)
    const newSeats = s.map(x => (!x.isOut && !x.folded) ? { ...x, showCards: true } : x);
    seats.value = newSeats;
    showdown.value = true;

    let comm = [...curComm];
    const d = [...deckArr];
    const cardsNeeded = 5 - comm.length;

    // 카드 하나씩 딜레이로 보여주기 (1.5초 간격)
    let delay = 500;
    for (let i = 0; i < cardsNeeded; i++) {
      const card = d.shift();
      setTimeout(() => {
        comm.push(card);
        community.value = [...comm];
        if (comm.length === 3) stage.value = 'flop';
        else if (comm.length === 4) stage.value = 'turn';
        else if (comm.length === 5) stage.value = 'river';
      }, delay);
      delay += 1500;
    }

    deckArr = d;
    // 마지막 카드 후 2초 뒤에 결과
    setTimeout(() => resolveHand(newSeats, curPot, comm, dlrIdx, blLvl), delay + 2000);
  }

  // ─── RESOLVE HAND ───
  function resolveHand(s, curPot, comm, dlrIdx, blLvl) {
    const newSeats = [...s.map(x => ({ ...x, bet: 0 }))];
    const notFolded = newSeats.filter(x => !x.isOut && !x.folded);

    if (notFolded.length === 1) {
      const winner = notFolded[0];
      const wi = newSeats.findIndex(x => x.id === winner.id);
      newSeats[wi] = { ...newSeats[wi], chips: newSeats[wi].chips + curPot };
      handResults.value = { winners: [{ name: winner.name, emoji: winner.emoji, hand: '-', pot: curPot }], msg: `${winner.emoji} ${winner.name} 승리! (상대 전원 폴드)` };
    } else {
      let results = notFolded.map(p => {
        const ev = comm.length >= 3 ? evalHand([...p.cards, ...comm]) : { rank: 0, name: '-', score: 0 };
        return { ...p, eval: ev };
      }).sort((a, b) => b.eval.score - a.eval.score);

      const winner = results[0];
      const wi = newSeats.findIndex(x => x.id === winner.id);
      newSeats[wi] = { ...newSeats[wi], chips: newSeats[wi].chips + curPot };
      notFolded.forEach(p => { const i = newSeats.findIndex(x => x.id === p.id); newSeats[i] = { ...newSeats[i], showCards: true }; });

      handResults.value = {
        winners: [{ name: winner.name, emoji: winner.emoji, hand: winner.eval.name, pot: curPot }],
        all: results.map(r => ({ name: r.name, emoji: r.emoji, hand: r.eval.name })),
        msg: `${winner.emoji} ${winner.name} 승리! (${winner.eval.name})`,
      };
    }

    // Eliminate busted + bounty
    const busted = [];
    const winnerSeat = notFolded.length === 1 ? notFolded[0] :
      notFolded.length > 0 ? notFolded.sort((a, b) => (evalHand([...b.cards, ...comm]).score || 0) - (evalHand([...a.cards, ...comm]).score || 0))[0] : null;

    newSeats.forEach((seat, i) => {
      if (seat.chips <= 0 && !seat.isOut) {
        newSeats[i] = { ...seat, isOut: true, chips: 0 };
        busted.push({ ...seat, eliminatedBy: winnerSeat?.name || '?' });
        const ggChat = getChat('eliminated');
        if (ggChat) chatBubbles.value = { ...chatBubbles.value, [i]: { text: ggChat, time: Date.now() } };
      }
    });

    const bountyAmt = Math.floor(config.value.buyIn * 0.1);
    if (busted.length > 0) {
      busted.forEach(b => {
        if (winnerSeat?.isPlayer) {
          myBounties.value = [...myBounties.value, { name: b.name, emoji: b.emoji, amount: bountyAmt }];
        }
      });
    }

    // Background eliminations
    let newRemaining = totalRemaining.value - busted.length;
    const otherTables = Math.max(0, Math.floor((newRemaining - newSeats.filter(x => !x.isOut).length) / 9));
    if (otherTables > 0 && Math.random() < 0.3) {
      const otherBusts = Math.floor(Math.random() * 3) + 1;
      newRemaining = Math.max(newSeats.filter(x => !x.isOut).length, newRemaining - otherBusts);
    }
    totalRemaining.value = newRemaining;

    if (busted.length > 0) {
      const msgs = busted.map(b => {
        const bountyMsg = b.eliminatedBy === '나' ? ` 💰 바운티 $${bountyAmt} 획득!` : '';
        return `${b.emoji} ${b.name} 탈락! (${b.eliminatedBy}에게 제거)${bountyMsg}`;
      });
      bustMsg.value = msgs.join(' | ');
      tourneyLog.value = [...tourneyLog.value, ...msgs];
    }

    // Rank
    const playerSeat = newSeats.find(s => s.isPlayer);
    if (playerSeat && !playerSeat.isOut) {
      const betterStacks = newSeats.filter(s => !s.isOut && !s.isPlayer && s.chips > playerSeat.chips).length;
      const estimatedBetter = Math.floor(betterStacks / 8 * newRemaining);
      myRank.value = Math.max(1, estimatedBetter + 1);
    }

    // Check if player busted
    if (playerSeat && playerSeat.chips <= 0) {
      tourneyOver.value = true;
      finalPlace.value = newRemaining + busted.filter(b => b.isPlayer).length;
      stopTimer();
      screen.value = 'result';
    }

    // Refill empty seats
    const liveCount = newSeats.filter(x => !x.isOut).length;
    const tableNum = Math.ceil(newRemaining / 9);
    if (liveCount < 7 && newRemaining > liveCount) {
      const emptySlots = newSeats.map((s, i) => s.isOut ? i : -1).filter(i => i >= 0);
      const fillCount = Math.min(emptySlots.length, Math.min(2, newRemaining - liveCount));
      for (let fi = 0; fi < fillCount; fi++) {
        if (fi >= emptySlots.length) break;
        const fillIdx = emptySlots[fi];
        const avgChips = newSeats.filter(x => !x.isOut).reduce((a, b) => a + b.chips, 0) / Math.max(1, liveCount);
        const np = shuffle(AI_PROFILES)[0];
        const fromTable = Math.floor(Math.random() * (tableNum - 1)) + 1;
        const suffix = fi === 0 ? '' : '③';
        newSeats[fillIdx] = {
          id: Date.now() + fi, name: np.name + suffix,
          chips: Math.floor(avgChips * (0.5 + Math.random() * 1.0)),
          cards: [], emoji: np.emoji, style: np.style, desc: np.desc, color: np.color || '#888888',
          folded: false, bet: 0, isPlayer: false, isOut: false, allIn: false, showCards: false, fromTable,
        };
        tourneyLog.value = [...tourneyLog.value, `➕ ${np.emoji} ${np.name}${suffix} 합류 (테이블 #${fromTable}에서 이동)`];
        const greet = CHAT_LINES.greeting[Math.floor(Math.random() * CHAT_LINES.greeting.length)];
        chatBubbles.value = { ...chatBubbles.value, [fillIdx]: { text: greet, time: Date.now() } };
      }
    }

    seats.value = newSeats; showdown.value = true; gameOver.value = true; pot.value = curPot;
    const liveIdxs = newSeats.map((x, i) => x.isOut ? -1 : i).filter(i => i >= 0);
    const nextDlr = liveIdxs[(liveIdxs.indexOf(dlrIdx) + 1) % liveIdxs.length] || 0;
    dealerIdx.value = nextDlr;
  }

  function endTourney(place) {
    tourneyOver.value = true; finalPlace.value = place; stopTimer(); screen.value = 'result';
  }

  // ─── PLAYER ACTIONS ───
  function doPlayerAction(action, amount = 0) {
    if (!isPlayerTurn.value) return;
    isPlayerTurn.value = false;
    stopTurnTimer();
    const s = [...seats.value];
    const pi = s.findIndex(x => x.isPlayer);
    const seat = s[pi];
    const toCall = Math.max(0, currentBetLevel.value - seat.bet);
    let newPot = pot.value, newBetLvl = currentBetLevel.value;

    if (action === 'fold') { s[pi] = { ...seat, folded: true }; lastAction.value = '😎 나: 폴드'; }
    else if (action === 'check') { lastAction.value = '😎 나: 체크'; }
    else if (action === 'call') {
      const cost = Math.min(toCall, seat.chips);
      s[pi] = { ...seat, chips: seat.chips - cost, bet: seat.bet + cost, allIn: seat.chips - cost <= 0 };
      newPot += cost; lastAction.value = `😎 나: 콜 (${cost})`;
    } else if (action === 'raise') {
      const totalCost = Math.min(toCall + amount, seat.chips);
      s[pi] = { ...seat, chips: seat.chips - totalCost, bet: seat.bet + totalCost, allIn: seat.chips - totalCost <= 0 };
      newBetLvl = s[pi].bet; newPot += totalCost; lastAction.value = `😎 나: 레이즈 → ${newBetLvl}`;
    } else if (action === 'allin') {
      const cost = seat.chips;
      s[pi] = { ...seat, chips: 0, bet: seat.bet + cost, allIn: true };
      if (s[pi].bet > newBetLvl) newBetLvl = s[pi].bet;
      newPot += cost; lastAction.value = `😎 나: 올인! (${cost})`;
    }
    seats.value = s; pot.value = newPot; currentBetLevel.value = newBetLvl;

    const nextIdx = findNextActor(s, pi);
    setTimeout(() => {
      if (nextIdx === null || checkRoundComplete(s, newBetLvl)) {
        advanceStage(s, newPot, newBetLvl, stage.value, community.value, dealerIdx.value, blindLevel.value);
      } else {
        processAction(s, nextIdx, newPot, newBetLvl, stage.value, community.value, dealerIdx.value, blindLevel.value);
      }
    }, 400);
  }

  function nextHand() { dealHand(null, dealerIdx.value, blindLevel.value); }

  function resetTournament() { stopTimer(); screen.value = 'setup'; }

  function cleanup() { stopTimer(); if (actionTimeout) clearTimeout(actionTimeout); }

  return {
    // State
    screen, config, blindLevel, totalRemaining, myRank, handNum, tourneyLog, myBounties, chatBubbles,
    seats, dealerIdx, community, pot, stage, actIdx, lastAction, isPlayerTurn, gameOver, showdown,
    raiseAmt, coachTips, showCoach, showMonitor, bustMsg, handResults, tourneyOver, finalPlace,
    currentBetLevel, levelTimer, elapsedTime, foldReveals, termTip, turnTimer, turnTimerMax,
    // Computed
    bl, nextBl, paidSlots, prizePool, prizes,
    // Methods
    startTournament, doPlayerAction, nextHand, resetTournament, cleanup,
    // Constants
    BLIND_SCHEDULE, POSITION_NAMES, TERMS,
    // Utils
    fmtTime, fmtElapsed,
  };
}
