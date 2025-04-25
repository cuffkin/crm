<?php
// /crm/modules/warehouse/save.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'warehouse')) {
    die("Нет доступа");
}

$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$loc  = trim($_POST['location'] ?? '');
$st   = trim($_POST['status'] ?? 'active');

if ($name==='') {
    die("Наименование не может быть пустым");
}

if ($id>0) {
    $sql="UPDATE PCRM_Warehouse SET name=?, location=?, status=? WHERE id=?";
    $stt=$conn->prepare($sql);
    $stt->bind_param("sssi", $name, $loc, $st, $id);
    $stt->execute();
    if ($stt->error) {
        die("Ошибка: ".$stt->error);
    }
    echo "OK";
} else {
    $sql="INSERT INTO PCRM_Warehouse (name, location, status) VALUES (?,?,?)";
    $stt=$conn->prepare($sql);
    $stt->bind_param("sss", $name, $loc, $st);
    $stt->execute();
    if ($stt->error) {
        die("Ошибка: ".$stt->error);
    }
    echo "OK";
}