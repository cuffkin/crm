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
  // Вызываем глобальную функцию напрямую (она определена в app.js)
  if (typeof moveToTrash === 'function') {
    moveToTrash('warehouse', wid, 'Вы уверены, что хотите удалить этот склад?', function() {
      // Обновляем список складов
      const activeTab = document.querySelector('.tab-pane.active');
      if (activeTab) {
        const moduleTab = document.querySelector('.nav-link.active[data-module*="warehouse"]');
        if (moduleTab) {
          const modulePath = moduleTab.getAttribute('data-module');
          fetch(modulePath)
            .then(response => response.text())
            .then(html => activeTab.innerHTML = html)
            .catch(error => console.error('Error reloading warehouses:', error));
        }
      }
    });
  } else {
    console.error('Глобальная функция moveToTrash не найдена');
    alert('Ошибка: функция удаления не найдена');
  }
}
</script>