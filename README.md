# ⚠️ **ВАЖНО**: ПЕРЕД ЛЮБЫМИ ДЕЙСТВИЯМИ С КОДОМ ОБЯЗАТЕЛЬНО ПРОЧИТАТЬ И ПОЛНОСТЬЮ ПОЙМЭТЬ ЭТОТ README.MD. ВСЕ ИЗМЕНЕНИЯ ДОПУСКАЮТСЯ ТОЛЬКО ПОСЛЕ ПОЛНОГО АНАЛИЗА.

# **ДИСКЛЕЙМЕР:** Этот файл необходимо прочесть полностью для корректной работы ИИ с проектом.

**Ключевые файлы для анализа:**
- `index.php`
- `config/db.php`
- `config/session.php`
- `includes/header.php`
- `includes/footer.php`
- `includes/session-manager.php`
- `includes/related_documents.php`
- `js/app.js`
- `prorabCRM_DB.sql`
- `modules/` (все подпапки)

*Другие ИИ должны дополнять или править этот README.md при изменениях в проекте.*

# ProrabCRM

## Структура директорий

- **modules/** — серверные модули (production, finances, purchases, sales и т. д.), реализующие бизнес-логику
- **includes/** — вспомогательные PHP-скрипты: шаблоны `header.php`, `footer.php`, управление сессиями, общие функции
- **config/** — файлы конфигурации: подключение к базе данных (`db.php`), настройки сессии (`session.php`)
- **js/** — клиентская логика SPA: `app.js`, `session-status.js` и `theme-switcher.js`
- **css/** — стили проекта: `style.css`, `enhanced-style.css`
- **assets/** — сторонние библиотеки: Bootstrap, jQuery, Font Awesome и прочие ресурсы
- **vendor/** — зависимости (Composer), автозагрузчик (если используется)
- **public/** — публичная папка для статики (при наличии)
- **index.php**, **login.php**, **logout.php**, **ping.php**, **save_form_state.php** — основные точки входа сервера

## Конфигурация

- **config/db.php**
  - Параметры подключения к MySQL: `$servername`, `$username`, `$password`, `$dbname`
  - Создание соединения через `mysqli` и проверка `connect_error`
- **config/session.php**
  - Инициализация и настройки `session_start()` для управления пользовательскими сессиями

## Точка входа

- **index.php**
  - Проверяет наличие `$_SESSION['user_id']` и перенаправляет на страницу входа
  - Подключает шаблоны `header.php` и `footer.php`
  - Формирует навигацию через атрибут `data-module` для динамической загрузки SPA-модулей
- **login.php** и **logout.php**
  - Обрабатывают аутентификацию пользователя и выход из системы
- **ping.php**
  - Проверка доступности сервера (например, для таймаутов или keep-alive)
- **save_form_state.php**
  - API для автосохранения состояния форм на сервере

## Клиентская логика

- **js/app.js**
  - Управление вкладками SPA: открытие, закрытие, выбор модуля через `data-module`
  - Загрузка контента модулей через AJAX (`*_partial.php`)
  - Автосохранение форм каждые 15 секунд и синхронизация с сервером каждые 45 секунд
  - Восстановление состояния вкладок и форм из `localStorage`
- **js/session-status.js**
  - Отображение статуса текущей сессии и времени последнего синхрона
- **js/theme-switcher.js**
  - Переключатель темы (светлая/тёмная) с сохранением в `localStorage`

## Стили

- **css/style.css** — базовые стили интерфейса
- **css/enhanced-style.css** — дополнительные стили для улучшенного визуала
- **Bootstrap 5** — сетка, компоненты и утилиты; файлы в `assets/bootstrap-5.3.3-dist`
- **Font Awesome** — иконки (CDN)

## Управление сессиями

- **config/session.php**
  - Вызывает `session_start()` для запуска сессии и хранения данных пользователя.
- **includes/session-manager.php**
  - Увеличивает время жизни сессии до 8 часов (`session.gc_maxlifetime` и `session_set_cookie_params`).
  - При XHR-запросах устанавливает заголовок `X-User-ID` с текущим `user_id`.
  - Функция `saveSessionState()`:
    - Учитывает количество визитов (`$_SESSION['visits']++`), время последнего посещения (`$_SESSION['last_visit']`).
    - Сохраняет сериализованные данные сессии в таблицу `PCRM_UserSession` (создаёт её при необходимости).
  - Функция `restoreUserSession($user_id)`:
    - Загружает последние данные из `PCRM_UserSession`, восстанавливает значения в `$_SESSION`, регенерирует идентификатор сессии.

## Связанные документы

- Файл **includes/related_documents.php**:
  - `showRelatedDocuments($conn, $source_type, $source_id)`:
    - Получает из таблицы `PCRM_RelatedDocuments` документы, из которых был создан текущий, и документы, созданные на основе текущего.
    - Выводит таблицу с колонками: тип документа, номер, дата, тип связи и кнопки открытия через JS-функции (`openOrderEditTab`, `openShipmentEditTab` и т. д.).
  - `registerRelatedDocument($conn, $source_type, $source_id, $related_type, $related_id)`:
    - Проверяет и создаёт (при отсутствии) таблицу `PCRM_RelatedDocuments`.
    - Добавляет новую связь, если её ещё нет.
  - Структура таблицы **PCRM_RelatedDocuments**:
    - `id` INT PK, `source_type`, `source_id`, `related_type`, `related_id`, `created_at`.
    - Индексы на (`source_type`, `source_id`) и (`related_type`, `related_id`).

## Модули

Каждый модуль лежит в отдельной папке `modules/{module_name}` и предоставляет:
- `list_partial.php` — список записей.
- `edit_partial.php` — форма создания/редактирования.
- `edit_post.php`, `delete.php` — API для сохранения и удаления.

Список бизнес-модулей:

- **users/** — управление пользователями.
- **access/** — управление правами доступа.
- **counterparty/** — контрагенты.
- **products/** — товары и их изображения.
- **categories/** — справочник категорий.
- **warehouse/** — склады.
- **stock/** — остатки и движения товаров.
- **sales/orders/** — заказы клиентов.
- **sales/returns/** — возвраты покупателей.
- **shipments/** — отгрузки.
- **purchases/orders/** — заказы поставщикам.
- **purchases/receipts/** — приёмки.
- **purchases/returns/** — возвраты поставщикам.
- **production/recipes/** — рецепты производства.
- **production/orders/** — заказы на производство.
- **production/operations/** — операции производства.
- **finances/** — финансовые операции и кассы.
- **drivers/** — водители.
- **loaders/** — грузчики

## Структура базы данных

В проекте используется набор таблиц для хранения данных по модулям:

- **Пользователи и сессии**: `PCRM_User`, `PCRM_UserSession`
- **Заказы**: `PCRM_Order`, `PCRM_OrderItem`, `PCRM_OrderHistory`
- **Отгрузки**: `PCRM_ShipmentHeader`, `PCRM_Shipments`
- **Возвраты**: `PCRM_ReturnHeader`, `PCRM_ReturnItem`, `PCRM_InboundReturns`, `PCRM_SupplierReturnHeader`, `PCRM_SupplierReturnItem`
- **Приёмки и закупки**: `PCRM_ReceiptHeader`, `PCRM_ReceiptItem`, `PCRM_PurchaseOrder`, `PCRM_PurchaseOrderItem`, `PCRM_InboundOperations`
- **Корректировки и инвентаризация**: `PCRM_Adjustments`
- **Финансы**: `PCRM_FinancialTransaction`, `PCRM_CashRegister`, `PCRM_PaymentMethodDetails`
- **Товары и склады**: `PCRM_Product`, `PCRM_ProductImages`, `PCRM_Categories`, `PCRM_Warehouse`, `PCRM_Stock`, `PCRM_Transfers`
- **Производство**: `PCRM_ProductionRecipe`, `PCRM_ProductionRecipeItem`, `PCRM_ProductionOrder`, `PCRM_ProductionOperation`, `PCRM_ProductionOperationItem`
- **Связанные документы**: `PCRM_RelatedDocuments`, `PCRM_DocumentRelation`

## Вспомогательные скрипты и API

- **login.php** — страница и скрипт входа: отображает форму авторизации, проверяет учетные данные, устанавливает `$_SESSION['user_id']`, `username`, `user_role`.
- **logout.php** — скрипт выхода: разрушает сессию и перенаправляет на страницу входа.
- **ping.php** — проверка доступности сервера, возвращает простое 200 OK для keep-alive.
- **save_form_state.php** — REST API для автосохранения и восстановления данных форм и вкладок:
  - Таблица `PCRM_FormState` хранит `state_key` (`forms` или `tabs`) и `state_data` в JSON.
  - Действия: `save`, `sync`, `save_tabs`, `restore`, `clear`.

- **includes/functions.php** — вспомогательные функции:
  - `check_access($conn, $userId, $moduleName)` — проверяет роль пользователя (`admin` или через таблицу `PCRM_AccessRules`), возвращает `bool` доступа. 