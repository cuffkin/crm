<?php
// /crm/modules/trash/debug_check.php - Диагностика корзины
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== ДИАГНОСТИКА КОРЗИНЫ ===\n\n";

// 1. Проверка подключения к БД
echo "1. Подключение к БД: ";
if ($conn) {
    echo "✓ Успешно\n";
} else {
    echo "✗ Ошибка\n";
    exit;
}

// 2. Проверка сессии
echo "2. Сессия пользователя: ";
if (isset($_SESSION['user_id'])) {
    echo "✓ user_id = " . $_SESSION['user_id'] . "\n";
} else {
    echo "✗ Не авторизован\n";
    exit;
}

// 3. Проверка таблицы PCRM_TrashItems
echo "3. Таблица PCRM_TrashItems: ";
$result = $conn->query("SHOW TABLES LIKE 'PCRM_TrashItems'");
if ($result && $result->num_rows > 0) {
    echo "✓ Существует\n";
} else {
    echo "✗ Не найдена\n";
    exit;
}

// 4. Проверка структуры таблицы
echo "4. Структура таблицы:\n";
$result = $conn->query("DESCRIBE PCRM_TrashItems");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "   - {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "   ✗ Ошибка получения структуры\n";
}

// 5. Количество записей в корзине
echo "5. Записи в корзине:\n";
$result = $conn->query("SELECT COUNT(*) as total FROM PCRM_TrashItems");
if ($result) {
    $row = $result->fetch_assoc();
    echo "   Всего записей: " . $row['total'] . "\n";
} else {
    echo "   ✗ Ошибка подсчета: " . $conn->error . "\n";
}

// 6. Разбивка по типам
echo "6. Разбивка по типам:\n";
$result = $conn->query("SELECT item_type, document_type, COUNT(*) as count FROM PCRM_TrashItems GROUP BY item_type, document_type");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "   {$row['item_type']}/{$row['document_type']}: {$row['count']}\n";
    }
} else {
    echo "   ✗ Ошибка: " . $conn->error . "\n";
}

// 7. Последние записи
echo "7. Последние 5 записей:\n";
$result = $conn->query("SELECT id, item_type, document_type, original_name, deleted_at FROM PCRM_TrashItems ORDER BY deleted_at DESC LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "   ID {$row['id']}: {$row['item_type']}/{$row['document_type']} - '{$row['original_name']}' ({$row['deleted_at']})\n";
    }
} else {
    echo "   ✗ Ошибка: " . $conn->error . "\n";
}

// 8. Тест TrashManager
echo "8. Тест TrashManager:\n";
try {
    require_once __DIR__ . '/TrashManager.php';
    $trashManager = new TrashManager($conn, $_SESSION['user_id']);
    echo "   ✓ TrashManager создан\n";
    
    $items = $trashManager->getTrashItems('document', '', 10, 0);
    echo "   ✓ getTrashItems() выполнен, найдено элементов: " . count($items) . "\n";
    
    if (!empty($items)) {
        echo "   Первый элемент:\n";
        $first = $items[0];
        foreach ($first as $key => $value) {
            $displayValue = is_string($value) ? (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value) : $value;
            echo "     $key: $displayValue\n";
        }
    }
    
} catch (Exception $e) {
    echo "   ✗ Ошибка TrashManager: " . $e->getMessage() . "\n";
}

echo "\n=== ДИАГНОСТИКА ЗАВЕРШЕНА ===\n";
?> 