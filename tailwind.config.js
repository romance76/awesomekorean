/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",
    ],
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                // Warm Modern 리뉴얼 (2026-06): 기존 amber-* 클래스가 그대로
                // 새 오렌지 스케일로 렌더링되도록 amber 팔레트를 오버라이드한다.
                amber: {
                    50: '#FFF4EF',
                    100: '#FFE6D9',
                    200: '#FFC9AD',
                    300: '#FFA478',
                    400: '#FF6B2C',
                    500: '#F2570F',
                    600: '#D9480A',
                    700: '#B23A08',
                    800: '#8C2D07',
                    900: '#6E2305',
                },
                primary: {
                    DEFAULT: '#FF6B2C',
                    soft: '#FFF1EA',
                    dark: '#F2570F',
                },
                surface: {
                    DEFAULT: '#F7F8FA',
                    card: '#FFFFFF',
                },
                ink: {
                    DEFAULT: '#191F28',
                    light: '#4E5968',
                    muted: '#8B95A1',
                    faint: '#B0B8C1',
                },
            },
            fontFamily: {
                sans: ['Pretendard Variable', 'Pretendard', 'Noto Sans KR', 'sans-serif'],
            },
            boxShadow: {
                card: '0 1px 4px rgba(25, 31, 40, 0.06)',
                lift: '0 8px 20px rgba(25, 31, 40, 0.10)',
                btn: '0 2px 8px rgba(255, 107, 44, 0.25)',
            },
            borderRadius: {
                card: '16px',
            },
        },
    },
    plugins: [],
}
