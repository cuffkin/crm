<?php
// /crm/modules/purchases/receipts/save.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'purchases_receipts')) {
    die(json_encode(["status" => "error", "message" => "Нет доступа"]));
}

// Получаем данные заголовка приёмки
$id = (int)($_POST['id'] ?? 0);
$receipt_number = trim($_POST['receipt_number'] ?? '');
$receipt_date = $_POST['receipt_date'] ?? date('Y-m-d H:i:s');
$purchase_order_id = (int)($_POST['purchase_order_id'] ?? 0);
$warehouse_id = (int)($_POST['warehouse_id'] ?? 0);
$loader_id = $_POST['loader_id'] ? (int)$_POST['loader_id'] : null;
$status = trim($_POST['status'] ?? 'new');
$conducted = (int)($_POST['conducted'] ?? 0);
$comment = trim($_POST['comment'] ?? '');
$based_on = trim($_POST['based_on'] ?? '');

// Получаем товары
$itemsJson = $_POST['items'] ?? '[]';
$itemsArr = json_decode($itemsJson, true);
if (!is_array($itemsArr)) {
    $itemsArr = [];
}

// Валидация
if ($purchase_order_id <= 0) {
    die(json_encode(["status" => "error", "message" => "Не выбран заказ поставщику"]));
}
if ($warehouse_id <= 0) {
    die(json_encode(["status" => "error", "message" => "Не выбран склад"]));
}
if (empty($itemsArr)) {
    die(json_encode(["status" => "error", "message" => "Добавьте хотя бы один товар"]));
}

// Начинаем транзакцию
$conn->begin_transaction();

try {
    // UPDATE или INSERT заголовка приёмки
    if ($id > 0) {
        $sql = "
            UPDATE PCRM_ReceiptHeader
            SET receipt_number=?, receipt_date=?, purchase_order_id=?, warehouse_id=?,
                loader_id=?, status=?, conducted=?, comment=?
            WHERE id=?
        ";
        $st = $conn->prepare($sql);
        $st->bind_param("ssiisissi",
            $receipt_number, $receipt_date, $purchase_order_id, $warehouse_id,
            $loader_id, $status, $conducted, $comment,
            $id
        );
        $st->execute();
        if ($st->error) {
            throw new Exception("Ошибка при UPDATE заголовка: " . $st->error);
        }
    } else {
        $created_by = $_SESSION['user_id'] ?? null;
        $sql = "
            INSERT INTO PCRM_ReceiptHeader
            (receipt_number, receipt_date, purchase_order_id, warehouse_id,
             loader_id, status, conducted, comment, created_by, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,NOW())
        ";
        $st = $conn->prepare($sql);
        $st->bind_param("ssiisissi",
            $receipt_number, $receipt_date, $purchase_order_id, $warehouse_id,
            $loader_id, $status, $conducted, $comment,
            $created_by
        );
        $st->execute();
        if ($st->error) {
            throw new Exception("Ошибка при INSERT заголовка: " . $st->error);
        }
        $id = $st->insert_id;
    }

    // Удаляем старые позиции приёмки
    $del = $conn->prepare("DELETE FROM PCRM_ReceiptItem WHERE receipt_header_id=?");
    $del->bind_param("i", $id);
    $del->execute();
    if ($del->error) {
        throw new Exception("Ошибка при удалении позиций: " . $del->error);
    }

    // Вставляем новые позиции приёмки
    $ins = $conn->prepare("
        INSERT INTO PCRM_ReceiptItem 
        (receipt_header_id, product_id, quantity, price, discount, created_at, updated_at)
        VALUES (?,?,?,?,?,NOW(),NOW())
    ");

    // Если проведено, обновляем остатки на складе
    if ($conducted) {
        // Подготовим запрос на обновление или вставку остатков на складе
        $updateStock = $conn->prepare("
            INSERT INTO PCRM_Stock (prod_id, warehouse, quantity) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
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
        
        // Если проведено, обновляем остатки на складе
        if ($conducted) {
            $updateStock->bind_param("iid", $pid, $warehouse_id, $qty);
            $updateStock->execute();
            if ($updateStock->error) {
                throw new Exception("Ошибка при обновлении остатков: " . $updateStock->error);
            }
        }
    }
    
    // Создаем связь с заказом поставщику, если создается новая приёмка
    if ($purchase_order_id > 0 && ($based_on === 'purchase_order' || $id === 0)) {
        // Проверяем, существует ли уже связь
        $checkRelSql = "SELECT id FROM PCRM_RelatedDocuments 
                        WHERE (source_type='purchase_order' AND source_id=? AND related_type='receipt' AND related_id=?)
                        OR (source_type='receipt' AND source_id=? AND related_type='purchase_order' AND related_id=?)";
        $checkRelStmt = $conn->prepare($checkRelSql);
        $checkRelStmt->bind_param("iiii", $purchase_order_id, $id, $id, $purchase_order_id);
        $checkRelStmt->execute();
        $checkRelResult = $checkRelStmt->get_result();
        
        if ($checkRelResult->num_rows == 0) {
            // Создаем связь между заказом поставщику и приёмкой
            $relSql = "INSERT INTO PCRM_RelatedDocuments 
                      (source_type, source_id, related_type, related_id, relation_type, created_at) 
                      VALUES ('purchase_order', ?, 'receipt', ?, 'created_from', NOW())";
            $relStmt = $conn->prepare($relSql);
            $relStmt->bind_param("ii", $purchase_order_id, $id);
            $relStmt->execute();
            if ($relStmt->error) {
                throw new Exception("Ошибка при создании связи с заказом: " . $relStmt->error);
            }
        }
    }

    // Завершаем транзакцию
    $conn->commit();
    echo json_encode([
        "status" => "ok", 
        "receipt_id" => $id
    ]);
} catch (Exception $e) {
    // Откатываем транзакцию в случае ошибки
    $conn->rollback();
    die(json_encode(["status" => "error", "message" => $e->getMessage()]));
}