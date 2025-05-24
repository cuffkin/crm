<?php
// /crm/modules/products/list_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Проверяем доступ
if (!check_access($conn, $_SESSION['user_id'], 'products')) {
    die("<div class='text-danger'>Доступ запрещён</div>");
}

// Получаем все категории (для дерева)
$catRes = $conn->query("SELECT id, name, pc_id, status FROM PCRM_Categories ORDER BY name");
$allCats = [];
while ($row = $catRes->fetch_assoc()) {
    $allCats[] = $row;
}
// Строим дерево категорий
function buildCatTree($cats, $parent = null) {
    $branch = [];
    foreach ($cats as $cat) {
        if ((string)$cat['pc_id'] === (string)$parent) {
            $children = buildCatTree($cats, $cat['id']);
            if ($children) $cat['children'] = $children;
            $branch[] = $cat;
        }
    }
    return $branch;
}
$catTree = buildCatTree($allCats, null);

// Получаем все товары
$prodRes = $conn->query("SELECT p.*, c.name AS cat_name FROM PCRM_Product p LEFT JOIN PCRM_Categories c ON p.category = c.id ORDER BY p.name");
$allProducts = [];
while ($row = $prodRes->fetch_assoc()) {
    $allProducts[] = $row;
}

?>
<style>
.erp-panel { background: #f8f9fa; border-bottom: 1px solid #dee2e6; padding: 12px 16px; }
.erp-panel .btn, .erp-panel input, .erp-panel select { margin-right: 8px; }
.erp-cats-tree { min-width: 220px; max-width: 260px; border-right: 1px solid #dee2e6; background: #fff; height: 100%; overflow-y: auto; }
.erp-cats-tree ul { list-style: none; padding-left: 18px; }
.erp-cats-tree li { cursor: pointer; padding: 2px 0; }
.erp-cats-tree .cat-active { font-weight: bold; color: #0d6efd; }
.erp-cats-tree .cat-inactive { color: #bbb; }
.erp-products-table-wrap { flex: 1 1 0; overflow-x: auto; }
.erp-products-table { width: 100%; background: #fff; }
.erp-products-table th, .erp-products-table td { vertical-align: middle; }
.erp-products-table tr.selected { background: #e9f7ef; }
.switch { position: relative; display: inline-block; width: 38px; height: 22px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .3s; border-radius: 22px; }
.slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
input:checked + .slider { background-color: #28a745; }
input:not(:checked) + .slider { background-color: #bbb; }
input:checked + .slider:before { transform: translateX(16px); }
.edit-icon { cursor: pointer; color: #6c757d; }
.edit-icon:hover { color: #0d6efd; }
/* Стили для формы редактирования - отображение/скрытие */
#product-edit-area { display: none; }
#product-edit-area.fade-in { display: block; animation: fadeIn 0.3s; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
/* Скрываем основной контент при отображении формы */
.product-list-view.hidden { display: none; }
/* Стили для кликабельных полей */
.editable { 
  cursor: pointer; 
  position: relative;
  transition: background-color 0.2s;
}
.editable:hover { 
  background-color: #f0f8ff; 
}
.editable:hover::after {
  content: "\f044"; /* Иконка карандаша */
  font-family: "Font Awesome 5 Free";
  font-weight: 900;
  position: absolute;
  right: 5px;
  color: #6c757d;
  font-size: 12px;
}
/* Активное редактирование */
.editing {
  padding: 0 !important;
  position: relative;
}
.editing input, .editing select {
  width: 100%;
  border: 1px solid #0d6efd;
  padding: 4px 8px;
  border-radius: 4px;
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 10;
  height: 100%;
  box-sizing: border-box;
  background: white;
}

/* Сохраняем ширину столбцов при редактировании */
.erp-products-table th, .erp-products-table td {
  white-space: nowrap;
  min-width: 100px;
}
.erp-products-table th:first-child, .erp-products-table td:first-child {
  min-width: 200px; /* Ширина для названия товара */
  max-width: 300px;
  overflow: hidden;
  text-overflow: ellipsis;
}
.erp-products-table th:nth-child(4), .erp-products-table td:nth-child(4) {
  min-width: 80px; /* Ширина для единицы измерения */
}
.erp-products-table th:nth-child(5), .erp-products-table td:nth-child(5) {
  min-width: 120px; /* Ширина для цены */
}
</style>

<div class="container-fluid mt-3">
  <!-- Контейнер для формы редактирования -->
  <div id="product-edit-area"></div>
  
  <!-- Основной контент со списком продуктов -->
  <div class="product-list-view">
    <div class="erp-panel d-flex align-items-center flex-wrap gap-2">
      <button class="btn btn-primary btn-sm" id="btn-add-product">Товар</button>
      <button class="btn btn-outline-primary btn-sm">Услуга</button>
      <button class="btn btn-outline-secondary btn-sm" id="btn-filter-products">Фильтр</button>
      <input type="text" id="erp-search" class="form-control form-control-sm" style="width:220px;" placeholder="Наименование, код или артикул">
      <button class="btn btn-outline-secondary btn-sm">Печать</button>
      <button class="btn btn-outline-secondary btn-sm">Импорт</button>
      <button class="btn btn-outline-secondary btn-sm">Экспорт</button>
      <button class="btn btn-outline-secondary btn-sm"><i class="fas fa-cog"></i></button>
    </div>
    <div class="d-flex" style="height:calc(100vh - 120px); min-height:500px;">
      <div class="erp-cats-tree p-2" id="erp-cats-tree">
        <?php
        function renderCatTree($tree, $activeId = null) {
          echo '<ul>';
          foreach ($tree as $cat) {
            $cls = ($cat['status']!=='active'?'cat-inactive ':'') . ($cat['id']==$activeId?'cat-active':'');
            echo '<li data-id="'.$cat['id'].'" class="'.$cls.'">'.htmlspecialchars($cat['name']);
            if (!empty($cat['children'])) renderCatTree($cat['children'], $activeId);
            echo '</li>';
          }
          echo '</ul>';
        }
        renderCatTree($catTree, null);
        ?>
      </div>
      <div class="erp-products-table-wrap p-3">
        <table class="table table-sm erp-products-table" id="erp-products-table">
          <thead>
            <tr>
              <th class="sortable-header" data-sort="name">
                Наименование 
                <i class="fas fa-sort sort-icon"></i>
              </th>
              <th class="sortable-header" data-sort="id">
                Код 
                <i class="fas fa-sort sort-icon"></i>
              </th>
              <th class="sortable-header" data-sort="sku">
                Артикул 
                <i class="fas fa-sort sort-icon"></i>
              </th>
              <th class="sortable-header" data-sort="unit_of_measure">
                Ед. изм. 
                <i class="fas fa-sort sort-icon"></i>
              </th>
              <th class="sortable-header" data-sort="price">
                Цена продажи 
                <i class="fas fa-sort sort-icon"></i>
              </th>
              <th style="width:60px;">Статус</th>
              <th style="width:40px;" title="Полное редактирование">Ред.</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allProducts as $p): ?>
            <tr data-cat="<?= $p['category'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>" data-sku="<?= htmlspecialchars($p['sku']) ?>" data-code="<?= htmlspecialchars($p['id']) ?>" data-id="<?= $p['id'] ?>">
              <td class="editable" data-field="name" data-id="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></td>
              <td><?= htmlspecialchars($p['id']) ?></td>
              <td class="editable" data-field="sku" data-id="<?= $p['id'] ?>"><?= htmlspecialchars($p['sku']) ?></td>
              <td class="editable" data-field="unit_of_measure" data-id="<?= $p['id'] ?>"><?= htmlspecialchars($p['unit_of_measure'] ?? 'шт') ?></td>
              <td class="editable" data-field="price" data-id="<?= $p['id'] ?>"><?= number_format($p['price'], 2, ',', ' ') ?></td>
              <td>
                <label class="switch">
                  <input type="checkbox" class="status-switch" data-id="<?= $p['id'] ?>" <?= $p['status']==='active'?'checked':'' ?>>
                  <span class="slider"></span>
                </label>
              </td>
              <td>
                <i class="fas fa-edit edit-icon" data-id="<?= $p['id'] ?>" title="Полное редактирование"></i>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script>
// --- JS для фильтрации, дерева, выделения, кнопок ---
let selectedCat = null;
let selectedRow = null;
function filterProducts() {
  let search = $('#erp-search').val().toLowerCase();
  $('#erp-products-table tbody tr').each(function() {
    let name = $(this).data('name').toLowerCase();
    let sku = $(this).data('sku').toLowerCase();
    let code = $(this).data('code').toString();
    let cat = $(this).data('cat');
    let show = true;
    if (selectedCat && cat != selectedCat) show = false;
    if (search && !(name.includes(search) || sku.includes(search) || code.includes(search))) show = false;
    $(this).toggle(show);
  });
}
$('#erp-search').on('input', filterProducts);
$('#erp-cats-tree').on('click', 'li[data-id]', function(e) {
  e.stopPropagation();
  selectedCat = $(this).data('id').toString();
  $('#erp-cats-tree li').removeClass('cat-active');
  $(this).addClass('cat-active');
  filterProducts();
});
// --- Выделение строки ---
$('#erp-products-table').on('click', 'tbody tr', function() {
  $('#erp-products-table tr').removeClass('selected');
  $(this).addClass('selected');
  selectedRow = $(this);
});
// --- Обработчик иконки редактирования ---
$('#erp-products-table').on('click', '.edit-icon', function(e) {
  e.stopPropagation(); // Останавливаем всплытие, чтобы не выделялась строка
  let id = $(this).data('id');
  if (id) editProduct(id);
});
// --- Двойной клик по строке для редактирования ---
$('#erp-products-table').on('dblclick', 'tbody tr', function() {
  let id = $(this).data('id');
  if (id) editProduct(id);
});
// --- Кнопки ---
$('#btn-add-product').on('click', function() { window.editProduct(0); });

// Исключаем кнопку фильтра из общего обработчика
$('.erp-panel .btn-outline-primary, .erp-panel .btn-outline-secondary').not('#btn-filter-products').on('click', function() {
  alert('В разработке');
});
// --- Слайдер статуса ---
$('#erp-products-table').on('change', '.status-switch', function(e) {
  e.stopPropagation(); // Предотвращаем выделение строки при клике на переключатель
  let id = $(this).data('id');
  let newStatus = $(this).is(':checked') ? 'active' : 'inactive';
  let slider = $(this).next('.slider');
  $.post('/crm/modules/products/save.php', { id: id, status_only: 1, status: newStatus }, function(resp) {
    if (resp !== 'OK') {
      alert('Ошибка смены статуса: ' + resp);
      // Откатываем чекбокс
      $(this).prop('checked', !$(this).is(':checked'));
    }
  }.bind(this));
});
// --- Функция редактирования продукта ---
window.editProduct = function(pid) {
  $.ajax({
    url: '/crm/modules/products/edit_partial.php',
    data: { id: pid },
    success: function(html) {
      // Показываем форму редактирования и скрываем основной интерфейс
      $('#product-edit-area').html(html).addClass('fade-in');
      $('.product-list-view').addClass('hidden');
    }
  });
};

// --- Быстрое редактирование по клику на ячейку ---
$('#erp-products-table').on('click', '.editable', function(e) {
  // Предотвращаем всплытие, чтобы не выделялась строка
  e.stopPropagation();
  
  // Если уже редактируем, не делаем ничего
  if ($('.editing').length > 0) return;
  
  const cell = $(this);
  const fieldName = cell.data('field');
  const productId = cell.data('id');
  const currentValue = cell.text().trim();
  
  // Сохраняем оригинальное содержимое и размеры
  cell.data('original', cell.html());
  
  // Выбираем тип поля ввода в зависимости от поля
  let inputHtml;
  
  if (fieldName === 'price') {
    // Преобразуем значение из формата с пробелами и запятой в формат с точкой
    const numValue = currentValue.replace(/\s/g, '').replace(',', '.');
    inputHtml = `<input type="number" step="0.01" value="${numValue}" class="quick-edit-input">`;
    
    // Устанавливаем содержимое и фокус
    cell.html(inputHtml).addClass('editing');
    cell.find('input').focus().select();
    
    // Обработчики для input
    setupInputHandlers(cell, fieldName, productId);
    
  } else if (fieldName === 'unit_of_measure') {
    // Для единицы измерения используем select
    // Сначала показываем input с анимацией загрузки пока получаем список единиц измерения
    cell.html('<input type="text" value="загрузка..." readonly class="quick-edit-input">').addClass('editing');
    
    // Получаем список единиц измерения через AJAX
    $.ajax({
      url: '/crm/modules/products/get_measurements.php',
      method: 'GET',
      success: function(response) {
        try {
          // Если response уже объект (jQuery автоматически распарсил JSON)
          let measurements = [];
          let debug = null;
          
          if (typeof response === 'object') {
            if (response.measurements) {
              measurements = response.measurements;
              debug = response.debug;
            } else {
              measurements = response; // Старый формат ответа
            }
          } else {
            // Парсим строку JSON если jQuery не сделал это за нас
            const parsed = JSON.parse(response);
            if (parsed.measurements) {
              measurements = parsed.measurements;
              debug = parsed.debug;
            } else {
              measurements = parsed;
            }
          }
          
          console.log('Debug info:', debug);
          
          if (!measurements || measurements.length === 0) {
            // Если единиц измерения нет, используем базовый набор
            measurements = [
              {id: 1, name: 'Штука', short_name: 'шт'},
              {id: 2, name: 'Килограмм', short_name: 'кг'},
              {id: 3, name: 'Метр', short_name: 'м'}
            ];
          }
          
          // Создаем выпадающий список с единицами измерения
          let selectHtml = '<select class="quick-edit-input">';
          let currentFound = false;
          
          // Добавляем все единицы из списка
          for (const m of measurements) {
            const selected = (m.short_name === currentValue) ? 'selected' : '';
            if (m.short_name === currentValue) currentFound = true;
            selectHtml += `<option value="${m.short_name}" ${selected}>${m.name} (${m.short_name})</option>`;
          }
          
          // Добавляем текущее значение, если его нет в списке
          if (!currentFound && currentValue) {
            selectHtml += `<option value="${currentValue}" selected>${currentValue}</option>`;
          }
          
          selectHtml += '</select>';
          
          // Обновляем содержимое ячейки
          cell.html(selectHtml);
          
          // Устанавливаем фокус на селект
          cell.find('select').focus();
          
          // Обработчики для select
          cell.find('select').on('blur', function() {
            saveQuickEdit(cell, fieldName, productId, $(this).val());
          });
          
          cell.find('select').on('keydown', function(e) {
            if (e.key === 'Enter') {
              saveQuickEdit(cell, fieldName, productId, $(this).val());
            } else if (e.key === 'Escape') {
              // Отмена редактирования
              cell.html(cell.data('original')).removeClass('editing');
            }
          });
          
        } catch (e) {
          console.error('Error processing measurements:', e, response);
          // Если ошибка парсинга - используем обычный input
          const inputHtml = `<input type="text" value="${currentValue}" class="quick-edit-input">`;
          cell.html(inputHtml);
          cell.find('input').focus().select();
          setupInputHandlers(cell, fieldName, productId);
        }
      },
      error: function(xhr, status, error) {
        console.error('AJAX error:', status, error);
        // В случае ошибки используем обычный input
        const inputHtml = `<input type="text" value="${currentValue}" class="quick-edit-input">`;
        cell.html(inputHtml);
        cell.find('input').focus().select();
        setupInputHandlers(cell, fieldName, productId);
      }
    });
    
  } else {
    // Для остальных полей - обычный текстовый ввод
    inputHtml = `<input type="text" value="${currentValue}" class="quick-edit-input">`;
    
    // Устанавливаем содержимое и фокус
    cell.html(inputHtml).addClass('editing');
    cell.find('input').focus().select();
    
    // Обработчики для input
    setupInputHandlers(cell, fieldName, productId);
  }
});

// Функция для установки обработчиков ввода
function setupInputHandlers(cell, fieldName, productId) {
  // Обработчик потери фокуса - сохраняет изменения
  cell.find('input').on('blur', function() {
    saveQuickEdit(cell, fieldName, productId, $(this).val());
  });
  
  // Обработчик нажатия Enter - сохраняет изменения
  cell.find('input').on('keydown', function(e) {
    if (e.key === 'Enter') {
      saveQuickEdit(cell, fieldName, productId, $(this).val());
    } else if (e.key === 'Escape') {
      // Отмена редактирования
      cell.html(cell.data('original')).removeClass('editing');
    }
  });
}

// Функция сохранения быстрого редактирования
function saveQuickEdit(cell, field, productId, newValue) {
  // Формируем данные для отправки
  const data = {
    id: productId,
    quick_edit: 1,
    field: field,
    value: newValue
  };
  
  // Отправляем запрос на сервер
  $.post('/crm/modules/products/save.php', data, function(resp) {
    if (resp === 'OK') {
      // Успешно сохранено, обновляем отображение
      
      // Форматируем значение для отображения
      let displayValue = newValue;
      if (field === 'price') {
        // Форматируем цену для отображения
        displayValue = parseFloat(newValue).toLocaleString('ru-RU', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        }).replace('.', ',');
      }
      
      cell.html(displayValue).removeClass('editing');
      
      // Обновляем data-атрибуты строки
      if (field === 'name') {
        cell.closest('tr').data('name', newValue);
      } else if (field === 'sku') {
        cell.closest('tr').data('sku', newValue);
      }
    } else {
      // Ошибка, восстанавливаем исходное значение
      alert('Ошибка сохранения: ' + resp);
      cell.html(cell.data('original')).removeClass('editing');
    }
  });
}

// Закрытие редактирования при клике вне поля
$(document).on('click', function(e) {
  if (!$(e.target).closest('.quick-edit-input').length && $('.editing').length > 0) {
    const input = $('.editing').find('input');
    if (input.length) {
      const cell = input.closest('.editable');
      const fieldName = cell.data('field');
      const productId = cell.data('id');
      saveQuickEdit(cell, fieldName, productId, input.val());
    }
  }
});

// === СОРТИРОВКА ТАБЛИЦЫ ===
let currentSort = { field: null, direction: 'asc' };

$('.sortable-header').on('click', function() {
  const field = $(this).data('sort');
  
  // Определяем направление сортировки
  if (currentSort.field === field) {
    currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
  } else {
    currentSort.direction = 'asc';
  }
  currentSort.field = field;
  
  // Обновляем иконки
  $('.sortable-header').removeClass('sort-asc sort-desc');
  $('.sort-icon').removeClass('fa-sort-up fa-sort-down').addClass('fa-sort');
  
  $(this).addClass('sort-' + currentSort.direction);
  $(this).find('.sort-icon').removeClass('fa-sort').addClass(
    currentSort.direction === 'asc' ? 'fa-sort-up' : 'fa-sort-down'
  );
  
  // Сортируем таблицу
  sortTable(field, currentSort.direction);
});

function sortTable(field, direction) {
  const tbody = $('#erp-products-table tbody');
  const rows = tbody.find('tr').toArray();
  
  rows.sort(function(a, b) {
    let aVal, bVal;
    
    if (field === 'price') {
      // Для цены берем числовое значение
      aVal = parseFloat($(a).find('[data-field="price"]').text().replace(/[^\d,.-]/g, '').replace(',', '.')) || 0;
      bVal = parseFloat($(b).find('[data-field="price"]').text().replace(/[^\d,.-]/g, '').replace(',', '.')) || 0;
    } else if (field === 'id') {
      // Для кода сравниваем как числа
      aVal = parseInt($(a).data('id')) || 0;
      bVal = parseInt($(b).data('id')) || 0;
    } else {
      // Для текстовых полей
      if (field === 'name') {
        aVal = $(a).data('name').toLowerCase();
        bVal = $(b).data('name').toLowerCase();
      } else if (field === 'sku') {
        aVal = $(a).data('sku').toLowerCase();
        bVal = $(b).data('sku').toLowerCase();
      } else {
        aVal = $(a).find('[data-field="' + field + '"]').text().toLowerCase();
        bVal = $(b).find('[data-field="' + field + '"]').text().toLowerCase();
      }
    }
    
    if (direction === 'asc') {
      return aVal < bVal ? -1 : aVal > bVal ? 1 : 0;
    } else {
      return aVal > bVal ? -1 : aVal < bVal ? 1 : 0;
    }
  });
  
  tbody.empty().append(rows);
}

// === ФИЛЬТРЫ ===
let activeFilters = {
  category: null,
  priceMin: null,
  priceMax: null,
  status: null,
  measurement: null
};

// Обработчик кнопки фильтров
$('#btn-filter-products').on('click', function() {
  $('#productsFilterModal').modal('show');
});

// Применение фильтров
function applyFilters() {
  $('#erp-products-table tbody tr').each(function() {
    const row = $(this);
    let show = true;
    
    // Фильтр по поиску (уже существующий)
    const search = $('#erp-search').val().toLowerCase();
    if (search) {
      const name = row.data('name').toLowerCase();
      const sku = row.data('sku').toLowerCase();
      const code = row.data('code').toString();
      if (!(name.includes(search) || sku.includes(search) || code.includes(search))) {
        show = false;
      }
    }
    
    // Фильтр по категории
    if (selectedCat && row.data('cat') != selectedCat) {
      show = false;
    }
    
    // Фильтр по цене
    if (activeFilters.priceMin !== null || activeFilters.priceMax !== null) {
      const priceText = row.find('[data-field="price"]').text();
      const price = parseFloat(priceText.replace(/[^\d,.-]/g, '').replace(',', '.')) || 0;
      
      if (activeFilters.priceMin !== null && price < activeFilters.priceMin) {
        show = false;
      }
      if (activeFilters.priceMax !== null && price > activeFilters.priceMax) {
        show = false;
      }
    }
    
    // Фильтр по статусу
    if (activeFilters.status !== null) {
      const isActive = row.find('.status-switch').is(':checked');
      const rowStatus = isActive ? 'active' : 'inactive';
      if (rowStatus !== activeFilters.status) {
        show = false;
      }
    }
    
    // Фильтр по единице измерения
    if (activeFilters.measurement !== null) {
      const measurement = row.find('[data-field="unit_of_measure"]').text().toLowerCase();
      if (measurement !== activeFilters.measurement.toLowerCase()) {
        show = false;
      }
    }
    
    row.toggle(show);
  });
  
  updateFilterBadge();
}

// Обновление счетчика активных фильтров
function updateFilterBadge() {
  const count = Object.values(activeFilters).filter(val => val !== null).length;
  const btn = $('#btn-filter-products');
  
  btn.find('.filter-badge').remove();
  if (count > 0) {
    btn.append(`<span class="badge badge-primary filter-badge ms-1">${count}</span>`);
  }
}

// Сброс фильтров
function resetFilters() {
  activeFilters = {
    category: null,
    priceMin: null,
    priceMax: null,
    status: null,
    measurement: null
  };
  
  // Сброс формы в модальном окне
  $('#filter-price-min').val('');
  $('#filter-price-max').val('');
  $('#filter-status').val('');
  $('#filter-measurement').val('');
  
  applyFilters();
}
</script>

<!-- Модальное окно фильтров -->
<div class="modal fade" id="productsFilterModal" tabindex="-1" aria-labelledby="productsFilterModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="productsFilterModalLabel">
          <i class="fas fa-filter me-2"></i>Фильтры товаров
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <!-- Ценовой диапазон -->
          <div class="col-md-6">
            <label for="filter-price-min" class="form-label">Цена от</label>
            <input type="number" step="0.01" class="form-control" id="filter-price-min" placeholder="Минимальная цена">
          </div>
          <div class="col-md-6">
            <label for="filter-price-max" class="form-label">Цена до</label>
            <input type="number" step="0.01" class="form-control" id="filter-price-max" placeholder="Максимальная цена">
          </div>
          
          <!-- Статус -->
          <div class="col-md-6">
            <label for="filter-status" class="form-label">Статус</label>
            <select class="form-select" id="filter-status">
              <option value="">Все</option>
              <option value="active">Активные</option>
              <option value="inactive">Неактивные</option>
            </select>
          </div>
          
          <!-- Единица измерения -->
          <div class="col-md-6">
            <label for="filter-measurement" class="form-label">Единица измерения</label>
            <select class="form-select" id="filter-measurement">
              <option value="">Все</option>
              <option value="шт">Штука</option>
              <option value="кг">Килограмм</option>
              <option value="м">Метр</option>
              <option value="л">Литр</option>
              <option value="м²">Кв. метр</option>
              <option value="м³">Куб. метр</option>
            </select>
          </div>
          
          <!-- Информация -->
          <div class="col-12">
            <div class="alert alert-info">
              <i class="fas fa-info-circle me-2"></i>
              <strong>Подсказка:</strong> Фильтры работают в дополнение к поиску по наименованию и выбранной категории в дереве слева.
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
          <i class="fas fa-eraser me-1"></i>Сбросить
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
        <button type="button" class="btn btn-primary" onclick="applyFiltersFromModal()">
          <i class="fas fa-check me-1"></i>Применить
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// Применение фильтров из модального окна
function applyFiltersFromModal() {
  // Считываем значения из формы
  activeFilters.priceMin = $('#filter-price-min').val() ? parseFloat($('#filter-price-min').val()) : null;
  activeFilters.priceMax = $('#filter-price-max').val() ? parseFloat($('#filter-price-max').val()) : null;
  activeFilters.status = $('#filter-status').val() || null;
  activeFilters.measurement = $('#filter-measurement').val() || null;
  
  // Применяем фильтры
  applyFilters();
  
  // Закрываем модальное окно
  $('#productsFilterModal').modal('hide');
}

// Переопределяем существующую функцию фильтрации для работы с новыми фильтрами
const originalFilterProducts = filterProducts;
filterProducts = function() {
  applyFilters();
};
</script>