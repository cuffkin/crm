<?php
// /crm/index.php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$username  = $_SESSION['username'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';

include __DIR__ . '/includes/header.php';
?>

<nav class="navbar navbar-expand-lg navbar-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">PRORABCRM SPA</a>
    <button class="navbar-toggler" type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNavbar"
            aria-controls="mainNavbar"
            aria-expanded="false"
            aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">

        <!-- Пользователи -->
        <li class="nav-item">
          <a class="nav-link" href="#" data-module="users/list">Пользователи</a>
        </li>

        <!-- Управление доступом -->
        <li class="nav-item">
          <a class="nav-link" href="#" data-module="access/list">Управление доступом</a>
        </li>

        <!-- Контрагенты -->
        <li class="nav-item">
          <a class="nav-link" href="#" data-module="counterparty/list">Контрагенты</a>
        </li>

        <!-- Товары (dropdown) -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="navbarProducts"
             role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Товары
          </a>
          <ul class="dropdown-menu" aria-labelledby="navbarProducts">
            <li>
              <a class="dropdown-item" href="#" data-module="products/list">
                Список товаров
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="#" data-module="categories/list">
                Категории
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="#" data-module="warehouse/list">
                Склады
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="#" data-module="stock/list">
                Остатки
              </a>
            </li>
          </ul>
        </li>

        <!-- Продажи (dropdown) -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="navbarSales"
             role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Продажи
          </a>
          <ul class="dropdown-menu" aria-labelledby="navbarSales">
            <li>
              <a class="dropdown-item" href="#" data-module="sales/orders/list">
                Заказы покупателей
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="#" data-module="shipments/list">
                Отгрузки
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="#" data-module="sales/returns/list">
                Возврат покупателя
              </a>
            </li>
          </ul>
        </li>
        
        <!-- Финансы -->
        <li class="nav-item">
          <a class="nav-link" href="#" data-module="finances/list">Финансы</a>
        </li>

        <!-- Закупки (dropdown) -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="navbarPurchases"
             role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Закупки
          </a>
          <ul class="dropdown-menu" aria-labelledby="navbarPurchases">
            <li>
              <a class="dropdown-item" href="#" data-module="purchases/orders/list">
                Заказ поставщику
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="#" data-module="purchases/receipts/list">
                Приёмки
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="#" data-module="purchases/returns/list">
                Возврат поставщику
              </a>
            </li>
          </ul>
        </li>

        <!-- Производство (dropdown) -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="navbarProduction"
             role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Производство
          </a>
          <ul class="dropdown-menu" aria-labelledby="navbarProduction">
            <li>
              <a class="dropdown-item" href="#" data-module="production/recipes/list">
                Рецепты
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="#" data-module="production/orders/list">
                Заказы на производство
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="#" data-module="production/operations/list">
                Операции производства
              </a>
            </li>
          </ul>
        </li>

        <!-- Корректировки (dropdown) -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="navbarCorrections"
             role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Корректировки
          </a>
          <ul class="dropdown-menu" aria-labelledby="navbarCorrections">
            <li>
              <a class="dropdown-item" href="#" data-module="corrections/inventory/list">
                Инвентаризация
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="#" data-module="corrections/appropriations/list">
                Оприходование
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="#" data-module="corrections/writeoff/list">
                Списание
              </a>
            </li>
          </ul>
        </li>

        <!-- Справочники (dropdown) -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="navbarRefs"
             role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Справочники
          </a>
          <ul class="dropdown-menu" aria-labelledby="navbarRefs">
            <li>
              <a class="dropdown-item" href="#" data-module="loaders/list">
                Грузчики
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="#" data-module="drivers/list">
                Водители
              </a>
            </li>
          </ul>
        </li>
        
        <!-- Автоматизации -->
        <li class="nav-item">
          <a class="nav-link" href="#" data-module="automations/list">Автоматизации</a>
        </li>

      </ul>

      <!-- Избранные вкладки -->
      <div id="favorite-tabs" class="d-flex align-items-center text-light me-3"></div>

      <!-- Статус сессии и информация о пользователе -->
      <div class="d-flex align-items-center text-light">
        <!-- Индикатор статуса сессии -->
        <div id="session-status" class="d-flex align-items-center me-3">
          <small id="session-info" class="text-light me-2"></small>
          <span id="sync-status" class="badge bg-secondary rounded-pill" title="Статус синхронизации">
            <i class="fas fa-sync-alt"></i>
          </span>
        </div>
        
        <!-- Информация о пользователе -->
        <span class="me-3">
          <?= htmlspecialchars($username) ?> (<?= htmlspecialchars($user_role) ?>)
        </span>
        
        <!-- Переключатель темы -->
        <label class="theme-switcher me-3" title="Переключить тему">
          <input type="checkbox" id="theme-switcher">
          <span class="theme-slider">
            <i class="fas fa-sun theme-icon theme-icon-light"></i>
            <i class="fas fa-moon theme-icon theme-icon-dark"></i>
          </span>
        </label>
        
        <a href="logout.php" class="btn btn-outline-light btn-sm">Выйти</a>
      </div>
    </div>
  </div>
</nav>

<div class="container mt-3">
  <!-- Вкладки -->
  <ul class="nav nav-tabs" id="crm-tabs"></ul>
  <!-- Контент вкладок -->
  <div class="tab-content" id="crm-tab-content">
    <p class="text-muted p-3">Выберите пункт меню</p>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>