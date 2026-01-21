<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const title = 'Футболка с принтом'
const price = 1990
const description = 'Кастомная футболка с вашим изображением.'

const placeholder = computed(() => {
  // simple inline SVG placeholder 256x256
  const svg = encodeURIComponent(
    `<svg xmlns='http://www.w3.org/2000/svg' width='256' height='256'>` +
      `<rect width='100%' height='100%' fill='#f0f0f0'/>` +
      `<text x='50%' y='50%' dominant-baseline='middle' text-anchor='middle' font-family='Arial' font-size='16' fill='#999'>Нет превью</text>` +
    `</svg>`
  )
  return `data:image/svg+xml;charset=utf-8,${svg}`
})

const previewImage = computed(() => {
  const p = localStorage.getItem('preview256')
  return p || placeholder.value
})

function goToConstructor() {
  router.push('/constructor')
}
</script>

<template>
  <div class="product-card">
    <img :src="previewImage" class="preview" />
    <h1>{{ title }}</h1>
    <p>{{ description }}</p>
    <div class="price">{{ price }} ₽</div>
    <button class="primary" @click="goToConstructor">Изменить</button>
  </div>
</template>

<style scoped>
.product-card {
  max-width: 820px;
  margin: 0 auto;
  padding: 24px 16px 48px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}
.preview {
  width: 256px;
  height: 256px;
  object-fit: cover;
  border: 1px solid #ddd;
  border-radius: 6px;
  background: #fafafa;
}
.price {
  font-weight: 700;
}
button {
  padding: 8px 16px;
  border: 1px solid #ccc;
  background: #fff;
  cursor: pointer;
  border-radius: 6px;
}
button.primary { background: #00c853; color: #fff; border-color: #00c853; }
</style>
