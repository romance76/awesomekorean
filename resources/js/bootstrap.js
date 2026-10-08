import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.baseURL = '/';

// JWT 토큰 자동 첨부
axios.interceptors.request.use(config => {
    const token = sessionStorage.getItem('sk_token') || localStorage.getItem('sk_token');
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
            const token = sessionStorage.getItem('sk_token') || localStorage.getItem('sk_token')
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

// 401 처리: 로그인 정보(토큰)를 보냈는데 서버가 거부하면 세션이 끝난 것 — 화면 상태까지 함께 로그아웃시킨다
// (예전엔 localStorage 만 지워서 화면은 로그인 상태로 남았고, 세션 저장소 로그인은 처리되지도 않았다)
const NO_LOGOUT_ON_401 = ['/api/login', '/api/register', '/api/auth/refresh', '/api/forgot-password', '/api/reset-password'];
axios.interceptors.response.use(
    response => response,
    async error => {
        const url = error.config?.url || '';
        const sentToken = !!(error.config?.headers?.Authorization || error.config?.headers?.get?.('Authorization'));
        if (error.response?.status === 401 && sentToken && !NO_LOGOUT_ON_401.some(p => url.includes(p))) {
            // 운영자는 토큰이 끝나서 401 이 난 경우 바로 로그아웃시키지 않고 한 번 갱신해서 같은 요청을 다시 보낸다
            // (서버가 갱신을 거부하면 — 비밀번호 변경·정지·기간 경과 — 예전처럼 로그아웃)
            if (window.__akRecoverSession && error.config && !error.config.__akRetried) {
                error.config.__akRetried = true;
                try {
                    const r = await window.__akRecoverSession();
                    if (r.retry) return axios(error.config);
                    if (!r.logout) return Promise.reject(error);
                } catch { /* 아래에서 로그아웃 처리 */ }
            }
            window.dispatchEvent(new Event('ak:auth-401'));
        }
        return Promise.reject(error);
    }
);
