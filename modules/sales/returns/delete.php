<?php
// /crm/modules/sales/returns/delete.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'sales_returns')) {
    die("Нет доступа");
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Некорректный ID возврата");
}

// Начинаем транзакцию
$conn->begin_transaction();

try {
    // Сначала удаляем связи с другими документами
    $delRel = $conn->prepare("DELETE FROM PCRM_RelatedDocuments WHERE (source_type='return' AND source_id=?) OR (related_type='return' AND related_id=?)");
    $delRel->bind_param("ii", $id, $id);
    $delRel->execute();
    
    // Затем удаляем позиции возврата
    $del1 = $conn->prepare("DELETE FROM PCRM_ReturnItem WHERE return_id=?");
    $del1->bind_param("i", $id);
    $del1->execute();
    if ($del1->error) {
        throw new Exception("Ошибка при удалении позиций: " . $del1->error);
    }
    
    // Затем удаляем заголовок возврата
    $del2 = $conn->prepare("DELETE FROM PCRM_ReturnHeader WHERE id=?");
    $del2->bind_param("i", $id);
    $del2->execute();
    if ($del2->error) {
        throw new Exception("Ошибка при удалении заголовка: " . $del2->error);
    }
    
    // Завершаем транзакцию
    $conn->commit();
    echo "OK";
} catch (Exception $e) {
    // Откатываем транзакцию в случае ошибки
    $conn->rollback();
    die($e->getMessage());
}