<?php
// /crm/modules/trash/check_access.php - API для проверки доступа к корзине
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// Проверяем авторизацию
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['has_access' => false, 'reason' => 'not_authenticated']);
    exit;
}

// ВРЕМЕННО: разрешаем доступ всем авторизованным пользователям
// TODO: после настройки прав доступа заменить на check_access($conn, $_SESSION['user_id'], 'trash_management')
$hasAccess = true;

echo json_encode([
    'has_access' => $hasAccess,
    'reason' => $hasAccess ? 'access_granted' : 'insufficient_privileges'
]);
?> 