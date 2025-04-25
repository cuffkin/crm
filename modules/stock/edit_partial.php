<?php
// /crm/modules/stock/edit_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'stock')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

$id = (int)($_GET['id'] ?? 0);

$warehouse = null;
$prod_id = null;
$quantity = '0.00';

if ($id > 0) {
    $st = $conn->prepare("SELECT * FROM PCRM_Stock WHERE id=?");
    $st->bind_param("i", $id);
    $st->execute();
    $res = $st->get_result();
    $stock = $res->fetch_assoc();
    if ($stock) {
        $warehouse = $stock['warehouse'];
        $prod_id = $stock['prod_id'];
        $quantity = $stock['quantity'];
    } else {
        die("<div class='text-danger'>Остаток не найден</div>");
    }
}

// Список складов
$whRes = $conn->query("SELECT id, name FROM PCRM_Warehouse WHERE status='active'");
$whList = $whRes->fetch_all(MYSQLI_ASSOC);
// Список товаров
$pdRes = $conn->query("SELECT id, name FROM PCRM_Product WHERE status='active'");
$pdList = $pdRes->fetch_all(MYSQLI_ASSOC);
?>
<div class="card">
    <div class="card-header">
        <?= $id > 0 ? 'Редактирование остатка' : 'Новый остаток' ?>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <label>Склад</label>
            <select id="s-warehouse" class="form-select">
                <option value="">(не выбран)</option>
                <?php foreach ($whList as $ww): ?>
                    <option value="<?= $ww['id'] ?>" <?= ($warehouse == $ww['id'] ? 'selected' : '') ?>>
                        <?= htmlspecialchars($ww['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label>Товар</label>
            <select id="s-product" class="form-select">
                <option value="">(не выбран)</option>
                <?php foreach ($pdList as $pp): ?>
                    <option value="<?= $pp['id'] ?>" <?= ($prod_id == $pp['id'] ? 'selected' : '') ?>>
                        <?= htmlspecialchars($pp['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label>Количество</label>
            <input type="number" step="0.01" id="s-quantity" class="form-control" value="<?= $quantity ?>">
        </div>
        <button class="btn btn-success" onclick="saveStock(<?= $id ?>)">Сохранить</button>
        <button class="btn btn-secondary" onclick="$('#stock-edit-area').html('')">Отмена</button>
    </div>
</div>

<script>
function saveStock(sid) {
    let data = {
        id: sid,
        warehouse: $('#s-warehouse').val(),
        prod_id: $('#s-product').val(),
        quantity: $('#s-quantity').val()
    };
    $.post('/crm/modules/stock/save.php', data, function(resp) {
        if (resp === 'OK') {
            $('#stock-edit-area').html('');
            $.get('/crm/modules/stock/list_partial.php', function(h) {
                $('#crm-tab-content .tab-pane.active').html(h);
            });
        } else {
            alert(resp);
        }
    });
}
</script>