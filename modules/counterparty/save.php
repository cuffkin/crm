<?php
// /crm/modules/counterparty/save.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'counterparty')) {
    die("Нет доступа");
}

// Данные
$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$type = trim($_POST['type'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$inn = trim($_POST['inn'] ?? '');
$kpp = trim($_POST['kpp'] ?? '');
$addr= trim($_POST['address'] ?? '');

if ($name === '') {
    die("Название не может быть пустым");
}

if ($id>0) {
    // UPDATE
    $sql = "UPDATE PCRM_Counterparty
            SET name=?, type=?, phone=?, email=?, inn=?, kpp=?, address=?
            WHERE id=?";
    $st = $conn->prepare($sql);
    $st->bind_param("sssssssi", $name, $type, $phone, $email, $inn, $kpp, $addr, $id);
    $st->execute();
    if ($st->error) {
        die("Ошибка при обновлении: " . $st->error);
    }
    echo "OK";
} else {
    // INSERT
    $sql = "INSERT INTO PCRM_Counterparty
            (name, type, phone, email, inn, kpp, address)
            VALUES (?,?,?,?,?,?,?)";
    $st = $conn->prepare($sql);
    $st->bind_param("sssssss", $name, $type, $phone, $email, $inn, $kpp, $addr);
    $st->execute();
    if ($st->error) {
        die("Ошибка при вставке: " . $st->error);
    }
    echo "OK";
}