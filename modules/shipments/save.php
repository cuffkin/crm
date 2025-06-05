<?php
// /crm/modules/shipments/save.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'shipments')) {
    die(json_encode(["status" => "error", "message" => "Нет доступа"]));
}

// Получаем данные заголовка отгрузки
$id = (int)($_POST['id'] ?? 0);
$shipment_number = trim($_POST['shipment_number'] ?? '');
$shipment_date = $_POST['shipment_date'] ?? date('Y-m-d H:i:s');
$order_id = (int)($_POST['order_id'] ?? 0);
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
if ($order_id <= 0) {
    die(json_encode(["status" => "error", "message" => "Не выбран заказ"]));
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
    // UPDATE или INSERT заголовка отгрузки
    if ($id > 0) {
        $sql = "
            UPDATE PCRM_ShipmentHeader
            SET shipment_number=?, shipment_date=?, order_id=?, warehouse_id=?,
                loader_id=?, status=?, conducted=?, comment=?
            WHERE id=?
        ";
        $st = $conn->prepare($sql);
        $st->bind_param("ssiisissi",
            $shipment_number, $shipment_date, $order_id, $warehouse_id,
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
            INSERT INTO PCRM_ShipmentHeader
            (shipment_number, shipment_date, order_id, warehouse_id,
             loader_id, status, conducted, comment, created_by, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,NOW())
        ";
        $st = $conn->prepare($sql);
        $st->bind_param("ssiisissi",
            $shipment_number, $shipment_date, $order_id, $warehouse_id,
            $loader_id, $status, $conducted, $comment,
            $created_by
        );
        $st->execute();
        if ($st->error) {
            throw new Exception("Ошибка при INSERT заголовка: " . $st->error);
        }
        $id = $st->insert_id;
    }

    // Удаляем старые позиции отгрузки
    $del = $conn->prepare("DELETE FROM PCRM_ShipmentItem WHERE shipment_header_id=?");
    $del->bind_param("i", $id);
    $del->execute();
    if ($del->error) {
        throw new Exception("Ошибка при удалении позиций: " . $del->error);
    }

    // Вставляем новые позиции отгрузки
    $ins = $conn->prepare("
        INSERT INTO PCRM_ShipmentItem 
        (shipment_header_id, product_id, quantity, price, discount, created_at, updated_at)
        VALUES (?,?,?,?,?,NOW(),NOW())
    ");
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
    }
    
    // Создаем связь с заказом, если создается новая отгрузка
    if ($order_id > 0 && ($based_on === 'order' || $id === 0)) {
        // Проверяем, существует ли уже связь
        $checkRelSql = "SELECT id FROM PCRM_RelatedDocuments 
                        WHERE (source_type='order' AND source_id=? AND related_type='shipment' AND related_id=?)
                        OR (source_type='shipment' AND source_id=? AND related_type='order' AND related_id=?)";
        $checkRelStmt = $conn->prepare($checkRelSql);
        $checkRelStmt->bind_param("iiii", $order_id, $id, $id, $order_id);
        $checkRelStmt->execute();
        $checkRelResult = $checkRelStmt->get_result();
        
        if ($checkRelResult->num_rows == 0) {
            // Создаем связь между заказом и отгрузкой
            $relSql = "INSERT INTO PCRM_RelatedDocuments 
                      (source_type, source_id, related_type, related_id, relation_type, created_at) 
                      VALUES ('order', ?, 'shipment', ?, 'created_from', NOW())";
            $relStmt = $conn->prepare($relSql);
            $relStmt->bind_param("ii", $order_id, $id);
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
        "shipment_id" => $id
    ]);
} catch (Exception $e) {
    // Откатываем транзакцию в случае ошибки
    $conn->rollback();
    die(json_encode(["status" => "error", "message" => $e->getMessage()]));
}