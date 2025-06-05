<?php
// /crm/modules/loaders/list_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Проверка прав
if (!check_access($conn, $_SESSION['user_id'], 'loaders')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

// Выбираем грузчиков
$sql = "SELECT * FROM PCRM_Loaders ORDER BY id DESC";
$res = $conn->query($sql);
if (!$res) {
    die("<div class='text-danger'>Ошибка запроса: " . $conn->error . "</div>");
}
$loaders = $res->fetch_all(MYSQLI_ASSOC);
?>
<h4>Грузчики</h4>
<button class="btn btn-primary btn-sm mb-2" onclick="editLoader(0)">
  Добавить грузчика
</button>

<table class="table table-bordered table-sm">
  <thead>
    <tr>
      <th>ID</th>
      <th>Имя (ФИО)</th>
      <th>Телефон</th>
      <th>Статус</th>
      <th>Действия</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($loaders as $l): ?>
    <tr>
      <td><?= $l['id'] ?></td>
      <td><?= htmlspecialchars($l['name']) ?></td>
      <td><?= htmlspecialchars($l['phone'] ?? '') ?></td>
      <td><?= htmlspecialchars($l['status'] ?? '') ?></td>
      <td>
        <button class="btn btn-warning btn-sm" onclick="editLoader(<?= $l['id'] ?>)">
          Редакт.
        </button>
        <button class="btn btn-danger btn-sm" onclick="deleteLoader(<?= $l['id'] ?>)">
          Удалить
        </button>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div id="loader-edit-area"></div>

<script>
function editLoader(lid) {
  $.ajax({
    url: '/crm/modules/loaders/edit_partial.php',
    data: { id: lid },
    success: function(html) {
      $('#loader-edit-area').html(html).addClass('fade-in');
    }
  });
}

function deleteLoader(lid) {
  // Вызываем глобальную функцию напрямую (она определена в app.js)
  if (typeof moveToTrash === 'function') {
    moveToTrash('loader', lid, 'Вы уверены, что хотите удалить этого грузчика?', function() {
      // Обновляем список грузчиков
      const activeTab = document.querySelector('.tab-pane.active');
      if (activeTab) {
        const moduleTab = document.querySelector('.nav-link.active[data-module*="loaders"]');
        if (moduleTab) {
          const modulePath = moduleTab.getAttribute('data-module');
          fetch(modulePath)
            .then(response => response.text())
            .then(html => activeTab.innerHTML = html)
            .catch(error => console.error('Error reloading loaders:', error));
        }
      }
    });
  } else {
    console.error('Глобальная функция moveToTrash не найдена');
    alert('Ошибка: функция удаления не найдена');
  }
}
</script>