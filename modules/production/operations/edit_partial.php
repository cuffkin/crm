<?php
// /crm/modules/production/operations/edit_partial.php
// Файл для редактирования и просмотра операций производства
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/related_documents.php';
require_once __DIR__ . '/utils.php'; // Подключаем вспомогательные функции

// Проверяем доступ
if (!check_access($conn, $_SESSION['user_id'], 'production')) {
    echo '<div class="alert alert-danger">У вас нет доступа к этому разделу.</div>';
    return;
}

// Получение ID операции из параметров, если 0 - значит новая операция
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$operation = null;
$operation_items = [];

// Получаем параметр режима просмотра (если есть)
$view_mode = isset($_GET['view']) && $_GET['view'] == 'true';

// Проверяем существование таблиц
$tables_exist = true;
$required_tables = ['PCRM_ProductionOperation', 'PCRM_ProductionOperationItem', 'PCRM_Product', 'PCRM_Warehouse'];
foreach($required_tables as $table) {
    $check_query = "SHOW TABLES LIKE '$table'";
    $result = $conn->query($check_query);
    if($result->num_rows == 0) {
        echo "<div class='alert alert-warning'>Таблица $table не существует. Проверьте структуру базы данных.</div>";
        $tables_exist = false;
    }
}

if(!$tables_exist) {
    echo '<div class="alert alert-danger">Отсутствуют необходимые таблицы. Создайте структуру базы данных.</div>';
    return;
}

try {
    // Если редактируем существующую операцию
    if ($id > 0) {
        $stmt = $conn->prepare("
            SELECT o.*, p.name as product_name, p.unit_of_measure,
                  w.name as warehouse_name,
                  CONCAT(u.name, ' (', u.username, ')') as user_name,
                  CONCAT(cu.name, ' (', cu.username, ')') as conducted_user_name,
                  r.name as recipe_name,
                  po.order_number as order_number
            FROM PCRM_ProductionOperation o
            LEFT JOIN PCRM_Product p ON o.product_id = p.id
            LEFT JOIN PCRM_Warehouse w ON o.warehouse_id = w.id
            LEFT JOIN PCRM_User u ON o.user_id = u.id
            LEFT JOIN PCRM_User cu ON o.conducted_by = cu.id
            LEFT JOIN PCRM_ProductionRecipe r ON o.recipe_id = r.id
            LEFT JOIN PCRM_ProductionOrder po ON o.order_id = po.id
            WHERE o.id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Ошибка подготовки запроса: " . $conn->error);
        }
        
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo '<div class="alert alert-warning">Операция не найдена.</div>';
            return;
        }
        
        $operation = $result->fetch_assoc();
        
        // Проверяем, проведена ли операция
        $is_conducted = isset($operation['conducted']) && $operation['conducted'] == 1;
        
        // Если операция проведена или запрошен режим просмотра, включаем режим просмотра
        if ($is_conducted || $view_mode) {
            $view_mode = true;
        }
        
        // Получаем элементы операции
        $items_stmt = $conn->prepare("
            SELECT i.*, p.name as product_name, p.unit_of_measure 
            FROM PCRM_ProductionOperationItem i
            JOIN PCRM_Product p ON i.ingredient_id = p.id
            WHERE i.operation_id = ?
        ");
        
        if ($items_stmt) {
            $items_stmt->bind_param('i', $id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            while ($item = $items_result->fetch_assoc()) {
                $operation_items[] = $item;
            }
        }
    } else {
        // Если это новая операция, инициализируем с пустыми значениями
        $operation = [
            'id' => 0,
            'operation_number' => generateOperationNumber($conn), // Генерируем номер операции
            'production_date' => date('Y-m-d'),                   // Текущая дата
            'product_id' => 0,
            'recipe_id' => 0,
            'warehouse_id' => 0,
            'output_quantity' => 0,
            'comment' => '',
            'status' => 'draft'
        ];
    }
    
    // Получение списка доступных продуктов
    $products_query = "SELECT id, name, unit_of_measure FROM PCRM_Product WHERE status = 'active' ORDER BY name";
    $products_result = $conn->query($products_query);
    $products = [];
    
    if ($products_result) {
        while ($row = $products_result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    
    // Получение списка рецептов
    $recipes_query = "SELECT r.id, r.name, r.product_id, p.name as product_name, p.unit_of_measure 
                     FROM PCRM_ProductionRecipe r 
                     JOIN PCRM_Product p ON r.product_id = p.id
                     WHERE r.status = 'active' 
                     ORDER BY r.name";
    $recipes_result = $conn->query($recipes_query);
    $recipes = [];
    
    if ($recipes_result) {
        while ($row = $recipes_result->fetch_assoc()) {
            $recipes[] = $row;
        }
    }
    
    // Получение списка складов
    $warehouses_query = "SELECT id, name FROM PCRM_Warehouse WHERE status = 'active' ORDER BY name";
    $warehouses_result = $conn->query($warehouses_query);
    $warehouses = [];
    
    if ($warehouses_result) {
        while ($row = $warehouses_result->fetch_assoc()) {
            $warehouses[] = $row;
        }
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Ошибка при загрузке данных: ' . htmlspecialchars($e->getMessage()) . '</div>';
    return;
}

// Определяем заголовки и режим формы
if ($view_mode) {
    $operation_title = 'Просмотр операции производства';
    $button_text = 'Вернуться к списку';
    $mode = 'view';
} else {
    $operation_title = $id > 0 ? 'Редактирование операции производства' : 'Новая операция производства';
    $button_text = $id > 0 ? 'Сохранить изменения' : 'Создать операцию';
    $mode = 'edit';
}

?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h4><?= htmlspecialchars($operation_title) ?></h4>
        </div>
        <div class="col-md-6 text-end">
            <button type="button" class="btn btn-secondary" id="cancelOperationEdit">Отмена</button>
            <?php if (!$view_mode): ?>
            <button type="button" class="btn btn-primary" id="saveOperationBtn">
                <i class="fas fa-save"></i> <?= htmlspecialchars($button_text) ?>
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <form id="operationForm">
        <input type="hidden" id="operation_id" name="operation_id" value="<?= $id ?>">
        <input type="hidden" id="form_mode" name="form_mode" value="<?= $mode ?>">
        
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title">Основная информация</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="operation_number" class="form-label">Номер операции</label>
                            <?php if ($view_mode): ?>
                                <p class="form-control-static"><?= htmlspecialchars($operation['operation_number'] ?? 'Н/Д') ?></p>
                            <?php else: ?>
                                <input type="text" class="form-control" id="operation_number" name="operation_number" 
                                    value="<?= htmlspecialchars($operation['operation_number'] ?? '') ?>" required>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="production_date" class="form-label">Дата производства</label>
                            <?php if ($view_mode): ?>
                                <p class="form-control-static"><?= isset($operation['production_date']) ? date('d.m.Y', strtotime($operation['production_date'])) : 'Н/Д' ?></p>
                            <?php else: ?>
                                <input type="date" class="form-control" id="production_date" name="production_date" 
                                    value="<?= isset($operation['production_date']) ? date('Y-m-d', strtotime($operation['production_date'])) : date('Y-m-d') ?>" required>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="status" class="form-label">Статус</label>
                            <?php if ($view_mode): ?>
                                <p class="form-control-static"><?= htmlspecialchars($operation['status'] ?? 'Н/Д') ?></p>
                            <?php else: ?>
                                <select class="form-select" id="status" name="status">
                                    <option value="draft" <?= (isset($operation['status']) && $operation['status'] == 'draft') ? 'selected' : '' ?>>Черновик</option>
                                    <option value="in_progress" <?= (isset($operation['status']) && $operation['status'] == 'in_progress') ? 'selected' : '' ?>>В процессе</option>
                                    <option value="completed" <?= (isset($operation['status']) && $operation['status'] == 'completed') ? 'selected' : '' ?>>Завершено</option>
                                    <option value="cancelled" <?= (isset($operation['status']) && $operation['status'] == 'cancelled') ? 'selected' : '' ?>>Отменено</option>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="recipe_id" class="form-label">Рецепт</label>
                            <?php if ($view_mode): ?>
                                <p class="form-control-static"><?= htmlspecialchars($operation['recipe_name'] ?? 'Н/Д') ?></p>
                            <?php else: ?>
                                <select class="form-select" id="recipe_id" name="recipe_id">
                                    <option value="">Выберите рецепт...</option>
                                    <?php foreach ($recipes as $recipe): ?>
                                    <option value="<?= $recipe['id'] ?>" 
                                        data-product-id="<?= $recipe['product_id'] ?>"
                                        data-product-name="<?= htmlspecialchars($recipe['product_name']) ?>"
                                        data-unit="<?= htmlspecialchars($recipe['unit_of_measure']) ?>"
                                        <?= (isset($operation['recipe_id']) && $operation['recipe_id'] == $recipe['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($recipe['name']) ?> (<?= htmlspecialchars($recipe['product_name']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="warehouse_id" class="form-label">Склад</label>
                            <?php if ($view_mode): ?>
                                <p class="form-control-static"><?= htmlspecialchars($operation['warehouse_name'] ?? 'Н/Д') ?></p>
                            <?php else: ?>
                                <select class="form-select" id="warehouse_id" name="warehouse_id" required>
                                    <option value="">Выберите склад...</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?= $warehouse['id'] ?>" <?= (isset($operation['warehouse_id']) && $operation['warehouse_id'] == $warehouse['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($warehouse['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="product_id" class="form-label">Готовый продукт</label>
                            <?php if ($view_mode): ?>
                                <p class="form-control-static"><?= htmlspecialchars($operation['product_name'] ?? 'Н/Д') ?></p>
                            <?php else: ?>
                                <select class="form-select" id="product_id" name="product_id" required>
                                    <option value="">Выберите продукт...</option>
                                    <?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id'] ?>" 
                                            data-unit="<?= htmlspecialchars($product['unit_of_measure']) ?>"
                                            <?= (isset($operation['product_id']) && $operation['product_id'] == $product['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($product['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Выбирается автоматически при выборе рецепта</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="output_quantity" class="form-label">Количество</label>
                            <div class="input-group">
                                <?php if ($view_mode): ?>
                                    <p class="form-control-static"><?= number_format($operation['output_quantity'] ?? 0, 2) ?> <?= htmlspecialchars($operation['unit_of_measure'] ?? '') ?></p>
                                <?php else: ?>
                                    <input type="number" step="0.01" min="0.01" class="form-control" id="output_quantity" name="output_quantity" 
                                        value="<?= number_format($operation['output_quantity'] ?? 0, 2, '.', '') ?>" required>
                                    <span class="input-group-text" id="product_unit"><?= htmlspecialchars($operation['unit_of_measure'] ?? '') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="check_ingredients" class="form-label">Проверка ингредиентов</label>
                            <?php if (!$view_mode): ?>
                                <button type="button" class="btn btn-outline-info form-control" id="check_ingredients">
                                    <i class="fas fa-check-circle"></i> Проверить наличие ингредиентов
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="comment" class="form-label">Комментарий</label>
                            <?php if ($view_mode): ?>
                                <p class="form-control-static"><?= nl2br(htmlspecialchars($operation['comment'] ?? '')) ?></p>
                            <?php else: ?>
                                <textarea class="form-control" id="comment" name="comment" rows="2"><?= htmlspecialchars($operation['comment'] ?? '') ?></textarea>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Блок ингредиентов -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Ингредиенты</h5>
                <?php if (!$view_mode): ?>
                <button type="button" class="btn btn-sm btn-success" id="add_ingredient">
                    <i class="fas fa-plus"></i> Добавить ингредиент
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered" id="ingredients_table">
                        <thead>
                            <tr>
                                <th width="45%">Ингредиент</th>
                                <th width="20%">Количество</th>
                                <th width="20%">Единица</th>
                                <?php if (!$view_mode): ?>
                                <th width="15%">Действия</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="ingredients_body">
                            <?php if (count($operation_items) > 0): ?>
                                <?php foreach ($operation_items as $index => $item): ?>
                                <tr data-index="<?= $index ?>">
                                    <td>
                                        <?php if ($view_mode): ?>
                                            <?= htmlspecialchars($item['product_name']) ?>
                                        <?php else: ?>
                                        <select class="form-select ingredient-select" name="ingredients[<?= $index ?>][ingredient_id]" required>
                                            <option value="">Выберите ингредиент...</option>
                                            <?php foreach ($products as $product): ?>
                                            <option value="<?= $product['id'] ?>" 
                                                    data-unit="<?= htmlspecialchars($product['unit_of_measure']) ?>"
                                                    <?= ($item['ingredient_id'] == $product['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($product['name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($view_mode): ?>
                                            <?= number_format($item['quantity'], 2) ?>
                                        <?php else: ?>
                                        <input type="number" step="0.01" min="0.01" class="form-control ingredient-quantity" 
                                               name="ingredients[<?= $index ?>][quantity]" value="<?= number_format($item['quantity'], 2, '.', '') ?>" required>
                                        <?php endif; ?>
                                    </td>
                                    <td class="ingredient-unit">
                                        <?= htmlspecialchars($item['unit_of_measure'] ?? '') ?>
                                    </td>
                                    <?php if (!$view_mode): ?>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm remove-ingredient">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="no-ingredients-row">
                                    <td colspan="<?= $view_mode ? 3 : 4 ?>" class="text-center">
                                        Нет добавленных ингредиентов
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Техническая информация -->
        <?php if ($id > 0): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title">Техническая информация</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <p class="mb-1 text-muted">ID операции:</p>
                        <p class="font-weight-bold"><?= $operation['id'] ?></p>
                    </div>
                    <div class="col-md-3">
                        <p class="mb-1 text-muted">Создана:</p>
                        <p class="font-weight-bold">
                            <?= isset($operation['created_at']) ? date('d.m.Y H:i', strtotime($operation['created_at'])) : 'Н/Д' ?>
                            <?php if (!empty($operation['user_name'])): ?>
                            <br><?= htmlspecialchars($operation['user_name']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-3">
                        <p class="mb-1 text-muted">Обновлена:</p>
                        <p class="font-weight-bold">
                            <?= isset($operation['updated_at']) ? date('d.m.Y H:i', strtotime($operation['updated_at'])) : 'Н/Д' ?>
                        </p>
                    </div>
                    <div class="col-md-3">
                        <p class="mb-1 text-muted">Проведена:</p>
                        <p class="font-weight-bold">
                            <?php if (isset($operation['conducted']) && $operation['conducted']): ?>
                                <?= isset($operation['conducted_date']) ? date('d.m.Y H:i', strtotime($operation['conducted_date'])) : 'Да' ?>
                                <?php if (!empty($operation['conducted_user_name'])): ?>
                                <br><?= htmlspecialchars($operation['conducted_user_name']) ?>
                                <?php endif; ?>
                            <?php else: ?>
                                Нет
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Блок для связанных документов -->
        <?php if ($id > 0): ?>
            <?php showRelatedDocuments($conn, 'production_operation', $id); ?>
        <?php endif; ?>
    </form>
</div>

<!-- Модальное окно для результатов проверки ингредиентов -->
<div class="modal fade" id="ingredientsCheckModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Проверка наличия ингредиентов</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="ingredients_check_results">
                    <p>Загрузка данных...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<script>
// Глобальные переменные
let ingredientIndex = <?= count($operation_items) ?>;
let productData = {}; // Хранение данных о продуктах

// Инициализация при загрузке страницы
$(document).ready(function() {
    // Инициализация данных о продуктах
    <?php foreach ($products as $product): ?>
    productData[<?= $product['id'] ?>] = {
        name: "<?= addslashes($product['name']) ?>",
        unit: "<?= addslashes($product['unit_of_measure']) ?>"
    };
    <?php endforeach; ?>
    
    // Обработчик выбора рецепта
    $('#recipe_id').on('change', function() {
        const selected = $(this).find('option:selected');
        const productId = selected.data('product-id');
        const productName = selected.data('product-name');
        const unit = selected.data('unit');
        
        if (productId) {
            // Устанавливаем продукт и его единицу измерения
            $('#product_id').val(productId).trigger('change');
            $('#product_unit').text(unit);
            
            // Загружаем ингредиенты для выбранного рецепта
            loadRecipeIngredients($(this).val());
        }
    });
    
    // Обработчик выбора продукта
    $('#product_id').on('change', function() {
        const selected = $(this).find('option:selected');
        const unit = selected.data('unit');
        
        if (unit) {
            $('#product_unit').text(unit);
        }
    });
    
    // Обработчик кнопки добавления ингредиента
    $('#add_ingredient').on('click', function() {
        addIngredientRow();
    });
    
    // Обработчик удаления ингредиента
    $(document).on('click', '.remove-ingredient', function() {
        $(this).closest('tr').remove();
        
        // Если нет ингредиентов, показываем заглушку
        if ($('#ingredients_body tr').length === 0) {
            $('#ingredients_body').html(`
                <tr class="no-ingredients-row">
                    <td colspan="4" class="text-center">
                        Нет добавленных ингредиентов
                    </td>
                </tr>
            `);
        }
    });
    
    // Обработчик выбора ингредиента
    $(document).on('change', '.ingredient-select', function() {
        const selected = $(this).find('option:selected');
        const unit = selected.data('unit');
        
        if (unit) {
            $(this).closest('tr').find('.ingredient-unit').text(unit);
        }
    });
    
    // Обработчик кнопки проверки ингредиентов
    $('#check_ingredients').on('click', function() {
        checkIngredients();
    });
    
    // Обработчик кнопки сохранения
    $('#saveOperationBtn').on('click', function() {
        saveOperation();
    });
    
    // Обработчик кнопки отмены
    $('#cancelOperationEdit').on('click', function() {
        returnToList();
    });
    
    // Если выбран рецепт при загрузке, загружаем его ингредиенты
    const recipeId = $('#recipe_id').val();
    if (recipeId) {
        // Загружаем только если это новая операция или нет ингредиентов
        if (<?= $id ?> === 0 || <?= count($operation_items) ?> === 0) {
            loadRecipeIngredients(recipeId);
        }
    }
});

// Функция добавления строки ингредиента
function addIngredientRow() {
    // Удаляем строку-заглушку, если она есть
    $('.no-ingredients-row').remove();
    
    const row = `
        <tr data-index="${ingredientIndex}">
            <td>
                <select class="form-select ingredient-select" name="ingredients[${ingredientIndex}][ingredient_id]" required>
                    <option value="">Выберите ингредиент...</option>
                    <?php foreach ($products as $product): ?>
                    <option value="<?= $product['id'] ?>" data-unit="<?= htmlspecialchars($product['unit_of_measure']) ?>">
                        <?= htmlspecialchars($product['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <input type="number" step="0.01" min="0.01" class="form-control ingredient-quantity" 
                       name="ingredients[${ingredientIndex}][quantity]" value="1.00" required>
            </td>
            <td class="ingredient-unit">
                
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm remove-ingredient">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `;
    
    $('#ingredients_body').append(row);
    ingredientIndex++;
}

// Функция загрузки ингредиентов рецепта
function loadRecipeIngredients(recipeId) {
    if (!recipeId) return;
    
    $.ajax({
        url: 'modules/production/operations/api.php',
        type: 'POST',
        data: { 
            action: 'get_recipe_ingredients',
            recipe_id: recipeId 
        },
        success: function(response) {
            try {
                const data = JSON.parse(response);
                
                if (data.success && data.ingredients && data.ingredients.length > 0) {
                    // Очищаем таблицу ингредиентов
                    $('#ingredients_body').empty();
                    ingredientIndex = 0;
                    
                    // Добавляем ингредиенты из рецепта
                    data.ingredients.forEach(function(item) {
                        const row = `
                            <tr data-index="${ingredientIndex}">
                                <td>
                                    <select class="form-select ingredient-select" name="ingredients[${ingredientIndex}][ingredient_id]" required>
                                        <option value="">Выберите ингредиент...</option>
                                        <?php foreach ($products as $product): ?>
                                        <option value="<?= $product['id'] ?>" 
                                                data-unit="<?= htmlspecialchars($product['unit_of_measure']) ?>"
                                                ${item.ingredient_id == <?= $product['id'] ?> ? 'selected' : ''}>
                                            <?= htmlspecialchars($product['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0.01" class="form-control ingredient-quantity" 
                                           name="ingredients[${ingredientIndex}][quantity]" value="${parseFloat(item.quantity).toFixed(2)}" required>
                                </td>
                                <td class="ingredient-unit">
                                    ${item.unit || ''}
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm remove-ingredient">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                        
                        $('#ingredients_body').append(row);
                        ingredientIndex++;
                    });
                    
                    // Устанавливаем продукт из рецепта
                    if (data.recipe && data.recipe.product_id) {
                        $('#product_id').val(data.recipe.product_id);
                        $('#product_unit').text(data.recipe.unit_of_measure || '');
                    }
                } else {
                    // Если нет ингредиентов, показываем заглушку
                    $('#ingredients_body').html(`
                        <tr class="no-ingredients-row">
                            <td colspan="4" class="text-center">
                                Нет ингредиентов в выбранном рецепте
                            </td>
                        </tr>
                    `);
                }
            } catch (e) {
                console.error('Ошибка при обработке ответа:', e);
                showAlert('danger', 'Ошибка при загрузке ингредиентов рецепта');
            }
        },
        error: function(xhr, status, error) {
            console.error('Ошибка запроса:', error);
            showAlert('danger', 'Ошибка при загрузке ингредиентов рецепта');
        }
    });
}

// Функция проверки наличия ингредиентов
function checkIngredients() {
    const recipeId = $('#recipe_id').val();
    const quantity = $('#output_quantity').val();
    const warehouseId = $('#warehouse_id').val();
    
    if (!recipeId || !quantity || !warehouseId) {
        showAlert('warning', 'Выберите рецепт, склад и укажите количество для проверки ингредиентов');
        return;
    }
    
    // Показываем модальное окно с индикатором загрузки
    $('#ingredientsCheckModal').modal('show');
    $('#ingredients_check_results').html('<p>Загрузка данных...</p>');
    
    $.ajax({
        url: 'modules/production/operations/api.php',
        type: 'POST',
        data: { 
            action: 'check_ingredients',
            recipe_id: recipeId,
            quantity: quantity,
            warehouse_id: warehouseId
        },
        success: function(response) {
            try {
                const data = JSON.parse(response);
                
                if (data.success) {
                    let html = `
                        <div class="mb-3">
                            <h5>Рецепт: ${data.recipe_name}</h5>
                            <p>Продукт: ${data.product_name}</p>
                            <p>Количество: ${quantity} ${data.product_unit}</p>
                        </div>
                    `;
                    
                    if (data.all_available) {
                        html += `
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                Все ингредиенты в наличии в достаточном количестве.
                            </div>
                        `;
                    } else {
                        html += `
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Недостаточно ингредиентов на складе!
                            </div>
                        `;
                    }
                    
                    html += `
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Ингредиент</th>
                                        <th>Требуется</th>
                                        <th>В наличии</th>
                                        <th>Статус</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    data.ingredients.forEach(function(item) {
                        html += `
                            <tr class="${item.available ? 'table-success' : 'table-danger'}">
                                <td>${item.name}</td>
                                <td>${parseFloat(item.required_quantity).toFixed(2)} ${item.unit}</td>
                                <td>${parseFloat(item.stock_quantity).toFixed(2)} ${item.unit}</td>
                                <td>
                                    ${item.available 
                                        ? '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Достаточно</span>' 
                                        : '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>Недостаточно</span>'}
                                </td>
                            </tr>
                        `;
                    });
                    
                    html += `
                                </tbody>
                            </table>
                        </div>
                    `;
                    
                    $('#ingredients_check_results').html(html);
                } else {
                    $('#ingredients_check_results').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Ошибка: ${data.error || 'Неизвестная ошибка при проверке ингредиентов'}
                        </div>
                    `);
                }
            } catch (e) {
                console.error('Ошибка при обработке ответа:', e);
                $('#ingredients_check_results').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Ошибка при обработке ответа
                    </div>
                `);
            }
        },
        error: function(xhr, status, error) {
            console.error('Ошибка запроса:', error);
            $('#ingredients_check_results').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Ошибка при выполнении запроса: ${error}
                </div>
            `);
        }
    });
}

// Функция сохранения операции
function saveOperation() {
    // Проверяем заполнение обязательных полей
    if (!validateForm()) {
        return;
    }
    
    // Собираем данные формы
    const formData = {
        id: $('#operation_id').val(),
        operation_number: $('#operation_number').val(),
        production_date: $('#production_date').val(),
        recipe_id: $('#recipe_id').val(),
        product_id: $('#product_id').val(),
        warehouse_id: $('#warehouse_id').val(),
        output_quantity: $('#output_quantity').val(),
        status: $('#status').val(),
        comment: $('#comment').val(),
        ingredients: []
    };
    
    // Добавляем ингредиенты
    $('#ingredients_body tr').each(function() {
        const index = $(this).data('index');
        if (index !== undefined) {
            const ingredientId = $(this).find('.ingredient-select').val();
            const quantity = $(this).find('.ingredient-quantity').val();
            
            if (ingredientId && quantity) {
                formData.ingredients.push({
                    ingredient_id: ingredientId,
                    quantity: quantity
                });
            }
        }
    });
    
    // Отправляем запрос на сохранение
    $.ajax({
        url: 'modules/production/operations/save.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        success: function(response) {
            try {
                const data = JSON.parse(response);
                
                if (data.success) {
                    showAlert('success', 'Операция успешно сохранена');
                    
                    // Если это новая операция, перенаправляем на страницу редактирования
                    if (formData.id == 0) {
                        // Открываем новую вкладку с созданной операцией
                        openProductionOperationTab(data.id);
                        // Закрываем текущую вкладку
                        returnToList();
                    } else {
                        // Перезагружаем текущую страницу для обновления данных
                        loadContent('modules/production/operations/edit_partial.php?id=' + data.id);
                    }
                } else {
                    showAlert('danger', 'Ошибка при сохранении: ' + (data.error || 'Неизвестная ошибка'));
                }
            } catch (e) {
                console.error('Ошибка при обработке ответа:', e);
                showAlert('danger', 'Ошибка при обработке ответа сервера');
            }
        },
        error: function(xhr, status, error) {
            console.error('Ошибка запроса:', error);
            showAlert('danger', 'Ошибка при отправке запроса на сервер');
        }
    });
}

// Функция проверки формы
function validateForm() {
    let isValid = true;
    
    // Проверка обязательных полей
    if (!$('#operation_number').val()) {
        showAlert('warning', 'Укажите номер операции');
        $('#operation_number').focus();
        isValid = false;
    } else if (!$('#production_date').val()) {
        showAlert('warning', 'Укажите дату производства');
        $('#production_date').focus();
        isValid = false;
    } else if (!$('#warehouse_id').val()) {
        showAlert('warning', 'Выберите склад');
        $('#warehouse_id').focus();
        isValid = false;
    } else if (!$('#product_id').val()) {
        showAlert('warning', 'Выберите продукт');
        $('#product_id').focus();
        isValid = false;
    } else if (!$('#output_quantity').val() || parseFloat($('#output_quantity').val()) <= 0) {
        showAlert('warning', 'Укажите количество');
        $('#output_quantity').focus();
        isValid = false;
    }
    
    // Проверка наличия ингредиентов
    if ($('#ingredients_body tr').length === 0 || $('.no-ingredients-row').length > 0) {
        showAlert('warning', 'Добавьте хотя бы один ингредиент');
        isValid = false;
    }
    
    return isValid;
}

// Функция для отображения уведомления
function showAlert(type, message) {
    const alertHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    // Создаем ID для блока уведомлений, если его нет
    if ($('#operation-alerts').length === 0) {
        $('#operationForm').before('<div id="operation-alerts"></div>');
    }
    
    // Добавляем уведомление в специальный блок
    const alertBox = $(alertHTML);
    $('#operation-alerts').append(alertBox);
    
    // Удаляем уведомление через 5 секунд
    setTimeout(function() {
        alertBox.fadeOut('slow', function() {
            $(this).remove();
        });
    }, 5000);
}

// Функция возврата к списку операций
function returnToList() {
    loadContent('modules/production/operations/list_partial.php');
}
</script>