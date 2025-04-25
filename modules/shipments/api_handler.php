<?php
// Файл /crm/modules/shipments/api_handler.php - расширенная версия
// Эмулирует get_last_shipment_id.php, get_order_items.php и get_shipment_info.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Определяем какой файл эмулировать
$requestFile = basename($_SERVER['SCRIPT_NAME']);

if ($requestFile === 'get_last_shipment_id.php') {
    // Эмулируем функциональность get_last_shipment_id.php
    if (!check_access($conn, $_SESSION['user_id'], 'shipments')) {
        die("0");
    }

    $res = $conn->query("SELECT id FROM PCRM_ShipmentHeader ORDER BY id DESC LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo $row['id'];
    } else {
        echo "0";
    }
} 
else if ($requestFile === 'get_order_items.php') {
    // Эмулируем функциональность get_order_items.php
    if (!check_access($conn, $_SESSION['user_id'], 'shipments')) {
        die(json_encode(["status" => "error", "message" => "Нет доступа"]));
    }

    $order_id = (int)($_GET['order_id'] ?? 0);
    if ($order_id <= 0) {
        die(json_encode(["status" => "error", "message" => "Некорректный ID заказа"]));
    }

    // Получаем товары из заказа
    $sqlItems = "
        SELECT i.product_id, i.quantity, i.price, i.discount, p.name AS product_name
        FROM PCRM_OrderItem i
        LEFT JOIN PCRM_Product p ON i.product_id = p.id
        WHERE i.order_id = ?
        ORDER BY i.id ASC
    ";
    $st = $conn->prepare($sqlItems);
    $st->bind_param("i", $order_id);
    $st->execute();
    $result = $st->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(["status" => "ok", "items" => $items]);
}
else if ($requestFile === 'get_shipment_info.php') {
    // Новый обработчик для получения информации об отгрузке
    if (!check_access($conn, $_SESSION['user_id'], 'shipments')) {
        die(json_encode(["status" => "error", "message" => "Нет доступа"]));
    }
    
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        die(json_encode(["status" => "error", "message" => "Некорректный ID отгрузки"]));
    }
    
    // Получаем информацию об отгрузке
    $sql = "
        SELECT sh.*, 
               o.order_number,
               w.name AS warehouse_name,
               l.name AS loader_name,
               (SELECT SUM((s.quantity * s.price) - s.discount) 
                FROM PCRM_Shipments s 
                WHERE s.shipment_header_id = sh.id) AS total_amount
        FROM PCRM_ShipmentHeader sh
        LEFT JOIN PCRM_Order o ON sh.order_id = o.id
        LEFT JOIN PCRM_Warehouse w ON sh.warehouse_id = w.id
        LEFT JOIN PCRM_Loaders l ON sh.loader_id = l.id
        WHERE sh.id = ?
    ";
    $st = $conn->prepare($sql);
    $st->bind_param("i", $id);
    $st->execute();
    $result = $st->get_result();
    
    if ($result->num_rows === 0) {
        die(json_encode(["status" => "error", "message" => "Отгрузка не найдена"]));
    }
    
    $shipmentInfo = $result->fetch_assoc();
    
    // Возвращаем данные отгрузки
    echo json_encode(["status" => "ok", "data" => $shipmentInfo]);
}
else {
    // Если вызван непосредственно api_handler.php
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "Прямой доступ к этому файлу запрещен"]);
}