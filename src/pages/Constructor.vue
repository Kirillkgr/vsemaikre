<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { useRouter } from 'vue-router'
import Konva from 'konva'
// resolve shirt assets via import.meta.url
const maikaUrl = new URL('../assets/maika.png', import.meta.url).href

const router = useRouter()

// Canvas base size (will be overridden responsively)
let STAGE_SIZE = 612
const PRINT_RATIO = 0.92 // print area as 92% of stage side for larger workspace
let PRINT_SIZE = Math.round(STAGE_SIZE * PRINT_RATIO)
let PRINT_X = Math.round((STAGE_SIZE - PRINT_SIZE) / 2)
let PRINT_Y = Math.round((STAGE_SIZE - PRINT_SIZE) / 2)
const stageRef = ref(null)
const layerMaskedRef = ref(null)
const layerOverlayRef = ref(null)
const layerBackgroundRef = ref(null)
let transformer = null
const printOutlineRef = ref(null)

const userImageNode = ref(null)
const maskImageNode = ref(null)
const overlayImageNode = ref(null)
let clipGroup = null

const userImageObj = new window.Image()
const maskImageObj = new window.Image()
const overlayImageObj = new window.Image()
// для пользовательского изображения разрешим кросс-домен, чтобы не таинтить canvas
userImageObj.crossOrigin = 'anonymous'

const uploadedSrc = ref('')

// Filters state
const useGrayscale = ref(false)
const useSepia = ref(false)
const brightness = ref(0) // -1..1
const contrast = ref(0) // -100..100 (Konva uses -100..100)
const saturation = ref(0) // -100..100
const hue = ref(0) // -100..100
const blur = ref(0) // px
const pixelSize = ref(1) // >=1
const noise = ref(0) // 0..1
const posterize = ref(0) // levels 0..255
const invert = ref(false)
const threshold = ref(0) // 0..1
const solarize = ref(false)

// User image opacity (0..1), default 0.8 => 20% transparency
const userOpacity = ref(0.8)

// Restore state if exists
const savedState = (() => {
  try {
    const raw = localStorage.getItem('constructorState')
    return raw ? JSON.parse(raw) : null
  } catch (e) { return null }
})()

// fallback placeholder for maika if not found
const maikaFallback = computed(() => {
  const svg = encodeURIComponent("<svg xmlns='http://www.w3.org/2000/svg' width='612' height='612'><rect width='100%' height='100%' fill='white' fill-opacity='0.0'/><rect x='56' y='56' width='500' height='500' rx='40' ry='40' fill='#eaeaea'/></svg>")
  return `data:image/svg+xml;utf8,${svg}`
})

function applyFilters() {
  if (!userImageNode.value || !userImageNode.value.image()) return
  const filters = []
  if (useGrayscale.value) filters.push(Konva.Filters.Grayscale)
  if (useSepia.value) filters.push(Konva.Filters.Sepia)
  if (brightness.value !== 0) filters.push(Konva.Filters.Brighten)
  if (contrast.value !== 0) filters.push(Konva.Filters.Contrast)
  if (saturation.value !== 0 || hue.value !== 0) filters.push(Konva.Filters.HSL)
  if (blur.value > 0) filters.push(Konva.Filters.Blur)
  if (pixelSize.value > 1) filters.push(Konva.Filters.Pixelate)
  if (noise.value > 0) filters.push(Konva.Filters.Noise)
  if (posterize.value > 0) filters.push(Konva.Filters.Posterize)
  if (invert.value) filters.push(Konva.Filters.Invert)
  if (threshold.value > 0) filters.push(Konva.Filters.Threshold)
  if (solarize.value) filters.push(Konva.Filters.Solarize)
  userImageNode.value.filters(filters)
  userImageNode.value.brightness(brightness.value)
  userImageNode.value.contrast(contrast.value)
  userImageNode.value.saturation(saturation.value)
  userImageNode.value.hue(hue.value)
  userImageNode.value.blurRadius(blur.value)
  userImageNode.value.pixelSize(Math.max(1, Math.round(pixelSize.value)))
  userImageNode.value.noise(noise.value)
  if (posterize.value > 0) userImageNode.value.levels(Math.round(posterize.value))
  userImageNode.value.threshold(threshold.value)
  // cache is required for filters to work efficiently
  if (!userImageNode.value.isCached()) {
    userImageNode.value.cache()
  } else {
    userImageNode.value.cache()
  }
  userImageNode.value.getLayer()?.batchDraw()
}

function persistState() {
  if (!userImageNode.value) return
  const state = {
    x: userImageNode.value.x(),
    y: userImageNode.value.y(),
    // сохраняем независимые масштабы по осям, чтобы не терять «растягивание/сжатие»
    scaleX: userImageNode.value.scaleX(),
    scaleY: userImageNode.value.scaleY(),
    rotation: userImageNode.value.rotation?.() ?? 0,
    useGrayscale: useGrayscale.value,
    useSepia: useSepia.value,
    brightness: brightness.value,
    contrast: contrast.value,
    saturation: saturation.value,
    hue: hue.value,
    blur: blur.value,
    pixelSize: pixelSize.value,
    noise: noise.value,
    posterize: posterize.value,
    invert: invert.value,
    threshold: threshold.value,
    solarize: solarize.value,
    opacity: userOpacity.value,
    uploadedSrc: uploadedSrc.value,
  }
  try { localStorage.setItem('constructorState', JSON.stringify(state)) } catch (e) {}
}

function restoreState() {
  if (!savedState || !userImageNode.value) return
  userImageNode.value.position({ x: savedState.x ?? STAGE_SIZE/2, y: savedState.y ?? STAGE_SIZE/2 })
  // поддерживаем старый формат (scale) и новый (scaleX/scaleY)
  const sx = (typeof savedState.scaleX === 'number') ? savedState.scaleX : (savedState.scale ?? 1)
  const sy = (typeof savedState.scaleY === 'number') ? savedState.scaleY : (savedState.scale ?? 1)
  userImageNode.value.scale({ x: sx, y: sy })
  if (typeof savedState.rotation === 'number') {
    userImageNode.value.rotation(savedState.rotation)
  }
  useGrayscale.value = !!savedState.useGrayscale
  useSepia.value = !!savedState.useSepia
  brightness.value = Number(savedState.brightness || 0)
  contrast.value = Number(savedState.contrast || 0)
  saturation.value = Number(savedState.saturation || 0)
  hue.value = Number(savedState.hue || 0)
  blur.value = Number(savedState.blur || 0)
  pixelSize.value = Number(savedState.pixelSize || 1)
  noise.value = Number(savedState.noise || 0)
  posterize.value = Number(savedState.posterize || 0)
  invert.value = !!savedState.invert
  threshold.value = Number(savedState.threshold || 0)
  solarize.value = !!savedState.solarize
  userOpacity.value = Number(savedState.opacity ?? 0.8)
  if (savedState.uploadedSrc) {
    uploadedSrc.value = savedState.uploadedSrc
    userImageObj.src = uploadedSrc.value
  }
}

function onFileChange(e) {
  const file = e.target.files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => {
    uploadedSrc.value = reader.result
    userImageObj.src = uploadedSrc.value
    persistState()
  }
  reader.readAsDataURL(file)
}

function onWheel(e) {
  e.evt.preventDefault()
  const scaleBy = 1.05
  const oldScale = userImageNode.value.scaleX()
  const pointer = stageRef.value.getPointerPosition()
  const mousePointTo = {
    x: (pointer.x - userImageNode.value.x()) / oldScale,
    y: (pointer.y - userImageNode.value.y()) / oldScale,
  }
  const direction = e.evt.deltaY > 0 ? -1 : 1
  let newScale = direction > 0 ? oldScale * scaleBy : oldScale / scaleBy
  newScale = Math.min(5, Math.max(0.1, newScale))
  userImageNode.value.scale({ x: newScale, y: newScale })
  const newPos = {
    x: pointer.x - mousePointTo.x * newScale,
    y: pointer.y - mousePointTo.y * newScale,
  }
  userImageNode.value.position(newPos)
  userImageNode.value.getLayer()?.batchDraw()
  persistState()
}

function onDragEnd() {
  persistState()
}

function setupKonva() {
  // Stage
  const stage = new Konva.Stage({
    container: 'konva-stage',
    width: STAGE_SIZE,
    height: STAGE_SIZE,
  })
  stageRef.value = stage

  // Layer where masking occurs: user image + mask image (destination-in)
  const layerMasked = new Konva.Layer()
  layerMaskedRef.value = layerMasked

  const userImg = new Konva.Image({
    x: STAGE_SIZE / 2,
    y: STAGE_SIZE / 2,
    offsetX: 0,
    offsetY: 0,
    draggable: true,
    listening: true,
  })
  userImg.on('dragstart', () => { userImg.opacity(Math.max(0.1, userOpacity.value - 0.1)); layerMasked.batchDraw() })
  userImg.on('dragend', () => { userImg.opacity(userOpacity.value); onDragEnd() })
  userImg.on('wheel', onWheel)
  userImg.on('transformstart', () => { userImg.opacity(Math.max(0.1, userOpacity.value - 0.1)); layerMasked.batchDraw() })
  userImg.on('transformend', () => { userImg.opacity(userOpacity.value); persistState(); layerMasked.batchDraw() })
  userImageNode.value = userImg

  // Ограничиваем область печати квадратом по центру
  PRINT_SIZE = Math.round(STAGE_SIZE * PRINT_RATIO)
  PRINT_X = Math.round((STAGE_SIZE - PRINT_SIZE) / 2)
  PRINT_Y = Math.round((STAGE_SIZE - PRINT_SIZE) / 2)
  clipGroup = new Konva.Group({ clip: { x: PRINT_X, y: PRINT_Y, width: PRINT_SIZE, height: PRINT_SIZE } })
  clipGroup.listening(true)
  clipGroup.add(userImg)
  // Маска: используем альфу maika.png, добавляем в ТОТ ЖЕ clipGroup ПОСЛЕ userImg, чтобы применился destination-in к содержимому группы
  const maskImg = new Konva.Image({ x: 0, y: 0, width: STAGE_SIZE, height: STAGE_SIZE, listening: false })
  maskImg.globalCompositeOperation('destination-in')
  clipGroup.add(maskImg)
  layerMasked.add(clipGroup)
  maskImageNode.value = maskImg

  // Фоновая майка под пользовательским изображением (для фона)
  const layerBackground = new Konva.Layer()
  layerBackgroundRef.value = layerBackground
  const bgImg = new Konva.Image({ x: 0, y: 0, width: STAGE_SIZE, height: STAGE_SIZE, listening: false, opacity: 1 })
  layerBackground.add(bgImg)

  // Transformer for resizing/rotating user image
  transformer = new Konva.Transformer({
    rotateEnabled: true,
    enabledAnchors: ['top-left','top-right','bottom-left','bottom-right','middle-left','middle-right','top-center','bottom-center'],
    boundBoxFunc: (oldBox, newBox) => {
      // minimum size
      const minSize = 30
      if (newBox.width < minSize || newBox.height < minSize) return oldBox
      return newBox
    }
  })
  layerMasked.add(transformer)

  // Overlay layer draws the shirt on top with some translucency
  const layerOverlay = new Konva.Layer()
  layerOverlayRef.value = layerOverlay
  const overlayImg = new Konva.Image({ x: 0, y: 0, width: STAGE_SIZE, height: STAGE_SIZE, opacity: 1, listening: false })
  overlayImageNode.value = overlayImg
  // Visual print area outline (for clarity)
  const printOutline = new Konva.Rect({ x: PRINT_X, y: PRINT_Y, width: PRINT_SIZE, height: PRINT_SIZE, stroke: '#3b82f6', dash: [6, 4], listening: false, shadowForStrokeEnabled: false })
  printOutlineRef.value = printOutline

  // порядок: фон майки -> пользовательское изображение + маска -> оверлей майки
  stage.add(layerBackground)
  stage.add(layerMasked)
  stage.add(layerOverlay)
  layerOverlay.add(printOutline)

  // Load images
  userImageObj.onload = () => {
    userImg.image(userImageObj)
    // задаем размеры и хит-область, чтобы можно было хватать даже прозрачные участки
    userImg.width(userImageObj.width)
    userImg.height(userImageObj.height)
    userImg.hitFunc((ctx, shape) => {
      ctx.beginPath()
      ctx.rect(0, 0, shape.width(), shape.height())
      ctx.closePath()
      ctx.fillStrokeShape(shape)
    })
    if (!savedState) {
      // Подгоняем под квадрат печати, сохраняя пропорции картинки
      const scale = Math.min(PRINT_SIZE / userImageObj.width, PRINT_SIZE / userImageObj.height)
      userImg.scale({ x: scale, y: scale })
      // центрируем в квадрате печати
      const imgW = userImageObj.width * scale
      const imgH = userImageObj.height * scale
      userImg.position({ x: PRINT_X + (PRINT_SIZE - imgW) / 2, y: PRINT_Y + (PRINT_SIZE - imgH) / 2 })
    }
    userImg.opacity(userOpacity.value)
    applyFilters()
    transformer.nodes([userImg])
    layerMasked.batchDraw()
  }

  // Маску временно не используем, оставляем только оверлей
  const setMask = () => {}

  const setOverlay = (src) => {
    overlayImageObj.onload = () => {
      overlayImg.image(overlayImageObj)
      layerOverlay.batchDraw()
    }
    overlayImageObj.onerror = () => {
      overlayImageObj.src = maikaFallback.value
    }
    overlayImageObj.src = src
  }

  // Устанавливаем изображение для маски (используем альфу файла майки)
  const setMaskFromMaika = (src) => {
    const img = new window.Image()
    img.onload = () => {
      // Проверим, есть ли альфа-канал. Если нет — не применяем маску (используем только оверлей),
      // чтобы не «съедать» изображение пользователя целиком
      const canvas = document.createElement('canvas')
      canvas.width = STAGE_SIZE
      canvas.height = STAGE_SIZE
      const ctx = canvas.getContext('2d')
      ctx.drawImage(img, 0, 0, STAGE_SIZE, STAGE_SIZE)
      const imageData = ctx.getImageData(0, 0, STAGE_SIZE, STAGE_SIZE)
      const data = imageData.data
      let hasAlpha = false
      for (let i = 3; i < data.length; i += 4) { if (data[i] < 255) { hasAlpha = true; break } }
      if (!hasAlpha) {
        // Нет альфы — отключаем маску
        maskImg.image(null)
        layerMasked.batchDraw()
        return
      }
      // Есть альфа — используем её как маску напрямую
      const tmp = new window.Image()
      tmp.onload = () => { maskImg.image(tmp); layerMasked.batchDraw() }
      tmp.src = canvas.toDataURL()
    }
    img.src = src
  }

  const setBackground = (src) => {
    const img = new window.Image()
    img.onload = () => { bgImg.image(img); layerBackground.batchDraw() }
    img.src = src
  }

  setBackground(maikaUrl)
  // установить маску напрямую из maika.png (используем её прозрачность)
  const setMaskFromPngAlpha = (src) => {
    const img = new window.Image()
    img.onload = () => { maskImg.image(img); layerMasked.batchDraw() }
    img.src = src
  }
  setMaskFromPngAlpha(maikaUrl)
  setOverlay(maikaUrl)

  // zoom on stage for better UX
  stage.on('wheel', onWheel)
  // click to select/deselect transformer
  stage.on('mousedown', (e) => {
    const clickedOnEmpty = e.target === stage || e.target === layerMasked
    if (clickedOnEmpty) {
      transformer.nodes([])
    } else {
      // if clicked on user image, select it
      if (e.target === userImg) {
        transformer.nodes([userImg])
      }
    }
    layerMasked.batchDraw()
  })

  // Restore any saved state (position/scale/filters)
  restoreState()

  // Observe work area and resize stage to fill available right area squarely
  const container = document.getElementById('konva-stage')
  const stageWrap = document.querySelector('.stage-wrap')
  const workArea = document.querySelector('.constructor .work')
  const sidebarArea = document.querySelector('.constructor .sidebar')
  let resizeScheduled = false
  const ro = new ResizeObserver(() => {
    if (resizeScheduled) return
    resizeScheduled = true
    requestAnimationFrame(() => { resizeStage(); resizeScheduled = false })
  })
  // наблюдаем рабочую секцию, чтобы реагировать на любые изменения лэйаута
  ro.observe(workArea || stageWrap || container)
  // наблюдаем левую панель и подгоняем min-height правой области под её высоту, чтобы низы совпадали
  if (sidebarArea && workArea) {
    const roSidebar = new ResizeObserver(() => {
      workArea.style.minHeight = `${sidebarArea.clientHeight}px`
    })
    roSidebar.observe(sidebarArea)
    // первичная установка
    workArea.style.minHeight = `${sidebarArea.clientHeight}px`
  }
  // первичный пересчет после монтирования и подписок
  requestAnimationFrame(() => resizeStage())

  function resizeStage() {
    // Откат к стабильной логике: размер сцены равен видимой ширине контейнера (квадрат через CSS aspect-ratio)
    const width = Math.floor(container.clientWidth)
    if (!width || width <= 0) return
    const size = width
    if (size === STAGE_SIZE) return
    const prevSize = STAGE_SIZE
    STAGE_SIZE = size
    stage.size({ width: STAGE_SIZE, height: STAGE_SIZE })
    // не трогаем размеры DOM-контейнера, ими управляет CSS (width:100% + aspect-ratio)
    // update overlay/mask sizes
    overlayImageNode.value?.size({ width: STAGE_SIZE, height: STAGE_SIZE })
    maskImageNode.value?.size({ width: STAGE_SIZE, height: STAGE_SIZE })
    layerBackgroundRef.value?.getChildren()?.each?.(n => n.size && n.size({ width: STAGE_SIZE, height: STAGE_SIZE }))
    // update print area clip rect
    PRINT_SIZE = Math.round(STAGE_SIZE * PRINT_RATIO)
    PRINT_X = Math.round((STAGE_SIZE - PRINT_SIZE) / 2)
    PRINT_Y = Math.round((STAGE_SIZE - PRINT_SIZE) / 2)
    clipGroup.clip({ x: PRINT_X, y: PRINT_Y, width: PRINT_SIZE, height: PRINT_SIZE })
    printOutline.position({ x: PRINT_X, y: PRINT_Y })
    printOutline.size({ width: PRINT_SIZE, height: PRINT_SIZE })
    // rebuild background and mask at new resolution for crisper edges
    setBackground(maikaUrl)
    setMaskFromPngAlpha(maikaUrl)
    // scale and reposition current user image proportionally; then ensure центр только если рамка выходит за границы
    if (userImageNode.value && userImageNode.value.image()) {
      const k = STAGE_SIZE / prevSize
      const sx = userImageNode.value.scaleX() * k
      const sy = userImageNode.value.scaleY() * k
      userImageNode.value.scale({ x: sx, y: sy })
      userImageNode.value.position({
        x: userImageNode.value.x() * k,
        y: userImageNode.value.y() * k,
      })
      // keep inside and center only if overflow
      const imgW = userImageNode.value.width() * sx
      const imgH = userImageNode.value.height() * sy
      const bx = userImageNode.value.x()
      const by = userImageNode.value.y()
      const overflows = (
        bx < PRINT_X ||
        by < PRINT_Y ||
        bx + imgW > PRINT_X + PRINT_SIZE ||
        by + imgH > PRINT_Y + PRINT_SIZE
      )
      if (overflows) {
        userImageNode.value.position({
          x: PRINT_X + (PRINT_SIZE - imgW) / 2,
          y: PRINT_Y + (PRINT_SIZE - imgH) / 2,
        })
      }
      transformer?.forceUpdate()
    }
    stage.batchDraw()
  }
  // пересчитываем при изменении окна
  window.addEventListener('resize', () => { requestAnimationFrame(resizeStage) })
}

function savePreviews() {
  const stage = stageRef.value
  if (!stage) return
  try {
    // Спрятать рамку печати и снять выделение перед экспортом
    const po = printOutlineRef.value
    const wasVisible = po?.isVisible?.() ?? false
    if (po) { po.visible(false); po.getLayer()?.batchDraw() }
    // снять выделение и скрыть трансформер
    if (transformer) { transformer.nodes([]); transformer.getLayer()?.batchDraw() }

    const data256 = stage.toDataURL({ pixelRatio: 256 / STAGE_SIZE })
    const data612 = stage.toDataURL({ pixelRatio: 612 / STAGE_SIZE })
    localStorage.setItem('preview256', data256)
    localStorage.setItem('preview612', data612)

    // вернуть рамку печати обратно (если пользователь останется на странице)
    if (po) { po.visible(wasVisible); po.getLayer()?.batchDraw() }
  } catch (e) {}
  persistState()
  router.push('/product')
}

onMounted(() => {
  setupKonva()
})

watch([useGrayscale, useSepia, brightness, contrast, saturation, hue, blur, pixelSize, noise, posterize, invert, threshold, solarize], applyFilters)
watch(userOpacity, (v) => {
  if (userImageNode.value) {
    userImageNode.value.opacity(Math.max(0, Math.min(1, v)))
    userImageNode.value.getLayer()?.batchDraw()
    persistState()
  }
})

function applyPreset(name) {
  // basic presets similar to typical print editors
  if (name === 'bw') {
    useGrayscale.value = true
    useSepia.value = false
    brightness.value = 0
    contrast.value = 20
    saturation.value = -100
    hue.value = 0
    blur.value = 0
    pixelSize.value = 1
    noise.value = 0
    posterize.value = 0
    invert.value = false
    threshold.value = 0
    solarize.value = false
  } else if (name === 'vivid') {
    useGrayscale.value = false
    useSepia.value = false
    brightness.value = 0.05
    contrast.value = 25
    saturation.value = 35
    hue.value = 0
    blur.value = 0
    pixelSize.value = 1
    noise.value = 0
    posterize.value = 0
    invert.value = false
    threshold.value = 0
    solarize.value = false
  } else if (name === 'sepia') {
    useGrayscale.value = false
    useSepia.value = true
    brightness.value = 0.05
    contrast.value = 10
    saturation.value = 10
    hue.value = 0
    blur.value = 0
    pixelSize.value = 1
    noise.value = 0
    posterize.value = 0
    invert.value = false
    threshold.value = 0
    solarize.value = false
  } else if (name === 'noisy') {
    useGrayscale.value = false
    useSepia.value = false
    brightness.value = 0
    contrast.value = 0
    saturation.value = 0
    hue.value = 0
    blur.value = 0
    pixelSize.value = 1
    noise.value = 0.25
    posterize.value = 0
    invert.value = false
    threshold.value = 0
    solarize.value = false
  }
  applyFilters()
  persistState()
}

function resetPosition() {
  if (!userImageNode.value || !userImageObj.width) return
  const PRINT_SIZE = 512
  const PRINT_X = (STAGE_SIZE - PRINT_SIZE) / 2
  const PRINT_Y = (STAGE_SIZE - PRINT_SIZE) / 2
  const scale = Math.min(PRINT_SIZE / userImageObj.width, PRINT_SIZE / userImageObj.height)
  userImageNode.value.scale({ x: scale, y: scale })
  const imgW = userImageObj.width * scale
  const imgH = userImageObj.height * scale
  userImageNode.value.position({ x: PRINT_X + (PRINT_SIZE - imgW) / 2, y: PRINT_Y + (PRINT_SIZE - imgH) / 2 })
  userImageNode.value.getLayer()?.batchDraw()
  persistState()
}

function onCancel() {
  try { localStorage.removeItem('constructorState') } catch (e) {}
  // also clear in-memory ref so при следующем входе начнется с чистого листа
  uploadedSrc.value = ''
  router.push('/product')
}
</script>

<template>
  <div class="constructor layout">
    <aside class="sidebar">
      <div class="group">
        <label class="file">
          <input type="file" accept="image/*" @change="onFileChange" />
        </label>
        <button class="primary" @click="savePreviews">Сохранить</button>
        <button @click="onCancel">Отмена</button>
        <button @click="resetPosition">Сбросить позицию</button>
      </div>
      <div class="presets">
        <span>Пресеты:</span>
        <button @click="applyPreset('bw')">Ч/Б контраст</button>
        <button @click="applyPreset('vivid')">Яркие цвета</button>
        <button @click="applyPreset('sepia')">Сепия</button>
        <button @click="applyPreset('noisy')">С шумом</button>
      </div>
      <div class="filters">
        <label class="range">Прозрачность изображения <input type="range" min="0" max="1" step="0.01" v-model.number="userOpacity" /></label>
        <label><input type="checkbox" v-model="useGrayscale" /> Grayscale</label>
        <label><input type="checkbox" v-model="useSepia" /> Sepia</label>
        <label class="range">Яркость <input type="range" min="-0.25" max="0.25" step="0.01" v-model.number="brightness" /></label>
        <label class="range">Контраст <input type="range" min="-40" max="40" step="1" v-model.number="contrast" /></label>
        <label class="range">Насыщенность <input type="range" min="-50" max="50" step="1" v-model.number="saturation" /></label>
        <label class="range">Оттенок <input type="range" min="-30" max="30" step="1" v-model.number="hue" /></label>
        <label class="range">Размытие <input type="range" min="0" max="10" step="1" v-model.number="blur" /></label>
        <label class="range">Пикселизация <input type="range" min="1" max="10" step="1" v-model.number="pixelSize" /></label>
        <label class="range">Шум <input type="range" min="0" max="0.2" step="0.01" v-model.number="noise" /></label>
        <label class="range">Постеризация <input type="range" min="0" max="8" step="1" v-model.number="posterize" /></label>
        <label><input type="checkbox" v-model="invert" /> Invert</label>
        <label><input type="checkbox" v-model="solarize" /> Solarize</label>
        <label class="range">Порог <input type="range" min="0" max="0.4" step="0.01" v-model.number="threshold" /></label>
      </div>
    </aside>
    <section class="work">
      <div class="stage-wrap">
        <div id="konva-stage" class="stage"></div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.constructor.layout {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 16px;
  width: 100%;
  max-width: none;
  margin: 0;
  padding: 12px;
  box-sizing: border-box;
  min-height: 100vh;
}
.sidebar { display: flex; flex-direction: column; gap: 12px; padding-bottom: 16px; }
.toolbar .group, .sidebar .group { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.presets { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.presets > span { color: #111; font-weight: 600; }
.filters { display: grid; grid-template-columns: 1fr; gap: 8px; background: #fff; padding: 12px; border-radius: 8px; border: 1px solid #e3e6ea; color: #111; }
.work { display: flex; align-items: flex-start; justify-content: center; height: auto; min-height: 0; }
.stage-wrap { width: 100%; display: flex; justify-content: center; align-items: center; padding: 8px; padding-bottom: 16px; box-sizing: border-box; }
.stage { border: 1px solid #e3e6ea; border-radius: 14px; background: #f7f8fa; box-shadow: 0 8px 24px rgba(0,0,0,0.18); }

/* Улучшаем внешний вид: адаптивная сетка, карточки, кнопки */
.sidebar .group > button, .sidebar .group > .file input[type='file'] {
  font-size: 14px;
}
.filters { box-shadow: 0 4px 14px rgba(0,0,0,0.08); }
@media (max-width: 1100px) {
  .constructor.layout { grid-template-columns: 280px 1fr; padding: 8px; }
}
@media (max-width: 860px) {
  .constructor.layout { grid-template-columns: 1fr; }
  .work { order: 2; }
  .sidebar { order: 1; }
}
input[type='file'] {
  padding: 6px 0;
}
button {
  padding: 6px 12px;
  border: 1px solid #ccc;
  background: #fff;
  border-radius: 6px;
  cursor: pointer;
}
button.primary { background: #00c853; color: #fff; border-color: #00c853; }
</style>
