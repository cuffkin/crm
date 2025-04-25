<?php
// /crm/modules/warehouse/list_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'warehouse')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

$res = $conn->query("SELECT * FROM PCRM_Warehouse ORDER BY id DESC");
$rows= $res->fetch_all(MYSQLI_ASSOC);
?>
<h4>Склады</h4>
<button class="btn btn-primary btn-sm mb-3" onclick="editWarehouse(0)">Добавить</button>
<table class="table table-bordered">
  <thead>
    <tr>
      <th>ID</th><th>Наименование</th><th>Адрес</th><th>Статус</th><th>Действия</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach($rows as $w): ?>
    <tr>
      <td><?= $w['id'] ?></td>
      <td><?= htmlspecialchars($w['name']) ?></td>
      <td><?= htmlspecialchars($w['location']) ?></td>
      <td><?= $w['status'] ?></td>
      <td>
        <button class="btn btn-warning btn-sm" onclick="editWarehouse(<?= $w['id'] ?>)">Ред</button>
        <button class="btn btn-danger btn-sm" onclick="deleteWarehouse(<?= $w['id'] ?>)">Удл</button>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<div id="warehouse-edit-area"></div>

<script>
function editWarehouse(wid) {
  $.ajax({
    url: '/crm/modules/warehouse/edit_partial.php',
    data: { id: wid },
    success: function(html){
      $('#warehouse-edit-area').html(html).addClass('fade-in');
    }
  });
}

function deleteWarehouse(wid) {
  if (!confirm('Удалить склад?')) return;
  $.get('/crm/modules/warehouse/delete.php', {id: wid}, function(resp){
    if (resp==='OK') {
      $.get('/crm/modules/warehouse/list_partial.php', function(h){
        $('#crm-tab-content .tab-pane.active').html(h);
      });
    } else {
      alert(resp);
    }
  });
}
</script>