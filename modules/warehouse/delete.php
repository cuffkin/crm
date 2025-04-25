<?php
// /crm/modules/warehouse/delete.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'warehouse')) {
    die("Нет доступа");
}

$id=(int)($_GET['id'] ?? 0);
if ($id>0) {
    // Либо DELETE, либо status='closed'
    $q=$conn->prepare("UPDATE PCRM_Warehouse SET status='closed' WHERE id=?");
    $q->bind_param("i", $id);
    $q->execute();
}
echo "OK";