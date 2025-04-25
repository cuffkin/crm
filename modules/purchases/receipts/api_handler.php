<?php
// /crm/modules/purchases/receipts/api_handler.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'purchases_receipts')) {
    die(json_encode(["status" => "error", "message" => "Нет доступа"]));
}

// Определяем тип запроса
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'get_receipt_info':
        getReceiptInfo();
        break;
    case 'get_last_receipt_id':
        getLastReceiptId();
        break;
    case 'get_receipt_items':
        getReceiptItems();
        break;
    default:
        // Если действие не указано, но передан ID, предполагаем get_receipt_info
        if (isset($_GET['id']) && !empty($_GET['id'])) {
            getReceiptInfo();
        } else {
            echo json_encode(["status" => "error", "message" => "Неизвестное действие"]);
        }
}

/**
 * Получает информацию о приёмке по ID
 */
function getReceiptInfo() {
    global $conn;
    
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        die(json_encode(["status" => "error", "message" => "Некорректный ID приёмки"]));
    }
    
    // Получаем информацию о приёмке
    $sql = "
        SELECT rh.*, 
               po.purchase_order_number,
               w.name AS warehouse_name,
               l.name AS loader_name,
               (SELECT SUM((ri.quantity * ri.price) - ri.discount) 
                FROM PCRM_ReceiptItem ri 
                WHERE ri.receipt_header_id = rh.id) AS total_amount
        FROM PCRM_ReceiptHeader rh
        LEFT JOIN PCRM_PurchaseOrder po ON rh.purchase_order_id = po.id
        LEFT JOIN PCRM_Warehouse w ON rh.warehouse_id = w.id
        LEFT JOIN PCRM_Loaders l ON rh.loader_id = l.id
        WHERE rh.id = ?
    ";
    $st = $conn->prepare($sql);
    $st->bind_param("i", $id);
    $st->execute();
    $result = $st->get_result();
    
    if ($result->num_rows === 0) {
        die(json_encode(["status" => "error", "message" => "Приёмка не найдена"]));
    }
    
    $receiptInfo = $result->fetch_assoc();
    
    // Возвращаем данные приёмки
    echo json_encode(["status" => "ok", "data" => $receiptInfo]);
}

/**
 * Получает ID последней созданной приёмки
 */
function getLastReceiptId() {
    global $conn;
    
    $res = $conn->query("SELECT id FROM PCRM_ReceiptHeader ORDER BY id DESC LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo $row['id'];
    } else {
        echo "0";
    }
}

/**
 * Получает товары приёмки по ID
 */
function getReceiptItems() {
    global $conn;
    
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        die(json_encode(["status" => "error", "message" => "Некорректный ID приёмки"]));
    }
    
    // Получаем товары приёмки
    $sql = "
        SELECT ri.*, p.name AS product_name
        FROM PCRM_ReceiptItem ri
        LEFT JOIN PCRM_Product p ON ri.product_id = p.id
        WHERE ri.receipt_header_id = ?
        ORDER BY ri.id ASC
    ";
    $st = $conn->prepare($sql);
    $st->bind_param("i", $id);
    $st->execute();
    $result = $st->get_result();
    
    $items = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        "status" => "ok", 
        "items" => $items,
        "count" => count($items)
    ]);
}