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
  
  <?php if ($measurementTableExists): ?>
  <button class="btn btn-secondary btn-sm" onclick="openNewTab('measurements/list')">
    <i class="fas fa-ruler"></i> Единицы измерения
  </button>
  <?php endif; ?>
</div>

<table class="table table-bordered">
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
    // total_stock может быть NULL, если нет записей в Stock
    $stockVal = ($p['total_stock'] !== null)
                  ? $p['total_stock']
                  : 0;
                  
    // Определяем единицу измерения для отображения
    $measurementDisplay = $defaultMeasurementColumnExists && $measurementTableExists 
        ? ($p['measurement_short_name'] ?? $p['unit_of_measure'] ?? 'шт')
        : ($p['unit_of_measure'] ?? 'шт');
  ?>
    <tr>
      <td><?= $p['id'] ?></td>
      <td><?= htmlspecialchars($p['name']) ?></td>
      <td><?= htmlspecialchars($p['sku']) ?></td>
      <td><?= htmlspecialchars($p['cat_name'] ?? '') ?></td>
      <td><?= htmlspecialchars($p['subcat_name'] ?? '') ?></td>
      <td><?= $p['price'] ?></td>
      <td><?= $p['cost_price'] ?></td>
      <td><?= htmlspecialchars($measurementDisplay) ?></td>
      <td><?= $stockVal ?> <?= htmlspecialchars($measurementDisplay) ?></td>
      <td><?= $p['status'] ?></td>
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