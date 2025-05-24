<?php
/**
 * Сайдбар CRM системы
 * 
 * Этот файл содержит всю HTML-структуру сайдбара системы и должен подключаться в других файлах,
 * а не дублироваться. Для использования в других файлах:
 * 
 * 1. Подключите файл через include:
 *    <?php include __DIR__ . '/includes/sidebar.php'; ?>
 * 
 * 2. Для программного переключения сайдбара используйте:
 *    if (typeof window.toggleSidebar === 'function') {
 *      window.toggleSidebar();
 *    }
 * 
 * 3. Не копируйте логику сайдбара в другие файлы!
 * 
 * Логика сайдбара находится в файле /js/sidebar.js, который должен быть 
 * подключен в header.php для корректной работы.
 */
// /crm/includes/sidebar.php
?>
<!-- Оверлей для закрытия сайдбара при клике вне -->
<div class="sidebar-overlay"></div>

<!-- Основной сайдбар -->
<nav class="sidebar">
  <ul class="nav flex-column">
    <!-- Группа Продажи -->
    <li class="nav-item sidebar-category">
      <a class="nav-link sidebar-toggle" href="#" data-submenu="sales">
        <i class="fas fa-shopping-cart"></i>
        <span>Продажи</span>
        <i class="fas fa-chevron-down"></i>
      </a>
      <div class="submenu" id="salesSubmenu">
        <ul class="nav flex-column">
          <li class="nav-item">
            <a class="nav-link" href="#" data-module="sales/orders/list">
              <i class="fas fa-shopping-cart"></i>
              <span>Заказы клиентов</span>
              <i class="fas fa-star star-icon" data-favorite="sales/orders/list"></i>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" data-module="shipments/list">
              <i class="fas fa-truck"></i>
              <span>Отгрузки</span>
              <i class="fas fa-star star-icon" data-favorite="shipments/list"></i>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" data-module="sales/returns/list">
              <i class="fas fa-undo-alt"></i>
              <span>Возвраты покупателей</span>
              <i class="fas fa-star star-icon" data-favorite="sales/returns/list"></i>
            </a>
          </li>
        </ul>
      </div>
    </li>
    
    <!-- Группа Закупки -->
    <li class="nav-item sidebar-category">
      <a class="nav-link sidebar-toggle" href="#" data-submenu="purchases">
        <i class="fas fa-shopping-bag"></i>
        <span>Закупки</span>
        <i class="fas fa-chevron-down"></i>
      </a>
      <div class="submenu" id="purchasesSubmenu">
        <ul class="nav flex-column">
          <li class="nav-item">
            <a class="nav-link" href="#" data-module="purchases/orders/list">
              <i class="fas fa-shopping-bag"></i>
              <span>Заказы поставщикам</span>
              <i class="fas fa-star star-icon" data-favorite="purchases/orders/list"></i>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" data-module="purchases/receipts/list">
              <i class="fas fa-receipt"></i>
              <span>Приёмки</span>
              <i class="fas fa-star star-icon" data-favorite="purchases/receipts/list"></i>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" data-module="purchases/returns/list">
              <i class="fas fa-undo"></i>
              <span>Возвраты поставщикам</span>
              <i class="fas fa-star star-icon" data-favorite="purchases/returns/list"></i>
            </a>
          </li>
        </ul>
      </div>
    </li>
    
    <!-- Группа Товары -->
    <li class="nav-item sidebar-category">
      <a class="nav-link sidebar-toggle" href="#" data-submenu="products">
        <i class="fas fa-box-open"></i>
        <span>Товары</span>
        <i class="fas fa-chevron-down"></i>
      </a>
      <div class="submenu" id="productsSubmenu">
        <ul class="nav flex-column">
          <li class="nav-item">
            <a class="nav-link" href="#" data-module="products/list">
              <i class="fas fa-box-open"></i>
              <span>Товары</span>
              <i class="fas fa-star star-icon" data-favorite="products/list"></i>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" data-module="categories/list">
              <i class="fas fa-tags"></i>
              <span>Категории</span>
              <i class="fas fa-star star-icon" data-favorite="categories/list"></i>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" data-module="measurements/list">
              <i class="fas fa-ruler"></i>
              <span>Единицы измерения</span>
              <i class="fas fa-star star-icon" data-favorite="measurements/list"></i>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" data-module="warehouse/list">
              <i class="fas fa-warehouse"></i>
              <span>Склады</span>
              <i class="fas fa-star star-icon" data-favorite="warehouse/list"></i>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" data-module="stock/list">
              <i class="fas fa-layer-group"></i>
              <span>Остатки</span>
              <i class="fas fa-star star-icon" data-favorite="stock/list"></i>
            </a>
          </li>
        </ul>
      </div>
    </li>
    
    <!-- Группа Производство -->
    <li class="nav-item sidebar-category">
      <a class="nav-link sidebar-toggle" href="#" data-submenu="production">
        <i class="fas fa-industry"></i>
        <span>Производство</span>
        <i class="fas fa-chevron-down"></i>
      </a>
      <div class="submenu" id="productionSubmenu">
        <ul class="nav flex-column">
          <li class="nav-item">
            <a class="nav-link" href="#" data-module="production/recipes/list">
              <i class="fas fa-book-open"></i>
              <span>Рецепты производства</span>
              <i class="fas fa-star star-icon" data-favorite="production/recipes/list"></i>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" data-module="production/orders/list">
              <i class="fas fa-cogs"></i>
              <span>Заказы на производство</span>
              <i class="fas fa-star star-icon" data-favorite="production/orders/list"></i>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" data-module="production/operations/list">
              <i class="fas fa-tools"></i>
              <span>Операции производства</span>
              <i class="fas fa-star star-icon" data-favorite="production/operations/list"></i>
            </a>
          </li>
        </ul>
      </div>
    </li>
    
    <!-- Группа Справочники -->
    <li class="nav-item sidebar-category">
      <a class="nav-link sidebar-toggle" href="#" data-submenu="directory">
        <i class="fas fa-book"></i>
        <span>Справочники</span>
        <i class="fas fa-chevron-down"></i>
      </a>
      <div class="submenu" id="directorySubmenu">
        <ul class="nav flex-column">
          <li class="nav-item">
            <a class="nav-link" href="#" data-module="counterparty/list">
              <i class="fas fa-building"></i>
              <span>Контрагенты</span>
              <i class="fas fa-star star-icon" data-favorite="counterparty/list"></i>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" data-module="drivers/list">
              <i class="fas fa-id-badge"></i>
              <span>Водители</span>
              <i class="fas fa-star star-icon" data-favorite="drivers/list"></i>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" data-module="loaders/list">
              <i class="fas fa-hands-helping"></i>
              <span>Грузчики</span>
              <i class="fas fa-star star-icon" data-favorite="loaders/list"></i>
            </a>
          </li>
        </ul>
      </div>
    </li>
    
    <!-- Финансы отдельным пунктом -->
    <li class="nav-item">
      <a class="nav-link" href="#" data-module="finances/list">
        <i class="fas fa-money-check-alt"></i>
        <span>Финансы</span>
        <i class="fas fa-star star-icon" data-favorite="finances/list"></i>
      </a>
    </li>
    
    <!-- Пользователи и доступ -->
    <li class="nav-item">
      <a class="nav-link" href="#" data-module="users/list">
        <i class="fas fa-users"></i>
        <span>Пользователи</span>
        <i class="fas fa-star star-icon" data-favorite="users/list"></i>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="#" data-module="access/list">
        <i class="fas fa-lock"></i>
        <span>Управление доступом</span>
        <i class="fas fa-star star-icon" data-favorite="access/list"></i>
      </a>
    </li>
    
    <!-- Настройки администратора -->
    <li class="nav-item">
      <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#adminSettingsModal">
        <i class="fas fa-cog"></i>
        <span>Настройки</span>
        <i class="fas fa-star star-icon" data-favorite="settings"></i>
      </a>
    </li>
  </ul>
</nav> 

<!-- Скрипт для работы сайдбара (вставляется в конце sidebar.php) -->
<script>
// Глобальная функция toggleSidebar, которая будет доступна для вызова из других мест
window.toggleSidebar = function() {
  try {
    console.log('[SIDEBAR] Вызвана функция toggleSidebar');
    
    // Находим элементы сайдбара
    var sidebar = document.querySelector('.sidebar');
    var overlay = document.querySelector('.sidebar-overlay');
    
    // Проверяем наличие элементов
    if (!sidebar || !overlay) {
      console.error('[SIDEBAR] Не найдены элементы сайдбара или оверлей');
      return;
    }
    
    // Переключаем состояние сайдбара
    if (sidebar.classList.contains('open')) {
      // Закрываем
      console.log('[SIDEBAR] Закрываем сайдбар');
      sidebar.classList.remove('open');
      overlay.classList.remove('active');
      document.body.classList.remove('sidebar-open');
    } else {
      // Открываем
      console.log('[SIDEBAR] Открываем сайдбар');
      sidebar.classList.add('open');
      overlay.classList.add('active');
      document.body.classList.add('sidebar-open');
    }
  } catch (err) {
    console.error('[SIDEBAR ERROR] Ошибка в функции toggleSidebar:', err);
  }
};

// Добавляем обработчик клика для overlay, который закрывает сайдбар
document.addEventListener('DOMContentLoaded', function() {
  try {
    console.log('[SIDEBAR] DOMContentLoaded в sidebar.php');
    
    // Находим оверлей
    var overlay = document.querySelector('.sidebar-overlay');
    
    // Добавляем обработчик клика на оверлей для закрытия сайдбара
    if (overlay) {
      overlay.addEventListener('click', function() {
        console.log('[SIDEBAR] Клик по оверлею, закрываем сайдбар');
        var sidebar = document.querySelector('.sidebar');
        if (sidebar) {
          sidebar.classList.remove('open');
          overlay.classList.remove('active');
          document.body.classList.remove('sidebar-open');
        }
      });
      console.log('[SIDEBAR] Обработчик клика по оверлею установлен');
    } else {
      console.error('[SIDEBAR] Элемент оверлея не найден!');
    }
  } catch (err) {
    console.error('[SIDEBAR ERROR] Ошибка в инициализации обработчиков:', err);
  }
});
</script> 