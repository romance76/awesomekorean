<template>
  <div id="somekorean-app">
    <NavBar v-if="!isAuthPage && !isGameFullscreen && !isAdminPage" />
    <main :class="isGameFullscreen ? '' : 'min-h-screen bg-gray-50'">
      <router-view />
    </main>
    <Footer v-if="!isAuthPage && !isGameFullscreen && !isAdminPage" />
    <MiniPlayer />
  </div>
</template>

<script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";
import NavBar from "./components/NavBar.vue";
import Footer from "./components/Footer.vue";
import MiniPlayer from "./components/MiniPlayer.vue";

const route = useRoute();
const isAuthPage  = computed(() => route.path.startsWith("/auth"));
const isAdminPage = computed(() => route.path.startsWith("/admin"));
const isGameFullscreen = computed(() =>
  ["/games/go-stop/solo", "/games/blackjack", "/games/poker", "/games/go-stop",
   "/games/holdem", "/games/memory", "/games/2048", "/games/bingo", "/games/omok"].includes(route.path)
);
</script>
