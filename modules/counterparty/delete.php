<?php
// /crm/modules/counterparty/delete.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'counterparty')) {
    die("Нет доступа");
}

$id = (int)($_GET['id'] ?? 0);
if ($id>0) {
    // Физическое удаление:
    $st = $conn->prepare("DELETE FROM PCRM_Counterparty WHERE id=?");
    $st->bind_param("i", $id);
    $st->execute();
}
echo "OK";