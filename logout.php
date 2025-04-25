<?php
// /crm/logout.php
require_once __DIR__ . '/config/session.php';

// Очищаем сессию
session_unset();
session_destroy();

// Перенаправляем на login
header("Location: login.php");
exit;