<?php
// /crm/modules/sales/orders/list_partial.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'sales_orders')) {
    die("<div class='text-danger'>Доступ запрещён</div>");
}

// Параметры пагинации
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 30;
$offset = ($page - 1) * $per_page;

// Ограничение количества записей
$limit = $per_page == 0 ? "" : "LIMIT $offset, $per_page";

// Подсчет общего количества заказов
$count_sql = "SELECT COUNT(*) as total FROM PCRM_Order WHERE deleted = 0";
$count_result = $conn->query($count_sql);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = $per_page > 0 ? ceil($total_records / $per_page) : 1;

// Основной запрос для получения списка заказов
$sql = "
SELECT o.id, o.order_number, o.order_date, o.status, o.customer, o.deleted, o.conducted, 
       o.driver_id, o.delivery_address, o.contacts,
       c.name AS customer_name,
       u.username AS creator_name,
       SUM((oi.quantity * oi.price) - oi.discount) AS order_sum
FROM PCRM_Order o
LEFT JOIN PCRM_Counterparty c ON o.customer = c.id
LEFT JOIN PCRM_OrderItem oi ON o.id = oi.order_id
LEFT JOIN PCRM_User u ON o.created_by = u.id
WHERE o.deleted = 0
GROUP BY o.id
ORDER BY o.id DESC
$limit
";

$res = $conn->query($sql);
if (!$res) {
    die("<div class='text-danger'>Ошибка запроса: " . $conn->error . "</div>");
}
$orders = $res->fetch_all(MYSQLI_ASSOC);

// Функция преобразования статуса заказа в читаемый вид
function translateOrderStatus($status) {
    switch ($status) {
        case 'new':        return '<span class="badge bg-primary">Новый</span>';
        case 'processing': return '<span class="badge bg-info">В обработке</span>';
        case 'completed':  return '<span class="badge bg-success">Выполнен</span>';
        case 'cancelled':  return '<span class="badge bg-danger">Отменен</span>';
        default:           return '<span class="badge bg-secondary">'. $status .'</span>';
    }
}

// Функция для вывода "да"/"нет" для проведенных заказов
function isConducted($val) {
    return ($val == 2) ? 'да' : 'нет';
}

// Функция отображения типа доставки
function getDeliveryType($driver_id) {
    return !empty($driver_id) ? 'Доставка' : 'Самовывоз';
}
?>
<h4>Заказы покупателей</h4>
<div class="d-flex justify-content-between mb-2">
  <button class="btn btn-primary btn-sm" onclick="openOrderEditTab(0)">Добавить заказ</button>
  <div class="d-flex align-items-center">
    <label class="me-2">Записей на странице:</label>
    <select class="form-select form-select-sm" style="width: auto;" onchange="changePerPage(this.value)">
      <option value="30" <?= $per_page == 30 ? 'selected' : '' ?>>30</option>
      <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
      <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
      <option value="0" <?= $per_page == 0 ? 'selected' : '' ?>>Все</option>
    </select>
  </div>
</div>

<table class="table table-bordered table-sm">
  <thead>
    <tr>
      <th>Номер</th>
      <th>Дата и время</th>
      <th>Клиент</th>
      <th>Контакты</th>
      <th>Статус</th>
      <th>Тип</th>
      <th>Сумма</th>
      <th>Проведен</th>
      <th>Создал</th>
      <th>Действия</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($orders as $order): ?>
    <tr>
      <td><?= htmlspecialchars($order['order_number']) ?></td>
      <td><?= $order['order_date'] ?></td>
      <td><?= htmlspecialchars($order['customer_name'] ?? '--') ?></td>
      <td><?= htmlspecialchars($order['contacts'] ?? '--') ?></td>
      <td><?= translateOrderStatus($order['status']) ?></td>
      <td><?= getDeliveryType($order['driver_id']) ?></td>
      <td><?= number_format($order['order_sum'] ?? 0, 2, '.', ' ') ?> руб.</td>
      <td><?= isConducted($order['conducted']) ?></td>
      <td><?= htmlspecialchars($order['creator_name'] ?? '--') ?></td>
      <td>
        <button class="btn btn-warning btn-sm" onclick="openOrderEditTab(<?= $order['id'] ?>)">Ред.</button>
        <button class="btn btn-danger btn-sm" onclick="deleteOrder(<?= $order['id'] ?>)">Удал.</button>
        <button class="btn btn-info btn-sm" onclick="printOrder(<?= $order['id'] ?>)">Печать</button>
        
        <!-- Кнопка "Создать на основании" -->
        <div class="btn-group order-dropdown">
          <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
            На основании
          </button>
          <ul class="dropdown-menu position-static">
            <li><a class="dropdown-item" href="#" onclick="createShipmentFromOrder(<?= $order['id'] ?>)">Отгрузка</a></li>
            <li><a class="dropdown-item" href="#" onclick="createReturnFromOrder(<?= $order['id'] ?>)">Возврат</a></li>
            <li><a class="dropdown-item" href="#" onclick="createFinanceFromOrder(<?= $order['id'] ?>, 'income')">Приход денег</a></li>
          </ul>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- Пагинация -->
<?php if ($per_page > 0 && $total_pages > 1): ?>
<nav aria-label="Навигация по страницам">
  <ul class="pagination">
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
      <a class="page-link" href="#" onclick="goToPage(<?= $page - 1 ?>)" aria-label="Предыдущая">
        <span aria-hidden="true">&laquo;</span>
      </a>
    </li>
    
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
      <a class="page-link" href="#" onclick="goToPage(<?= $i ?>)"><?= $i ?></a>
    </li>
    <?php endfor; ?>
    
    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
      <a class="page-link" href="#" onclick="goToPage(<?= $page + 1 ?>)" aria-label="Следующая">
        <span aria-hidden="true">&raquo;</span>
      </a>
    </li>
  </ul>
</nav>
<?php endif; ?>

<style>
/* Стили для правильного отображения выпадающих меню в списке */
.btn-group.order-dropdown {
  position: relative;
}

.btn-group.order-dropdown .dropdown-menu.position-static {
  position: absolute !important;
  transform: translate(0, 32px) !important;
  top: 0 !important;
  left: 0 !important;
  margin: 0 !important;
  display: none;
  z-index: 1021;
}

.btn-group.order-dropdown.show .dropdown-menu.position-static {
  display: block;
}
</style>

<script>
function openOrderEditTab(orderId) {
  // Создаем новую вкладку для редактирования
  const tabId = 'order-tab-' + Math.floor(Math.random() * 1000000);
  const tabContentId = 'order-content-' + Math.floor(Math.random() * 1000000);
  
  // Заголовок вкладки
  let tabTitle = orderId > 0 ? 'Заказ #' + orderId : 'Новый заказ';
  
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
    url: '/crm/modules/sales/orders/edit_partial.php',
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

function deleteOrder(orderId) {
  if (!confirm('Вы уверены, что хотите удалить этот заказ?')) return;
  
  $.get('/crm/modules/sales/orders/delete.php', { id: orderId }, function(response) {
    if (response === 'OK') {
      updateOrderLists();
      showNotification('Заказ успешно удален', 'success');
    } else {
      alert('Ошибка при удалении: ' + response);
    }
  });
}

function printOrder(orderId) {
  // Открываем окно печати
  window.open('/crm/modules/sales/orders/print.php?id=' + orderId, '_blank');
}

function updateOrderLists() {
  // Получаем текущие параметры пагинации
  const currentPage = getURLParameter('page') || 1;
  const perPage = getURLParameter('per_page') || 30;
  
  // Обновляем список заказов с сохранением пагинации
  $.get('/crm/modules/sales/orders/list_partial.php', { page: currentPage, per_page: perPage }, function(html) {
    $('.tab-pane').each(function() {
      if ($(this).find('h4:contains("Заказы покупателей")').length > 0) {
        $(this).html(html);
      }
    });
  });
}

// Функция для получения параметра из URL
function getURLParameter(name) {
  const urlParams = new URLSearchParams(window.location.search);
  return urlParams.get(name);
}

// Функция для перехода на определенную страницу
function goToPage(page) {
  if (page <= 0) return;
  
  const perPage = getURLParameter('per_page') || 30;
  $.get('/crm/modules/sales/orders/list_partial.php', { page: page, per_page: perPage }, function(html) {
    $('.tab-pane').each(function() {
      if ($(this).find('h4:contains("Заказы покупателей")').length > 0) {
        $(this).html(html);
        
        // Обновляем URL без перезагрузки страницы
        const newUrl = updateQueryStringParameter(window.location.href, 'page', page);
        window.history.pushState({ path: newUrl }, '', newUrl);
      }
    });
  });
  
  return false; // Предотвращаем переход по ссылке
}

// Функция для изменения количества записей на странице
function changePerPage(perPage) {
  $.get('/crm/modules/sales/orders/list_partial.php', { page: 1, per_page: perPage }, function(html) {
    $('.tab-pane').each(function() {
      if ($(this).find('h4:contains("Заказы покупателей")').length > 0) {
        $(this).html(html);
        
        // Обновляем URL без перезагрузки страницы
        let newUrl = updateQueryStringParameter(window.location.href, 'per_page', perPage);
        newUrl = updateQueryStringParameter(newUrl, 'page', 1);
        window.history.pushState({ path: newUrl }, '', newUrl);
      }
    });
  });
}

// Функция для обновления параметров в URL
function updateQueryStringParameter(uri, key, value) {
  const re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
  const separator = uri.indexOf('?') !== -1 ? "&" : "?";
  
  if (uri.match(re)) {
    return uri.replace(re, '$1' + key + "=" + value + '$2');
  } else {
    return uri + separator + key + "=" + value;
  }
}

// Функции для создания документов на основании заказа
function createShipmentFromOrder(orderId) {
  // Создаем новую вкладку для отгрузки
  const tabId = 'shipment-tab-' + Math.floor(Math.random() * 1000000);
  const tabContentId = 'shipment-content-' + Math.floor(Math.random() * 1000000);
  
  // Заголовок вкладки
  let tabTitle = 'Новая отгрузка';
  
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
  
  // Загружаем содержимое редактирования отгрузки с предварительно выбранным заказом
  $.ajax({
    url: '/crm/modules/shipments/edit_partial.php',
    data: { 
      id: 0,
      order_id: orderId,
      tab: 1,
      tab_id: tabId,
      content_id: tabContentId,
      based_on: 'order'
    },
    success: function(html) {
      $(`#${tabContentId}`).html(html);
    },
    error: function(xhr, status, error) {
      $(`#${tabContentId}`).html(`
        <div class="alert alert-danger">
          <h4>Ошибка загрузки формы отгрузки</h4>
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

function createFinanceFromOrder(orderId) {
  // Создаем новую вкладку для финансовой операции
  const tabId = 'finance-tab-' + Math.floor(Math.random() * 1000000);
  const tabContentId = 'finance-content-' + Math.floor(Math.random() * 1000000);
  
  // Заголовок вкладки
  let tabTitle = 'Новый приход';
  
  // Загружаем информацию о заказе
  $.getJSON('/crm/modules/sales/orders/order_api.php', { action: 'get_order_info', id: orderId }, function(orderData) {
    if (orderData.status === 'ok') {
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
          type: 'income',
          order_id: orderId,
          amount: orderData.data.order_sum,
          counterparty_id: orderData.data.customer,
          tab: 1,
          tab_id: tabId,
          content_id: tabContentId,
          based_on: 'order'
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
      alert('Ошибка при получении данных заказа: ' + (orderData.message || 'Неизвестная ошибка'));
    }
  });
}

function createReturnFromOrder(orderId) {
  // Создаем новую вкладку для возврата
  const tabId = 'return-tab-' + Math.floor(Math.random() * 1000000);
  const tabContentId = 'return-content-' + Math.floor(Math.random() * 1000000);
  
  // Заголовок вкладки
  let tabTitle = 'Новый возврат';
  
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
    url: '/crm/modules/sales/returns/edit_partial.php',
    data: { 
      id: 0,
      order_id: orderId,
      tab: 1,
      tab_id: tabId,
      content_id: tabContentId,
      based_on: 'order'
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

// Функция инициализации выпадающих меню
function initListDropdowns() {
  $('.order-dropdown .dropdown-toggle').on('click', function(e) {
    const $button = $(this);
    const $menu = $button.next('.dropdown-menu');
    const $container = $button.closest('.order-dropdown');
    
    // Убираем все активные меню и контейнеры
    $('.order-dropdown').not($container).removeClass('show');
    $('.order-dropdown .dropdown-menu').not($menu).hide();
    
    // Устанавливаем позицию меню относительно кнопки
    $container.toggleClass('show');
    $menu.toggle();
    
    // Предотвращаем закрытие меню при клике на его элементы
    $menu.find('.dropdown-item').on('click', function(e) {
      e.stopPropagation();
      $container.removeClass('show');
      $menu.hide();
    });
    
    // Останавливаем всплытие события, чтобы не закрывать меню сразу
    e.stopPropagation();
  });
  
  // Закрываем меню при клике вне его
  $(document).on('click', function(e) {
    if (!$(e.target).closest('.order-dropdown').length) {
      $('.order-dropdown').removeClass('show');
      $('.order-dropdown .dropdown-menu').hide();
    }
  });
}

// Вызываем инициализацию после загрузки
$(document).ready(function() {
  initListDropdowns();
});
</script>