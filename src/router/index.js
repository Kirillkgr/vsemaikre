import { createRouter, createWebHistory } from 'vue-router'

const ProductCard = () => import('../pages/ProductCard.vue')
const Constructor = () => import('../pages/Constructor.vue')

const routes = [
  { path: '/', redirect: '/product' },
  { path: '/product', component: ProductCard },
  { path: '/constructor', component: Constructor },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router
