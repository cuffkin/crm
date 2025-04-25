<?php
// /crm/modules/sales/orders/print.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);

$sql = "
SELECT o.*,
       org.name AS org_name,
       cc.name  AS customer_name,
       w.name   AS warehouse_name,
       d.name   AS driver_name,
       d.phone  AS driver_phone,
       u.username AS created_by_name
FROM PCRM_Order o
LEFT JOIN PCRM_Organization org ON o.organization = org.id
LEFT JOIN PCRM_Counterparty cc ON o.customer = cc.id
LEFT JOIN PCRM_Warehouse w    ON o.warehouse = w.id
LEFT JOIN PCRM_Drivers   d    ON o.driver_id = d.id
LEFT JOIN PCRM_User     u    ON o.created_by = u.id
WHERE o.id=$id AND o.deleted=0
";
$res = $conn->query($sql);
if (!$res || !$res->num_rows) {
    die("Заказ не найден или удалён");
}
$order = $res->fetch_assoc();

// Получаем список товаров для печатной формы
$sqlItems = "
SELECT i.*, p.name AS product_name
FROM PCRM_OrderItem i
LEFT JOIN PCRM_Product p ON i.product_id = p.id
WHERE i.order_id = ?
ORDER BY i.id ASC
";
$st = $conn->prepare($sqlItems);
$st->bind_param("i", $id);
$st->execute();
$result = $st->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);

// Функция перевода статуса на русский
function translateStatus($dbVal) {
    switch ($dbVal) {
        case 'new':       return 'Новый';
        case 'confirmed': return 'Подтверждён';
        case 'in_transit': return 'В пути';
        case 'completed': return 'Завершён';
        case 'cancelled': return 'Отменён';
        // Обратная совместимость со старыми значениями
        case 'draft':     return 'Новый';
        case 'shipped':   return 'В пути';
        default:          return $dbVal;
    }
}

// Получаем русскоязычный статус
$statusRus = translateStatus($order['status']);

// Подсчитываем общую сумму
$total_amount = 0;
foreach ($items as $item) {
    $total_amount += ($item['quantity'] * $item['price']) - $item['discount'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Печать заказа #<?= $order['id'] ?></title>
  <style>
    body { font-family: sans-serif; margin: 20px; }
    .title { font-weight: bold; font-size: 1.5em; text-align: center; margin-bottom: 20px; }
    .label { font-weight: bold; }
    .block { margin-bottom: 1em; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .text-right { text-align: right; }
    .total-row { font-weight: bold; }
    .signature-block { margin-top: 50px; }
    .signature-line { border-top: 1px solid #000; width: 200px; display: inline-block; margin-right: 20px; }
  </style>
</head>
<body onload="window.print()">

<div class="title">Заказ №<?= htmlspecialchars($order['order_number']) ?> от <?= date('d.m.Y', strtotime($order['order_date'])) ?></div>

<div class="block">
  <span class="label">Организация:</span>
  <?= htmlspecialchars($order['org_name'] ?? '') ?>
</div>
<div class="block">
  <span class="label">Номер:</span> <?= htmlspecialchars($order['order_number']) ?><br>
  <span class="label">Дата и время:</span> <?= $order['order_date'] ?><br>
  <span class="label">Статус:</span> <?= htmlspecialchars($statusRus) ?><br>
  <span class="label">Проведен:</span> <?= ($order['conducted'] == 2 ? 'Да' : 'Нет') ?>
</div>
<div class="block">
  <span class="label">Контрагент:</span>
  <?= htmlspecialchars($order['customer_name'] ?? '') ?><br>
  <span class="label">Контакты:</span>
  <?= htmlspecialchars($order['contacts'] ?? '') ?><br>
  <span class="label">Склад:</span>
  <?= htmlspecialchars($order['warehouse_name'] ?? '') ?><br>
  <span class="label">Адрес доставки:</span>
  <?= htmlspecialchars($order['delivery_address'] ?? '') ?>
</div>
<div class="block">
  <?php
  // Если driver_id не null => выводим
  if ($order['driver_name']) {
    echo '<span class="label">Тип доставки:</span> Доставка<br>';
    echo '<span class="label">Водитель:</span> '
         . htmlspecialchars($order['driver_name'])
         . ' (тел. ' . htmlspecialchars($order['driver_phone'] ?? '') . ')<br>';
  } else {
    echo '<span class="label">Тип доставки:</span> Самовывоз<br>';
  }
  ?>
</div>
<div class="block">
  <span class="label">Комментарий:</span>
  <?= nl2br(htmlspecialchars($order['comment'] ?? '')) ?>
</div>

<!-- Таблица товаров -->
<table>
  <thead>
    <tr>
      <th>№</th>
      <th>Наименование</th>
      <th>Количество</th>
      <th>Цена</th>
      <th>Скидка</th>
      <th>Сумма</th>
    </tr>
  </thead>
  <tbody>
    <?php $i = 1; foreach ($items as $item): 
      $sum = ($item['quantity'] * $item['price']) - $item['discount'];
    ?>
    <tr>
      <td><?= $i++ ?></td>
      <td><?= htmlspecialchars($item['product_name'] ?? '') ?></td>
      <td><?= $item['quantity'] ?></td>
      <td class="text-right"><?= number_format($item['price'], 2, '.', ' ') ?></td>
      <td class="text-right"><?= number_format($item['discount'], 2, '.', ' ') ?></td>
      <td class="text-right"><?= number_format($sum, 2, '.', ' ') ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr class="total-row">
      <td colspan="5" class="text-right">Итого:</td>
      <td class="text-right"><?= number_format($total_amount, 2, '.', ' ') ?></td>
    </tr>
  </tfoot>
</table>

<div class="signature-block">
  <div>
    <span class="label">Создал:</span> <?= htmlspecialchars($order['created_by_name'] ?? '') ?>
  </div>
  
  <div style="margin-top: 30px;">
    <div class="signature-line"></div>
    <div class="signature-line"></div>
  </div>
  <div>
    <span style="display: inline-block; width: 200px;">Подпись</span>
    <span style="display: inline-block; width: 200px;">Расшифровка</span>
  </div>
</div>

</body>
</html>