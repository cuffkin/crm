<?php
// /crm/modules/categories/list_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Проверяем доступ
if (!check_access($conn, $_SESSION['user_id'], 'categories')) {
    die("<div class='text-danger'>Доступ запрещён</div>");
}

// Выбираем все категории/подкатегории (можно убрать статус inactive, если не нужно видеть удалённые)
$sql = "SELECT * FROM PCRM_Categories ORDER BY id DESC";
$res = $conn->query($sql);
$cats = $res->fetch_all(MYSQLI_ASSOC);

?>
<h4>Категории / Подкатегории</h4>
<button class="btn btn-primary btn-sm mb-2" onclick="editCategory(0)">Добавить новую</button>

<table class="table table-bordered">
  <thead>
    <tr>
      <th>ID</th>
      <th>Название</th>
      <th>Тип</th>
      <th>Parent ID</th>
      <th>Статус</th>
      <th>Действия</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($cats as $c): ?>
      <?php
        // Переводим type для отображения
        $typeText = ($c['type'] === 'category') 
                    ? 'категория' 
                    : (($c['type'] === 'subcategory') ? 'подкатегория' : $c['type']);
      ?>
      <tr>
        <td><?= $c['id'] ?></td>
        <td><?= htmlspecialchars($c['name']) ?></td>
        <td><?= htmlspecialchars($typeText) ?></td>
        <td><?= htmlspecialchars($c['pc_id']) ?></td>
        <td><?= htmlspecialchars($c['status']) ?></td>
        <td>
          <button class="btn btn-warning btn-sm" onclick="editCategory(<?= $c['id'] ?>)">Редактировать</button>
          <button class="btn btn-danger btn-sm"  onclick="deleteCategory(<?= $c['id'] ?>)">Удалить</button>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- Блок для загрузки формы редактирования/добавления -->
<div id="cat-edit-area"></div>

<script>
function editCategory(catId) {
  $.ajax({
    url: '/crm/modules/categories/edit_partial.php',
    data: { id: catId },
    success: function(html) {
      $('#cat-edit-area').html(html).addClass('fade-in');
    },
    error: function() {
      alert('Ошибка при загрузке формы редактирования.');
    }
  });
}

function deleteCategory(catId) {
  if (!confirm('Точно пометить категорию как inactive?')) return;
  $.get('/crm/modules/categories/delete.php', { id: catId }, function(resp) {
    if (resp === 'OK') {
      // Перезагрузим список
      $.get('/crm/modules/categories/list_partial.php', function(h) {
        $('#crm-tab-content .tab-pane.active').html(h);
      });
    } else {
      alert(resp);
    }
  });
}
</script>