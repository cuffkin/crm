<?php
// /crm/modules/products/get_subcats.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Если нужно, можно проверить доступ. Но часто для AJAX-фильтра достаточно,
// чтобы пользователь в принципе был авторизован. 
// if (!check_access($conn, $_SESSION['user_id'], 'products')) {
//     die(json_encode([])); // или просто пустой массив
// }

$parentId = (int)($_GET['parent'] ?? 0);

// Выберем все subcategory с pc_id = $parentId и status='active'
$sql = "SELECT id, name
        FROM PCRM_Categories
        WHERE type='subcategory'
          AND pc_id=?
          AND status='active'
        ORDER BY name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $parentId);
$stmt->execute();
$res = $stmt->get_result();

$subcats = [];
while ($row = $res->fetch_assoc()) {
    $subcats[] = $row;
}

// Отдаём данные в формате JSON
header('Content-Type: application/json; charset=utf-8');
echo json_encode($subcats, JSON_UNESCAPED_UNICODE);