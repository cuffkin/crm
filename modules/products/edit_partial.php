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
$default_measurement_id = null;
$weight = '0.000';
$volume = '0.000';
$status = 'active';

// Проверка существования таблицы единиц измерения
$checkMeasurementTable = "SHOW TABLES LIKE 'PCRM_Measurement'";
$measurementTableExists = $conn->query($checkMeasurementTable)->num_rows > 0;

// Если таблицы не существует, используем старое поле unit_of_measure
$useOldUnitOfMeasure = !$measurementTableExists;
$unit_of_measure = 'шт';

// Связанные единицы измерения
$linkedMeasurements = [];

// Если редактирование (id>0) — загрузим из БД
if ($id > 0) {
    $st = $conn->prepare("SELECT * FROM PCRM_Product WHERE id=?");
    $st->bind_param("i", $id);
    $st->execute();
    $res = $st->get_result();
    $prod = $res->fetch_assoc();
    if ($prod) {
        $name = $prod['name'];
        $sku = $prod['sku'];
        $description = $prod['description'];
        $category = $prod['category'];
        $subcategory = $prod['subcategory'];
        $price = $prod['price'];
        $cost_price = $prod['cost_price'];
        
        // Проверяем наличие нового поля default_measurement_id
        $default_measurement_id = isset($prod['default_measurement_id']) ? $prod['default_measurement_id'] : null;
        
        // Если используем старый формат
        if ($useOldUnitOfMeasure || $default_measurement_id === null) {
            $unit_of_measure = $prod['unit_of_measure'];
        }
        
        $weight = $prod['weight'];
        $volume = $prod['volume'];
        $status = $prod['status'];
        
        // Загружаем связанные единицы измерения
        if ($measurementTableExists && !$useOldUnitOfMeasure) {
            $linkSql = "SELECT pm.*, m.name, m.short_name 
                        FROM PCRM_Product_Measurement pm 
                        JOIN PCRM_Measurement m ON pm.measurement_id = m.id 
                        WHERE pm.product_id = ?";
            $linkStmt = $conn->prepare($linkSql);
            $linkStmt->bind_param("i", $id);
            $linkStmt->execute();
            $linkResult = $linkStmt->get_result();
            
            while ($link = $linkResult->fetch_assoc()) {
                $linkedMeasurements[] = $link;
            }
        }
    }
}

// Список категорий (type='категория')
$catRes = $conn->query("SELECT id,name FROM PCRM_Categories WHERE type='category' AND status='active' ORDER BY name");
$allCats = $catRes->fetch_all(MYSQLI_ASSOC);

// Список подкатегорий (type='подкатегория')
$subCatRes = $conn->query("SELECT id,name FROM PCRM_Categories WHERE type='subcategory' AND status='active' ORDER BY name");
$allSubCats = $subCatRes->fetch_all(MYSQLI_ASSOC);

// Получаем список единиц измерения, если таблица существует
$measurements = [];
if ($measurementTableExists) {
    $measurementSql = "SELECT * FROM PCRM_Measurement WHERE status = 'active' ORDER BY name";
    $measurementResult = $conn->query($measurementSql);
    $measurements = $measurementResult->fetch_all(MYSQLI_ASSOC);
    
    // Если нет единиц измерения или не выбрана по умолчанию, 
    // попробуем найти единицу измерения с коротким именем, соответствующим текущему значению
    if (($default_measurement_id === null || $default_measurement_id <= 0) && !empty($unit_of_measure)) {
        foreach ($measurements as $m) {
            if ($m['short_name'] === $unit_of_measure) {
                $default_measurement_id = $m['id'];
                break;
            }
        }
    }
}
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
      <?php if ($useOldUnitOfMeasure || empty($measurements)): ?>
      <div class="col">
        <label>Единица измерения</label>
        <input type="text" id="p-unit" class="form-control" value="<?= htmlspecialchars($unit_of_measure) ?>">
      </div>
      <?php else: ?>
      <div class="col">
        <label>Основная единица измерения</label>
        <select id="p-default-measurement" class="form-select">
          <option value="">(выберите единицу измерения)</option>
          <?php foreach ($measurements as $m): ?>
            <option value="<?= $m['id'] ?>" <?= ($default_measurement_id == $m['id'] ? 'selected' : '') ?>>
              <?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['short_name']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col">
        <label>Вес (кг)</label>
        <input type="number" step="0.001" id="p-weight" class="form-control" value="<?= $weight ?>">
      </div>
      <div class="col">
        <label>Объём (м³)</label>
        <input type="number" step="0.001" id="p-volume" class="form-control" value="<?= $volume ?>">
      </div>
    </div>

    <?php if (!$useOldUnitOfMeasure && !empty($measurements)): ?>
    <div class="mb-3">
      <label>Дополнительные единицы измерения</label>
      <div id="additional-measurements">
        <?php if (empty($linkedMeasurements)): ?>
          <div class="row mb-2 measurement-row">
            <div class="col-5">
              <select class="form-select measurement-select">
                <option value="">(выберите единицу измерения)</option>
                <?php foreach ($measurements as $m): ?>
                  <option value="<?= $m['id'] ?>">
                    <?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['short_name']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-5">
              <div class="input-group">
                <span class="input-group-text">Коэффициент</span>
                <input type="number" step="0.0001" class="form-control conversion-factor" value="1.0000">
              </div>
            </div>
            <div class="col-2">
              <button type="button" class="btn btn-danger btn-sm remove-measurement">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($linkedMeasurements as $lm): ?>
            <div class="row mb-2 measurement-row" data-id="<?= $lm['id'] ?>">
              <div class="col-5">
                <select class="form-select measurement-select">
                  <option value="">(выберите единицу измерения)</option>
                  <?php foreach ($measurements as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= ($lm['measurement_id'] == $m['id'] ? 'selected' : '') ?>>
                      <?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['short_name']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-5">
                <div class="input-group">
                  <span class="input-group-text">Коэффициент</span>
                  <input type="number" step="0.0001" class="form-control conversion-factor" 
                         value="<?= $lm['conversion_factor'] ?>">
                </div>
              </div>
              <div class="col-2">
                <button type="button" class="btn btn-danger btn-sm remove-measurement">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <button type="button" class="btn btn-sm btn-secondary mt-2" id="add-measurement">
        <i class="fas fa-plus"></i> Добавить единицу измерения
      </button>
    </div>
    <?php endif; ?>

    <div class="mb-3">
      <label>Статус</label><br>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="p-status" <?= ($status === 'active' ? 'checked' : '') ?>>
        <label class="form-check-label fw-bold ms-2" for="p-status">
          <span class="<?= $status === 'active' ? 'text-success' : 'text-danger' ?>"><?= $status === 'active' ? 'Активен' : 'Неактивен' ?></span>
        </label>
      </div>
    </div>

    <!-- В будущем: кнопка для выбора товара через модальное окно -->
    <!-- <button class="btn btn-outline-info" id="open-product-picker">Выбрать товар из дерева</button> -->

    <button class="btn btn-success" onclick="saveProduct(<?= $id ?>)">Сохранить</button>
    <button class="btn btn-secondary" onclick="closeEditForm()">Отмена</button>

  </div>
</div>

<script>
// Функция закрытия формы редактирования и возврата к списку товаров
function closeEditForm() {
  $('#product-edit-area').html('').removeClass('fade-in');
  $('.product-list-view').removeClass('hidden');
}

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
    weight: $('#p-weight').val(),
    volume: $('#p-volume').val(),
    status: $('#p-status').is(':checked') ? 'active' : 'inactive'
  };

  <?php if ($useOldUnitOfMeasure || empty($measurements)): ?>
  // Используем старое поле unit_of_measure
  data.unit_of_measure = $('#p-unit').val();
  <?php else: ?>
  // Используем новое поле default_measurement_id
  data.default_measurement_id = $('#p-default-measurement').val();
  
  // Добавляем дополнительные единицы измерения
  let additionalMeasurements = [];
  $('.measurement-row').each(function() {
    const row = $(this);
    const measurementId = row.find('.measurement-select').val();
    if (measurementId) {
      additionalMeasurements.push({
        id: row.data('id') || 0,
        measurement_id: measurementId,
        conversion_factor: row.find('.conversion-factor').val()
      });
    }
  });
  data.additional_measurements = JSON.stringify(additionalMeasurements);
  <?php endif; ?>

  $.post('/crm/modules/products/save.php', data, function(resp) {
    if (resp === 'OK') {
      // Закрываем форму
      closeEditForm();
      // Перезагружаем список
      $.get('/crm/modules/products/list_partial.php', function(h) {
        $('#crm-tab-content .tab-pane.active').html(h);
      });
    } else {
      alert(resp);
    }
  });
}

<?php if (!$useOldUnitOfMeasure && !empty($measurements)): ?>
// Обработчик для добавления новой единицы измерения
$('#add-measurement').on('click', function() {
  const template = `
    <div class="row mb-2 measurement-row">
      <div class="col-5">
        <select class="form-select measurement-select">
          <option value="">(выберите единицу измерения)</option>
          <?php foreach ($measurements as $m): ?>
            <option value="<?= $m['id'] ?>">
              <?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['short_name']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-5">
        <div class="input-group">
          <span class="input-group-text">Коэффициент</span>
          <input type="number" step="0.0001" class="form-control conversion-factor" value="1.0000">
        </div>
      </div>
      <div class="col-2">
        <button type="button" class="btn btn-danger btn-sm remove-measurement">
          <i class="fas fa-trash"></i>
        </button>
      </div>
    </div>
  `;
  $('#additional-measurements').append(template);
});

// Обработчик для удаления единицы измерения
$(document).on('click', '.remove-measurement', function() {
  $(this).closest('.measurement-row').remove();
});

// Обновление списка доступных единиц при изменении основной единицы
$('#p-default-measurement').on('change', function() {
  const defaultMeasurementId = $(this).val();
  
  // Обновляем списки в дополнительных единицах
  $('.measurement-select').each(function() {
    const select = $(this);
    const currentValue = select.val();
    
    // Отключаем выбранную основную единицу в дополнительных
    select.find('option').each(function() {
      const option = $(this);
      const optionValue = option.val();
      
      if (optionValue !== '') {
        if (optionValue === defaultMeasurementId) {
          option.prop('disabled', true);
          // Если выбрана та же, что установили основной - сбрасываем
          if (currentValue === defaultMeasurementId) {
            select.val('');
          }
        } else {
          option.prop('disabled', false);
        }
      }
    });
  });
});

// Инициализация дизейблинга единиц измерения
$(document).ready(function() {
  $('#p-default-measurement').trigger('change');
});
<?php endif; ?>

$(function() {
  $('#p-status').on('change', function() {
    var label = $(this).closest('.form-check').find('span');
    if ($(this).is(':checked')) {
      label.text('Активен').removeClass('text-danger').addClass('text-success');
    } else {
      label.text('Неактивен').removeClass('text-success').addClass('text-danger');
    }
  });
});
</script>