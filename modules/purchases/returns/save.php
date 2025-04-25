<?php
// /crm/modules/purchases/returns/save.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'purchases_returns')) {
    die(json_encode(["status" => "error", "message" => "Нет доступа"]));
}

// Получаем данные заголовка возврата
$id = (int)($_POST['id'] ?? 0);
$return_number = trim($_POST['return_number'] ?? '');
$return_date = $_POST['return_date'] ?? date('Y-m-d H:i:s');
$purchase_order_id = (int)($_POST['purchase_order_id'] ?? 0);
$receipt_id = (int)($_POST['receipt_id'] ?? 0);
$warehouse_id = (int)($_POST['warehouse_id'] ?? 0);
$loader_id = (int)($_POST['loader_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$status = trim($_POST['status'] ?? 'new');
$conducted = (int)($_POST['conducted'] ?? 0);
$create_pko = (int)($_POST['create_pko'] ?? 0);
$based_on = trim($_POST['based_on'] ?? '');

// Получаем товары
$itemsJson = $_POST['items'] ?? '[]';
$itemsArr = json_decode($itemsJson, true);
if (!is_array($itemsArr)) {
    $itemsArr = [];
}

// Валидация
if (empty($return_number)) {
    $nextIdRes = $conn->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'PCRM_SupplierReturnHeader'");
    $nextId = $nextIdRes->fetch_row()[0] ?? 1;
    $return_number = 'SRET-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
}
if ($warehouse_id <= 0) {
    die(json_encode(["status" => "error", "message" => "Не выбран склад"]));
}
if ($loader_id <= 0) {
    die(json_encode(["status" => "error", "message" => "Не выбран грузчик"]));
}
if (empty($reason)) {
    die(json_encode(["status" => "error", "message" => "Не выбрана причина возврата"]));
}
if ($reason === 'Другое' && empty($notes)) {
    die(json_encode(["status" => "error", "message" => "При выборе причины 'Другое' необходимо заполнить примечания"]));
}
if (empty($itemsArr)) {
    die(json_encode(["status" => "error", "message" => "Добавьте хотя бы один товар"]));
}

// Начинаем транзакцию
$conn->begin_transaction();

try {
    // UPDATE или INSERT заголовка возврата
    if ($id > 0) {
        $sql = "
            UPDATE PCRM_SupplierReturnHeader
            SET return_number=?, return_date=?, purchase_order_id=?, warehouse_id=?,
                loader_id=?, reason=?, notes=?, status=?, conducted=?
            WHERE id=?
        ";
        $st = $conn->prepare($sql);
        $st->bind_param("ssiiisissii",
            $return_number, $return_date, $purchase_order_id, $warehouse_id,
            $loader_id, $reason, $notes, $status, $conducted,
            $id
        );
        $st->execute();
        if ($st->error) {
            throw new Exception("Ошибка при UPDATE заголовка: " . $st->error);
        }
    } else {
        $created_by = $_SESSION['user_id'] ?? null;
        $sql = "
            INSERT INTO PCRM_SupplierReturnHeader
            (return_number, return_date, purchase_order_id, warehouse_id,
             loader_id, reason, notes, status, conducted, created_by, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,NOW())
        ";
        $st = $conn->prepare($sql);
        $st->bind_param("ssiiisissii",
            $return_number, $return_date, $purchase_order_id, $warehouse_id,
            $loader_id, $reason, $notes, $status, $conducted,
            $created_by
        );
        $st->execute();
        if ($st->error) {
            throw new Exception("Ошибка при INSERT заголовка: " . $st->error);
        }
        $id = $st->insert_id;
    }

    // Удаляем старые позиции возврата
    $del = $conn->prepare("DELETE FROM PCRM_SupplierReturnItem WHERE return_id=?");
    $del->bind_param("i", $id);
    $del->execute();
    if ($del->error) {
        throw new Exception("Ошибка при удалении позиций: " . $del->error);
    }

    // Вставляем новые позиции возврата
    $ins = $conn->prepare("
        INSERT INTO PCRM_SupplierReturnItem 
        (return_id, product_id, quantity, price, discount, created_at)
        VALUES (?,?,?,?,?,NOW())
    ");
    
    $total_amount = 0;
    
    // Если проведено, обновляем остатки на складе
    if ($conducted) {
        // Подготовим запрос на уменьшение остатков на складе
        $updateStock = $conn->prepare("
            UPDATE PCRM_Stock 
            SET quantity = GREATEST(0, quantity - ?) 
            WHERE prod_id = ? AND warehouse = ?
        ");
    }
    
    foreach ($itemsArr as $itm) {
        $pid = (int)$itm['product_id'];
        $qty = (float)$itm['quantity'];
        $prc = (float)$itm['price'];
        $dsc = (float)($itm['discount'] ?? 0);
        
        if ($pid <= 0 || $qty <= 0) {
            continue;
        }
        
        $ins->bind_param("iiddd", 
            $id, $pid, $qty, $prc, $dsc
        );
        $ins->execute();
        if ($ins->error) {
            throw new Exception("Ошибка при INSERT позиции: " . $ins->error);
        }
        
        // Если проведено, уменьшаем остатки на складе
        if ($conducted) {
            $updateStock->bind_param("dii", $qty, $pid, $warehouse_id);
            $updateStock->execute();
            if ($updateStock->error) {
                throw new Exception("Ошибка при обновлении остатков: " . $updateStock->error);
            }
        }
        
        $total_amount += ($qty * $prc) - $dsc;
    }
    
    // Создаем связи с другими документами
    if ($purchase_order_id > 0 && ($based_on === 'purchase_order' || $id === 0)) {
        // Проверяем, существует ли уже связь с заказом поставщику
        $checkRelSql = "SELECT id FROM PCRM_RelatedDocuments 
                        WHERE (source_type='purchase_order' AND source_id=? AND related_type='supplier_return' AND related_id=?)
                        OR (source_type='supplier_return' AND source_id=? AND related_type='purchase_order' AND related_id=?)";
        $checkRelStmt = $conn->prepare($checkRelSql);
        $checkRelStmt->bind_param("iiii", $purchase_order_id, $id, $id, $purchase_order_id);
        $checkRelStmt->execute();
        $checkRelResult = $checkRelStmt->get_result();
        
        if ($checkRelResult->num_rows == 0) {
            // Создаем связь между заказом поставщику и возвратом
            $relSql = "INSERT INTO PCRM_RelatedDocuments 
                      (source_type, source_id, related_type, related_id, relation_type, created_at) 
                      VALUES ('purchase_order', ?, 'supplier_return', ?, 'created_from', NOW())";
            $relStmt = $conn->prepare($relSql);
            $relStmt->bind_param("ii", $purchase_order_id, $id);
            $relStmt->execute();
            if ($relStmt->error) {
                throw new Exception("Ошибка при создании связи с заказом: " . $relStmt->error);
            }
        }
    }
    
    if ($receipt_id > 0 && ($based_on === 'receipt' || $id === 0)) {
        // Проверяем, существует ли уже связь с приёмкой
        $checkRelSql = "SELECT id FROM PCRM_RelatedDocuments 
                        WHERE (source_type='receipt' AND source_id=? AND related_type='supplier_return' AND related_id=?)
                        OR (source_type='supplier_return' AND source_id=? AND related_type='receipt' AND related_id=?)";
        $checkRelStmt = $conn->prepare($checkRelSql);
        $checkRelStmt->bind_param("iiii", $receipt_id, $id, $id, $receipt_id);
        $checkRelStmt->execute();
        $checkRelResult = $checkRelStmt->get_result();
        
        if ($checkRelResult->num_rows == 0) {
            // Создаем связь между приёмкой и возвратом
            $relSql = "INSERT INTO PCRM_RelatedDocuments 
                      (source_type, source_id, related_type, related_id, relation_type, created_at) 
                      VALUES ('receipt', ?, 'supplier_return', ?, 'created_from', NOW())";
            $relStmt = $conn->prepare($relSql);
            $relStmt->bind_param("ii", $receipt_id, $id);
            $relStmt->execute();
            if ($relStmt->error) {
                throw new Exception("Ошибка при создании связи с приёмкой: " . $relStmt->error);
            }
        }
    }
    
    // Если нужно создать ПКО
    $pko_id = 0;
    if ($create_pko && $conducted) {
        // Получаем дополнительную информацию
        $supplier_id = null;
        if ($purchase_order_id > 0) {
            $suppStmt = $conn->prepare("SELECT supplier_id FROM PCRM_PurchaseOrder WHERE id=?");
            $suppStmt->bind_param("i", $purchase_order_id);
            $suppStmt->execute();
            $suppResult = $suppStmt->get_result();
            if ($suppRow = $suppResult->fetch_assoc()) {
                $supplier_id = $suppRow['supplier_id'];
            }
        }
        
        // Получаем кассу по умолчанию
        $cashRegId = null;
        $cashRegQuery = $conn->query("SELECT id FROM PCRM_CashRegister WHERE status='active' ORDER BY id LIMIT 1");
        if ($cashRegRow = $cashRegQuery->fetch_assoc()) {
            $cashRegId = $cashRegRow['id'];
        }
        
        if ($supplier_id && $cashRegId) {
            // Генерируем номер ПКО
            $pkoNumQuery = $conn->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'PCRM_FinancialTransaction'");
            $pkoNextId = $pkoNumQuery->fetch_row()[0] ?? 1;
            $pko_number = 'IN-' . str_pad($pkoNextId, 6, '0', STR_PAD_LEFT);
            
            $pkoDescription = "Возврат средств по возврату поставщику №{$return_number}" . ($purchase_order_id ? " (Заказ поставщику №{$purchase_order_id})" : "");
            
            // Создаем ПКО
            $pkoSql = "
                INSERT INTO PCRM_FinancialTransaction
                (transaction_number, transaction_date, transaction_type,
                 amount, counterparty_id, cash_register_id, 
                 payment_method, description, conducted, user_id, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,NOW())
            ";
            $pkoStmt = $conn->prepare($pkoSql);
            $pkoType = 'income';
            $pkoPayMethod = 'cash';
            $pkoConducted = 1;
            $pkoUserId = $_SESSION['user_id'] ?? null;
            
            $pkoStmt->bind_param("sssdissiii",
                $pko_number, $return_date, $pkoType,
                $total_amount, $supplier_id, $cashRegId,
                $pkoPayMethod, $pkoDescription, $pkoConducted, $pkoUserId
            );
            $pkoStmt->execute();
            if ($pkoStmt->error) {
                throw new Exception("Ошибка при создании ПКО: " . $pkoStmt->error);
            }
            $pko_id = $pkoStmt->insert_id;
            
            // Создаем двустороннюю связь между возвратом и ПКО
            if ($pko_id > 0) {
                // 1. Связь от возврата к ПКО
                $relSql = "INSERT INTO PCRM_RelatedDocuments 
                          (source_type, source_id, related_type, related_id, relation_type, created_at) 
                          VALUES (?, ?, ?, ?, ?, NOW())";
                $relStmt = $conn->prepare($relSql);
                $sourceType = 'supplier_return';
                $relatedType = 'finance';
                $relationType = 'created_to';
                $relStmt->bind_param("siiss", $sourceType, $id, $relatedType, $pko_id, $relationType);
                $relStmt->execute();
                if ($relStmt->error) {
                    throw new Exception("Ошибка при создании связи с ПКО: " . $relStmt->error);
                }
                
                // 2. Обратная связь от ПКО к возврату
                $relSql2 = "INSERT INTO PCRM_RelatedDocuments 
                           (source_type, source_id, related_type, related_id, relation_type, created_at) 
                           VALUES (?, ?, ?, ?, ?, NOW())";
                $relStmt2 = $conn->prepare($relSql2);
                $sourceType2 = 'finance';
                $relatedType2 = 'supplier_return';
                $relationType2 = 'created_from';
                $relStmt2->bind_param("siiss", $sourceType2, $pko_id, $relatedType2, $id, $relationType2);
                $relStmt2->execute();
                if ($relStmt2->error) {
                    throw new Exception("Ошибка при создании обратной связи: " . $relStmt2->error);
                }
            }
        }
    }

    // Завершаем транзакцию
    $conn->commit();
    echo json_encode([
        "status" => "ok", 
        "return_id" => $id, 
        "pko_created" => ($pko_id > 0)
    ]);
} catch (Exception $e) {
    // Откатываем транзакцию в случае ошибки
    $conn->rollback();
    die(json_encode(["status" => "error", "message" => $e->getMessage()]));
}