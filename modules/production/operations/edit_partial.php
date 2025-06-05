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
                                        <div class="product-selector-container ingredient-selector" data-ingredient-id="<?= $item['ingredient_id'] ?>"></div>
                                        <input type="hidden" name="ingredients[<?= $index ?>][ingredient_id]" value="<?= $item['ingredient_id'] ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($view_mode): ?>
                                            <?= number_format($item['required_quantity'], 2) ?>
                                        <?php else: ?>
                                        <input type="number" step="0.01" min="0.01" class="form-control ingredient-quantity" 
                                               name="ingredients[<?= $index ?>][required_quantity]" value="<?= number_format($item['required_quantity'], 2, '.', '') ?>" required>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($view_mode): ?>
                                            <?= number_format($item['actual_quantity'], 2) ?>
                                        <?php else: ?>
                                        <input type="number" step="0.01" min="0" class="form-control actual-quantity" 
                                               name="ingredients[<?= $index ?>][actual_quantity]" value="<?= number_format($item['actual_quantity'], 2, '.', '') ?>">
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
                                    <td colspan="<?= $view_mode ? 4 : 5 ?>" class="text-center">
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

<!-- Подключение общих JavaScript функций -->
<script src="/crm/js/common.js"></script>

<script>
console.log('🟢 МОДУЛЬ ОПЕРАЦИЙ ПРОИЗВОДСТВА: Скрипт начал загружаться');

// Переменные для управления ингредиентами
let ingredientIndex = <?= count($operation_items) ?>;
const ALL_PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;

$(document).ready(function() {
    console.log('📋 Инициализация операций производства...');
    
    <?php if (!$view_mode): ?>
    // Инициализируем селекторы ингредиентов для существующих строк
    $('.ingredient-selector').each(function() {
        const $container = $(this);
        const $row = $container.closest('tr');
        const ingredientId = $container.data('ingredient-id');
        
        const ingredientSelector = createProductSelector(this, {
            context: 'production',
            placeholder: 'Выберите ингредиент...',
            onSelect: function(product) {
                // Обновляем скрытое поле
                $row.find('input[name$="[ingredient_id]"]').val(product.id);
                // Обновляем единицу измерения
                $row.find('.ingredient-unit').text(product.unit || 'шт');
                console.log('✅ Выбран ингредиент:', product.name);
            },
            onClear: function() {
                $row.find('input[name$="[ingredient_id]"]').val('');
                $row.find('.ingredient-unit').text('шт');
            }
        });
        
        // Устанавливаем выбранный ингредиент
        if (ingredientId) {
            const ingredient = ALL_PRODUCTS.find(p => p.id == ingredientId);
            if (ingredient) {
                ingredientSelector.setProduct(ingredient);
            }
        }
    });
    <?php endif; ?>
    
    // Обработчик выбора рецепта
    $('#recipe_id').on('change', function() {
        const recipeId = $(this).val();
        
        if (recipeId) {
            // Загружаем ингредиенты рецепта
            loadRecipeIngredients(recipeId);
        } else {
            // Очищаем список ингредиентов
            $('#ingredients_body').html(`
                <tr class="no-ingredients-row">
                    <td colspan="<?= $view_mode ? 4 : 5 ?>" class="text-center">
                        Выберите рецепт для загрузки ингредиентов
                    </td>
                </tr>
            `);
        }
    });
    
    // Обработчик добавления ингредиента
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
                    <td colspan="<?= $view_mode ? 4 : 5 ?>" class="text-center">
                        Нет добавленных ингредиентов
                    </td>
                </tr>
            `);
        }
    });
    
    // Обработчик кнопки сохранения
    $('#saveOperationBtn').on('click', function() {
        saveOperation();
    });
    
    // Обработчик кнопки отмены
    $('#cancelOperationEdit').on('click', function() {
        returnToList();
    });
});

// Функция загрузки ингредиентов рецепта
function loadRecipeIngredients(recipeId) {
    $.ajax({
        url: 'modules/production/operations/get_recipe_ingredients.php',
        type: 'GET',
        data: { recipe_id: recipeId },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.ingredients) {
                // Очищаем текущий список ингредиентов
                $('#ingredients_body').empty();
                
                // Добавляем ингредиенты из рецепта
                response.ingredients.forEach(function(ingredient) {
                    addIngredientRowWithData(ingredient);
                });
                
                if (response.ingredients.length === 0) {
                    $('#ingredients_body').html(`
                        <tr class="no-ingredients-row">
                            <td colspan="<?= $view_mode ? 4 : 5 ?>" class="text-center">
                                В рецепте нет ингредиентов
                            </td>
                        </tr>
                    `);
                }
                
                // Сбрасываем счетчик
                ingredientIndex = response.ingredients.length;
            } else {
                showAlert('danger', 'Ошибка загрузки ингредиентов рецепта');
            }
        },
        error: function() {
            showAlert('danger', 'Ошибка при запросе ингредиентов рецепта');
        }
    });
}

// Функция добавления строки ингредиента
function addIngredientRow() {
    // Удаляем строку-заглушку, если она есть
    $('.no-ingredients-row').remove();
    
    const row = `
        <tr data-index="${ingredientIndex}">
            <td>
                <div class="product-selector-container ingredient-selector"></div>
                <input type="hidden" name="ingredients[${ingredientIndex}][ingredient_id]" value="">
            </td>
            <td>
                <input type="number" step="0.01" min="0.01" class="form-control ingredient-quantity" 
                       name="ingredients[${ingredientIndex}][required_quantity]" value="1.00" required>
            </td>
            <td>
                <input type="number" step="0.01" min="0" class="form-control actual-quantity" 
                       name="ingredients[${ingredientIndex}][actual_quantity]" value="0.00">
            </td>
            <td class="ingredient-unit">шт</td>
            <td>
                <button type="button" class="btn btn-danger btn-sm remove-ingredient">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `;
    
    $('#ingredients_body').append(row);
    
    // Инициализируем Product Selector для новой строки
    const $newRow = $('#ingredients_body tr').last();
    const $container = $newRow.find('.ingredient-selector');
    
    const ingredientSelector = createProductSelector($container[0], {
        context: 'production',
        placeholder: 'Выберите ингредиент...',
        onSelect: function(product) {
            $newRow.find('input[name$="[ingredient_id]"]').val(product.id);
            $newRow.find('.ingredient-unit').text(product.unit || 'шт');
        },
        onClear: function() {
            $newRow.find('input[name$="[ingredient_id]"]').val('');
            $newRow.find('.ingredient-unit').text('шт');
        }
    });
    
    ingredientIndex++;
}

// Функция добавления строки ингредиента с данными
function addIngredientRowWithData(ingredient) {
    // Удаляем строку-заглушку, если она есть
    $('.no-ingredients-row').remove();
    
    const row = `
        <tr data-index="${ingredientIndex}" data-ingredient-id="${ingredient.ingredient_id}">
            <td>
                <div class="product-selector-container ingredient-selector" data-ingredient-id="${ingredient.ingredient_id}"></div>
                <input type="hidden" name="ingredients[${ingredientIndex}][ingredient_id]" value="${ingredient.ingredient_id}">
            </td>
            <td>
                <input type="number" step="0.01" min="0.01" class="form-control ingredient-quantity" 
                       name="ingredients[${ingredientIndex}][required_quantity]" value="${ingredient.required_quantity || ingredient.quantity}" required>
            </td>
            <td>
                <input type="number" step="0.01" min="0" class="form-control actual-quantity" 
                       name="ingredients[${ingredientIndex}][actual_quantity]" value="${ingredient.actual_quantity || 0}">
            </td>
            <td class="ingredient-unit">${ingredient.unit_of_measure || 'шт'}</td>
            <td>
                <button type="button" class="btn btn-danger btn-sm remove-ingredient">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `;
    
    $('#ingredients_body').append(row);
    
    // Инициализируем Product Selector для новой строки
    const $newRow = $('#ingredients_body tr').last();
    const $container = $newRow.find('.ingredient-selector');
    
    const ingredientSelector = createProductSelector($container[0], {
        context: 'production',
        placeholder: 'Выберите ингредиент...',
        onSelect: function(product) {
            $newRow.find('input[name$="[ingredient_id]"]').val(product.id);
            $newRow.find('.ingredient-unit').text(product.unit || 'шт');
        },
        onClear: function() {
            $newRow.find('input[name$="[ingredient_id]"]').val('');
            $newRow.find('.ingredient-unit').text('шт');
        }
    });
    
    // Устанавливаем выбранный ингредиент
    if (ingredient.ingredient_id) {
        const product = ALL_PRODUCTS.find(p => p.id == ingredient.ingredient_id);
        if (product) {
            ingredientSelector.setProduct(product);
        }
    }
    
    ingredientIndex++;
}

// Функция сохранения операции
function saveOperation() {
    // Проверяем заполнение обязательных полей
    if (!validateForm()) {
        return;
    }
    
    // Собираем данные формы
    const operationData = {
        id: $('#operation_id').val(),
        operation_number: $('#operation_number').val(),
        production_date: $('#production_date').val(),
        product_id: $('#product_id').val(),
        recipe_id: $('#recipe_id').val(),
        warehouse_id: $('#warehouse_id').val(),
        output_quantity: $('#output_quantity').val(),
        comment: $('#comment').val(),
        status: $('#status').val(),
        ingredients: []
    };
    
    // Добавляем ингредиенты
    $('#ingredients_table tbody tr').each(function() {
        const ingredientId = $(this).find('input[name$="[ingredient_id]"]').val();
        const requiredQuantity = $(this).find('.ingredient-quantity').val();
        const actualQuantity = $(this).find('.actual-quantity').val();
        
        if (ingredientId && requiredQuantity) {
            operationData.ingredients.push({
                ingredient_id: ingredientId,
                required_quantity: requiredQuantity,
                actual_quantity: actualQuantity || 0
            });
        }
    });
    
    // Отправляем запрос на сохранение
    $.ajax({
        url: 'modules/production/operations/save.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(operationData),
        success: function(response) {
            try {
                const data = JSON.parse(response);
                
                if (data.success) {
                    showAlert('success', 'Операция успешно сохранена');
                    
                    // Если это новая операция, перенаправляем на страницу редактирования
                    if (operationData.id == 0) {
                        // Открываем новую вкладку с созданной операцией
                        openOperationTab(data.id);
                        // Закрываем текущую вкладку
                        returnToList();
                    } else {
                        // Перезагружаем текущую страницу для обновления данных
                        loadContent(`modules/production/operations/edit_partial.php?id=${data.id}`);
                    }
                } else {
                    showAlert('danger', 'Ошибка при сохранении: ' + (data.error || 'Неизвестная ошибка'));
                }
            } catch (e) {
                console.error('Ошибка при обработке ответа:', e, response);
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
    } else if (!$('#product_id').val()) {
        showAlert('warning', 'Выберите производимый продукт');
        isValid = false;
    } else if (!$('#warehouse_id').val()) {
        showAlert('warning', 'Выберите склад');
        isValid = false;
    } else if (!$('#output_quantity').val() || parseFloat($('#output_quantity').val()) <= 0) {
        showAlert('warning', 'Укажите корректное количество выхода');
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