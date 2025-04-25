<?php
// /crm/modules/production/orders/save.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'production')) {
    die("Нет доступа");
}

// Получаем и валидируем данные
$id = (int)($_POST['id'] ?? 0);
$order_number = trim($_POST['order_number'] ?? '');
$recipe_id = (int)($_POST['recipe_id'] ?? 0);
$planned_date = $_POST['planned_date'] ?? '';
$status = $_POST['status'] ?? 'new';
$warehouse_id = (int)($_POST['warehouse_id'] ?? 0);
$quantity = (float)($_POST['quantity'] ?? 0);
$comment = trim($_POST['comment'] ?? '');
$created_by = $_SESSION['user_id'];

// Проверка обязательных полей
if (empty($order_number)) {
    die("Номер заказа не может быть пустым");
}

if ($recipe_id <= 0) {
    die("Необходимо выбрать рецепт");
}

if (empty($planned_date)) {
    die("Дата производства не может быть пустой");
}

if ($warehouse_id <= 0) {
    die("Необходимо выбрать склад");
}

if ($quantity <= 0) {
    die("Количество должно быть больше нуля");
}

try {
    if ($id > 0) {
        // Проверяем, не проведен ли заказ
        $checkStmt = $conn->prepare("SELECT conducted FROM PCRM_ProductionOrder WHERE id = ?");
        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row && $row['conducted'] == 1) {
            die("Заказ уже проведен и не может быть изменен");
        }
        
        // Обновляем существующий заказ
        $stmt = $conn->prepare("
            UPDATE PCRM_ProductionOrder 
            SET order_number = ?, 
                recipe_id = ?, 
                planned_date = ?, 
                status = ?, 
                warehouse_id = ?, 
                quantity = ?, 
                comment = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sissiisi", $order_number, $recipe_id, $planned_date, $status, $warehouse_id, $quantity, $comment, $id);
        $stmt->execute();
        
        if ($stmt->error) {
            throw new Exception("Ошибка при обновлении заказа: " . $stmt->error);
        }
    } else {
        // Создаем новый заказ
        $stmt = $conn->prepare("
            INSERT INTO PCRM_ProductionOrder 
            (order_number, recipe_id, planned_date, status, warehouse_id, quantity, comment, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sississi", $order_number, $recipe_id, $planned_date, $status, $warehouse_id, $quantity, $comment, $created_by);
        $stmt->execute();
        
        if ($stmt->error) {
            throw new Exception("Ошибка при создании заказа: " . $stmt->error);
        }
    }
    
    echo "OK";
} catch (Exception $e) {
    die($e->getMessage());
} 