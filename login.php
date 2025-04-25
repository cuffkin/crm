<?php
// /crm/login.php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $sql = "SELECT * FROM PCRM_User WHERE username=? AND status='active' LIMIT 1";
    $st  = $conn->prepare($sql);
    $st->bind_param("s", $username);
    $st->execute();
    $res  = $st->get_result();
    $user = $res->fetch_assoc();

    if ($user) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            header("Location: index.php");
            exit;
        } else {
            $message = 'Неверный пароль.';
        }
    } else {
        $message = 'Пользователь не найден или неактивен.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#003366">
  <title>PRORABCRM - Вход в систему</title>
  <link rel="stylesheet" href="/crm/assets/bootstrap-5.3.3-dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="/crm/css/style.css">
  <link rel="stylesheet" href="/crm/css/enhanced-style.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Скрипт переключения тем -->
  <script src="/crm/js/theme-switcher.js"></script>
</head>
<body class="login-body">
  <!-- Переключатель темы в правом верхнем углу -->
  <div class="position-fixed top-0 end-0 mt-3 me-3 z-index-1000">
    <label class="theme-switcher">
      <input type="checkbox" id="theme-switcher">
      <span class="theme-slider">
        <i class="fas fa-sun theme-icon theme-icon-light"></i>
        <i class="fas fa-moon theme-icon theme-icon-dark"></i>
      </span>
    </label>
  </div>

  <div class="login-wrapper anim-fade-in">
    <div class="login-container">
      <div class="login-header">
        <div class="login-logo">
          <i class="fas fa-building"></i>
        </div>
        <h1 class="login-title">PRORAB<span>CRM</span></h1>
        <p class="login-subtitle">Система управления строительным бизнесом</p>
      </div>
      
      <?php if ($message): ?>
        <div class="alert alert-danger">
          <i class="fas fa-exclamation-circle me-2"></i>
          <?= htmlspecialchars($message); ?>
        </div>
      <?php endif; ?>
      
      <form method="post" class="login-form">
        <div class="form-floating mb-3">
          <input type="text" name="username" id="username" class="form-control" placeholder="Имя пользователя" required>
          <label for="username"><i class="fas fa-user me-2"></i>Имя пользователя</label>
        </div>
        <div class="form-floating mb-4">
          <input type="password" name="password" id="password" class="form-control" placeholder="Пароль" required>
          <label for="password"><i class="fas fa-lock me-2"></i>Пароль</label>
        </div>
        <button class="btn btn-primary btn-login w-100" type="submit">
          <i class="fas fa-sign-in-alt me-2"></i>Войти в систему
        </button>
      </form>
      
      <div class="login-footer">
        <p>&copy; <?= date('Y') ?> PRORABCRM. Все права защищены.</p>
      </div>
    </div>
    
    <div class="login-decoration">
      <div class="login-decoration-content">
        <h2>Профессиональное управление вашими проектами</h2>
        <p>Эффективное решение для учета, контроля и аналитики строительного бизнеса</p>
        <ul>
          <li><i class="fas fa-check-circle"></i> Управление заказами</li>
          <li><i class="fas fa-check-circle"></i> Контроль складских остатков</li>
          <li><i class="fas fa-check-circle"></i> Работа с контрагентами</li>
          <li><i class="fas fa-check-circle"></i> Полная аналитика</li>
        </ul>
      </div>
    </div>
  </div>

  <!-- Подключаем локальные JS файлы -->
  <script src="/crm/assets/jquery-3.3.1/jquery.min.js"></script>
  <script src="/crm/assets/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
  
  <!-- Дополнительные скрипты для анимаций -->
  <script>
    // При загрузке страницы
    document.addEventListener('DOMContentLoaded', function() {
      // Автоматически устанавливаем фокус на поле имени пользователя
      setTimeout(function() {
        document.getElementById('username').focus();
      }, 500);
      
      // Анимация для элементов списка в декоративной части
      const listItems = document.querySelectorAll('.login-decoration li');
      listItems.forEach((item, index) => {
        item.style.animationDelay = (0.4 + index * 0.1) + 's';
        item.classList.add('anim-fade-in-left');
      });
    });
    
    // Обработчик отправки формы с плавными переходами
    document.querySelector('.login-form').addEventListener('submit', function() {
      // Добавляем анимацию загрузки на кнопку
      const submitBtn = this.querySelector('button[type="submit"]');
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Вход в систему...';
      submitBtn.disabled = true;
    });
  </script>
</body>
</html>