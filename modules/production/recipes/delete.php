<?php
// /crm/modules/production/recipes/delete.php
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

// Получаем ID рецепта
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Неверный ID рецепта']);
    exit;
}

// Начинаем транзакцию
$conn->begin_transaction();

try {
    // Проверяем, используется ли рецепт в операциях производства
    $check_operations_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM PCRM_ProductionOperation 
        WHERE recipe_id = ?
    ");
    
    if (!$check_operations_stmt) {
        throw new Exception("Ошибка подготовки запроса проверки операций: " . $conn->error);
    }
    
    $check_operations_stmt->bind_param('i', $id);
    $check_operations_stmt->execute();
    $check_operations_result = $check_operations_stmt->get_result();
    $check_operations_row = $check_operations_result->fetch_assoc();
    
    if ($check_operations_row['count'] > 0) {
        throw new Exception("Невозможно удалить рецепт, так как он используется в операциях производства");
    }
    
    // Сначала сохраняем информацию о рецепте для журнала
    $get_recipe_stmt = $conn->prepare("
        SELECT r.name, p.name as product_name
        FROM PCRM_ProductionRecipe r
        LEFT JOIN PCRM_Product p ON r.product_id = p.id
        WHERE r.id = ?
    ");
    $get_recipe_stmt->bind_param('i', $id);
    $get_recipe_stmt->execute();
    $recipe_result = $get_recipe_stmt->get_result();
    $recipe = $recipe_result->fetch_assoc();
    
    // Удаляем ингредиенты рецепта
    $delete_items_stmt = $conn->prepare("DELETE FROM PCRM_ProductionRecipeItem WHERE recipe_id = ?");
    
    if (!$delete_items_stmt) {
        throw new Exception("Ошибка подготовки запроса удаления ингредиентов: " . $conn->error);
    }
    
    $delete_items_stmt->bind_param('i', $id);
    
    if (!$delete_items_stmt->execute()) {
        throw new Exception("Ошибка выполнения запроса удаления ингредиентов: " . $delete_items_stmt->error);
    }
    
    // Удаляем сам рецепт
    $delete_stmt = $conn->prepare("DELETE FROM PCRM_ProductionRecipe WHERE id = ?");
    
    if (!$delete_stmt) {
        throw new Exception("Ошибка подготовки запроса удаления рецепта: " . $conn->error);
    }
    
    $delete_stmt->bind_param('i', $id);
    
    if (!$delete_stmt->execute()) {
        throw new Exception("Ошибка выполнения запроса удаления рецепта: " . $delete_stmt->error);
    }
    
    // Проверяем, был ли удален рецепт
    if ($delete_stmt->affected_rows === 0) {
        throw new Exception("Рецепт не был удален");
    }
    
    // Записываем действие в журнал
    $log_stmt = $conn->prepare("
        INSERT INTO PCRM_Log
        (user_id, action, entity_type, entity_id, details, created_at)
        VALUES (?, 'delete', 'production_recipe', ?, ?, NOW())
    ");
    
    $log_details = json_encode([
        'name' => $recipe['name'] ?? "ID: $id", 
        'product_name' => $recipe['product_name'] ?? 'Н/Д'
    ], JSON_UNESCAPED_UNICODE);
    
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