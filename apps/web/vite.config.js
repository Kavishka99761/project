import { fileURLToPath, URL } from 'node:url';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  css: {
    preprocessorOptions: {
      scss: {
        // Bootstrap 5.3's Sass still uses @import and global functions; keep the
        // build output readable until Bootstrap 6 migrates to the module system.
        quietDeps: true,
        silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'mixed-decls', 'if-function'],
      },
    },
  },
  server: {
    port: 5173,
    host: '127.0.0.1',
  },
  preview: {
    port: 4173,
  },
  build: {
    sourcemap: true,
    chunkSizeWarningLimit: 900,
    rollupOptions: {
      output: {
        manualChunks: {
          react: ['react', 'react-dom', 'react-router-dom'],
          bootstrap: ['react-bootstrap'],
          charts: ['chart.js', 'react-chartjs-2'],
          firebase: ['firebase/app', 'firebase/auth', 'firebase/firestore'],
          motion: ['framer-motion'],
        },
      },
    },
  },
});
