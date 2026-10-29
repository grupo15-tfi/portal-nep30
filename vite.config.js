import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // mapa-sedes.js lleva Leaflet y se carga solo en la página de sedes (#49)
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/mapa-sedes.js'],
            refresh: true,
            // Fuentes del logo: Fraunces (títulos) y Nunito (texto)
            fonts: [
                bunny('Fraunces', {
                    weights: [600, 700],
                }),
                bunny('Nunito', {
                    weights: [400, 600, 700],
                }),
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
