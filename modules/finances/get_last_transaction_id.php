<?php
// /crm/modules/finances/get_last_transaction_id.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'finances')) {
    die("0");
}

$res = $conn->query("SELECT id FROM PCRM_FinancialTransaction ORDER BY id DESC LIMIT 1");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    echo $row['id'];
} else {
    echo "0";
}