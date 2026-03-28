<template>
  <div class="flex flex-col select-none"
    style="height:100dvh;overflow:hidden;background:linear-gradient(160deg,#0e3d5a 0%,#1a6080 60%,#0a2e45 100%);font-family:'Malgun Gothic',sans-serif;">

    <!-- ── 상단 헤더 ── -->
    <div class="flex items-center px-3 py-1.5 flex-shrink-0 gap-2"
      style="background:rgba(0,0,0,.6);border-bottom:1px solid rgba(255,255,255,.1);">
      <button @click="$router.back()" class="text-white/50 hover:text-white text-sm w-7">◀</button>
      <span class="text-yellow-300 font-black tracking-wider text-sm">맞고</span>
      <span class="text-white/30 text-xs">vs 컴퓨터</span>
      <span v-if="phase==='my_turn'" class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold" style="background:#27ae60;color:#fff;">내 차례</span>
      <span v-else-if="phase==='bot_turn'" class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold animate-pulse" style="background:#e74c3c;color:#fff;">컴 생각중...</span>
      <div class="ml-auto flex items-center gap-2">
        <div class="flex items-center gap-1 px-2 py-0.5 rounded text-[10px]" style="background:rgba(0,0,0,.5)">
          <div style="width:9px;height:13px;background:#c0392b;border:1px solid #e74c3c;border-radius:2px;display:inline-block;"></div>
          <span class="text-white font-bold">패더미 {{ deckLeft }}장</span>
        </div>
        <button @click="initGame" class="text-[11px] font-bold px-2.5 py-1 rounded" style="background:linear-gradient(180deg,#f39c12,#e67e22);color:#fff;">새게임</button>
      </div>
    </div>

    <!-- ── 딜 화면 ── -->
    <div v-if="phase==='dealing'" class="flex-1 flex flex-col items-center justify-center gap-5">
      <div class="text-yellow-300 text-xl font-black animate-pulse">패를 나눠드립니다...</div>
      <div class="relative" style="width:50px;height:70px">
        <div v-for="i in 7" :key="i" class="absolute rounded shadow"
          style="width:42px;height:60px;background:linear-gradient(135deg,#8b0000,#c0392b);border:1px solid #e74c3c"
          :style="{top:-(i*2)+'px',left:(i*1)+'px'}"></div>
      </div>
      <div class="w-52 space-y-1.5">
        <div v-for="({label,val,max,color}) in dealProgress" :key="label" class="flex items-center gap-2">
          <span class="text-white/50 text-xs w-14 text-right">{{ label }}</span>
          <div class="flex-1 rounded-full h-2" style="background:rgba(0,0,0,.4)">
            <div class="h-2 rounded-full transition-all duration-200" :style="{width:(val/max*100)+'%',background:color}"></div>
          </div>
          <span class="text-xs w-8 font-mono" :style="{color}">{{ val }}/{{ max }}</span>
        </div>
      </div>
    </div>

    <!-- ── 메인 게임 ── -->
    <template v-else>
      <div class="flex flex-1 min-h-0 p-1 gap-1">

        <!-- ── 좌측: 먹은 패 패널 ── -->
        <div class="flex flex-col gap-1 flex-shrink-0" style="width:108px">

          <!-- 컴퓨터 먹은 패 -->
          <div class="flex-1 rounded-lg p-1.5 overflow-y-auto min-h-0" style="background:rgba(0,0,0,.5);border:1px solid rgba(231,76,60,.4)">
            <div class="flex justify-between items-baseline mb-1">
              <span class="text-red-300 text-[10px] font-bold">컴퓨터</span>
              <span class="text-white font-black" style="font-size:15px">{{ botScore }}<span class="text-white/30 text-[8px]">점</span></span>
            </div>
            <!-- 광 -->
            <template v-if="byType(botCapture,'gwang').length">
              <div class="text-yellow-400 text-[8px] font-bold mb-0.5">광 {{ byType(botCapture,'gwang').length }}</div>
              <div class="flex flex-wrap gap-0.5 mb-1">
                <div v-for="c in byType(botCapture,'gwang')" :key="'bg'+c.id" class="rounded border border-yellow-500/40 flex flex-col items-center justify-center font-black text-white"
                  style="width:22px;height:30px;font-size:9px" :style="{background:cardGradient(c)}">
                  {{ c.month }}
                </div>
              </div>
            </template>
            <!-- 열끗 -->
            <template v-if="byType(botCapture,'yeol').length">
              <div class="text-pink-300 text-[8px] font-bold mb-0.5">열끗 {{ byType(botCapture,'yeol').length }}</div>
              <div class="flex flex-wrap gap-0.5 mb-1">
                <div v-for="c in byType(botCapture,'yeol')" :key="'by'+c.id" class="rounded flex flex-col items-center justify-center font-black text-white"
                  style="width:22px;height:30px;font-size:9px" :style="{background:cardGradient(c)}">
                  {{ c.month }}
                </div>
              </div>
            </template>
            <!-- 띠 -->
            <template v-if="byType(botCapture,'tti').length">
              <div class="text-blue-300 text-[8px] font-bold mb-0.5">띠 {{ byType(botCapture,'tti').length }}</div>
              <div class="flex flex-wrap gap-0.5 mb-1">
                <div v-for="c in byType(botCapture,'tti')" :key="'bt'+c.id" class="rounded flex flex-col items-center justify-center font-black text-white"
                  style="width:22px;height:30px;font-size:9px" :style="{background:cardGradient(c)}">
                  {{ c.month }}
                </div>
              </div>
            </template>
            <!-- 피 -->
            <div v-if="botPiTotal" class="text-gray-400 text-[8px]">피 {{ botPiTotal }}장</div>
          </div>

          <!-- 내 먹은 패 -->
          <div class="flex-1 rounded-lg p-1.5 overflow-y-auto min-h-0" style="background:rgba(0,0,0,.5);border:1px solid rgba(39,174,96,.4)">
            <div class="flex justify-between items-baseline mb-1">
              <span class="text-green-300 text-[10px] font-bold">나</span>
              <span class="text-white font-black" style="font-size:15px">{{ myScore }}<span class="text-white/30 text-[8px]">점</span></span>
            </div>
            <!-- 광 -->
            <template v-if="byType(myCapture,'gwang').length">
              <div class="text-yellow-400 text-[8px] font-bold mb-0.5">광 {{ byType(myCapture,'gwang').length }}</div>
              <div class="flex flex-wrap gap-0.5 mb-1">
                <div v-for="c in byType(myCapture,'gwang')" :key="'mg'+c.id" class="rounded border border-yellow-400/60 flex flex-col items-center justify-center font-black text-white"
                  style="width:22px;height:30px;font-size:9px" :style="{background:cardGradient(c)}">
                  {{ c.month }}
                </div>
              </div>
            </template>
            <!-- 열끗 -->
            <template v-if="byType(myCapture,'yeol').length">
              <div class="text-pink-300 text-[8px] font-bold mb-0.5">열끗 {{ byType(myCapture,'yeol').length }}</div>
              <div class="flex flex-wrap gap-0.5 mb-1">
                <div v-for="c in byType(myCapture,'yeol')" :key="'my'+c.id" class="rounded flex flex-col items-center justify-center font-black text-white"
                  style="width:22px;height:30px;font-size:9px" :style="{background:cardGradient(c)}">
                  {{ c.month }}
                </div>
              </div>
            </template>
            <!-- 띠 -->
            <template v-if="byType(myCapture,'tti').length">
              <div class="text-blue-300 text-[8px] font-bold mb-0.5">띠 {{ byType(myCapture,'tti').length }}</div>
              <div class="flex flex-wrap gap-0.5 mb-1">
                <div v-for="c in byType(myCapture,'tti')" :key="'mt'+c.id" class="rounded flex flex-col items-center justify-center font-black text-white"
                  style="width:22px;height:30px;font-size:9px" :style="{background:cardGradient(c)}">
                  {{ c.month }}
                </div>
              </div>
            </template>
            <!-- 피 -->
            <div v-if="myPiTotal" class="text-gray-400 text-[8px]">피 {{ myPiTotal }}장</div>
            <!-- 고 -->
            <div v-if="goCount" class="mt-1 text-yellow-400 font-black text-xs text-center">{{ goCount }}고!</div>
            <!-- 족보 -->
            <div v-for="y in activeYaku" :key="y" class="text-yellow-300 font-bold text-center animate-pulse" style="font-size:9px">✨ {{ y }}</div>
          </div>
        </div>

        <!-- ── 중앙: 게임판 ── -->
        <div class="flex-1 flex flex-col min-w-0 gap-1">

          <!-- 컴퓨터 패 (상단, 뒤집힘) -->
          <div class="flex-shrink-0">
            <div class="text-red-300/40 mb-0.5 px-1" style="font-size:9px">컴퓨터 패 ({{ botHand.length }}장)</div>
            <div class="flex gap-0.5 flex-wrap">
              <div v-for="(c,i) in botHand" :key="'bot'+i" class="rounded shadow"
                style="width:32px;height:46px;background:linear-gradient(135deg,#8b0000,#c0392b);border:1px solid #c0392b;flex-shrink:0">
              </div>
            </div>
          </div>

          <!-- 바닥패 (핵심 게임 영역) -->
          <div class="flex-1 relative rounded-xl p-2 min-h-0"
            style="background:radial-gradient(ellipse at center,#1a6080 0%,#0d3b59 100%);border:2px solid rgba(255,255,255,.12);box-shadow:inset 0 0 30px rgba(0,0,0,.5)">

            <div class="flex gap-2 h-full">
              <!-- 패더미 (쌓인 카드) -->
              <div class="flex-shrink-0 flex flex-col items-center" style="padding-top:8px">
                <div class="text-white/25 mb-1" style="font-size:8px">패더미</div>
                <div class="relative" style="width:36px;height:54px">
                  <div v-for="i in Math.min(deckLeft,8)" :key="'dk'+i" class="absolute rounded shadow"
                    style="width:32px;height:46px;background:linear-gradient(135deg,#8b0000,#c0392b);border:1px solid #c0392b"
                    :style="{top:-(i*2.2)+'px',left:(i*.7)+'px'}">
                  </div>
                </div>
                <div class="text-white/50 font-bold mt-1" style="font-size:9px">{{ deckLeft }}장</div>
              </div>

              <!-- 바닥 카드들 -->
              <div class="flex-1 min-w-0">
                <div class="text-white/25 mb-1" style="font-size:8px">바닥 ({{ tableCards.length }}장)</div>
                <div class="flex gap-1 flex-wrap">
                  <TransitionGroup name="card-slide">
                    <div v-for="c in tableCards" :key="'t'+c.id"
                      class="rounded-lg shadow-lg flex flex-col items-center justify-center font-bold border-2 transition-all duration-200"
                      :class="selected?.month===c.month
                        ? 'border-yellow-400 scale-110 shadow-yellow-400/60 shadow-xl'
                        : 'border-white/10'"
                      style="width:38px;height:54px;flex-shrink:0"
                      :style="{background:cardGradient(c)}">
                      <span class="font-black text-white drop-shadow leading-none" style="font-size:11px">{{ c.month }}</span>
                      <span class="text-white/70 leading-none" style="font-size:7px">{{ typeLabel(c.type) }}</span>
                    </div>
                  </TransitionGroup>
                </div>
              </div>
            </div>

            <!-- ── Go/Stop 다이얼로그 ── -->
            <div v-if="phase==='go_stop'"
              class="absolute inset-0 flex items-center justify-center rounded-xl"
              style="background:rgba(0,0,0,.82);backdrop-filter:blur(4px)">
              <div class="rounded-2xl p-5 text-center shadow-2xl"
                style="background:linear-gradient(160deg,#1c3f5e,#0d2438);border:2px solid rgba(255,193,7,.55);min-width:210px;max-width:260px">
                <div class="text-4xl mb-1">🎯</div>
                <div class="text-yellow-300 font-black mb-0.5" style="font-size:17px">{{ goCount + 1 }}고 하시겠습니까?</div>
                <div class="text-white/40 text-xs mb-4">현재 <span class="text-yellow-300 font-bold">{{ myScore }}점</span> 달성!</div>
                <div class="flex gap-3 justify-center mb-2">
                  <button @click="chooseGo"
                    class="font-black rounded-xl active:scale-95 transition-transform"
                    style="padding:10px 28px;font-size:18px;background:linear-gradient(180deg,#2ecc71,#27ae60);color:#fff;border:2px solid #58d68d;min-width:82px">
                    고!
                  </button>
                  <button @click="chooseStop"
                    class="font-black rounded-xl active:scale-95 transition-transform"
                    style="padding:10px 28px;font-size:18px;background:linear-gradient(180deg,#e74c3c,#c0392b);color:#fff;border:2px solid #f1948a;min-width:82px">
                    스톱
                  </button>
                </div>
                <div class="text-white/25" style="font-size:10px">( 한 번 더 하기 / 끝내기 )</div>
              </div>
            </div>

            <!-- ── 결과 오버레이 ── -->
            <div v-if="phase==='result'"
              class="absolute inset-0 flex items-center justify-center rounded-xl"
              style="background:rgba(0,0,0,.88);backdrop-filter:blur(4px)">
              <div class="text-center px-6">
                <div class="text-5xl mb-2">{{ myScore>botScore?'🏆':myScore===botScore?'🤝':'😢' }}</div>
                <div class="font-black mb-3" style="font-size:22px"
                  :class="myScore>botScore?'text-yellow-300':myScore===botScore?'text-gray-300':'text-red-400'">
                  {{ myScore>botScore?'승리!':myScore===botScore?'무승부':'패배...' }}
                </div>
                <div class="flex gap-8 justify-center mb-4">
                  <div>
                    <div class="text-white/40" style="font-size:10px">나</div>
                    <div class="text-green-400 font-black" style="font-size:28px">{{ myScore }}</div>
                    <div class="text-white/30" style="font-size:10px">점</div>
                  </div>
                  <div class="text-white/20 font-black self-center" style="font-size:20px">vs</div>
                  <div>
                    <div class="text-white/40" style="font-size:10px">컴퓨터</div>
                    <div class="text-red-400 font-black" style="font-size:28px">{{ botScore }}</div>
                    <div class="text-white/30" style="font-size:10px">점</div>
                  </div>
                </div>
                <button @click="initGame" class="w-full font-black rounded-xl" style="padding:10px;font-size:15px;background:linear-gradient(180deg,#27ae60,#1e8449);color:#fff;border:2px solid #58d68d">
                  다시하기
                </button>
              </div>
            </div>
          </div>

          <!-- 상태 힌트 -->
          <div v-if="statusMsg && phase!=='result' && phase!=='go_stop'" class="text-center flex-shrink-0">
            <span class="px-3 py-0.5 rounded-full" style="background:rgba(0,0,0,.5);color:rgba(255,255,255,.6);font-size:10px">{{ statusMsg }}</span>
          </div>

          <!-- 내 패 (하단) -->
          <div class="flex-shrink-0">
            <div class="text-green-300/40 mb-0.5 px-1" style="font-size:9px">
              내 패 ({{ myHand.length }}장) — <span class="text-yellow-300/40">클릭=선택 · 더블클릭=내기</span>
            </div>
            <div class="flex gap-0.5 flex-wrap">
              <TransitionGroup name="card-pop">
                <div v-for="c in myHand" :key="'h'+c.id"
                  @click="onCardClick(c)"
                  @dblclick.prevent="onCardDblClick(c)"
                  class="rounded-lg shadow-lg flex flex-col items-center justify-center font-bold cursor-pointer border-2 active:scale-90 transition-all duration-150"
                  :class="selected?.id===c.id
                    ? 'border-yellow-400 -translate-y-4 shadow-yellow-300/70 shadow-xl z-10'
                    : 'border-white/10 hover:border-white/40 hover:-translate-y-2'"
                  style="width:38px;height:54px;flex-shrink:0"
                  :style="{background:cardGradient(c)}">
                  <span class="font-black text-white drop-shadow leading-none" style="font-size:11px">{{ c.month }}</span>
                  <span class="text-white/70 leading-none" style="font-size:7px">{{ typeLabel(c.type) }}</span>
                  <span v-if="selected?.id===c.id" class="text-yellow-300 animate-pulse leading-none" style="font-size:6px">▲내기</span>
                </div>
              </TransitionGroup>
            </div>
          </div>
        </div>

        <!-- ── 우측: 플레이어 정보 ── -->
        <div class="flex flex-col gap-1 flex-shrink-0" style="width:82px">
          <!-- 컴퓨터 -->
          <div class="rounded-lg p-2 text-center flex-shrink-0" style="background:rgba(0,0,0,.5);border:1px solid rgba(231,76,60,.4)">
            <div style="font-size:28px" class="mb-0.5">🤖</div>
            <div class="text-red-300 font-bold" style="font-size:9px">컴퓨터</div>
            <div class="text-white font-black leading-none" style="font-size:18px">{{ botScore }}<span class="text-white/30" style="font-size:9px">점</span></div>
            <div class="text-white/25" style="font-size:8px">패 {{ botHand.length }}장</div>
            <div class="text-white/25" style="font-size:8px">먹은 {{ botCapture.length }}장</div>
          </div>

          <!-- 중간 -->
          <div class="flex-1 flex flex-col items-center justify-center rounded-lg gap-1" style="background:rgba(0,0,0,.3)">
            <div v-if="goCount" class="text-yellow-300 font-black" style="font-size:20px">{{ goCount }}고!</div>
            <div class="text-white/25 text-center leading-tight" style="font-size:9px">{{ phaseLabel }}</div>
          </div>

          <!-- 나 -->
          <div class="rounded-lg p-2 text-center flex-shrink-0" style="background:rgba(0,0,0,.5);border:1px solid rgba(39,174,96,.4)">
            <div style="font-size:28px" class="mb-0.5">👤</div>
            <div class="text-green-300 font-bold" style="font-size:9px">나</div>
            <div class="text-white font-black leading-none" style="font-size:18px">{{ myScore }}<span class="text-white/30" style="font-size:9px">점</span></div>
            <div class="text-white/25" style="font-size:8px">패 {{ myHand.length }}장</div>
            <div class="text-white/25" style="font-size:8px">먹은 {{ myCapture.length }}장</div>
          </div>
        </div>

      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

// ── 사운드 ─────────────────────────────────────────────────────────────────────
let _ac = null
function ac() { if(!_ac)_ac=new(window.AudioContext||window.webkitAudioContext)();return _ac }

function playSound(type) {
  try {
    const ctx = ac()
    if (type==='deal') {
      const buf=ctx.createBuffer(1,ctx.sampleRate*.05,ctx.sampleRate)
      const d=buf.getChannelData(0)
      for(let i=0;i<d.length;i++) d[i]=(Math.random()*2-1)*Math.pow(1-i/d.length,3)
      const src=ctx.createBufferSource();src.buffer=buf
      const f=ctx.createBiquadFilter();f.type='bandpass';f.frequency.value=2200
      const g=ctx.createGain();g.gain.value=0.45
      src.connect(f);f.connect(g);g.connect(ctx.destination);src.start()
    } else if (type==='capture') {
      [520,700].forEach((freq,i)=>{
        const o=ctx.createOscillator(),g=ctx.createGain()
        o.connect(g);g.connect(ctx.destination);o.type='sine';o.frequency.value=freq
        g.gain.setValueAtTime(0,ctx.currentTime+i*.07)
        g.gain.linearRampToValueAtTime(.22,ctx.currentTime+i*.07+.02)
        g.gain.exponentialRampToValueAtTime(.001,ctx.currentTime+i*.07+.14)
        o.start(ctx.currentTime+i*.07);o.stop(ctx.currentTime+i*.07+.15)
      })
    } else if (type==='select') {
      const o=ctx.createOscillator(),g=ctx.createGain()
      o.connect(g);g.connect(ctx.destination);o.type='sine';o.frequency.value=900
      g.gain.setValueAtTime(.1,ctx.currentTime);g.gain.exponentialRampToValueAtTime(.001,ctx.currentTime+.07)
      o.start();o.stop(ctx.currentTime+.07)
    } else if (type==='go') {
      [440,550,660,880].forEach((freq,i)=>{
        const o=ctx.createOscillator(),g=ctx.createGain()
        o.connect(g);g.connect(ctx.destination);o.type='triangle';o.frequency.value=freq
        g.gain.setValueAtTime(0,ctx.currentTime+i*.1)
        g.gain.linearRampToValueAtTime(.28,ctx.currentTime+i*.1+.03)
        g.gain.exponentialRampToValueAtTime(.001,ctx.currentTime+i*.1+.22)
        o.start(ctx.currentTime+i*.1);o.stop(ctx.currentTime+i*.1+.22)
      })
    } else if (type==='stop') {
      [660,440,330].forEach((freq,i)=>{
        const o=ctx.createOscillator(),g=ctx.createGain()
        o.connect(g);g.connect(ctx.destination);o.type='sawtooth';o.frequency.value=freq
        g.gain.setValueAtTime(.18,ctx.currentTime+i*.1)
        g.gain.exponentialRampToValueAtTime(.001,ctx.currentTime+i*.1+.18)
        o.start(ctx.currentTime+i*.1);o.stop(ctx.currentTime+i*.1+.18)
      })
    } else if (type==='win') {
      [523,659,784,1047,1319].forEach((freq,i)=>{
        const o=ctx.createOscillator(),g=ctx.createGain()
        o.connect(g);g.connect(ctx.destination);o.type='triangle';o.frequency.value=freq
        g.gain.setValueAtTime(0,ctx.currentTime+i*.13)
        g.gain.linearRampToValueAtTime(.22,ctx.currentTime+i*.13+.05)
        g.gain.exponentialRampToValueAtTime(.001,ctx.currentTime+i*.13+.3)
        o.start(ctx.currentTime+i*.13);o.stop(ctx.currentTime+i*.13+.3)
      })
    } else if (type==='lose') {
      [392,330,262,220].forEach((freq,i)=>{
        const o=ctx.createOscillator(),g=ctx.createGain()
        o.connect(g);g.connect(ctx.destination);o.type='sine';o.frequency.value=freq
        g.gain.setValueAtTime(.18,ctx.currentTime+i*.18)
        g.gain.exponentialRampToValueAtTime(.001,ctx.currentTime+i*.18+.22)
        o.start(ctx.currentTime+i*.18);o.stop(ctx.currentTime+i*.18+.22)
      })
    } else if (type==='yaku') {
      // 족보 완성 사운드
      [523,784,1047,784,1047,1319].forEach((freq,i)=>{
        const o=ctx.createOscillator(),g=ctx.createGain()
        o.connect(g);g.connect(ctx.destination);o.type='triangle';o.frequency.value=freq
        g.gain.setValueAtTime(0,ctx.currentTime+i*.08)
        g.gain.linearRampToValueAtTime(.2,ctx.currentTime+i*.08+.03)
        g.gain.exponentialRampToValueAtTime(.001,ctx.currentTime+i*.08+.18)
        o.start(ctx.currentTime+i*.08);o.stop(ctx.currentTime+i*.08+.2)
      })
    }
  } catch(e){}
}

// ── 화투 48장 ──────────────────────────────────────────────────────────────────
const DECK_DEF = [
  [1,'gwang',null],[1,'tti','hong'],[1,'pi',null],[1,'pi',null],
  [2,'yeol',null],[2,'tti','hong'],[2,'pi',null],[2,'pi',null],
  [3,'gwang',null],[3,'tti','hong'],[3,'pi',null],[3,'pi',null],
  [4,'yeol',null],[4,'tti','cho'],[4,'pi',null],[4,'pi',null],
  [5,'yeol',null],[5,'tti','cho'],[5,'pi',null],[5,'pi',null],
  [6,'yeol',null],[6,'tti','chung'],[6,'pi',null],[6,'pi',null],
  [7,'yeol',null],[7,'tti','cho'],[7,'pi',null],[7,'pi',null],
  [8,'gwang',null],[8,'yeol',null],[8,'pi',null],[8,'pi',null],
  [9,'yeol',null],[9,'tti','chung'],[9,'pi',null],[9,'pi',null],
  [10,'yeol',null],[10,'tti','chung'],[10,'pi',null],[10,'pi',null],
  [11,'gwang','bi'],[11,'yeol',null],[11,'tti',null],[11,'ssang',null],
  [12,'gwang',null],[12,'pi',null],[12,'pi',null],[12,'ssang',null],
]

// 월별 그라디언트
const MONTH_COLORS = {
  1:'#dc2626,#991b1b', 2:'#ea580c,#c2410c', 3:'#d97706,#92400e',
  4:'#16a34a,#14532d', 5:'#0d9488,#134e4a', 6:'#2563eb,#1e3a8a',
  7:'#7c3aed,#4c1d95', 8:'#9333ea,#581c87', 9:'#c026d3,#701a75',
  10:'#db2777,#831843', 11:'#475569,#1e293b', 12:'#1e293b,#0f172a',
}
function cardGradient(c) {
  const [a,b] = (MONTH_COLORS[c.month]||'#555,#333').split(',')
  return `linear-gradient(150deg,${a},${b})`
}
function buildDeck() { return DECK_DEF.map((d,i)=>({id:i,month:d[0],type:d[1],sub:d[2]})) }
function shuffle(a) {
  const arr=[...a]
  for(let i=arr.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[arr[i],arr[j]]=[arr[j],arr[i]]}
  return arr
}

function typeLabel(t)  { return {gwang:'광',yeol:'열끗',tti:'띠',pi:'피',ssang:'쌍피'}[t]??t }
function shortType(t)  { return {gwang:'광',yeol:'열',tti:'띠',pi:'피',ssang:'쌍'}[t]??t }

// ── 점수 계산 ──────────────────────────────────────────────────────────────────
function calcScore(cap) {
  let s=0
  const gw=cap.filter(c=>c.type==='gwang'), ye=cap.filter(c=>c.type==='yeol')
  const ti=cap.filter(c=>c.type==='tti')
  const pi=cap.reduce((n,c)=>n+(c.type==='ssang'?2:c.type==='pi'?1:0),0)
  const gn=gw.length, hasBi=gw.some(c=>c.sub==='bi')
  if(gn>=3) s+=gn===5?15:gn===4?4:hasBi?2:3
  if(ye.length>=5) s+=1+(ye.length-5)
  const gm=ye.map(c=>c.month)
  if([5,7,9].every(m=>gm.includes(m))) s+=5
  if(ti.length>=5) s+=1+(ti.length-5)
  const tm=ti.map(c=>c.month)
  if([1,2,3].every(m=>tm.includes(m))) s+=3
  if([6,9,10].every(m=>tm.includes(m))) s+=3
  if([4,5,7].every(m=>tm.includes(m))) s+=3
  if(pi>=10) s+=1+(pi-10)
  return s
}

// ── 상태 ──────────────────────────────────────────────────────────────────────
const deckPile   = ref([])
const myHand     = ref([])
const botHand    = ref([])
const tableCards = ref([])
const myCapture  = ref([])
const botCapture = ref([])
const selected   = ref(null)
const phase      = ref('dealing')
const statusMsg  = ref('')
const goCount    = ref(0)
const myScore    = ref(0)
const botScore   = ref(0)

const deckLeft   = computed(()=>deckPile.value.length)
const myPiTotal  = computed(()=>myCapture.value.reduce((n,c)=>n+(c.type==='ssang'?2:c.type==='pi'?1:0),0))
const botPiTotal = computed(()=>botCapture.value.reduce((n,c)=>n+(c.type==='ssang'?2:c.type==='pi'?1:0),0))

function byType(cap, type) { return cap.filter(c=>c.type===type) }

const phaseLabel = computed(()=>({
  my_turn:'내 차례', bot_turn:'컴퓨터 생각 중...', go_stop:'고/스톱?', result:'게임 종료'
}[phase.value]??''))

const dealProgress = computed(()=>[
  {label:'컴퓨터', val:botHand.value.length,   max:10, color:'#f97316'},
  {label:'나',     val:myHand.value.length,    max:10, color:'#4ade80'},
  {label:'바닥',   val:tableCards.value.length, max:8,  color:'#60a5fa'},
])

// ── 족보 체크 ─────────────────────────────────────────────────────────────────
let prevYaku = []
const activeYaku = computed(()=>{
  const yaku=[]
  const cap=myCapture.value
  const gw=cap.filter(c=>c.type==='gwang')
  const ye=cap.filter(c=>c.type==='yeol')
  const ti=cap.filter(c=>c.type==='tti')
  const gn=gw.length
  if(gn>=5) yaku.push('오광')
  else if(gn>=4) yaku.push('사광')
  else if(gn>=3) yaku.push(gw.some(c=>c.sub==='bi')?'비광':'삼광')
  const gm=ye.map(c=>c.month)
  if([5,7,9].every(m=>gm.includes(m))) yaku.push('고도리')
  if(ye.length>=5) yaku.push('열끗')
  const tm=ti.map(c=>c.month)
  if([1,2,3].every(m=>tm.includes(m))) yaku.push('홍단')
  if([6,9,10].every(m=>tm.includes(m))) yaku.push('청단')
  if([4,5,7].every(m=>tm.includes(m))) yaku.push('초단')
  if(ti.length>=5) yaku.push('띠')
  if(myPiTotal.value>=10) yaku.push('피')
  // 새 족보 완성 시 사운드
  const newYaku=yaku.filter(y=>!prevYaku.includes(y))
  if(newYaku.length) { playSound('yaku'); prevYaku=[...yaku] }
  return yaku
})

// ── 더블클릭 처리 ─────────────────────────────────────────────────────────────
let clickTimer = null
function onCardClick(card) {
  if(phase.value!=='my_turn') return
  if(clickTimer) return
  clickTimer=setTimeout(()=>{
    clickTimer=null
    if(selected.value?.id===card.id){ selected.value=null; statusMsg.value='카드를 선택하세요.' }
    else { selected.value=card; playSound('select'); statusMsg.value=`${card.month}월 ${typeLabel(card.type)} 선택 — 더블클릭으로 내기` }
  },220)
}
function onCardDblClick(card) {
  if(phase.value!=='my_turn') return
  if(clickTimer){clearTimeout(clickTimer);clickTimer=null}
  selected.value=card
  confirmPlay()
}

// ── 딜 애니메이션 ─────────────────────────────────────────────────────────────
async function dealAnimation(deck) {
  phase.value='dealing'
  myHand.value=[];botHand.value=[];tableCards.value=[]
  prevYaku=[]
  for(let i=0;i<10;i++){ botHand.value.push(deck.splice(0,1)[0]); playSound('deal'); await sleep(110) }
  for(let i=0;i<10;i++){ myHand.value.push(deck.splice(0,1)[0]);  playSound('deal'); await sleep(110) }
  for(let i=0;i<8;i++){  tableCards.value.push(deck.splice(0,1)[0]); playSound('deal'); await sleep(110) }
  deckPile.value=deck
  await sleep(300)
  phase.value='my_turn'
  statusMsg.value='카드를 선택하세요.'
}
const sleep=(ms)=>new Promise(r=>setTimeout(r,ms))

async function initGame() {
  if(clickTimer){clearTimeout(clickTimer);clickTimer=null}
  selected.value=null;myCapture.value=[];botCapture.value=[]
  goCount.value=0;myScore.value=0;botScore.value=0;statusMsg.value=''
  prevYaku=[]
  await dealAnimation(shuffle(buildDeck()))
}

// ── 캡처 로직 ─────────────────────────────────────────────────────────────────
function doCapture(card, capture) {
  const matches=tableCards.value.filter(t=>t.month===card.month)
  if(!matches.length){ tableCards.value.push(card) }
  else {
    matches.forEach(m=>{ tableCards.value.splice(tableCards.value.findIndex(t=>t.id===m.id),1); capture.push(m) })
    capture.push(card); playSound('capture')
  }
}
function processPlay(card, hand, capture) {
  hand.splice(hand.findIndex(c=>c.id===card.id),1)
  doCapture(card,capture)
  if(deckPile.value.length>0) doCapture(deckPile.value.splice(0,1)[0],capture)
}

// ── 내 차례 ───────────────────────────────────────────────────────────────────
async function confirmPlay() {
  if(!selected.value||phase.value!=='my_turn') return
  const card=selected.value; selected.value=null; statusMsg.value=''
  processPlay(card,myHand.value,myCapture.value)
  myScore.value=calcScore(myCapture.value)
  if(myScore.value>=3){ playSound('go'); phase.value='go_stop'; statusMsg.value=`🎉 ${myScore.value}점 달성!`; return }
  if(checkEnd()) return
  phase.value='bot_turn'; await botTurn()
}

// ── 봇 ────────────────────────────────────────────────────────────────────────
async function botTurn() {
  await sleep(850)
  if(!botHand.value.length){endGame();return}
  const card=botHand.value.find(c=>tableCards.value.some(t=>t.month===c.month))
    ??botHand.value[Math.floor(Math.random()*botHand.value.length)]
  processPlay(card,botHand.value,botCapture.value)
  botScore.value=calcScore(botCapture.value)
  if(botScore.value>=5||(botScore.value>=3&&Math.random()>.55)){
    playSound('stop'); statusMsg.value='컴퓨터가 스톱!'
    await sleep(700); endGame(); return
  }
  if(checkEnd()) return
  statusMsg.value='내 차례입니다!'; phase.value='my_turn'
}

function checkEnd() {
  if(myHand.value.length===0&&deckPile.value.length===0){endGame();return true}
  return false
}
function endGame() {
  myScore.value=calcScore(myCapture.value)
  botScore.value=calcScore(botCapture.value)
  if(goCount.value>0) myScore.value=Math.floor(myScore.value*(1+goCount.value*.15))
  phase.value='result'; selected.value=null
  playSound(myScore.value>=botScore.value?'win':'lose')
}
async function chooseGo() {
  goCount.value++; playSound('go'); statusMsg.value=`${goCount.value}고!`
  if(checkEnd()) return; phase.value='bot_turn'; await botTurn()
}
function chooseStop() { playSound('stop'); endGame() }

onMounted(initGame)
onUnmounted(()=>{ if(clickTimer){clearTimeout(clickTimer);clickTimer=null} })
</script>

<style scoped>
.card-pop-enter-active  { animation: cardPop .2s cubic-bezier(.34,1.56,.64,1); }
.card-pop-leave-active  { animation: cardFly .16s ease-in forwards; }
@keyframes cardPop { from{transform:scale(0) rotate(-10deg);opacity:0} to{transform:scale(1) rotate(0);opacity:1} }
@keyframes cardFly { to{transform:translateY(-22px) scale(.5);opacity:0} }
.card-slide-enter-active { animation: cardSlide .25s ease-out; }
.card-slide-leave-active { animation: cardSlide .16s ease-in reverse forwards; }
@keyframes cardSlide { from{transform:translateY(-28px) scale(.85);opacity:0} to{transform:translateY(0) scale(1);opacity:1} }
</style>
