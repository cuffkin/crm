<?php
// /crm/modules/stock/list_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'stock')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

// Фильтры по складу и товару
$wh = $_GET['wh'] ?? '';
$pd = $_GET['prod'] ?? '';

$sql = "SELECT s.*, w.name AS wh_name, p.name AS prod_name
        FROM PCRM_Stock s
        JOIN PCRM_Warehouse w ON s.warehouse = w.id
        JOIN PCRM_Product p ON s.prod_id = p.id
        WHERE 1=1";

$params = [];
$types = '';
if ($wh != '') {
    $sql .= " AND s.warehouse=? ";
    $params[] = $wh;
    $types .= 'i';
}
if ($pd != '') {
    $sql .= " AND s.prod_id=? ";
    $params[] = $pd;
    $types .= 'i';
}

$sql .= " ORDER BY s.id DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$stocks = $res->fetch_all(MYSQLI_ASSOC);

// Список складов
$whRes = $conn->query("SELECT id, name FROM PCRM_Warehouse WHERE status='active'");
$whList = $whRes->fetch_all(MYSQLI_ASSOC);
// Список товаров
$pdRes = $conn->query("SELECT id, name FROM PCRM_Product WHERE status='active'");
$pdList = $pdRes->fetch_all(MYSQLI_ASSOC);
?>
<h4>Остатки</h4>
<!-- Фильтр -->
<form class="row g-3 mb-3" onsubmit="return false;">
    <div class="col-auto">
        <select id="fwh" class="form-select">
            <option value="">Все склады</option>
            <?php foreach ($whList as $ww): ?>
                <option value="<?= $ww['id'] ?>" <?= ($wh == $ww['id'] ? 'selected' : '') ?>>
                    <?= htmlspecialchars($ww['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <select id="fprod" class="form-select">
            <option value="">Все товары</option>
            <?php foreach ($pdList as $pp): ?>
                <option value="<?= $pp['id'] ?>" <?= ($pd == $pp['id'] ? 'selected' : '') ?>>
                    <?= htmlspecialchars($pp['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button class="btn btn-secondary" onclick="applyStockFilter()">Фильтр</button>
    </div>
</form>

<!-- Кнопка для добавления нового остатка -->
<button class="btn btn-primary btn-sm mb-2" onclick="editStock(0)">Добавить остаток</button>

<table class="table table-bordered">
    <thead>
        <tr>
            <th>ID</th>
            <th>Склад</th>
            <th>Товар</th>
            <th>Количество</th>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($stocks as $s): ?>
            <tr>
                <td><?= $s['id'] ?></td>
                <td><?= htmlspecialchars($s['wh_name']) ?></td>
                <td><?= htmlspecialchars($s['prod_name']) ?></td>
                <td><?= $s['quantity'] ?></td>
                <td>
                    <button class="btn btn-warning btn-sm" onclick="editStock(<?= $s['id'] ?>)">Редакт</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteStock(<?= $s['id'] ?>)">Удал</button>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div id="stock-edit-area"></div>

<script>
function applyStockFilter() {
    let wh = $('#fwh').val();
    let pd = $('#fprod').val();
    $.get('/crm/modules/stock/list_partial.php', { wh: wh, prod: pd }, function(htm) {
        $('#crm-tab-content .tab-pane.active').html(htm);
    });
}

function editStock(stockId) {
    $.ajax({
        url: '/crm/modules/stock/edit_partial.php',
        data: { id: stockId },
        success: function(html) {
            $('#stock-edit-area').html(html);
        }
    });
}

function deleteStock(stockId) {
    if (!confirm('Точно удалить остаток?')) return;
    $.get('/crm/modules/stock/delete.php', { id: stockId }, function(resp) {
        if (resp === 'OK') {
            $.get('/crm/modules/stock/list_partial.php', function(h) {
                $('#crm-tab-content .tab-pane.active').html(h);
            });
        } else {
            alert(resp);
        }
    });
}
</script>