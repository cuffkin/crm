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
  <!-- Основной navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
      <div class="d-flex align-items-center">
        <!-- Кнопка для sidebar -->
        <button id="sidebar-toggle" class="btn btn-outline-light me-2 d-flex align-items-center justify-content-center" title="Меню"><i class="fas fa-bars"></i></button>
        <!-- Бренд -->
        <a class="navbar-brand" href="#">PRORABCRM SPA</a>
      </div>
      <!-- Toggle collapse -->
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
      <div class="collapse navbar-collapse" id="mainNavbar">
      <!-- Избранные табы -->
      <div id="favorite-tabs" class="d-flex ms-3" style="position: relative; z-index: 100; min-height: 38px; pointer-events: auto;"></div>
      <!-- Статус и профиль -->
      <div class="d-flex align-items-center text-light ms-auto">
        <div id="session-status" class="d-flex align-items-center me-3"><small id="session-info" class="text-light me-2"></small><span id="sync-status" class="badge bg-secondary rounded-pill"><i class="fas fa-sync-alt"></i></span></div>
        <span class="me-3"><?= htmlspecialchars($username) ?> (<?= htmlspecialchars($user_role) ?>)</span>
        <label class="theme-switcher me-3"><input type="checkbox" id="theme-switcher"><span class="theme-slider"><i class="fas fa-sun theme-icon theme-icon-light"></i><i class="fas fa-moon theme-icon theme-icon-dark"></i></span></label>
        <a href="logout.php" class="btn btn-outline-light btn-sm">Выйти</a>
      </div>
      </div>
    </div>
  </nav>
  <?php include __DIR__ . '/sidebar.php'; ?>
  
  <!-- Контейнеры для вкладок -->
  <div class="container-fluid mt-3">
    <ul class="nav nav-tabs" id="crm-tabs"></ul>
    <div class="tab-content" id="crm-tab-content"></div>
  </div>
  
  <!-- Контент страницы -->