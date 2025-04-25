<?php
// /crm/includes/header.php
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php if (isset($_SESSION['user_id'])): ?>
  <meta name="user-id" content="<?= $_SESSION['user_id'] ?>">
  <?php endif; ?>
  <title>PRORABCRM SPA</title>
  <!-- Подключаем Bootstrap CSS из локальной папки -->
  <link rel="stylesheet" href="/crm/assets/bootstrap-5.3.3-dist/css/bootstrap.min.css">
  <!-- Font Awesome для иконок -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Основные стили -->
  <link rel="stylesheet" href="/crm/css/style.css">
  <!-- Улучшенный визуальный стиль -->
  <link rel="stylesheet" href="/crm/css/enhanced-style.css">
  <!-- Скрипт переключения тем -->
  <script src="/crm/js/theme-switcher.js"></script>
</head>
<body class="light-theme">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <!-- Кнопка открытия сайдбара -->
  <button id="sidebar-toggle" class="btn btn-outline-light me-2" title="Меню">
    <i class="fas fa-bars"></i>
  </button>
  <!-- Начало основного содержимого -->