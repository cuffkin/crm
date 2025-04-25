<?php
// /crm/modules/counterparty/edit_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'counterparty')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

$id = (int)($_GET['id'] ?? 0);

$name = '';
$type = 'физлицо'; // по умолчанию
$phone = '';
$email = '';
$address = '';
$inn = '';
$kpp = '';

if ($id > 0) {
    $st = $conn->prepare("SELECT * FROM PCRM_Counterparty WHERE id=?");
    $st->bind_param("i", $id);
    $st->execute();
    $res = $st->get_result();
    $row = $res->fetch_assoc();
    if ($row) {
        $name    = $row['name'];
        $type    = $row['type'];
        $phone   = $row['phone'];
        $email   = $row['email'];
        $address = $row['address'];
        $inn     = $row['inn'];
        $kpp     = $row['kpp'];
    } else {
        die("<div class='text-danger'>Контрагент не найден</div>");
    }
}
?>
<div class="card">
  <div class="card-header">
    <?= $id>0 ? 'Редактирование контрагента' : 'Новый контрагент' ?>
  </div>
  <div class="card-body">
    <div class="mb-3">
      <label>Название</label>
      <input type="text" id="cp-name" class="form-control" value="<?= htmlspecialchars($name) ?>">
    </div>
    <div class="mb-3">
      <label>Тип</label>
      <select id="cp-type" class="form-select">
        <option value="физлицо" <?= ($type=='физлицо'?'selected':'') ?>>Физ. лицо</option>
        <option value="юрлицо"  <?= ($type=='юрлицо'?'selected':'') ?>>Юр. лицо</option>
        <!-- Или любые другие варианты -->
      </select>
    </div>
    <div class="mb-3">
      <label>Телефон</label>
      <input type="text" id="cp-phone" class="form-control" value="<?= htmlspecialchars($phone) ?>">
    </div>
    <div class="mb-3">
      <label>Email</label>
      <input type="email" id="cp-email" class="form-control" value="<?= htmlspecialchars($email) ?>">
    </div>
    <div class="mb-3">
      <label>ИНН</label>
      <input type="text" id="cp-inn" class="form-control" value="<?= htmlspecialchars($inn) ?>">
    </div>
    <div class="mb-3">
      <label>КПП</label>
      <input type="text" id="cp-kpp" class="form-control" value="<?= htmlspecialchars($kpp) ?>">
    </div>
    <div class="mb-3">
      <label>Адрес</label>
      <input type="text" id="cp-address" class="form-control" value="<?= htmlspecialchars($address) ?>">
    </div>

    <button class="btn btn-success" onclick="saveCounterparty(<?= $id ?>)">Сохранить</button>
    <button class="btn btn-secondary" onclick="$('#cp-edit-area').html('')">Отмена</button>
  </div>
</div>

<script>
function saveCounterparty(cid) {
  let data = {
    id: cid,
    name: $('#cp-name').val(),
    type: $('#cp-type').val(),
    phone: $('#cp-phone').val(),
    email: $('#cp-email').val(),
    inn: $('#cp-inn').val(),
    kpp: $('#cp-kpp').val(),
    address: $('#cp-address').val()
  };
  $.post('/crm/modules/counterparty/save.php', data, function(resp){
    if (resp === 'OK') {
      // Закрыть форму
      $('#cp-edit-area').html('');
      // Обновить список
      $.get('/crm/modules/counterparty/list_partial.php', function(h){
        $('#crm-tab-content .tab-pane.active').html(h);
      });
    } else {
      alert(resp);
    }
  });
}
</script>