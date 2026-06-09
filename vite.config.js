import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  publicDir: false,
  server: {
    origin: 'http://localhost:5173',
    cors: true,
  },
  build: {
    manifest: 'manifest.json',
    outDir: 'public/build',
    rollupOptions: {
      input: {
        agdGraph: 'resources/js/agd-graph/app.jsx',
      },
    },
  },
});
