<?php
session_start();
include_once "../../../config/db.php";

$userId = (int)$_GET['id'];
$user = $conn->query("SELECT username FROM PCRM_User WHERE id = $userId")->fetch_assoc();

$modules = [
    'sales/orders/list', 'sales/shipments/list', 'sales/returns/list',
    'purchases/orders/list', 'purchases/receipts/list', 'purchases/returns/list',
    'corrections/inventory/list', 'corrections/appropriations/list', 'corrections/writeoff/list',
    'users/list', 'categories/list', 'products/list', 'warehouse/list', 'stock/list', 'access/list'
];

$sql = "SELECT module_name, can_access FROM PCRM_AccessRules WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$access = [];
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $access[$row['module_name']] = $row['can_access'];
}
?>
<h3>Доступ для <?php echo $user['username']; ?></h3>
<form id="accessForm" action="/crm/modules/access/save.php" method="POST">
    <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
    <?php foreach ($modules as $module): ?>
        <div class="form-check">
            <input type="checkbox" name="modules[<?php echo $module; ?>]" value="1" 
                   class="form-check-input" <?php echo isset($access[$module]) && $access[$module] ? 'checked' : ''; ?>>
            <label class="form-check-label"><?php echo getModuleTitle($module); ?></label>
        </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-primary mt-3">Сохранить</button>
</form>

<script>
    $('#accessForm').on('submit', function(e) {
        e.preventDefault();
        $.post($(this).attr('action'), $(this).serialize(), function(response) {
            if (response.success) {
                alert('Доступы сохранены');
            } else {
                alert('Ошибка: ' + response.error);
            }
        }, 'json');
    });
</script>

<?php
function getModuleTitle($path) {
    switch ($path) {
        case 'sales/orders/list': return 'Заказы покупателей';
        case 'sales/shipments/list': return 'Отгрузки';
        case 'sales/returns/list': return 'Возврат покупателя';
        case 'purchases/orders/list': return 'Заказ поставщику';
        case 'purchases/receipts/list': return 'Приёмки';
        case 'purchases/returns/list': return 'Возврат поставщику';
        case 'corrections/inventory/list': return 'Инвентаризация';
        case 'corrections/appropriations/list': return 'Оприходование';
        case 'corrections/writeoff/list': return 'Списание';
        case 'users/list': return 'Пользователи';
        case 'categories/list': return 'Категории';
        case 'products/list': return 'Товары';
        case 'warehouse/list': return 'Склады';
        case 'stock/list': return 'Остатки';
        case 'access/list': return 'Управление доступом';
        default: return $path;
    }
}