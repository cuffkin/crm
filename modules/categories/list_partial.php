<?php
// /crm/modules/categories/list_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Проверяем доступ
if (!check_access($conn, $_SESSION['user_id'], 'categories')) {
    die("<div class='text-danger'>Доступ запрещён</div>");
}

// Параметры фильтрации, сортировки и пагинации
$filter_level = isset($_GET['filter_level']) ? $_GET['filter_level'] : 'all';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'id';
$sort_order = isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC', 'DESC']) ? strtoupper($_GET['sort_order']) : 'DESC';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$per_page_options = [10, 25, 50, 100, 0]; // 0 для "Все"
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : $per_page_options[0];
if (!in_array($per_page, $per_page_options)) $per_page = $per_page_options[0];

// Формирование SQL запроса
$sql_base = "FROM PCRM_Categories"; // Убираем SELECT *, так как он будет ниже для подсчета и для данных
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

// Подсчет общего количества строк для пагинации (с учетом фильтров)
$sql_count = "SELECT COUNT(*) as total_rows " . $sql_base . $sql_where_clause;
$count_res = $conn->query($sql_count);
$total_rows_data = $count_res->fetch_assoc();
$total_rows = $total_rows_data ? (int)$total_rows_data['total_rows'] : 0;

$total_pages = ($per_page > 0 && $total_rows > 0) ? ceil($total_rows / $per_page) : 1;
if ($page > $total_pages) $page = $total_pages;
if ($page < 1 && $total_pages > 0) $page = 1; // Убедимся, что страница не меньше 1, если есть страницы

$sql_order_clause = "";
$valid_direct_sort_columns = ['id', 'name', 'status'];

if ($sort_by === 'level') {
    $level_expression_order = ($sort_order === 'ASC') ? 'DESC' : 'ASC';
    $sql_order_clause = " ORDER BY (pc_id IS NULL OR pc_id = 0 OR pc_id = '') " . $level_expression_order . ", name ASC";
} elseif (in_array($sort_by, $valid_direct_sort_columns)) {
    $sql_order_clause = " ORDER BY `$sort_by` $sort_order, `id` ASC";
} else {
    $sort_by = 'id';
    $sql_order_clause = " ORDER BY `id` $sort_order";
}

$sql_limit_offset = "";
if ($per_page > 0) {
    $offset = ($page - 1) * $per_page;
    $sql_limit_offset = " LIMIT $per_page OFFSET $offset";
}

$sql_data = "SELECT * " . $sql_base . $sql_where_clause . $sql_order_clause . $sql_limit_offset;
$res = $conn->query($sql_data);
$cats = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// Для JavaScript: передаем текущие параметры
$current_params_json = json_encode([
    'filter_level' => $filter_level,
    'sort_by' => $sort_by,
    'sort_order' => $sort_order,
    'page' => $page,
    'per_page' => $per_page
]);

?>
<div id="categories-list-container">
    <h4>Категории / Подкатегории</h4>

    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
        <div class="btn-group mb-2 mb-md-0" role="group" aria-label="Filter categories">
            <button type="button" class="btn btn-outline-secondary btn-sm filter-btn <?php if ($filter_level === 'all') echo 'active'; ?>" data-filter="all">Все</button>
            <button type="button" class="btn btn-outline-secondary btn-sm filter-btn <?php if ($filter_level === 'category') echo 'active'; ?>" data-filter="category">Категории</button>
            <button type="button" class="btn btn-outline-secondary btn-sm filter-btn <?php if ($filter_level === 'subcategory') echo 'active'; ?>" data-filter="subcategory">Подкатегории</button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <select id="filter-status" class="form-select form-select-sm" style="width:auto;">
                <option value="">Все статусы</option>
                <option value="active">Активные</option>
                <option value="inactive">Неактивные</option>
            </select>
        </div>
        <button class="btn btn-primary btn-sm" onclick="editCategory(0)">Добавить новую</button>
    </div>

    <table class="table table-bordered" id="categories-table">
      <thead>
        <tr>
          <th class="sortable-header" data-sort="id">ID <span class="sort-icon"></span></th>
          <th class="sortable-header" data-sort="name">Название <span class="sort-icon"></span></th>
          <th class="sortable-header" data-sort="level">Уровень <span class="sort-icon"></span></th>
          <th>Тип</th>
          <th>Parent ID</th>
          <th class="sortable-header" data-sort="status">Статус <span class="sort-icon"></span></th>
          <th>Действия</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($cats)): ?>
            <tr><td colspan="7" class="text-center">Нет категорий для отображения с учетом текущих фильтров.</td></tr>
        <?php else: ?>
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
                $statusColor = $c['status'] === 'active' ? 'text-success' : 'text-danger';
              ?>
              <tr data-status="<?= $c['status'] ?>" data-level="<?= $levelText ?>">
                <td><?= $c['id'] ?></td>
                <td><?= htmlspecialchars($c['name']) ?></td>
                <td class="<?= $levelClass ?>"><?= $levelText ?></td>
                <td><?= $typeText ?></td>
                <td><?= htmlspecialchars($c['pc_id']) ?></td>
                <td>
                  <div class="form-check form-switch">
                    <input class="form-check-input status-switch" type="checkbox" data-id="<?= $c['id'] ?>" <?= $c['status']==='active'?'checked':'' ?>>
                    <span class="fw-bold ms-2 <?= $statusColor ?>"><?= $c['status'] === 'active' ? 'Активна' : 'Неактивна' ?></span>
                  </div>
                </td>
                <td>
                  <button class="btn btn-warning btn-sm" onclick="editCategory(<?= $c['id'] ?>)">Редактировать</button>
                  <button class="btn btn-danger btn-sm"  onclick="deleteCategory(<?= $c['id'] ?>)">Удалить</button>
                </td>
              </tr>
            <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- Пагинация -->
    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap">
        <div class="mb-2 mb-md-0">
            <label for="perPageSelect" class="me-2 form-label-sm">Показывать по:</label>
            <select id="perPageSelect" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                <?php foreach($per_page_options as $option_val): ?>
                    <option value="<?= $option_val ?>" <?php if ($per_page == $option_val) echo 'selected'; ?>>
                        <?= $option_val == 0 ? 'Все' : $option_val ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($per_page > 0 && $total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
                        <a class="page-link" href="#" data-page="<?= $page - 1 ?>">&laquo;</a>
                    </li>
                    <?php
                        // Логика для отображения номеров страниц (можно улучшить для большого кол-ва страниц)
                        $num_links = 5; // Количество ссылок на страницы для отображения вокруг текущей
                        $start = max(1, $page - floor($num_links / 2));
                        $end = min($total_pages, $page + floor($num_links / 2));

                        if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>';
                            if ($start > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }

                        for ($i = $start; $i <= $end; $i++):
                    ?>
                        <li class="page-item <?php if ($page == $i) echo 'active'; ?>">
                            <a class="page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php
                        if ($end < $total_pages) {
                            if ($end < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="#" data-page="'.$total_pages.'">'.$total_pages.'</a></li>';
                        }
                    ?>
                    <li class="page-item <?php if ($page >= $total_pages) echo 'disabled'; ?>">
                        <a class="page-link" href="#" data-page="<?= $page + 1 ?>">&raquo;</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
    <div class="text-muted mt-1"><small>Всего записей: <?= $total_rows ?></small></div>

</div>

<!-- Category Edit Modal HTML (добавляем сюда) -->
<div class="modal fade" id="categoryEditModal" tabindex="-1" aria-labelledby="categoryEditModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="categoryEditModalLabel">Редактирование</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- Содержимое будет загружаться сюда динамически -->
        <p class="text-center">Загрузка...</p>
      </div>
    </div>
  </div>
</div>

<script>
var currentListParams = JSON.parse('<?= $current_params_json ?>');

function loadCategoriesList(params = {}) {
    var queryParams = {
        filter_level: params.filter_level !== undefined ? params.filter_level : currentListParams.filter_level,
        sort_by: params.sort_by !== undefined ? params.sort_by : currentListParams.sort_by,
        sort_order: params.sort_order !== undefined ? params.sort_order : currentListParams.sort_order,
        page: params.page !== undefined ? params.page : currentListParams.page,
        per_page: params.per_page !== undefined ? params.per_page : currentListParams.per_page
    };

    currentListParams = { ...currentListParams, ...queryParams }; // Обновляем глобальные параметры

    $.ajax({
        url: '/crm/modules/categories/list_partial.php',
        type: 'GET',
        data: queryParams,
        success: function(response) {
            var $activePane = $('#crm-tab-content .tab-pane.active');
            if ($activePane.length) {
                 var $newListContainer = $(response).find('#categories-list-container');
                 if ($newListContainer.length) {
                    $activePane.find('#categories-list-container').replaceWith($newListContainer);
                 } else {
                    $activePane.html(response); // Fallback
                 }
            } else {
                 console.error("Активная вкладка для обновления списка категорий не найдена.");
            }
            // initializeCategoryListUI() будет вызван при повторном выполнении скрипта из AJAX ответа
        },
        error: function() {
            alert('Ошибка при загрузке списка категорий.');
        }
    });
}

function initializeCategoryListUI() {
    // Установка активной кнопки фильтра и иконок сортировки
    $('.filter-btn').removeClass('active');
    $('.filter-btn[data-filter="' + currentListParams.filter_level + '"]').addClass('active');

    $('.sortable-header').removeClass('sort-asc sort-desc');
    $('.sortable-header .sort-icon').html('');

    var $activeSortHeader = $('.sortable-header[data-sort="' + currentListParams.sort_by + '"]');
    if ($activeSortHeader.length) {
        if (currentListParams.sort_order === 'ASC') {
            $activeSortHeader.addClass('sort-asc').find('.sort-icon').html('&#x25B2;'); // ▲
        } else {
            $activeSortHeader.addClass('sort-desc').find('.sort-icon').html('&#x25BC;'); // ▼
        }
    }
    
    // Обработчики событий
    $('.filter-btn').off('click').on('click', function() {
        var filter = $(this).data('filter');
        loadCategoriesList({ filter_level: filter, page: 1 }); // Сбрасываем на 1 страницу при смене фильтра
    });

    $('.sortable-header').off('click').on('click', function() {
        var newSortBy = $(this).data('sort');
        var newSortOrder;
        if (newSortBy === currentListParams.sort_by) {
            newSortOrder = currentListParams.sort_order === 'ASC' ? 'DESC' : 'ASC';
        } else {
            newSortOrder = 'ASC';
        }
        loadCategoriesList({ sort_by: newSortBy, sort_order: newSortOrder, page: 1 }); // Сбрасываем на 1 страницу
    });

    // Пагинация: выбор количества на странице
    $('#perPageSelect').off('change').on('change', function() {
        loadCategoriesList({ per_page: $(this).val(), page: 1 }); // Сбрасываем на 1 страницу
    });

    // Пагинация: клики по ссылкам страниц
    $('.pagination .page-link').off('click').on('click', function(e) {
        e.preventDefault();
        var pageNum = $(this).data('page');
        if (pageNum && pageNum != currentListParams.page) { // Загружаем, только если страница изменилась
             loadCategoriesList({ page: pageNum });
        }
    });
}

$(document).ready(function() {
    initializeCategoryListUI();
});

function editCategory(catId) {
  const modalId = 'categoryEditModal';
  const modalElement = document.getElementById(modalId);

  if (!modalElement) {
    console.error('Modal element #' + modalId + ' not found in DOM.');
    alert('Ошибка: HTML-элемент модального окна не найден.');
    return;
  }

  // 1. Dispose any old Bootstrap modal instance associated with the element
  const existingBootstrapModal = bootstrap.Modal.getInstance(modalElement);
  if (existingBootstrapModal) {
    existingBootstrapModal.dispose();
  }

  // 2. Create a new, fresh Bootstrap modal instance directly from the element
  const bsModal = new bootstrap.Modal(modalElement);

  // 3. Find title and body elements within the modal
  const modalTitleElement = modalElement.querySelector('.modal-title');
  const modalBodyElement = modalElement.querySelector('.modal-body');

  if (!modalTitleElement || !modalBodyElement) {
    console.error('Modal title or body element not found within #' + modalId);
    alert('Ошибка: структура модального окна повреждена.');
    return;
  }

  // 4. Set title and initial content (spinner) directly
  modalTitleElement.textContent = (catId == 0) ? 'Добавить новую категорию' : 'Редактировать категорию (ID: ' + catId + ')';
  modalBodyElement.innerHTML = '<div class="text-center p-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Загрузка...</span></div></div>';

  // 5. Show the modal using the direct Bootstrap instance
  bsModal.show();

  // 6. Load content into the modal body using AJAX, setting it directly
  $.ajax({
    url: '/crm/modules/categories/edit_partial.php',
    type: 'GET',
    data: { id: catId },
    success: function(html) {
      modalBodyElement.innerHTML = html;
    },
    error: function() {
      modalBodyElement.innerHTML = '<div class="alert alert-danger">Ошибка при загрузке формы редактирования.</div>';
    }
  });
}

function deleteCategory(catId) {
  if (!confirm('Точно пометить категорию как inactive?')) return;
  $.get('/crm/modules/categories/delete.php', { id: catId }, function(resp) {
    if (resp === 'OK') {
      loadCategoriesList(); 
    } else {
      alert(resp);
    }
  });
}

$(function() {
  $('#filter-status').on('change', function() {
    var stat = $(this).val();
    $('#categories-table tbody tr').each(function() {
      var show = true;
      if (stat && $(this).data('status') != stat) show = false;
      $(this).toggle(show);
    });
  });
  $('.status-switch').on('change', function() {
    var id = $(this).data('id');
    var newStatus = $(this).is(':checked') ? 'active' : 'inactive';
    $.post('/crm/modules/categories/save.php', { id: id, status_only: 1, status: newStatus }, function(resp) {
      if (resp === 'OK') {
        location.reload();
      } else {
        alert('Ошибка смены статуса: ' + resp);
      }
    });
  });
});
</script>