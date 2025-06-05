<?php
// /crm/modules/purchases/returns/list_partial.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'purchases_returns')) {
    die("<div class='text-danger'>Доступ запрещён</div>");
}

$sql = "
SELECT sr.id, sr.return_number, sr.return_date, sr.purchase_order_id, 
       sr.warehouse_id, sr.loader_id, sr.reason, sr.notes, sr.status, sr.conducted,
       po.purchase_order_number,
       w.name AS warehouse_name,
       l.name AS loader_name,
       (SELECT SUM((sri.quantity * sri.price) - sri.discount) FROM PCRM_SupplierReturnItem sri WHERE sri.return_id = sr.id) AS total_amount
FROM PCRM_SupplierReturnHeader sr
LEFT JOIN PCRM_PurchaseOrder po ON sr.purchase_order_id = po.id
LEFT JOIN PCRM_Warehouse w ON sr.warehouse_id = w.id
LEFT JOIN PCRM_Loaders l ON sr.loader_id = l.id
ORDER BY sr.id DESC
";
$res = $conn->query($sql);
if (!$res) {
    die("<div class='text-danger'>Ошибка запроса: ".$conn->error."</div>");
}
$returns = $res->fetch_all(MYSQLI_ASSOC);

function translateStatus($dbVal) {
    switch ($dbVal) {
        case 'new':       return '<span class="badge bg-primary">Новый</span>';
        case 'confirmed': return '<span class="badge bg-success">Подтверждён</span>';
        case 'completed': return '<span class="badge bg-secondary">Завершён</span>';
        case 'cancelled': return '<span class="badge bg-danger">Отменён</span>';
        default:          return $dbVal;
    }
}

function translateReason($reason) {
    return $reason;
}

function getConductedText($val) {
    return ($val == 1) ? 'да' : 'нет';
}
?>
<h4>Возврат поставщикам</h4>
<button class="btn btn-primary btn-sm mb-2" onclick="openSupplierReturnEditTab(0)">Добавить возврат</button>

<table class="table table-bordered table-sm">
  <thead>
    <tr>
      <th>ID</th>
      <th>Номер</th>
      <th>Дата</th>
      <th>Заказ поставщику</th>
      <th>Склад</th>
      <th>Грузчик</th>
      <th>Причина возврата</th>
      <th>Статус</th>
      <th>Проведён</th>
      <th>Сумма</th>
      <th>Действия</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($returns as $r):
      $statusHtml = translateStatus($r['status']);
      $conductedTxt = getConductedText($r['conducted']);
      $reasonTxt = translateReason($r['reason']);
    ?>
    <tr>
      <td><?= $r['id'] ?></td>
      <td><?= htmlspecialchars($r['return_number']) ?></td>
      <td><?= $r['return_date'] ?></td>
      <td>
        <?php if ($r['purchase_order_id']): ?>
        #<?= $r['purchase_order_id'] ?> (<?= htmlspecialchars($r['purchase_order_number'] ?? '') ?>)
        <?php else: ?>
        -
        <?php endif; ?>
      </td>
      <td><?= htmlspecialchars($r['warehouse_name'] ?? '') ?></td>
      <td><?= htmlspecialchars($r['loader_name'] ?? '') ?></td>
      <td><?= htmlspecialchars($reasonTxt) ?></td>
      <td><?= $statusHtml ?></td>
      <td><?= $conductedTxt ?></td>
      <td><?= number_format($r['total_amount'] ?? 0, 2, '.', ' ') ?></td>
      <td>
        <button class="btn btn-warning btn-sm" onclick="openSupplierReturnEditTab(<?= $r['id'] ?>)">Редакт</button>
        <button class="btn btn-danger btn-sm" onclick="deleteSupplierReturn(<?= $r['id'] ?>)">Удал</button>
        <button class="btn btn-info btn-sm" onclick="printSupplierReturn(<?= $r['id'] ?>)">Печать</button>
        
        <!-- Кнопка "Создать на основании" -->
        <div class="btn-group">
          <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            На основании
          </button>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#" onclick="createFinanceFromSupplierReturn(<?= $r['id'] ?>, 'income')">Приходная кассовая операция</a></li>
          </ul>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<script>
function openSupplierReturnEditTab(returnId, options = {}) {
  // Создаем новую вкладку для редактирования
  const tabId = 'supplier-return-tab-' + Math.floor(Math.random() * 1000000);
  const tabContentId = 'supplier-return-content-' + Math.floor(Math.random() * 1000000);
  
  // Заголовок вкладки
  let tabTitle = returnId > 0 ? `Возврат поставщику #${returnId}` : 'Новый возврат поставщику';
  
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
  
  // Формируем параметры для запроса
  const params = {
    id: returnId,
    tab: 1,
    tab_id: tabId,
    content_id: tabContentId
  };
  
  // Добавляем параметры, если они переданы
  if (options.purchase_order_id) params.purchase_order_id = options.purchase_order_id;
  if (options.receipt_id) params.receipt_id = options.receipt_id;
  if (options.based_on) params.based_on = options.based_on;
  
  // Загружаем содержимое редактирования
  $.ajax({
    url: '/crm/modules/purchases/returns/edit_partial.php',
    data: params,
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

function deleteSupplierReturn(returnId) {
  // Вызываем глобальную функцию напрямую (она определена в app.js)
  if (typeof moveToTrash === 'function') {
    moveToTrash('supplier_return', returnId, 'Вы уверены, что хотите удалить этот возврат поставщику?', updateSupplierReturnsList);
  } else {
    console.error('Глобальная функция moveToTrash не найдена');
    alert('Ошибка: функция удаления не найдена');
  }
}

function printSupplierReturn(returnId) {
  // Открываем окно печати
  window.open('/crm/modules/purchases/returns/print.php?id=' + returnId, '_blank');
}

// Функция для обновления списка возвратов
function updateSupplierReturnsList() {
  // Обновляем список возвратов в текущей вкладке
  $.get('/crm/modules/purchases/returns/list_partial.php', function(html) {
    $('.tab-pane').each(function() {
      if ($(this).find('h4:contains("Возврат поставщикам")').length > 0) {
        $(this).html(html);
      }
    });
  });
}

// Функция для создания ПКО на основании возврата поставщику
function createFinanceFromSupplierReturn(returnId, type = 'income') {
  // Получаем информацию о возврате
  $.getJSON('/crm/modules/purchases/returns/api_handler.php', { action: 'get_return_info', id: returnId }, function(response) {
    if (response.status === 'ok') {
      const data = response.data;
      
      // Создаем новую вкладку для финансовой операции
      const tabId = 'finance-tab-' + Math.floor(Math.random() * 1000000);
      const tabContentId = 'finance-content-' + Math.floor(Math.random() * 1000000);
      
      // Заголовок вкладки
      let tabTitle = (type === 'income') ? 'Новый приход' : 'Новый расход';
      
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
      const purchaseOrderId = data.purchase_order_id;
      let supplierId = null;
      
      function loadFinanceForm(supplierId) {
        // Загружаем содержимое редактирования финансовой операции
        $.ajax({
          url: '/crm/modules/finances/edit_partial.php',
          data: { 
            id: 0,
            type: type,
            supplier_return_id: returnId,
            purchase_order_id: purchaseOrderId,
            amount: data.total_amount,
            counterparty_id: supplierId,
            tab: 1,
            tab_id: tabId,
            content_id: tabContentId,
            based_on: 'supplier_return',
            description: 'Возврат средств по возврату поставщику №' + data.return_number + (purchaseOrderId ? ' (Заказ поставщику №' + purchaseOrderId + ')' : '')
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
      
      // Если есть ID заказа, получаем поставщика из заказа
      if (purchaseOrderId) {
        $.getJSON('/crm/modules/purchases/orders/order_api.php', { 
            action: 'get_order_info', 
            id: purchaseOrderId 
        }, function(orderData) {
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
      
      // Обработчик закрытия вкладки
      $(`#${tabId} .btn-close`).on('click', function(e) {
        e.stopPropagation();
        closeModuleTab(tabId, tabContentId);
      });
    } else {
      alert('Ошибка при получении данных возврата: ' + (response.message || 'Неизвестная ошибка'));
    }
  });
}

// Функция инициализации выпадающих меню (глобальная)
function initDropdowns() {
  console.log('🔧 [PURCHASES/RETURNS/LIST] Инициализация dropdown кнопок...');
  
  // Проверяем наличие Bootstrap
  if (typeof bootstrap !== 'undefined') {
    console.log('✅ Bootstrap найден, используем стандартные dropdown');
    return;
  }
  
  console.log('⚠️ Bootstrap не найден, используем кастомные обработчики');
  
  $('[data-bs-toggle="dropdown"], .dropdown-toggle').off('click.customDropdown').on('click.customDropdown', function(e) {
    console.log('👆 Клик по dropdown кнопке:', $(this).text().trim());
    
    const $button = $(this);
    const $menu = $button.next('.dropdown-menu').length > 0 
                  ? $button.next('.dropdown-menu') 
                  : $button.siblings('.dropdown-menu');
    const $container = $button.closest('.dropdown, .btn-group');
    
    $('.dropdown, .btn-group').not($container).removeClass('show');
    $('.dropdown-menu').not($menu).removeClass('show').hide();
    
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
    
    $button.attr('aria-expanded', !isOpen);
    e.preventDefault();
    e.stopPropagation();
    return false;
  });
  
  $(document).off('click.customDropdown').on('click.customDropdown', function(e) {
    if (!$(e.target).closest('.dropdown, .btn-group').length) {
      $('.dropdown, .btn-group').removeClass('show');
      $('.dropdown-menu').removeClass('show').hide();
      $('[data-bs-toggle="dropdown"], .dropdown-toggle').attr('aria-expanded', 'false');
    }
  });
  
  $('.dropdown-menu').off('click.customDropdown').on('click.customDropdown', function(e) {
    e.stopPropagation();
  });
  
  console.log('✅ Кастомные dropdown обработчики установлены');
}

$(document).ready(function() {
  console.log('📄 [PURCHASES/RETURNS/LIST] Документ загружен, инициализируем dropdown...');
  setTimeout(function() {
    initDropdowns();
  }, 100);
});
</script>