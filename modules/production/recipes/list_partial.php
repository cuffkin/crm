<?php
// /crm/modules/production/recipes/list_partial.php
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
    // Показываем пустую таблицу если нужные таблицы не существуют
    $recipes = [];
} else {
    try {
        // Упрощенный запрос для избежания проблем с JOIN, исключаем удаленные
        $sql = "
        SELECT pr.id, pr.name, pr.description, pr.status, pr.product_id, pr.output_quantity
        FROM PCRM_ProductionRecipe pr
        WHERE pr.deleted = 0
        ORDER BY pr.name
        ";
        $res = $conn->query($sql);
        if (!$res) {
            throw new Exception("Ошибка выполнения запроса: " . $conn->error);
        }
        
        $recipes = [];
        while($row = $res->fetch_assoc()) {
            // Получаем информацию о продукте отдельным запросом
            $product_name = "Н/Д";
            $unit_name = "шт";
            
            if(isset($row['product_id']) && $row['product_id'] > 0) {
                $product_stmt = $conn->prepare("
                    SELECT p.name, u.short_name as unit_name
                    FROM PCRM_Product p
                    LEFT JOIN PCRM_Unit u ON p.unit_id = u.id
                    WHERE p.id = ?
                ");
                
                if($product_stmt) {
                    $product_stmt->bind_param('i', $row['product_id']);
                    $product_stmt->execute();
                    $product_result = $product_stmt->get_result();
                    if($product_result && $product_row = $product_result->fetch_assoc()) {
                        $product_name = $product_row['name'];
                        $unit_name = $product_row['unit_name'] ?? 'шт';
                    }
                }
            }
            
            // Получаем количество ингредиентов отдельным запросом
            $ingredient_count = 0;
            $item_stmt = $conn->prepare("
                SELECT COUNT(*) as count
                FROM PCRM_ProductionRecipeItem
                WHERE recipe_id = ?
            ");
            
            if($item_stmt) {
                $item_stmt->bind_param('i', $row['id']);
                $item_stmt->execute();
                $item_result = $item_stmt->get_result();
                if($item_result && $item_row = $item_result->fetch_assoc()) {
                    $ingredient_count = $item_row['count'];
                }
            }
            
            $row['product_name'] = $product_name;
            $row['unit_name'] = $unit_name;
            $row['ingredient_count'] = $ingredient_count;
            $recipes[] = $row;
        }
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Ошибка при загрузке данных: ' . htmlspecialchars($e->getMessage()) . '</div>';
        $recipes = [];
    }
}
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="card-title mb-0">Рецепты производства</h4>
        <div>
            <button type="button" class="btn btn-primary" onclick="openRecipeTab(0)">
                <i class="fas fa-plus"></i> Новый рецепт
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Выходной продукт</th>
                        <th>Количество выхода</th>
                        <th>Ингредиентов</th>
                        <th>Активен</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recipes) > 0): ?>
                        <?php foreach ($recipes as $recipe): ?>
                            <tr>
                                <td><?= $recipe['id'] ?></td>
                                <td><?= htmlspecialchars($recipe['name']) ?></td>
                                <td><?= htmlspecialchars($recipe['product_name']) ?></td>
                                <td><?= isset($recipe['output_quantity']) ? number_format($recipe['output_quantity'], 2) : '0.00' ?> <?= htmlspecialchars($recipe['unit_name']) ?></td>
                                <td><?= $recipe['ingredient_count'] ?></td>
                                <td><?= ($recipe['status'] == 'active') ? 'Да' : 'Нет' ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-info btn-sm" onclick="openRecipeTab(<?= $recipe['id'] ?>, true)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-warning btn-sm" onclick="openRecipeTab(<?= $recipe['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteRecipe(<?= $recipe['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">Рецепты не найдены</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Функция удаления рецепта через корзину
function deleteRecipe(id) {
    // Вызываем глобальную функцию напрямую (она определена в app.js)
    if (typeof moveToTrash === 'function') {
        moveToTrash('production_recipe', id, 'Вы уверены, что хотите удалить этот рецепт?', function() {
            // Обновляем список рецептов
            const activeTab = document.querySelector('.tab-pane.active');
            if (activeTab) {
                const moduleTab = document.querySelector('.nav-link.active[data-module*="production/recipes"]');
                if (moduleTab) {
                    const modulePath = moduleTab.getAttribute('data-module');
                    fetch(modulePath)
                        .then(response => response.text())
                        .then(html => activeTab.innerHTML = html)
                        .catch(error => console.error('Error reloading production recipes:', error));
                }
            }
        });
    } else {
        console.error('Глобальная функция moveToTrash не найдена');
        alert('Ошибка: функция удаления не найдена');
    }
}

// Функция отображения уведомления
function showAlert(type, message) {
    // Проверяем, есть ли глобальная функция для уведомлений
    if (typeof window.appShowNotification === 'function') {
        window.appShowNotification(message, type);
        return;
    }
    
    const alertHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    // Создаем ID для блока уведомлений, если его нет
    if ($('#recipe-alerts').length === 0) {
        $('.card:first').before('<div id="recipe-alerts"></div>');
    }
    
    // Добавляем уведомление в специальный блок
    const alertBox = $(alertHTML);
    $('#recipe-alerts').append(alertBox);
    
    // Удаляем уведомление через 5 секунд
    setTimeout(function() {
        alertBox.fadeOut('slow', function() {
            $(this).remove();
        });
    }, 5000);
}
</script>