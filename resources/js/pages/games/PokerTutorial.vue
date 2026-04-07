<template>
<div class="min-h-screen bg-gray-950 text-gray-200">
  <div class="max-w-4xl mx-auto px-4 py-6 space-y-8">

    <!-- Header -->
    <div class="text-center relative">
      <button @click="$router.back()" class="absolute left-0 top-1 text-white/50 hover:text-white text-lg">&#9664;</button>
      <div class="text-4xl mb-2">&#128218;</div>
      <h1 class="text-2xl font-black text-amber-400">&#54252;&#52964; &#47344; &amp; &#53916;&#53664;&#47532;&#50620;</h1>
      <p class="text-gray-400 text-xs tracking-[0.3em] mt-1">TEXAS HOLD'EM TOURNAMENT GUIDE</p>
    </div>

    <!-- Table of Contents -->
    <nav class="bg-gray-900 rounded-xl border border-gray-800 p-4">
      <h2 class="text-sm font-bold text-blue-400 mb-3">&#128209; &#47785;&#52264;</h2>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
        <button v-for="sec in sections" :key="sec.id" @click="scrollTo(sec.id)"
          class="text-left bg-gray-800 hover:bg-gray-700 rounded-lg px-3 py-2 text-xs text-gray-300 transition">
          {{ sec.icon }} {{ sec.label }}
        </button>
      </div>
    </nav>

    <!-- 1. Game Overview -->
    <section id="overview" class="bg-gray-900 rounded-xl border border-gray-700 p-5">
      <h2 class="text-lg font-bold text-amber-400 mb-3">&#127918; &#53581;&#49324;&#49828; &#54848;&#45924;&#51060;&#46976;?</h2>
      <div class="text-sm space-y-3 leading-relaxed">
        <p>&#53581;&#49324;&#49828; &#54848;&#45924;&#51008; &#49464;&#44228;&#50640;&#49436; &#44032;&#51109; &#51064;&#44592; &#51080;&#45716; &#54252;&#52964; &#44172;&#51076;&#51077;&#45768;&#45796;. &#44033; &#54540;&#47112;&#51060;&#50612;&#50640;&#44172; 2&#51109;&#51032; &#44060;&#51064; &#52852;&#46300;(&#54848; &#52852;&#46300;)&#44032; &#51452;&#50612;&#51648;&#44256;, &#53580;&#51060;&#48660; &#51473;&#50521;&#50640; 5&#51109;&#51032; &#44277;&#50976; &#52852;&#46300;(&#52964;&#48036;&#45768;&#54000; &#52852;&#46300;)&#44032; &#44277;&#44060;&#46121;&#45768;&#45796;. &#44060;&#51064; &#52852;&#46300; 2&#51109;&#44284; &#52964;&#48036;&#45768;&#54000; &#52852;&#46300; 5&#51109; &#51473; &#52572;&#44256;&#51032; 5&#51109; &#51312;&#54633;&#51004;&#47196; &#49849;&#51088;&#47484; &#44208;&#51221;&#54633;&#45768;&#45796;.</p>
        <p>&#53664;&#45320;&#47676;&#53944; &#47784;&#46300;&#50640;&#49436;&#45716; &#47588;&#54645;&#50640; &#52841;&#51012; &#44152;&#44256;, &#52841;&#51012; &#47784;&#46160; &#51075;&#51004;&#47732; &#53448;&#46973;&#54633;&#45768;&#45796;. &#47560;&#51648;&#47561;&#50640; &#45224;&#45716; &#54540;&#47112;&#51060;&#50612;&#44032; &#49849;&#47532;&#54633;&#45768;&#45796;!</p>
        <div class="bg-gray-800 rounded-lg p-3 mt-3">
          <div class="text-xs text-blue-400 font-bold mb-2">&#44172;&#51076; &#55120;&#47492;</div>
          <div class="flex items-center gap-2 text-xs text-gray-400 flex-wrap">
            <span class="bg-blue-500/20 px-2 py-1 rounded text-blue-300">&#54848;&#52852;&#46300; 2&#51109; &#48176;&#48516;</span>
            <span>&#8594;</span>
            <span class="bg-blue-500/20 px-2 py-1 rounded text-blue-300">&#54532;&#47532;&#54540;&#47101; &#48288;&#54021;</span>
            <span>&#8594;</span>
            <span class="bg-green-500/20 px-2 py-1 rounded text-green-300">&#54540;&#47101; (3&#51109;)</span>
            <span>&#8594;</span>
            <span class="bg-yellow-500/20 px-2 py-1 rounded text-yellow-300">&#53556; (4&#48264;&#51704;)</span>
            <span>&#8594;</span>
            <span class="bg-red-500/20 px-2 py-1 rounded text-red-300">&#47532;&#48260; (5&#48264;&#51704;)</span>
            <span>&#8594;</span>
            <span class="bg-amber-500/20 px-2 py-1 rounded text-amber-300">&#49660;&#45796;&#50868;</span>
          </div>
        </div>
      </div>
    </section>

    <!-- 2. Hand Rankings -->
    <section id="hands" class="bg-gray-900 rounded-xl border border-gray-700 p-5">
      <h2 class="text-lg font-bold text-amber-400 mb-3">&#127183; &#54648;&#46300; &#46321;&#44553; (&#44053;&#54620; &#49692;&#49436;)</h2>
      <div class="space-y-2">
        <div v-for="(h, i) in handRankings" :key="i"
          class="flex items-center gap-3 bg-black/20 rounded-lg px-3 py-2">
          <span class="text-amber-400 font-mono text-sm w-5 text-right font-bold">{{ i + 1 }}</span>
          <span :style="{ color: h.color }" class="font-bold text-sm min-w-[100px]">{{ h.name }}</span>
          <span class="text-gray-300 text-xs flex-1">{{ h.desc }}</span>
          <span class="text-gray-400 text-xs font-mono hidden sm:inline">{{ h.example }}</span>
        </div>
      </div>
    </section>

    <!-- 3. Position Guide -->
    <section id="position" class="bg-gray-900 rounded-xl border border-gray-700 p-5">
      <h2 class="text-lg font-bold text-amber-400 mb-3">&#128205; &#54252;&#51648;&#49496; &#44032;&#51060;&#46300;</h2>
      <p class="text-xs text-gray-300 mb-3">&#54252;&#51648;&#49496;&#51008; &#54252;&#52964;&#50640;&#49436; &#44032;&#51109; &#51473;&#50836;&#54620; &#50836;&#49548; &#51473; &#54616;&#45208;&#51077;&#45768;&#45796;. &#45734;&#44172; &#54665;&#46041;&#54624;&#49688;&#47197; &#50976;&#47532;&#54633;&#45768;&#45796;.</p>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div v-for="pos in positions" :key="pos.abbr"
          class="bg-black/20 rounded-lg p-3 flex items-start gap-3 border-l-4" :class="pos.borderClass">
          <span :class="pos.badgeClass" class="text-[10px] font-extrabold text-white px-2 py-0.5 rounded shrink-0 mt-0.5">{{ pos.abbr }}</span>
          <div>
            <div class="text-gray-200 text-sm font-semibold">{{ pos.full }}</div>
            <div class="text-gray-300 text-xs mt-0.5 leading-relaxed">{{ pos.tip }}</div>
          </div>
        </div>
      </div>
    </section>

    <!-- 4. Blind Structure -->
    <section id="blinds" class="bg-gray-900 rounded-xl border border-gray-700 p-5">
      <h2 class="text-lg font-bold text-amber-400 mb-3">&#9202;&#65039; &#48660;&#46972;&#51064;&#46300; &#49828;&#52992;&#51460;</h2>
      <p class="text-xs text-gray-300 mb-3">&#48660;&#46972;&#51064;&#46300;&#45716; &#49884;&#44036;&#51060; &#51648;&#45216;&#49688;&#47197; &#51216;&#51216; &#50732;&#46972;&#44049;&#45768;&#45796;. &#44033; &#47112;&#48296;&#51032; &#51648;&#49549; &#49884;&#44036;&#51060; &#51648;&#45208;&#47732; &#45796;&#51020; &#47112;&#48296;&#47196; &#51088;&#46041; &#51204;&#54872;&#46121;&#45768;&#45796;.</p>
      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead>
            <tr class="text-gray-300 border-b border-white/10">
              <th class="py-2 text-center">&#47112;&#48296;</th>
              <th class="py-2 text-center">SB</th>
              <th class="py-2 text-center">BB</th>
              <th class="py-2 text-center">&#50532;&#54000;</th>
              <th class="py-2 text-center">&#49884;&#44036;</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(b, i) in BLIND_SCHEDULE" :key="i"
              class="border-b border-white/[0.03]"
              :class="i < 4 ? 'text-green-400' : i < 8 ? 'text-yellow-400' : i < 12 ? 'text-orange-400' : 'text-red-400'">
              <td class="py-1.5 text-center font-bold text-blue-400">{{ i + 1 }}</td>
              <td class="text-center font-mono">{{ b.sb.toLocaleString() }}</td>
              <td class="text-center font-mono">{{ b.bb.toLocaleString() }}</td>
              <td class="text-center font-mono" :class="b.ante > 0 ? 'text-amber-400' : 'text-gray-700'">{{ b.ante > 0 ? b.ante.toLocaleString() : '-' }}</td>
              <td class="text-center text-gray-300">{{ b.dur }}&#48516;</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- 5. Tournament Rules -->
    <section id="rules" class="bg-gray-900 rounded-xl border border-gray-700 p-5">
      <h2 class="text-lg font-bold text-amber-400 mb-3">&#127942; &#53664;&#45320;&#47676;&#53944; &#44508;&#52825;</h2>
      <div class="space-y-4">
        <!-- Bounty -->
        <div class="bg-gray-800 rounded-lg p-4">
          <h3 class="text-sm font-bold text-yellow-400 mb-2">&#128176; &#48148;&#50868;&#54000; &#49884;&#49828;&#53596;</h3>
          <ul class="text-xs text-gray-400 space-y-1 list-disc list-inside leading-relaxed">
            <li>&#49345;&#45824;&#47484; &#53448;&#46973;&#49884;&#53412;&#47732; &#48148;&#51060;&#51064;&#51032; 10%&#47484; &#48148;&#50868;&#54000;&#47196; &#54925;&#46301;</li>
            <li>&#50696;: &#48148;&#51060;&#51064; 400&#52841;&#51060;&#47732; &#48148;&#50868;&#54000; 40&#52841;</li>
            <li>&#48148;&#50868;&#54000;&#45716; &#49345;&#44552;&#44284; &#48324;&#46020;&#47196; &#51648;&#44553;</li>
          </ul>
        </div>

        <!-- Prize Structure -->
        <div class="bg-gray-800 rounded-lg p-4">
          <h3 class="text-sm font-bold text-green-400 mb-2">&#128176; &#49345;&#44552; &#44396;&#51312;</h3>
          <ul class="text-xs text-gray-400 space-y-1 list-disc list-inside leading-relaxed">
            <li>&#52280;&#44032;&#51088;&#51032; &#49345;&#50948; 15%&#44032; &#51077;&#49345;&#44428; (ITM)</li>
            <li>1&#50948;: 25%, 2&#50948;: 16%, 3&#50948;: 11%</li>
            <li>4~6&#50948;: &#44033; 7%, 7~10&#50948;: &#44033; 4%</li>
            <li>11&#50948; &#51060;&#54616;: &#45208;&#47672;&#51648; &#44512;&#46321; &#48176;&#48516;</li>
          </ul>
        </div>

        <!-- Bubble/ITM -->
        <div class="bg-gray-800 rounded-lg p-4">
          <h3 class="text-sm font-bold text-blue-400 mb-2">&#129531; &#48260;&#48660; &amp; ITM</h3>
          <ul class="text-xs text-gray-400 space-y-1 list-disc list-inside leading-relaxed">
            <li><strong class="text-yellow-400">&#48260;&#48660;:</strong> &#51077;&#49345;&#44428; &#51649;&#51204; &#49345;&#53468;. 1&#47749;&#47564; &#53448;&#46973;&#54616;&#47732; &#45208;&#47672;&#51648; &#47784;&#46160; &#49345;&#44552; &#49688;&#47161;</li>
            <li><strong class="text-green-400">ITM (In The Money):</strong> &#51077;&#49345;&#44428;&#50640; &#51652;&#51077;&#54620; &#49345;&#53468;. &#52572;&#49548; &#49345;&#44552; &#48372;&#51109;</li>
            <li>&#48260;&#48660; &#44540;&#52376;&#50640;&#49436;&#45716; &#48372;&#49688;&#51201; &#54540;&#47112;&#51060; &#44428;&#51109;</li>
          </ul>
        </div>
      </div>
    </section>

    <!-- 6. Coaching System -->
    <section id="coaching" class="bg-gray-900 rounded-xl border border-gray-700 p-5">
      <h2 class="text-lg font-bold text-amber-400 mb-3">&#128161; &#53076;&#52845; &#49884;&#49828;&#53596;</h2>
      <div class="text-sm space-y-3 leading-relaxed">
        <p>&#44172;&#51076; &#51473; &#53076;&#52845; &#54056;&#45328;&#51060; &#49892;&#49884;&#44036;&#51004;&#47196; &#46020;&#50880;&#51012; &#51469;&#45768;&#45796;. &#49345;&#45800; &#128161; &#48260;&#53948;&#51004;&#47196; &#53664;&#44544;&#54624; &#49688; &#51080;&#49845;&#45768;&#45796;.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="bg-gray-800 rounded-lg p-3">
            <div class="text-xs text-blue-400 font-bold mb-1">&#54252;&#51648;&#49496; &#51221;&#48372;</div>
            <p class="text-xs text-gray-300">&#54788;&#51116; &#51088;&#49888;&#51032; &#54252;&#51648;&#49496;&#44284; &#44428;&#51109; &#54540;&#47112;&#51060; &#48276;&#50948; &#54364;&#49884;</p>
          </div>
          <div class="bg-gray-800 rounded-lg p-3">
            <div class="text-xs text-green-400 font-bold mb-1">&#49849;&#47456; (Equity)</div>
            <p class="text-xs text-gray-300">&#54788;&#51116; &#54648;&#46300;&#44032; &#51060;&#44600; &#54869;&#47456;&#51012; &#48148; &#52264;&#53944;&#47196; &#54364;&#49884;</p>
          </div>
          <div class="bg-gray-800 rounded-lg p-3">
            <div class="text-xs text-yellow-400 font-bold mb-1">&#54055;&#50724;&#51592; (Pot Odds)</div>
            <p class="text-xs text-gray-300">&#53084; &#48708;&#50857; &#45824;&#48708; &#54055; &#53356;&#44592;&#51032; &#48708;&#50984;. &#49849;&#47456;&#51060; &#54055;&#50724;&#51592;&#48372;&#45796; &#45458;&#51004;&#47732; &#53084;&#51060; &#51060;&#51061;</p>
          </div>
          <div class="bg-gray-800 rounded-lg p-3">
            <div class="text-xs text-amber-400 font-bold mb-1">&#52628;&#52380; &#50561;&#49496;</div>
            <p class="text-xs text-gray-300">&#49345;&#54889;&#50640; &#47582;&#45716; &#52572;&#51201; &#54665;&#46041;&#44284; &#51060;&#50976; &#49444;&#47749;</p>
          </div>
        </div>
        <div class="bg-gray-800 rounded-lg p-3 mt-2">
          <div class="text-xs text-gray-400 font-bold mb-1">&#127183; &#54260;&#46300; &#52852;&#46300; &#44277;&#44060;</div>
          <p class="text-xs text-gray-300">AI &#54540;&#47112;&#51060;&#50612;&#44032; &#54260;&#46300;&#54620; &#52852;&#46300;&#50752; &#51060;&#50976;&#51012; &#44277;&#44060;&#54633;&#45768;&#45796;. &#49345;&#45824;&#51032; &#49828;&#53440;&#51068;&#51012; &#54028;&#50501;&#54616;&#45716; &#45936; &#46020;&#50880;&#51060; &#46121;&#45768;&#45796;!</p>
        </div>
      </div>
    </section>

    <!-- 7. Wallet System -->
    <section id="wallet" class="bg-gray-900 rounded-xl border border-gray-700 p-5">
      <h2 class="text-lg font-bold text-amber-400 mb-3">&#128176; &#51648;&#44049; &#49884;&#49828;&#53596;</h2>
      <div class="text-sm space-y-3 leading-relaxed">
        <p>&#54252;&#52964; &#44172;&#51076;&#51008; &#51204;&#50857; &#52841; &#51648;&#44049;&#51012; &#49324;&#50857;&#54633;&#45768;&#45796;. &#54252;&#51064;&#53944;&#47484; &#52841;&#51004;&#47196; &#44368;&#54872;&#54616;&#50668; &#54540;&#47112;&#51060;&#54624; &#49688; &#51080;&#49845;&#45768;&#45796;.</p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div class="bg-gray-800 rounded-lg p-4 text-center">
            <div class="text-green-400 font-bold text-base mb-2">&#51077;&#44552;</div>
            <p class="text-xs text-gray-400">&#54252;&#51064;&#53944; &#8594; &#52841;</p>
            <p class="text-xs text-gray-400 mt-1">1:1 &#44368;&#54872; &#48708;&#50984;</p>
          </div>
          <div class="bg-gray-800 rounded-lg p-4 text-center">
            <div class="text-red-400 font-bold text-base mb-2">&#52636;&#44552;</div>
            <p class="text-xs text-gray-400">&#52841; &#8594; &#54252;&#51064;&#53944;</p>
            <p class="text-xs text-gray-400 mt-1">&#49688;&#49688;&#47308; &#51201;&#50857; &#44032;&#45733;</p>
          </div>
          <div class="bg-gray-800 rounded-lg p-4 text-center">
            <div class="text-amber-400 font-bold text-base mb-2">&#49345;&#44552;</div>
            <p class="text-xs text-gray-400">&#51077;&#49345; &#49884; &#51088;&#46041; &#51648;&#44553;</p>
            <p class="text-xs text-gray-400 mt-1">&#48148;&#50868;&#54000;&#46020; &#48324;&#46020; &#51648;&#44553;</p>
          </div>
        </div>
      </div>
    </section>

    <!-- 8. Poker Terms -->
    <section id="terms" class="bg-gray-900 rounded-xl border border-gray-700 p-5">
      <h2 class="text-lg font-bold text-amber-400 mb-3">&#128214; &#54252;&#52964; &#50857;&#50612; &#49324;&#51204;</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        <div v-for="(term, key) in basicTerms" :key="key"
          class="bg-black/20 rounded-lg px-3 py-2">
          <div class="flex items-center gap-2 mb-0.5">
            <span class="text-xs font-bold text-blue-400">{{ term.en }}</span>
            <span class="text-xs text-gray-300">{{ term.kr }}</span>
          </div>
          <p class="text-[11px] text-gray-300 leading-relaxed">{{ term.desc }}</p>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <div class="text-center pb-8 pt-4">
      <RouterLink to="/games/poker"
        class="inline-block px-10 py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 text-gray-950 font-bold text-lg hover:from-amber-400 hover:to-amber-500 transition no-underline shadow-lg shadow-amber-500/20">
        &#127918; &#44172;&#51076; &#49884;&#51089;
      </RouterLink>
    </div>
  </div>
</div>
</template>

<script setup>
import { BLIND_SCHEDULE } from '@/composables/useBlindSchedule'
import { POS_FULL, POS_TIP, TERMS } from '@/composables/useCoaching'

const sections = [
  { id: 'overview', icon: '\u{1F3AE}', label: '\uAC8C\uC784 \uAC1C\uC694' },
  { id: 'hands', icon: '\u{1F0CF}', label: '\uD578\uB4DC \uB4F1\uAE09' },
  { id: 'position', icon: '\u{1F4CD}', label: '\uD3EC\uC9C0\uC158' },
  { id: 'blinds', icon: '\u23F2', label: '\uBE14\uB77C\uC778\uB4DC' },
  { id: 'rules', icon: '\u{1F3C6}', label: '\uD1A0\uB108\uBA3C\uD2B8 \uADDC\uCE59' },
  { id: 'coaching', icon: '\u{1F4A1}', label: '\uCF54\uCE6D' },
  { id: 'wallet', icon: '\u{1F4B0}', label: '\uC9C0\uAC11' },
  { id: 'terms', icon: '\u{1F4D6}', label: '\uC6A9\uC5B4' },
]

function scrollTo(id) {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const handRankings = [
  { name: '\uB85C\uC5F4 \uD50C\uB7EC\uC2DC', desc: '\uAC19\uC740 \uBB34\uB298 A-K-Q-J-10', example: 'A\u2660 K\u2660 Q\u2660 J\u2660 10\u2660', color: '#ffd700' },
  { name: '\uC2A4\uD2B8\uB808\uC774\uD2B8 \uD50C\uB7EC\uC2DC', desc: '\uAC19\uC740 \uBB34\uB291 \uC5F0\uC18D 5\uC7A5', example: '9\u2665 8\u2665 7\u2665 6\u2665 5\u2665', color: '#ff6b35' },
  { name: '\uD3EC\uCE74\uB4DC', desc: '\uAC19\uC740 \uC22B\uC790 4\uC7A5', example: 'K\u2660 K\u2665 K\u2666 K\u2663 A\u2660', color: '#e040fb' },
  { name: '\uD480\uD558\uC6B0\uC2A4', desc: '3\uC7A5 + 2\uC7A5 (\uD2B8\uB9AC\uD50C + \uC6D0\uD398\uC5B4)', example: 'J\u2660 J\u2665 J\u2666 8\u2663 8\u2660', color: '#ff9800' },
  { name: '\uD50C\uB7EC\uC2DC', desc: '\uAC19\uC740 \uBB34\uB291 5\uC7A5', example: 'A\u2663 J\u2663 8\u2663 5\u2663 3\u2663', color: '#29b6f6' },
  { name: '\uC2A4\uD2B8\uB808\uC774\uD2B8', desc: '\uC5F0\uC18D\uB41C 5\uC7A5 (\uBB34\uB291 \uB2EC\uB77C\uB3C4 OK)', example: '10\u2660 9\u2665 8\u2666 7\u2663 6\u2660', color: '#66bb6a' },
  { name: '\uD2B8\uB9AC\uD50C', desc: '\uAC19\uC740 \uC22B\uC790 3\uC7A5', example: 'Q\u2660 Q\u2665 Q\u2666 9\u2663 4\u2660', color: '#42a5f5' },
  { name: '\uD22C\uD398\uC5B4', desc: '\uAC19\uC740 \uC22B\uC790 \uC6D0\uD398\uC5B4 2\uC870', example: 'A\u2660 A\u2665 7\u2666 7\u2663 K\u2660', color: '#ab47bc' },
  { name: '\uC6D0\uD398\uC5B4', desc: '\uAC19\uC740 \uC22B\uC790 2\uC7A5', example: '10\u2660 10\u2665 A\u2666 8\u2663 3\u2660', color: '#78909c' },
  { name: '\uD558\uC774\uCE74\uB4DC', desc: '\uC544\uBB34 \uC870\uD569\uB3C4 \uC5C6\uC744 \uB54C \uAC00\uC7A5 \uB192\uC740 \uCE74\uB4DC', example: 'A\u2660 K\u2665 9\u2666 5\u2663 2\u2660', color: '#607d8b' },
]

const positions = [
  { abbr: 'BTN', full: POS_FULL['BTN'], tip: POS_TIP['BTN'], badgeClass: 'bg-amber-500', borderClass: 'border-yellow-400' },
  { abbr: 'SB', full: POS_FULL['SB'], tip: POS_TIP['SB'], badgeClass: 'bg-blue-500', borderClass: 'border-blue-400' },
  { abbr: 'BB', full: POS_FULL['BB'], tip: POS_TIP['BB'], badgeClass: 'bg-red-600', borderClass: 'border-red-400' },
  { abbr: 'UTG', full: POS_FULL['UTG'], tip: POS_TIP['UTG'], badgeClass: 'bg-orange-600', borderClass: 'border-orange-400' },
  { abbr: 'UTG+1', full: POS_FULL['UTG+1'], tip: POS_TIP['UTG+1'], badgeClass: 'bg-orange-600', borderClass: 'border-orange-400' },
  { abbr: 'MP', full: POS_FULL['MP'], tip: POS_TIP['MP'], badgeClass: 'bg-gray-600', borderClass: 'border-gray-400' },
  { abbr: 'MP+1', full: POS_FULL['MP+1'], tip: POS_TIP['MP+1'], badgeClass: 'bg-gray-600', borderClass: 'border-gray-400' },
  { abbr: 'HJ', full: POS_FULL['HJ'], tip: POS_TIP['HJ'], badgeClass: 'bg-purple-600', borderClass: 'border-purple-400' },
  { abbr: 'CO', full: POS_FULL['CO'], tip: POS_TIP['CO'], badgeClass: 'bg-green-600', borderClass: 'border-green-400' },
]

const basicTerms = {
  fold: TERMS.fold,
  check: TERMS.check,
  call: TERMS.call,
  raise: TERMS.raise,
  allin: TERMS.allin,
  blind: TERMS.blind,
  ante: TERMS.ante,
  pot: TERMS.pot,
  flop: TERMS.flop,
  turn: TERMS.turn,
  river: TERMS.river,
  potodds: TERMS.potodds,
  equity: TERMS.equity,
  ev: TERMS.ev,
  steal: TERMS.steal,
  cbet: TERMS.cbet,
  valuebet: TERMS.valuebet,
  bubble: TERMS.bubble,
  shortstack: TERMS.shortstack,
}
</script>
