<?php
// /crm/modules/loaders/save.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'loaders')) {
    die("Нет доступа");
}

$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$status= trim($_POST['status'] ?? 'active');

if ($name === '') {
    die("Имя не может быть пустым");
}

if ($id>0) {
    // UPDATE
    $sql = "UPDATE PCRM_Loaders SET name=?, phone=?, status=? WHERE id=?";
    $st = $conn->prepare($sql);
    $st->bind_param("sssi", $name, $phone, $status, $id);
    $st->execute();
    if ($st->error) {
        die("Ошибка при обновлении: " . $st->error);
    }
    echo "OK";
} else {
    // INSERT
    $sql = "INSERT INTO PCRM_Loaders (name, phone, status)
            VALUES (?,?,?)";
    $st = $conn->prepare($sql);
    $st->bind_param("sss", $name, $phone, $status);
    $st->execute();
    if ($st->error) {
        die("Ошибка при вставке: " . $st->error);
    }
    echo "OK";
}