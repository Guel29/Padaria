import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, '.', 'DEV_');
  const previewUrl = env.DEV_PREVIEW_URL ? new URL(env.DEV_PREVIEW_URL) : undefined;
  return {
    base: mode === 'production' ? '/Padaria/' : '/',
    plugins: [react(), tailwindcss(), VitePWA({
      registerType: 'prompt',
      injectRegister: false,
      includeAssets: ['icon.svg', 'icon-192.png', 'icon-512.png'],
      manifest: {
        id: '/Padaria/',
        name: 'Padaria do Supermercado — Comandas',
        short_name: 'Padaria',
        description: 'Demonstração de atendimento e comandas da padaria.',
        lang: 'pt-BR',
        start_url: '/Padaria/',
        scope: '/Padaria/',
        display: 'standalone',
        background_color: '#faf7f2',
        theme_color: '#663006',
        icons: [
          { src: 'icon-192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
          { src: 'icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
          { src: 'icon.svg', sizes: 'any', type: 'image/svg+xml', purpose: 'any' },
        ],
      },
      workbox: {
        cleanupOutdatedCaches: true,
        navigateFallback: '/Padaria/index.html',
        globPatterns: ['**/*.{js,css,html,svg,png,ico}'],
        // Product images are illustrative; don't cache third-party requests.
        runtimeCaching: [],
      },
      devOptions: { enabled: false },
    })],
    server: {
      host: '0.0.0.0',
      port: 5173,
      strictPort: true,
      headers: { 'Cache-Control': 'no-store' },
      allowedHosts: previewUrl ? [previewUrl.hostname] : [],
      hmr: previewUrl ? {
        host: previewUrl.hostname,
        protocol: previewUrl.protocol === 'https:' ? 'wss' : 'ws',
        clientPort: Number(previewUrl.port || (previewUrl.protocol === 'https:' ? 443 : 80)),
      } : true,
      watch: env.DEV_USE_POLLING === 'true' ? { usePolling: true, interval: 300 } : undefined,
    },
    preview: { host: '0.0.0.0', port: 4173, strictPort: true },
  };
});
