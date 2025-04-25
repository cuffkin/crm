<?php
// /crm/modules/warehouse/edit_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'warehouse')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

$id = (int)($_GET['id'] ?? 0);
$name=''; $loc=''; $status='active';

if ($id>0) {
    $st = $conn->prepare("SELECT * FROM PCRM_Warehouse WHERE id=?");
    $st->bind_param("i", $id);
    $st->execute();
    $r = $st->get_result();
    $row= $r->fetch_assoc();
    if ($row) {
        $name = $row['name'];
        $loc  = $row['location'];
        $status=$row['status'];
    }
}
?>
<div class="card">
  <div class="card-header">
    <?= $id>0 ? 'Редактирование склада' : 'Новый склад' ?>
  </div>
  <div class="card-body">
    <div class="mb-3">
      <label>Наименование</label>
      <input type="text" id="w-name" class="form-control"
             value="<?= htmlspecialchars($name) ?>">
    </div>
    <div class="mb-3">
      <label>Адрес / Местоположение</label>
      <input type="text" id="w-loc" class="form-control"
             value="<?= htmlspecialchars($loc) ?>">
    </div>
    <div class="mb-3">
      <label>Статус</label>
      <select id="w-status" class="form-select">
        <option value="active" <?= ($status=='active'?'selected':'') ?>>active</option>
        <option value="closed" <?= ($status=='closed'?'selected':'') ?>>closed</option>
      </select>
    </div>
    <button class="btn btn-success" onclick="saveWarehouse(<?= $id ?>)">Сохранить</button>
    <button class="btn btn-secondary" onclick="$('#warehouse-edit-area').html('')">Отмена</button>
  </div>
</div>

<script>
function saveWarehouse(wid) {
  let data = {
    id: wid,
    name: $('#w-name').val(),
    location: $('#w-loc').val(),
    status: $('#w-status').val()
  };
  $.post('/crm/modules/warehouse/save.php', data, function(resp){
    if (resp==='OK') {
      $('#warehouse-edit-area').html('');
      $.get('/crm/modules/warehouse/list_partial.php', function(h){
        $('#crm-tab-content .tab-pane.active').html(h);
      });
    } else {
      alert(resp);
    }
  });
}
</script>