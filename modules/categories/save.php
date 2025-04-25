<?php
// /crm/modules/categories/save.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'categories')) {
    die("Нет доступа");
}

// Получаем данные из POST
$id   = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$type = trim($_POST['type'] ?? 'category'); // "category" или "subcategory"
$pc   = $_POST['pc_id'] ? (int)$_POST['pc_id'] : null;
$desc = trim($_POST['desc'] ?? '');
$st   = trim($_POST['status'] ?? 'active');

if ($name === '') {
    die("Название не может быть пустым");
}

// Если поле type не category/subcategory, можно отсеять, но это на ваше усмотрение
if (!in_array($type, ['category','subcategory'])) {
    $type = 'category'; 
}

if ($id > 0) {
    // UPDATE
    $sql = "UPDATE PCRM_Categories
            SET name=?, type=?, pc_id=?, description=?, status=?
            WHERE id=?";
    $stt = $conn->prepare($sql);
    $stt->bind_param("ssissi", 
        $name, 
        $type, 
        $pc, 
        $desc, 
        $st, 
        $id
    );
    $stt->execute();
    if ($stt->error) {
        die("Ошибка при обновлении: " . $stt->error);
    }
    echo "OK";
} else {
    // INSERT
    $sql = "INSERT INTO PCRM_Categories 
            (name, type, pc_id, description, status)
            VALUES (?,?,?,?,?)";
    $stt = $conn->prepare($sql);
    $stt->bind_param("ssiss", 
        $name, 
        $type, 
        $pc, 
        $desc, 
        $st
    );
    $stt->execute();
    if ($stt->error) {
        die("Ошибка при вставке: " . $stt->error);
    }
    echo "OK";
}