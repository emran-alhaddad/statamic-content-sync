import { defineConfig } from 'vite';
import vue2 from '@vitejs/plugin-vue2';
import { resolve } from 'path';

export default defineConfig({
  plugins: [vue2()],
  build: {
    outDir: 'resources/dist',
    emptyOutDir: true,
    rollupOptions: {
      input: { 'content-sync': resolve(__dirname, 'resources/js/addon.js') },
      output: {
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/[name].js',
        assetFileNames: 'css/[name][extname]'
      }
    }
  }
});
