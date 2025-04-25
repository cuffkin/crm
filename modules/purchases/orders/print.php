<?php
// /crm/modules/purchases/orders/print.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);

$sql = "
SELECT po.*,
       org.name AS org_name,
       c.name  AS supplier_name,
       c.phone AS supplier_phone,
       c.address AS supplier_address,
       c.inn AS supplier_inn,
       w.name  AS warehouse_name
FROM PCRM_PurchaseOrder po
LEFT JOIN PCRM_Organization org ON po.organization = org.id
LEFT JOIN PCRM_Counterparty c ON po.supplier_id = c.id
LEFT JOIN PCRM_Warehouse w ON po.warehouse_id = w.id
WHERE po.id=? AND po.deleted=0
";
$st = $conn->prepare($sql);
$st->bind_param("i", $id);
$st->execute();
$res = $st->get_result();
if (!$res || !$res->num_rows) {
    die("Заказ не найден или удалён");
}
$order = $res->fetch_assoc();

// Получаем элементы заказа
$sqlItems = "
SELECT poi.*, p.name AS product_name, p.sku
FROM PCRM_PurchaseOrderItem poi
LEFT JOIN PCRM_Product p ON poi.product_id = p.id
WHERE poi.purchase_order_id = ?
ORDER BY poi.id ASC
";
$st2 = $conn->prepare($sqlItems);
$st2->bind_param("i", $id);
$st2->execute();
$res2 = $st2->get_result();
$items = $res2->fetch_all(MYSQLI_ASSOC);

// Функция перевода статуса на русский
function translateStatus($dbVal) {
    switch ($dbVal) {
        case 'draft':     return 'Черновик';
        case 'new':       return 'Новый';
        case 'confirmed': return 'Подтверждён';
        case 'processing': return 'В обработке';
        case 'completed': return 'Завершён';
        case 'cancelled': return 'Отменён';
        default:          return $dbVal;
    }
}

// Получаем русскоязычный статус
$statusRus = translateStatus($order['status']);

// Получаем связанные документы
$sqlRelated = "
SELECT rd.*, 
       CASE 
           WHEN rd.related_type = 'receipt' THEN rh.receipt_number
           WHEN rd.related_type = 'supplier_return' THEN srh.return_number
           WHEN rd.related_type = 'finance' THEN ft.transaction_number
       END AS related_document_number,
       CASE 
           WHEN rd.related_type = 'receipt' THEN 'Приёмка'
           WHEN rd.related_type = 'supplier_return' THEN 'Возврат поставщику'
           WHEN rd.related_type = 'finance' THEN 'Финансовая операция'
           ELSE rd.related_type
       END AS related_document_type
FROM PCRM_RelatedDocuments rd
LEFT JOIN PCRM_ReceiptHeader rh ON rd.related_type = 'receipt' AND rd.related_id = rh.id
LEFT JOIN PCRM_SupplierReturnHeader srh ON rd.related_type = 'supplier_return' AND rd.related_id = srh.id
LEFT JOIN PCRM_FinancialTransaction ft ON rd.related_type = 'finance' AND rd.related_id = ft.id
WHERE rd.source_type = 'purchase_order' AND rd.source_id = ?
ORDER BY rd.id
";
$stRel = $conn->prepare($sqlRelated);
$stRel->bind_param("i", $id);
$stRel->execute();
$relRes = $stRel->get_result();
$relatedDocs = $relRes->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Печать заказа поставщику #<?= $order['id'] ?></title>
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
  <div class="title">ЗАКАЗ ПОСТАВЩИКУ №<?= htmlspecialchars($order['purchase_order_number']) ?></div>
  <div>от <?= date('d.m.Y', strtotime($order['date'])) ?></div>
</div>

<div class="document-info">
  <div><strong>Организация:</strong> <?= htmlspecialchars($order['org_name'] ?? '') ?></div>
  <div><strong>Номер заказа:</strong> <?= htmlspecialchars($order['purchase_order_number']) ?></div>
  <div><strong>Дата:</strong> <?= date('d.m.Y H:i', strtotime($order['date'])) ?></div>
  <div><strong>Статус:</strong> <?= htmlspecialchars($statusRus) ?></div>
  <div><strong>Проведен:</strong> <?= $order['conducted'] ? 'Да' : 'Нет' ?></div>
</div>

<div class="section">
  <div class="section-title">Поставщик:</div>
  <div><?= htmlspecialchars($order['supplier_name'] ?? '') ?></div>
  <?php if (!empty($order['supplier_inn'])): ?>
  <div>ИНН: <?= htmlspecialchars($order['supplier_inn']) ?></div>
  <?php endif; ?>
  <?php if (!empty($order['supplier_phone'])): ?>
  <div>Телефон: <?= htmlspecialchars($order['supplier_phone']) ?></div>
  <?php endif; ?>
  <?php if (!empty($order['supplier_address'])): ?>
  <div>Адрес: <?= htmlspecialchars($order['supplier_address']) ?></div>
  <?php endif; ?>
</div>

<div class="section">
  <div class="section-title">Склад:</div>
  <div><?= htmlspecialchars($order['warehouse_name'] ?? '') ?></div>
</div>

<?php if (!empty($order['delivery_address'])): ?>
<div class="section">
  <div class="section-title">Адрес доставки:</div>
  <div><?= htmlspecialchars($order['delivery_address']) ?></div>
</div>
<?php endif; ?>

<?php if (!empty($order['comment'])): ?>
<div class="section">
  <div class="section-title">Комментарий:</div>
  <div><?= nl2br(htmlspecialchars($order['comment'])) ?></div>
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
        $sum = ($item['quantity'] * $item['price']) - $item['discount'];
        $total += $sum;
      ?>
      <tr>
        <td><?= $index + 1 ?></td>
        <td><?= htmlspecialchars($item['sku'] ?? '') ?></td>
        <td><?= htmlspecialchars($item['product_name'] ?? '') ?></td>
        <td><?= $item['quantity'] ?></td>
        <td><?= number_format($item['price'], 2, '.', ' ') ?></td>
        <td><?= number_format($item['discount'], 2, '.', ' ') ?></td>
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
    <span style="margin-left: 50px;">Менеджер __________________</span>
  </div>
</div>

<div class="footer">
  <p>Документ сформирован <?= date('d.m.Y H:i:s') ?></p>
</div>

</body>
</html>