<?php
// /crm/modules/production/operations/save.php
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

// Получаем данные из POST-запроса
$data = json_decode(file_get_contents('php://input'), true);

// Проверяем корректность данных
if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Некорректные данные']);
    exit;
}

// Начинаем транзакцию
$conn->begin_transaction();

try {
    // Подготавливаем данные для сохранения
    $id = isset($data['id']) ? intval($data['id']) : 0;
    $operation_number = isset($data['operation_number']) ? $conn->real_escape_string($data['operation_number']) : '';
    $production_date = isset($data['production_date']) ? $conn->real_escape_string($data['production_date']) : date('Y-m-d H:i:s');
    $warehouse_id = isset($data['warehouse_id']) ? intval($data['warehouse_id']) : 0;
    $product_id = isset($data['product_id']) ? intval($data['product_id']) : 0;
    $output_quantity = isset($data['output_quantity']) ? floatval($data['output_quantity']) : 0;
    $status = isset($data['status']) ? $conn->real_escape_string($data['status']) : 'draft';
    $comment = isset($data['comment']) ? $conn->real_escape_string($data['comment']) : '';
    
    // Валидация данных
    if (empty($operation_number)) {
        throw new Exception('Номер операции не указан');
    }
    
    if ($warehouse_id <= 0) {
        throw new Exception('Не выбран склад');
    }
    
    if ($product_id <= 0) {
        throw new Exception('Не выбран продукт');
    }
    
    if ($output_quantity <= 0) {
        throw new Exception('Указано некорректное количество');
    }
    
    // Если это новая операция, вставляем запись
    if ($id == 0) {
        $stmt = $conn->prepare("
            INSERT INTO PCRM_ProductionOperation 
            (operation_number, production_date, warehouse_id, product_id, output_quantity, status, comment, user_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        if (!$stmt) {
            throw new Exception("Ошибка подготовки запроса: " . $conn->error);
        }
        
        $stmt->bind_param('ssiidssi', $operation_number, $production_date, $warehouse_id, $product_id, $output_quantity, $status, $comment, $_SESSION['user_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Ошибка выполнения запроса: " . $stmt->error);
        }
        
        $id = $conn->insert_id;
    } else {
        // Проверяем, что операция существует и не проведена
        $check_stmt = $conn->prepare("SELECT conducted FROM PCRM_ProductionOperation WHERE id = ?");
        $check_stmt->bind_param('i', $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            throw new Exception("Операция не найдена");
        }
        
        $operation = $check_result->fetch_assoc();
        
        if ($operation['conducted']) {
            throw new Exception("Невозможно изменить проведенную операцию");
        }
        
        // Обновляем существующую операцию
        $stmt = $conn->prepare("
            UPDATE PCRM_ProductionOperation SET
                operation_number = ?,
                production_date = ?,
                warehouse_id = ?,
                product_id = ?,
                output_quantity = ?,
                status = ?,
                comment = ?,
                updated_at = NOW()
            WHERE id = ? AND conducted = 0
        ");
        
        if (!$stmt) {
            throw new Exception("Ошибка подготовки запроса обновления: " . $conn->error);
        }
        
        $stmt->bind_param('ssiidssi', $operation_number, $production_date, $warehouse_id, $product_id, $output_quantity, $status, $comment, $id);
        
        if (!$stmt->execute()) {
            throw new Exception("Ошибка выполнения запроса обновления: " . $stmt->error);
        }
        
        // Удаляем существующие элементы операции
        $delete_stmt = $conn->prepare("DELETE FROM PCRM_ProductionOperationItem WHERE operation_id = ?");
        
        if (!$delete_stmt) {
            throw new Exception("Ошибка подготовки запроса удаления элементов: " . $conn->error);
        }
        
        $delete_stmt->bind_param('i', $id);
        
        if (!$delete_stmt->execute()) {
            throw new Exception("Ошибка выполнения запроса удаления элементов: " . $delete_stmt->error);
        }
    }
    
    // Добавляем ингредиенты операции
    if (isset($data['ingredients']) && is_array($data['ingredients'])) {
        $item_stmt = $conn->prepare("
            INSERT INTO PCRM_ProductionOperationItem 
            (operation_id, ingredient_id, quantity)
            VALUES (?, ?, ?)
        ");
        
        if (!$item_stmt) {
            throw new Exception("Ошибка подготовки запроса добавления ингредиентов: " . $conn->error);
        }
        
        foreach ($data['ingredients'] as $ingredient) {
            $ingredient_id = intval($ingredient['ingredient_id']);
            $quantity = floatval($ingredient['quantity']);
            
            if ($ingredient_id <= 0 || $quantity <= 0) {
                continue; // Пропускаем некорректные данные
            }
            
            $item_stmt->bind_param('iid', $id, $ingredient_id, $quantity);
            
            if (!$item_stmt->execute()) {
                throw new Exception("Ошибка выполнения запроса добавления ингредиента: " . $item_stmt->error);
            }
        }
    }
    
    // Завершаем транзакцию
    $conn->commit();
    
    echo json_encode(['success' => true, 'id' => $id]);
    
} catch (Exception $e) {
    // Откатываем транзакцию в случае ошибки
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 