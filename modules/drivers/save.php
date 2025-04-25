<?php
// /crm/modules/drivers/save.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'drivers')) {
    die("Нет доступа");
}

$id           = (int)($_POST['id'] ?? 0);
$name         = trim($_POST['name'] ?? '');
$vehicle_name = trim($_POST['vehicle_name'] ?? '');
$load_capacity= (float)($_POST['load_capacity'] ?? 0);
$max_volume   = (float)($_POST['max_volume'] ?? 0);
$phone        = trim($_POST['phone'] ?? '');

if ($name === '') {
    die("Имя водителя не может быть пустым");
}

if ($id>0) {
    // UPDATE
    $sql = "UPDATE PCRM_Drivers
            SET name=?, vehicle_name=?, load_capacity=?, max_volume=?, phone=?
            WHERE id=?";
    $st = $conn->prepare($sql);
    $st->bind_param("ssddsi", 
        $name, $vehicle_name, $load_capacity, $max_volume, $phone, $id
    );
    $st->execute();
    if ($st->error) {
        die("Ошибка при обновлении: " . $st->error);
    }
    echo "OK";
} else {
    // INSERT
    $sql = "INSERT INTO PCRM_Drivers
            (name, vehicle_name, load_capacity, max_volume, phone)
            VALUES (?,?,?,?,?)";
    $st = $conn->prepare($sql);
    $st->bind_param("ssdds",
        $name, $vehicle_name, $load_capacity, $max_volume, $phone
    );
    $st->execute();
    if ($st->error) {
        die("Ошибка при вставке: " . $st->error);
    }
    echo "OK";
}