<?php
// /crm/modules/trash/permanent_delete.php - API для окончательного удаления элементов из корзины
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

$trashId = (int)($_POST['id'] ?? 0);

if ($trashId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Некорректный ID элемента корзины']);
    exit;
}

$trashManager = new TrashManager($conn, $_SESSION['user_id']);
$result = $trashManager->permanentDelete($trashId);

echo json_encode($result);
?> 