<?php
// /crm/modules/categories/edit_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'categories')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

// Идентификатор категории
$id = (int)($_GET['id'] ?? 0);

// Поля по умолчанию
$name        = '';
$type        = 'category'; // по умолчанию новая запись — "category"
$pc_id       = null;
$status      = 'active';
$description = '';

// Если редактирование
if ($id > 0) {
    $st = $conn->prepare("SELECT * FROM PCRM_Categories WHERE id=?");
    $st->bind_param("i", $id);
    $st->execute();
    $res = $st->get_result();
    $c   = $res->fetch_assoc();
    if ($c) {
        $name        = $c['name'];
        $type        = $c['type'];       // 'category' или 'subcategory'
        $pc_id       = $c['pc_id'];
        $status      = $c['status'];
        $description = $c['description'];
    }
}

// Список потенциальных родительских категорий (только те, у кого type='category' AND status='active')
$catRes = $conn->query("
    SELECT id, name
    FROM PCRM_Categories
    WHERE type='category'
      AND status='active'
    ORDER BY name
");
$allParents = $catRes->fetch_all(MYSQLI_ASSOC);
?>

<div class="card">
  <div class="card-header">
    <?= $id > 0 ? 'Редактирование категории' : 'Новая категория/подкатегория' ?>
  </div>
  <div class="card-body">
    <div class="mb-3">
      <label>Название</label>
      <input type="text" id="cat-name" class="form-control"
             value="<?= htmlspecialchars($name) ?>">
    </div>
    <div class="mb-3">
      <label>Тип</label>
      <select id="cat-type" class="form-select">
        <!-- Отображаем для пользователя по-русски, в value храним "category" или "subcategory" -->
        <option value="category"    <?= ($type === 'category'    ? 'selected' : '') ?>>категория</option>
        <option value="subcategory" <?= ($type === 'subcategory' ? 'selected' : '') ?>>подкатегория</option>
      </select>
    </div>
    <div class="mb-3">
      <label>Родитель (для подкатегории)</label>
      <select id="cat-pc" class="form-select">
        <option value="">(нет)</option>
        <?php foreach ($allParents as $p): ?>
          <option value="<?= $p['id'] ?>" <?= ($pc_id == $p['id'] ? 'selected' : '') ?>>
            <?= htmlspecialchars($p['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <small class="text-muted">Если «тип» = «подкатегория», выберите родительскую категорию.</small>
    </div>
    <div class="mb-3">
      <label>Описание</label>
      <textarea id="cat-desc" class="form-control" rows="3"><?= htmlspecialchars($description) ?></textarea>
    </div>
    <div class="mb-3">
      <label>Статус</label>
      <select id="cat-status" class="form-select">
        <option value="active"   <?= ($status === 'active'   ? 'selected' : '') ?>>active</option>
        <option value="inactive" <?= ($status === 'inactive' ? 'selected' : '') ?>>inactive</option>
      </select>
    </div>

    <button class="btn btn-success" onclick="saveCategory(<?= $id ?>)">Сохранить</button>
    <button class="btn btn-secondary" onclick="$('#cat-edit-area').html('')">Отмена</button>
  </div>
</div>

<script>
function saveCategory(catId) {
  let data = {
    id:      catId,
    name:    $('#cat-name').val(),
    type:    $('#cat-type').val(),    // "category" или "subcategory"
    pc_id:   $('#cat-pc').val(),      // может быть пустым
    desc:    $('#cat-desc').val(),
    status:  $('#cat-status').val()
  };

  $.post('/crm/modules/categories/save.php', data, function(resp) {
    if (resp === 'OK') {
      // Закрыть форму
      $('#cat-edit-area').html('');
      // Перезагрузить список
      $.get('/crm/modules/categories/list_partial.php', function(h) {
        $('#crm-tab-content .tab-pane.active').html(h);
      });
    } else {
      alert(resp);
    }
  });
}
</script>