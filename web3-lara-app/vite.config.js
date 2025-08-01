import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        chunkSizeWarningLimit: 1000,
        rollupOptions: {
            output: {
                manualChunks: {
                    'vendor-web3': ['web3'],
                    'vendor-ethers': ['ethers'],
                    'vendor-fontawesome': ['@fortawesome/fontawesome-free/css/all.min.css'],
                    'vendor-alpinejs': ['alpinejs'],
                }
            }
        }
    }
});
