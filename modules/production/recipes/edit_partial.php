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
        $stmt = $conn->prepare("SELECT * FROM PCRM_ProductionRecipe WHERE id = ?");
        
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
            <div class="card-header">
                <h5 class="card-title">Ингредиенты</h5>
                <?php if (!$view_mode): ?>
                <div class="float-end">
                    <button type="button" class="btn btn-primary btn-sm" id="add_ingredient">
                        <i class="fas fa-plus"></i> Добавить ингредиент
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <table class="table table-bordered" id="ingredients_table">
                    <thead>
                        <tr>
                            <th>Ингредиент</th>
                            <th>Количество</th>
                            <th>Ед. изм.</th>
                            <?php if (!$view_mode): ?>
                            <th width="60">Действие</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="ingredients_body">
                        <?php if (empty($ingredients)): ?>
                        <tr class="no-ingredients-row">
                            <td colspan="<?= $view_mode ? 3 : 4 ?>" class="text-center">
                                <?= $view_mode ? 'Нет ингредиентов' : 'Нет добавленных ингредиентов' ?>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($ingredients as $index => $ingredient): ?>
                            <tr data-index="<?= $index ?>" data-ingredient-id="<?= $ingredient['ingredient_id'] ?>">
                                <td>
                                    <?php if ($view_mode): ?>
                                        <?= htmlspecialchars($ingredient['ingredient_name']) ?>
                                    <?php else: ?>
                                        <div class="product-selector-container ingredient-selector" data-ingredient-id="<?= $ingredient['ingredient_id'] ?>"></div>
                                        <input type="hidden" name="ingredients[<?= $index ?>][ingredient_id]" value="<?= $ingredient['ingredient_id'] ?>">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($view_mode): ?>
                                        <?= htmlspecialchars($ingredient['quantity']) ?>
                                    <?php else: ?>
                                        <input type="number" step="0.01" min="0.01" class="form-control ingredient-quantity" 
                                               name="ingredients[<?= $index ?>][quantity]" value="<?= $ingredient['quantity'] ?>" required>
                                    <?php endif; ?>
                                </td>
                                <td class="ingredient-unit">
                                    <?= htmlspecialchars($ingredient['unit_of_measure'] ?? 'шт') ?>
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
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if (!$view_mode): ?>
                <!-- Форма для добавления нового ингредиента -->
                <div class="row mt-3" id="new_ingredient_form" style="display: none;">
                    <div class="col-md-6">
                        <label class="form-label">Ингредиент</label>
                        <div class="product-selector-container" id="new-ingredient-selector"></div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Количество</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" id="new_quantity" value="1.00">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Ед. изм.</label>
                        <p class="form-control-static mt-2" id="new_unit">шт</p>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="button" class="btn btn-success btn-sm" id="confirm_add_ingredient">
                                <i class="fas fa-check"></i> Добавить
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" id="cancel_add_ingredient">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- Подключение общих JavaScript функций -->
<script src="/crm/js/common.js"></script>

<script>
console.log('🟢 МОДУЛЬ РЕЦЕПТОВ ПРОИЗВОДСТВА: Скрипт начал загружаться');

// Переменные для управления ингредиентами
let ingredientIndex = <?= count($ingredients) ?>;
let mainProductSelector = null;
let newIngredientSelector = null;
const ALL_PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;

$(document).ready(function() {
    console.log('📋 Инициализация рецептов производства...');
    
    <?php if (!$view_mode): ?>
    // Инициализируем основной селектор продукта
    mainProductSelector = createProductSelector('#main-product-selector', {
        context: 'production',
        placeholder: 'Выберите производимый продукт...',
        onSelect: function(product) {
            $('#product_id').val(product.id);
            console.log('✅ Выбран производимый продукт:', product.name);
        },
        onClear: function() {
            $('#product_id').val('');
        }
    });
    
    // Устанавливаем выбранный продукт если есть
    <?php if (!empty($recipe['product_id'])): ?>
    const mainProduct = ALL_PRODUCTS.find(p => p.id == <?= $recipe['product_id'] ?>);
    if (mainProduct) {
        mainProductSelector.setProduct(mainProduct);
    }
    <?php endif; ?>
    
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
    
    // Инициализируем селектор для нового ингредиента
    newIngredientSelector = createProductSelector('#new-ingredient-selector', {
        context: 'production',
        placeholder: 'Выберите ингредиент...',
        onSelect: function(product) {
            $('#new_unit').text(product.unit || 'шт');
            console.log('✅ Выбран новый ингредиент:', product.name);
        },
        onClear: function() {
            $('#new_unit').text('шт');
        }
    });
    <?php endif; ?>
    
    // Обработчик кнопки добавления ингредиента
    $('#add_ingredient').on('click', function() {
        $('#new_ingredient_form').show();
        if (newIngredientSelector) {
            newIngredientSelector.elements.input.focus();
        }
    });
    
    // Обработчик отмены добавления ингредиента
    $('#cancel_add_ingredient').on('click', function() {
        $('#new_ingredient_form').hide();
        if (newIngredientSelector) {
            newIngredientSelector.clear();
        }
        $('#new_quantity').val('1.00');
        $('#new_unit').text('шт');
    });
    
    // Обработчик подтверждения добавления ингредиента
    $('#confirm_add_ingredient').on('click', function() {
        if (!newIngredientSelector) return;
        
        const selectedProduct = newIngredientSelector.getSelectedProduct();
        if (!selectedProduct) {
            alert('Выберите ингредиент');
            return;
        }
        
        const quantity = $('#new_quantity').val();
        if (!quantity || parseFloat(quantity) <= 0) {
            alert('Укажите корректное количество');
            $('#new_quantity').focus();
            return;
        }
        
        const unit = $('#new_unit').text();
        
        // Добавляем ингредиент в таблицу
        addIngredientRow(selectedProduct.id, selectedProduct.name, quantity, unit);
        
        // Сбрасываем форму
        newIngredientSelector.clear();
        $('#new_quantity').val('1.00');
        $('#new_unit').text('шт');
        $('#new_ingredient_form').hide();
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
    
    // Обработчик кнопки сохранения
    $('#saveRecipeBtn').on('click', function() {
        saveRecipe();
    });
    
    // Обработчик кнопки отмены
    $('#cancelRecipeEdit').on('click', function() {
        returnToList();
    });
});

// Функция добавления строки ингредиента
function addIngredientRow(ingredientId, ingredientName, quantity, unit) {
    // Удаляем строку-заглушку, если она есть
    $('.no-ingredients-row').remove();
    
    const row = `
        <tr data-index="${ingredientIndex}" data-ingredient-id="${ingredientId}">
            <td>
                <div class="product-selector-container ingredient-selector" data-ingredient-id="${ingredientId}"></div>
                <input type="hidden" name="ingredients[${ingredientIndex}][ingredient_id]" value="${ingredientId}">
            </td>
            <td>
                <input type="number" step="0.01" min="0.01" class="form-control ingredient-quantity" 
                       name="ingredients[${ingredientIndex}][quantity]" value="${quantity}" required>
            </td>
            <td class="ingredient-unit">
                ${unit}
            </td>
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
    const ingredient = ALL_PRODUCTS.find(p => p.id == ingredientId);
    if (ingredient) {
        ingredientSelector.setProduct(ingredient);
    }
    
    ingredientIndex++;
}

// Функция сохранения рецепта
function saveRecipe() {
    // Проверяем заполнение обязательных полей
    if (!validateForm()) {
        return;
    }
    
    // Собираем данные формы
    const recipeData = {
        id: $('#recipe_id').val(),
        name: $('#recipe_name').val(),
        product_id: $('#product_id').val(),
        output_quantity: $('#output_quantity').val(),
        description: $('#description').val(),
        status: $('#recipe_status').val(),
        ingredients: []
    };
    
    // Добавляем ингредиенты
    $('#ingredients_table tbody tr').each(function() {
        const ingredientId = $(this).find('input[name$="[ingredient_id]"]').val();
        const quantity = $(this).find('.ingredient-quantity').val();
        
        if (ingredientId && quantity) {
            recipeData.ingredients.push({
                ingredient_id: ingredientId,
                quantity: quantity
            });
        }
    });
    
    // Отправляем запрос на сохранение
    $.ajax({
        url: 'modules/production/recipes/save.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(recipeData),
        success: function(response) {
            try {
                const data = JSON.parse(response);
                
                if (data.success) {
                    showAlert('success', 'Рецепт успешно сохранен');
                    
                    // Если это новый рецепт, перенаправляем на страницу редактирования
                    if (recipeData.id == 0) {
                        // Открываем новую вкладку с созданным рецептом
                        openRecipeTab(data.id);
                        // Закрываем текущую вкладку
                        returnToList();
                    } else {
                        // Перезагружаем текущую страницу для обновления данных
                        loadContent(`modules/production/recipes/edit_partial.php?id=${data.id}`);
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
    if (!$('#recipe_name').val()) {
        showAlert('warning', 'Укажите название рецепта');
        $('#recipe_name').focus();
        isValid = false;
    } else if (!$('#product_id').val()) {
        showAlert('warning', 'Выберите производимый продукт');
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
    
    // Добавляем уведомление в блок
    const alertBox = $(alertHTML);
    $('#recipe-alerts').append(alertBox);
    
    // Удаляем уведомление через 5 секунд
    setTimeout(function() {
        alertBox.fadeOut('slow', function() {
            $(this).remove();
        });
    }, 5000);
}

// Функция возврата к списку рецептов
function returnToList() {
    loadContent('modules/production/recipes/list_partial.php');
}
</script>