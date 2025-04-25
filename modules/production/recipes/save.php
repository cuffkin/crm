<?php
// /crm/modules/production/recipes/save.php
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

// Получаем данные из POST
$content = file_get_contents('php://input');
$recipeData = json_decode($content, true);

if (!$recipeData) {
    echo json_encode(['success' => false, 'error' => 'Данные рецепта отсутствуют']);
    exit;
}

try {
    // Проверяем обязательные поля
    if (empty($recipeData['name'])) {
        throw new Exception('Название рецепта не может быть пустым');
    }
    
    if (empty($recipeData['product_id'])) {
        throw new Exception('Необходимо выбрать производимый товар');
    }
    
    if (empty($recipeData['output_quantity']) || $recipeData['output_quantity'] <= 0) {
        throw new Exception('Количество производимого товара должно быть больше нуля');
    }
    
    if (empty($recipeData['ingredients']) || !is_array($recipeData['ingredients']) || count($recipeData['ingredients']) === 0) {
        throw new Exception('Рецепт должен содержать хотя бы один ингредиент');
    }
    
    // Начинаем транзакцию
    $conn->begin_transaction();
    
    $id = (int)$recipeData['id'];
    $name = $recipeData['name'];
    $product_id = (int)$recipeData['product_id'];
    $output_quantity = (float)$recipeData['output_quantity'];
    $description = $recipeData['description'] ?? '';
    $status = $recipeData['status'] ?? 'active';
    $created_by = $_SESSION['user_id'];
    
    if ($id > 0) {
        // Обновляем существующий рецепт
        $sql = "UPDATE PCRM_ProductionRecipe 
                SET name=?, product_id=?, output_quantity=?, description=?, status=?, updated_at=NOW() 
                WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sidssi", $name, $product_id, $output_quantity, $description, $status, $id);
        $stmt->execute();
        
        if ($stmt->error) {
            throw new Exception("Ошибка при обновлении рецепта: " . $stmt->error);
        }
        
        // Удаляем все существующие ингредиенты
        $deleteStmt = $conn->prepare("DELETE FROM PCRM_ProductionRecipeItem WHERE recipe_id=?");
        $deleteStmt->bind_param("i", $id);
        $deleteStmt->execute();
        
        if ($deleteStmt->error) {
            throw new Exception("Ошибка при удалении ингредиентов: " . $deleteStmt->error);
        }
    } else {
        // Создаем новый рецепт
        $sql = "INSERT INTO PCRM_ProductionRecipe 
                (name, product_id, output_quantity, description, status, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sidisi", $name, $product_id, $output_quantity, $description, $status, $created_by);
        $stmt->execute();
        
        if ($stmt->error) {
            throw new Exception("Ошибка при создании рецепта: " . $stmt->error);
        }
        
        $id = $stmt->insert_id;
    }
    
    // Добавляем ингредиенты
    $insertItemSql = "INSERT INTO PCRM_ProductionRecipeItem 
                      (recipe_id, ingredient_id, quantity) 
                      VALUES (?, ?, ?)";
    $insertItemStmt = $conn->prepare($insertItemSql);
    
    foreach ($recipeData['ingredients'] as $ingredient) {
        $ingredient_id = (int)$ingredient['ingredient_id'];
        $quantity = (float)$ingredient['quantity'];
        
        $insertItemStmt->bind_param("iid", $id, $ingredient_id, $quantity);
        $insertItemStmt->execute();
        
        if ($insertItemStmt->error) {
            throw new Exception("Ошибка при добавлении ингредиента: " . $insertItemStmt->error);
        }
    }
    
    // Завершаем транзакцию
    $conn->commit();
    
    // Записываем в журнал
    $log_stmt = $conn->prepare("
        INSERT INTO PCRM_Log
        (user_id, action, entity_type, entity_id, details, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $action = $recipeData['id'] > 0 ? 'update' : 'create';
    $entity_type = 'production_recipe';
    $log_details = json_encode([
        'name' => $name,
        'product_id' => $product_id,
        'ingredient_count' => count($recipeData['ingredients'])
    ], JSON_UNESCAPED_UNICODE);
    
    if($log_stmt) {
        $log_stmt->bind_param('issis', $_SESSION['user_id'], $action, $entity_type, $id, $log_details);
        $log_stmt->execute();
    }
    
    echo json_encode(['success' => true, 'id' => $id]);
    
} catch (Exception $e) {
    // В случае ошибки откатываем транзакцию
    if (isset($conn) && $conn->connect_error === false) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}