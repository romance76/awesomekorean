<template>
  <div id="somekorean-app">
    <NavBar v-if="!isAuthPage && !isGameFullscreen" />
    <main :class="isGameFullscreen ? '' : 'min-h-screen bg-gray-50'">
      <router-view />
    </main>
    <BottomNav v-if="!isAuthPage && !isGameFullscreen && isMobile" />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import NavBar from './components/NavBar.vue';
import BottomNav from './components/BottomNav.vue';

const route = useRoute();
const isAuthPage = computed(() => route.path.startsWith('/auth'));
// 전체화면 페이지 (NavBar/BottomNav 숨김)
const isGameFullscreen = computed(() =>
  ['/games/go-stop/solo', '/games/blackjack', '/games/poker', '/games/go-stop',
   '/games/holdem', '/games/memory', '/games/2048', '/games/bingo', '/games/omok'].includes(route.path)
);
const isMobile = computed(() => window.innerWidth < 768);
</script>
