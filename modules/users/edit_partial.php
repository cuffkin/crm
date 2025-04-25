<?php
// /crm/modules/users/edit_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die("<div class='text-danger'>Нет доступа</div>");
}

$user_id = $_GET['id'] ?? 0;
$user_id = (int)$user_id;

$username = $email = $role = $status = $first_name = $last_name = $phone = '';
$isEdit   = false;

if ($user_id > 0) {
    $isEdit = true;
    $stmt = $conn->prepare("SELECT * FROM PCRM_User WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res  = $stmt->get_result();
    $row  = $res->fetch_assoc();
    if ($row) {
        $username = $row['username'];
        $email    = $row['email'];
        $role     = $row['role'];
        $status   = $row['status'];
        $first_name = $row['first_name'];
        $last_name  = $row['last_name'];
        $phone      = $row['phone'];
    }
}

// Выводим HTML-форму (без submit-кнопки, тк будем делать AJAX)
?>
<div class="card mt-3">
  <div class="card-header">
    <?= $isEdit ? 'Редактировать' : 'Добавить' ?> пользователя
  </div>
  <div class="card-body">
    <div class="mb-3">
      <label class="form-label">Username</label>
      <input type="text" id="user-username" class="form-control" 
             value="<?= htmlspecialchars($username) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Email</label>
      <input type="email" id="user-email" class="form-control"
             value="<?= htmlspecialchars($email) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Пароль <?= $isEdit ? '(если хотите сменить)' : '(обязательно)' ?></label>
      <input type="password" id="user-password" class="form-control">
    </div>
    <div class="mb-3">
      <label class="form-label">Role</label>
      <select id="user-role" class="form-select">
        <option value="admin" <?= ($role==='admin'?'selected':''); ?>>admin</option>
        <option value="manager" <?= ($role==='manager'?'selected':''); ?>>manager</option>
        <option value="warehouse_operator" <?= ($role==='warehouse_operator'?'selected':''); ?>>
          warehouse_operator
        </option>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Status</label>
      <select id="user-status" class="form-select">
        <option value="active" <?= ($status==='active'?'selected':''); ?>>active</option>
        <option value="inactive" <?= ($status==='inactive'?'selected':''); ?>>inactive</option>
      </select>
    </div>
    <button class="btn btn-success" onclick="saveUser(<?= $user_id ?>)">Сохранить</button>
    <button class="btn btn-secondary" onclick="$('#user-edit-area').html('')">Отмена</button>
  </div>
</div>

<script>
function saveUser(uid) {
  let data = {
    action: 'saveUser',
    id: uid,
    username: $('#user-username').val(),
    email: $('#user-email').val(),
    password: $('#user-password').val(),
    role: $('#user-role').val(),
    status: $('#user-status').val()
  };

  $.ajax({
    url: '/crm/modules/users/edit_post.php',
    method: 'POST',
    data: data,
    success: function(resp) {
      if (resp === 'OK') {
        // Скрываем форму
        $('#user-edit-area').html('');
        // Обновляем список
        $.ajax({
          url: '/crm/modules/users/list_partial.php',
          success: function(html) {
            // Заново вставим таблицу
            let container = $('#user-edit-area').parent(); 
            // или ищем по #tab...
            container.html(html);
          }
        });
      } else {
        alert(resp);
      }
    },
    error: function() {
      alert('Ошибка при сохранении');
    }
  });
}
</script>