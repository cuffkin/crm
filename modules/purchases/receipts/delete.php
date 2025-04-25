<?php
// /crm/modules/purchases/receipts/delete.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'purchases_receipts')) {
    die("Нет доступа");
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Некорректный ID приёмки");
}

// Начинаем транзакцию
$conn->begin_transaction();

try {
    // Проверяем, проведена ли приёмка
    $checkConducted = $conn->prepare("SELECT conducted FROM PCRM_ReceiptHeader WHERE id = ?");
    $checkConducted->bind_param("i", $id);
    $checkConducted->execute();
    $result = $checkConducted->get_result();
    $row = $result->fetch_assoc();
    $conducted = $row['conducted'] ?? 0;
    
    // Если приёмка проведена, нужно вернуть товары на склад
    if ($conducted == 1) {
        // Получаем информацию о приёмке
        $getReceiptInfo = $conn->prepare("SELECT warehouse_id FROM PCRM_ReceiptHeader WHERE id = ?");
        $getReceiptInfo->bind_param("i", $id);
        $getReceiptInfo->execute();
        $receiptInfoResult = $getReceiptInfo->get_result();
        $receiptInfo = $receiptInfoResult->fetch_assoc();
        $warehouseId = $receiptInfo['warehouse_id'];
        
        // Получаем товары приёмки
        $getReceiptItems = $conn->prepare("
            SELECT product_id, quantity FROM PCRM_ReceiptItem WHERE receipt_header_id = ?
        ");
        $getReceiptItems->bind_param("i", $id);
        $getReceiptItems->execute();
        $receiptItemsResult = $getReceiptItems->get_result();
        $receiptItems = $receiptItemsResult->fetch_all(MYSQLI_ASSOC);
        
        // Уменьшаем количество товаров на складе
        $updateStock = $conn->prepare("
            UPDATE PCRM_Stock 
            SET quantity = GREATEST(0, quantity - ?) 
            WHERE prod_id = ? AND warehouse = ?
        ");
        
        foreach ($receiptItems as $item) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'];
            
            $updateStock->bind_param("dii", $quantity, $productId, $warehouseId);
            $updateStock->execute();
            if ($updateStock->error) {
                throw new Exception("Ошибка при обновлении остатков: " . $updateStock->error);
            }
        }
    }
    
    // Сначала удаляем связи с другими документами
    $delRel = $conn->prepare("DELETE FROM PCRM_RelatedDocuments WHERE (source_type='receipt' AND source_id=?) OR (related_type='receipt' AND related_id=?)");
    $delRel->bind_param("ii", $id, $id);
    $delRel->execute();
    
    // Затем удаляем позиции приёмки
    $del1 = $conn->prepare("DELETE FROM PCRM_ReceiptItem WHERE receipt_header_id=?");
    $del1->bind_param("i", $id);
    $del1->execute();
    if ($del1->error) {
        throw new Exception("Ошибка при удалении позиций: " . $del1->error);
    }
    
    // Затем удаляем заголовок приёмки
    $del2 = $conn->prepare("DELETE FROM PCRM_ReceiptHeader WHERE id=?");
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