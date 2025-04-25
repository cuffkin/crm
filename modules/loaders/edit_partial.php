<?php
// /crm/modules/loaders/edit_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'loaders')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

$id = (int)($_GET['id'] ?? 0);

$name = '';
$phone = '';
$status = 'active';

if ($id > 0) {
    $st = $conn->prepare("SELECT * FROM PCRM_Loaders WHERE id=?");
    $st->bind_param("i", $id);
    $st->execute();
    $res = $st->get_result();
    $l = $res->fetch_assoc();
    if ($l) {
        $name   = $l['name'];
        $phone  = $l['phone'];
        $status = $l['status'];
    } else {
        die("<div class='text-danger'>Грузчик не найден</div>");
    }
}
?>
<div class="card">
  <div class="card-header">
    <?= $id>0 ? 'Редактирование грузчика' : 'Новый грузчик' ?>
  </div>
  <div class="card-body">
    <div class="mb-3">
      <label>Имя (ФИО)</label>
      <input type="text" id="ldr-name" class="form-control"
             value="<?= htmlspecialchars($name) ?>">
    </div>
    <div class="mb-3">
      <label>Телефон</label>
      <input type="text" id="ldr-phone" class="form-control"
             value="<?= htmlspecialchars($phone) ?>">
    </div>
    <div class="mb-3">
      <label>Статус</label>
      <select id="ldr-status" class="form-select">
        <option value="active"   <?= ($status=='active'?'selected':'') ?>>active</option>
        <option value="inactive"<?= ($status=='inactive'?'selected':'') ?>>inactive</option>
      </select>
    </div>
    <button class="btn btn-success" onclick="saveLoader(<?= $id ?>)">Сохранить</button>
    <button class="btn btn-secondary" onclick="$('#loader-edit-area').html('')">Отмена</button>
  </div>
</div>

<script>
function saveLoader(lid) {
  let data = {
    id: lid,
    name: $('#ldr-name').val(),
    phone: $('#ldr-phone').val(),
    status: $('#ldr-status').val()
  };
  $.post('/crm/modules/loaders/save.php', data, function(resp){
    if (resp === 'OK') {
      $('#loader-edit-area').html('');
      $.get('/crm/modules/loaders/list_partial.php', function(h){
        $('#crm-tab-content .tab-pane.active').html(h);
      });
    } else {
      alert(resp);
    }
  });
}
</script>