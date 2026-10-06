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
                // Warm Modern v3 (2026-10, 새 로고 기준): 기존 amber-* 클래스가 그대로
                // 새 로고의 오렌지→핫핑크 그라데이션 계열로 렌더링되도록 amber 팔레트를
                // 오버라이드한다. (이전 v2는 순수 오렌지 #FF5A1F 단색 계열이었음)
                amber: {
                    50: '#FFF1E8',
                    100: '#FFE1D0',
                    200: '#FFC2A8',
                    300: '#FF9478',
                    400: '#FF5C6B',
                    500: '#F23D5C',
                    600: '#D62E4C',
                    700: '#B22440',
                    800: '#8C1C35',
                    900: '#6E162B',
                },
                primary: {
                    DEFAULT: '#F23D5C',
                    soft: '#FFF1E8',
                    dark: '#D62E4C',
                },
                surface: {
                    DEFAULT: '#F8F6F3',
                    card: '#FFFFFF',
                },
                // 웜 블랙 텍스트 스케일 (샘플 v1.0 기준)
                ink: {
                    DEFAULT: '#1B1613',
                    light: '#5C544D',
                    muted: '#9B9189',
                    faint: '#B8AFA7',
                },
                line: '#EDE8E2',
                night: '#211B16',
            },
            fontFamily: {
                sans: ['Pretendard Variable', 'Pretendard', 'Noto Sans KR', 'sans-serif'],
            },
            boxShadow: {
                card: '0 1px 2px rgba(27, 22, 19, 0.04), 0 8px 24px -12px rgba(27, 22, 19, 0.10)',
                lift: '0 2px 4px rgba(27, 22, 19, 0.05), 0 16px 40px -16px rgba(242, 61, 92, 0.18)',
                btn: '0 4px 14px -4px rgba(242, 61, 92, 0.45)',
            },
            borderRadius: {
                card: '18px',
            },
        },
    },
    plugins: [],
}
