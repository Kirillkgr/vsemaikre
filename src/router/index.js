import { createRouter, createWebHistory } from 'vue-router'

const ProductCard = () => import('../pages/ProductCard.vue')
const Constructor = () => import('../pages/Constructor.vue')

const routes = [
  { path: '/', redirect: '/product' },
  { path: '/product', component: ProductCard },
  { path: '/constructor', component: Constructor },
]

const router = createRouter({
  // важно: используем BASE_URL от Vite, чтобы роутинг корректно работал на GitHub Pages
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

export default router
