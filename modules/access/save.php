<?php
session_start();
include_once "../../../config/db.php";

header('Content-Type: application/json');

$userId = (int)$_POST['user_id'];
$modules = $_POST['modules'] ?? [];

$conn->query("DELETE FROM PCRM_AccessRules WHERE user_id = $userId");

foreach ($modules as $module => $canAccess) {
    $sql = "INSERT INTO PCRM_AccessRules (user_id, module_name, can_access) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $userId, $module, $canAccess);
    $stmt->execute();
}

echo json_encode(['success' => true]);