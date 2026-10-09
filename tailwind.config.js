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
                // Warm Modern v4 (2026-10, 새 로고 #FC226B 기준): 기존 amber-* 클래스가 그대로
                // 브랜드 핫핑크 계열(#FC226B 중심)로 렌더링되도록 amber 팔레트를
                // 오버라이드한다. (이전 v2는 순수 오렌지 #FF5A1F 단색 계열이었음)
                amber: {
                    50: '#FFF0F5',
                    100: '#FFE0EB',
                    200: '#FFC2D6',
                    300: '#FF8FB4',
                    400: '#FF4F88',
                    500: '#FC226B',
                    600: '#E0145A',
                    700: '#B80F49',
                    800: '#8F0C3A',
                    900: '#6B092C',
                },
                primary: {
                    DEFAULT: '#FC226B',
                    soft: '#FFF0F5',
                    dark: '#E0145A',
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
                lift: '0 2px 4px rgba(27, 22, 19, 0.05), 0 16px 40px -16px rgba(252, 34, 107, 0.18)',
                btn: '0 4px 14px -4px rgba(252, 34, 107, 0.45)',
            },
            borderRadius: {
                card: '18px',
            },
        },
    },
    plugins: [],
}
