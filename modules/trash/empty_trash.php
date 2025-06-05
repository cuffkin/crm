<?php
// /crm/modules/trash/empty_trash.php - API для полной очистки корзины
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/TrashManager.php';

header('Content-Type: application/json');

// Проверка доступа - ВРЕМЕННО: доступ для всех авторизованных пользователей
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Необходима авторизация']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Получаем все элементы корзины
    $query = "SELECT * FROM PCRM_TrashItems ORDER BY id";
    $result = $conn->query($query);
    
    $trashManager = new TrashManager($conn, $_SESSION['user_id']);
    $deletedCount = 0;
    
    while ($item = $result->fetch_assoc()) {
        $deleteResult = $trashManager->permanentDelete($item['id']);
        if ($deleteResult['success']) {
            $deletedCount++;
        }
    }
    
    $conn->commit();
    echo json_encode(['success' => true, 'deleted_count' => $deletedCount]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 