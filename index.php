<?php
// /crm/index.php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$username  = $_SESSION['username'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';

include __DIR__ . '/includes/header.php';
?>

<div class="container mt-3">
  <!-- Дашборд-плейсхолдер -->
  <div id="dashboard" class="dashboard-empty">
    <!-- Здесь будет контент дашборда -->
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>