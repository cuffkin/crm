<?php
// /crm/modules/shipments/delete.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'shipments')) {
    die("Нет доступа");
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Некорректный ID отгрузки");
}

// Начинаем транзакцию
$conn->begin_transaction();

try {
    // Сначала удаляем позиции отгрузки
    $del1 = $conn->prepare("DELETE FROM PCRM_Shipments WHERE shipment_header_id=?");
    $del1->bind_param("i", $id);
    $del1->execute();
    if ($del1->error) {
        throw new Exception("Ошибка при удалении позиций: " . $del1->error);
    }
    
    // Затем удаляем заголовок отгрузки
    $del2 = $conn->prepare("DELETE FROM PCRM_ShipmentHeader WHERE id=?");
    $del2->bind_param("i", $id);
    $del2->execute();
    if ($del2->error) {
        throw new Exception("Ошибка при удалении заголовка: " . $del2->error);
    }
    
    // Завершаем транзакцию
    $conn->commit();
    echo "OK";
} catch (Exception $e) {
    // Откатываем транзакцию в случае ошибки
    $conn->rollback();
    die($e->getMessage());
}