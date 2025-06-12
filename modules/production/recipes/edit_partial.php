<?php
// /crm/modules/production/recipes/edit_partial.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

// Проверяем доступ
if (!check_access($conn, $_SESSION['user_id'], 'production')) {
    echo '<div class="alert alert-danger">У вас нет доступа к этому разделу.</div>';
    return;
}

// Получение ID рецепта из параметров
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$recipe = null;
$ingredients = [];

// Получаем параметр режима просмотра (если есть)
$view_mode = isset($_GET['view']) && $_GET['view'] == 'true';

// Проверяем существование таблиц
$tables_exist = true;
$required_tables = ['PCRM_ProductionRecipe', 'PCRM_ProductionRecipeItem', 'PCRM_Product'];
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
    // Если редактируем существующий рецепт
    if ($id > 0) {
        $stmt = $conn->prepare("SELECT * FROM PCRM_ProductionRecipe WHERE id = ? AND deleted = 0");
        
        if (!$stmt) {
            throw new Exception("Ошибка подготовки запроса: " . $conn->error);
        }
        
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo '<div class="alert alert-warning">Рецепт не найден.</div>';
            return;
        }
        
        $recipe = $result->fetch_assoc();
        
        // Получаем ингредиенты рецепта
        $items_stmt = $conn->prepare("
            SELECT ri.*, p.name as ingredient_name, p.unit_of_measure 
            FROM PCRM_ProductionRecipeItem ri
            JOIN PCRM_Product p ON ri.ingredient_id = p.id
            WHERE ri.recipe_id = ?
        ");
        
        if ($items_stmt) {
            $items_stmt->bind_param('i', $id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            while ($item = $items_result->fetch_assoc()) {
                $ingredients[] = $item;
            }
        }
    } else {
        // Если создаем новый рецепт, инициализируем значения по умолчанию
        $recipe = [
            'id' => 0,
            'name' => '',
            'description' => '',
            'product_id' => 0,
            'output_quantity' => 1.00,
            'status' => 'active'
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
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Ошибка при загрузке данных: ' . htmlspecialchars($e->getMessage()) . '</div>';
    return;
}

// Определяем заголовки и режим формы
if ($view_mode) {
    $recipe_title = 'Просмотр рецепта производства';
    $button_text = 'Вернуться к списку';
    $mode = 'view';
} else {
    $recipe_title = $id > 0 ? 'Редактирование рецепта производства' : 'Новый рецепт производства';
    $button_text = $id > 0 ? 'Сохранить изменения' : 'Создать рецепт';
    $mode = 'edit';
}
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h4><?= htmlspecialchars($recipe_title) ?></h4>
        </div>
        <div class="col-md-6 text-end">
            <button type="button" class="btn btn-secondary" id="cancelRecipeEdit">Отмена</button>
            <?php if (!$view_mode): ?>
            <button type="button" class="btn btn-primary" id="saveRecipeBtn">
                <i class="fas fa-save"></i> <?= htmlspecialchars($button_text) ?>
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Блок для уведомлений -->
    <div id="recipe-alerts"></div>
    
    <form id="recipeForm">
        <input type="hidden" id="recipe_id" name="recipe_id" value="<?= $id ?>">
        <input type="hidden" id="form_mode" name="form_mode" value="<?= $mode ?>">
        
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title">Основная информация</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="recipe_name" class="form-label">Название рецепта</label>
                            <?php if ($view_mode): ?>
                                <p class="form-control-static"><?= htmlspecialchars($recipe['name'] ?? 'Н/Д') ?></p>
                            <?php else: ?>
                                <input type="text" class="form-control" id="recipe_name" name="recipe_name" 
                                    value="<?= htmlspecialchars($recipe['name'] ?? '') ?>" required>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="recipe_status" class="form-label">Статус</label>
                            <?php if ($view_mode): ?>
                                <p class="form-control-static"><?= $recipe['status'] == 'active' ? 'Активен' : 'Неактивен' ?></p>
                            <?php else: ?>
                                <select class="form-select" id="recipe_status" name="recipe_status">
                                    <option value="active" <?= ($recipe['status'] ?? '') == 'active' ? 'selected' : '' ?>>Активен</option>
                                    <option value="inactive" <?= ($recipe['status'] ?? '') == 'inactive' ? 'selected' : '' ?>>Неактивен</option>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="product_id" class="form-label">Производимый продукт</label>
                            <?php if ($view_mode): ?>
                                <?php
                                $product_name = "Н/Д";
                                foreach ($products as $product) {
                                    if ($product['id'] == $recipe['product_id']) {
                                        $product_name = $product['name'];
                                        break;
                                    }
                                }
                                ?>
                                <p class="form-control-static"><?= htmlspecialchars($product_name) ?></p>
                            <?php else: ?>
                                <div class="product-selector-container" id="main-product-selector"></div>
                                <input type="hidden" id="product_id" name="product_id" value="<?= $recipe['product_id'] ?? '' ?>">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="output_quantity" class="form-label">Количество выхода</label>
                            <?php if ($view_mode): ?>
                                <p class="form-control-static"><?= htmlspecialchars($recipe['output_quantity'] ?? '1.00') ?></p>
                            <?php else: ?>
                                <input type="number" step="0.01" min="0.01" class="form-control" 
                                       id="output_quantity" name="output_quantity" 
                                       value="<?= htmlspecialchars($recipe['output_quantity'] ?? '1.00') ?>" required>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="description" class="form-label">Описание</label>
                            <?php if ($view_mode): ?>
                                <div class="p-2 bg-light rounded">
                                    <?= nl2br(htmlspecialchars($recipe['description'] ?? '')) ?>
                                </div>
                            <?php else: ?>
                                <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($recipe['description'] ?? '') ?></textarea>
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
                <div>
                    <button type="button" class="btn btn-primary btn-sm" id="add_ingredient">
                        <i class="fas fa-plus"></i> Добавить ингредиент
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-hover" id="ingredients_table">
                    <thead>
                        <tr>
                            <th style="width: 50%;">Ингредиент</th>
                            <th>Количество</th>
                            <th>Ед. изм.</th>
                            <?php if (!$view_mode): ?>
                            <th style="width: 60px;">Действие</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="ingredients_body">
                        <!-- Динамические строки будут добавлены здесь с помощью JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>

<!-- Подключение общих JavaScript функций -->
<script src="/crm/js/common.js"></script>

<script>
$(document).ready(function() {
    // Получаем режим формы и данные из PHP
    const isViewMode = $('#form_mode').val() === 'view';
    const initialProductId = $('#product_id').val();
    let ingredientsData = <?= json_encode($ingredients) ?>;
    
    // --- Инициализация главного селектора продукта ---
    const mainProductSelector = new ProductSelector('#main-product-selector', {
        initialProductId: (initialProductId && initialProductId > 0) ? parseInt(initialProductId) : null,
        onSelect: function(product) {
            if (product) {
                console.log('Выбран основной продукт:', product);
                $('#product_id').val(product.id);
            } else {
                 $('#product_id').val('');
            }
        },
        disabled: isViewMode,
        // Используем встроенный метод loadModalData() из ProductSelector
        // Удален кастомный onShowAll обработчик
    });

    // --- Логика таблицы ингредиентов ---
    let ingredientSelectors = []; // Массив для хранения экземпляров селекторов

    // Функция для добавления строки ингредиента
    window.addIngredientRow = function(ingredient = null) {
        const tableBody = $('#ingredients_body');
        const newRowId = `ingredient-row-${tableBody.children().length}`;
        const selectorId = `ingredient-selector-${tableBody.children().length}`;

        const row = `
            <tr id="${newRowId}">
                <td>
                    <div id="${selectorId}" class="product-selector-container"></div>
                    <input type="hidden" name="ingredients[${tableBody.children().length}][ingredient_id]" class="ingredient-id-input">
                </td>
                <td>
                    <input type="number" step="0.01" min="0.01" class="form-control quantity-input" name="ingredients[${tableBody.children().length}][quantity]" value="${ingredient ? ingredient.quantity : '1.00'}" ${isViewMode ? 'disabled' : ''}>
                </td>
                <td class="unit-of-measure">${ingredient ? ingredient.unit_of_measure : '...'}</td>
                ${!isViewMode ? `
                <td>
                    <button type="button" class="btn btn-danger btn-sm delete-ingredient">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>` : ''}
            </tr>
        `;
        tableBody.append(row);

        const newSelector = new ProductSelector(`#${selectorId}`, {
            initialProductId: (ingredient && ingredient.ingredient_id > 0) ? parseInt(ingredient.ingredient_id) : null,
            onSelect: function(product) {
                const currentRow = $(`#${selectorId}`).closest('tr');
                if (product) {
                    currentRow.find('.ingredient-id-input').val(product.id);
                    currentRow.find('.unit-of-measure').text(product.unit || product.unit_of_measure || 'шт');
                } else {
                    currentRow.find('.ingredient-id-input').val('');
                    currentRow.find('.unit-of-measure').text('...');
                }
            },
            disabled: isViewMode,
            // Используем встроенный метод loadModalData() из ProductSelector
            // Удален кастомный onShowAll обработчик
        });
        ingredientSelectors.push(newSelector);
    };

    // Добавление новой строки
    $('#add_ingredient').on('click', function() {
        addIngredientRow();
    });

    // Удаление строки
    $('#ingredients_table').on('click', '.delete-ingredient', function() {
        $(this).closest('tr').remove();
        // Примечание: нужно будет переиндексировать name атрибуты, если это важно для бэкенда
        // В данном случае, бэкенд обработает массив как есть.
    });

    // Загрузка существующих ингредиентов
    if (ingredientsData && ingredientsData.length > 0) {
        ingredientsData.forEach(function(ing) {
            addIngredientRow(ing);
        });
    } else if (!isViewMode) {
        // Если это новый рецепт, добавляем одну пустую строку
        addIngredientRow();
    }
    
    // --- Сохранение и отмена ---
    $('#cancelRecipeEdit').on('click', function() {
        if (confirm('Вы уверены, что хотите отменить? Все несохраненные изменения будут потеряны.')) {
            openModuleTab('production/recipes/list');
        }
    });

    $('#saveRecipeBtn').on('click', function() {
        const formData = new FormData($('#recipeForm')[0]);

        // Собираем данные ингредиентов вручную, чтобы убедиться в их корректности
        let ingredients = [];
        $('#ingredients_body tr').each(function(index, row) {
            const ingredientId = $(row).find('.ingredient-id-input').val();
            const quantity = $(row).find('.quantity-input').val();
            if (ingredientId && quantity) {
                ingredients.push({
                    ingredient_id: ingredientId,
                    quantity: quantity
                });
            }
        });
        
        // Добавляем массив ингредиентов в FormData
        formData.append('ingredients_json', JSON.stringify(ingredients));
        
        // Добавляем ID рецепта, если он есть
        formData.append('recipe_id', $('#recipe_id').val());

        console.log('--- ОТПРАВКА ДАННЫХ РЕЦЕПТА ---');
        for (const pair of formData.entries()) {
            console.log(`${pair[0]}: ${pair[1]}`);
        }
        console.log('------------------------------------');

        $.ajax({
            url: 'modules/production/recipes/api.php?action=save_recipe',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showNotification('Рецепт успешно сохранен!', 'success');
                    openModuleTab('production/recipes/list');
                } else {
                    showNotification('Ошибка: ' + response.message, 'error');
                    $('#recipe-alerts').html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            },
            error: function(xhr, status, error) {
                const errorMsg = xhr.responseText ? JSON.parse(xhr.responseText).message : 'Серверная ошибка';
                showNotification('Ошибка при сохранении: ' + errorMsg, 'error');
                $('#recipe-alerts').html('<div class="alert alert-danger">Ошибка: ' + errorMsg + '</div>');
            }
        });
    });
});
</script>