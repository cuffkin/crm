<?php
// /crm/modules/production/operations/api.php
// Единый файл API для всех операций производства

require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/related_documents.php';

// Проверяем доступ
if (!check_access($conn, $_SESSION['user_id'], 'production')) {
    echo json_encode(['success' => false, 'error' => 'У вас нет доступа к этому разделу']);
    exit;
}

// Обрабатываем тип запроса
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

switch ($action) {
    case 'check_ingredients':
        checkIngredients($conn);
        break;
    case 'check_stock':
        checkStock($conn);
        break;
    case 'get_recipe_ingredients':
        getRecipeIngredients($conn);
        break;
    case 'get_stock_for_ingredients':
        get_stock_for_ingredients($conn);
        break;
    case 'conduct':
        conductOperation($conn);
        break;
    case 'cancel':
        cancelOperation($conn);
        break;
    case 'create_from_order':
        createFromOrder($conn);
        break;
    case 'execute_production':
        executeProduction($conn);
        break;
    case 'get_total_stock':
        getTotalStock($conn);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
        break;
}

/**
 * Проверяет наличие ингредиентов для производства по указанному рецепту
 */
function checkIngredients($conn) {
    // Используем JSON для получения данных
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Если данные не в JSON, пробуем получить из POST
    if (!$data) {
        $data = [
            'recipe_id' => isset($_POST['recipe_id']) ? intval($_POST['recipe_id']) : 0,
            'quantity' => isset($_POST['quantity']) ? floatval($_POST['quantity']) : 0,
            'warehouse_id' => isset($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0
        ];
    }
    
    if (!$data['recipe_id'] || !$data['quantity'] || !$data['warehouse_id']) {
        echo json_encode(['success' => false, 'error' => 'Не указаны все необходимые параметры']);
        return;
    }
    
    $recipe_id = intval($data['recipe_id']);
    $quantity = floatval($data['quantity']);
    $warehouse_id = intval($data['warehouse_id']);
    
    if ($recipe_id <= 0 || $quantity <= 0 || $warehouse_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Некорректные параметры']);
        return;
    }
    
    try {
        // Получаем информацию о рецепте и продукте
        $recipe_stmt = $conn->prepare("
            SELECT r.*, p.name as product_name, p.unit_of_measure 
            FROM PCRM_ProductionRecipe r
            JOIN PCRM_Product p ON r.product_id = p.id
            WHERE r.id = ? AND r.deleted = 0
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
        
        // Получаем ингредиенты рецепта
        $stmt = $conn->prepare("
            SELECT i.*, p.name, p.unit_of_measure 
            FROM PCRM_ProductionRecipeItem i
            JOIN PCRM_Product p ON i.ingredient_id = p.id
            WHERE i.recipe_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception($conn->error);
        }
        
        $stmt->bind_param('i', $recipe_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $ingredients = [];
        $allAvailable = true;
        
        while ($row = $result->fetch_assoc()) {
            // Для каждого ингредиента проверяем наличие на складе
            $required_quantity = $row['quantity'] * $quantity;
            
            // Получаем количество на складе
            $stock_stmt = $conn->prepare("
                SELECT COALESCE(SUM(quantity), 0) as stock_quantity 
                FROM PCRM_Stock 
                WHERE product_id = ? AND warehouse_id = ?
            ");
            
            if (!$stock_stmt) {
                throw new Exception($conn->error);
            }
            
            $stock_stmt->bind_param('ii', $row['ingredient_id'], $warehouse_id);
            $stock_stmt->execute();
            $stock_result = $stock_stmt->get_result();
            $stock_row = $stock_result->fetch_assoc();
            
            $stock_quantity = $stock_row ? floatval($stock_row['stock_quantity']) : 0;
            $available = $stock_quantity >= $required_quantity;
            
            if (!$available) {
                $allAvailable = false;
            }
            
            $ingredients[] = [
                'id' => $row['ingredient_id'],
                'name' => $row['name'],
                'required_quantity' => $required_quantity,
                'stock_quantity' => $stock_quantity,
                'available' => $available,
                'unit' => $row['unit_of_measure']
            ];
        }
        
        echo json_encode([
            'success' => true, 
            'ingredients' => $ingredients,
            'all_available' => $allAvailable,
            'recipe_name' => $recipe['name'],
            'product_name' => $recipe['product_name'],
            'product_unit' => $recipe['unit_of_measure']
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Ошибка при проверке ингредиентов: ' . $e->getMessage()]);
    }
}

/**
 * Проверяет наличие товаров на складе
 */
function checkStock($conn) {
    // Используем JSON для получения данных
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Если данные не в JSON, пробуем получить из POST
    if (!$data) {
        $data = [
            'product_id' => isset($_POST['product_id']) ? intval($_POST['product_id']) : 0,
            'warehouse_id' => isset($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0
        ];
    }
    
    if (!$data['product_id'] || !$data['warehouse_id']) {
        echo json_encode(['success' => false, 'error' => 'Не указаны все необходимые параметры']);
        return;
    }
    
    $product_id = intval($data['product_id']);
    $warehouse_id = intval($data['warehouse_id']);
    
    if ($product_id <= 0 || $warehouse_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Некорректные параметры']);
        return;
    }
    
    try {
        // Получаем информацию о продукте
        $product_stmt = $conn->prepare("
            SELECT name, unit_of_measure 
            FROM PCRM_Product 
            WHERE id = ?
        ");
        
        if (!$product_stmt) {
            throw new Exception($conn->error);
        }
        
        $product_stmt->bind_param('i', $product_id);
        $product_stmt->execute();
        $product_result = $product_stmt->get_result();
        
        if ($product_result->num_rows === 0) {
            throw new Exception("Продукт не найден");
        }
        
        $product = $product_result->fetch_assoc();
        
        // Получаем количество на складе
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(quantity), 0) as stock_quantity 
            FROM PCRM_Stock 
            WHERE product_id = ? AND warehouse_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception($conn->error);
        }
        
        $stmt->bind_param('ii', $product_id, $warehouse_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $stock_quantity = $row ? floatval($row['stock_quantity']) : 0;
        
        echo json_encode([
            'success' => true, 
            'stock_quantity' => $stock_quantity,
            'product_name' => $product['name'],
            'unit_of_measure' => $product['unit_of_measure']
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Ошибка при проверке остатков: ' . $e->getMessage()]);
    }
}

/**
 * Получает список ингредиентов для указанного рецепта
 */
function getRecipeIngredients($conn) {
    header('Content-Type: application/json');
    $recipe_id = isset($_GET['recipe_id']) ? intval($_GET['recipe_id']) : 0;
    $warehouse_id = isset($_GET['warehouse_id']) ? intval($_GET['warehouse_id']) : 0; // Склад теперь важен

    if ($recipe_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Неверный ID рецепта.']);
        return;
    }

    try {
        // Запрос для получения ингредиентов и их остатков одним махом
        $sql = "
            SELECT 
                ri.ingredient_id,
                ri.quantity,
                p.name AS product_name,
                p.unit_of_measure,
                (SELECT COALESCE(SUM(s.quantity), 0) 
                 FROM PCRM_Stock s 
                 WHERE s.prod_id = ri.ingredient_id" . 
                 ($warehouse_id > 0 ? " AND s.warehouse_id = ?" : "") . 
                ") as stock_quantity
            FROM PCRM_ProductionRecipeItem ri
            JOIN PCRM_Product p ON ri.ingredient_id = p.id
            WHERE ri.recipe_id = ?
        ";
        
        $stmt = $conn->prepare($sql);

        if ($warehouse_id > 0) {
            $stmt->bind_param("ii", $warehouse_id, $recipe_id);
        } else {
            $stmt->bind_param("i", $recipe_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $ingredients = $result->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode(['success' => true, 'ingredients' => $ingredients]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Ошибка при получении ингредиентов: ' . $e->getMessage()]);
    }
}

function get_stock_for_ingredients($conn) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $ingredient_ids = $data['ingredient_ids'] ?? [];
    $warehouse_id = $data['warehouse_id'] ?? 0;

    if (empty($ingredient_ids) || $warehouse_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Не указаны ID ингредиентов или склад.']);
        return;
    }

    try {
        $placeholders = implode(',', array_fill(0, count($ingredient_ids), '?'));
        $sql = "
            SELECT prod_id, COALESCE(SUM(quantity), 0) as stock_quantity
            FROM PCRM_Stock
            WHERE warehouse_id = ? AND prod_id IN ($placeholders)
            GROUP BY prod_id
        ";
        
        $stmt = $conn->prepare($sql);
        $types = 'i' . str_repeat('i', count($ingredient_ids));
        $params = array_merge([$warehouse_id], $ingredient_ids);
        $stmt->bind_param($types, ...$params);
        
        $stmt->execute();
        $result = $stmt->get_result();
        $stocks = [];
        while($row = $result->fetch_assoc()) {
            $stocks[$row['prod_id']] = $row['stock_quantity'];
        }

        // Убедимся, что для всех запрошенных ID есть ответ
        foreach ($ingredient_ids as $id) {
            if (!isset($stocks[$id])) {
                $stocks[$id] = 0;
            }
        }

        echo json_encode(['success' => true, 'stocks' => $stocks]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Ошибка при получении остатков: ' . $e->getMessage()]);
    }
}

/**
 * Проводит операцию производства
 */
function conductOperation($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Неверный ID операции']);
        return;
    }
    
    try {
        // Начинаем транзакцию
        $conn->begin_transaction();
        
        // Получаем данные операции
        $stmt = $conn->prepare("
            SELECT o.*, p.name as product_name, w.name as warehouse_name
            FROM PCRM_ProductionOperation o
            JOIN PCRM_Product p ON o.product_id = p.id
            JOIN PCRM_Warehouse w ON o.warehouse_id = w.id
            WHERE o.id = ? AND o.conducted = 0
        ");
        
        if (!$stmt) {
            throw new Exception("Ошибка подготовки запроса: " . $conn->error);
        }
        
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Операция не найдена или уже проведена");
        }
        
        $operation = $result->fetch_assoc();
        $warehouse_id = $operation['warehouse_id'];
        $product_id = $operation['product_id'];
        $output_quantity = $operation['output_quantity'];
        
        // Получаем ингредиенты операции
        $items_stmt = $conn->prepare("
            SELECT i.*, p.name as ingredient_name, p.unit_of_measure 
            FROM PCRM_ProductionOperationItem i
            JOIN PCRM_Product p ON i.ingredient_id = p.id
            WHERE i.operation_id = ?
        ");
        
        if (!$items_stmt) {
            throw new Exception("Ошибка подготовки запроса ингредиентов: " . $conn->error);
        }
        
        $items_stmt->bind_param('i', $id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        
        // Проверяем, есть ли ингредиенты
        if ($items_result->num_rows === 0) {
            throw new Exception("Не найдены ингредиенты для операции");
        }
        
        // Проверяем наличие ингредиентов на складе
        $ingredients = [];
        $missing_ingredients = [];
        
        while ($item = $items_result->fetch_assoc()) {
            $ingredients[] = $item;
            
            // Проверяем наличие на складе
            $stock_stmt = $conn->prepare("
                SELECT COALESCE(SUM(quantity), 0) as stock_quantity 
                FROM PCRM_Stock 
                WHERE product_id = ? AND warehouse_id = ?
            ");
            
            if (!$stock_stmt) {
                throw new Exception("Ошибка проверки остатков: " . $conn->error);
            }
            
            $stock_stmt->bind_param('ii', $item['ingredient_id'], $warehouse_id);
            $stock_stmt->execute();
            $stock_result = $stock_stmt->get_result();
            $stock_row = $stock_result->fetch_assoc();
            
            $stock_quantity = floatval($stock_row['stock_quantity']);
            
            if ($stock_quantity < $item['quantity']) {
                $missing_ingredients[] = [
                    'name' => $item['ingredient_name'],
                    'required' => $item['quantity'],
                    'available' => $stock_quantity,
                    'unit' => $item['unit_of_measure']
                ];
            }
        }
        
        // Если есть отсутствующие ингредиенты, возвращаем ошибку
        if (count($missing_ingredients) > 0) {
            $missing_list = "";
            foreach ($missing_ingredients as $item) {
                $missing_list .= "- {$item['name']}: требуется {$item['required']} {$item['unit']}, в наличии {$item['available']} {$item['unit']}\n";
            }
            
            throw new Exception("Недостаточно ингредиентов на складе:\n" . $missing_list);
        }
        
        // Снова получаем все ингредиенты, так как курсор закрылся
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        
        // Списываем ингредиенты
        while ($item = $items_result->fetch_assoc()) {
            // Вычитаем количество ингредиента со склада
            $update_stock_stmt = $conn->prepare("
                UPDATE PCRM_Stock 
                SET quantity = quantity - ? 
                WHERE product_id = ? AND warehouse_id = ?
            ");
            
            if (!$update_stock_stmt) {
                throw new Exception("Ошибка обновления остатков: " . $conn->error);
            }
            
            $update_stock_stmt->bind_param('dii', $item['quantity'], $item['ingredient_id'], $warehouse_id);
            $update_stock_stmt->execute();
            
            if ($update_stock_stmt->affected_rows === 0) {
                // Если записи нет, создаем с отрицательным значением
                $insert_stock_stmt = $conn->prepare("
                    INSERT INTO PCRM_Stock (product_id, warehouse_id, quantity) 
                    VALUES (?, ?, ?)
                ");
                
                if (!$insert_stock_stmt) {
                    throw new Exception("Ошибка вставки в остатки: " . $conn->error);
                }
                
                $negative_quantity = -1 * $item['quantity'];
                $insert_stock_stmt->bind_param('iid', $item['ingredient_id'], $warehouse_id, $negative_quantity);
                $insert_stock_stmt->execute();
            }
            
            // Добавляем запись в журнал движения
            $movement_stmt = $conn->prepare("
                INSERT INTO PCRM_StockMovement 
                (product_id, warehouse_id, quantity, movement_type, document_type, document_id, user_id, movement_date, notes) 
                VALUES (?, ?, ?, 'output', 'production', ?, ?, NOW(), 'Списание материалов для производства')
            ");
            
            if (!$movement_stmt) {
                throw new Exception("Ошибка создания записи движения: " . $conn->error);
            }
            
            $negative_quantity = -1 * $item['quantity'];
            $movement_stmt->bind_param('iidii', $item['ingredient_id'], $warehouse_id, $negative_quantity, $id, $_SESSION['user_id']);
            $movement_stmt->execute();
        }
        
        // Добавляем готовый продукт на склад
        $update_product_stock_stmt = $conn->prepare("
            UPDATE PCRM_Stock 
            SET quantity = quantity + ? 
            WHERE product_id = ? AND warehouse_id = ?
        ");
        
        if (!$update_product_stock_stmt) {
            throw new Exception("Ошибка обновления остатков продукта: " . $conn->error);
        }
        
        $update_product_stock_stmt->bind_param('dii', $output_quantity, $product_id, $warehouse_id);
        $update_product_stock_stmt->execute();
        
        if ($update_product_stock_stmt->affected_rows === 0) {
            // Если записи нет, создаем новую
            $insert_product_stock_stmt = $conn->prepare("
                INSERT INTO PCRM_Stock (product_id, warehouse_id, quantity) 
                VALUES (?, ?, ?)
            ");
            
            if (!$insert_product_stock_stmt) {
                throw new Exception("Ошибка вставки в остатки продукта: " . $conn->error);
            }
            
            $insert_product_stock_stmt->bind_param('iid', $product_id, $warehouse_id, $output_quantity);
            $insert_product_stock_stmt->execute();
        }
        
        // Добавляем запись в журнал движения готового продукта
        $product_movement_stmt = $conn->prepare("
            INSERT INTO PCRM_StockMovement 
            (product_id, warehouse_id, quantity, movement_type, document_type, document_id, user_id, movement_date, notes) 
            VALUES (?, ?, ?, 'input', 'production', ?, ?, NOW(), 'Оприходование готовой продукции')
        ");
        
        if (!$product_movement_stmt) {
            throw new Exception("Ошибка создания записи движения продукта: " . $conn->error);
        }
        
        $product_movement_stmt->bind_param('iidii', $product_id, $warehouse_id, $output_quantity, $id, $_SESSION['user_id']);
        $product_movement_stmt->execute();
        
        // Обновляем статус операции
        $update_status_stmt = $conn->prepare("
            UPDATE PCRM_ProductionOperation 
            SET conducted = 1, status = 'completed', conducted_date = NOW(), conducted_by = ? 
            WHERE id = ?
        ");
        
        if (!$update_status_stmt) {
            throw new Exception("Ошибка обновления статуса: " . $conn->error);
        }
        
        $update_status_stmt->bind_param('ii', $_SESSION['user_id'], $id);
        $update_status_stmt->execute();
        
        // Записываем в журнал
        $log_stmt = $conn->prepare("
            INSERT INTO PCRM_Log
            (user_id, action, entity_type, entity_id, details, created_at)
            VALUES (?, 'conduct', 'production_operation', ?, ?, NOW())
        ");
        
        $log_details = json_encode([
            'product_name' => $operation['product_name'],
            'quantity' => $output_quantity,
            'warehouse_name' => $operation['warehouse_name']
        ]);
        
        $log_stmt->bind_param('iis', $_SESSION['user_id'], $id, $log_details);
        $log_stmt->execute();
        
        // Если есть связь с заказом на производство, обновляем его статус
        if (isset($operation['order_id']) && $operation['order_id'] > 0) {
            $update_order_stmt = $conn->prepare("
                UPDATE PCRM_ProductionOrder
                SET status = 'completed', completed_at = NOW()
                WHERE id = ? AND status = 'in_progress'
            ");
            
            $update_order_stmt->bind_param('i', $operation['order_id']);
            $update_order_stmt->execute();
            
            // Регистрируем связь с заказом на производство
            registerRelatedDocument($conn, 'production_operation', $id, 'production_order', $operation['order_id']);
        }
        
        // Завершаем транзакцию
        $conn->commit();
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        // Откатываем транзакцию в случае ошибки
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Отменяет проведение операции производства
 */
function cancelOperation($conn) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Неверный ID операции']);
        return;
    }
    
    try {
        // Начинаем транзакцию
        $conn->begin_transaction();
        
        // Получаем данные операции
        $stmt = $conn->prepare("
            SELECT o.*, p.name as product_name, w.name as warehouse_name
            FROM PCRM_ProductionOperation o
            JOIN PCRM_Product p ON o.product_id = p.id
            JOIN PCRM_Warehouse w ON o.warehouse_id = w.id
            WHERE o.id = ? AND o.conducted = 1
        ");
        
        if (!$stmt) {
            throw new Exception("Ошибка подготовки запроса: " . $conn->error);
        }
        
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Операция не найдена или не проведена");
        }
        
        $operation = $result->fetch_assoc();
        $warehouse_id = $operation['warehouse_id'];
        $product_id = $operation['product_id'];
        $output_quantity = $operation['output_quantity'];
        
        // Проверяем наличие готового продукта на складе
        $stock_stmt = $conn->prepare("
            SELECT COALESCE(SUM(quantity), 0) as stock_quantity 
            FROM PCRM_Stock 
            WHERE product_id = ? AND warehouse_id = ?
        ");
        
        $stock_stmt->bind_param('ii', $product_id, $warehouse_id);
        $stock_stmt->execute();
        $stock_result = $stock_stmt->get_result();
        $stock_row = $stock_result->fetch_assoc();
        
        $stock_quantity = floatval($stock_row['stock_quantity']);
        
        if ($stock_quantity < $output_quantity) {
            throw new Exception("Недостаточно готового продукта на складе для отмены проведения. Требуется: {$output_quantity}, в наличии: {$stock_quantity}");
        }
        
        // Получаем ингредиенты операции
        $items_stmt = $conn->prepare("
            SELECT i.*, p.name as ingredient_name, p.unit_of_measure 
            FROM PCRM_ProductionOperationItem i
            JOIN PCRM_Product p ON i.ingredient_id = p.id
            WHERE i.operation_id = ?
        ");
        
        if (!$items_stmt) {
            throw new Exception("Ошибка подготовки запроса ингредиентов: " . $conn->error);
        }
        
        $items_stmt->bind_param('i', $id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        
        // Обратные движения: списываем готовый продукт
        $update_product_stock_stmt = $conn->prepare("
            UPDATE PCRM_Stock 
            SET quantity = quantity - ? 
            WHERE product_id = ? AND warehouse_id = ?
        ");
        
        if (!$update_product_stock_stmt) {
            throw new Exception("Ошибка обновления остатков продукта: " . $conn->error);
        }
        
        $update_product_stock_stmt->bind_param('dii', $output_quantity, $product_id, $warehouse_id);
        $update_product_stock_stmt->execute();
        
        // Добавляем запись в журнал движения продукта
        $product_movement_stmt = $conn->prepare("
            INSERT INTO PCRM_StockMovement 
            (product_id, warehouse_id, quantity, movement_type, document_type, document_id, user_id, movement_date, notes) 
            VALUES (?, ?, ?, 'output', 'production_cancel', ?, ?, NOW(), 'Отмена оприходования продукции')
        ");
        
        if (!$product_movement_stmt) {
            throw new Exception("Ошибка создания записи движения продукта: " . $conn->error);
        }
        
        $negative_quantity = -1 * $output_quantity;
        $product_movement_stmt->bind_param('iidii', $product_id, $warehouse_id, $negative_quantity, $id, $_SESSION['user_id']);
        $product_movement_stmt->execute();
        
        // Возвращаем ингредиенты на склад
        while ($item = $items_result->fetch_assoc()) {
            // Возвращаем количество ингредиента на склад
            $update_stock_stmt = $conn->prepare("
                UPDATE PCRM_Stock 
                SET quantity = quantity + ? 
                WHERE product_id = ? AND warehouse_id = ?
            ");
            
            if (!$update_stock_stmt) {
                throw new Exception("Ошибка обновления остатков: " . $conn->error);
            }
            
            $update_stock_stmt->bind_param('dii', $item['quantity'], $item['ingredient_id'], $warehouse_id);
            $update_stock_stmt->execute();
            
            // Добавляем запись в журнал движения
            $movement_stmt = $conn->prepare("
                INSERT INTO PCRM_StockMovement 
                (product_id, warehouse_id, quantity, movement_type, document_type, document_id, user_id, movement_date, notes) 
                VALUES (?, ?, ?, 'input', 'production_cancel', ?, ?, NOW(), 'Возврат материалов при отмене производства')
            ");
            
            if (!$movement_stmt) {
                throw new Exception("Ошибка создания записи движения: " . $conn->error);
            }
            
            $movement_stmt->bind_param('iidii', $item['ingredient_id'], $warehouse_id, $item['quantity'], $id, $_SESSION['user_id']);
            $movement_stmt->execute();
        }
        
        // Обновляем статус операции
        $update_status_stmt = $conn->prepare("
            UPDATE PCRM_ProductionOperation 
            SET conducted = 0, status = 'cancelled', conducted_date = NULL, conducted_by = NULL 
            WHERE id = ?
        ");
        
        if (!$update_status_stmt) {
            throw new Exception("Ошибка обновления статуса: " . $conn->error);
        }
        
        $update_status_stmt->bind_param('i', $id);
        $update_status_stmt->execute();
        
        // Записываем в журнал
        $log_stmt = $conn->prepare("
            INSERT INTO PCRM_Log
            (user_id, action, entity_type, entity_id, details, created_at)
            VALUES (?, 'cancel', 'production_operation', ?, ?, NOW())
        ");
        
        $log_details = json_encode([
            'product_name' => $operation['product_name'],
            'quantity' => $output_quantity,
            'warehouse_name' => $operation['warehouse_name']
        ]);
        
        $log_stmt->bind_param('iis', $_SESSION['user_id'], $id, $log_details);
        $log_stmt->execute();
        
        // Если есть связь с заказом на производство, обновляем его статус
        if (isset($operation['order_id']) && $operation['order_id'] > 0) {
            $update_order_stmt = $conn->prepare("
                UPDATE PCRM_ProductionOrder
                SET status = 'cancelled'
                WHERE id = ? AND status = 'completed'
            ");
            
            $update_order_stmt->bind_param('i', $operation['order_id']);
            $update_order_stmt->execute();
        }
        
        // Завершаем транзакцию
        $conn->commit();
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        // Откатываем транзакцию в случае ошибки
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Создает операцию производства на основании заказа на производство
 */
function createFromOrder($conn) {
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    
    if ($order_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Не указан ID заказа на производство']);
        return;
    }
    
    try {
        // Начинаем транзакцию
        $conn->begin_transaction();
        
        // Получаем данные заказа на производство
        $order_stmt = $conn->prepare("
            SELECT o.*, r.name as recipe_name, p.name as product_name
            FROM PCRM_ProductionOrder o
            JOIN PCRM_ProductionRecipe r ON o.recipe_id = r.id
            JOIN PCRM_Product p ON r.product_id = p.id
            WHERE o.id = ?
        ");
        
        if (!$order_stmt) {
            throw new Exception("Ошибка подготовки запроса заказа: " . $conn->error);
        }
        
        $order_stmt->bind_param('i', $order_id);
        $order_stmt->execute();
        $order_result = $order_stmt->get_result();
        
        if ($order_result->num_rows === 0) {
            throw new Exception("Заказ на производство не найден");
        }
        
        $order = $order_result->fetch_assoc();
        
        // Проверяем, что заказ не в статусе 'completed' или 'cancelled'
        if ($order['status'] === 'completed' || $order['status'] === 'cancelled') {
            throw new Exception("Невозможно создать операцию на основе завершенного или отмененного заказа");
        }
        
        // Проверяем, есть ли уже операция для этого заказа
        $check_stmt = $conn->prepare("
            SELECT id FROM PCRM_ProductionOperation
            WHERE order_id = ?
        ");
        
        if (!$check_stmt) {
            throw new Exception("Ошибка подготовки запроса проверки: " . $conn->error);
        }
        
        $check_stmt->bind_param('i', $order_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $operation = $check_result->fetch_assoc();
            throw new Exception("Для этого заказа уже создана операция #" . $operation['id']);
        }
        
        // Создаем операцию производства
        $insert_stmt = $conn->prepare("
            INSERT INTO PCRM_ProductionOperation
            (operation_number, recipe_id, product_id, output_quantity, warehouse_id, production_date, status, order_id, user_id, created_at)
            VALUES (?, ?, ?, ?, ?, NOW(), 'draft', ?, ?, NOW())
        ");
        
        if (!$insert_stmt) {
            throw new Exception("Ошибка подготовки запроса вставки: " . $conn->error);
        }
        
        // Генерируем номер операции
        $operation_number = 'OP-' . date('Ymd') . '-' . rand(1000, 9999);
        
        $insert_stmt->bind_param('siidiii', 
            $operation_number, 
            $order['recipe_id'], 
            $order['product_id'], 
            $order['quantity'], 
            $order['warehouse_id'], 
            $order_id, 
            $_SESSION['user_id']
        );
        
        if (!$insert_stmt->execute()) {
            throw new Exception("Ошибка создания операции: " . $insert_stmt->error);
        }
        
        $operation_id = $conn->insert_id;
        
        // Получаем список ингредиентов из рецепта
        $ingredients_stmt = $conn->prepare("
            SELECT ri.ingredient_id, ri.quantity * ? as required_quantity
            FROM PCRM_ProductionRecipeItem ri
            WHERE ri.recipe_id = ?
        ");
        
        if (!$ingredients_stmt) {
            throw new Exception("Ошибка подготовки запроса ингредиентов: " . $conn->error);
        }
        
        $ingredients_stmt->bind_param('di', $order['quantity'], $order['recipe_id']);
        $ingredients_stmt->execute();
        $ingredients_result = $ingredients_stmt->get_result();
        
        // Добавляем ингредиенты в операцию
        $item_stmt = $conn->prepare("
            INSERT INTO PCRM_ProductionOperationItem
            (operation_id, ingredient_id, quantity)
            VALUES (?, ?, ?)
        ");
        
        if (!$item_stmt) {
            throw new Exception("Ошибка подготовки запроса добавления ингредиента: " . $conn->error);
        }
        
        while ($ingredient = $ingredients_result->fetch_assoc()) {
            $item_stmt->bind_param('iid', 
                $operation_id, 
                $ingredient['ingredient_id'], 
                $ingredient['required_quantity']
            );
            
            if (!$item_stmt->execute()) {
                throw new Exception("Ошибка добавления ингредиента: " . $item_stmt->error);
            }
        }
        
        // Обновляем статус заказа на производство
        $update_order_stmt = $conn->prepare("
            UPDATE PCRM_ProductionOrder
            SET status = 'in_progress'
            WHERE id = ?
        ");
        
        if (!$update_order_stmt) {
            throw new Exception("Ошибка подготовки запроса обновления заказа: " . $conn->error);
        }
        
        $update_order_stmt->bind_param('i', $order_id);
        $update_order_stmt->execute();
        
        // Регистрируем связанные документы
        registerRelatedDocument($conn, 'production_order', $order_id, 'production_operation', $operation_id);
        
        // Записываем в журнал
        $log_stmt = $conn->prepare("
            INSERT INTO PCRM_Log
            (user_id, action, entity_type, entity_id, details, created_at)
            VALUES (?, 'create_from_order', 'production_operation', ?, ?, NOW())
        ");
        
        $log_details = json_encode([
            'order_id' => $order_id,
            'recipe_name' => $order['recipe_name'],
            'product_name' => $order['product_name'],
            'quantity' => $order['quantity']
        ]);
        
        $log_stmt->bind_param('iis', $_SESSION['user_id'], $operation_id, $log_details);
        $log_stmt->execute();
        
        // Завершаем транзакцию
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'operation_id' => $operation_id,
            'operation_number' => $operation_number
        ]);
        
    } catch (Exception $e) {
        // Откатываем транзакцию в случае ошибки
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Выполняет производственный процесс
 */
function executeProduction($conn) {
    // Получаем данные из POST
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Не получены данные для выполнения производства']);
        return;
    }
    
    // Проверяем наличие необходимых данных
    $required_fields = ['recipe_id', 'warehouse_id', 'quantity'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            echo json_encode(['success' => false, 'error' => "Не указано поле {$field}"]);
            return;
        }
    }
    
    // Получаем и валидируем данные
    $recipe_id = intval($data['recipe_id']);
    $warehouse_id = intval($data['warehouse_id']);
    $quantity = floatval($data['quantity']);
    $comment = isset($data['comment']) ? $data['comment'] : '';
    
    if ($recipe_id <= 0 || $warehouse_id <= 0 || $quantity <= 0) {
        echo json_encode(['success' => false, 'error' => 'Некорректные значения параметров']);
        return;
    }
    
    try {
        // Начинаем транзакцию
        $conn->begin_transaction();
        
        // Получаем информацию о рецепте
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
        $product_id = $recipe['product_id'];
        
        // Получаем ингредиенты рецепта
        $ingredients_stmt = $conn->prepare("
            SELECT ri.*, p.name as ingredient_name
            FROM PCRM_ProductionRecipeItem ri
            JOIN PCRM_Product p ON ri.ingredient_id = p.id
            WHERE ri.recipe_id = ?
        ");
        
        if (!$ingredients_stmt) {
            throw new Exception("Ошибка подготовки запроса ингредиентов: " . $conn->error);
        }
        
        $ingredients_stmt->bind_param('i', $recipe_id);
        $ingredients_stmt->execute();
        $ingredients_result = $ingredients_stmt->get_result();
        
        if ($ingredients_result->num_rows === 0) {
            throw new Exception("В рецепте не найдены ингредиенты");
        }
        
        // Проверяем наличие ингредиентов на складе
        $missing_ingredients = [];
        $ingredients = [];
        
        while ($ingredient = $ingredients_result->fetch_assoc()) {
            $ingredients[] = $ingredient;
            $required_quantity = $ingredient['quantity'] * $quantity;
            
            // Проверяем наличие на складе
            $stock_stmt = $conn->prepare("
                SELECT COALESCE(SUM(quantity), 0) as stock_quantity
                FROM PCRM_Stock
                WHERE product_id = ? AND warehouse_id = ?
            ");
            
            if (!$stock_stmt) {
                throw new Exception("Ошибка проверки остатков: " . $conn->error);
            }
            
            $stock_stmt->bind_param('ii', $ingredient['ingredient_id'], $warehouse_id);
            $stock_stmt->execute();
            $stock_result = $stock_stmt->get_result();
            $stock_row = $stock_result->fetch_assoc();
            
            $stock_quantity = floatval($stock_row['stock_quantity']);
            
            if ($stock_quantity < $required_quantity) {
                $missing_ingredients[] = [
                    'name' => $ingredient['ingredient_name'],
                    'required' => $required_quantity,
                    'available' => $stock_quantity
                ];
            }
        }
        
        // Если есть недостающие ингредиенты, выдаем ошибку
        if (count($missing_ingredients) > 0) {
            $missing_list = "";
            foreach ($missing_ingredients as $item) {
                $missing_list .= "- {$item['name']}: требуется {$item['required']}, в наличии {$item['available']}\n";
            }
            
            throw new Exception("Недостаточно ингредиентов на складе:\n" . $missing_list);
        }
        
        // Генерируем номер операции
        $operation_number = 'OP-' . date('Ymd') . '-' . rand(1000, 9999);
        
        // Создаем операцию производства
        $operation_stmt = $conn->prepare("
            INSERT INTO PCRM_ProductionOperation
            (operation_number, recipe_id, product_id, output_quantity, warehouse_id, production_date, status, user_id, created_at, comment)
            VALUES (?, ?, ?, ?, ?, NOW(), 'draft', ?, NOW(), ?)
        ");
        
        if (!$operation_stmt) {
            throw new Exception("Ошибка подготовки запроса создания операции: " . $conn->error);
        }
        
        $operation_stmt->bind_param('siidiis',
            $operation_number,
            $recipe_id,
            $product_id,
            $quantity,
            $warehouse_id,
            $_SESSION['user_id'],
            $comment
        );
        
        if (!$operation_stmt->execute()) {
            throw new Exception("Ошибка создания операции: " . $operation_stmt->error);
        }
        
        $operation_id = $conn->insert_id;
        
        // Добавляем ингредиенты в операцию
        $item_stmt = $conn->prepare("
            INSERT INTO PCRM_ProductionOperationItem
            (operation_id, ingredient_id, quantity)
            VALUES (?, ?, ?)
        ");
        
        if (!$item_stmt) {
            throw new Exception("Ошибка подготовки запроса добавления ингредиента: " . $conn->error);
        }
        
        foreach ($ingredients as $ingredient) {
            $required_quantity = $ingredient['quantity'] * $quantity;
            
            $item_stmt->bind_param('iid',
                $operation_id,
                $ingredient['ingredient_id'],
                $required_quantity
            );
            
            if (!$item_stmt->execute()) {
                throw new Exception("Ошибка добавления ингредиента: " . $item_stmt->error);
            }
        }
        
        // Сразу проводим операцию, если запрошено
        if (isset($data['auto_conduct']) && $data['auto_conduct']) {
            // Списываем ингредиенты
            foreach ($ingredients as $ingredient) {
                $required_quantity = $ingredient['quantity'] * $quantity;
                
                // Обновляем остатки
                $update_stock_stmt = $conn->prepare("
                    UPDATE PCRM_Stock
                    SET quantity = quantity - ?
                    WHERE product_id = ? AND warehouse_id = ?
                ");
                
                if (!$update_stock_stmt) {
                    throw new Exception("Ошибка обновления остатков: " . $conn->error);
                }
                
                $update_stock_stmt->bind_param('dii', $required_quantity, $ingredient['ingredient_id'], $warehouse_id);
                $update_stock_stmt->execute();
                
                if ($update_stock_stmt->affected_rows === 0) {
                    // Если записи нет, создаем новую
                    $insert_stock_stmt = $conn->prepare("
                        INSERT INTO PCRM_Stock (product_id, warehouse_id, quantity)
                        VALUES (?, ?, ?)
                    ");
                    
                    if (!$insert_stock_stmt) {
                        throw new Exception("Ошибка вставки в остатки: " . $conn->error);
                    }
                    
                    $negative_quantity = -1 * $required_quantity;
                    $insert_stock_stmt->bind_param('iid', $ingredient['ingredient_id'], $warehouse_id, $negative_quantity);
                    $insert_stock_stmt->execute();
                }
                
                // Добавляем запись в журнал движения
                $movement_stmt = $conn->prepare("
                    INSERT INTO PCRM_StockMovement
                    (product_id, warehouse_id, quantity, movement_type, document_type, document_id, user_id, movement_date, notes)
                    VALUES (?, ?, ?, 'output', 'production', ?, ?, NOW(), 'Списание материалов для автопроизводства')
                ");
                
                if (!$movement_stmt) {
                    throw new Exception("Ошибка создания записи движения: " . $conn->error);
                }
                
                $negative_quantity = -1 * $required_quantity;
                $movement_stmt->bind_param('iidii', $ingredient['ingredient_id'], $warehouse_id, $negative_quantity, $operation_id, $_SESSION['user_id']);
                $movement_stmt->execute();
            }
            
            // Приходуем готовый продукт
            $update_product_stock_stmt = $conn->prepare("
                UPDATE PCRM_Stock
                SET quantity = quantity + ?
                WHERE product_id = ? AND warehouse_id = ?
            ");
            
            if (!$update_product_stock_stmt) {
                throw new Exception("Ошибка обновления остатков продукта: " . $conn->error);
            }
            
            $update_product_stock_stmt->bind_param('dii', $quantity, $product_id, $warehouse_id);
            $update_product_stock_stmt->execute();
            
            if ($update_product_stock_stmt->affected_rows === 0) {
                // Если записи нет, создаем новую
                $insert_product_stock_stmt = $conn->prepare("
                    INSERT INTO PCRM_Stock (product_id, warehouse_id, quantity)
                    VALUES (?, ?, ?)
                ");
                
                if (!$insert_product_stock_stmt) {
                    throw new Exception("Ошибка вставки в остатки продукта: " . $conn->error);
                }
                
                $insert_product_stock_stmt->bind_param('iid', $product_id, $warehouse_id, $quantity);
                $insert_product_stock_stmt->execute();
            }
            
            // Добавляем запись в журнал движения готового продукта
            $product_movement_stmt = $conn->prepare("
                INSERT INTO PCRM_StockMovement
                (product_id, warehouse_id, quantity, movement_type, document_type, document_id, user_id, movement_date, notes)
                VALUES (?, ?, ?, 'input', 'production', ?, ?, NOW(), 'Оприходование готовой продукции (автопроизводство)')
            ");
            
            if (!$product_movement_stmt) {
                throw new Exception("Ошибка создания записи движения продукта: " . $conn->error);
            }
            
            $product_movement_stmt->bind_param('iidii', $product_id, $warehouse_id, $quantity, $operation_id, $_SESSION['user_id']);
            $product_movement_stmt->execute();
            
            // Обновляем статус операции
            $update_status_stmt = $conn->prepare("
                UPDATE PCRM_ProductionOperation
                SET conducted = 1, status = 'completed', conducted_date = NOW(), conducted_by = ?
                WHERE id = ?
            ");
            
            if (!$update_status_stmt) {
                throw new Exception("Ошибка обновления статуса: " . $conn->error);
            }
            
            $update_status_stmt->bind_param('ii', $_SESSION['user_id'], $operation_id);
            $update_status_stmt->execute();
        }
        
        // Записываем в журнал
        $log_stmt = $conn->prepare("
            INSERT INTO PCRM_Log
            (user_id, action, entity_type, entity_id, details, created_at)
            VALUES (?, 'execute_production', 'production_operation', ?, ?, NOW())
        ");
        
        $log_details = json_encode([
            'recipe_name' => $recipe['name'],
            'product_name' => $recipe['product_name'],
            'quantity' => $quantity,
            'auto_conduct' => isset($data['auto_conduct']) && $data['auto_conduct']
        ]);
        
        $log_stmt->bind_param('iis', $_SESSION['user_id'], $operation_id, $log_details);
        $log_stmt->execute();
        
        // Завершаем транзакцию
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'operation_id' => $operation_id,
            'operation_number' => $operation_number,
            'conducted' => isset($data['auto_conduct']) && $data['auto_conduct']
        ]);
        
    } catch (Exception $e) {
        // Откатываем транзакцию в случае ошибки
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Получает общий остаток товара по всем складам.
 */
function getTotalStock($conn) {
    $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Некорректный ID товара']);
        return;
    }

    try {
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(quantity), 0) as total_stock 
            FROM PCRM_Stock 
            WHERE prod_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        echo json_encode(['success' => true, 'stock' => $row['total_stock']]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Ошибка при получении общего остатка: ' . $e->getMessage()]);
    }
}