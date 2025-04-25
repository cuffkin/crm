<?php
// /crm/modules/production/orders/conduct.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'production')) {
    die("Нет доступа");
}

$id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($id <= 0) {
    die("Некорректный ID заказа");
}

if (!in_array($action, ['conduct', 'cancel'])) {
    die("Некорректное действие");
}

try {
    // Начинаем транзакцию
    $conn->begin_transaction();
    
    // Получаем данные заказа
    $orderStmt = $conn->prepare("
        SELECT po.*, pr.product_id, pr.output_quantity 
        FROM PCRM_ProductionOrder po
        JOIN PCRM_ProductionRecipe pr ON po.recipe_id = pr.id
        WHERE po.id = ?
    ");
    $orderStmt->bind_param("i", $id);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    $order = $orderResult->fetch_assoc();
    
    if (!$order) {
        throw new Exception("Заказ не найден");
    }
    
    // Проверяем текущее состояние проведения
    $currentlyConected = (bool)$order['conducted'];
    
    if ($action === 'conduct' && $currentlyConected) {
        throw new Exception("Заказ уже проведен");
    }
    
    if ($action === 'cancel' && !$currentlyConected) {
        throw new Exception("Заказ не проведен");
    }
    
    // Обработка проведения/отмены
    if ($action === 'conduct') {
        // Устанавливаем флаг проведения и обновляем статус
        $conductStmt = $conn->prepare("
            UPDATE PCRM_ProductionOrder 
            SET conducted = 1, 
                status = CASE WHEN status = 'new' THEN 'in_progress' ELSE status END 
            WHERE id = ?
        ");
        $conductStmt->bind_param("i", $id);
        $conductStmt->execute();
        
        if ($conductStmt->error) {
            throw new Exception("Ошибка при проведении заказа: " . $conductStmt->error);
        }
    } else { // action == 'cancel'
        // Отменяем проведение
        $cancelStmt = $conn->prepare("
            UPDATE PCRM_ProductionOrder 
            SET conducted = 0
            WHERE id = ?
        ");
        $cancelStmt->bind_param("i", $id);
        $cancelStmt->execute();
        
        if ($cancelStmt->error) {
            throw new Exception("Ошибка при отмене проведения заказа: " . $cancelStmt->error);
        }
    }
    
    // Завершаем транзакцию
    $conn->commit();
    echo "OK";
    
} catch (Exception $e) {
    // В случае ошибки откатываем транзакцию
    $conn->rollback();
    die($e->getMessage());
} 