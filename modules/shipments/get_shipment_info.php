<?php
// /crm/modules/shipments/get_shipment_info.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

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