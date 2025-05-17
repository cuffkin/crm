<?php
// /crm/modules/categories/list_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Проверяем доступ
if (!check_access($conn, $_SESSION['user_id'], 'categories')) {
    die("<div class='text-danger'>Доступ запрещён</div>");
}

// Параметры фильтрации и сортировки
$filter_level = isset($_GET['filter_level']) ? $_GET['filter_level'] : 'all';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'id';
$sort_order = isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC', 'DESC']) ? strtoupper($_GET['sort_order']) : 'DESC';

// Формирование SQL запроса
$sql_base = "SELECT * FROM PCRM_Categories";
$sql_conditions = [];

if ($filter_level === 'category') {
    $sql_conditions[] = "(pc_id IS NULL OR pc_id = 0 OR pc_id = '')";
} elseif ($filter_level === 'subcategory') {
    $sql_conditions[] = "(pc_id IS NOT NULL AND pc_id != 0 AND pc_id != '')";
}

$sql_where_clause = "";
if (!empty($sql_conditions)) {
    $sql_where_clause = " WHERE " . implode(" AND ", $sql_conditions);
}

$sql_order_clause = "";
$valid_direct_sort_columns = ['id', 'name', 'status'];

if ($sort_by === 'level') {
    // Уровень ASC: Категории (true) сначала -> (expression) DESC.
    // Уровень DESC: Подкатегории (false) сначала -> (expression) ASC.
    $level_expression_order = ($sort_order === 'ASC') ? 'DESC' : 'ASC';
    $sql_order_clause = " ORDER BY (pc_id IS NULL OR pc_id = 0 OR pc_id = '') " . $level_expression_order . ", name ASC";
} elseif (in_array($sort_by, $valid_direct_sort_columns)) {
    $sql_order_clause = " ORDER BY `$sort_by` $sort_order, `id` ASC"; // Добавлена вторичная сортировка по ID
} else { // Default sort
    $sort_by = 'id'; // Устанавливаем $sort_by в 'id' если он невалидный
    $sql_order_clause = " ORDER BY `id` $sort_order";
}

$sql = $sql_base . $sql_where_clause . $sql_order_clause;
$res = $conn->query($sql);
$cats = $res->fetch_all(MYSQLI_ASSOC);

// Для JavaScript: передаем текущие параметры фильтрации/сортировки
$current_params_json = json_encode([
    'filter_level' => $filter_level,
    'sort_by' => $sort_by,
    'sort_order' => $sort_order
]);

?>
<div id="categories-list-container"> <!-- Обертка для всего списка, чтобы его можно было легко обновлять -->
    <h4>Категории / Подкатегории</h4>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="btn-group" role="group" aria-label="Filter categories">
            <button type="button" class="btn btn-outline-secondary btn-sm filter-btn <?php if ($filter_level === 'all') echo 'active'; ?>" data-filter="all">Все</button>
            <button type="button" class="btn btn-outline-secondary btn-sm filter-btn <?php if ($filter_level === 'category') echo 'active'; ?>" data-filter="category">Категории</button>
            <button type="button" class="btn btn-outline-secondary btn-sm filter-btn <?php if ($filter_level === 'subcategory') echo 'active'; ?>" data-filter="subcategory">Подкатегории</button>
        </div>
        <button class="btn btn-primary btn-sm" onclick="editCategory(0)">Добавить новую</button>
    </div>

    <table class="table table-bordered">
      <thead>
        <tr>
          <th class="sortable-header" data-sort="id">ID <span class="sort-icon"></span></th>
          <th class="sortable-header" data-sort="name">Название <span class="sort-icon"></span></th>
          <th class="sortable-header" data-sort="level">Уровень <span class="sort-icon"></span></th>
          <th>Тип</th> <!-- Тип пока не делаем сортируемым -->
          <th>Parent ID</th>
          <th class="sortable-header" data-sort="status">Статус <span class="sort-icon"></span></th>
          <th>Действия</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($cats)): ?>
            <tr><td colspan="7" class="text-center">Нет категорий для отображения с учетом текущих фильтров.</td></tr>
        <?php endif; ?>
        <?php foreach ($cats as $c): ?>
          <?php
            $levelText = '';
            $levelClass = '';
            if (is_null($c['pc_id']) || $c['pc_id'] == 0 || $c['pc_id'] == '') {
                $levelText = 'Категория';
                $levelClass = 'level-category';
            } else {
                $levelText = 'Подкатегория';
                $levelClass = 'level-subcategory';
            }
            $typeText = htmlspecialchars($c['type']);
          ?>
          <tr>
            <td><?= $c['id'] ?></td>
            <td><?= htmlspecialchars($c['name']) ?></td>
            <td class="<?= $levelClass ?>"><?= $levelText ?></td>
            <td><?= $typeText ?></td>
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
</div>
<!-- Блок для загрузки формы редактирования/добавления -->
<div id="cat-edit-area"></div>

<script>
// Сохраняем текущие параметры, переданные из PHP
var currentListParams = JSON.parse('<?= $current_params_json ?>');

function loadCategoriesList(params = {}) {
    var queryParams = {
        filter_level: params.filter_level !== undefined ? params.filter_level : currentListParams.filter_level,
        sort_by: params.sort_by !== undefined ? params.sort_by : currentListParams.sort_by,
        sort_order: params.sort_order !== undefined ? params.sort_order : currentListParams.sort_order
    };

    // Обновляем глобальные текущие параметры
    currentListParams.filter_level = queryParams.filter_level;
    currentListParams.sort_by = queryParams.sort_by;
    currentListParams.sort_order = queryParams.sort_order;

    $.ajax({
        url: '/crm/modules/categories/list_partial.php',
        type: 'GET',
        data: queryParams,
        success: function(response) {
            // Предполагаем, что этот скрипт загружается в активную вкладку
            // Если у вас есть более конкретный контейнер для списка, используйте его
            var $activePane = $('#crm-tab-content .tab-pane.active');
            if ($activePane.length) {
                 // Заменяем только содержимое #categories-list-container внутри активной вкладки
                 // чтобы не перерисовывать всю вкладку и не терять другие элементы на ней.
                 var $newListContainer = $(response).find('#categories-list-container');
                 if ($newListContainer.length) {
                    $activePane.find('#categories-list-container').replaceWith($newListContainer);
                 } else { // Fallback если структура ответа изменилась
                    $activePane.html(response);
                 }
            } else {
                 // Fallback если не нашли активную вкладку (например, если структура DOM другая)
                 console.error("Активная вкладка для обновления списка категорий не найдена.");
                 // Можно попробовать обновить какой-то родительский элемент
                 // $('#some-categories-main-container').html(response);
            }
            // После успешной загрузки, инициализируем обработчики и UI снова,
            // так как они были внутри замененного HTML.
            // Эта часть будет внутри list_partial.php и выполнится при каждой загрузке
            // initializeCategoryListUI(); // Эта функция будет ниже
        },
        error: function() {
            alert('Ошибка при загрузке списка категорий.');
        }
    });
}

function initializeCategoryListUI() {
    // Установка активной кнопки фильтра и иконок сортировки
    // Кнопки фильтров
    $('.filter-btn').removeClass('active');
    $('.filter-btn[data-filter="' + currentListParams.filter_level + '"]').addClass('active');

    // Иконки сортировки
    $('.sortable-header').removeClass('sort-asc sort-desc');
    $('.sortable-header .sort-icon').html(''); // Очищаем все иконки

    var $activeSortHeader = $('.sortable-header[data-sort="' + currentListParams.sort_by + '"]');
    if ($activeSortHeader.length) {
        if (currentListParams.sort_order === 'ASC') {
            $activeSortHeader.addClass('sort-asc').find('.sort-icon').html('&#x25B2;'); // ▲
        } else {
            $activeSortHeader.addClass('sort-desc').find('.sort-icon').html('&#x25BC;'); // ▼
        }
    }
    
    // Обработчики событий (перепривязываем, т.к. контент мог быть заменен)
    // Используем делегирование событий к статическому родительскому элементу, если #categories-list-container перезагружается целиком.
    // Если же #categories-list-container НЕ перезагружается, а только его внутренности (напр. tbody),
    // то можно привязывать напрямую. Но т.к. я его заменяю, то нужно делегирование или повторная инициализация.
    // Проще всего повторно вызвать initializeCategoryListUI после AJAX success.

    // Фильтры
    $('.filter-btn').off('click').on('click', function() {
        var filter = $(this).data('filter');
        loadCategoriesList({ filter_level: filter });
    });

    // Сортировка
    $('.sortable-header').off('click').on('click', function() {
        var newSortBy = $(this).data('sort');
        var newSortOrder;

        if (newSortBy === currentListParams.sort_by) {
            newSortOrder = currentListParams.sort_order === 'ASC' ? 'DESC' : 'ASC';
        } else {
            newSortOrder = 'ASC'; // По умолчанию ASC при смене колонки
        }
        loadCategoriesList({ sort_by: newSortBy, sort_order: newSortOrder });
    });
}

// Вызываем инициализацию UI при первой загрузке скрипта
$(document).ready(function() {
    initializeCategoryListUI();
    // Также необходимо вызвать initializeCategoryListUI в success callback-е loadCategoriesList,
    // если вы перезагружаете и сам <script> тег.
    // Но т.к. PHP код и JS код в одном файле, при каждой AJAX-загрузке list_partial.php
    // этот JS код будет выполняться заново, так что $(document).ready() должен сработать.
});


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
      // Перезагрузим список с текущими параметрами фильтрации и сортировки
      loadCategoriesList(); 
    } else {
      alert(resp);
    }
  });
}
</script>