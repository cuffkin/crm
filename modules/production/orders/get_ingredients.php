<?php
// /crm/modules/production/orders/get_ingredients.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'production')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

$recipe_id = (int)($_GET['recipe_id'] ?? 0);
$quantity = (float)($_GET['quantity'] ?? 1);
$warehouse_id = (int)($_GET['warehouse_id'] ?? 0);

if ($recipe_id <= 0) {
    die("<div class='alert alert-danger'>Не указан ID рецепта</div>");
}

// Получаем информацию о рецепте
$recipeStmt = $conn->prepare("
    SELECT r.*, p.name as product_name, p.unit_of_measure
    FROM PCRM_ProductionRecipe r
    JOIN PCRM_Product p ON r.product_id = p.id
    WHERE r.id = ?
");
$recipeStmt->bind_param("i", $recipe_id);
$recipeStmt->execute();
$recipeResult = $recipeStmt->get_result();
$recipe = $recipeResult->fetch_assoc();

if (!$recipe) {
    die("<div class='alert alert-danger'>Рецепт не найден</div>");
}

// Получаем ингредиенты рецепта
$itemsStmt = $conn->prepare("
    SELECT ri.*, p.name as ingredient_name, p.unit_of_measure
    FROM PCRM_ProductionRecipeItem ri
    JOIN PCRM_Product p ON ri.ingredient_id = p.id
    WHERE ri.recipe_id = ?
");
$itemsStmt->bind_param("i", $recipe_id);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();
$ingredients = $itemsResult->fetch_all(MYSQLI_ASSOC);

// Если указан склад, проверяем наличие ингредиентов
$stockInfo = [];
if ($warehouse_id > 0) {
    $stockStmt = $conn->prepare("
        SELECT prod_id, quantity 
        FROM PCRM_Stock 
        WHERE warehouse = ? AND prod_id IN (
            SELECT ingredient_id FROM PCRM_ProductionRecipeItem WHERE recipe_id = ?
        )
    ");
    $stockStmt->bind_param("ii", $warehouse_id, $recipe_id);
    $stockStmt->execute();
    $stockResult = $stockStmt->get_result();
    
    while ($stock = $stockResult->fetch_assoc()) {
        $stockInfo[$stock['prod_id']] = $stock['quantity'];
    }
}

// Подготавливаем данные для отображения
$canProduce = true;
?>

<table class="table table-bordered">
  <thead>
    <tr>
      <th>Ингредиент</th>
      <th>Требуется (на единицу)</th>
      <th>Требуется (всего)</th>
      <th>Ед. изм.</th>
      <?php if ($warehouse_id > 0): ?>
      <th>Доступно на складе</th>
      <th>Статус</th>
      <?php endif; ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($ingredients as $item): 
      $requiredQuantity = $item['quantity'] * $quantity;
      $stockQuantity = $stockInfo[$item['ingredient_id']] ?? 0;
      $isAvailable = $stockQuantity >= $requiredQuantity;
      if (!$isAvailable) $canProduce = false;
    ?>
    <tr>
      <td><?= htmlspecialchars($item['ingredient_name']) ?></td>
      <td><?= $item['quantity'] ?></td>
      <td><?= $requiredQuantity ?></td>
      <td><?= htmlspecialchars($item['unit_of_measure']) ?></td>
      <?php if ($warehouse_id > 0): ?>
      <td class="ingredient-stock <?= $isAvailable ? 'text-success' : 'text-danger' ?>"><?= $stockQuantity ?></td>
      <td>
        <?php if ($isAvailable): ?>
          <span class="badge bg-success">Доступно</span>
        <?php else: ?>
          <span class="badge bg-danger">Не хватает <?= $requiredQuantity - $stockQuantity ?></span>
        <?php endif; ?>
      </td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div class="row mb-3">
  <div class="col-md-12">
    <div class="alert <?= $canProduce ? 'alert-success' : 'alert-warning' ?>">
      <?php if ($canProduce && $warehouse_id > 0): ?>
        <i class="fas fa-check-circle"></i> Все ингредиенты в наличии на складе.
      <?php elseif (!$canProduce && $warehouse_id > 0): ?>
        <i class="fas fa-exclamation-triangle"></i> Не хватает некоторых ингредиентов на складе.
      <?php else: ?>
        <i class="fas fa-info-circle"></i> Выберите склад, чтобы проверить наличие ингредиентов.
      <?php endif; ?>
    </div>
  </div>
</div> 