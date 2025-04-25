<?php
// /crm/modules/access/list_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Проверяем, что текущий пользователь - admin (или кто-то с правами)
$myId = $_SESSION['user_id'] ?? 0;
if (!$myId) {
    die("<div class='text-danger'>Нет сессии</div>");
}
// Узнаём роль
$q = $conn->prepare("SELECT role FROM PCRM_User WHERE id=?");
$q->bind_param("i", $myId);
$q->execute();
$myRole = $q->get_result()->fetch_assoc()['role'] ?? '';
if ($myRole !== 'admin') {
    die("<div class='text-danger'>Только admin может управлять доступом</div>");
}

// Список пользователей (кроме admin)
$usersSql = "SELECT * FROM PCRM_User WHERE role<>'admin' ORDER BY id";
$usersRes = $conn->query($usersSql);
$users    = $usersRes->fetch_all(MYSQLI_ASSOC);

// Модули, которые ограничиваем
// (Можно жестко прописать, или собрать из меню)
$allModules = [
    'users','categories','products','warehouse','stock','access',
    'shipments','returns_customer','purchase_orders','receipts','returns_supplier',
    'inventory','appropriations','writeoff'
    // ... если хотите дополнить ...
];

// Получим все доступные роли (SELECT DISTINCT role FROM PCRM_User)
$roleRes = $conn->query("SELECT DISTINCT role FROM PCRM_User ORDER BY role");
$roles = $roleRes->fetch_all(MYSQLI_ASSOC);
?>
<h4>Управление доступом к модулям</h4>

<!-- Блок "Выдать все доступы роли" -->
<div class="card mb-3">
  <div class="card-body">
    <label for="roleSelect" class="form-label">Выберите роль</label>
    <select id="roleSelect" class="form-select w-auto d-inline-block">
      <?php foreach($roles as $rr): ?>
        <option value="<?= htmlspecialchars($rr['role']) ?>">
          <?= htmlspecialchars($rr['role']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-success btn-sm" onclick="giveAllAccessForRole()">
      Выдать все доступы
    </button>
  </div>
</div>

<!-- Таблица "пользователь -> модули" -->
<table class="table table-bordered">
  <thead>
    <tr>
      <th>Пользователь</th>
      <?php foreach ($allModules as $m): ?>
        <th><?= $m ?></th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <tr>
      <td><?= $u['id'].' - '.htmlspecialchars($u['username']).' ('.$u['role'].')' ?></td>
      <?php foreach ($allModules as $m):
          $has = check_access($conn, $u['id'], $m);
      ?>
        <td>
          <input type="checkbox"
                 data-userid="<?= $u['id'] ?>"
                 data-module="<?= $m ?>"
                 class="access-chk"
                 <?= $has?'checked':'' ?>>
        </td>
      <?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<script>
$(function(){
  // При клике на чекбокс - AJAX
  $('.access-chk').on('change', function(){
    let userId = $(this).data('userid');
    let modName= $(this).data('module');
    let canAcc = $(this).is(':checked') ? 1 : 0;

    $.post('/crm/modules/access/save.php', {
      user_id: userId,
      module_name: modName,
      can_access: canAcc
    }, function(resp){
      if(resp !== 'OK'){
        alert('Ошибка: '+resp);
      }
    });
  });
});

function giveAllAccessForRole(){
  let roleVal = $('#roleSelect').val();
  if(!roleVal) {
    alert('Не выбрана роль');
    return;
  }
  if(!confirm('Выдать все доступы для роли: '+roleVal+' ?')) return;

  $.post('/crm/modules/access/give_all_access.php', {role: roleVal}, function(resp){
    if(resp==='OK'){
      // Перезагрузим текущую вкладку
      $.get('/crm/modules/access/list_partial.php', function(h){
        $('#crm-tab-content .tab-pane.active').html(h);
      });
    } else {
      alert(resp);
    }
  });
}
</script>