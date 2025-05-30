<?php
// /crm/modules/purchases/receipts/list_partial.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'purchases_receipts')) {
    die("<div class='text-danger'>Доступ запрещён</div>");
}

// Основной запрос для получения списка приёмок
$sql = "
SELECT rh.id, rh.receipt_number, rh.receipt_date, rh.purchase_order_id, 
       rh.warehouse_id, rh.loader_id, rh.status, rh.conducted, rh.comment,
       po.purchase_order_number,
       w.name AS warehouse_name,
       l.name AS loader_name,
       (SELECT SUM((ri.quantity * ri.price) - ri.discount) FROM PCRM_ReceiptItem ri WHERE ri.receipt_header_id = rh.id) AS total_amount
FROM PCRM_ReceiptHeader rh
LEFT JOIN PCRM_PurchaseOrder po ON rh.purchase_order_id = po.id
LEFT JOIN PCRM_Warehouse w ON rh.warehouse_id = w.id
LEFT JOIN PCRM_Loaders l ON rh.loader_id = l.id
ORDER BY rh.id DESC
";

$res = $conn->query($sql);
if (!$res) {
    die("<div class='text-danger'>Ошибка запроса: " . $conn->error . "</div>");
}
$receipts = $res->fetch_all(MYSQLI_ASSOC);

// Функция преобразования статуса в читаемый вид
function translateStatus($status) {
    switch ($status) {
        case 'new':        return '<span class="badge bg-primary">Новая</span>';
        case 'in_progress': return '<span class="badge bg-info">В процессе</span>';
        case 'completed':  return '<span class="badge bg-success">Завершена</span>';
        case 'cancelled':  return '<span class="badge bg-danger">Отменена</span>';
        default:           return $status;
    }
}

// Функция для вывода "да"/"нет" для проведенных документов
function isConducted($val) {
    return ($val == 1) ? 'да' : 'нет';
}
?>
<h4>Приёмки товаров</h4>
<button class="btn btn-primary btn-sm mb-2" onclick="openReceiptEditTab(0)">Добавить приёмку</button>

<table class="table table-bordered table-sm">
  <thead>
    <tr>
      <th>ID</th>
      <th>Номер</th>
      <th>Дата</th>
      <th>Заказ поставщику</th>
      <th>Склад</th>
      <th>Грузчик</th>
      <th>Статус</th>
      <th>Проведена</th>
      <th>Сумма</th>
      <th>Действия</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($receipts as $receipt): 
        // Получаем сумму для каждой приёмки
        $total = $receipt['total_amount'] ?? 0;
    ?>
    <tr>
      <td><?= $receipt['id'] ?></td>
      <td><?= htmlspecialchars($receipt['receipt_number']) ?></td>
      <td><?= $receipt['receipt_date'] ?></td>
      <td>
        <?php if ($receipt['purchase_order_id']): ?>
        #<?= $receipt['purchase_order_id'] ?> (<?= htmlspecialchars($receipt['purchase_order_number'] ?? '') ?>)
        <?php else: ?>
        -
        <?php endif; ?>
      </td>
      <td><?= htmlspecialchars($receipt['warehouse_name'] ?? '') ?></td>
      <td><?= htmlspecialchars($receipt['loader_name'] ?? '') ?></td>
      <td><?= translateStatus($receipt['status']) ?></td>
      <td><?= isConducted($receipt['conducted']) ?></td>
      <td><?= number_format($total, 2, '.', ' ') ?> руб.</td>
      <td>
        <button class="btn btn-warning btn-sm" onclick="openReceiptEditTab(<?= $receipt['id'] ?>)">Ред.</button>
        <button class="btn btn-danger btn-sm" onclick="deleteReceipt(<?= $receipt['id'] ?>)">Удал.</button>
        <button class="btn btn-info btn-sm" onclick="printReceipt(<?= $receipt['id'] ?>)">Печать</button>
        
        <!-- Кнопка "Создать на основании" -->
        <div class="btn-group">
          <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            На основании
          </button>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#" onclick="createSupplierReturnFromReceipt(<?= $receipt['id'] ?>)">Возврат поставщику</a></li>
            <li><a class="dropdown-item" href="#" onclick="createFinanceFromReceipt(<?= $receipt['id'] ?>, 'expense')">Расходная операция</a></li>
          </ul>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<script>
function openReceiptEditTab(receiptId) {
  // Создаем новую вкладку для редактирования
  const tabId = 'receipt-tab-' + Math.floor(Math.random() * 1000000);
  const tabContentId = 'receipt-content-' + Math.floor(Math.random() * 1000000);
  
  // Заголовок вкладки
  let tabTitle = receiptId > 0 ? 'Приёмка #' + receiptId : 'Новая приёмка';
  
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
    url: '/crm/modules/purchases/receipts/edit_partial.php',
    data: { 
      id: receiptId,
      tab: 1,
      tab_id: tabId,
      content_id: tabContentId
    },
    success: function(html) {
      $(`#${tabContentId}`).html(html);
    },
    error: function(xhr, status, error) {
      console.error("Error loading receipt:", error);
      $(`#${tabContentId}`).html(`
        <div class="alert alert-danger">
          <h4>Ошибка загрузки приёмки</h4>
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

function deleteReceipt(receiptId) {
  if (!confirm('Вы уверены, что хотите удалить эту приёмку?')) return;
  
  $.get('/crm/modules/purchases/receipts/delete.php', { id: receiptId }, function(response) {
    if (response === 'OK') {
      // Обновляем список приёмок
      updateReceiptList();
      showNotification('Приёмка успешно удалена', 'success');
    } else {
      alert('Ошибка при удалении: ' + response);
    }
  });
}

function printReceipt(receiptId) {
  // Открываем окно печати
  window.open('/crm/modules/purchases/receipts/print.php?id=' + receiptId, '_blank');
}

function updateReceiptList() {
  // Обновляем список приёмок
  $.get('/crm/modules/purchases/receipts/list_partial.php', function(html) {
    $('.tab-pane').each(function() {
      if ($(this).find('h4:contains("Приёмки товаров")').length > 0) {
        $(this).html(html);
      }
    });
  });
}

// Функция для создания возврата поставщику на основании приёмки
function createSupplierReturnFromReceipt(receiptId) {
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
      receipt_id: receiptId,
      based_on: 'receipt',
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

// Функция для создания финансовой операции на основании приёмки
function createFinanceFromReceipt(receiptId, type = 'expense') {
  // Получаем информацию о приёмке
  $.getJSON('/crm/modules/purchases/receipts/api_handler.php', { action: 'get_receipt_info', id: receiptId }, function(response) {
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
      
      // Получаем counterparty_id из заказа поставщику, если есть
      let supplierId = null;
      const purchaseOrderId = data.purchase_order_id;
      
      if (purchaseOrderId) {
        $.getJSON('/crm/modules/purchases/orders/order_api.php', { action: 'get_order_info', id: purchaseOrderId }, function(orderData) {
          if (orderData.status === 'ok') {
            supplierId = orderData.data.supplier_id;
            loadFinanceForm(supplierId);
          } else {
            loadFinanceForm(null);
          }
        });
      } else {
        loadFinanceForm(null);
      }
      
      function loadFinanceForm(supplierId) {
        // Загружаем содержимое редактирования финансовой операции
        $.ajax({
          url: '/crm/modules/finances/edit_partial.php',
          data: { 
            id: 0,
            type: type,
            receipt_id: receiptId,
            purchase_order_id: purchaseOrderId || 0,
            amount: data.total_amount || 0,
            counterparty_id: supplierId || 0,
            tab: 1,
            tab_id: tabId,
            content_id: tabContentId,
            based_on: 'receipt',
            description: 'Оплата по приёмке №' + data.receipt_number + (purchaseOrderId ? ' (Заказ поставщику №' + purchaseOrderId + ')' : '')
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
      }
      
      // Обработчик закрытия вкладки
      $(`#${tabId} .btn-close`).on('click', function(e) {
        e.stopPropagation();
        closeModuleTab(tabId, tabContentId);
      });
    } else {
      alert('Ошибка при получении данных приёмки: ' + (response.message || 'Неизвестная ошибка'));
    }
  });
}

// Функция инициализации выпадающих меню (глобальная)
function initDropdowns() {
  console.log('🔧 [PURCHASES/RECEIPTS/LIST] Инициализация dropdown кнопок...');
  
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
  console.log('📄 [PURCHASES/RECEIPTS/LIST] Документ загружен, инициализируем dropdown...');
  setTimeout(function() {
    initDropdowns();
  }, 100);
});
</script>