<?php
// /crm/modules/production/recipes/api.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

$response = ['success' => false, 'message' => 'Invalid action'];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Доступ запрещен: вы не авторизованы.';
    echo json_encode($response);
    exit;
}

if (!check_access($conn, $_SESSION['user_id'], 'production')) {
    $response['message'] = 'Доступ запрещен: у вас нет прав.';
    echo json_encode($response);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_recipes':
        handle_get_recipes($conn);
        break;
    case 'save_recipe':
        handle_save_recipe($conn);
        break;
    case 'delete_recipe':
        handle_delete_recipe($conn);
        break;
    default:
        echo json_encode($response);
        break;
}

function handle_get_recipes($conn) {
    try {
        $query = "SELECT r.id, r.name, p.name as product_name, r.output_quantity, r.status, r.updated_at 
                  FROM PCRM_ProductionRecipe r
                  LEFT JOIN PCRM_Product p ON r.product_id = p.id
                  WHERE r.deleted = 0
                  ORDER BY r.updated_at DESC";
        $result = $conn->query($query);
        $recipes = [];
        while ($row = $result->fetch_assoc()) {
            $recipes[] = $row;
        }
        echo json_encode(['success' => true, 'recipes' => $recipes]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Ошибка при получении рецептов: ' . $e->getMessage()]);
    }
}

function handle_save_recipe($conn) {
    $response = ['success' => false, 'message' => 'Не удалось сохранить рецепт.'];
    
    $recipe_id = isset($_POST['recipe_id']) ? intval($_POST['recipe_id']) : 0;
    $recipe_name = $_POST['recipe_name'] ?? '';
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;
    $output_quantity = $_POST['output_quantity'] ?? 1;
    $description = $_POST['description'] ?? '';
    $status = $_POST['recipe_status'] ?? 'active';
    $ingredients_json = $_POST['ingredients_json'] ?? '[]';
    $ingredients = json_decode($ingredients_json, true);

    if (empty($recipe_name) || $product_id <= 0 || !is_array($ingredients)) {
        $response['message'] = 'Пожалуйста, заполните все обязательные поля: Название, Производимый продукт и Ингредиенты.';
        $response['debug_product_id_received'] = $_POST['product_id'] ?? 'НЕ ПОЛУЧЕНО';
        $response['debug_product_id_parsed'] = $product_id;
        echo json_encode($response);
        return;
    }

    $conn->begin_transaction();

    try {
        if ($recipe_id > 0) {
            // Обновление существующего рецепта
            $stmt = $conn->prepare(
                "UPDATE PCRM_ProductionRecipe SET name = ?, product_id = ?, output_quantity = ?, description = ?, status = ? WHERE id = ?"
            );
            $stmt->bind_param('sidssi', $recipe_name, $product_id, $output_quantity, $description, $status, $recipe_id);
        } else {
            // Создание нового рецепта
            $stmt = $conn->prepare(
                "INSERT INTO PCRM_ProductionRecipe (name, product_id, output_quantity, description, status) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('sidss', $recipe_name, $product_id, $output_quantity, $description, $status);
        }
        
        if (!$stmt->execute()) {
             throw new Exception("Ошибка при сохранении рецепта: " . $stmt->error);
        }

        if ($recipe_id == 0) {
            $recipe_id = $conn->insert_id;
        }

        // Удаляем старые ингредиенты
        $delete_stmt = $conn->prepare("DELETE FROM PCRM_ProductionRecipeItem WHERE recipe_id = ?");
        $delete_stmt->bind_param('i', $recipe_id);
        $delete_stmt->execute();
        
        // Добавляем новые/обновленные ингредиенты
        if (!empty($ingredients)) {
            $item_stmt = $conn->prepare(
                "INSERT INTO PCRM_ProductionRecipeItem (recipe_id, ingredient_id, quantity) VALUES (?, ?, ?)"
            );
            foreach ($ingredients as $ing) {
                if (!empty($ing['ingredient_id']) && !empty($ing['quantity'])) {
                    $item_stmt->bind_param('iid', $recipe_id, $ing['ingredient_id'], $ing['quantity']);
                    if (!$item_stmt->execute()) {
                        throw new Exception("Ошибка при сохранении ингредиента: " . $item_stmt->error);
                    }
                }
            }
        }
        
        $conn->commit();
        $response = ['success' => true, 'message' => 'Рецепт успешно сохранен!', 'recipe_id' => $recipe_id];

    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = 'Транзакция отменена: ' . $e->getMessage();
    }

    echo json_encode($response);
}

function handle_delete_recipe($conn) {
    $response = ['success' => false, 'message' => 'Не удалось удалить рецепт.'];
    $recipe_id = isset($_POST['recipe_id']) ? intval($_POST['recipe_id']) : 0;

    if ($recipe_id > 0) {
        $conn->begin_transaction();
        try {
            // Удаляем ингредиенты
            $stmt_items = $conn->prepare("DELETE FROM PCRM_ProductionRecipeItem WHERE recipe_id = ?");
            $stmt_items->bind_param('i', $recipe_id);
            $stmt_items->execute();

            // Удаляем сам рецепт
            $stmt_recipe = $conn->prepare("DELETE FROM PCRM_ProductionRecipe WHERE id = ?");
            $stmt_recipe->bind_param('i', $recipe_id);
            $stmt_recipe->execute();
            
            $conn->commit();
            $response = ['success' => true, 'message' => 'Рецепт успешно удален.'];

        } catch (Exception $e) {
            $conn->rollback();
            $response['message'] = 'Ошибка при удалении: ' . $e->getMessage();
        }
    } else {
        $response['message'] = 'Неверный ID рецепта.';
    }

    echo json_encode($response);
} 