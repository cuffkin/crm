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
  <!-- Основной navbar -->
  <nav class="navbar navbar-dark bg-primary">
    <div class="container-fluid">
      <!-- Кнопка открытия сайдбара -->
      <button id="sidebar-toggle" class="btn btn-outline-light me-2" title="Меню">
        <i class="fas fa-bars"></i>
      </button>
      <a class="navbar-brand text-light" href="#">PRORABCRM SPA</a>
      <div id="favorite-tabs" class="d-flex"></div>
      <div class="d-flex align-items-center text-light ms-auto">
        <div id="session-status" class="d-flex align-items-center me-3">
          <small id="session-info" class="text-light me-2"></small>
          <span id="sync-status" class="badge bg-secondary rounded-pill" title="Статус синхронизации">
            <i class="fas fa-sync-alt"></i>
          </span>
        </div>
        <span class="me-3"><?= htmlspecialchars($username) ?> (<?= htmlspecialchars($user_role) ?>)</span>
        <label class="theme-switcher me-3" title="Переключить тему">
          <input type="checkbox" id="theme-switcher">
          <span class="theme-slider">
            <i class="fas fa-sun theme-icon theme-icon-light"></i>
            <i class="fas fa-moon theme-icon theme-icon-dark"></i>
          </span>
        </label>
        <a href="logout.php" class="btn btn-outline-light btn-sm">Выйти</a>
      </div>
    </div>
  </nav>
  <!-- Контент страницы -->