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

// Получение и валидация данных формы
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$name = trim($_POST['name'] ?? '');
$shortName = trim($_POST['short_name'] ?? '');
$description = trim($_POST['description'] ?? '');
$isDefault = isset($_POST['is_default']) ? intval($_POST['is_default']) : 0;
$status = $_POST['status'] ?? 'active';

// Базовая валидация
if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Наименование обязательно']);
    exit;
}

if (empty($shortName)) {
    echo json_encode(['success' => false, 'message' => 'Сокращение обязательно']);
    exit;
}

// Проверка уникальности сокращения
$stmt = $conn->prepare("SELECT id FROM PCRM_Measurement WHERE short_name = ? AND id != ?");
$stmt->bind_param('si', $shortName, $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Сокращение уже используется']);
    exit;
}
$stmt->close();

// Начинаем транзакцию
$conn->begin_transaction();

try {
    // Если установлен флаг по умолчанию, сбрасываем его у других единиц
    if ($isDefault) {
        $stmt = $conn->prepare("UPDATE PCRM_Measurement SET is_default = 0");
        $stmt->execute();
        $stmt->close();
    }
    
    // Создание или обновление единицы измерения
    if ($id > 0) {
        // Обновление
        $stmt = $conn->prepare("UPDATE PCRM_Measurement SET name = ?, short_name = ?, description = ?, is_default = ?, status = ? WHERE id = ?");
        $stmt->bind_param('sssisi', $name, $shortName, $description, $isDefault, $status, $id);
    } else {
        // Создание
        $stmt = $conn->prepare("INSERT INTO PCRM_Measurement (name, short_name, description, is_default, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssis', $name, $shortName, $description, $isDefault, $status);
    }
    
    $result = $stmt->execute();
    
    if (!$result) {
        throw new Exception($stmt->error);
    }
    
    $newId = $id > 0 ? $id : $conn->insert_id;
    $stmt->close();
    
    // Если ни одна единица не является дефолтной, делаем текущую дефолтной
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM PCRM_Measurement WHERE is_default = 1");
    $stmt->execute();
    $defaultCount = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
    if ($defaultCount === 0) {
        $stmt = $conn->prepare("UPDATE PCRM_Measurement SET is_default = 1 WHERE id = ?");
        $stmt->bind_param('i', $newId);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->commit();
    echo json_encode(['success' => true, 'id' => $newId]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Ошибка при сохранении: ' . $e->getMessage()]);
} 