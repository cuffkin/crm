<?php
// /crm/modules/trash/cleanup_old.php - API для автоочистки старых элементов корзины
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

$trashManager = new TrashManager($conn, $_SESSION['user_id']);
$result = $trashManager->autoCleanup();

echo json_encode($result);
?> 