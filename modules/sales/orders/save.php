<?php
// /crm/modules/sales/orders/save.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'sales_orders')) {
    die("Нет доступа");
}

// Шапка
$id       = (int)($_POST['id'] ?? 0);
$org      = $_POST['organization'] ? (int)$_POST['organization'] : null;
$num      = trim($_POST['order_number'] ?? '');
$odate    = $_POST['order_date'] ?? date('Y-m-d H:i:s');
$cust     = $_POST['customer'] ? (int)$_POST['customer'] : null;
$contacts = trim($_POST['contacts'] ?? '');
$wh       = $_POST['warehouse'] ? (int)$_POST['warehouse'] : null;
$driver   = $_POST['driver_id'] ? (int)$_POST['driver_id'] : null;
$deliv    = trim($_POST['delivery_addr'] ?? '');
$comment  = trim($_POST['comment'] ?? '');
$status   = trim($_POST['status'] ?? 'draft');
$total    = (float)($_POST['total_amount'] ?? 0);
$conducted= (int)($_POST['conducted'] ?? 0); // 0 или 2

// Позиции
$itemsJson = $_POST['items'] ?? '[]';
$itemsArr  = json_decode($itemsJson, true);
if (!is_array($itemsArr)) {
    $itemsArr = [];
}

// Валидация
if (empty($num)) {
    $r = $conn->query("SELECT id FROM PCRM_Order ORDER BY id DESC LIMIT 1");
    $last = $r->fetch_assoc();
    $newId = $last ? ($last['id'] + 1) : 1;
    $num = "SO-" . str_pad($newId, 6, '0', STR_PAD_LEFT);
}
if (empty($itemsArr)) {
    die("Добавьте хотя бы один товар");
}

// UPDATE или INSERT шапки
if ($id > 0) {
    $sql = "
      UPDATE PCRM_Order
      SET organization=?, order_number=?, order_date=?, 
          customer=?, contacts=?, warehouse=?, driver_id=?,
          delivery_address=?, comment=?, status=?, 
          total_amount=?, conducted=?
      WHERE id=? AND deleted=0
    ";
    $st = $conn->prepare($sql);
    $st->bind_param("issisiisssdii",
        $org, $num, $odate,
        $cust, $contacts, $wh, $driver,
        $deliv, $comment, $status,
        $total, $conducted,
        $id
    );
    $st->execute();
    if ($st->error) {
        die("Ошибка при UPDATE: " . $st->error);
    }
} else {
    $created_by = $_SESSION['user_id'] ?? null;
    $sql = "
      INSERT INTO PCRM_Order
      (organization, order_number, order_date, 
       customer, contacts, warehouse, driver_id,
       delivery_address, comment, status,
       total_amount, conducted,
       created_by, deleted)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?, ?, 0)
    ";
    $st = $conn->prepare($sql);
    $st->bind_param("issisiisssdii",
        $org, $num, $odate,
        $cust, $contacts, $wh, $driver,
        $deliv, $comment, $status,
        $total, $conducted,
        $created_by
    );
    $st->execute();
    if ($st->error) {
        die("Ошибка при INSERT: " . $st->error);
    }
    $id = $st->insert_id;
}

// Сохраняем позиции (OrderItem)
$del = $conn->prepare("DELETE FROM PCRM_OrderItem WHERE order_id=?");
$del->bind_param("i", $id);
$del->execute();

$ins = $conn->prepare("
  INSERT INTO PCRM_OrderItem (order_id, product_id, quantity, price, discount)
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