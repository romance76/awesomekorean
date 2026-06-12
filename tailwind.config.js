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
                // Warm Modern v2 (2026-06, Kay 샘플 기반): 기존 amber-* 클래스가 그대로
                // 새 브랜드 오렌지(#FF5A1F) 스케일로 렌더링되도록 amber 팔레트를 오버라이드한다.
                amber: {
                    50: '#FFF1EA',
                    100: '#FFE3D5',
                    200: '#FFC5A8',
                    300: '#FF9A6B',
                    400: '#FF5A1F',
                    500: '#F24A0E',
                    600: '#D63E08',
                    700: '#B23306',
                    800: '#8C2805',
                    900: '#6E2004',
                },
                primary: {
                    DEFAULT: '#FF5A1F',
                    soft: '#FFF1EA',
                    dark: '#F24A0E',
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
                lift: '0 2px 4px rgba(27, 22, 19, 0.05), 0 16px 40px -16px rgba(255, 90, 31, 0.18)',
                btn: '0 4px 14px -4px rgba(255, 90, 31, 0.45)',
            },
            borderRadius: {
                card: '18px',
            },
        },
    },
    plugins: [],
}
