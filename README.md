# vsemaikre — Vue 3 T‑shirt Constructor

Короткая инструкция по установке, запуску и деплою. Стек: Vue 3 + Vite + Vue Router + Konva. Превью и состояние сохраняются в localStorage.

## Требования
- Node.js 20 LTS (рекомендуется)
- npm 9+

Проверка версий:
```
node -v
npm -v
```

## Установка
- Если есть package-lock.json:
```
npm ci
```
- Если первый запуск (нет lock-файла):
```
npm install
```

## Запуск локально (dev)
```
npm run dev
```
Откройте ссылку из консоли (обычно http://localhost:5173/). HMR включён.

## Сборка (prod)
```
npm run build
```
Готовые файлы будут в папке dist/.

## Локальный предпросмотр prod-сборки
```
npm run preview
```
Откройте адрес из консоли (обычно http://localhost:4173/).

## Ключевые возможности
- Загрузка и редактирование изображения (перетаскивание, масштаб, поворот, фильтры)
- Клиппинг по контуру майки (destination-in), видимая зона печати
- Редактируемый текст (ввод, цвет, размер, прозрачность, перемещение, масштаб, поворот), центрирование при первом появлении
- Полная реставрация состояния из localStorage, экспорт чистых превью 256 и 612

## Деплой на GitHub Pages
Проект содержит workflow .github/workflows/gh-pages.yml, который деплоит на Pages при push в master.

Шаги:
1) Включите Pages: Settings → Pages → Source: GitHub Actions
2) Закоммитьте и запушьте:
```
git add .
git commit -m "deploy"
git push origin master
```
3) Дождитесь успешного workflow. Сайт будет по адресу:
```
https://<user>.github.io/<repo>/
```

Примечания:
- vite.config.js настраивает base динамически для корректной работы под /<repo>/
- Добавлен SPA fallback 404.html, чтобы прямые ссылки не отдавали 404 на Pages

## Частые проблемы
- Пустая страница на Pages → проверьте base в vite.config.js и что devtools отключены в проде
- 404 на вложенных маршрутах → проверьте, что задеплоен 404.html (SPA fallback)
# vsemaikre

This template should help get you started developing with Vue 3 in Vite.

## Recommended IDE Setup

[VS Code](https://code.visualstudio.com/) + [Vue (Official)](https://marketplace.visualstudio.com/items?itemName=Vue.volar) (and disable Vetur).

## Recommended Browser Setup

- Chromium-based browsers (Chrome, Edge, Brave, etc.):
  - [Vue.js devtools](https://chromewebstore.google.com/detail/vuejs-devtools/nhdogjmejiglipccpnnnanhbledajbpd)
  - [Turn on Custom Object Formatter in Chrome DevTools](http://bit.ly/object-formatters)
- Firefox:
  - [Vue.js devtools](https://addons.mozilla.org/en-US/firefox/addon/vue-js-devtools/)
  - [Turn on Custom Object Formatter in Firefox DevTools](https://fxdx.dev/firefox-devtools-custom-object-formatters/)

## Customize configuration

See [Vite Configuration Reference](https://vite.dev/config/).

## Project Setup

```sh
npm install
```

### Compile and Hot-Reload for Development

```sh
npm run dev
```

### Compile and Minify for Production

```sh
npm run build
```
