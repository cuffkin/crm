<?php
// /crm/modules/production/operations/list_partial.php
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
$required_tables = ['PCRM_ProductionOperation', 'PCRM_ProductionRecipe', 'PCRM_Product', 'PCRM_User'];
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
    $operations = [];
} else {
    try {
        // Получение данных основной таблицы
        $sql = "
        SELECT o.*
        FROM PCRM_ProductionOperation o
        ORDER BY o.id DESC
        ";
        
        $res = $conn->query($sql);
        if (!$res) {
            throw new Exception("Ошибка выполнения запроса: " . $conn->error);
        }
        
        $operations = [];
        while ($row = $res->fetch_assoc()) {
            // Загружаем данные о рецепте и продукте
            $recipe_name = "Н/Д";
            $product_name = "Н/Д";

            if (isset($row['recipe_id']) && $row['recipe_id'] > 0) {
                $recipe_stmt = $conn->prepare("
                    SELECT r.name, p.name as product_name
                    FROM PCRM_ProductionRecipe r
                    LEFT JOIN PCRM_Product p ON r.product_id = p.id
                    WHERE r.id = ?
                ");
                
                if ($recipe_stmt) {
                    $recipe_stmt->bind_param('i', $row['recipe_id']);
                    $recipe_stmt->execute();
                    $recipe_result = $recipe_stmt->get_result();
                    if ($recipe_result && $recipe_row = $recipe_result->fetch_assoc()) {
                        $recipe_name = $recipe_row['name'];
                        $product_name = $recipe_row['product_name'];
                    }
                }
            }
            
            // Загружаем информацию о пользователе
            $user_name = "Н/Д";
            if (isset($row['user_id']) && $row['user_id'] > 0) {
                $user_stmt = $conn->prepare("SELECT name FROM PCRM_User WHERE id = ?");
                if ($user_stmt) {
                    $user_stmt->bind_param('i', $row['user_id']);
                    $user_stmt->execute();
                    $user_result = $user_stmt->get_result();
                    if ($user_result && $user_row = $user_result->fetch_assoc()) {
                        $user_name = $user_row['name'];
                    }
                }
            }
            
            $row['recipe_name'] = $recipe_name;
            $row['product_name'] = $product_name;
            $row['user_name'] = $user_name;
            $operations[] = $row;
        }
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Ошибка при загрузке данных: ' . htmlspecialchars($e->getMessage()) . '</div>';
        $operations = [];
    }
}
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="card-title mb-0">Операции производства</h4>
        <div>
            <button type="button" class="btn btn-primary" onclick="openProductionOperationTab(0)">
                <i class="fas fa-plus"></i> Новая операция
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Номер</th>
                        <th>Дата</th>
                        <th>Рецепт</th>
                        <th>Продукт</th>
                        <th>Количество</th>
                        <th>Статус</th>
                        <th>Пользователь</th>
                        <th>Проведена</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($operations) > 0): ?>
                        <?php foreach ($operations as $op): ?>
                            <tr>
                                <td><?= $op['id'] ?></td>
                                <td><?= htmlspecialchars($op['operation_number'] ?? '') ?></td>
                                <td><?= isset($op['production_date']) ? date('d.m.Y', strtotime($op['production_date'])) : 'Н/Д' ?></td>
                                <td><?= htmlspecialchars($op['recipe_name']) ?></td>
                                <td><?= htmlspecialchars($op['product_name']) ?></td>
                                <td><?= isset($op['quantity']) ? number_format($op['quantity'], 2) : '0.00' ?></td>
                                <td><?= htmlspecialchars($op['status'] ?? 'Н/Д') ?></td>
                                <td><?= htmlspecialchars($op['user_name']) ?></td>
                                <td><?= isset($op['conducted']) && $op['conducted'] ? 'Да' : 'Нет' ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-info btn-sm" onclick="openProductionOperationTab(<?= $op['id'] ?>, '<?= htmlspecialchars($op['operation_number'] ?? $op['id']) ?>')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if (!(isset($op['conducted']) && $op['conducted'])): ?>
                                        <button class="btn btn-warning btn-sm" onclick="openProductionOperationTab(<?= $op['id'] ?>, '<?= htmlspecialchars($op['operation_number'] ?? $op['id']) ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteOperation(<?= $op['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if (!(isset($op['conducted']) && $op['conducted'])): ?>
                                        <button class="btn btn-success btn-sm" onclick="conductOperation(<?= $op['id'] ?>, 'conduct')">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <?php else: ?>
                                        <button class="btn btn-secondary btn-sm" onclick="conductOperation(<?= $op['id'] ?>, 'cancel')">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center">Операции производства не найдены</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function deleteOperation(id) {
  // Вызываем глобальную функцию напрямую (она определена в app.js)
  if (typeof moveToTrash === 'function') {
    moveToTrash('production_operation', id, 'Вы уверены, что хотите удалить эту операцию?', function() {
      // Обновляем список операций
      const activeTab = document.querySelector('.tab-pane.active');
      if (activeTab) {
        const moduleTab = document.querySelector('.nav-link.active[data-module*="production/operations"]');
        if (moduleTab) {
          const modulePath = moduleTab.getAttribute('data-module');
          fetch(modulePath)
            .then(response => response.text())
            .then(html => activeTab.innerHTML = html)
            .catch(error => console.error('Error reloading production operations:', error));
        }
      }
    });
  } else {
    console.error('Глобальная функция moveToTrash не найдена');
    alert('Ошибка: функция удаления не найдена');
  }
}

function conductOperation(id, action) {
    var confirmMessage = action === 'conduct' ? 'Провести операцию?' : 'Отменить проведение операции?';
    
    if (!confirm(confirmMessage)) return;
    
    $.ajax({
        url: 'modules/production/operations/api.php',
        type: 'POST',
        data: { 
            id: id, 
            action: action
        },
        success: function(response) {
            try {
                var data = JSON.parse(response);
                if (data.success) {
                    var message = action === 'conduct' ? 'Операция успешно проведена' : 'Проведение операции отменено';
                    showAlert('success', message);
                    loadContent('modules/production/operations/list_partial.php');
                } else {
                    showAlert('danger', 'Ошибка: ' + data.error);
                }
            } catch (e) {
                showAlert('danger', 'Ошибка при обработке ответа: ' + response);
            }
        },
        error: function(xhr, status, error) {
            showAlert('danger', 'Произошла ошибка при выполнении запроса: ' + error);
        }
    });
}

// Обновление списка операций
function updateOperationsList() {
    loadContent('modules/production/operations/list_partial.php');
}

</script> 