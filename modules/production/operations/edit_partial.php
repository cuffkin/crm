<?php
// /crm/modules/production/operations/edit_partial.php
// Файл для редактирования и просмотра операций производства (Версия 4.0, рефакторинг)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/utils.php';

// --- ЗАГРУЗКА ДАННЫХ ---
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$operation = null;
$operation_items = [];
$view_mode = isset($_GET['view']) && $_GET['view'] == 'true';

try {
    if ($id > 0) {
        $stmt = $conn->prepare("
            SELECT o.*, p.name as product_name, p.unit_of_measure, w.name as warehouse_name, r.name as recipe_name
            FROM PCRM_ProductionOperation o
            LEFT JOIN PCRM_Product p ON o.product_id = p.id
            LEFT JOIN PCRM_Warehouse w ON o.warehouse_id = w.id
            LEFT JOIN PCRM_ProductionRecipe r ON o.recipe_id = r.id
            WHERE o.id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) { throw new Exception("Операция не найдена."); }
        $operation = $result->fetch_assoc();
        
        if (!empty($operation['conducted'])) { $view_mode = true; }

        $items_stmt = $conn->prepare("
            SELECT i.*, p.name as product_name, p.unit_of_measure, 
                   (SELECT COALESCE(SUM(s.quantity), 0) FROM PCRM_Stock s WHERE s.prod_id = i.ingredient_id AND s.warehouse_id = ?) as stock_quantity
            FROM PCRM_ProductionOperationItem i
            JOIN PCRM_Product p ON i.ingredient_id = p.id
            WHERE i.operation_id = ?
        ");
        $items_stmt->bind_param('ii', $operation['warehouse_id'], $id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
        while ($item = $items_result->fetch_assoc()) { $operation_items[] = $item; }
    } else {
        $operation = [
            'id' => 0, 'operation_number' => generateOperationNumber($conn), 'production_date' => date('Y-m-d'),
            'product_id' => 0, 'recipe_id' => 0, 'warehouse_id' => 0, 'output_quantity' => 1.00,
            'comment' => '', 'status' => 'draft'
        ];
    }

    $recipes = $conn->query("SELECT r.id, r.name, r.product_id FROM PCRM_ProductionRecipe r WHERE r.status = 'active' AND r.deleted = 0 ORDER BY r.name")->fetch_all(MYSQLI_ASSOC);
    $warehouses = $conn->query("SELECT id, name FROM PCRM_Warehouse WHERE status = 'active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Ошибка: ' . htmlspecialchars($e->getMessage()) . '</div>';
    return;
}

$title = $view_mode ? 'Просмотр операции' : ($id > 0 ? 'Редактирование операции' : 'Новая операция');
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6"><h4><?= htmlspecialchars($title) ?></h4></div>
        <div class="col-md-6 text-end">
            <button type="button" class="btn btn-secondary" id="cancelOperationEdit">Отмена</button>
            <?php if (!$view_mode): ?><button type="button" class="btn btn-primary" id="saveOperationBtn"><i class="fas fa-save"></i> Сохранить</button><?php endif; ?>
        </div>
    </div>
    
    <form id="operationForm" novalidate>
        <input type="hidden" name="operation_id" value="<?= $id ?>">
        
        <div class="card mb-3"><div class="card-header"><h5 class="card-title">Основная информация</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4"><div class="mb-3">
                        <label for="operation_number" class="form-label">Номер</label>
                        <input type="text" class="form-control" id="operation_number" name="operation_number" value="<?= htmlspecialchars($operation['operation_number'] ?? '') ?>" <?= $view_mode ? 'disabled' : '' ?> required>
                    </div></div>
                    <div class="col-md-4"><div class="mb-3">
                        <label for="production_date" class="form-label">Дата</label>
                        <input type="date" class="form-control" id="production_date" name="production_date" value="<?= htmlspecialchars(date('Y-m-d', strtotime($operation['production_date']))) ?>" <?= $view_mode ? 'disabled' : '' ?> required>
                    </div></div>
                     <div class="col-md-4"><div class="mb-3">
                            <label for="status" class="form-label">Статус</label>
                        <select class="form-select" id="status" name="status" <?= $view_mode ? 'disabled' : '' ?>>
                            <option value="draft" <?= ($operation['status'] == 'draft') ? 'selected' : '' ?>>Черновик</option>
                            <option value="completed" <?= ($operation['status'] == 'completed') ? 'selected' : '' ?>>Завершено</option>
                            <option value="cancelled" <?= ($operation['status'] == 'cancelled') ? 'selected' : '' ?>>Отменено</option>
                                </select>
                    </div></div>
                </div>
                <div class="row">
                    <div class="col-md-6"><div class="mb-3">
                            <label for="recipe_id" class="form-label">Рецепт</label>
                         <select class="form-select" id="recipe_id" name="recipe_id" <?= $view_mode ? 'disabled' : '' ?>>
                                    <option value="">Выберите рецепт...</option>
                                    <?php foreach ($recipes as $recipe): ?>
                            <option value="<?= $recipe['id'] ?>" data-product-id="<?= $recipe['product_id'] ?>" <?= ($operation['recipe_id'] == $recipe['id']) ? 'selected' : '' ?>><?= htmlspecialchars($recipe['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                    </div></div>
                    <div class="col-md-6"><div class="mb-3">
                        <label for="product_id" class="form-label">Готовый продукт</label>
                        <div id="operation-product-selector"></div>
                        <input type="hidden" id="product_id" name="product_id" value="<?= $operation['product_id'] ?? '' ?>">
                    </div></div>
                        </div>
                 <div class="row">
                    <div class="col-md-6"><div class="mb-3">
                        <label for="output_quantity" class="form-label">Количество выпуска</label>
                        <input type="number" step="0.01" class="form-control" id="output_quantity" name="output_quantity" value="<?= htmlspecialchars($operation['output_quantity'] ?? '1.00') ?>" <?= $view_mode ? 'disabled' : '' ?> required>
                    </div></div>
                    <div class="col-md-6"><div class="mb-3">
                            <label for="warehouse_id" class="form-label">Склад</label>
                         <select class="form-select" id="warehouse_id" name="warehouse_id" <?= $view_mode ? 'disabled' : '' ?> required>
                                    <option value="">Выберите склад...</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                             <option value="<?= $warehouse['id'] ?>" <?= ($operation['warehouse_id'] == $warehouse['id']) ? 'selected' : '' ?>><?= htmlspecialchars($warehouse['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                    </div></div>
                </div>
            </div>
        </div>
        
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Ингредиенты</h5>
                <?php if (!$view_mode): ?>
                    <button type="button" class="btn btn-primary btn-sm" id="add_ingredient"><i class="fas fa-plus"></i> Добавить</button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <table class="table table-bordered" id="ingredients_table">
                    <thead class="table-light"><tr>
                        <th style="width: 40%;">Ингредиент</th>
                        <th style="width: 15%;">Остаток на складе</th>
                        <th style="width: 15%;">Требуется</th>
                        <th style="width: 15%;">Факт. списание</th>
                        <th>Ед.</th>
                        <?php if (!$view_mode): ?><th style="width: 60px;"></th><?php endif; ?>
                    </tr></thead>
                        <tbody id="ingredients_body">
                        <!-- Динамическое содержимое -->
                        </tbody>
                    </table>
            </div>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    console.log("🟢 МОДУЛЬ ОПЕРАЦИЙ: Скрипт инициализации v4.2 - Fixed");

    // --- Состояние и переменные ---
    const isViewMode = <?= json_encode($view_mode) ?>;
    const initialItems = <?= json_encode($operation_items) ?>;
    let ingredientSelectors = [];

    // --- Элементы jQuery ---
    const $recipeSelect = $('#recipe_id');
    const $warehouseSelect = $('#warehouse_id');
    const $ingredientsBody = $('#ingredients_body');
    const $productIdField = $('#product_id');
    
    // --- Инициализация селектора основного продукта ---
    const mainProductSelector = new ProductSelector('#operation-product-selector', {
        initialProductId: <?= !empty($operation['product_id']) ? $operation['product_id'] : 'null' ?>,
        disabled: isViewMode,
        onSelect: (p) => {
            $productIdField.val(p ? p.id : '');
            if ($recipeSelect.val() && (!p || $recipeSelect.find('option:selected').data('product-id') != p.id)) {
                $recipeSelect.val(''); // Сбрасываем рецепт, если продукт изменен вручную
            }
        }
    });
    
    // --- Функции ---

    function addIngredientRow(data = {}) {
        const rowIndex = Date.now(); // Уникальный индекс
        const selectorId = `ing-selector-${rowIndex}`;
        const stockQuantity = parseFloat(data.stock_quantity || 0).toFixed(2);
        const requiredQuantity = parseFloat(data.quantity || data.required_quantity || 1).toFixed(3);
        const actualQuantity = parseFloat(data.actual_quantity || requiredQuantity).toFixed(3);
        const unit = data.unit_of_measure || '...';
        
        const rowHTML = `
            <tr data-ingredient-id="${data.ingredient_id || ''}">
                <td><div id="${selectorId}"></div><input type="hidden" class="ingredient-id-input" value="${data.ingredient_id || ''}"></td>
                <td class="stock-quantity text-end fw-bold">${stockQuantity}</td>
                <td><input type="number" step="0.001" class="form-control required-quantity" value="${requiredQuantity}" ${isViewMode ? 'disabled' : ''}></td>
                <td><input type="number" step="0.001" class="form-control actual-quantity" value="${actualQuantity}" ${isViewMode ? 'disabled' : ''}></td>
                <td class="unit-of-measure">${unit}</td>
                ${isViewMode ? '' : '<td><button type="button" class="btn btn-danger btn-sm remove-ingredient"><i class="fas fa-trash"></i></button></td>'}
            </tr>`;
        $ingredientsBody.append(rowHTML);
        
        const selector = new ProductSelector(`#${selectorId}`, {
            initialProductId: data.ingredient_id,
            disabled: isViewMode,
            onSelect: (p) => {
                const $row = $(`#${selectorId}`).closest('tr');
                $row.find('.ingredient-id-input').val(p ? p.id : '');
                $row.data('ingredient-id', p ? p.id : '');
                $row.find('.unit-of-measure').text(p ? p.unit_of_measure : '...');
                updateStockLevels(); // Обновляем остатки для всех при выборе нового ингредиента
            }
        });
        ingredientSelectors.push({ id: selectorId, instance: selector });
    }

    function fetchIngredientsForRecipe() {
        const recipeId = $recipeSelect.val();
        const warehouseId = $warehouseSelect.val();
        
        if (!recipeId) {
            $ingredientsBody.html('<tr><td colspan="6" class="text-center">Выберите рецепт для загрузки ингредиентов.</td></tr>');
            return;
        }
        if (!warehouseId) {
            alert('Пожалуйста, сначала выберите склад!');
            $recipeSelect.val('');
            return;
        }

        $.get(`modules/production/operations/api.php?action=get_recipe_ingredients`, { recipe_id: recipeId, warehouse_id: warehouseId })
            .done(data => {
                $ingredientsBody.empty();
                ingredientSelectors = [];
                if (data.success && data.ingredients.length > 0) {
                    data.ingredients.forEach(addIngredientRow);
                } else {
                    $ingredientsBody.html('<tr><td colspan="6" class="text-center">В выбранном рецепте нет ингредиентов.</td></tr>');
                }
            })
            .fail(() => showNotification('Ошибка при загрузке ингредиентов.', 'error'));
        
        const productId = $recipeSelect.find('option:selected').data('product-id');
        mainProductSelector.setProductById(productId);
    }

    function updateStockLevels() {
        const warehouseId = $warehouseSelect.val();
        const ingredientIds = $ingredientsBody.find('.ingredient-id-input').map((i, el) => $(el).val()).get().filter(id => id);

        if (!warehouseId || ingredientIds.length === 0) return;

    $.ajax({
            url: `modules/production/operations/api.php?action=get_stock_for_ingredients`,
        type: 'POST',
        contentType: 'application/json',
            data: JSON.stringify({ ingredient_ids: ingredientIds, warehouse_id: warehouseId })
        }).done(data => {
                if (data.success) {
                $ingredientsBody.find('tr').each((i, el) => {
                    const id = $(el).find('.ingredient-id-input').val();
                    if (data.stocks[id] !== undefined) {
                        $(el).find('.stock-quantity').text(parseFloat(data.stocks[id]).toFixed(2));
                    }
                });
        }
    });
}

    // --- Обработчики событий ---
    $recipeSelect.on('change', fetchIngredientsForRecipe);
    $warehouseSelect.on('change', updateStockLevels);
    
    $('#add_ingredient').on('click', function() {
        if ($ingredientsBody.find('td[colspan]').length) $ingredientsBody.empty();
        addIngredientRow();
    });

    $ingredientsBody.on('click', '.remove-ingredient', function() {
        $(this).closest('tr').remove();
    });

    // --- Инициализация ---
    if (initialItems && initialItems.length > 0) {
        initialItems.forEach(addIngredientRow);
    } else {
        $ingredientsBody.html('<tr><td colspan="6" class="text-center">Нет ингредиентов для отображения.</td></tr>');
    }
});
</script>