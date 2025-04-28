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
                                <select class="form-select" id="product_id" name="product_id" required>
                                    <option value="">Выберите продукт...</option>
                                    <?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id'] ?>" 
                                            data-unit="<?= htmlspecialchars($product['unit_of_measure']) ?>"
                                            <?= (isset($recipe['product_id']) && $recipe['product_id'] == $product['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($product['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="output_quantity" class="form-label">Выход (количество)</label>
                            <div class="input-group">
                                <?php if ($view_mode): ?>
                                    <?php
                                    $unit_of_measure = "шт";
                                    foreach ($products as $product) {
                                        if ($product['id'] == $recipe['product_id']) {
                                            $unit_of_measure = $product['unit_of_measure'];
                                            break;
                                        }
                                    }
                                    ?>
                                    <p class="form-control-static"><?= number_format($recipe['output_quantity'] ?? 0, 2) ?> <?= htmlspecialchars($unit_of_measure) ?></p>
                                <?php else: ?>
                                    <input type="number" step="0.01" min="0.01" class="form-control" id="output_quantity" name="output_quantity" 
                                        value="<?= number_format($recipe['output_quantity'] ?? 0, 2, '.', '') ?>" required>
                                    <span class="input-group-text" id="output_unit">
                                        <?php
                                        $unit_of_measure = "шт";
                                        foreach ($products as $product) {
                                            if ($product['id'] == $recipe['product_id']) {
                                                $unit_of_measure = $product['unit_of_measure'];
                                                break;
                                            }
                                        }
                                        echo htmlspecialchars($unit_of_measure);
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="description" class="form-label">Описание процесса производства</label>
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
                            <?php if (count($ingredients) > 0): ?>
                                <?php foreach ($ingredients as $index => $item): ?>
                                <tr data-index="<?= $index ?>" data-ingredient-id="<?= $item['ingredient_id'] ?>">
                                    <td>
                                        <?php if ($view_mode): ?>
                                            <?= htmlspecialchars($item['ingredient_name']) ?>
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
                
                <?php if (!$view_mode): ?>
                <div class="row mt-3">
                    <div class="col-md-5">
                        <select class="form-select" id="new_ingredient">
                            <option value="">Выберите ингредиент...</option>
                            <?php foreach ($products as $product): ?>
                            <option value="<?= $product['id'] ?>" 
                                    data-unit="<?= htmlspecialchars($product['unit_of_measure']) ?>"
                                    data-name="<?= htmlspecialchars($product['name']) ?>">
                                <?= htmlspecialchars($product['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group">
                            <input type="number" step="0.01" min="0.01" class="form-control" id="new_quantity" value="1.00">
                            <span class="input-group-text" id="new_unit">шт</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <button type="button" id="add_new_ingredient" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Добавить ингредиент
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<script>
// Глобальные переменные
let ingredientIndex = <?= count($ingredients) ?>;

// Инициализация при загрузке страницы
$(document).ready(function() {
    // Обработчик выбора продукта
    $('#product_id').on('change', function() {
        const selected = $(this).find('option:selected');
        const unit = selected.data('unit');
        
        if (unit) {
            $('#output_unit').text(unit);
        }
    });
    
    // Обработчик добавления нового ингредиента через форму внизу
    $('#add_new_ingredient').on('click', function() {
        const ingredientId = $('#new_ingredient').val();
        if (!ingredientId) {
            showAlert('warning', 'Выберите ингредиент');
            return;
        }
        
        const quantity = $('#new_quantity').val();
        if (!quantity || quantity <= 0) {
            showAlert('warning', 'Введите корректное количество');
            return;
        }
        
        // Проверяем, есть ли уже такой ингредиент в таблице
        const existingRow = $(`#ingredients_table tbody tr[data-ingredient-id="${ingredientId}"]`);
        if (existingRow.length > 0) {
            showAlert('warning', 'Этот ингредиент уже добавлен в рецепт');
            return;
        }
        
        // Получаем данные об ингредиенте
        const ingredientName = $('#new_ingredient option:selected').text();
        const unit = $('#new_ingredient option:selected').data('unit') || 'шт';
        
        // Добавляем ингредиент в таблицу
        addIngredientRow(ingredientId, ingredientName, quantity, unit);
        
        // Сбрасываем форму
        $('#new_ingredient').val('');
        $('#new_quantity').val('1.00');
        $('#new_unit').text('шт');
    });
    
    // Обработчик кнопки добавления ингредиента
    $('#add_ingredient').on('click', function() {
        $('#new_ingredient').focus();
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
    
    // Обработчик выбора ингредиента в новой форме
    $('#new_ingredient').on('change', function() {
        const selected = $(this).find('option:selected');
        const unit = selected.data('unit');
        
        if (unit) {
            $('#new_unit').text(unit);
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
                <select class="form-select ingredient-select" name="ingredients[${ingredientIndex}][ingredient_id]" required>
                    <option value="">Выберите ингредиент...</option>
                    <?php foreach ($products as $product): ?>
                    <option value="<?= $product['id'] ?>" 
                            data-unit="<?= htmlspecialchars($product['unit_of_measure']) ?>"
                            ${<?= $product['id'] ?> == ingredientId ? 'selected' : ''}>
                        <?= htmlspecialchars($product['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
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
        const ingredientId = $(this).find('.ingredient-select').val();
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
        $('#product_id').focus();
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