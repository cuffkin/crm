<?php
// /crm/modules/trash/move_to_trash.php - Универсальный API для перемещения в корзину
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/TrashManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
    exit;
}

$documentType = $_POST['document_type'] ?? '';
$documentId = (int)($_POST['document_id'] ?? 0);
$reason = $_POST['reason'] ?? null;
$userId = $_SESSION['user_id'] ?? 1; // Fallback если нет сессии

if (!$documentType || $documentId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Некорректные параметры']);
    exit;
}

try {
    $trashManager = new TrashManager($conn, $userId);
    $result = $trashManager->moveToTrash('document', $documentType, $documentId, $reason);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true, 
            'message' => 'Элемент перемещен в корзину',
            'trash_id' => $result['trash_id']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка перемещения в корзину']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 