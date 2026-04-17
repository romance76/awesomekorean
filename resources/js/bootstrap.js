import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.baseURL = '/';

// JWT 토큰 자동 첨부
axios.interceptors.request.use(config => {
    const token = localStorage.getItem('sk_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// Laravel Echo (WebSocket via Reverb)
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/api/broadcasting/auth',
    auth: {
        headers: {},
    },
    // 토큰을 동적으로 가져옴 (로그인 후에도 최신 토큰 사용)
    authorizer: (channel) => ({
        authorize: (socketId, callback) => {
            const token = localStorage.getItem('sk_token')
            console.log('[Echo] Authorizing channel:', channel.name, 'token:', token ? 'yes' : 'NO TOKEN')
            axios.post('/api/broadcasting/auth', {
                socket_id: socketId,
                channel_name: channel.name,
            }, {
                headers: { Authorization: `Bearer ${token}` },
            }).then(response => {
                console.log('[Echo] Channel authorized OK:', channel.name)
                callback(null, response.data)
            }).catch(error => {
                console.error('[Echo] Channel auth FAILED:', channel.name, error.response?.status, error.response?.data)
                callback(error)
            })
        },
    }),
});

// 사이트 설정 실시간 반영 (Phase 2-C 묶음 7)
// 관리자 저장 시 Reverb 'site-settings' 채널로 브로드캐스트 → siteStore 강제 재로드
try {
    window.Echo.channel('site-settings')
        .listen('.settings.updated', (e) => {
            console.log('[site-settings] update received', e)
            import('./stores/site.js').then(({ useSiteStore }) => {
                const store = useSiteStore()
                store.forceReload().catch(() => {})
            })
        })
} catch (err) {
    console.warn('[site-settings] listener setup failed', err)
}

// 401 처리: 토큰 만료 시 조용히 재시도 (broadcasting/auth 같은 public 엔드포인트는 로그아웃시키지 않음)
const PUBLIC_401_ENDPOINTS = ['/broadcasting/auth', '/api/banners/active', '/api/banners/mobile', '/api/settings/', '/api/user'];
axios.interceptors.response.use(
    response => response,
    error => {
        const url = error.config?.url || '';
        const isPublicOk = PUBLIC_401_ENDPOINTS.some(p => url.includes(p));
        if (error.response?.status === 401 && !isPublicOk) {
            // 토큰 있는데 401이면 만료된 토큰 → 제거만 하고 로그인 페이지는 유저가 명시적 액션 시에만
            const hadToken = !!localStorage.getItem('sk_token');
            localStorage.removeItem('sk_token');
            localStorage.removeItem('sk_user');
            // 로그인이 필요한 페이지에서 401이 났을 때만 리다이렉트
            const needsAuth = /\/dashboard|\/write|\/create|\/edit|\/ad-apply|\/my-/.test(window.location.pathname);
            if (hadToken && needsAuth && !window.location.pathname.startsWith('/login')) {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);
