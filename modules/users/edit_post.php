<?php
// /crm/modules/users/edit_post.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die("Нет прав");
}

$action = $_POST['action'] ?? '';
if ($action !== 'saveUser') {
    die("Некорректное действие");
}

$id       = (int)($_POST['id'] ?? 0);
$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$role     = trim($_POST['role'] ?? 'manager');
$status   = trim($_POST['status'] ?? 'active');
$pass     = trim($_POST['password'] ?? '');

if ($username === '') {
    die("Имя пользователя не может быть пустым");
}

if ($id > 0) {
    // Обновление
    if ($pass !== '') {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $sql  = "UPDATE PCRM_User
                 SET username=?, email=?, role=?, status=?, password=?
                 WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", 
            $username, $email, $role, $status, $hash, $id);
    } else {
        $sql  = "UPDATE PCRM_User
                 SET username=?, email=?, role=?, status=?
                 WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", 
            $username, $email, $role, $status, $id);
    }
    $stmt->execute();
    if ($stmt->error) {
        die("Ошибка: " . $stmt->error);
    }
    echo "OK";
} else {
    // Создание
    if ($pass === '') {
        die("При создании нового пользователя пароль обязателен");
    }
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $sql  = "INSERT INTO PCRM_User (username, email, role, status, password)
             VALUES (?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $username, $email, $role, $status, $hash);
    $stmt->execute();
    if ($stmt->error) {
        die("Ошибка: " . $stmt->error);
    }
    echo "OK";
}