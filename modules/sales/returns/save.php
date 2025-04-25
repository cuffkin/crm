<?php
// /crm/modules/sales/returns/save.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'sales_returns')) {
    die(json_encode(["status" => "error", "message" => "Нет доступа"]));
}

// Получаем данные заголовка возврата
$id = (int)($_POST['id'] ?? 0);
$return_number = trim($_POST['return_number'] ?? '');
$return_date = $_POST['return_date'] ?? date('Y-m-d H:i:s');
$order_id = (int)($_POST['order_id'] ?? 0);
$warehouse_id = (int)($_POST['warehouse_id'] ?? 0);
$loader_id = (int)($_POST['loader_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$status = trim($_POST['status'] ?? 'new');
$conducted = (int)($_POST['conducted'] ?? 0);
$create_rko = (int)($_POST['create_rko'] ?? 0);
$based_on = trim($_POST['based_on'] ?? '');

// Получаем товары
$itemsJson = $_POST['items'] ?? '[]';
$itemsArr = json_decode($itemsJson, true);
if (!is_array($itemsArr)) {
    $itemsArr = [];
}

// Валидация
if (empty($return_number)) {
    $nextIdRes = $conn->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'PCRM_ReturnHeader'");
    $nextId = $nextIdRes->fetch_row()[0] ?? 1;
    $return_number = 'RET-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
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
            UPDATE PCRM_ReturnHeader
            SET return_number=?, return_date=?, order_id=?, warehouse_id=?,
                loader_id=?, reason=?, notes=?, status=?, conducted=?
            WHERE id=?
        ";
        $st = $conn->prepare($sql);
        $st->bind_param("ssiisssii",
            $return_number, $return_date, $order_id, $warehouse_id,
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
            INSERT INTO PCRM_ReturnHeader
            (return_number, return_date, order_id, warehouse_id,
             loader_id, reason, notes, status, conducted, created_by, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,NOW())
        ";
        $st = $conn->prepare($sql);
        $st->bind_param("ssiisssiii",
            $return_number, $return_date, $order_id, $warehouse_id,
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
    $del = $conn->prepare("DELETE FROM PCRM_ReturnItem WHERE return_id=?");
    $del->bind_param("i", $id);
    $del->execute();
    if ($del->error) {
        throw new Exception("Ошибка при удалении позиций: " . $del->error);
    }

    // Вставляем новые позиции возврата
    $ins = $conn->prepare("
        INSERT INTO PCRM_ReturnItem 
        (return_id, product_id, quantity, price, discount, created_at)
        VALUES (?,?,?,?,?,NOW())
    ");
    
    $total_amount = 0;
    
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
        
        $total_amount += ($qty * $prc) - $dsc;
    }
    
    // Если нужно создать связь с заказом
    if ($order_id > 0 && ($based_on === 'order' || $id === 0)) {
        // Проверяем, существует ли уже связь
        $checkRelSql = "SELECT id FROM PCRM_RelatedDocuments 
                        WHERE (source_type='order' AND source_id=? AND related_type='return' AND related_id=?) 
                        OR (source_type='return' AND source_id=? AND related_type='order' AND related_id=?)";
        $checkRelStmt = $conn->prepare($checkRelSql);
        $checkRelStmt->bind_param("iiii", $order_id, $id, $id, $order_id);
        $checkRelStmt->execute();
        $checkRelResult = $checkRelStmt->get_result();
        
        if ($checkRelResult->num_rows == 0) {
            // Создаем связь между заказом и возвратом
            $relSql = "INSERT INTO PCRM_RelatedDocuments 
                      (source_type, source_id, related_type, related_id, relation_type, created_at) 
                      VALUES ('order', ?, 'return', ?, 'created_from', NOW())";
            $relStmt = $conn->prepare($relSql);
            $relStmt->bind_param("ii", $order_id, $id);
            $relStmt->execute();
            if ($relStmt->error) {
                throw new Exception("Ошибка при создании связи с заказом: " . $relStmt->error);
            }
        }
    }
    
    // Если нужно создать РКО
$rko_id = 0;
if ($create_rko && $conducted) {
    // Получаем дополнительную информацию
    $counterparty_id = null;
    if ($order_id > 0) {
        $custStmt = $conn->prepare("SELECT customer FROM PCRM_Order WHERE id=?");
        $custStmt->bind_param("i", $order_id);
        $custStmt->execute();
        $custResult = $custStmt->get_result();
        if ($custRow = $custResult->fetch_assoc()) {
            $counterparty_id = $custRow['customer'];
        }
    }
    
    // Получаем кассу по умолчанию
    $cashRegId = null;
    $cashRegQuery = $conn->query("SELECT id FROM PCRM_CashRegister WHERE status='active' ORDER BY id LIMIT 1");
    if ($cashRegRow = $cashRegQuery->fetch_assoc()) {
        $cashRegId = $cashRegRow['id'];
    }
    
    if ($counterparty_id && $cashRegId) {
        // Генерируем номер РКО
        $rkoNumQuery = $conn->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'PCRM_FinancialTransaction'");
        $rkoNextId = $rkoNumQuery->fetch_row()[0] ?? 1;
        $rko_number = 'OUT-' . str_pad($rkoNextId, 6, '0', STR_PAD_LEFT);
        
        $rkoDescription = "Возврат средств по возврату №{$return_number}" . ($order_id ? " (Заказ №{$order_id})" : "");
        
        // Создаем РКО
        $rkoSql = "
            INSERT INTO PCRM_FinancialTransaction
            (transaction_number, transaction_date, transaction_type,
             amount, counterparty_id, cash_register_id, 
             payment_method, description, conducted, user_id, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,NOW())
        ";
        $rkoStmt = $conn->prepare($rkoSql);
        $rkoType = 'expense';
        $rkoPayMethod = 'cash';
        $rkoConducted = 1;
        $rkoUserId = $_SESSION['user_id'] ?? null;
        
        $rkoStmt->bind_param("sssdissiii",
            $rko_number, $return_date, $rkoType,
            $total_amount, $counterparty_id, $cashRegId,
            $rkoPayMethod, $rkoDescription, $rkoConducted, $rkoUserId
        );
        $rkoStmt->execute();
        if ($rkoStmt->error) {
            throw new Exception("Ошибка при создании РКО: " . $rkoStmt->error);
        }
        $rko_id = $rkoStmt->insert_id;
        
        // Создаем двустороннюю связь между возвратом и РКО
        if ($rko_id > 0) {
            // 1. Связь от возврата к РКО
            $relSql = "INSERT INTO PCRM_RelatedDocuments 
                      (source_type, source_id, related_type, related_id, relation_type, created_at) 
                      VALUES (?, ?, ?, ?, ?, NOW())";
            $relStmt = $conn->prepare($relSql);
            $sourceType = 'return';
            $relatedType = 'finance';
            $relationType = 'created_to';
            $relStmt->bind_param("siiss", $sourceType, $id, $relatedType, $rko_id, $relationType);
            $relStmt->execute();
            if ($relStmt->error) {
                throw new Exception("Ошибка при создании связи с РКО: " . $relStmt->error);
            }
            
            // 2. Обратная связь от РКО к возврату
            $relSql2 = "INSERT INTO PCRM_RelatedDocuments 
                       (source_type, source_id, related_type, related_id, relation_type, created_at) 
                       VALUES (?, ?, ?, ?, ?, NOW())";
            $relStmt2 = $conn->prepare($relSql2);
            $sourceType2 = 'finance';
            $relatedType2 = 'return';
            $relationType2 = 'created_from';
            $relStmt2->bind_param("siiss", $sourceType2, $rko_id, $relatedType2, $id, $relationType2);
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
        "rko_created" => ($rko_id > 0)
    ]);
} catch (Exception $e) {
    // Откатываем транзакцию в случае ошибки
    $conn->rollback();
    die(json_encode(["status" => "error", "message" => $e->getMessage()]));
}