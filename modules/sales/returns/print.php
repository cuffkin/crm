<?php
// /crm/modules/sales/returns/print.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);

$sql = "
SELECT r.*, 
       o.order_number,
       w.name AS warehouse_name,
       l.name AS loader_name
FROM PCRM_ReturnHeader r
LEFT JOIN PCRM_Order o ON r.order_id = o.id
LEFT JOIN PCRM_Warehouse w ON r.warehouse_id = w.id
LEFT JOIN PCRM_Loaders l ON r.loader_id = l.id
WHERE r.id=?
";
$st = $conn->prepare($sql);
$st->bind_param("i", $id);
$st->execute();
$res = $st->get_result();
if (!$res || $res->num_rows === 0) {
    die("Возврат не найден");
}
$return = $res->fetch_assoc();

// Получаем позиции возврата
$sqlItems = "
SELECT ri.*, p.name AS product_name
FROM PCRM_ReturnItem ri
LEFT JOIN PCRM_Product p ON ri.product_id = p.id
WHERE ri.return_id = ?
ORDER BY ri.id ASC
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
        case 'new':       return 'Новый';
        case 'confirmed': return 'Подтверждён';
        case 'completed': return 'Завершён';
        case 'cancelled': return 'Отменён';
        default:          return $dbVal;
    }
}

// Получаем русскоязычный статус
$statusRus = translateStatus($return['status']);

// Получаем связанные документы
$relatedSql = "
(SELECT 'order' AS doc_type, o.id, o.order_number AS number, o.order_date AS date
 FROM PCRM_RelatedDocuments rd
 JOIN PCRM_Order o ON rd.source_id = o.id
 WHERE rd.source_type = 'order' AND rd.related_type = 'return' AND rd.related_id = ?)
UNION
(SELECT 'finance' AS doc_type, ft.id, ft.transaction_number AS number, ft.transaction_date AS date
 FROM PCRM_RelatedDocuments rd
 JOIN PCRM_FinancialTransaction ft ON rd.related_id = ft.id
 WHERE rd.source_type = 'return' AND rd.source_id = ? AND rd.related_type = 'finance')
ORDER BY date DESC
";
$relatedStmt = $conn->prepare($relatedSql);
$relatedStmt->bind_param("ii", $id, $id);
$relatedStmt->execute();
$relatedDocs = $relatedStmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Печать возврата <?= htmlspecialchars($return['return_number']) ?></title>
  <style>
    body { font-family: sans-serif; }
    .title { font-weight: bold; font-size: 1.2em; margin-bottom: 0.5em; }
    .label { font-weight: bold; }
    .block { margin-bottom: 1em; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 1em; }
    th, td { border: 1px solid #ddd; padding: 5px; }
    th { background-color: #f5f5f5; }
    .text-right { text-align: right; }
    hr { margin: 20px 0; border: 0; border-top: 1px solid #ccc; }
  </style>
</head>
<body onload="window.print()">

<div class="title">Возврат №<?= htmlspecialchars($return['return_number']) ?></div>
<div class="block">
  <span class="label">ID:</span> <?= $return['id'] ?>
</div>
<div class="block">
  <?php if ($return['order_id']): ?>
  <span class="label">Заказ:</span>
  #<?= $return['order_id'] ?> (<?= htmlspecialchars($return['order_number'] ?? '') ?>)<br>
  <?php endif; ?>
  <span class="label">Дата:</span> <?= $return['return_date'] ?><br>
  <span class="label">Склад:</span> <?= htmlspecialchars($return['warehouse_name'] ?? '') ?><br>
  <span class="label">Грузчик:</span> <?= htmlspecialchars($return['loader_name'] ?? '') ?><br>
  <span class="label">Статус:</span> <?= htmlspecialchars($statusRus) ?><br>
  <span class="label">Проведен:</span> <?= $return['conducted'] == 1 ? 'Да' : 'Нет' ?>
</div>
<div class="block">
  <span class="label">Причина возврата:</span> <?= htmlspecialchars($return['reason']) ?>
</div>

<?php if ($return['notes']): ?>
<div class="block">
  <span class="label">Примечания:</span>
  <?= nl2br(htmlspecialchars($return['notes'])) ?>
</div>
<?php endif; ?>

<div class="block">
  <span class="label">Позиции возврата:</span>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Товар</th>
        <th>Кол-во</th>
        <th>Цена</th>
        <th>Скидка</th>
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
        <td class="text-right"><?= number_format($sum, 2, '.', ' ') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="5" class="text-right"><strong>Итого:</strong></td>
        <td class="text-right"><strong><?= number_format($total_amount, 2, '.', ' ') ?></strong></td>
      </tr>
    </tfoot>
  </table>
</div>

<?php if (!empty($relatedDocs)): ?>
<hr>
<div class="block">
  <span class="label">Связанные документы:</span>
  <table>
    <thead>
      <tr>
        <th>Тип документа</th>
        <th>Номер</th>
        <th>Дата</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($relatedDocs as $doc): 
        $docTypeText = $doc['doc_type'] === 'order' ? 'Заказ' : ($doc['doc_type'] === 'finance' ? 'Финансовая операция' : $doc['doc_type']);
      ?>
      <tr>
        <td><?= htmlspecialchars($docTypeText) ?></td>
        <td><?= htmlspecialchars($doc['number']) ?></td>
        <td><?= $doc['date'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="block">
  <p>______________________ / _____________________</p>
  <small>(подпись)</small> <small>(расшифровка)</small>
</div>

</body>
</html>