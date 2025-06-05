<?php
// /crm/scripts/cleanup_trash_cron.php - Скрипт автоочистки корзины для cron
// Добавьте в crontab: 0 2 * * * /usr/bin/php /path/to/crm/scripts/cleanup_trash_cron.php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../modules/trash/TrashManager.php';

try {
    // Получаем настройку автоочистки
    $settingsQuery = "SELECT value FROM PCRM_Settings WHERE `key` = 'trash_auto_cleanup_enabled'";
    $result = $conn->query($settingsQuery);
    
    if ($result && $row = $result->fetch_assoc()) {
        $autoCleanupEnabled = (bool)$row['value'];
        
        if (!$autoCleanupEnabled) {
            echo "[" . date('Y-m-d H:i:s') . "] Автоочистка корзины отключена в настройках\n";
            exit;
        }
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] Настройка автоочистки не найдена, пропускаем\n";
        exit;
    }
    
    // Системный пользователь для операций автоочистки (ID = 1 - админ)
    $systemUserId = 1;
    
    $trashManager = new TrashManager($conn, $systemUserId);
    $result = $trashManager->autoCleanup();
    
    if ($result['success']) {
        echo "[" . date('Y-m-d H:i:s') . "] Автоочистка корзины завершена. Удалено элементов: " . $result['deleted_count'] . "\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] Ошибка автоочистки: " . $result['error'] . "\n";
    }
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Критическая ошибка автоочистки: " . $e->getMessage() . "\n";
}
?> 