import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { google } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                google('Manrope', {
                    weights: ['200..800'],
                    subsets: ['latin', 'latin-ext', 'cyrillic'],
                }),
                google('Inter', {
                    weights: ['100..900'],
                    subsets: ['latin', 'latin-ext', 'cyrillic'],
                }),
                // google('Instrument Sans', {
                //     weights: ['400..700'],
                //     subsets: ['latin', 'latin-ext'],
                // }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
