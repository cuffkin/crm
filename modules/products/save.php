<?php
// /crm/modules/products/save.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'products')) {
    die("Нет доступа");
}

// Получаем данные из POST
$id             = (int)($_POST['id'] ?? 0);
$name           = trim($_POST['name'] ?? '');
$sku            = trim($_POST['sku'] ?? '');
$category       = $_POST['category'] ? (int)$_POST['category'] : null;
$subcategory    = $_POST['subcategory'] ? (int)$_POST['subcategory'] : null;
$price          = $_POST['price'] ?? '0.00';
$cost_price     = $_POST['cost_price'] ?? '0.00';
$description    = trim($_POST['description'] ?? '');
$unit_of_measure= trim($_POST['unit_of_measure'] ?? 'шт');
$weight         = $_POST['weight'] ?? '0.000';
$volume         = $_POST['volume'] ?? '0.000';
$status         = trim($_POST['status'] ?? 'active');

// Проверка обязательных полей
if ($name === '') {
    die("Название товара не может быть пустым");
}

// UPDATE или INSERT
if ($id > 0) {
    // Обновляем
    $sql = "UPDATE PCRM_Product
            SET name=?, sku=?, category=?, subcategory=?, price=?, cost_price=?, 
                description=?, unit_of_measure=?, weight=?, volume=?, status=?
            WHERE id=?";
    $stt = $conn->prepare($sql);
    $stt->bind_param("ssiiddssddsi",
        $name, $sku, $category, $subcategory, $price, $cost_price, 
        $description, $unit_of_measure, $weight, $volume, $status, $id
    );
    $stt->execute();
    if ($stt->error) {
        die("Ошибка: " . $stt->error);
    }
    echo "OK";
} else {
    // Добавляем
    $sql = "INSERT INTO PCRM_Product
            (name, sku, category, subcategory, price, cost_price, 
             description, unit_of_measure, weight, volume, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)";
    $stt = $conn->prepare($sql);
    $stt->bind_param("ssiiddssdds",
        $name, $sku, $category, $subcategory, $price, $cost_price, 
        $description, $unit_of_measure, $weight, $volume, $status
    );
    $stt->execute();
    if ($stt->error) {
        die("Ошибка: " . $stt->error);
    }
    echo "OK";
}