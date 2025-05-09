<?php
// /crm/modules/drivers/list_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'drivers')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

// Выбираем водителей
$sql = "SELECT * FROM PCRM_Drivers ORDER BY id DESC";
$res = $conn->query($sql);
if (!$res) {
    die("<div class='text-danger'>Ошибка запроса: " . $conn->error . "</div>");
}
$drivers = $res->fetch_all(MYSQLI_ASSOC);
?>
<h4>Водители</h4>
<button class="btn btn-primary btn-sm mb-2" onclick="editDriver(0)">
  Добавить водителя
</button>

<table class="table table-bordered table-sm">
  <thead>
    <tr>
      <th>ID</th>
      <th>ФИО</th>
      <th>Автомобиль</th>
      <th>Грузоподъёмность</th>
      <th>Объём макс.</th>
      <th>Телефон</th>
      <th>Действия</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($drivers as $d): ?>
    <tr>
      <td><?= $d['id'] ?></td>
      <td><?= htmlspecialchars($d['name']) ?></td>
      <td><?= htmlspecialchars($d['vehicle_name'] ?? '') ?></td>
      <td><?= $d['load_capacity'] ?></td>
      <td><?= $d['max_volume'] ?></td>
      <td><?= htmlspecialchars($d['phone'] ?? '') ?></td>
      <td>
        <button class="btn btn-warning btn-sm" onclick="editDriver(<?= $d['id'] ?>)">
          Редакт.
        </button>
        <button class="btn btn-danger btn-sm" onclick="deleteDriver(<?= $d['id'] ?>)">
          Удалить
        </button>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div id="driver-edit-area"></div>

<script>
function editDriver(did) {
  $.ajax({
    url: '/crm/modules/drivers/edit_partial.php',
    data: { id: did },
    success: function(html) {
      $('#driver-edit-area').html(html).addClass('fade-in');
    }
  });
}

function deleteDriver(did) {
  if (!confirm('Точно удалить водителя?')) return;
  $.get('/crm/modules/drivers/delete.php', { id: did }, function(resp){
    if (resp === 'OK') {
      $.get('/crm/modules/drivers/list_partial.php', function(h){
        $('#crm-tab-content .tab-pane.active').html(h);
      });
    } else {
      alert(resp);
    }
  });
}
</script>