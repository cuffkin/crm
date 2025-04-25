<?php
// /crm/modules/purchases/returns/api_handler.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'purchases_returns')) {
    die(json_encode(["status" => "error", "message" => "Нет доступа"]));
}

// Определяем тип запроса
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'get_return_info':
        getReturnInfo();
        break;
    case 'get_last_return_id':
        getLastReturnId();
        break;
    case 'get_return_items':
        getReturnItems();
        break;
    default:
        // Если действие не указано, но передан ID, предполагаем get_return_info
        if (isset($_GET['id']) && !empty($_GET['id'])) {
            getReturnInfo();
        } else {
            echo json_encode(["status" => "error", "message" => "Неизвестное действие"]);
        }
}

/**
 * Получает информацию о возврате поставщику по ID
 */
function getReturnInfo() {
    global $conn;
    
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        die(json_encode(["status" => "error", "message" => "Некорректный ID возврата"]));
    }
    
    // Получаем информацию о возврате
    $sql = "
        SELECT sr.*, 
               po.purchase_order_number,
               w.name AS warehouse_name,
               l.name AS loader_name,
               (SELECT SUM((sri.quantity * sri.price) - sri.discount) 
                FROM PCRM_SupplierReturnItem sri 
                WHERE sri.return_id = sr.id) AS total_amount
        FROM PCRM_SupplierReturnHeader sr
        LEFT JOIN PCRM_PurchaseOrder po ON sr.purchase_order_id = po.id
        LEFT JOIN PCRM_Warehouse w ON sr.warehouse_id = w.id
        LEFT JOIN PCRM_Loaders l ON sr.loader_id = l.id
        WHERE sr.id = ?
    ";
    $st = $conn->prepare($sql);
    $st->bind_param("i", $id);
    $st->execute();
    $result = $st->get_result();
    
    if ($result->num_rows === 0) {
        die(json_encode(["status" => "error", "message" => "Возврат не найден"]));
    }
    
    $returnInfo = $result->fetch_assoc();
    
    // Возвращаем данные возврата
    echo json_encode(["status" => "ok", "data" => $returnInfo]);
}

/**
 * Получает ID последнего созданного возврата поставщику
 */
function getLastReturnId() {
    global $conn;
    
    $res = $conn->query("SELECT id FROM PCRM_SupplierReturnHeader ORDER BY id DESC LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo $row['id'];
    } else {
        echo "0";
    }
}

/**
 * Получает товары возврата поставщику по ID
 */
function getReturnItems() {
    global $conn;
    
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        die(json_encode(["status" => "error", "message" => "Некорректный ID возврата"]));
    }
    
    // Получаем товары возврата
    $sql = "
        SELECT sri.*, p.name AS product_name
        FROM PCRM_SupplierReturnItem sri
        LEFT JOIN PCRM_Product p ON sri.product_id = p.id
        WHERE sri.return_id = ?
        ORDER BY sri.id ASC
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