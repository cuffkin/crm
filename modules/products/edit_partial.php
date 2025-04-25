<?php
// /crm/modules/products/edit_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'products')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

$id = (int)($_GET['id'] ?? 0);

// Инициализация полей
$name = '';
$sku = '';
$description = '';
$category = null;
$subcategory = null;
$price = '0.00';
$cost_price = '0.00';
$unit_of_measure = 'шт';
$weight = '0.000';
$volume = '0.000';
$status = 'active';

// Если редактирование (id>0) — загрузим из БД
if ($id > 0) {
    $st = $conn->prepare("SELECT * FROM PCRM_Product WHERE id=?");
    $st->bind_param("i", $id);
    $st->execute();
    $res = $st->get_result();
    $prod = $res->fetch_assoc();
    if ($prod) {
        $name           = $prod['name'];
        $sku            = $prod['sku'];
        $description    = $prod['description'];
        $category       = $prod['category'];
        $subcategory    = $prod['subcategory'];
        $price          = $prod['price'];
        $cost_price     = $prod['cost_price'];
        $unit_of_measure= $prod['unit_of_measure'];
        $weight         = $prod['weight'];
        $volume         = $prod['volume'];
        $status         = $prod['status'];
    }
}

// Список категорий (type='категория')
$catRes = $conn->query("SELECT id,name FROM PCRM_Categories WHERE type='категория' AND status='active' ORDER BY name");
$allCats = $catRes->fetch_all(MYSQLI_ASSOC);

// Список подкатегорий (type='подкатегория')
$subCatRes = $conn->query("SELECT id,name FROM PCRM_Categories WHERE type='подкатегория' AND status='active' ORDER BY name");
$allSubCats = $subCatRes->fetch_all(MYSQLI_ASSOC);
?>

<div class="card">
  <div class="card-header">
    <?= $id > 0 ? 'Редактирование товара' : 'Новый товар' ?>
  </div>
  <div class="card-body">

    <div class="mb-3">
      <label>Название товара</label>
      <input type="text" id="p-name" class="form-control" value="<?= htmlspecialchars($name) ?>">
    </div>

    <div class="mb-3">
      <label>SKU (артикул)</label>
      <input type="text" id="p-sku" class="form-control" value="<?= htmlspecialchars($sku) ?>">
    </div>

    <div class="mb-3">
      <label>Категория</label>
      <select id="p-category" class="form-select">
        <option value="">(нет)</option>
        <?php foreach ($allCats as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($category == $c['id'] ? 'selected' : '') ?>>
            <?= htmlspecialchars($c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-3">
      <label>Подкатегория</label>
      <select id="p-subcategory" class="form-select">
        <option value="">(нет)</option>
        <?php foreach ($allSubCats as $sc): ?>
          <option value="<?= $sc['id'] ?>" <?= ($subcategory == $sc['id'] ? 'selected' : '') ?>>
            <?= htmlspecialchars($sc['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-3">
      <label>Цена продажи</label>
      <input type="number" step="0.01" id="p-price" class="form-control" value="<?= $price ?>">
    </div>

    <div class="mb-3">
      <label>Себестоимость</label>
      <input type="number" step="0.01" id="p-costprice" class="form-control" value="<?= $cost_price ?>">
    </div>

    <div class="mb-3">
      <label>Описание</label>
      <textarea id="p-description" class="form-control" rows="3"><?= htmlspecialchars($description) ?></textarea>
    </div>

    <div class="mb-3 row">
      <div class="col">
        <label>Единица измерения</label>
        <input type="text" id="p-unit" class="form-control" value="<?= htmlspecialchars($unit_of_measure) ?>">
      </div>
      <div class="col">
        <label>Вес (кг)</label>
        <input type="number" step="0.001" id="p-weight" class="form-control" value="<?= $weight ?>">
      </div>
      <div class="col">
        <label>Объём (м³)</label>
        <input type="number" step="0.001" id="p-volume" class="form-control" value="<?= $volume ?>">
      </div>
    </div>

    <div class="mb-3">
      <label>Статус</label>
      <select id="p-status" class="form-select">
        <option value="active"   <?= ($status == 'active'   ? 'selected' : '') ?>>active</option>
        <option value="inactive" <?= ($status == 'inactive' ? 'selected' : '') ?>>inactive</option>
      </select>
    </div>

    <button class="btn btn-success" onclick="saveProduct(<?= $id ?>)">Сохранить</button>
    <button class="btn btn-secondary" onclick="$('#product-edit-area').html('')">Отмена</button>

  </div>
</div>

<script>
function saveProduct(pid) {
  let data = {
    id: pid,
    name: $('#p-name').val(),
    sku: $('#p-sku').val(),
    category: $('#p-category').val(),
    subcategory: $('#p-subcategory').val(),
    price: $('#p-price').val(),
    cost_price: $('#p-costprice').val(),
    description: $('#p-description').val(),
    unit_of_measure: $('#p-unit').val(),
    weight: $('#p-weight').val(),
    volume: $('#p-volume').val(),
    status: $('#p-status').val()
  };

  $.post('/crm/modules/products/save.php', data, function(resp) {
    if (resp === 'OK') {
      // Закрываем форму
      $('#product-edit-area').html('');
      // Перезагружаем список
      $.get('/crm/modules/products/list_partial.php', function(h) {
        $('#crm-tab-content .tab-pane.active').html(h);
      });
    } else {
      alert(resp);
    }
  });
}
</script>