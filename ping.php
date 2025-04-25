<?php
// /crm/ping.php
require_once __DIR__ . '/config/session.php';

// Проверяем активность сессии
if (isset($_SESSION['user_id'])) {
    // Обновляем время последнего посещения
    $_SESSION['last_activity'] = time();
    
    // Возвращаем OK и user_id в заголовке
    header('X-User-ID: ' . $_SESSION['user_id']);
    echo 'OK';
} else {
    // Если сессия неактивна, отвечаем соответствующим статусом
    http_response_code(401); // Unauthorized
    echo 'SESSION_EXPIRED';
}
?>