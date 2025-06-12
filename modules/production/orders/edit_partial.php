<?php
// /crm/modules/production/orders/edit_partial.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'production')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

$id = (int)($_GET['id'] ?? 0);

// Инициализация переменных
$order_number = '';
$recipe_id = null;
$planned_date = date('Y-m-d H:i');
$status = 'new';
$warehouse_id = null;
$quantity = '1.000';
$comment = '';

// Если редактирование - загрузим данные заказа
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM PCRM_ProductionOrder WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    
    if ($order) {
        $order_number = $order['order_number'];
        $recipe_id = $order['recipe_id'];
        $planned_date = date('Y-m-d H:i', strtotime($order['planned_date']));
        $status = $order['status'];
        $warehouse_id = $order['warehouse_id'];
        $quantity = $order['quantity'];
        $comment = $order['comment'];
    }
}

// Получаем список активных рецептов
$recipes = $conn->query("
    SELECT r.*, p.name as product_name, p.unit_of_measure 
                    FROM PCRM_ProductionRecipe r
        JOIN PCRM_Product p ON r.product_id = p.id 
        WHERE r.status='active' AND r.deleted = 0 
    ORDER BY r.name
");

// Получаем список активных складов
$warehouses = $conn->query("SELECT id, name FROM PCRM_Warehouse WHERE status='active' ORDER BY name");

// Генерируем номер для нового заказа
if ($id == 0) {
    $nextNumber = $conn->query("SELECT MAX(CAST(SUBSTRING(order_number, 4) AS UNSIGNED)) as max_num FROM PCRM_ProductionOrder");
    $nextNumberRow = $nextNumber->fetch_assoc();
    $num = ($nextNumberRow['max_num'] ?? 0) + 1;
    $order_number = 'PO-' . str_pad($num, 6, '0', STR_PAD_LEFT);
}
?>

<div class="card">
  <div class="card-header">
    <?= $id > 0 ? 'Редактирование заказа на производство' : 'Новый заказ на производство' ?>
  </div>
  <div class="card-body">
    <form id="order-form">
      <input type="hidden" id="order-id" value="<?= $id ?>">
      
      <div class="row mb-3">
        <div class="col-md-4">
          <label>Номер заказа</label>
          <input type="text" id="order-number" class="form-control" value="<?= htmlspecialchars($order_number) ?>" required>
        </div>
        <div class="col-md-4">
          <label>Статус</label>
          <select id="order-status" class="form-select">
            <option value="new" <?= $status == 'new' ? 'selected' : '' ?>>Новый</option>
            <option value="in_progress" <?= $status == 'in_progress' ? 'selected' : '' ?>>В процессе</option>
            <option value="completed" <?= $status == 'completed' ? 'selected' : '' ?>>Завершен</option>
            <option value="cancelled" <?= $status == 'cancelled' ? 'selected' : '' ?>>Отменен</option>
          </select>
        </div>
        <div class="col-md-4">
          <label>Планируемая дата производства</label>
          <input type="datetime-local" id="order-date" class="form-control" value="<?= $planned_date ?>" required>
        </div>
      </div>
      
      <div class="row mb-3">
        <div class="col-md-6">
          <label>Рецепт производства</label>
          <select id="order-recipe" class="form-select" required>
            <option value="">Выберите рецепт</option>
            <?php while ($r = $recipes->fetch_assoc()): ?>
              <option value="<?= $r['id'] ?>" 
                      data-product-id="<?= $r['product_id'] ?>" 
                      data-product-name="<?= htmlspecialchars($r['product_name']) ?>"
                      data-unit="<?= htmlspecialchars($r['unit_of_measure']) ?>"
                      data-output="<?= $r['output_quantity'] ?>"
                      <?= $recipe_id == $r['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['name']) ?> (<?= htmlspecialchars($r['product_name']) ?>)
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label>Склад</label>
          <select id="order-warehouse" class="form-select" required>
            <option value="">Выберите склад</option>
            <?php while ($w = $warehouses->fetch_assoc()): ?>
              <option value="<?= $w['id'] ?>" <?= $warehouse_id == $w['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($w['name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
      </div>
      
      <div class="row mb-3">
        <div class="col-md-6">
          <label>Количество для производства</label>
          <div class="input-group">
            <input type="number" id="order-quantity" class="form-control" min="0.001" step="0.001" 
                   value="<?= $quantity ?>" required>
            <span class="input-group-text" id="output-unit">шт</span>
          </div>
        </div>
        <div class="col-md-6">
          <label>Итого будет произведено</label>
          <div class="input-group">
            <input type="text" id="total-output" class="form-control" readonly>
            <span class="input-group-text" id="total-unit">шт</span>
          </div>
        </div>
      </div>
      
      <div class="mb-3">
        <label>Комментарий</label>
        <textarea id="order-comment" class="form-control" rows="2"><?= htmlspecialchars($comment) ?></textarea>
      </div>
      
      <h5 class="mt-4">Ингредиенты по рецепту</h5>
      <div id="ingredients-container">
        <div class="alert alert-info">Выберите рецепт, чтобы увидеть необходимые ингредиенты</div>
      </div>
      
      <div class="mt-3">
        <button type="button" id="save-order" class="btn btn-success">Сохранить</button>
        <button type="button" class="btn btn-secondary" onclick="$('#order-edit-area').html('')">Отмена</button>
      </div>
    </form>
  </div>
</div>

<script>
// Загрузка ингредиентов при выборе рецепта
$('#order-recipe').change(function() {
  updateRecipeInfo();
  
  const recipeId = $(this).val();
  if (!recipeId) {
    $('#ingredients-container').html('<div class="alert alert-info">Выберите рецепт, чтобы увидеть необходимые ингредиенты</div>');
    return;
  }
  
  $.ajax({
    url: '/crm/modules/production/orders/get_ingredients.php',
    data: { 
      recipe_id: recipeId,
      quantity: $('#order-quantity').val(),
      warehouse_id: $('#order-warehouse').val() 
    },
    success: function(html) {
      $('#ingredients-container').html(html);
    }
  });
});

// Обновление ингредиентов при изменении количества
$('#order-quantity').change(function() {
  updateRecipeInfo();
  
  const recipeId = $('#order-recipe').val();
  if (!recipeId) return;
  
  $.ajax({
    url: '/crm/modules/production/orders/get_ingredients.php',
    data: { 
      recipe_id: recipeId,
      quantity: $(this).val(),
      warehouse_id: $('#order-warehouse').val() 
    },
    success: function(html) {
      $('#ingredients-container').html(html);
    }
  });
});

// Обновление ингредиентов при изменении склада
$('#order-warehouse').change(function() {
  const recipeId = $('#order-recipe').val();
  if (!recipeId) return;
  
  $.ajax({
    url: '/crm/modules/production/orders/get_ingredients.php',
    data: { 
      recipe_id: recipeId,
      quantity: $('#order-quantity').val(),
      warehouse_id: $(this).val() 
    },
    success: function(html) {
      $('#ingredients-container').html(html);
    }
  });
});

// Обновление информации о выходе продукции
function updateRecipeInfo() {
  const selectedOption = $('#order-recipe option:selected');
  const unit = selectedOption.data('unit') || 'шт';
  const outputQuantity = selectedOption.data('output') || 1;
  const orderQuantity = parseFloat($('#order-quantity').val()) || 0;
  
  $('#output-unit').text(unit);
  $('#total-unit').text(unit);
  
  // Рассчитываем общее количество произведенного продукта
  const totalOutput = (outputQuantity * orderQuantity).toFixed(3);
  $('#total-output').val(totalOutput + ' ' + unit);
}

// Инициализация при загрузке
$(document).ready(function() {
  updateRecipeInfo();
  
  const recipeId = $('#order-recipe').val();
  if (recipeId) {
    $.ajax({
      url: '/crm/modules/production/orders/get_ingredients.php',
      data: { 
        recipe_id: recipeId,
        quantity: $('#order-quantity').val(),
        warehouse_id: $('#order-warehouse').val() 
      },
      success: function(html) {
        $('#ingredients-container').html(html);
      }
    });
  }
});

// Сохранение заказа
$('#save-order').click(function() {
  const form = document.getElementById('order-form');
  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }
  
  // Проверка доступности ингредиентов
  const ingredientsAvailable = $('.ingredient-stock').toArray().every(function(el) {
    return !$(el).hasClass('text-danger');
  });
  
  if (!ingredientsAvailable) {
    if (!confirm('Не все ингредиенты доступны на складе. Продолжить сохранение?')) {
      return;
    }
  }
  
  // Сбор данных о заказе
  const orderData = {
    id: $('#order-id').val(),
    order_number: $('#order-number').val(),
    recipe_id: $('#order-recipe').val(),
    planned_date: $('#order-date').val(),
    status: $('#order-status').val(),
    warehouse_id: $('#order-warehouse').val(),
    quantity: $('#order-quantity').val(),
    comment: $('#order-comment').val()
  };
  
  // Отправка данных на сервер
  $.ajax({
    url: '/crm/modules/production/orders/save.php',
    method: 'POST',
    data: orderData,
    success: function(response) {
      if (response === 'OK') {
        // Закрываем форму и обновляем список
        $('#order-edit-area').html('');
        $.get('/crm/modules/production/orders/list_partial.php', function(html) {
          $('#crm-tab-content .tab-pane.active').html(html);
        });
      } else {
        alert(response);
      }
    },
    error: function() {
      alert('Произошла ошибка при сохранении заказа');
    }
  });
});
</script> 