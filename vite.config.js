import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    // 배포 때 새 빌드를 임시 폴더에 만든 뒤 한 번에 교체하려고 출력 폴더를 바꿀 수 있게 함 (deploy.sh).
    // 값이 없으면 기존처럼 public/build. 자산 URL(/build/...)은 출력 폴더와 무관하게 그대로.
    ...(process.env.VITE_OUT_DIR ? { build: { outDir: process.env.VITE_OUT_DIR } } : {}),
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
