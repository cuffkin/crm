<?php
// /crm/modules/purchases/returns/delete.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'purchases_returns')) {
    die("Нет доступа");
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Некорректный ID возврата");
}

// Начинаем транзакцию
$conn->begin_transaction();

try {
    // Проверяем, проведен ли возврат
    $checkConducted = $conn->prepare("SELECT conducted FROM PCRM_SupplierReturnHeader WHERE id = ?");
    $checkConducted->bind_param("i", $id);
    $checkConducted->execute();
    $result = $checkConducted->get_result();
    $row = $result->fetch_assoc();
    $conducted = $row['conducted'] ?? 0;
    
    // Если возврат проведен, нужно вернуть товары на склад
    if ($conducted == 1) {
        // Получаем информацию о возврате
        $getReturnInfo = $conn->prepare("SELECT warehouse_id FROM PCRM_SupplierReturnHeader WHERE id = ?");
        $getReturnInfo->bind_param("i", $id);
        $getReturnInfo->execute();
        $returnInfoResult = $getReturnInfo->get_result();
        $returnInfo = $returnInfoResult->fetch_assoc();
        $warehouseId = $returnInfo['warehouse_id'];
        
        // Получаем товары возврата
        $getReturnItems = $conn->prepare("
            SELECT product_id, quantity FROM PCRM_SupplierReturnItem WHERE return_id = ?
        ");
        $getReturnItems->bind_param("i", $id);
        $getReturnItems->execute();
        $returnItemsResult = $getReturnItems->get_result();
        $returnItems = $returnItemsResult->fetch_all(MYSQLI_ASSOC);
        
        // Возвращаем товары на склад
        $updateStock = $conn->prepare("
            INSERT INTO PCRM_Stock (prod_id, warehouse, quantity) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
        ");
        
        foreach ($returnItems as $item) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'];
            
            $updateStock->bind_param("iid", $productId, $warehouseId, $quantity);
            $updateStock->execute();
            if ($updateStock->error) {
                throw new Exception("Ошибка при обновлении остатков: " . $updateStock->error);
            }
        }
    }
    
    // Сначала удаляем связи с другими документами
    $delRel = $conn->prepare("DELETE FROM PCRM_RelatedDocuments WHERE (source_type='supplier_return' AND source_id=?) OR (related_type='supplier_return' AND related_id=?)");
    $delRel->bind_param("ii", $id, $id);
    $delRel->execute();
    
    // Затем удаляем позиции возврата
    $del1 = $conn->prepare("DELETE FROM PCRM_SupplierReturnItem WHERE return_id=?");
    $del1->bind_param("i", $id);
    $del1->execute();
    if ($del1->error) {
        throw new Exception("Ошибка при удалении позиций: " . $del1->error);
    }
    
    // Затем удаляем заголовок возврата
    $del2 = $conn->prepare("DELETE FROM PCRM_SupplierReturnHeader WHERE id=?");
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