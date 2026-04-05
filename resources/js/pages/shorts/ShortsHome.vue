<template>
  <div class="shorts-page">
    <div ref="scrollContainer" class="shorts-scroll" @scroll="onScroll">

      <div v-for="(short, idx) in shorts" :key="short.id" class="shorts-slide">
        <div class="shorts-content">

          <!-- Video -->
          <div class="shorts-video">
            <iframe
              v-if="Math.abs(idx - currentIndex) <= 1"
              :src="idx === currentIndex ? getEmbedUrl(short) : ''"
              class="w-full h-full"
              frameborder="0"
              allow="autoplay; encrypted-media; gyroscope; picture-in-picture"
              allowfullscreen
            ></iframe>
            <img v-else-if="short.thumbnail" :src="short.thumbnail" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full flex items-center justify-center bg-black text-white">
              <svg class="w-16 h-16 opacity-30" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            </div>

            <!-- Bottom gradient overlay -->
            <div class="absolute bottom-0 left-0 right-0 p-4 bg-gradient-to-t from-black/70 to-transparent">
              <h3 class="text-white text-sm font-semibold line-clamp-2">{{ short.title }}</h3>
              <div v-if="short.tags && short.tags.length" class="flex flex-wrap gap-1 mt-1">
                <span v-for="tag in short.tags.slice(0, 3)" :key="tag"
                  class="text-xs bg-white/20 text-white px-2 py-0.5 rounded-full">
                  #{{ tag }}
                </span>
              </div>
            </div>
          </div>

          <!-- Action buttons (right side) -->
          <div class="shorts-actions">
            <!-- Like -->
            <button @click="toggleLike(short)" class="action-btn" :class="{ 'text-red-500': short.is_liked }">
              <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
                <path v-if="short.is_liked" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                <path v-else d="M16.5 3c-1.74 0-3.41.81-4.5 2.09C10.91 3.81 9.24 3 7.5 3 4.42 3 2 5.42 2 8.5c0 3.78 3.4 6.86 8.55 11.54L12 21.35l1.45-1.32C18.6 15.36 22 12.28 22 8.5 22 5.42 19.58 3 16.5 3zm-4.4 15.55l-.1.1-.1-.1C7.14 14.24 4 11.39 4 8.5 4 6.5 5.5 5 7.5 5c1.54 0 3.04.99 3.57 2.36h1.87C13.46 5.99 14.96 5 16.5 5c2 0 3.5 1.5 3.5 3.5 0 2.89-3.14 5.74-7.9 10.05z"/>
              </svg>
              <span class="text-xs mt-1">{{ formatCount(short.likes_count || 0) }}</span>
            </button>

            <!-- Dislike -->
            <button @click="toggleDislike(short)" class="action-btn" :class="{ 'text-blue-500': short.is_disliked }">
              <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
                <path d="M15 3H6c-.83 0-1.54.5-1.84 1.22l-3.02 7.05c-.09.23-.14.47-.14.73v2c0 1.1.9 2 2 2h6.31l-.95 4.57-.03.32c0 .41.17.79.44 1.06L9.83 23l6.59-6.59c.36-.36.58-.86.58-1.41V5c0-1.1-.9-2-2-2zm4 0v12h4V3h-4z"/>
              </svg>
              <span class="text-xs mt-1">{{ formatCount(short.dislikes_count || 0) }}</span>
            </button>

            <!-- Comment -->
            <button @click="openComments(short)" class="action-btn">
              <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
                <path d="M21.99 4c0-1.1-.89-2-1.99-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14l4 4-.01-18z"/>
              </svg>
              <span class="text-xs mt-1">{{ formatCount(short.comments_count || 0) }}</span>
            </button>

            <!-- Share -->
            <button @click="shareShort(short)" class="action-btn">
              <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
                <path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/>
              </svg>
              <span class="text-xs mt-1">Share</span>
            </button>
          </div>

        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="shorts-slide flex items-center justify-center">
        <div class="animate-spin rounded-full h-10 w-10 border-2 border-gray-300 border-t-blue-500"></div>
      </div>

      <!-- Empty -->
      <div v-if="!loading && shorts.length === 0" class="shorts-slide flex items-center justify-center">
        <div class="text-center text-gray-400">
          <svg class="w-16 h-16 mx-auto mb-4 opacity-30" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
          <p>No shorts available</p>
        </div>
      </div>

    </div>

    <!-- Navigation arrows -->
    <div class="shorts-nav">
      <button @click="goPrev" :disabled="currentIndex === 0"
        class="nav-arrow" :class="{ 'opacity-30': currentIndex === 0 }">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
        </svg>
      </button>
      <button @click="goNext" :disabled="currentIndex >= shorts.length - 1"
        class="nav-arrow" :class="{ 'opacity-30': currentIndex >= shorts.length - 1 }">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
      </button>
    </div>

  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'ShortsHome',

  data() {
    return {
      shorts: [],
      currentIndex: 0,
      loading: false,
      page: 1,
      hasMore: true,
    };
  },

  mounted() {
    this.fetchShorts();
    window.addEventListener('keydown', this.onKeydown);
  },

  beforeUnmount() {
    window.removeEventListener('keydown', this.onKeydown);
  },

  methods: {
    async fetchShorts() {
      if (this.loading || !this.hasMore) return;
      this.loading = true;
      try {
        const res = await axios.get('/api/shorts', {
          params: { sort: 'random', page: this.page, per_page: 10 }
        });
        const data = res.data.data || res.data;
        const items = Array.isArray(data) ? data : (data.data || []);
        if (items.length === 0) {
          this.hasMore = false;
        } else {
          this.shorts.push(...items);
          this.page++;
        }
      } catch (e) {
        console.error('Failed to load shorts:', e);
      } finally {
        this.loading = false;
      }
    },

    getEmbedUrl(short) {
      if (!short.url && !short.embed_url) return '';
      const url = short.embed_url || short.url || "";
      let videoId = '';
      if (url.includes('youtube.com/embed/')) {
        videoId = url.split('embed/')[1]?.split(/[?&#]/)[0];
      } else
      if (url.includes('youtube.com/shorts/')) {
        videoId = url.split('shorts/')[1]?.split(/[?&#]/)[0];
      } else if (url.includes('youtube.com/watch')) {
        const u = new URL(url);
        videoId = u.searchParams.get('v');
      } else if (url.includes('youtu.be/')) {
        videoId = url.split('youtu.be/')[1]?.split(/[?&#]/)[0];
      }
      if (videoId) {
        return 'https://www.youtube.com/embed/' + videoId + '?autoplay=1&loop=1&playlist=' + videoId + '&mute=0&controls=1&rel=0';
      }
      return url;
    },

    onScroll() {
      const container = this.$refs.scrollContainer;
      if (!container) return;
      const scrollTop = container.scrollTop;
      const slideHeight = container.clientHeight;
      const newIndex = Math.round(scrollTop / slideHeight);
      if (newIndex !== this.currentIndex) {
        this.currentIndex = newIndex;
        this.trackView(this.shorts[newIndex]);
      }
      if (newIndex >= this.shorts.length - 3) {
        this.fetchShorts();
      }
    },

    goNext() {
      if (this.currentIndex >= this.shorts.length - 1) return;
      const container = this.$refs.scrollContainer;
      if (container) {
        container.scrollTo({ top: (this.currentIndex + 1) * container.clientHeight, behavior: 'smooth' });
      }
    },

    goPrev() {
      if (this.currentIndex <= 0) return;
      const container = this.$refs.scrollContainer;
      if (container) {
        container.scrollTo({ top: (this.currentIndex - 1) * container.clientHeight, behavior: 'smooth' });
      }
    },

    onKeydown(e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); this.goNext(); }
      if (e.key === 'ArrowUp') { e.preventDefault(); this.goPrev(); }
    },

    async toggleLike(short) {
      try {
        const res = await axios.post('/api/shorts/' + short.id + '/like');
        short.is_liked = !short.is_liked;
        short.likes_count = res.data.likes_count != null ? res.data.likes_count : (short.is_liked ? (short.likes_count || 0) + 1 : (short.likes_count || 1) - 1);
        if (short.is_liked) short.is_disliked = false;
      } catch (e) {
        console.error('Like failed:', e);
      }
    },

    async toggleDislike(short) {
      try {
        const res = await axios.post('/api/shorts/' + short.id + '/dislike');
        short.is_disliked = !short.is_disliked;
        short.dislikes_count = res.data.dislikes_count != null ? res.data.dislikes_count : (short.is_disliked ? (short.dislikes_count || 0) + 1 : (short.dislikes_count || 1) - 1);
        if (short.is_disliked) short.is_liked = false;
      } catch (e) {
        console.error('Dislike failed:', e);
      }
    },

    openComments(short) {
      this.$router.push({ name: 'shorts.show', params: { id: short.id } });
    },

    async shareShort(short) {
      const url = window.location.origin + '/shorts/' + short.id;
      if (navigator.share) {
        try {
          await navigator.share({ title: short.title, url: url });
        } catch (e) { /* cancelled */ }
      } else {
        await navigator.clipboard.writeText(url);
        alert('Link copied!');
      }
      try { await axios.post('/api/shorts/' + short.id + '/share'); } catch (e) { /* ignore */ }
    },

    async trackView(short) {
      if (!short) return;
      try { await axios.post('/api/shorts/' + short.id + '/view'); } catch (e) { /* ignore */ }
    },

    formatCount(n) {
      if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
      if (n >= 1000) return (n / 1000).toFixed(1) + 'K';
      return String(n);
    },
  },
};
</script>

<style scoped>
.shorts-page {
  position: fixed;
  top: 48px;
  left: 0;
  right: 0;
  bottom: 0;
  background: #ffffff;
  z-index: 10;
}

@media (min-width: 768px) {
  .shorts-page {
    top: 84px;
  }
}

.shorts-scroll {
  height: 100%;
  overflow-y: scroll;
  scroll-snap-type: y mandatory;
  -ms-overflow-style: none;
  scrollbar-width: none;
}

.shorts-scroll::-webkit-scrollbar {
  display: none;
}

.shorts-slide {
  height: 100%;
  scroll-snap-align: start;
  display: flex;
  align-items: center;
  justify-content: center;
}

.shorts-content {
  position: relative;
  display: flex;
  align-items: center;
  gap: 16px;
  height: 100%;
  padding: 16px 0;
}

.shorts-video {
  position: relative;
  width: 405px;
  height: 100%;
  max-height: 720px;
  background: #000;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 24px rgba(0,0,0,0.12);
}

.shorts-actions {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
  padding: 8px 0;
}

.action-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #333;
  cursor: pointer;
  transition: transform 0.15s;
  background: none;
  border: none;
  padding: 8px;
}

.action-btn:hover {
  transform: scale(1.15);
}

.shorts-nav {
  position: fixed;
  right: 24px;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  flex-direction: column;
  gap: 8px;
  z-index: 20;
}

.nav-arrow {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(255,255,255,0.9);
  border: 1px solid #e5e7eb;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #333;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  transition: background 0.15s;
}

.nav-arrow:hover {
  background: #f3f4f6;
}

@media (max-width: 640px) {
  .shorts-content {
    padding: 0;
  }

  .shorts-video {
    width: 100%;
    max-height: none;
    border-radius: 0;
    box-shadow: none;
  }

  .shorts-actions {
    position: absolute;
    right: 12px;
    bottom: 80px;
    z-index: 15;
  }

  .action-btn {
    color: #fff;
    filter: drop-shadow(0 1px 2px rgba(0,0,0,0.5));
  }

  .shorts-nav {
    right: 12px;
  }

  .nav-arrow {
    width: 36px;
    height: 36px;
    background: rgba(255,255,255,0.7);
  }
}
</style>
