<?php
// /crm/modules/production/operations/utils.php
// Вспомогательные функции для модуля "Операции производства"

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

/**
 * Генерирует номер для новой операции производства
 * 
 * @param mysqli $conn        Соединение с БД
 * @param string $prefix      Префикс для номера (по умолчанию 'OP')
 * @return string             Сгенерированный номер операции
 */
function generateOperationNumber($conn, $prefix = 'OP') {
    // Формат: OP-YYYYMMDD-XXXX, где XXXX — порядковый номер в день
    $date_part = date('Ymd');
    $number_template = "{$prefix}-{$date_part}-%04d";
    
    // Получаем максимальный номер за сегодня
    $sql = "SELECT operation_number FROM PCRM_ProductionOperation 
            WHERE operation_number LIKE ? 
            ORDER BY id DESC LIMIT 1";
    
    $like_pattern = "{$prefix}-{$date_part}-%";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $like_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_number = $row['operation_number'];
        // Извлекаем последний числовой компонент
        $matches = [];
        if (preg_match('/-(\d+)$/', $last_number, $matches)) {
            $last_seq = intval($matches[1]);
            $next_seq = $last_seq + 1;
        } else {
            $next_seq = 1;
        }
    } else {
        $next_seq = 1;
    }
    
    return sprintf($number_template, $next_seq);
}

/**
 * Проверяет наличие ингредиентов на складе для указанного рецепта и количества
 * 
 * @param mysqli $conn            Соединение с БД
 * @param int $recipe_id          ID рецепта
 * @param float $quantity         Количество продукции
 * @param int $warehouse_id       ID склада
 * @return array                  Массив с результатом проверки
 */
function checkRecipeIngredients($conn, $recipe_id, $quantity, $warehouse_id) {
    $result = [
        'success' => true,
        'all_available' => true,
        'ingredients' => [],
        'recipe_info' => null,
        'missing_items' => []
    ];
    
    try {
        // Получаем информацию о рецепте и продукте
        $recipe_stmt = $conn->prepare("
            SELECT r.*, p.name as product_name, p.unit_of_measure 
            FROM PCRM_ProductionRecipe r
            JOIN PCRM_Product p ON r.product_id = p.id
            WHERE r.id = ?
        ");
        
        if (!$recipe_stmt) {
            throw new Exception("Ошибка подготовки запроса рецепта: " . $conn->error);
        }
        
        $recipe_stmt->bind_param('i', $recipe_id);
        $recipe_stmt->execute();
        $recipe_result = $recipe_stmt->get_result();
        
        if ($recipe_result->num_rows === 0) {
            throw new Exception("Рецепт не найден");
        }
        
        $recipe = $recipe_result->fetch_assoc();
        $result['recipe_info'] = [
            'id' => $recipe['id'],
            'name' => $recipe['name'],
            'product_id' => $recipe['product_id'],
            'product_name' => $recipe['product_name'],
            'unit_of_measure' => $recipe['unit_of_measure']
        ];
        
        // Получаем ингредиенты рецепта
        $stmt = $conn->prepare("
            SELECT i.*, p.name, p.unit_of_measure 
            FROM PCRM_ProductionRecipeItem i
            JOIN PCRM_Product p ON i.ingredient_id = p.id
            WHERE i.recipe_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Ошибка получения ингредиентов: " . $conn->error);
        }
        
        $stmt->bind_param('i', $recipe_id);
        $stmt->execute();
        $ingredients_result = $stmt->get_result();
        
        // Проверяем наличие каждого ингредиента на складе
        while ($row = $ingredients_result->fetch_assoc()) {
            $required_quantity = $row['quantity'] * $quantity;
            
            // Получаем количество на складе
            $stock_stmt = $conn->prepare("
                SELECT COALESCE(SUM(quantity), 0) as stock_quantity 
                FROM PCRM_Stock 
                WHERE product_id = ? AND warehouse_id = ?
            ");
            
            if (!$stock_stmt) {
                throw new Exception("Ошибка проверки остатков: " . $conn->error);
            }
            
            $stock_stmt->bind_param('ii', $row['ingredient_id'], $warehouse_id);
            $stock_stmt->execute();
            $stock_result = $stock_stmt->get_result();
            $stock_row = $stock_result->fetch_assoc();
            
            $stock_quantity = $stock_row ? floatval($stock_row['stock_quantity']) : 0;
            $available = $stock_quantity >= $required_quantity;
            
            if (!$available) {
                $result['all_available'] = false;
                $result['missing_items'][] = [
                    'name' => $row['name'],
                    'required' => $required_quantity,
                    'available' => $stock_quantity,
                    'unit' => $row['unit_of_measure']
                ];
            }
            
            $result['ingredients'][] = [
                'id' => $row['id'],
                'ingredient_id' => $row['ingredient_id'],
                'name' => $row['name'],
                'required_quantity' => $required_quantity,
                'stock_quantity' => $stock_quantity,
                'available' => $available,
                'unit' => $row['unit_of_measure']
            ];
        }
        
        return $result;
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Создает связанные записи в таблице операций производства
 * 
 * @param mysqli $conn            Соединение с БД
 * @param array $data             Данные операции
 * @return array                  Результат операции
 */
function createProductionOperation($conn, $data) {
    try {
        // Проверяем наличие обязательных полей
        $required_fields = ['recipe_id', 'warehouse_id', 'quantity'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Отсутствует обязательное поле: {$field}");
            }
        }
        
        // Получаем данные рецепта для проверки
        $recipe_stmt = $conn->prepare("
            SELECT r.*, p.id as product_id, p.name as product_name
            FROM PCRM_ProductionRecipe r
            JOIN PCRM_Product p ON r.product_id = p.id
            WHERE r.id = ?
        ");
        
        if (!$recipe_stmt) {
            throw new Exception("Ошибка запроса рецепта: " . $conn->error);
        }
        
        $recipe_stmt->bind_param('i', $data['recipe_id']);
        $recipe_stmt->execute();
        $recipe_result = $recipe_stmt->get_result();
        
        if ($recipe_result->num_rows === 0) {
            throw new Exception("Рецепт не найден");
        }
        
        $recipe = $recipe_result->fetch_assoc();
        
        // Начинаем транзакцию
        $conn->begin_transaction();
        
        // Генерируем номер операции, если не задан
        $operation_number = isset($data['operation_number']) ? 
            $data['operation_number'] : 
            generateOperationNumber($conn);
        
        // Подготовка данных для операции
        $user_id = isset($data['user_id']) ? $data['user_id'] : $_SESSION['user_id'];
        $status = isset($data['status']) ? $data['status'] : 'draft';
        $comment = isset($data['comment']) ? $data['comment'] : '';
        $production_date = isset($data['production_date']) ? $data['production_date'] : date('Y-m-d');
        
        // Создаем запись операции
        $stmt = $conn->prepare("
            INSERT INTO PCRM_ProductionOperation
            (operation_number, recipe_id, product_id, output_quantity, warehouse_id, 
             production_date, status, user_id, comment, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        if (!$stmt) {
            throw new Exception("Ошибка подготовки запроса: " . $conn->error);
        }
        
        $stmt->bind_param('siidissis', 
            $operation_number,
            $data['recipe_id'],
            $recipe['product_id'],
            $data['quantity'],
            $data['warehouse_id'],
            $production_date,
            $status,
            $user_id,
            $comment
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Ошибка создания операции: " . $stmt->error);
        }
        
        $operation_id = $conn->insert_id;
        
        // Получаем ингредиенты рецепта
        $ingredients_stmt = $conn->prepare("
            SELECT ingredient_id, quantity
            FROM PCRM_ProductionRecipeItem
            WHERE recipe_id = ?
        ");
        
        if (!$ingredients_stmt) {
            throw new Exception("Ошибка запроса ингредиентов: " . $conn->error);
        }
        
        $ingredients_stmt->bind_param('i', $data['recipe_id']);
        $ingredients_stmt->execute();
        $ingredients_result = $ingredients_stmt->get_result();
        
        // Добавляем ингредиенты операции
        while ($ingredient = $ingredients_result->fetch_assoc()) {
            $item_quantity = $ingredient['quantity'] * $data['quantity'];
            
            $item_stmt = $conn->prepare("
                INSERT INTO PCRM_ProductionOperationItem
                (operation_id, ingredient_id, quantity)
                VALUES (?, ?, ?)
            ");
            
            if (!$item_stmt) {
                throw new Exception("Ошибка подготовки запроса ингредиента: " . $conn->error);
            }
            
            $item_stmt->bind_param('iid', 
                $operation_id, 
                $ingredient['ingredient_id'], 
                $item_quantity
            );
            
            if (!$item_stmt->execute()) {
                throw new Exception("Ошибка добавления ингредиента: " . $item_stmt->error);
            }
        }
        
        // Если есть привязка к заказу, добавляем связь
        if (isset($data['order_id']) && $data['order_id'] > 0) {
            $update_stmt = $conn->prepare("
                UPDATE PCRM_ProductionOperation
                SET order_id = ?
                WHERE id = ?
            ");
            
            if (!$update_stmt) {
                throw new Exception("Ошибка подготовки запроса связи: " . $conn->error);
            }
            
            $update_stmt->bind_param('ii', $data['order_id'], $operation_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception("Ошибка добавления связи с заказом: " . $update_stmt->error);
            }
            
            // Обновляем статус заказа
            $order_stmt = $conn->prepare("
                UPDATE PCRM_ProductionOrder
                SET status = 'in_progress'
                WHERE id = ?
            ");
            
            if ($order_stmt) {
                $order_stmt->bind_param('i', $data['order_id']);
                $order_stmt->execute();
                
                // Добавляем связь в таблицу связанных документов
                if (function_exists('registerRelatedDocument')) {
                    registerRelatedDocument(
                        $conn, 
                        'production_order', 
                        $data['order_id'], 
                        'production_operation', 
                        $operation_id
                    );
                }
            }
        }
        
        // Завершаем транзакцию
        $conn->commit();
        
        return [
            'success' => true,
            'operation_id' => $operation_id,
            'operation_number' => $operation_number
        ];
        
    } catch (Exception $e) {
        // Откатываем транзакцию в случае ошибки
        if (isset($conn) && $conn->connect_error === false) {
            $conn->rollback();
        }
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Добавляет запись в лог-журнал действий
 * 
 * @param mysqli $conn         Соединение с БД
 * @param string $action       Действие (create, update, delete, и т.д.)
 * @param string $entity_type  Тип сущности (production_operation, и т.д.)
 * @param int $entity_id       ID сущности
 * @param array $details       Детали операции в виде ассоциативного массива
 * @return boolean             Результат операции
 */
function logAction($conn, $action, $entity_type, $entity_id, $details = []) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO PCRM_Log
            (user_id, action, entity_type, entity_id, details, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        if (!$stmt) {
            return false;
        }
        
        $json_details = json_encode($details, JSON_UNESCAPED_UNICODE);
        $stmt->bind_param('issis', $_SESSION['user_id'], $action, $entity_type, $entity_id, $json_details);
        
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Ошибка при добавлении в журнал: " . $e->getMessage());
        return false;
    }
}