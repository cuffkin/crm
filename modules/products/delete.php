<?php
// /crm/modules/products/delete.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'products')) {
    die("Нет доступа");
}

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    // Логическое удаление (деактивация)
    $del = $conn->prepare("UPDATE PCRM_Product SET status='inactive' WHERE id=?");
    $del->bind_param("i", $id);
    $del->execute();
}
echo "OK";