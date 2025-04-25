<?php
// /crm/config/db.php

$servername = "localhost";
$username   = "prorab";
$password   = "iX5nW4zR1w";
$dbname     = "prorab";

// Создаем соединение (MySQLi)
$conn = new mysqli($servername, $username, $password, $dbname);

// Проверяем соединение
if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}