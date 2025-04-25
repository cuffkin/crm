<?php
// /crm/modules/sales/orders/delete.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'sales_orders')) {
    die("Нет доступа");
}

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $del = $conn->prepare("UPDATE PCRM_Order SET deleted=1 WHERE id=?");
    $del->bind_param("i", $id);
    $del->execute();
}
echo "OK";