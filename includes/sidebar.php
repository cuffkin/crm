<?php
// /crm/includes/sidebar.php
?>
<nav class="sidebar">
  <ul class="nav flex-column">
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center" href="#" data-module="users/list">
        <i class="fas fa-users me-2"></i>
        <span>Пользователи</span>
        <i class="fas fa-star ms-auto star-icon" onclick="toggleFavorite('users/list'); event.stopPropagation();" title="В избранное"></i>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center" href="#" data-module="access/list">
        <i class="fas fa-lock me-2"></i>
        <span>Управление доступом</span>
        <i class="fas fa-star ms-auto star-icon" onclick="toggleFavorite('access/list'); event.stopPropagation();" title="В избранное"></i>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center" href="#" data-module="counterparty/list">
        <i class="fas fa-building me-2"></i>
        <span>Контрагенты</span>
        <i class="fas fa-star ms-auto star-icon" onclick="toggleFavorite('counterparty/list'); event.stopPropagation();" title="В избранное"></i>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center" href="#" data-module="products/list">
        <i class="fas fa-box-open me-2"></i>
        <span>Товары</span>
        <i class="fas fa-star ms-auto star-icon" onclick="toggleFavorite('products/list'); event.stopPropagation();" title="В избранное"></i>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center" href="#" data-module="categories/list">
        <i class="fas fa-tags me-2"></i>
        <span>Категории</span>
        <i class="fas fa-star ms-auto star-icon" onclick="toggleFavorite('categories/list'); event.stopPropagation();" title="В избранное"></i>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center" href="#" data-module="warehouse/list">
        <i class="fas fa-warehouse me-2"></i>
        <span>Склады</span>
        <i class="fas fa-star ms-auto star-icon" onclick="toggleFavorite('warehouse/list'); event.stopPropagation();" title="В избранное"></i>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center" href="#" data-module="stock/list">
        <i class="fas fa-layer-group me-2"></i>
        <span>Остатки</span>
        <i class="fas fa-star ms-auto star-icon" onclick="toggleFavorite('stock/list'); event.stopPropagation();" title="В избранное"></i>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center" href="#" data-module="sales/orders/list">
        <i class="fas fa-shopping-cart me-2"></i>
        <span>Заказы клиентов</span>
        <i class="fas fa-star ms-auto star-icon" onclick="toggleFavorite('sales/orders/list'); event.stopPropagation();" title="В избранное"></i>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center" href="#" data-module="shipments/list">
        <i class="fas fa-truck me-2"></i>
        <span>Отгрузки</span>
        <i class="fas fa-star ms-auto star-icon" onclick="toggleFavorite('shipments/list'); event.stopPropagation();" title="В избранное"></i>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center" href="#" data-module="sales/returns/list">
        <i class="fas fa-undo-alt me-2"></i>
        <span>Возвраты покупателей</span>
        <i class="fas fa-star ms-auto star-icon" onclick="toggleFavorite('sales/returns/list'); event.stopPropagation();" title="В избранное"></i>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center" href="#" data-module="purchases/orders/list">
        <i class="fas fa-shopping-bag me-2"></i>
        <span>Заказы поставщикам</span>
        <i class="fas fa-star ms-auto star-icon" onclick="toggleFavorite('purchases/orders/list'); event.stopPropagation();" title="В избранное"></i>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center" href="#" data-module="purchases/receipts/list">
        <i class="fas fa-receipt me-2"></i>
        <span>Приёмки</span>
        <i class="fas fa-star ms-auto star-icon" onclick="toggleFavorite('purchases/receipts/list'); event.stopPropagation();" title="В избранное"></i>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center" href="#" data-module="purchases/returns/list">
        <i class="fas fa-undo me-2"></i>
        <span>Возвраты поставщикам</span>
        <i class="fas fa-star ms-auto star-icon" onclick="toggleFavorite('purchases/returns/list'); event.stopPropagation();" title="В избранное"></i>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center" href="#" data-module="production/recipes/list">
        <i class="fas fa-book-open me-2"></i>
        <span>Рецепты производства</span>
        <i class="fas fa-star ms-auto star-icon" onclick="toggleFavorite('production/recipes/list'); event.stopPropagation();" title="В избранное"></i>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center" href="#" data-module="production/orders/list">
        <i class="fas fa-cogs me-2"></i>
        <span>Заказы на производство</span>
        <i class="fas fa-star ms-auto star-icon" onclick="toggleFavorite('production/orders/list'); event.stopPropagation();" title="В избранное"></i>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center" href="#" data-module="production/operations/list">
        <i class="fas fa-tools me-2"></i>
        <span>Операции производства</span>
        <i class="fas fa-star ms-auto star-icon" onclick="toggleFavorite('production/operations/list'); event.stopPropagation();" title="В избранное"></i>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center" href="#" data-module="finances/list">
        <i class="fas fa-money-check-alt me-2"></i>
        <span>Финансы</span>
        <i class="fas fa-star ms-auto star-icon" onclick="toggleFavorite('finances/list'); event.stopPropagation();" title="В избранное"></i>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center" href="#" data-module="drivers/list">
        <i class="fas fa-id-badge me-2"></i>
        <span>Водители</span>
        <i class="fas fa-star ms-auto star-icon" onclick="toggleFavorite('drivers/list'); event.stopPropagation();" title="В избранное"></i>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center" href="#" data-module="loaders/list">
        <i class="fas fa-hands-helping me-2"></i>
        <span>Грузчики</span>
        <i class="fas fa-star ms-auto star-icon" onclick="toggleFavorite('loaders/list'); event.stopPropagation();" title="В избранное"></i>
      </a>
    </li>
  </ul>
</nav> 