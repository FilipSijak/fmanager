import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import biome from 'vite-plugin-biome';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';

export default defineConfig(({mode}) => {
    // eslint-disable-next-line no-undef
    const env = loadEnv(mode, process.cwd());
    const wayfinderEnabled = env.VITE_WAYFINDER_ENABLED === '1' || false;

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.tsx'],
                ssr: 'resources/js/ssr.tsx',
                refresh: true,
            }),
            react({
                babel: {
                    plugins: ['babel-plugin-react-compiler'],
                },
            }),
            tailwindcss(),
            wayfinderEnabled ? wayfinder({
                formVariants: true,
                command: 'docker exec fmanager-container php artisan wayfinder:generate'
            }) : '',
            biome({
                mode: 'check',
                files: './resources/js',
                applyFixes: false,
                failOnError: false,
            }),
        ],
        esbuild: {
            jsx: 'automatic',
        },
        server: {
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
            cors: {
                origin: '*',
                credentials: true,
            },
            host: true,
            port: 3000,
            strictPort: true,
            hmr: {
                host: 'localhost',
                clientPort: 5173,
            },
        },
    };
});
