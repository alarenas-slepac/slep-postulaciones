import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({

    server: {
        host: '127.0.0.1',
        port: 5173,
        hmr: { host: '127.0.0.1' },
    },

    plugins: [
        laravel({
            input: [
                'resources/scss/app.scss',
                'resources/js/app.js',
                'resources/css/centro-operaciones.css',
                'resources/js/centro-operaciones.js',
                'resources/css/votaciones-publicas.css',
                'resources/js/votaciones-publicas.js',
                'resources/css/votaciones-admin.css',
                'resources/css/consulta-votacion.css',
                'resources/js/consulta-votacion-mapa.js',
                'resources/js/votaciones-admin.js',
                'resources/js/votaciones-admin-rutas.js',
                'resources/css/dotacion-establecimiento.css',
                'resources/js/dotacion-asignacion.js',
                'resources/css/whatsapp-admin.css',
                'resources/css/notification-events.css',
                'resources/css/pie-comparison.css',
                'resources/js/pie-comparison.js',
                'resources/js/whatsapp-admin.js',
            ],
            refresh: true,
        }),
    ],
});
