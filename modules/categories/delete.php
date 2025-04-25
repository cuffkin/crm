<?php
// /crm/modules/categories/delete.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'categories')) {
    die("Нет доступа");
}

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    // Логическое удаление
    $del = $conn->prepare("UPDATE PCRM_Categories SET status='inactive' WHERE id=?");
    // Физическое удаление (раскомментируйте, если нужно):
    // $del = $conn->prepare("DELETE FROM PCRM_Categories WHERE id=?");

    $del->bind_param("i", $id);
    $del->execute();
}
echo "OK";