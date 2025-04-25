<?php
// /crm/modules/purchases/receipts/print.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);

$sql = "
SELECT rh.*, 
       po.purchase_order_number,
       c.name AS supplier_name, c.phone AS supplier_phone, c.address AS supplier_address,
       w.name AS warehouse_name,
       l.name AS loader_name
FROM PCRM_ReceiptHeader rh
LEFT JOIN PCRM_PurchaseOrder po ON rh.purchase_order_id = po.id
LEFT JOIN PCRM_Counterparty c ON po.supplier_id = c.id
LEFT JOIN PCRM_Warehouse w ON rh.warehouse_id = w.id
LEFT JOIN PCRM_Loaders l ON rh.loader_id = l.id
WHERE rh.id=?
";
$st = $conn->prepare($sql);
$st->bind_param("i", $id);
$st->execute();
$res = $st->get_result();
if (!$res || $res->num_rows === 0) {
    die("Приёмка не найдена");
}
$receipt = $res->fetch_assoc();

// Получаем позиции приёмки
$sqlItems = "
SELECT ri.*, p.name AS product_name, p.sku
FROM PCRM_ReceiptItem ri
LEFT JOIN PCRM_Product p ON ri.product_id = p.id
WHERE ri.receipt_header_id = ?
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
    $total_amount += ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0);
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
$statusRus = translateStatus($receipt['status']);

// Получаем связанные документы
$sqlRelated = "
SELECT rd.*, 
       CASE 
           WHEN rd.source_type = 'purchase_order' THEN po.purchase_order_number
           WHEN rd.related_type = 'supplier_return' THEN srh.return_number
           WHEN rd.related_type = 'finance' THEN ft.transaction_number
       END AS related_document_number,
       CASE 
           WHEN rd.source_type = 'purchase_order' THEN 'Заказ поставщику'
           WHEN rd.related_type = 'supplier_return' THEN 'Возврат поставщику'
           WHEN rd.related_type = 'finance' THEN 'Финансовая операция'
           ELSE rd.related_type
       END AS related_document_type
FROM PCRM_RelatedDocuments rd
LEFT JOIN PCRM_PurchaseOrder po ON rd.source_type = 'purchase_order' AND rd.source_id = po.id
LEFT JOIN PCRM_SupplierReturnHeader srh ON rd.related_type = 'supplier_return' AND rd.related_id = srh.id
LEFT JOIN PCRM_FinancialTransaction ft ON rd.related_type = 'finance' AND rd.related_id = ft.id
WHERE (rd.source_type = 'receipt' AND rd.source_id = ?) OR (rd.related_type = 'receipt' AND rd.related_id = ?)
ORDER BY rd.id
";
$stRel = $conn->prepare($sqlRelated);
$stRel->bind_param("ii", $id, $id);
$stRel->execute();
$relRes = $stRel->get_result();
$relatedDocs = $relRes->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Печать приёмки №<?= htmlspecialchars($receipt['receipt_number']) ?></title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .header { text-align: center; margin-bottom: 20px; }
    .title { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
    .document-info { margin-bottom: 20px; }
    .section { margin-bottom: 15px; }
    .section-title { font-weight: bold; margin-bottom: 5px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .totals { text-align: right; margin-top: 10px; }
    .signatures { margin-top: 40px; }
    .signature-line { margin-top: 30px; border-top: 1px solid #000; display: inline-block; width: 200px; }
    .footer { margin-top: 30px; font-size: 12px; color: #666; }
  </style>
</head>
<body onload="window.print()">

<div class="header">
  <div class="title">ПРИЁМКА ТОВАРОВ №<?= htmlspecialchars($receipt['receipt_number']) ?></div>
  <div>от <?= date('d.m.Y', strtotime($receipt['receipt_date'])) ?></div>
</div>

<div class="document-info">
  <div><strong>Номер приёмки:</strong> <?= htmlspecialchars($receipt['receipt_number']) ?></div>
  <div><strong>Дата:</strong> <?= date('d.m.Y H:i', strtotime($receipt['receipt_date'])) ?></div>
  <div><strong>Статус:</strong> <?= htmlspecialchars($statusRus) ?></div>
  <div><strong>Проведена:</strong> <?= $receipt['conducted'] ? 'Да' : 'Нет' ?></div>
  <div><strong>Заказ поставщику:</strong> <?= $receipt['purchase_order_id'] ? "#{$receipt['purchase_order_id']} ({$receipt['purchase_order_number']})" : '-' ?></div>
</div>

<div class="section">
  <div class="section-title">Поставщик:</div>
  <?php if (!empty($receipt['supplier_name'])): ?>
  <div><?= htmlspecialchars($receipt['supplier_name']) ?></div>
  <?php if (!empty($receipt['supplier_phone'])): ?>
  <div>Телефон: <?= htmlspecialchars($receipt['supplier_phone']) ?></div>
  <?php endif; ?>
  <?php if (!empty($receipt['supplier_address'])): ?>
  <div>Адрес: <?= htmlspecialchars($receipt['supplier_address']) ?></div>
  <?php endif; ?>
  <?php else: ?>
  <div>Информация о поставщике отсутствует</div>
  <?php endif; ?>
</div>

<div class="section">
  <div class="section-title">Склад:</div>
  <div><?= htmlspecialchars($receipt['warehouse_name'] ?? '') ?></div>
</div>

<?php if (!empty($receipt['loader_name'])): ?>
<div class="section">
  <div class="section-title">Грузчик:</div>
  <div><?= htmlspecialchars($receipt['loader_name']) ?></div>
</div>
<?php endif; ?>

<?php if (!empty($receipt['comment'])): ?>
<div class="section">
  <div class="section-title">Комментарий:</div>
  <div><?= nl2br(htmlspecialchars($receipt['comment'])) ?></div>
</div>
<?php endif; ?>

<div class="section">
  <div class="section-title">Товары:</div>
  <table>
    <thead>
      <tr>
        <th>№</th>
        <th>Артикул</th>
        <th>Наименование</th>
        <th>Количество</th>
        <th>Цена</th>
        <th>Скидка</th>
        <th>Сумма</th>
      </tr>
    </thead>
    <tbody>
      <?php 
      $total = 0;
      foreach ($items as $index => $item): 
        $sum = ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0);
        $total += $sum;
      ?>
      <tr>
        <td><?= $index + 1 ?></td>
        <td><?= htmlspecialchars($item['sku'] ?? '') ?></td>
        <td><?= htmlspecialchars($item['product_name'] ?? '') ?></td>
        <td><?= $item['quantity'] ?></td>
        <td><?= number_format($item['price'], 2, '.', ' ') ?></td>
        <td><?= number_format($item['discount'] ?? 0, 2, '.', ' ') ?></td>
        <td><?= number_format($sum, 2, '.', ' ') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  
  <div class="totals">
    <div><strong>Итого:</strong> <?= number_format($total, 2, '.', ' ') ?> руб.</div>
  </div>
</div>

<?php if (!empty($relatedDocs)): ?>
<div class="section">
  <div class="section-title">Связанные документы:</div>
  <table>
    <thead>
      <tr>
        <th>Тип документа</th>
        <th>Номер</th>
        <th>Тип связи</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($relatedDocs as $doc): ?>
      <tr>
        <td><?= htmlspecialchars($doc['related_document_type']) ?></td>
        <td><?= htmlspecialchars($doc['related_document_number'] ?? '') ?></td>
        <td><?= htmlspecialchars($doc['relation_type']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="signatures">
  <div>
    <span>Руководитель __________________</span>
    <span style="margin-left: 50px;">Кладовщик __________________</span>
    <?php if (!empty($receipt['loader_name'])): ?>
    <span style="margin-left: 50px;">Грузчик __________________</span>
    <?php endif; ?>
  </div>
</div>

<div class="footer">
  <p>Документ сформирован <?= date('d.m.Y H:i:s') ?></p>
</div>

</body>
</html>