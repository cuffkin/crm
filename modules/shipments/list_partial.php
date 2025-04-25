<?php
// /crm/modules/shipments/list_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'shipments')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

// Основной запрос для получения списка отгрузок
$sql = "
SELECT sh.id, sh.shipment_number, sh.shipment_date, sh.order_id, 
       sh.warehouse_id, sh.loader_id, sh.status, sh.conducted, sh.comment,
       o.order_number,
       w.name AS warehouse_name,
       l.name AS loader_name
FROM PCRM_ShipmentHeader sh
LEFT JOIN PCRM_Order o ON sh.order_id = o.id
LEFT JOIN PCRM_Warehouse w ON sh.warehouse_id = w.id
LEFT JOIN PCRM_Loaders l ON sh.loader_id = l.id
ORDER BY sh.id DESC
";

$res = $conn->query($sql);
if (!$res) {
    die("<div class='text-danger'>Ошибка запроса: " . $conn->error . "</div>");
}
$shipments = $res->fetch_all(MYSQLI_ASSOC);

// Функция для расчета суммы отгрузки
function calculateShipmentTotal($conn, $id) {
    $sql = "
        SELECT SUM((quantity * price) - discount) as total
        FROM PCRM_Shipments
        WHERE shipment_header_id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? ($row['total'] ?? 0) : 0;
}

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
<h4>Отгрузки</h4>
<button class="btn btn-primary btn-sm mb-2" onclick="openShipmentEditTab(0)">Добавить отгрузку</button>

<table class="table table-bordered table-sm">
  <thead>
    <tr>
      <th>ID</th>
      <th>Номер</th>
      <th>Дата</th>
      <th>Заказ</th>
      <th>Склад</th>
      <th>Грузчик</th>
      <th>Статус</th>
      <th>Проведена</th>
      <th>Сумма</th>
      <th>Действия</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($shipments as $sh): 
        // Рассчитываем сумму для каждой отгрузки
        $total = calculateShipmentTotal($conn, $sh['id']);
    ?>
    <tr>
      <td><?= $sh['id'] ?></td>
      <td><?= htmlspecialchars($sh['shipment_number']) ?></td>
      <td><?= $sh['shipment_date'] ?></td>
      <td>#<?= $sh['order_id'] ?> (<?= htmlspecialchars($sh['order_number'] ?? '') ?>)</td>
      <td><?= htmlspecialchars($sh['warehouse_name'] ?? '') ?></td>
      <td><?= htmlspecialchars($sh['loader_name'] ?? '') ?></td>
      <td><?= translateStatus($sh['status']) ?></td>
      <td><?= isConducted($sh['conducted']) ?></td>
      <td><?= number_format($total, 2, '.', ' ') ?> руб.</td>
      <td>
        <button class="btn btn-warning btn-sm" onclick="openShipmentEditTab(<?= $sh['id'] ?>)">Ред.</button>
        <button class="btn btn-danger btn-sm" onclick="deleteShipment(<?= $sh['id'] ?>)">Удал.</button>
        <button class="btn btn-info btn-sm" onclick="printShipment(<?= $sh['id'] ?>)">Печать</button>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<script>
function openShipmentEditTab(shipmentId) {
  // Создаем новую вкладку для редактирования
  const tabId = 'shipment-tab-' + Math.floor(Math.random() * 1000000);
  const tabContentId = 'shipment-content-' + Math.floor(Math.random() * 1000000);
  
  // Заголовок вкладки
  let tabTitle = shipmentId > 0 ? 'Отгрузка #' + shipmentId : 'Новая отгрузка';
  
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
    url: '/crm/modules/shipments/edit_partial.php',
    data: { 
      id: shipmentId,
      tab: 1,
      tab_id: tabId,
      content_id: tabContentId
    },
    success: function(html) {
      $(`#${tabContentId}`).html(html);
    },
    error: function(xhr, status, error) {
      console.error("Error loading shipment:", error);
      $(`#${tabContentId}`).html(`
        <div class="alert alert-danger">
          <h4>Ошибка загрузки отгрузки</h4>
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

function deleteShipment(shipmentId) {
  if (!confirm('Вы уверены, что хотите удалить эту отгрузку?')) return;
  
  $.get('/crm/modules/shipments/delete.php', { id: shipmentId }, function(response) {
    if (response === 'OK') {
      // Обновляем список отгрузок
      updateShipmentList();
      showNotification('Отгрузка успешно удалена', 'success');
    } else {
      alert('Ошибка при удалении: ' + response);
    }
  });
}

function printShipment(shipmentId) {
  // Открываем окно печати
  window.open('/crm/modules/shipments/print.php?id=' + shipmentId, '_blank');
}

function updateShipmentList() {
  // Обновляем список отгрузок
  $.get('/crm/modules/shipments/list_partial.php', function(html) {
    $('.tab-pane').each(function() {
      if ($(this).find('h4:contains("Отгрузки")').length > 0) {
        $(this).html(html);
      }
    });
  });
}
</script>