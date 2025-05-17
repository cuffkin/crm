<?php
// Перенаправление на страницу 404
header("Location: 404.php");
exit;
?>

// /crm/modules/access/give_all_access.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Проверяем, что admin
$myId = $_SESSION['user_id'] ?? 0;
if(!$myId) {
    die("Нет сессии");
}
$q = $conn->prepare("SELECT role FROM PCRM_User WHERE id=?");
$q->bind_param("i", $myId);
$q->execute();
$myRole = $q->get_result()->fetch_assoc()['role'] ?? '';
if($myRole !== 'admin'){
    die("Только admin");
}

// Получаем роль
$role = trim($_POST['role'] ?? '');
if(!$role) {
    die("Нет роли");
}

// Список модулей, как в list_partial
$allModules = [
    'users','categories','products','warehouse','stock','access',
    'shipments','returns_customer','purchase_orders','receipts','returns_supplier',
    'inventory','appropriations','writeoff'
];

// 1) Находим всех пользователей с этой ролью
$sqlU = "SELECT id FROM PCRM_User WHERE role=?";
$stU  = $conn->prepare($sqlU);
$stU->bind_param("s", $role);
$stU->execute();
$resU= $stU->get_result();
$usersInRole = $resU->fetch_all(MYSQLI_ASSOC);

// 2) Для каждого модуля, для каждого user, ставим can_access=1
foreach($usersInRole as $u) {
    $uid = $u['id'];
    foreach($allModules as $mod) {
        // Проверяем, есть ли запись
        $sel = $conn->prepare("SELECT id FROM PCRM_AccessRules 
                               WHERE user_id=? AND module_name=? LIMIT 1");
        $sel->bind_param("is", $uid, $mod);
        $sel->execute();
        $r = $sel->get_result();
        if($row = $r->fetch_assoc()) {
            // update
            $upd = $conn->prepare("UPDATE PCRM_AccessRules SET can_access=1 WHERE id=?");
            $upd->bind_param("i", $row['id']);
            $upd->execute();
        } else {
            // insert
            $ins = $conn->prepare("INSERT INTO PCRM_AccessRules (user_id, module_name, can_access)
                                   VALUES (?,?,1)");
            $ins->bind_param("is", $uid, $mod);
            $ins->execute();
        }
    }
}

echo "OK";