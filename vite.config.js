import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx', 'resources/js/checkin.tsx'],
            refresh: true,
            fonts: [
                bunny('Barlow', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('Barlow Condensed', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('Playfair Display', {
                    weights: [400, 600, 700],
                }),
            ],
        }),
        tailwindcss(),
        react(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
