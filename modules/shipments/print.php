<?php
// /crm/modules/shipments/print.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);

$sql = "
SELECT sh.*, 
       o.order_number,
       w.name AS warehouse_name,
       d.name AS driver_name, d.phone AS driver_phone
FROM PCRM_ShipmentHeader sh
LEFT JOIN PCRM_Order o ON sh.order_id = o.id
LEFT JOIN PCRM_Warehouse w ON sh.warehouse_id = w.id
LEFT JOIN PCRM_Drivers d ON sh.driver_id = d.id
WHERE sh.id=?
";
$st = $conn->prepare($sql);
$st->bind_param("i", $id);
$st->execute();
$res = $st->get_result();
if (!$res || $res->num_rows === 0) {
    die("Отгрузка не найдена");
}
$shipment = $res->fetch_assoc();

// Получаем позиции отгрузки
$sqlItems = "
SELECT s.*, p.name AS product_name,
       l.name AS loader_name
FROM PCRM_ShipmentItem s
LEFT JOIN PCRM_Product p ON s.product_id = p.id
LEFT JOIN PCRM_Loaders l ON s.unloaded_by = l.id
WHERE s.shipment_header_id = ?
ORDER BY s.id ASC
";
$st2 = $conn->prepare($sqlItems);
$st2->bind_param("i", $id);
$st2->execute();
$res2 = $st2->get_result();
$items = $res2->fetch_all(MYSQLI_ASSOC);

// Расчет общей суммы
$total_amount = 0;
foreach ($items as $item) {
    $total_amount += ($item['quantity'] * $item['price']) - $item['discount'];
}

// Функция перевода статуса на русский
function translateStatus($dbVal) {
    switch ($dbVal) {
        case 'new':        return 'Новая';
        case 'in_progress': return 'В процессе';
        case 'completed':  return 'Завершена';
        case 'cancelled':  return 'Отменена';
        default:           return $dbVal;
    }
}

// Получаем русскоязычный статус
$statusRus = translateStatus($shipment['status']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Печать отгрузки <?= htmlspecialchars($shipment['shipment_number']) ?></title>
  <style>
    body { font-family: sans-serif; }
    .title { font-weight: bold; font-size: 1.2em; margin-bottom: 0.5em; }
    .label { font-weight: bold; }
    .block { margin-bottom: 1em; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 1em; }
    th, td { border: 1px solid #ddd; padding: 5px; }
    th { background-color: #f5f5f5; }
    .text-right { text-align: right; }
  </style>
</head>
<body onload="window.print()">

<div class="title">Отгрузка №<?= htmlspecialchars($shipment['shipment_number']) ?></div>
<div class="block">
  <span class="label">ID:</span> <?= $shipment['id'] ?>
</div>
<div class="block">
  <span class="label">Заказ:</span>
  #<?= $shipment['order_id'] ?> (<?= htmlspecialchars($shipment['order_number'] ?? '') ?>)
</div>
<div class="block">
  <span class="label">Дата:</span> <?= $shipment['shipment_date'] ?><br>
  <span class="label">Склад:</span> <?= htmlspecialchars($shipment['warehouse_name'] ?? '') ?><br>
  <span class="label">Статус:</span> <?= htmlspecialchars($statusRus) ?><br>
  <span class="label">Проведена:</span> <?= $shipment['conducted'] == 1 ? 'Да' : 'Нет' ?>
</div>
<div class="block">
  <?php if ($shipment['driver_name']): ?>
  <span class="label">Водитель:</span> <?= htmlspecialchars($shipment['driver_name']) ?>
  <?php if ($shipment['driver_phone']): ?>
   (Тел: <?= htmlspecialchars($shipment['driver_phone']) ?>)
  <?php endif; ?>
  <br>
  <?php endif; ?>
</div>

<?php if ($shipment['comment']): ?>
<div class="block">
  <span class="label">Комментарий:</span>
  <?= nl2br(htmlspecialchars($shipment['comment'])) ?>
</div>
<?php endif; ?>

<div class="block">
  <span class="label">Позиции отгрузки:</span>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Товар</th>
        <th>Кол-во</th>
        <th>Цена</th>
        <th>Скидка</th>
        <th>Грузчик</th>
        <th>Проведено</th>
        <th>Сумма</th>
      </tr>
    </thead>
    <tbody>
      <?php 
      $i = 1; 
      foreach ($items as $item): 
        $sum = ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0);
      ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($item['product_name'] ?? '') ?></td>
        <td><?= $item['quantity'] ?></td>
        <td class="text-right"><?= $item['price'] ?></td>
        <td class="text-right"><?= $item['discount'] ?? '0.00' ?></td>
        <td><?= htmlspecialchars($item['loader_name'] ?? '') ?></td>
        <td><?= $item['conducted'] == 1 ? 'Да' : 'Нет' ?></td>
        <td class="text-right"><?= number_format($sum, 2, '.', ' ') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="7" class="text-right"><strong>Итого:</strong></td>
        <td class="text-right"><strong><?= number_format($total_amount, 2, '.', ' ') ?></strong></td>
      </tr>
    </tfoot>
  </table>
</div>

<div class="block">
  <p>______________________ / _____________________</p>
  <small>(подпись)</small> <small>(расшифровка)</small>
</div>

</body>
</html>