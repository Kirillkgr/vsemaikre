# Отчёт по аддону `brending_wizart`

## 1) Назначение аддона
Аддон `brending_wizart` реализует «мастер» (wizard) для быстрого запуска витрины продавца и базовой настройки оформления.

Цели:
- Создать продавца (vendor) и пользователя-продавца из публичной формы.
- Создать/настроить витрину (storefront) продавца.
- Дать продавцу экран «Мой магазин» в vendor panel с:
  - предпросмотром витрины,
  - настройкой цветов (фон/акцент/текст) с live-preview,
  - загрузкой логотипов (для шапки витрины и для списка продавцов).

## 2) Пользовательские сценарии (мастера)

### 2.1) Мастер «Купить магазин» на витрине (frontend)
Точка входа: `dispatch=brending_wizart.buy`.

Поток:
1. Пользователь вводит данные (ник/логин, пароль, описание, выбор цветов, загрузка логотипов).
2. Создаётся компания-продавец (vendor) через ядро.
3. Создаётся пользователь, привязанный к компании.
4. Генерируется одноразовый `ekey` для безопасного входа.
5. Создаётся витрина (storefront) на основе дефолтной.
6. Сохраняются настройки брендинга (цвета) в таблицу аддона `?:brending_wizart_storefront_settings` по `storefront_id`.
7. Загружаются логотипы, приводятся к нужным размерам (ресайз) и сохраняются через механизм логотипов ядра.
8. Пользователь перенаправляется в vendor panel, где он входит по `auth.ekey_login`.

Файлы:
- `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`
- `app/design/themes/responsive/templates/addons/brending_wizart/views/brending_wizart/wizard.tpl`

### 2.2) Мастер настройки в vendor panel («Мой магазин») (backend/vendor)
Точка входа: `vendor.php?dispatch=brending_wizart.my_store`.

Поток:
1. Определяем витрину продавца через `Storefront\Repository->findAvailableForCompanyId()`.
2. Загружаем сохранённые цвета из `?:brending_wizart_storefront_settings`.
3. Показываем экран с:
   - iframe предпросмотра витрины,
   - полями цветов (picker + hex),
   - 2 полями логотипов.
4. На `dispatch=brending_wizart.save_my_store`:
   - сохраняем цвета в `?:brending_wizart_storefront_settings` (upsert),
   - обрабатываем и сохраняем логотипы.

Файлы:
- `app/app/addons/brending_wizart/controllers/backend/brending_wizart.php`
- `app/design/backend/templates/addons/brending_wizart/views/brending_wizart/my_store.tpl`

## 3) Хранилище данных брендинга (цвета)

### Почему не `storefront.extra`
В данной установке CS-Cart в таблице `?:storefronts` отсутствует колонка `extra`, поэтому хранение кастомных данных витрины через `Storefront->extra` невозможно.

### Реализация
Создана таблица аддона:
- `?:brending_wizart_storefront_settings`
  - `storefront_id` (PK)
  - `background_color`
  - `accent_color`
  - `text_color`
  - `updated_at`

Таблица создаётся безопасно через `CREATE TABLE IF NOT EXISTS` при первом обращении.

Чтение на витрине:
- `app/app/addons/brending_wizart/controllers/frontend/init.post.php`
  - определяет текущий storefront (`Tygh::$app['storefront']`)
  - читает цвета по `storefront_id`
  - прокидывает в Smarty переменные `bw_background_color`, `bw_accent_color`, `bw_text_color`

Инъекция CSS:
- `app/design/themes/responsive/templates/addons/brending_wizart/hooks/index/head_scripts.post.tpl`
  - выставляет CSS variables `--bw-background-color`, `--bw-accent-color`, `--bw-text-color`
  - (debug) пишет meta `bw-colors` в preview режиме

### Примечание про «одинаковые цвета на разных субдоменах»
В текущей конфигурации проекта на разных субдоменах движок резолвит одну и ту же витрину:
- `Tygh::$app['storefront']->storefront_id = 1`
- `Tygh::$app['storefront']->url = localhost`

Так как аддон читает цвета строго по ключу `storefront_id`, то на всех доменах/субдоменах, которые указывают на один и тот же `storefront_id`, цвета будут одинаковыми — это ожидаемое поведение.

Проверка:
- открыть страницу на нужном домене с `?bw_preview=1`
- посмотреть `<meta name="bw-colors" ...>` — там выводится `storefront_id`, `storefront_url` и значения цветов.

## 4) Логотипы

### Требования
- 2 типа логотипов:
  - **Логотип для шапки витрины** (header): ресайз до `320x120`
  - **Логотип для списка продавцов** (list): ресайз до `200x200`

### Реализация
- Копирование временных файлов загрузки (`$_FILES[..]['tmp_name']`) в директорию files.
- Ресайз через `fn_resize_image()`.
- Запись результата через `fn_put_contents()`.
- Создание/обновление сущности логотипа через `fn_update_logo()`.
- Перед записью пары изображений удаляем старые через `fn_delete_image_pairs()`.
- Записываем новые изображения через `fn_update_image_pairs()`.

## 5) Live preview цветов (JS)

### Где
- wizard (frontend): `wizard.tpl`
- vendor panel: `my_store.tpl`

### Что делает
- синхронизирует input color и input text (hex)
- меняет CSS variables на элементах с `data-bw-preview` для мгновенного предпросмотра
- обновляет iframe URL с `bw_preview=<timestamp>` для cache-busting

## 6) Используемые функции/классы ядра (полный список)

Ниже приведён список API ядра CS‑Cart, которые реально используются аддоном, и подробное описание:
- **Что делает в CS‑Cart** (смысл/ответственность)
- **Как используется в нашем аддоне** (контекст)
- **Где используется** (файл)

### 6.1) Общая инфраструктура контроллеров

#### `CONTROLLER_STATUS_REDIRECT`
- **Что делает в CS‑Cart**
  - Специальный статус, который возвращает контроллер для выполнения HTTP redirect.
- **Как используется в нашем аддоне**
  - Возвращаем редиректы между шагами wizard, на страницу «Мой магазин», а также на `auth.ekey_login` после создания продавца.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`
  - `app/app/addons/brending_wizart/controllers/backend/brending_wizart.php`
  - `app/app/addons/brending_wizart/controllers/frontend/index.pre.php`
  - `app/app/addons/brending_wizart/controllers/backend/index.post.php`

#### `CONTROLLER_STATUS_DENIED`
- **Что делает в CS‑Cart**
  - Прерывает выполнение контроллера с отказом в доступе.
- **Как используется в нашем аддоне**
  - Запрещаем доступ к vendor‑страницам мастера, если пользователь не продавец.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/backend/brending_wizart.php`

#### `__($lang_var)`
- **Что делает в CS‑Cart**
  - Возвращает локализованную строку по ключу языка.
- **Как используется в нашем аддоне**
  - Для заголовков стандартных уведомлений (`__('error')`, `__('notice')`) и breadcrumb (`__('home')`).
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`
  - `app/app/addons/brending_wizart/controllers/backend/brending_wizart.php`

#### `fn_url($dispatch, $area = AREA)`
- **Что делает в CS‑Cart**
  - Генерирует URL по dispatch и области (`C`/`A`/`VENDOR_PANEL`). Учитывает настройки витрины, индекса, SEO и т.п.
- **Как используется в нашем аддоне**
  - Формируем ссылки/редиректы в vendor panel: `products.manage`, `auth.ekey_login`.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`

#### `fn_add_breadcrumb($title, $link = '')`
- **Что делает в CS‑Cart**
  - Добавляет элемент в breadcrumb‑цепочку.
- **Как используется в нашем аддоне**
  - Оформляем навигацию на страницах «Купить магазин» и «Мастер настройки магазина».
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`

### 6.2) Сессия, DI‑контейнер, реестр

#### `Tygh::$app['session']`
- **Что делает в CS‑Cart**
  - Доступ к сессии через контейнер приложения.
- **Как используется в нашем аддоне**
  - Храним данные мастера для vendor panel (например `brending_wizart_vendor`) и флаг автозапуска мастера.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/backend/brending_wizart.php`
  - `app/app/addons/brending_wizart/controllers/backend/index.post.php`

#### `Tygh::$app['db']` (`Tygh\Database\Connection`)
- **Что делает в CS‑Cart**
  - Единая точка доступа к БД: `query`, `getRow`, `getField`, `replaceInto`.
- **Как используется в нашем аддоне**
  - Создаём таблицу `?:brending_wizart_storefront_settings` (однократно), читаем/пишем сохранённые цвета витрины.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/init.post.php`
  - `app/app/addons/brending_wizart/controllers/backend/brending_wizart.php`
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`

#### `Tygh::$app['view']->assign($name, $value)` / `assign([..])`
- **Что делает в CS‑Cart**
  - Передаёт переменные в Smarty‑шаблоны.
- **Как используется в нашем аддоне**
  - Передаём `bw_data` (значения формы), `bw_storefront_url` (URL предпросмотра), а на витрине — `bw_*` цвета.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/backend/brending_wizart.php`
  - `app/app/addons/brending_wizart/controllers/frontend/init.post.php`

#### `Tygh\Registry::get('config.*')`
- **Что делает в CS‑Cart**
  - Доступ к конфигурации окружения (пути, домен, current_location, и т.д.).
- **Как используется в нашем аддоне**
  - Берём `config.dir.files` для временного сохранения загруженных изображений перед ресайзом.
  - Берём `config.current_location` чтобы корректно собрать URL предпросмотра с тем же протоколом/портом.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`
  - `app/app/addons/brending_wizart/controllers/backend/brending_wizart.php`

### 6.3) Storefront API (витрины)

#### `Tygh::$app['storefront']`
- **Что делает в CS‑Cart**
  - Текущий объект витрины, вычисленный движком для текущего запроса (на фронте).
- **Как используется в нашем аддоне**
  - В `init.post.php` берём `storefront_id`, чтобы достать цвета из таблицы аддона.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/init.post.php`

#### `Tygh::$app['storefront.repository']` (`Tygh\Storefront\Repository`)
- **Что делает в CS‑Cart**
  - Репозиторий для чтения/записи витрин.
- **Как используется в нашем аддоне**
  - `findAvailableForCompanyId($company_id)`:
    - На vendor‑странице «Мой магазин» получаем витрину, доступную компании‑продавцу.
    - В мастере настройки (vendor) проверяем, не создана ли витрина ранее.
  - `findById($storefront_id)`:
    - Получаем полный объект витрины по ID (после `findAvailableForCompanyId`).
  - `findDefault()`:
    - В мастере покупки создаём новую витрину, копируя тему/настройки из дефолтной.
  - `findByUrl($url)`:
    - Используем при подборе URL витрины (субдомен/хост) — проверяем, что витрина с таким URL ещё не существует.
  - `save(Storefront $storefront)`:
    - Сохраняем созданную витрину.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/backend/brending_wizart.php`
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`
  - `app/app/addons/brending_wizart/controllers/backend/index.post.php`

#### `Tygh::$app['storefront.factory']` (`Tygh\Storefront\Factory`)
- **Что делает в CS‑Cart**
  - Создаёт entity `Storefront` из массива данных (DTO‑подход), который затем сохраняется через репозиторий.
- **Как используется в нашем аддоне**
  - В мастере покупки собираем `$storefront_data` и создаём storefront entity через `fromArray()`, затем `repository->save()`.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`
  - `app/app/addons/brending_wizart/controllers/backend/brending_wizart.php`

### 6.4) Vendor/Company/User API

#### `fn_update_company(array $company_data)`
- **Что делает в CS‑Cart**
  - Создаёт или обновляет компанию (в мультивендоре — продавца).
- **Как используется в нашем аддоне**
  - В мастере покупки создаём нового продавца на основании формы.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`

#### `fn_update_user($user_id, array $user_data, &$auth, $ship_to_another, $notify_user)`
- **Что делает в CS‑Cart**
  - Создаёт/обновляет пользователя, может привязать к компании и назначить тип/статус.
- **Как используется в нашем аддоне**
  - В мастере покупки создаём аккаунт продавца и привязываем к `company_id`.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`

### 6.5) Безопасный логин продавца

#### `fn_generate_ekey($user_id, $type, $ttl)`
- **Что делает в CS‑Cart**
  - Генерирует одноразовый ключ (ekey) для безопасных сценариев входа (в том числе восстановления/автовхода).
- **Как используется в нашем аддоне**
  - После создания пользователя генерируем ekey и делаем redirect на `auth.ekey_login`, чтобы продавец попал в vendor panel без ручного логина.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`

### 6.6) Уведомления

#### `fn_set_notification($type, $title, $message)`
- **Что делает в CS‑Cart**
  - Показывает пользователю уведомление (error/notice/warning) в UI.
- **Как используется в нашем аддоне**
  - Ошибки валидации формы (пароли/ник), ошибки загрузки логотипа, успешное сохранение настроек.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`
  - `app/app/addons/brending_wizart/controllers/backend/brending_wizart.php`

#### `Tygh\Enum\NotificationSeverity::*`
- **Что делает в CS‑Cart**
  - Enum для типов уведомлений (строже, чем строковые значения).
- **Как используется в нашем аддоне**
  - В vendor panel используем `NotificationSeverity::ERROR`.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/backend/brending_wizart.php`

### 6.7) Файлы и директории

#### `fn_mkdir($path)`
- **Что делает в CS‑Cart**
  - Создаёт директорию с нужными правами (с учётом окружения).
- **Как используется в нашем аддоне**
  - Обеспечиваем существование директории `config.dir.files` перед копированием/ресайзом логотипов.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`
  - `app/app/addons/brending_wizart/controllers/backend/brending_wizart.php`

#### `fn_put_contents($path, $contents)`
- **Что делает в CS‑Cart**
  - Безопасная запись содержимого в файл (обёртка над file_put_contents с учетом окружения).
- **Как используется в нашем аддоне**
  - Записываем результат ресайза (`fn_resize_image` возвращает бинарные данные) во временный файл.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`
  - `app/app/addons/brending_wizart/controllers/backend/brending_wizart.php`

### 6.8) Работа с изображениями/логотипами

#### `fn_resize_image($path, $width, $height, $format)`
- **Что делает в CS‑Cart**
  - Ресайзит изображение до нужных размеров и может конвертировать формат.
- **Как используется в нашем аддоне**
  - Приводим логотипы к фиксированным размерам: `320x120` (header) и `200x200` (list).
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`
  - `app/app/addons/brending_wizart/controllers/backend/brending_wizart.php`

#### `fn_update_logo($logo_data, $company_id, $storefront_id)`
- **Что делает в CS‑Cart**
  - Создаёт/находит сущность логотипа (logo_id) по типу (`theme`/`vendor` и др.), компании и витрине.
- **Как используется в нашем аддоне**
  - Получаем `logo_id`, к которому дальше привязываем картинки через `fn_update_image_pairs`.
  - Используем разные комбинации:
    - storefront theme logo (для шапки)
    - company theme/vendor logo (для списка продавцов)
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`
  - `app/app/addons/brending_wizart/controllers/backend/brending_wizart.php`

#### `fn_delete_image_pairs($pair_id, $object_type, $pair_type)`
- **Что делает в CS‑Cart**
  - Удаляет существующие пары изображений, связанные с объектом (`logos`).
- **Как используется в нашем аддоне**
  - Перед загрузкой нового логотипа удаляем старую картинку, чтобы не оставались “старые” файлы/пары.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`
  - `app/app/addons/brending_wizart/controllers/backend/brending_wizart.php`

#### `fn_update_image_pairs(...)`
- **Что делает в CS‑Cart**
  - Привязывает изображение (icon/detailed) к объекту (в нашем случае — `logos`).
  - Обрабатывает перенос файла в хранилище изображений и запись метаданных.
- **Как используется в нашем аддоне**
  - Загружаем подготовленный файл (после копирования/ресайза) как `icon` для logo_id.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`
  - `app/app/addons/brending_wizart/controllers/backend/brending_wizart.php`

### 6.9) URL утилиты

#### `Tygh\Tools\Url`
- **Что делает в CS‑Cart**
  - Утилита для сборки/нормализации URL (protocol, host, port, path, query).
- **Как используется в нашем аддоне**
  - Строим URL предпросмотра витрины с тем же протоколом/портом и с параметром `bw_preview` (cache‑busting).
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/backend/brending_wizart.php`

### 6.10) Enum/проверки пользователя

#### `Tygh\Enum\UserTypes::isVendor()`
- **Что делает в CS‑Cart**
  - Проверяет, относится ли `auth['user_type']` к продавцу.
- **Как используется в нашем аддоне**
  - Закрываем доступ к контроллерам vendor panel для всех, кроме продавцов.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/backend/brending_wizart.php`
  - `app/app/addons/brending_wizart/controllers/backend/index.post.php`

### 6.11) Enum/константы статусов и значений (используются при создании продавца и витрины)

#### `Tygh\Enum\YesNo::*`
- **Что делает в CS‑Cart**
  - Enum для значений `Y`/`N` (вместо “магических строк”).
- **Как используется в нашем аддоне**
  - В `fn_update_user` устанавливаем `create_vendor_admin => YesNo::YES`.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`

#### `Tygh\Enum\VendorStatuses::*`
- **Что делает в CS‑Cart**
  - Enum статусов продавца/компании.
- **Как используется в нашем аддоне**
  - При создании компании ставим `status => VendorStatuses::ACTIVE`.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`

#### `Tygh\Enum\StorefrontStatuses::*`
- **Что делает в CS‑Cart**
  - Enum статусов витрин.
- **Как используется в нашем аддоне**
  - При создании витрины устанавливаем `status => StorefrontStatuses::OPEN`.
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`

#### `Tygh\Enum\SiteArea::*`
- **Что делает в CS‑Cart**
  - Enum области сайта (Customer/Admin/Vendor panel) для функций вроде `fn_url`.
- **Как используется в нашем аддоне**
  - Формируем ссылки для редиректа именно в vendor panel (`SiteArea::VENDOR_PANEL`).
- **Где используется**
  - `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`

## 7) Где в коде это используется (карта файлов)

### PHP
- `app/app/addons/brending_wizart/controllers/frontend/brending_wizart.php`
  - создание vendor/user/storefront
  - ekey login
  - запись цветов в таблицу аддона
  - обработка логотипов
- `app/app/addons/brending_wizart/controllers/backend/brending_wizart.php`
  - экран «Мой магазин»
  - сохранение цветов/логотипов
- `app/app/addons/brending_wizart/controllers/frontend/init.post.php`
  - чтение цветов на витрине и assign `bw_*`

### Smarty (templates)
- `app/design/themes/responsive/templates/addons/brending_wizart/hooks/index/head_scripts.post.tpl`
  - CSS variables + meta debug
- `app/design/themes/responsive/templates/addons/brending_wizart/views/brending_wizart/wizard.tpl`
  - форма мастера на витрине + live preview
- `app/design/backend/templates/addons/brending_wizart/views/brending_wizart/my_store.tpl`
  - форма в vendor panel + синхронизация полей

## 8) Рекомендации по документации CS-Cart
Официальные страницы (для заказчика):
- Add-ons / структура аддонов: https://docs.cs-cart.com/latest/developer_guide/addons/index.html
- Controllers (dispatch/modes): https://docs.cs-cart.com/latest/developer_guide/core/controllers/index.html
- Hooks и шаблоны: https://docs.cs-cart.com/latest/developer_guide/addons/hooks/index.html
- Работа с изображениями (общие принципы): https://docs.cs-cart.com/latest/developer_guide/core/images/index.html
- Storefronts (если доступно в вашей версии документации): https://docs.cs-cart.com/latest/developer_guide/core/storefronts/index.html

Примечание: конкретные сигнатуры `fn_update_logo`/`fn_update_image_pairs` могут отличаться по версии CS-Cart — рекомендуется сверять с исходниками вашего проекта.

## 9) Рекомендации по изоляции цветовых тем и полировке аддона

### 9.1) Как изолировать цветовые темы «по магазинам»
Сейчас тема изолируется по `storefront_id` (витрина). Это правильная модель в CS‑Cart для мультивитринности.

Чтобы на разных субдоменах были разные цвета, нужно, чтобы на этих субдоменах движок определял **разные** `storefront_id`:
- Создать отдельные storefront'ы в админке.
- Привязать домены/субдомены к конкретным storefront'ам.

Если в бизнес‑логике требуется изоляция не по витрине, а по **продавцу**, возможны альтернативы:
- **По `company_id`** (продавец): хранить настройки в таблице аддона по `company_id`, а на витрине получать компанию из runtime (зависит от того, как построена навигация и какой vendor «контекстный»).
- **По `HTTP_HOST`** (домен/субдомен): хранить настройки по строке домена. Это проще, но обходная модель, и может конфликтовать со штатной архитектурой storefronts.

Рекомендуемые API/точки расширения для изоляции по витринам:
- `Tygh::$app['storefront']` — источник текущего `storefront_id`.
- `Tygh\Storefront\Repository` — чтение/создание витрин и проверка уникальности URL.

### 9.2) Полировка и улучшения (рекомендации)

#### Установка/миграции таблицы
Сейчас таблица создаётся через `CREATE TABLE IF NOT EXISTS` при обращении.
Более “правильный” способ для production:
- добавить SQL установки аддона (install) и удаления (uninstall), чтобы таблица создавалась при установке, а не при каждом запросе.

#### Валидация цветов
Добавить проверку формата `#RRGGBB` перед сохранением:
- отклонять некорректные значения,
- нормализовать регистр,
- при ошибке показывать `fn_set_notification`.

#### Ограничение области применения CSS
Сейчас CSS правит `:root`, `body`, `a` и т.д. — это влияет на всю витрину.
Если нужно более мягкое внедрение:
- применять стили только внутри контейнера (например, `.bw-theme-scope ...`) или конкретных блоков,
- либо прокидывать переменные без глобальных `!important` переопределений.

#### Оптимизация производительности
`init.post.php` (и `index.post.php`) выполняют запрос к БД на каждом запросе.
Возможные улучшения:
- использовать кеширование (например, кешировать результат выборки по `storefront_id` на N секунд),
- или подгружать настройки только на нужных страницах, если это допустимо.

#### Единая точка чтения цветов
Сейчас чтение цветов продублировано в `init.post.php` и `index.post.php`.
Рекомендация:
- оставить один frontend‑хук (обычно достаточно `init.post.php`) и убрать лишнее, чтобы не было расхождений.

#### Работа с логотипами
Потенциальные улучшения:
- точнее определить `layout_id`/`style_id` для конкретной темы витрины, если в проекте используется несколько стилей,
- добавить обработку ошибок `fn_update_logo`/`fn_update_image_pairs` и сообщения пользователю,
- ограничить форматы изображений и размер файла.
