<?php
// /crm/modules/stock/save.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'stock')) {
    die("Нет доступа");
}

$id = (int)($_POST['id'] ?? 0);
$warehouse = (int)($_POST['warehouse'] ?? 0);
$prod_id = (int)($_POST['prod_id'] ?? 0);
$quantity = (float)($_POST['quantity'] ?? 0);

if ($warehouse <= 0 || $prod_id <= 0) {
    die("Выберите склад и товар");
}

if ($id > 0) {
    // Обновляем
    $sql = "UPDATE PCRM_Stock SET warehouse=?, prod_id=?, quantity=? WHERE id=?";
    $st = $conn->prepare($sql);
    $st->bind_param("iidi", $warehouse, $prod_id, $quantity, $id);
    $st->execute();
    if ($st->error) {
        die("Ошибка: " . $st->error);
    }
    echo "OK";
} else {
    // Проверяем, нет ли уже остатка
    $chk = $conn->prepare("SELECT id FROM PCRM_Stock WHERE warehouse=? AND prod_id=?");
    $chk->bind_param("ii", $warehouse, $prod_id);
    $chk->execute();
    $res = $chk->get_result();
    if ($res->num_rows > 0) {
        die("Остаток для этого товара на складе уже существует");
    }
    // Добавляем
    $sql = "INSERT INTO PCRM_Stock (warehouse, prod_id, quantity) VALUES (?,?,?)";
    $st = $conn->prepare($sql);
    $st->bind_param("iid", $warehouse, $prod_id, $quantity);
    $st->execute();
    if ($st->error) {
        die("Ошибка: " . $st->error);
    }
    echo "OK";
}