<?php
// /crm/modules/production/orders/delete.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'production')) {
    die("Нет доступа");
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Некорректный ID заказа");
}

try {
    // Проверяем, не проведен ли заказ
    $checkStmt = $conn->prepare("SELECT conducted FROM PCRM_ProductionOrder WHERE id = ?");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row && $row['conducted'] == 1) {
        die("Заказ проведен и не может быть удален. Сначала отмените проведение.");
    }
    
    // Проверяем, используется ли заказ в операциях
    $operationStmt = $conn->prepare("SELECT COUNT(*) as count FROM PCRM_ProductionOperation WHERE order_id = ?");
    $operationStmt->bind_param("i", $id);
    $operationStmt->execute();
    $opResult = $operationStmt->get_result();
    $opRow = $opResult->fetch_assoc();
    
    if ($opRow && $opRow['count'] > 0) {
        die("Заказ используется в операциях производства и не может быть удален");
    }
    
    // Удаляем заказ
    $stmt = $conn->prepare("DELETE FROM PCRM_ProductionOrder WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    if ($stmt->error) {
        throw new Exception("Ошибка при удалении заказа: " . $stmt->error);
    }
    
    echo "OK";
} catch (Exception $e) {
    die($e->getMessage());
} 