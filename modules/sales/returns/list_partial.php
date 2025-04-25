<?php
// /crm/modules/sales/returns/list_partial.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'sales_returns')) {
    die("<div class='text-danger'>Доступ запрещён</div>");
}

$sql = "
SELECT r.id, r.return_number, r.return_date, r.order_id, 
       r.warehouse_id, r.loader_id, r.reason, r.notes, r.status, r.conducted,
       o.order_number,
       w.name AS warehouse_name,
       l.name AS loader_name,
       (SELECT SUM((ri.quantity * ri.price) - ri.discount) FROM PCRM_ReturnItem ri WHERE ri.return_id = r.id) AS total_amount
FROM PCRM_ReturnHeader r
LEFT JOIN PCRM_Order o ON r.order_id = o.id
LEFT JOIN PCRM_Warehouse w ON r.warehouse_id = w.id
LEFT JOIN PCRM_Loaders l ON r.loader_id = l.id
ORDER BY r.id DESC
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
<h4>Возвраты покупателей</h4>
<button class="btn btn-primary btn-sm mb-2" onclick="openReturnEditTab(0)">Добавить возврат</button>

<table class="table table-bordered table-sm">
  <thead>
    <tr>
      <th>ID</th>
      <th>Номер</th>
      <th>Дата</th>
      <th>Заказ</th>
      <th>Склад</th>
      <th>Грузчик</th>
      <th>Причина возврата</th>
      <th>Статус</th>
      <th>Проведён?</th>
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
        <?php if ($r['order_id']): ?>
        #<?= $r['order_id'] ?> (<?= htmlspecialchars($r['order_number'] ?? '') ?>)
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
        <button class="btn btn-warning btn-sm" onclick="openReturnEditTab(<?= $r['id'] ?>)">Редакт</button>
        <button class="btn btn-danger btn-sm" onclick="deleteReturn(<?= $r['id'] ?>)">Удал</button>
        <button class="btn btn-info btn-sm" onclick="printReturn(<?= $r['id'] ?>)">Печать</button>
        
        <!-- Кнопка "Создать на основании" -->
        <div class="btn-group">
          <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            На основании
          </button>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#" onclick="createRkoFromReturn(<?= $r['id'] ?>)">Расходная кассовая операция</a></li>
          </ul>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<script>
function openReturnEditTab(returnId, options = {}) {
  // Создаем новую вкладку для редактирования
  const tabId = 'return-tab-' + Math.floor(Math.random() * 1000000);
  const tabContentId = 'return-content-' + Math.floor(Math.random() * 1000000);
  
  // Заголовок вкладки
  let tabTitle = returnId > 0 ? `Возврат #${returnId}` : 'Новый возврат';
  
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
  if (options.order_id) params.order_id = options.order_id;
  if (options.based_on) params.based_on = options.based_on;
  
  // Загружаем содержимое редактирования
  $.ajax({
    url: '/crm/modules/sales/returns/edit_partial.php',
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

function deleteReturn(returnId) {
  if (!confirm('Вы уверены, что хотите удалить этот возврат?')) return;
  
  $.get('/crm/modules/sales/returns/delete.php', { id: returnId }, function(response) {
    if (response === 'OK') {
      // Обновляем список возвратов
      updateReturnsList();
      showNotification('Возврат успешно удален', 'success');
    } else {
      alert('Ошибка при удалении: ' + response);
    }
  });
}

function printReturn(returnId) {
  // Открываем окно печати
  window.open('/crm/modules/sales/returns/print.php?id=' + returnId, '_blank');
}

// Функция для обновления списка возвратов
function updateReturnsList() {
  // Обновляем список возвратов в текущей вкладке
  $.get('/crm/modules/sales/returns/list_partial.php', function(html) {
    $('.tab-pane').each(function() {
      if ($(this).find('h4:contains("Возвраты покупателей")').length > 0) {
        $(this).html(html);
      }
    });
  });
}

// Функция для создания РКО на основании возврата
function createRkoFromReturn(returnId) {
  // Получаем информацию о возврате
  $.getJSON('/crm/modules/sales/returns/get_return_info.php', { id: returnId }, function(response) {
    if (response.status === 'ok') {
      const data = response.data;
      
      // Создаем новую вкладку для финансовой операции
      const tabId = 'finance-tab-' + Math.floor(Math.random() * 1000000);
      const tabContentId = 'finance-content-' + Math.floor(Math.random() * 1000000);
      
      // Заголовок вкладки
      let tabTitle = 'Новый расход';
      
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
      
      // Получаем counterparty_id из заказа, если есть
      const orderId = data.order_id;
      let counterpartyId = null;
      
      function loadFinanceForm(counterpId) {
        // Загружаем содержимое редактирования финансовой операции
        $.ajax({
          url: '/crm/modules/finances/edit_partial.php',
          data: { 
            id: 0,
            type: 'expense',
            return_id: returnId,
            order_id: orderId,
            amount: data.total_amount,
            counterparty_id: counterpId,
            tab: 1,
            tab_id: tabId,
            content_id: tabContentId,
            based_on: 'return',
            description: 'Возврат средств по возврату №' + data.return_number + (orderId ? ' (Заказ №' + orderId + ')' : '')
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
      
      // Если есть ID заказа, получаем контрагента из заказа
      if (orderId) {
        $.getJSON('/crm/modules/sales/orders/order_api.php', { action: 'get_order_info', id: orderId }, function(orderData) {
          if (orderData.status === 'ok') {
            counterpartyId = orderData.data.customer;
            loadFinanceForm(counterpartyId);
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
</script>