<?php
// /crm/modules/products/list_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Проверяем доступ
if (!check_access($conn, $_SESSION['user_id'], 'products')) {
    die("<div class='text-danger'>Доступ запрещён</div>");
}

// Проверяем существование таблицы единиц измерения
$checkMeasurementTable = "SHOW TABLES LIKE 'PCRM_Measurement'";
$measurementTableExists = $conn->query($checkMeasurementTable)->num_rows > 0;

// Проверяем, есть ли поле default_measurement_id в продуктах
$checkColumnSQL = "SHOW COLUMNS FROM PCRM_Product LIKE 'default_measurement_id'";
$defaultMeasurementColumnExists = $conn->query($checkColumnSQL)->num_rows > 0;

// Запрос в зависимости от наличия поля default_measurement_id
$sql = "
SELECT p.*,
       c.name AS cat_name,
       sc.name AS subcat_name,
       " . ($defaultMeasurementColumnExists && $measurementTableExists ? "m.short_name as measurement_short_name," : "") . "
       (
         SELECT SUM(st.quantity)
         FROM PCRM_Stock st
         WHERE st.prod_id = p.id
       ) AS total_stock
FROM PCRM_Product p
LEFT JOIN PCRM_Categories c
       ON p.category = c.id
LEFT JOIN PCRM_Categories sc
       ON p.subcategory = sc.id
" . ($defaultMeasurementColumnExists && $measurementTableExists ? "
LEFT JOIN PCRM_Measurement m
       ON p.default_measurement_id = m.id
" : "") . "       
ORDER BY p.id DESC
";

$res = $conn->query($sql);
if (!$res) {
    die("<div class='text-danger'>Ошибка запроса: " . $conn->error . "</div>");
}
$products = $res->fetch_all(MYSQLI_ASSOC);
?>
<h4>Справочник товаров</h4>
<div class="d-flex justify-content-between mb-3">
  <button class="btn btn-primary btn-sm" onclick="editProduct(0)">Добавить товар</button>
  <div class="d-flex align-items-center gap-2">
    <select id="filter-category" class="form-select form-select-sm" style="width:auto;">
      <option value="">Все категории</option>
      <?php
      $catRes = $conn->query("SELECT id, name FROM PCRM_Categories WHERE status='active' ORDER BY name");
      $cats = $catRes->fetch_all(MYSQLI_ASSOC);
      foreach ($cats as $cat) {
        echo '<option value="' . $cat['id'] . '">' . htmlspecialchars($cat['name']) . '</option>';
      }
      ?>
    </select>
    <select id="filter-status" class="form-select form-select-sm" style="width:auto;">
      <option value="">Все статусы</option>
      <option value="active">Активные</option>
      <option value="inactive">Неактивные</option>
    </select>
  </div>
  <?php if ($measurementTableExists): ?>
  <button class="btn btn-secondary btn-sm" onclick="openNewTab('measurements/list')">
    <i class="fas fa-ruler"></i> Единицы измерения
  </button>
  <?php endif; ?>
</div>

<table class="table table-bordered" id="products-table">
  <thead>
    <tr>
      <th>ID</th>
      <th>Название</th>
      <th>SKU</th>
      <th>Категория</th>
      <th>Подкатегория</th>
      <th>Цена</th>
      <th>Себестоимость</th>
      <th>Ед. изм.</th>
      <th>Остаток (общий)</th>
      <th>Статус</th>
      <th>Действия</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($products as $p): 
    $stockVal = ($p['total_stock'] !== null) ? $p['total_stock'] : 0;
    $measurementDisplay = $defaultMeasurementColumnExists && $measurementTableExists 
        ? ($p['measurement_short_name'] ?? $p['unit_of_measure'] ?? 'шт')
        : ($p['unit_of_measure'] ?? 'шт');
    $statusColor = $p['status'] === 'active' ? 'text-success' : 'text-danger';
  ?>
    <tr data-category="<?= $p['category'] ?>" data-status="<?= $p['status'] ?>">
      <td><?= $p['id'] ?></td>
      <td><?= htmlspecialchars($p['name']) ?></td>
      <td><?= htmlspecialchars($p['sku']) ?></td>
      <td><?= htmlspecialchars($p['cat_name'] ?? '') ?></td>
      <td><?= htmlspecialchars($p['subcat_name'] ?? '') ?></td>
      <td><?= $p['price'] ?></td>
      <td><?= $p['cost_price'] ?></td>
      <td><?= htmlspecialchars($measurementDisplay) ?></td>
      <td><?= $stockVal ?> <?= htmlspecialchars($measurementDisplay) ?></td>
      <td>
        <div class="form-check form-switch">
          <input class="form-check-input status-switch" type="checkbox" data-id="<?= $p['id'] ?>" <?= $p['status']==='active'?'checked':'' ?>>
          <span class="fw-bold ms-2 <?= $statusColor ?>"><?= $p['status'] === 'active' ? 'Активен' : 'Неактивен' ?></span>
        </div>
      </td>
      <td>
        <button class="btn btn-warning btn-sm" onclick="editProduct(<?= $p['id'] ?>)">Редакт.</button>
        <button class="btn btn-danger btn-sm" onclick="deleteProduct(<?= $p['id'] ?>)">Удалить</button>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<div id="product-edit-area"></div>

<script>
$(function() {
  // Фильтрация
  $('#filter-category, #filter-status').on('change', function() {
    var cat = $('#filter-category').val();
    var stat = $('#filter-status').val();
    $('#products-table tbody tr').each(function() {
      var show = true;
      if (cat && $(this).data('category') != cat) show = false;
      if (stat && $(this).data('status') != stat) show = false;
      $(this).toggle(show);
    });
  });
  // Bootstrap Switch для статуса
  $('.status-switch').on('change', function() {
    var id = $(this).data('id');
    var newStatus = $(this).is(':checked') ? 'active' : 'inactive';
    $.post('/crm/modules/products/save.php', { id: id, status_only: 1, status: newStatus }, function(resp) {
      if (resp === 'OK') {
        $.get('/crm/modules/products/list_partial.php', function(h) {
          $('#crm-tab-content .tab-pane.active').html(h);
        });
      } else {
        alert('Ошибка смены статуса: ' + resp);
      }
    });
  });
});

function editProduct(pid) {
  $.ajax({
    url: '/crm/modules/products/edit_partial.php',
    data: { id: pid },
    success: function(html) {
      $('#product-edit-area').html(html).addClass('fade-in');
    }
  });
}

function deleteProduct(pid) {
  if (!confirm('Точно удалить (деактивировать) товар?')) return;
  $.get('/crm/modules/products/delete.php', { id: pid }, function(resp) {
    if (resp === 'OK') {
      // Перезагрузим список
      $.get('/crm/modules/products/list_partial.php', function(h) {
        $('#crm-tab-content .tab-pane.active').html(h);
      });
    } else {
      alert(resp);
    }
  });
}
</script>