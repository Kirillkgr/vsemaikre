# vsemaikre — Vue 3 T‑shirt Constructor + CS‑Cart Branding Text Addon

Проект включает:
- **Vue 3 конструктор футболок** (стек: Vue 3 + Vite + Vue Router + Konva). Состояние и превью сохраняются в localStorage.
- **CS‑Cart аддон `branding_text`** — прототип плагина для брендирования товара на витрине (загрузка лого, текст, превью, подмена картинок для авторизованных и гостей).

---

## 1. Vue 3 конструктор (фронтенд)

### Требования
- Node.js 20 LTS (рекомендуется)
- npm 9+

### Установка
```bash
# Если есть package-lock.json
npm ci
# Если первый запуск
npm install
```

### Запуск локально (dev)
```bash
npm run dev
```
Откройте ссылку из консоли (обычно http://localhost:5173/). HMR включён.

### Сборка (prod)
```bash
npm run build
```
Готовые файлы будут в папке `dist/`.

### Локальный предпросмотр prod-сборки
```bash
npm run preview
```

### Ключевые возможности конструктора
- Загрузка и редактирование изображения (перетаскивание, масштаб, поворот, фильтры)
- Клиппинг по контуру майки (destination-in), видимая зона печати
- Редактируемый текст (ввод, цвет, размер, прозрачность, перемещение, масштаб, поворот)
- Полная реставрация состояния из localStorage, экспорт чистых превью 256 и 612

---

## 2. CS‑Cart аддон `branding_text` (бэкенд)

### Что делает аддон
- **Конструктор на витрине**: кнопка в карточке товара открывает панель для брендирования (текст + логотип).
- **Загрузка логотипов**: AJAX‑загрузка, превью, фильтры, параметры.
- **Сохранение брендирования**: текст, параметры текста, логотип, PNG‑превью.
- **Подмена картинок на витрине**: серверная замена стоковых изображений на брендированные превью для владельца (авторизованный или гость).
- **Поддержка гостей**: стабильный временный идентификатор (`bt_guest_id`) в cookie/сессии, чтобы гость мог брендировать и видеть свои превью.
- **Совместимость со старыми записями**: используется CS‑Cart session id (`...-1-C`) для поиска старых загрузок/брендингов.
- **Кеширование**: персонализированный кеш блоков `products` по `user_id`/`bt_guest_id` и `bt_cache_bust` для мгновенного обновления превью после сохранения.
- **Темы**: поддержка `responsive` и `bright_theme` (override-шаблоны для корзины/миникорзины).

### Структура аддона
```
app/app/addons/branding_text/
├── addon.xml
├── init.php
├── func.php
├── controllers/frontend/branding_text.php
├── schemas/
│   ├── controllers/controllers.post.php
│   ├── permissions/permissions.post.php
│   └── block_manager/blocks.post.php
js/addons/branding_text/
├── bt_core.js
├── designer.js
├── bt_preview.js
└── vendor/fabric.min.js
design/themes/
├── responsive/templates/addons/branding_text/
│   ├── hooks/index/scripts.post.tpl
│   └── views/branding_text/constructor.tpl
└── bright_theme/templates/addons/branding_text/
    ├── hooks/index/scripts.post.tpl
    └── hooks/checkout/
        ├── product_icon.override.tpl
        └── minicart_product_info.override.tpl
```

### Установка аддона
1) Скопировать `app/`, `js/`, `design/` в корень CS‑Cart.
2) В админке CS‑Cart: Управление → Модули → Установить `branding_text`.
3) Очистить кеш шаблонов и реестра.

### Сборка аддона в zip
```bash
./scripts/build-addon.sh
```
Архив будет в `addon/branding_text-*.zip`. Скрипт проверяет наличие всех критичных файлов и падает с ошибкой, если чего-то не хватает.

---

Примечания:
- `vite.config.js` настраивает `base` динамически для корректной работы под `/<repo>/`
- Добавлен SPA fallback `404.html`, чтобы прямые ссылки не отдавали 404 на Pages

