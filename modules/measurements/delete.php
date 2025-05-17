<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/session-manager.php';

// Проверка авторизации
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Не авторизован']);
    exit;
}

// Проверка прав доступа
if (!check_access($conn, $_SESSION['user_id'], 'measurements')) {
    echo json_encode(['success' => false, 'message' => 'Нет доступа к модулю']);
    exit;
}

// Получение ID единицы измерения для удаления
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Некорректный ID единицы измерения']);
    exit;
}

// Проверяем, используется ли единица измерения в товарах
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM PCRM_Product WHERE default_measurement_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$productCount = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

if ($productCount > 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Невозможно удалить: эта единица измерения используется как основная в ' . $productCount . ' товаре(ах)'
    ]);
    exit;
}

// Проверяем, используется ли единица измерения в таблице связей
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM PCRM_Product_Measurement WHERE measurement_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$linkCount = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

if ($linkCount > 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Невозможно удалить: эта единица измерения используется в ' . $linkCount . ' товаре(ах)'
    ]);
    exit;
}

// Проверяем, является ли единица измерения дефолтной
$stmt = $conn->prepare("SELECT is_default FROM PCRM_Measurement WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Единица измерения не найдена']);
    exit;
}

$isDefault = $result->fetch_assoc()['is_default'];

if ($isDefault) {
    echo json_encode([
        'success' => false, 
        'message' => 'Невозможно удалить единицу измерения, используемую по умолчанию'
    ]);
    exit;
}

// Удаляем единицу измерения
$stmt = $conn->prepare("DELETE FROM PCRM_Measurement WHERE id = ?");
$stmt->bind_param('i', $id);
$result = $stmt->execute();
$stmt->close();

if ($result) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Ошибка при удалении: ' . $conn->error]);
} 