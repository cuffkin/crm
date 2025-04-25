<?php
// /crm/modules/sales/returns/get_return_info.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'sales_returns')) {
    die(json_encode(["status" => "error", "message" => "Нет доступа"]));
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die(json_encode(["status" => "error", "message" => "Некорректный ID возврата"]));
}

// Получаем информацию о возврате
$sql = "
    SELECT r.*, 
           o.order_number,
           w.name AS warehouse_name,
           l.name AS loader_name,
           (SELECT SUM((ri.quantity * ri.price) - ri.discount) 
            FROM PCRM_ReturnItem ri 
            WHERE ri.return_id = r.id) AS total_amount
    FROM PCRM_ReturnHeader r
    LEFT JOIN PCRM_Order o ON r.order_id = o.id
    LEFT JOIN PCRM_Warehouse w ON r.warehouse_id = w.id
    LEFT JOIN PCRM_Loaders l ON r.loader_id = l.id
    WHERE r.id = ?
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