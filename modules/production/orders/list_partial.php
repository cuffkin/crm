<?php
// /crm/modules/production/orders/list_partial.php
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
$required_tables = ['PCRM_ProductionOrder', 'PCRM_ProductionRecipe', 'PCRM_Product', 'PCRM_Warehouse', 'PCRM_User'];
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
    $orders = [];
} else {
    try {
        // Упрощенный запрос для избежания проблем с JOIN
        $sql = "
        SELECT po.*, 
               po.recipe_id, po.warehouse_id
        FROM PCRM_ProductionOrder po
        ORDER BY po.id DESC
        ";
        
        $res = $conn->query($sql);
        if (!$res) {
            throw new Exception("Ошибка выполнения запроса: " . $conn->error);
        }
        
        $orders = [];
        while($row = $res->fetch_assoc()) {
            // Получаем дополнительную информацию отдельными запросами
            // Информация о рецепте и продукте
            $recipe_name = "Н/Д";
            $product_name = "Н/Д";
            if(isset($row['recipe_id']) && $row['recipe_id'] > 0) {
                $recipe_stmt = $conn->prepare("SELECT r.name, p.name as product_name
                                            FROM PCRM_ProductionRecipe r
                                            LEFT JOIN PCRM_Product p ON r.product_id = p.id
                                            WHERE r.id = ?");
                if($recipe_stmt) {
                    $recipe_stmt->bind_param('i', $row['recipe_id']);
                    $recipe_stmt->execute();
                    $recipe_result = $recipe_stmt->get_result();
                    if($recipe_result && $recipe_row = $recipe_result->fetch_assoc()) {
                        $recipe_name = $recipe_row['name'];
                        $product_name = $recipe_row['product_name'];
                    }
                }
            }
            
            // Информация о складе
            $warehouse_name = "Н/Д";
            if(isset($row['warehouse_id']) && $row['warehouse_id'] > 0) {
                $wh_stmt = $conn->prepare("SELECT name FROM PCRM_Warehouse WHERE id = ?");
                if($wh_stmt) {
                    $wh_stmt->bind_param('i', $row['warehouse_id']);
                    $wh_stmt->execute();
                    $wh_result = $wh_stmt->get_result();
                    if($wh_result && $wh_row = $wh_result->fetch_assoc()) {
                        $warehouse_name = $wh_row['name'];
                    }
                }
            }
            
            $row['recipe_name'] = $recipe_name;
            $row['product_name'] = $product_name;
            $row['warehouse_name'] = $warehouse_name;
            $orders[] = $row;
        }
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Ошибка при загрузке данных: ' . htmlspecialchars($e->getMessage()) . '</div>';
        $orders = [];
    }
}
?>

<div class="card">
    <div class="card-header">
        <h4 class="card-title">Заказы на производство</h4>
        <div class="card-tools">
            <button type="button" class="btn btn-primary" id="newOrderBtn">
                <i class="fas fa-plus"></i> Создать заказ
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>№ заказа</th>
                        <th>Рецепт</th>
                        <th>Продукт</th>
                        <th>Количество</th>
                        <th>Дата</th>
                        <th>Склад</th>
                        <th>Статус</th>
                        <th>Проведен</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($orders) > 0): ?>
                    <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><?= $o['id'] ?></td>
                        <td><?= htmlspecialchars($o['order_number'] ?? '') ?></td>
                        <td><?= htmlspecialchars($o['recipe_name']) ?></td>
                        <td><?= htmlspecialchars($o['product_name']) ?></td>
                        <td><?= isset($o['quantity']) ? number_format($o['quantity'], 3) : '0.000' ?></td>
                        <td><?= isset($o['planned_date']) ? date('d.m.Y H:i', strtotime($o['planned_date'])) : 'Н/Д' ?></td>
                        <td><?= htmlspecialchars($o['warehouse_name']) ?></td>
                        <td><?= htmlspecialchars($o['status'] ?? 'Н/Д') ?></td>
                        <td><?= isset($o['conducted']) && $o['conducted'] ? 'Да' : 'Нет' ?></td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-warning btn-sm" onclick="editOrder(<?= $o['id'] ?>)" <?= isset($o['conducted']) && $o['conducted'] ? 'disabled' : '' ?>>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deleteOrder(<?= $o['id'] ?>)" <?= isset($o['conducted']) && $o['conducted'] ? 'disabled' : '' ?>>
                                    <i class="fas fa-trash"></i>
                                </button>
                                
                                <?php if (!(isset($o['conducted']) && $o['conducted'])): ?>
                                <button class="btn btn-success btn-sm" onclick="conductOrder(<?= $o['id'] ?>)">
                                    <i class="fas fa-check"></i>
                                </button>
                                <?php else: ?>
                                <button class="btn btn-secondary btn-sm" onclick="cancelOrder(<?= $o['id'] ?>)">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-info btn-sm" onclick="createOperation(<?= $o['id'] ?>)">
                                    <i class="fas fa-industry"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="text-center">Заказы на производство не найдены</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="order-edit-area"></div>

<script>
$(document).ready(function() {
    // Обработчик для создания нового заказа
    $('#newOrderBtn').click(function() {
        editOrder(0);
    });
});

function editOrder(orderId) {
    $.ajax({
        url: 'modules/production/orders/edit_partial.php',
        data: { id: orderId },
        success: function(html) {
            $('#order-edit-area').html(html).addClass('fade-in');
        },
        error: function(xhr, status, error) {
            showAlert('danger', 'Произошла ошибка при загрузке формы заказа: ' + error);
        }
    });
}

function deleteOrder(orderId) {
    // Вызываем глобальную функцию напрямую (она определена в app.js)
    if (typeof moveToTrash === 'function') {
        moveToTrash('production_order', orderId, 'Вы уверены, что хотите удалить заказ на производство?', function() {
            // Обновляем список заказов на производство
            const activeTab = document.querySelector('.tab-pane.active');
            if (activeTab) {
                const moduleTab = document.querySelector('.nav-link.active[data-module*="production/orders"]');
                if (moduleTab) {
                    const modulePath = moduleTab.getAttribute('data-module');
                    fetch(modulePath)
                        .then(response => response.text())
                        .then(html => activeTab.innerHTML = html)
                        .catch(error => console.error('Error reloading production orders:', error));
                }
            }
        });
    } else {
        console.error('Глобальная функция moveToTrash не найдена');
        alert('Ошибка: функция удаления не найдена');
    }
}

function conductOrder(orderId) {
    if (!confirm('Провести заказ на производство?')) return;
    
    $.ajax({
        url: 'modules/production/orders/conduct.php',
        data: { id: orderId, action: 'conduct' },
        type: 'POST',
        success: function(response) {
            try {
                var data = JSON.parse(response);
                if (data.success) {
                    showAlert('success', 'Заказ успешно проведен');
                    loadContent('modules/production/orders/list_partial.php');
                } else {
                    showAlert('danger', 'Ошибка: ' + data.error);
                }
            } catch (e) {
                if (response === 'OK') {
                    showAlert('success', 'Заказ успешно проведен');
                    loadContent('modules/production/orders/list_partial.php');
                } else {
                    showAlert('danger', 'Ошибка: ' + response);
                }
            }
        },
        error: function(xhr, status, error) {
            showAlert('danger', 'Произошла ошибка при выполнении запроса: ' + error);
        }
    });
}

function cancelOrder(orderId) {
    if (!confirm('Отменить проведение заказа на производство?')) return;
    
    $.ajax({
        url: 'modules/production/orders/conduct.php',
        data: { id: orderId, action: 'cancel' },
        type: 'POST',
        success: function(response) {
            try {
                var data = JSON.parse(response);
                if (data.success) {
                    showAlert('success', 'Проведение заказа отменено');
                    loadContent('modules/production/orders/list_partial.php');
                } else {
                    showAlert('danger', 'Ошибка: ' + data.error);
                }
            } catch (e) {
                if (response === 'OK') {
                    showAlert('success', 'Проведение заказа отменено');
                    loadContent('modules/production/orders/list_partial.php');
                } else {
                    showAlert('danger', 'Ошибка: ' + response);
                }
            }
        },
        error: function(xhr, status, error) {
            showAlert('danger', 'Произошла ошибка при выполнении запроса: ' + error);
        }
    });
}

function createOperation(orderId) {
    $.ajax({
        url: 'modules/production/operations/create_from_order.php',
        data: { order_id: orderId },
        success: function(response) {
            try {
                var data = JSON.parse(response);
                if (data.success) {
                    showAlert('success', 'Операция производства создана');
                    // Открываем вкладку операций производства
                    loadContent('modules/production/operations/list_partial.php');
                } else {
                    showAlert('danger', 'Ошибка: ' + data.error);
                }
            } catch (e) {
                if (response.startsWith('OK:')) {
                    const operationId = response.split(':')[1];
                    showAlert('success', 'Операция производства создана');
                    // Открываем вкладку операций производства
                    loadContent('modules/production/operations/list_partial.php');
                } else {
                    showAlert('danger', 'Ошибка: ' + response);
                }
            }
        },
        error: function(xhr, status, error) {
            showAlert('danger', 'Произошла ошибка при создании операции: ' + error);
        }
    });
}
</script> 