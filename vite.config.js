import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const isDev = mode === 'development'
  return {
    plugins: [
      vue(),
      // devtools включаем только в режиме разработки, чтобы не ломать прод-сборку на Pages
      ...(isDev ? [vueDevTools()] : []),
    ],
    // Базовый путь для GitHub Pages: /vsemaikre/ на CI, локально — '/'
    base: process.env.GITHUB_REPOSITORY
      ? `/${process.env.GITHUB_REPOSITORY.split('/').pop()}/`
      : '/',
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url))
      },
    },
  }
})
