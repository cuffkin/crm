<?php
// /crm/modules/trash/view_details.php - Просмотр деталей удаленного элемента
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/TrashManager.php';

// Проверка доступа - ВРЕМЕННО: доступ для всех авторизованных пользователей
if (!isset($_SESSION['user_id'])) {
    die('<div class="alert alert-danger">Необходима авторизация</div>');
}

$trashId = (int)($_GET['id'] ?? 0);

if ($trashId <= 0) {
    die('<div class="alert alert-danger">Некорректный ID элемента корзины</div>');
}

// Получаем данные элемента корзины
$query = "SELECT 
    t.*, 
    u.username as deleted_by_username,
    DATEDIFF(t.auto_delete_at, NOW()) as days_until_auto_delete
FROM PCRM_TrashItems t 
LEFT JOIN PCRM_User u ON t.deleted_by = u.id 
WHERE t.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $trashId);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();

if (!$item) {
    die('<div class="alert alert-danger">Элемент корзины не найден</div>');
}

// Декодируем JSON данные
$documentData = json_decode($item['document_data'], true);
$relatedData = json_decode($item['related_data'], true);

// Названия полей для красивого отображения
$fieldNames = [
    'id' => 'ID',
    'order_number' => 'Номер заказа',
    'purchase_order_number' => 'Номер заказа поставщику',
    'shipment_number' => 'Номер отгрузки',
    'receipt_number' => 'Номер приемки',
    'return_number' => 'Номер возврата',
    'operation_number' => 'Номер операции',
    'name' => 'Название',
    'description' => 'Описание',
    'amount' => 'Сумма',
    'total_amount' => 'Общая сумма',
    'created_at' => 'Создан',
    'updated_at' => 'Обновлен',
    'status' => 'Статус',
    'type' => 'Тип',
    'counterparty_id' => 'Контрагент ID',
    'warehouse_id' => 'Склад ID',
    'user_id' => 'Пользователь ID',
    'date' => 'Дата',
    'created_by' => 'Создал',
    'phone' => 'Телефон',
    'email' => 'Email',
    'address' => 'Адрес',
    'inn' => 'ИНН',
    'category_id' => 'Категория ID',
    'price' => 'Цена',
    'quantity' => 'Количество',
    'unit' => 'Единица измерения'
];
?>

<div class="row">
    <!-- Основная информация -->
    <div class="col-md-6">
        <h6 class="text-primary">📋 Основная информация</h6>
        <div class="card mb-3">
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td><strong>Тип:</strong></td>
                        <td><?= $item['item_type'] === 'document' ? '📄 Документ' : '📚 Справочник' ?></td>
                    </tr>
                    <tr>
                        <td><strong>Категория:</strong></td>
                        <td><?= htmlspecialchars($item['document_type']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Название:</strong></td>
                        <td><?= htmlspecialchars($item['original_name']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Удален:</strong></td>
                        <td><?= date('d.m.Y H:i:s', strtotime($item['deleted_at'])) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Кем удален:</strong></td>
                        <td><?= htmlspecialchars($item['deleted_by_username'] ?? 'Неизвестен') ?></td>
                    </tr>
                    <?php if ($item['reason']): ?>
                    <tr>
                        <td><strong>Причина:</strong></td>
                        <td><?= htmlspecialchars($item['reason']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong>Автоудаление:</strong></td>
                        <td>
                            <?php if ($item['days_until_auto_delete'] > 0): ?>
                                <span class="<?= $item['days_until_auto_delete'] <= 7 ? 'text-warning fw-bold' : 'text-success' ?>">
                                    через <?= $item['days_until_auto_delete'] ?> дн.
                                </span>
                            <?php else: ?>
                                <span class="text-danger fw-bold">Просрочен</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Можно восстановить:</strong></td>
                        <td>
                            <?= $item['can_restore'] ? '<span class="text-success">✅ Да</span>' : '<span class="text-danger">❌ Нет</span>' ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Сохраненные данные -->
    <div class="col-md-6">
        <h6 class="text-info">💾 Сохраненные данные</h6>
        <div class="card mb-3">
            <div class="card-body">
                <?php if ($documentData): ?>
                    <table class="table table-sm">
                        <?php foreach ($documentData as $key => $value): ?>
                            <?php if ($key !== 'deleted' && $value !== null): ?>
                            <tr>
                                <td width="40%"><strong><?= $fieldNames[$key] ?? $key ?>:</strong></td>
                                <td>
                                    <?php if (in_array($key, ['created_at', 'updated_at', 'date']) && $value): ?>
                                        <?= date('d.m.Y H:i:s', strtotime($value)) ?>
                                    <?php elseif (is_numeric($value) && in_array($key, ['amount', 'total_amount', 'price'])): ?>
                                        <?= number_format($value, 2) ?> ₽
                                    <?php else: ?>
                                        <?= htmlspecialchars($value) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <div class="text-muted">Нет сохраненных данных</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Связанные данные -->
<?php if ($relatedData && !empty(array_filter($relatedData))): ?>
<div class="row">
    <div class="col-12">
        <h6 class="text-warning">🔗 Связанные данные</h6>
        <div class="card">
            <div class="card-body">
                <?php foreach ($relatedData as $tableName => $tableData): ?>
                    <?php if (!empty($tableData)): ?>
                        <h6 class="text-secondary"><?= htmlspecialchars($tableName) ?></h6>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <?php if (!empty($tableData[0])): ?>
                                            <?php foreach (array_keys($tableData[0]) as $column): ?>
                                                <th><?= $fieldNames[$column] ?? $column ?></th>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tableData as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $key => $value): ?>
                                                <td>
                                                    <?php if (in_array($key, ['created_at', 'updated_at', 'date']) && $value): ?>
                                                        <?= date('d.m.Y H:i', strtotime($value)) ?>
                                                    <?php elseif (is_numeric($value) && in_array($key, ['amount', 'price', 'total'])): ?>
                                                        <?= number_format($value, 2) ?>
                                                    <?php else: ?>
                                                        <?= htmlspecialchars($value) ?>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- JSON данные для отладки (скрытые по умолчанию) -->
<div class="row mt-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="text-muted">🔧 Сырые данные (JSON)</h6>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleRawData()">
                Показать/скрыть
            </button>
        </div>
        <div id="rawDataContainer" style="display: none;">
            <div class="card mt-2">
                <div class="card-body">
                    <h6>Основные данные:</h6>
                    <pre class="bg-light p-2 small"><?= htmlspecialchars(json_encode($documentData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                    
                    <?php if ($relatedData): ?>
                    <h6>Связанные данные:</h6>
                    <pre class="bg-light p-2 small"><?= htmlspecialchars(json_encode($relatedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleRawData() {
    const container = document.getElementById('rawDataContainer');
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
}
</script> 