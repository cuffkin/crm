<?php
// /crm/modules/drivers/edit_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'drivers')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

$id = (int)($_GET['id'] ?? 0);

$name         = '';
$vehicle_name = '';
$load_capacity= '0.00';
$max_volume   = '0.000';
$phone        = '';

if ($id > 0) {
    $st = $conn->prepare("SELECT * FROM PCRM_Drivers WHERE id=?");
    $st->bind_param("i", $id);
    $st->execute();
    $res = $st->get_result();
    $dr = $res->fetch_assoc();
    if ($dr) {
        $name         = $dr['name'];
        $vehicle_name = $dr['vehicle_name'];
        $load_capacity= $dr['load_capacity'];
        $max_volume   = $dr['max_volume'];
        $phone        = $dr['phone'];
    } else {
        die("<div class='text-danger'>Водитель не найден</div>");
    }
}
?>
<div class="card">
  <div class="card-header">
    <?= $id>0 ? 'Редактирование водителя' : 'Новый водитель' ?>
  </div>
  <div class="card-body">
    <div class="mb-3">
      <label>ФИО</label>
      <input type="text" id="dr-name" class="form-control"
             value="<?= htmlspecialchars($name) ?>">
    </div>
    <div class="mb-3">
      <label>Автомобиль</label>
      <input type="text" id="dr-vehicle" class="form-control"
             value="<?= htmlspecialchars($vehicle_name) ?>">
    </div>
    <div class="row mb-3">
      <div class="col">
        <label>Грузоподъёмность (кг)</label>
        <input type="number" step="0.01" id="dr-capacity" class="form-control"
               value="<?= $load_capacity ?>">
      </div>
      <div class="col">
        <label>Объём макс. (м³)</label>
        <input type="number" step="0.001" id="dr-volume" class="form-control"
               value="<?= $max_volume ?>">
      </div>
    </div>
    <div class="mb-3">
      <label>Телефон</label>
      <input type="text" id="dr-phone" class="form-control"
             value="<?= htmlspecialchars($phone) ?>">
    </div>
    <button class="btn btn-success" onclick="saveDriver(<?= $id ?>)">Сохранить</button>
    <button class="btn btn-secondary" onclick="$('#driver-edit-area').html('')">Отмена</button>
  </div>
</div>

<script>
function saveDriver(did) {
  let data = {
    id: did,
    name: $('#dr-name').val(),
    vehicle_name: $('#dr-vehicle').val(),
    load_capacity: $('#dr-capacity').val(),
    max_volume: $('#dr-volume').val(),
    phone: $('#dr-phone').val()
  };
  $.post('/crm/modules/drivers/save.php', data, function(resp){
    if (resp === 'OK') {
      $('#driver-edit-area').html('');
      $.get('/crm/modules/drivers/list_partial.php', function(h){
        $('#crm-tab-content .tab-pane.active').html(h);
      });
    } else {
      alert(resp);
    }
  });
}
</script>