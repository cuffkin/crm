<?php
// /crm/modules/trash/restore_all.php - Массовое восстановление элементов из корзины

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Только POST запросы']);
    exit;
}

// Получаем параметры
$type = $_POST['type'] ?? '';
$search = $_POST['search'] ?? '';

if (!in_array($type, ['documents', 'references'])) {
    echo json_encode(['success' => false, 'error' => 'Неверный тип элементов']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Определяем условие для типа элементов
    $typeCondition = ($type === 'documents') ? "item_type = 'document'" : "item_type = 'reference'";
    
    // Добавляем условие поиска если необходимо
    $searchCondition = '';
    $params = [];
    if (!empty($search)) {
        $searchCondition = " AND (original_data LIKE ? OR document_type LIKE ?)";
        $params = ["%$search%", "%$search%"];
    }
    
    // Получаем список элементов для восстановления
    $query = "SELECT id, item_type, item_id, document_type, original_data 
              FROM PCRM_TrashItems 
              WHERE $typeCondition $searchCondition
              ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param('ss', ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $restored_count = 0;
    $errors = [];
    
    while ($item = $result->fetch_assoc()) {
        try {
            if ($item['item_type'] === 'document') {
                // Восстановление документа
                $data = json_decode($item['original_data'], true);
                if (!$data) {
                    $errors[] = "Не удалось декодировать данные для элемента {$item['id']}";
                    continue;
                }
                
                // Определяем таблицу для восстановления
                $table_mapping = [
                    'customer_order' => 'PCRM_Order',
                    'shipment' => 'PCRM_ShipmentHeader',
                    'transaction' => 'PCRM_FinancialTransaction',
                    'purchase_order' => 'PCRM_PurchaseOrder',
                    'receipt' => 'PCRM_ReceiptHeader',
                    'supplier_return' => 'PCRM_SupplierReturnHeader',
                    'sales_return' => 'PCRM_ReturnHeader',
                    'production_order' => 'PCRM_ProductionOrder',
                    'production_operation' => 'PCRM_ProductionOperation'
                ];
                
                $table = $table_mapping[$item['document_type']] ?? null;
                if (!$table) {
                    $errors[] = "Неизвестный тип документа: {$item['document_type']}";
                    continue;
                }
                
                // Формируем запрос на восстановление
                $columns = array_keys($data);
                $placeholders = str_repeat('?,', count($columns) - 1) . '?';
                $insertQuery = "INSERT INTO $table (" . implode(',', $columns) . ") VALUES ($placeholders)";
                
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param(str_repeat('s', count($data)), ...array_values($data));
                
                if ($insertStmt->execute()) {
                    $restored_count++;
                    
                    // Удаляем из корзины
                    $deleteStmt = $conn->prepare("DELETE FROM PCRM_TrashItems WHERE id = ?");
                    $deleteStmt->bind_param('i', $item['id']);
                    $deleteStmt->execute();
                    
                    // Логируем восстановление
                    $logStmt = $conn->prepare("INSERT INTO PCRM_TrashLog (action, item_type, item_id, document_type, details, user_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $details = "Массовое восстановление через поиск: '$search'";
                    $user_id = $_SESSION['user_id'] ?? 0;
                    $logStmt->bind_param('ssissi', $action = 'restore', $item['item_type'], $item['item_id'], $item['document_type'], $details, $user_id);
                    $logStmt->execute();
                } else {
                    $errors[] = "Ошибка восстановления элемента {$item['id']}: " . $conn->error;
                }
                
            } else {
                // Восстановление справочника
                $data = json_decode($item['original_data'], true);
                if (!$data) {
                    $errors[] = "Не удалось декодировать данные для элемента {$item['id']}";
                    continue;
                }
                
                // Определяем таблицу для восстановления
                $table_mapping = [
                    'counterparty' => 'PCRM_Counterparty',
                    'product' => 'PCRM_Product',
                    'category' => 'PCRM_Categories',
                    'warehouse' => 'PCRM_Warehouse',
                    'driver' => 'PCRM_Drivers',
                    'loader' => 'PCRM_Loaders'
                ];
                
                $table = $table_mapping[$item['document_type']] ?? null;
                if (!$table) {
                    $errors[] = "Неизвестный тип справочника: {$item['document_type']}";
                    continue;
                }
                
                // Формируем запрос на восстановление
                $columns = array_keys($data);
                $placeholders = str_repeat('?,', count($columns) - 1) . '?';
                $insertQuery = "INSERT INTO $table (" . implode(',', $columns) . ") VALUES ($placeholders)";
                
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param(str_repeat('s', count($data)), ...array_values($data));
                
                if ($insertStmt->execute()) {
                    $restored_count++;
                    
                    // Удаляем из корзины
                    $deleteStmt = $conn->prepare("DELETE FROM PCRM_TrashItems WHERE id = ?");
                    $deleteStmt->bind_param('i', $item['id']);
                    $deleteStmt->execute();
                    
                    // Логируем восстановление
                    $logStmt = $conn->prepare("INSERT INTO PCRM_TrashLog (action, item_type, item_id, document_type, details, user_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $details = "Массовое восстановление через поиск: '$search'";
                    $user_id = $_SESSION['user_id'] ?? 0;
                    $logStmt->bind_param('ssissi', $action = 'restore', $item['item_type'], $item['item_id'], $item['document_type'], $details, $user_id);
                    $logStmt->execute();
                } else {
                    $errors[] = "Ошибка восстановления элемента {$item['id']}: " . $conn->error;
                }
            }
        } catch (Exception $e) {
            $errors[] = "Ошибка обработки элемента {$item['id']}: " . $e->getMessage();
        }
    }
    
    $conn->commit();
    
    if (!empty($errors)) {
        echo json_encode([
            'success' => true, 
            'restored_count' => $restored_count,
            'warnings' => $errors
        ]);
    } else {
        echo json_encode([
            'success' => true, 
            'restored_count' => $restored_count
        ]);
    }
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false, 
        'error' => 'Ошибка массового восстановления: ' . $e->getMessage()
    ]);
}
?> 