<?php
// /crm/modules/purchases/orders/order_api.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'purchases_orders')) {
    die(json_encode(["status" => "error", "message" => "Нет доступа"]));
}

// Определяем тип запроса
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'get_order_info':
        getOrderInfo();
        break;
    case 'generate':
        generateOrderNumber();
        break;
    case 'check':
        checkOrderNumber();
        break;
    case 'get_last_id':
        getLastOrderId();
        break;
    case 'get_order_items':
        getOrderItems();
        break;
    default:
        echo json_encode(["status" => "error", "message" => "Неизвестное действие"]);
}

/**
 * Получает информацию о заказе поставщику по ID
 */
function getOrderInfo() {
    global $conn;
    
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        die(json_encode(["status" => "error", "message" => "Некорректный ID заказа"]));
    }

    // Получаем информацию о заказе
    $sql = "
        SELECT po.id, po.purchase_order_number, po.organization, po.supplier_id, po.warehouse_id, 
               po.delivery_address, po.total_amount, po.status, po.conducted,
               org.name AS organization_name,
               c.name AS supplier_name,
               w.name AS warehouse_name,
               SUM((poi.quantity * poi.price) - poi.discount) AS order_sum
        FROM PCRM_PurchaseOrder po
        LEFT JOIN PCRM_Organization org ON po.organization = org.id
        LEFT JOIN PCRM_Counterparty c ON po.supplier_id = c.id
        LEFT JOIN PCRM_Warehouse w ON po.warehouse_id = w.id
        LEFT JOIN PCRM_PurchaseOrderItem poi ON po.id = poi.purchase_order_id
        WHERE po.id = ? AND po.deleted = 0
        GROUP BY po.id
    ";
    $st = $conn->prepare($sql);
    $st->bind_param("i", $id);
    $st->execute();
    $result = $st->get_result();

    if ($result->num_rows === 0) {
        die(json_encode(["status" => "error", "message" => "Заказ не найден"]));
    }

    $orderInfo = $result->fetch_assoc();

    // Возвращаем данные заказа
    echo json_encode(["status" => "ok", "data" => $orderInfo]);
}

/**
 * Генерирует новый номер заказа поставщику
 */
function generateOrderNumber() {
    global $conn;
    
    $r = $conn->query("SELECT id FROM PCRM_PurchaseOrder ORDER BY id DESC LIMIT 1");
    $last = $r->fetch_assoc();
    $newId = $last ? ($last['id'] + 1) : 1;
    $num = "PO-" . str_pad($newId, 6, '0', STR_PAD_LEFT);
    
    echo json_encode(["status" => "ok", "number" => $num]);
}

/**
 * Проверяет уникальность номера заказа поставщику
 */
function checkOrderNumber() {
    global $conn;
    
    $number = trim($_REQUEST['number'] ?? '');
    $orderId = (int)($_REQUEST['id'] ?? 0);
    
    if (empty($number)) {
        die(json_encode(["status" => "error", "message" => "Номер заказа не может быть пустым"]));
    }
    
    // Проверяем, существует ли уже такой номер заказа (кроме текущего редактируемого заказа)
    $stmt = $conn->prepare("SELECT id FROM PCRM_PurchaseOrder WHERE purchase_order_number = ? AND id != ? AND deleted = 0");
    $stmt->bind_param("si", $number, $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Заказ с таким номером уже существует"]);
    } else {
        echo json_encode(["status" => "ok"]);
    }
}

/**
 * Получает ID последнего созданного заказа поставщику
 */
function getLastOrderId() {
    global $conn;
    
    $res = $conn->query("SELECT id FROM PCRM_PurchaseOrder ORDER BY id DESC LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo $row['id'];
    } else {
        echo "0";
    }
}

/**
 * Получает элементы заказа поставщику
 */
function getOrderItems() {
    global $conn;
    
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        die(json_encode(["status" => "error", "message" => "Некорректный ID заказа"]));
    }
    
    $sql = "
        SELECT poi.*, p.name AS product_name
        FROM PCRM_PurchaseOrderItem poi
        LEFT JOIN PCRM_Product p ON poi.product_id = p.id
        WHERE poi.purchase_order_id = ?
        ORDER BY poi.id ASC
    ";
    $st = $conn->prepare($sql);
    $st->bind_param("i", $id);
    $st->execute();
    $result = $st->get_result();
    
    $items = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(["status" => "ok", "items" => $items]);
}