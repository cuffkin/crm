<?php
// /crm/includes/functions.php

function check_access($conn, $userId, $moduleName) {
    // 1) Проверим, не admin ли этот пользователь
    $sqlRole = "SELECT role FROM PCRM_User WHERE id=?";
    $st = $conn->prepare($sqlRole);
    $st->bind_param("i", $userId);
    $st->execute();
    $res  = $st->get_result();
    $u    = $res->fetch_assoc();
    if ($u && $u['role'] === 'admin') {
        return true;
    }

    // 2) Иначе смотрим в таблице PCRM_AccessRules
    $sql = "SELECT can_access FROM PCRM_AccessRules 
            WHERE user_id=? AND module_name=? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $userId, $moduleName);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return (bool)$row['can_access'];
    }
    // если записи нет, запрещаем
    return false;
}