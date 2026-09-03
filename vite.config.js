import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/admin.css',
                'resources/css/auth.css',
                'resources/css/register.css',
                'resources/css/modules/dashboard.css',
                'resources/css/modules/cobranzas.css',
                'resources/css/modules/ventas.css',
                'resources/css/modules/ordenes-compra.css',
                'resources/css/modules/facturas.css',
                'resources/css/modules/productos.css',
                'resources/css/modules/galonaje.css',
                'resources/css/modules/historial-pagos.css',
                'resources/css/modules/transporte.css',
                'resources/css/modules/pos.css',
                'resources/js/app.js',
                'resources/js/glow-cursor.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
