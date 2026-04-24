/// <reference types="vitest" />

import vue from '@vitejs/plugin-vue'
import path from 'path'
import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [
    vue(),
    tailwindcss()
  ],
  server: {
    host: 'localhost',
    port: 3000,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  test: {
    globals: true,
    environment: 'jsdom'
  },
  build: {
    target: 'esnext',
    cssTarget: 'chrome80',
    rollupOptions: {
      output: {
        manualChunks: {
          'vendor-ionic': ['@ionic/vue', '@ionic/core'],
          'vendor-vue': ['vue', 'vue-router'],
        }
      }
    }
  }
})