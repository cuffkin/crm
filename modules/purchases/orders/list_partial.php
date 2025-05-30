<?php
// /crm/modules/purchases/orders/list_partial.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'purchases_orders')) {
    die("<div class='text-danger'>Доступ запрещён</div>");
}

// Основной запрос для получения списка заказов поставщикам
$sql = "
SELECT po.id, po.purchase_order_number, po.date, po.status, po.supplier_id, po.deleted, po.conducted,
       c.name AS supplier_name,
       SUM((poi.quantity * poi.price) - poi.discount) AS order_sum
FROM PCRM_PurchaseOrder po
LEFT JOIN PCRM_Counterparty c ON po.supplier_id = c.id
LEFT JOIN PCRM_PurchaseOrderItem poi ON po.id = poi.purchase_order_id
WHERE po.deleted = 0
GROUP BY po.id
ORDER BY po.id DESC
";

$res = $conn->query($sql);
if (!$res) {
    die("<div class='text-danger'>Ошибка запроса: " . $conn->error . "</div>");
}
$orders = $res->fetch_all(MYSQLI_ASSOC);

// Функция преобразования статуса заказа в читаемый вид
function translateOrderStatus($status) {
    switch ($status) {
        case 'draft':      return '<span class="badge bg-secondary">Черновик</span>';
        case 'new':        return '<span class="badge bg-primary">Новый</span>';
        case 'processing': return '<span class="badge bg-info">В обработке</span>';
        case 'completed':  return '<span class="badge bg-success">Выполнен</span>';
        case 'cancelled':  return '<span class="badge bg-danger">Отменен</span>';
        default:           return '<span class="badge bg-secondary">'. $status .'</span>';
    }
}

// Функция для вывода "да"/"нет" для проведенных заказов
function isConducted($val) {
    return ($val == 1) ? 'да' : 'нет';
}
?>
<h4>Заказы поставщикам</h4>
<button class="btn btn-primary btn-sm mb-2" onclick="openPurchaseOrderEditTab(0)">Добавить заказ поставщику</button>

<table class="table table-bordered table-sm">
  <thead>
    <tr>
      <th>ID</th>
      <th>Номер</th>
      <th>Дата</th>
      <th>Поставщик</th>
      <th>Статус</th>
      <th>Проведен</th>
      <th>Сумма</th>
      <th>Действия</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($orders as $order): ?>
    <tr>
      <td><?= $order['id'] ?></td>
      <td><?= htmlspecialchars($order['purchase_order_number'] ?? '--') ?></td>
      <td><?= $order['date'] ?></td>
      <td><?= htmlspecialchars($order['supplier_name'] ?? '--') ?></td>
      <td><?= translateOrderStatus($order['status']) ?></td>
      <td><?= isConducted($order['conducted']) ?></td>
      <td><?= number_format($order['order_sum'] ?? 0, 2, '.', ' ') ?> руб.</td>
      <td>
        <button class="btn btn-warning btn-sm" onclick="openPurchaseOrderEditTab(<?= $order['id'] ?>)">Ред.</button>
        <button class="btn btn-danger btn-sm" onclick="deletePurchaseOrder(<?= $order['id'] ?>)">Удал.</button>
        <button class="btn btn-info btn-sm" onclick="printPurchaseOrder(<?= $order['id'] ?>)">Печать</button>
        
        <!-- Кнопка "Создать на основании" -->
        <div class="btn-group">
          <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            На основании
          </button>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#" onclick="createReceiptFromPurchaseOrder(<?= $order['id'] ?>)">Приёмка</a></li>
            <li><a class="dropdown-item" href="#" onclick="createSupplierReturnFromPurchaseOrder(<?= $order['id'] ?>)">Возврат поставщику</a></li>
            <li><a class="dropdown-item" href="#" onclick="createFinanceFromPurchaseOrder(<?= $order['id'] ?>, 'expense')">Расход денег</a></li>
          </ul>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<script>
function openPurchaseOrderEditTab(orderId) {
  // Создаем новую вкладку для редактирования
  const tabId = 'purchase-order-tab-' + Math.floor(Math.random() * 1000000);
  const tabContentId = 'purchase-order-content-' + Math.floor(Math.random() * 1000000);
  
  // Заголовок вкладки
  let tabTitle = orderId > 0 ? 'Заказ поставщику #' + orderId : 'Новый заказ поставщику';
  
  // Добавляем новую вкладку
  $('#crm-tabs').append(`
    <li class="nav-item">
      <a class="nav-link active" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" role="tab">
        ${tabTitle} <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>
  `);
  
  // Добавляем содержимое вкладки
  $('#crm-tab-content').append(`
    <div class="tab-pane fade show active" id="${tabContentId}" role="tabpanel">
      <div class="text-center p-5">
        <div class="spinner-border" role="status">
          <span class="visually-hidden">Загрузка...</span>
        </div>
      </div>
    </div>
  `);
  
  // Делаем новую вкладку активной
  $('.nav-link').removeClass('active');
  $('.tab-pane').removeClass('show active');
  $(`#${tabId}`).addClass('active');
  $(`#${tabContentId}`).addClass('show active');
  
  // Загружаем содержимое редактирования
  $.ajax({
    url: '/crm/modules/purchases/orders/edit_partial.php',
    data: { 
      id: orderId,
      tab: 1,
      tab_id: tabId,
      content_id: tabContentId
    },
    success: function(html) {
      $(`#${tabContentId}`).html(html);
    },
    error: function(xhr, status, error) {
      console.error("Error loading order:", error);
      $(`#${tabContentId}`).html(`
        <div class="alert alert-danger">
          <h4>Ошибка загрузки заказа</h4>
          <p>Статус: ${status}, Код: ${xhr.status}</p>
          <p>Сообщение: ${error}</p>
          <p>Ответ сервера: ${xhr.responseText}</p>
        </div>
      `);
    }
  });
  
  // Обработчик закрытия вкладки
  $(`#${tabId} .btn-close`).on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
  });
}

function deletePurchaseOrder(orderId) {
  if (!confirm('Вы уверены, что хотите удалить этот заказ поставщику?')) return;
  
  $.get('/crm/modules/purchases/orders/delete.php', { id: orderId }, function(response) {
    if (response === 'OK') {
      updatePurchaseOrderLists();
      showNotification('Заказ поставщику успешно удален', 'success');
    } else {
      alert('Ошибка при удалении: ' + response);
    }
  });
}

function printPurchaseOrder(orderId) {
  // Открываем окно печати
  window.open('/crm/modules/purchases/orders/print.php?id=' + orderId, '_blank');
}

function updatePurchaseOrderLists() {
  // Обновляем список заказов
  $.get('/crm/modules/purchases/orders/list_partial.php', function(html) {
    $('.tab-pane').each(function() {
      if ($(this).find('h4:contains("Заказы поставщикам")').length > 0) {
        $(this).html(html);
      }
    });
  });
}

// Функция для создания приёмки на основании заказа поставщику
function createReceiptFromPurchaseOrder(orderId) {
  // Создаем новую вкладку для приёмки
  const tabId = 'receipt-tab-' + Math.floor(Math.random() * 1000000);
  const tabContentId = 'receipt-content-' + Math.floor(Math.random() * 1000000);
  
  // Заголовок вкладки
  let tabTitle = 'Новая приёмка';
  
  // Добавляем новую вкладку
  $('#crm-tabs').append(`
    <li class="nav-item">
      <a class="nav-link active" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" role="tab">
        ${tabTitle} <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>
  `);
  
  // Добавляем содержимое вкладки
  $('#crm-tab-content').append(`
    <div class="tab-pane fade show active" id="${tabContentId}" role="tabpanel">
      <div class="text-center p-5">
        <div class="spinner-border" role="status">
          <span class="visually-hidden">Загрузка...</span>
        </div>
      </div>
    </div>
  `);
  
  // Делаем новую вкладку активной
  $('.nav-link').removeClass('active');
  $('.tab-pane').removeClass('show active');
  $(`#${tabId}`).addClass('active');
  $(`#${tabContentId}`).addClass('show active');
  
  // Загружаем содержимое редактирования приёмки
  $.ajax({
    url: '/crm/modules/purchases/receipts/edit_partial.php',
    data: { 
      id: 0, 
      purchase_order_id: orderId,
      based_on: 'purchase_order',
      tab: 1,
      tab_id: tabId,
      content_id: tabContentId
    },
    success: function(html) {
      $(`#${tabContentId}`).html(html);
    },
    error: function(xhr, status, error) {
      $(`#${tabContentId}`).html(`
        <div class="alert alert-danger">
          <h4>Ошибка загрузки формы приёмки</h4>
          <p>Ответ сервера: ${xhr.responseText}</p>
        </div>
      `);
    }
  });
  
  // Обработчик закрытия вкладки
  $(`#${tabId} .btn-close`).on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
  });
}

// Функция для создания возврата поставщику на основании заказа
function createSupplierReturnFromPurchaseOrder(orderId) {
  // Создаем новую вкладку для возврата
  const tabId = 'supplier-return-tab-' + Math.floor(Math.random() * 1000000);
  const tabContentId = 'supplier-return-content-' + Math.floor(Math.random() * 1000000);
  
  // Заголовок вкладки
  let tabTitle = 'Новый возврат поставщику';
  
  // Добавляем новую вкладку
  $('#crm-tabs').append(`
    <li class="nav-item">
      <a class="nav-link active" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" role="tab">
        ${tabTitle} <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>
  `);
  
  // Добавляем содержимое вкладки
  $('#crm-tab-content').append(`
    <div class="tab-pane fade show active" id="${tabContentId}" role="tabpanel">
      <div class="text-center p-5">
        <div class="spinner-border" role="status">
          <span class="visually-hidden">Загрузка...</span>
        </div>
      </div>
    </div>
  `);
  
  // Делаем новую вкладку активной
  $('.nav-link').removeClass('active');
  $('.tab-pane').removeClass('show active');
  $(`#${tabId}`).addClass('active');
  $(`#${tabContentId}`).addClass('show active');
  
  // Загружаем содержимое редактирования возврата
  $.ajax({
    url: '/crm/modules/purchases/returns/edit_partial.php',
    data: { 
      id: 0, 
      purchase_order_id: orderId,
      based_on: 'purchase_order',
      tab: 1,
      tab_id: tabId,
      content_id: tabContentId
    },
    success: function(html) {
      $(`#${tabContentId}`).html(html);
    },
    error: function(xhr, status, error) {
      $(`#${tabContentId}`).html(`
        <div class="alert alert-danger">
          <h4>Ошибка загрузки формы возврата</h4>
          <p>Ответ сервера: ${xhr.responseText}</p>
        </div>
      `);
    }
  });
  
  // Обработчик закрытия вкладки
  $(`#${tabId} .btn-close`).on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
  });
}

// Функция для создания финансовой операции на основании заказа поставщику
function createFinanceFromPurchaseOrder(orderId, type = 'expense') {
  // Получаем информацию о заказе поставщику
  $.getJSON('/crm/modules/purchases/orders/order_api.php', { action: 'get_order_info', id: orderId }, function(response) {
    if (response.status === 'ok') {
      const data = response.data;
      
      // Создаем новую вкладку для финансовой операции
      const tabId = 'finance-tab-' + Math.floor(Math.random() * 1000000);
      const tabContentId = 'finance-content-' + Math.floor(Math.random() * 1000000);
      
      // Заголовок вкладки
      let tabTitle = (type === 'expense') ? 'Новый расход' : 'Новый приход';
      
      // Добавляем новую вкладку
      $('#crm-tabs').append(`
        <li class="nav-item">
          <a class="nav-link active" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" role="tab">
            ${tabTitle} <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
          </a>
        </li>
      `);
      
      // Добавляем содержимое вкладки
      $('#crm-tab-content').append(`
        <div class="tab-pane fade show active" id="${tabContentId}" role="tabpanel">
          <div class="text-center p-5">
            <div class="spinner-border" role="status">
              <span class="visually-hidden">Загрузка...</span>
            </div>
          </div>
        </div>
      `);
      
      // Делаем новую вкладку активной
      $('.nav-link').removeClass('active');
      $('.tab-pane').removeClass('show active');
      $(`#${tabId}`).addClass('active');
      $(`#${tabContentId}`).addClass('show active');
      
      // Загружаем содержимое редактирования финансовой операции
      $.ajax({
        url: '/crm/modules/finances/edit_partial.php',
        data: { 
          id: 0,
          type: type,
          purchase_order_id: orderId,
          amount: data.total_amount || data.order_sum,
          counterparty_id: data.supplier_id,
          tab: 1,
          tab_id: tabId,
          content_id: tabContentId,
          based_on: 'purchase_order',
          description: 'Оплата по заказу поставщику №' + data.purchase_order_number
        },
        success: function(html) {
          $(`#${tabContentId}`).html(html);
        },
        error: function(xhr, status, error) {
          $(`#${tabContentId}`).html(`
            <div class="alert alert-danger">
              <h4>Ошибка загрузки формы финансовой операции</h4>
              <p>Ответ сервера: ${xhr.responseText}</p>
            </div>
          `);
        }
      });
      
      // Обработчик закрытия вкладки
      $(`#${tabId} .btn-close`).on('click', function(e) {
        e.stopPropagation();
        closeModuleTab(tabId, tabContentId);
      });
    } else {
      alert('Ошибка при получении данных заказа: ' + (response.message || 'Неизвестная ошибка'));
    }
  });
}

// Функция инициализации выпадающих меню (глобальная)
function initDropdowns() {
  console.log('🔧 [PURCHASES/ORDERS/LIST] Инициализация dropdown кнопок...');
  
  // Проверяем наличие Bootstrap
  if (typeof bootstrap !== 'undefined') {
    console.log('✅ Bootstrap найден, используем стандартные dropdown');
    // Bootstrap 5 сам обрабатывает data-bs-toggle="dropdown"
    return;
  }
  
  console.log('⚠️ Bootstrap не найден, используем кастомные обработчики');
  
  // Кастомная обработка для кнопок с data-bs-toggle="dropdown"
  $('[data-bs-toggle="dropdown"], .dropdown-toggle').off('click.customDropdown').on('click.customDropdown', function(e) {
    console.log('👆 Клик по dropdown кнопке:', $(this).text().trim());
    
    const $button = $(this);
    const $menu = $button.next('.dropdown-menu').length > 0 
                  ? $button.next('.dropdown-menu') 
                  : $button.siblings('.dropdown-menu');
    const $container = $button.closest('.dropdown, .btn-group');
    
    console.log('📋 Найдено меню:', $menu.length > 0);
    console.log('📦 Найден контейнер:', $container.length > 0);
    
    // Закрываем все другие меню
    $('.dropdown, .btn-group').not($container).removeClass('show');
    $('.dropdown-menu').not($menu).removeClass('show').hide();
    
    // Переключаем текущее меню
    const isOpen = $container.hasClass('show');
    $container.toggleClass('show', !isOpen);
    $menu.toggleClass('show', !isOpen);
    
    if (!isOpen) {
      $menu.show();
      console.log('🟢 Меню открыто');
    } else {
      $menu.hide();
      console.log('🔴 Меню закрыто');
    }
    
    // Обновляем aria-expanded
    $button.attr('aria-expanded', !isOpen);
    
    // Предотвращаем всплытие
    e.preventDefault();
    e.stopPropagation();
    
    return false;
  });
  
  // Закрытие при клике вне меню
  $(document).off('click.customDropdown').on('click.customDropdown', function(e) {
    if (!$(e.target).closest('.dropdown, .btn-group').length) {
      $('.dropdown, .btn-group').removeClass('show');
      $('.dropdown-menu').removeClass('show').hide();
      $('[data-bs-toggle="dropdown"], .dropdown-toggle').attr('aria-expanded', 'false');
    }
  });
  
  // Предотвращаем закрытие при клике на элементы меню
  $('.dropdown-menu').off('click.customDropdown').on('click.customDropdown', function(e) {
    e.stopPropagation();
  });
  
  console.log('✅ Кастомные dropdown обработчики установлены');
}

// Вызываем инициализацию после загрузки
$(document).ready(function() {
  console.log('📄 [PURCHASES/ORDERS/LIST] Документ загружен, инициализируем dropdown...');
  setTimeout(function() {
    initDropdowns();
  }, 100);
});
</script>