<?php
// /crm/modules/users/delete.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die("Нет прав");
}

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $sql  = "UPDATE PCRM_User SET status='inactive' WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
}
echo "OK";