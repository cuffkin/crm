<?php
// /crm/modules/purchases/orders/save.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'purchases_orders')) {
    die("Нет доступа");
}

// Шапка
$id          = (int)($_POST['id'] ?? 0);
$org         = $_POST['organization'] ? (int)$_POST['organization'] : null;
$num         = trim($_POST['purchase_order_number'] ?? '');
$date        = $_POST['date'] ?? date('Y-m-d H:i:s');
$supplier_id = $_POST['supplier_id'] ? (int)$_POST['supplier_id'] : null;
$warehouse_id = $_POST['warehouse_id'] ? (int)$_POST['warehouse_id'] : null;
$delivery_address = trim($_POST['delivery_address'] ?? '');
$comment     = trim($_POST['comment'] ?? '');
$status      = trim($_POST['status'] ?? 'draft');
$total_amount = (float)($_POST['total_amount'] ?? 0);
$conducted   = (int)($_POST['conducted'] ?? 0);

// Позиции
$itemsJson = $_POST['items'] ?? '[]';
$itemsArr  = json_decode($itemsJson, true);
if (!is_array($itemsArr)) {
    $itemsArr = [];
}

// Валидация
if (empty($num)) {
    $r = $conn->query("SELECT id FROM PCRM_PurchaseOrder ORDER BY id DESC LIMIT 1");
    $last = $r->fetch_assoc();
    $newId = $last ? ($last['id'] + 1) : 1;
    $num = "PO-" . str_pad($newId, 6, '0', STR_PAD_LEFT);
}
if (empty($itemsArr)) {
    die("Добавьте хотя бы один товар");
}

// UPDATE или INSERT шапки
if ($id > 0) {
    $sql = "
      UPDATE PCRM_PurchaseOrder
      SET organization=?, purchase_order_number=?, date=?, 
          supplier_id=?, warehouse_id=?, delivery_address=?, 
          comment=?, status=?, total_amount=?, conducted=?
      WHERE id=? AND deleted=0
    ";
    $st = $conn->prepare($sql);
    $st->bind_param("issiisssdii",
        $org, $num, $date,
        $supplier_id, $warehouse_id, $delivery_address,
        $comment, $status, $total_amount, $conducted,
        $id
    );
    $st->execute();
    if ($st->error) {
        die("Ошибка при UPDATE: " . $st->error);
    }
} else {
    $created_by = $_SESSION['user_id'] ?? null;
    $sql = "
      INSERT INTO PCRM_PurchaseOrder
      (organization, purchase_order_number, date, 
       supplier_id, warehouse_id, delivery_address,
       comment, status, total_amount, conducted,
       created_by, deleted)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,0)
    ";
    $st = $conn->prepare($sql);
    $st->bind_param("issiisssdii",
        $org, $num, $date,
        $supplier_id, $warehouse_id, $delivery_address,
        $comment, $status, $total_amount, $conducted,
        $created_by
    );
    $st->execute();
    if ($st->error) {
        die("Ошибка при INSERT: " . $st->error);
    }
    $id = $st->insert_id;
}

// Сохраняем позиции (PurchaseOrderItem)
$del = $conn->prepare("DELETE FROM PCRM_PurchaseOrderItem WHERE purchase_order_id=?");
$del->bind_param("i", $id);
$del->execute();

$ins = $conn->prepare("
  INSERT INTO PCRM_PurchaseOrderItem (purchase_order_id, product_id, quantity, price, discount)
  VALUES (?,?,?,?,?)
");
foreach ($itemsArr as $itm) {
    $pid = (int)$itm['product_id'];
    $qty = (float)$itm['quantity'];
    $prc = (float)$itm['price'];
    $dsc = (float)$itm['discount'];
    if ($pid <= 0 || $qty <= 0) {
        continue;
    }
    $ins->bind_param("iiddd", $id, $pid, $qty, $prc, $dsc);
    $ins->execute();
    if ($ins->error) {
        die("Ошибка INSERT позиции: " . $ins->error);
    }
}

echo "OK";