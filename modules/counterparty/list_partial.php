<?php
// /crm/modules/counterparty/list_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Проверка прав
if (!check_access($conn, $_SESSION['user_id'], 'counterparty')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

// Получаем список контрагентов
$sql = "SELECT * FROM PCRM_Counterparty ORDER BY id DESC";
$res = $conn->query($sql);
if (!$res) {
    die("<div class='text-danger'>Ошибка запроса: " . $conn->error . "</div>");
}
$contrs = $res->fetch_all(MYSQLI_ASSOC);
?>
<h4>Контрагенты</h4>
<button class="btn btn-primary btn-sm mb-2" onclick="editCounterparty(0)">
  Добавить контрагента
</button>

<table class="table table-bordered table-sm">
  <thead>
    <tr>
      <th>ID</th>
      <th>Название</th>
      <th>Тип</th>
      <th>Телефон</th>
      <th>Email</th>
      <th>ИНН</th>
      <th>КПП</th>
      <th>Адрес</th>
      <th>Действия</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($contrs as $c): ?>
    <tr>
      <td><?= $c['id'] ?></td>
      <td><?= htmlspecialchars($c['name']) ?></td>
      <td><?= htmlspecialchars($c['type']) ?></td>
      <td><?= htmlspecialchars($c['phone'] ?? '') ?></td>
      <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
      <td><?= htmlspecialchars($c['inn'] ?? '') ?></td>
      <td><?= htmlspecialchars($c['kpp'] ?? '') ?></td>
      <td><?= htmlspecialchars($c['address'] ?? '') ?></td>
      <td>
        <button class="btn btn-warning btn-sm" onclick="editCounterparty(<?= $c['id'] ?>)">
          Редакт.
        </button>
        <button class="btn btn-danger btn-sm" onclick="deleteCounterparty(<?= $c['id'] ?>)">
          Удалить
        </button>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div id="cp-edit-area"></div>

<script>
function editCounterparty(cid) {
  $.ajax({
    url: '/crm/modules/counterparty/edit_partial.php',
    data: { id: cid },
    success: function(html) {
      $('#cp-edit-area').html(html).addClass('fade-in');
    }
  });
}

function deleteCounterparty(cid) {
  // Вызываем глобальную функцию напрямую (она определена в app.js)
  if (typeof moveToTrash === 'function') {
    moveToTrash('counterparty', cid, 'Вы уверены, что хотите удалить этого контрагента?', function() {
      // Обновляем список контрагентов
      const activeTab = document.querySelector('.tab-pane.active');
      if (activeTab) {
        const moduleTab = document.querySelector('.nav-link.active[data-module*="counterparty"]');
        if (moduleTab) {
          const modulePath = moduleTab.getAttribute('data-module');
          fetch(modulePath)
            .then(response => response.text())
            .then(html => activeTab.innerHTML = html)
            .catch(error => console.error('Error reloading counterparties:', error));
        }
      }
    });
  } else {
    console.error('Глобальная функция moveToTrash не найдена');
    alert('Ошибка: функция удаления не найдена');
  }
}
</script>