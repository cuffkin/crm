<?php
// modules/production/operations/view_partial.php
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

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo '<div class="alert alert-danger">Некорректный ID операции.</div>';
    return;
}

try {
    // Получаем данные операции
    $stmt = $conn->prepare("
        SELECT o.*
        FROM PCRM_ProductionOperation o
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
    
    // Получаем данные рецепта
    $recipe_name = "Н/Д";
    $product_name = "Н/Д";
    
    if (isset($operation['recipe_id']) && $operation['recipe_id'] > 0) {
        $recipe_stmt = $conn->prepare("
            SELECT r.name, p.name as product_name
            FROM PCRM_ProductionRecipe r
            LEFT JOIN PCRM_Product p ON r.product_id = p.id
            WHERE r.id = ?
        ");
        
        if ($recipe_stmt) {
            $recipe_stmt->bind_param('i', $operation['recipe_id']);
            $recipe_stmt->execute();
            $recipe_result = $recipe_stmt->get_result();
            if ($recipe_result && $recipe_row = $recipe_result->fetch_assoc()) {
                $recipe_name = $recipe_row['name'];
                $product_name = $recipe_row['product_name'];
            }
        }
    }
    
    // Получаем имя пользователя
    $user_name = "Н/Д";
    if (isset($operation['user_id']) && $operation['user_id'] > 0) {
        $user_stmt = $conn->prepare("SELECT name FROM PCRM_User WHERE id = ?");
        if ($user_stmt) {
            $user_stmt->bind_param('i', $operation['user_id']);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            if ($user_result && $user_row = $user_result->fetch_assoc()) {
                $user_name = $user_row['name'];
            }
        }
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Ошибка при загрузке данных: ' . htmlspecialchars($e->getMessage()) . '</div>';
    return;
}
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h4>Просмотр операции №<?= htmlspecialchars($operation['number'] ?? $id) ?></h4>
        </div>
        <div class="col-md-6 text-end">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            
            <?php if (!(isset($operation['conducted']) && $operation['conducted'])): ?>
            <button type="button" class="btn btn-primary" onclick="editOperation(<?= $id ?>)">
                <i class="fas fa-edit"></i> Редактировать
            </button>
            <button type="button" class="btn btn-success" onclick="conductOperation(<?= $id ?>, 'conduct')">
                <i class="fas fa-check"></i> Провести
            </button>
            <?php else: ?>
            <button type="button" class="btn btn-warning" onclick="conductOperation(<?= $id ?>, 'cancel')">
                <i class="fas fa-times"></i> Отменить проведение
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title">Основная информация</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <tr>
                            <th>ID операции:</th>
                            <td><?= $id ?></td>
                        </tr>
                        <tr>
                            <th>Номер:</th>
                            <td><?= htmlspecialchars($operation['number'] ?? 'Н/Д') ?></td>
                        </tr>
                        <tr>
                            <th>Дата:</th>
                            <td><?= isset($operation['created_at']) ? date('d.m.Y H:i', strtotime($operation['created_at'])) : 'Н/Д' ?></td>
                        </tr>
                        <tr>
                            <th>Статус:</th>
                            <td><?= htmlspecialchars($operation['status'] ?? 'Н/Д') ?></td>
                        </tr>
                        <tr>
                            <th>Проведена:</th>
                            <td><?= isset($operation['conducted']) && $operation['conducted'] ? 'Да' : 'Нет' ?></td>
                        </tr>
                        <tr>
                            <th>Автор:</th>
                            <td><?= htmlspecialchars($user_name) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title">Данные производства</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <tr>
                            <th>Рецепт:</th>
                            <td><?= htmlspecialchars($recipe_name) ?></td>
                        </tr>
                        <tr>
                            <th>Продукт:</th>
                            <td><?= htmlspecialchars($product_name) ?></td>
                        </tr>
                        <tr>
                            <th>Количество:</th>
                            <td><?= isset($operation['quantity']) ? number_format($operation['quantity'], 2) : '0.00' ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($operation['notes'])): ?>
    <div class="row">
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title">Примечания</h5>
                </div>
                <div class="card-body">
                    <p><?= nl2br(htmlspecialchars($operation['notes'])) ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    // Возврат к списку операций
    $('#backToOperationsList').click(function() {
        loadContent('modules/production/operations/list_partial.php');
    });
    
    // Редактирование операции
    $('.edit-operation').click(function() {
        var id = $(this).data('id');
        loadContent('modules/production/operations/edit_partial.php?id=' + id);
    });
    
    // Проведение операции
    $('.conduct-operation').click(function() {
        var id = $(this).data('id');
        
        if (confirm('Вы уверены, что хотите провести эту операцию производства?')) {
            $.ajax({
                url: 'modules/production/operations/conduct.php',
                type: 'POST',
                data: JSON.stringify({ id: id }),
                contentType: 'application/json',
                success: function(response) {
                    var data = JSON.parse(response);
                    if (data.success) {
                        showAlert('success', 'Операция успешно проведена');
                        loadContent('modules/production/operations/view_partial.php?id=' + id);
                    } else {
                        showAlert('danger', 'Ошибка: ' + data.error);
                    }
                },
                error: function() {
                    showAlert('danger', 'Произошла ошибка при выполнении запроса');
                }
            });
        }
    });
    
    // Отмена операции
    $('.cancel-operation').click(function() {
        var id = $(this).data('id');
        
        if (confirm('Вы уверены, что хотите отменить эту операцию производства? Это вернет ингредиенты и спишет произведенный товар.')) {
            $.ajax({
                url: 'modules/production/operations/cancel.php',
                type: 'POST',
                data: JSON.stringify({ id: id }),
                contentType: 'application/json',
                success: function(response) {
                    var data = JSON.parse(response);
                    if (data.success) {
                        showAlert('success', 'Операция успешно отменена');
                        loadContent('modules/production/operations/view_partial.php?id=' + id);
                    } else {
                        showAlert('danger', 'Ошибка: ' + data.error);
                    }
                },
                error: function() {
                    showAlert('danger', 'Произошла ошибка при выполнении запроса');
                }
            });
        }
    });
    
    // Удаление операции
    $('.delete-operation').click(function() {
        var id = $(this).data('id');
        
        if (confirm('Вы уверены, что хотите удалить эту операцию производства?')) {
            $.ajax({
                url: 'modules/production/operations/delete.php',
                type: 'POST',
                data: JSON.stringify({ id: id }),
                contentType: 'application/json',
                success: function(response) {
                    var data = JSON.parse(response);
                    if (data.success) {
                        showAlert('success', 'Операция успешно удалена');
                        loadContent('modules/production/operations/list_partial.php');
                    } else {
                        showAlert('danger', 'Ошибка: ' + data.error);
                    }
                },
                error: function() {
                    showAlert('danger', 'Произошла ошибка при выполнении запроса');
                }
            });
        }
    });
});
</script> 
</script> 