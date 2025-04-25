<?php
// /crm/modules/production/operations/delete.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

// Проверяем доступ
if (!check_access($conn, $_SESSION['user_id'], 'production')) {
    echo json_encode(['success' => false, 'error' => 'У вас нет доступа к этому разделу']);
    exit;
}

// Получаем ID операции
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Неверный ID операции']);
    exit;
}

// Начинаем транзакцию
$conn->begin_transaction();

try {
    // Проверяем, что операция существует и не проведена
    $check_stmt = $conn->prepare("SELECT conducted FROM PCRM_ProductionOperation WHERE id = ?");
    
    if (!$check_stmt) {
        throw new Exception("Ошибка подготовки запроса проверки: " . $conn->error);
    }
    
    $check_stmt->bind_param('i', $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        throw new Exception("Операция не найдена");
    }
    
    $operation = $check_result->fetch_assoc();
    
    if ($operation['conducted']) {
        throw new Exception("Невозможно удалить проведенную операцию. Сначала отмените проведение.");
    }
    
    // Проверяем, есть ли зависимые записи (например, в истории движения товаров)
    $stock_movement_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM PCRM_StockMovement 
        WHERE document_type = 'production' AND document_id = ?
    ");
    
    if (!$stock_movement_stmt) {
        throw new Exception("Ошибка подготовки запроса проверки движений: " . $conn->error);
    }
    
    $stock_movement_stmt->bind_param('i', $id);
    $stock_movement_stmt->execute();
    $stock_movement_result = $stock_movement_stmt->get_result();
    $stock_movement_row = $stock_movement_result->fetch_assoc();
    
    if ($stock_movement_row['count'] > 0) {
        throw new Exception("Невозможно удалить операцию, так как по ней есть движения товаров");
    }
    
    // Удаляем связанные элементы операции (ингредиенты)
    $delete_items_stmt = $conn->prepare("DELETE FROM PCRM_ProductionOperationItem WHERE operation_id = ?");
    
    if (!$delete_items_stmt) {
        throw new Exception("Ошибка подготовки запроса удаления элементов: " . $conn->error);
    }
    
    $delete_items_stmt->bind_param('i', $id);
    
    if (!$delete_items_stmt->execute()) {
        throw new Exception("Ошибка выполнения запроса удаления элементов: " . $delete_items_stmt->error);
    }
    
    // Удаляем саму операцию
    $delete_stmt = $conn->prepare("DELETE FROM PCRM_ProductionOperation WHERE id = ?");
    
    if (!$delete_stmt) {
        throw new Exception("Ошибка подготовки запроса удаления операции: " . $conn->error);
    }
    
    $delete_stmt->bind_param('i', $id);
    
    if (!$delete_stmt->execute()) {
        throw new Exception("Ошибка выполнения запроса удаления операции: " . $delete_stmt->error);
    }
    
    // Проверяем, была ли удалена запись
    if ($delete_stmt->affected_rows === 0) {
        throw new Exception("Операция не была удалена");
    }
    
    // Записываем действие в журнал
    $log_stmt = $conn->prepare("
        INSERT INTO PCRM_Log
        (user_id, action, entity_type, entity_id, details, created_at)
        VALUES (?, 'delete', 'production_operation', ?, ?, NOW())
    ");
    
    $log_details = json_encode(['id' => $id]);
    $log_stmt->bind_param('iis', $_SESSION['user_id'], $id, $log_details);
    $log_stmt->execute();
    
    // Завершаем транзакцию
    $conn->commit();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // Откатываем транзакцию в случае ошибки
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 