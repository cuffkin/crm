<?php
// /crm/modules/finances/delete.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'finances')) {
    die("Нет доступа");
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Некорректный ID финансовой операции");
}

// Начинаем транзакцию
$conn->begin_transaction();

try {
    // Удаляем сначала детали гибридных платежей (если есть)
    $del1 = $conn->prepare("DELETE FROM PCRM_PaymentMethodDetails WHERE transaction_id=?");
    $del1->bind_param("i", $id);
    $del1->execute();
    if ($del1->error) {
        throw new Exception("Ошибка при удалении деталей платежа: " . $del1->error);
    }
    
    // Затем удаляем саму транзакцию
    $del2 = $conn->prepare("DELETE FROM PCRM_FinancialTransaction WHERE id=?");
    $del2->bind_param("i", $id);
    $del2->execute();
    if ($del2->error) {
        throw new Exception("Ошибка при удалении транзакции: " . $del2->error);
    }
    
    // Завершаем транзакцию
    $conn->commit();
    echo "OK";
} catch (Exception $e) {
    // Откатываем транзакцию в случае ошибки
    $conn->rollback();
    die($e->getMessage());
}