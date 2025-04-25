<?php
// /crm/modules/sales/orders/order_api.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'sales_orders')) {
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
    case 'get_counterparty_info':
        getCounterpartyInfo();
        break;
    default:
        echo json_encode(["status" => "error", "message" => "Неизвестное действие"]);
}

/**
 * Получает информацию о заказе по ID
 */
function getOrderInfo() {
    global $conn;
    
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        die(json_encode(["status" => "error", "message" => "Некорректный ID заказа"]));
    }

    // Получаем информацию о заказе
    $sql = "
        SELECT o.id, o.order_number, o.organization, o.customer, o.warehouse, 
               o.total_amount, o.driver_id, o.delivery_address, o.conducted, o.status,
               SUM((oi.quantity * oi.price) - oi.discount) AS order_sum
        FROM PCRM_Order o
        LEFT JOIN PCRM_OrderItem oi ON o.id = oi.order_id
        WHERE o.id = ? AND o.deleted = 0
        GROUP BY o.id
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
 * Генерирует новый номер заказа
 */
function generateOrderNumber() {
    global $conn;
    
    $r = $conn->query("SELECT id FROM PCRM_Order ORDER BY id DESC LIMIT 1");
    $last = $r->fetch_assoc();
    $newId = $last ? ($last['id'] + 1) : 1;
    $num = "SO-" . str_pad($newId, 6, '0', STR_PAD_LEFT);
    
    echo json_encode(["status" => "ok", "number" => $num]);
}

/**
 * Проверяет уникальность номера заказа
 */
function checkOrderNumber() {
    global $conn;
    
    $number = trim($_REQUEST['number'] ?? '');
    $orderId = (int)($_REQUEST['id'] ?? 0);
    
    if (empty($number)) {
        die(json_encode(["status" => "error", "message" => "Номер заказа не может быть пустым"]));
    }
    
    // Проверяем, существует ли уже такой номер заказа (кроме текущего редактируемого заказа)
    $stmt = $conn->prepare("SELECT id FROM PCRM_Order WHERE order_number = ? AND id != ? AND deleted = 0");
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
 * Получает ID последнего созданного заказа
 */
function getLastOrderId() {
    global $conn;
    
    $res = $conn->query("SELECT id FROM PCRM_Order ORDER BY id DESC LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo $row['id'];
    } else {
        echo "0";
    }
}

/**
 * Получает информацию о контрагенте
 */
function getCounterpartyInfo() {
    global $conn;
    
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        die(json_encode(["status" => "error", "message" => "Некорректный ID контрагента"]));
    }
    
    // Получаем информацию о контрагенте
    $sql = "SELECT id, name, address, contact_info FROM PCRM_Counterparty WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        die(json_encode(["status" => "error", "message" => "Контрагент не найден"]));
    }
    
    $counterpartyInfo = $result->fetch_assoc();
    
    // Возвращаем данные контрагента
    echo json_encode(["status" => "ok", "data" => $counterpartyInfo]);
}