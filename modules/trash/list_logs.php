<?php
// /crm/modules/trash/list_logs.php - Журнал операций с корзиной
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// УБИРАЕМ ВСЕ ПРОВЕРКИ АВТОРИЗАЦИИ - пусть работает для всех

$limit = 100;
$offset = 0;

// Получаем логи операций
$query = "SELECT 
    l.*, 
    u.username as user_name,
    ti.original_name as item_name
FROM PCRM_TrashLog l 
LEFT JOIN PCRM_User u ON l.user_id = u.id 
LEFT JOIN PCRM_TrashItems ti ON l.trash_item_id = ti.id
ORDER BY l.created_at DESC 
LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

// Названия действий
$actionNames = [
    'moved_to_trash' => ['название' => 'Перемещен в корзину', 'class' => 'warning', 'icon' => '🗑️'],
    'restored' => ['название' => 'Восстановлен', 'class' => 'success', 'icon' => '♻️'],
    'permanently_deleted' => ['название' => 'Удален навсегда', 'class' => 'danger', 'icon' => '💥'],
    'auto_deleted' => ['название' => 'Автоочистка', 'class' => 'secondary', 'icon' => '⏰']
];

if (empty($logs)) {
    echo '<div class="text-center p-4">
        <div class="text-muted">
            <i class="fas fa-clipboard-list fa-3x mb-3"></i>
            <h5>Журнал операций пуст</h5>
            <p>Здесь будут отображаться все действия с корзиной</p>
        </div>
    </div>';
    return;
}
?>

<div class="table-responsive">
    <table class="table table-hover">
        <thead class="table-light">
            <tr>
                <th width="60">Действие</th>
                <th>Элемент</th>
                <th>Тип</th>
                <th>Пользователь</th>
                <th>Дата/время</th>
                <th>Детали</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
                <?php 
                $action = $actionNames[$log['action']] ?? ['название' => $log['action'], 'class' => 'secondary', 'icon' => '❓'];
                ?>
                <tr>
                    <td class="text-center">
                        <span class="badge bg-<?= $action['class'] ?>" title="<?= $action['название'] ?>">
                            <?= $action['icon'] ?>
                        </span>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($log['item_name'] ?? "ID: {$log['document_id']}") ?></strong>
                        <br><small class="text-muted"><?= htmlspecialchars($log['document_type']) ?></small>
                    </td>
                    <td>
                        <span class="badge bg-info"><?= htmlspecialchars($log['document_type']) ?></span>
                    </td>
                    <td>
                        <small><?= htmlspecialchars($log['user_name'] ?? 'Система') ?></small>
                    </td>
                    <td>
                        <small class="text-muted">
                            <?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?>
                        </small>
                    </td>
                    <td>
                        <?php if ($log['details']): ?>
                            <?php 
                            $details = json_decode($log['details'], true);
                            if ($details): ?>
                                <button type="button" class="btn btn-sm btn-outline-info" 
                                        onclick="showLogDetails(<?= htmlspecialchars(json_encode($details)) ?>)">
                                    Детали
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <small class="text-muted">—</small>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Модалка деталей лога -->
<div class="modal fade" id="logDetailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Детали операции</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="logDetailsContent">
                <!-- Содержимое загружается динамически -->
            </div>
        </div>
    </div>
</div>

<script>
function showLogDetails(details) {
    const modal = new bootstrap.Modal(document.getElementById('logDetailsModal'));
    const content = document.getElementById('logDetailsContent');
    
    let html = '<div class="row">';
    for (const [key, value] of Object.entries(details)) {
        html += `
            <div class="col-12 mb-2">
                <strong>${key}:</strong> ${typeof value === 'object' ? JSON.stringify(value, null, 2) : value}
            </div>
        `;
    }
    html += '</div>';
    
    content.innerHTML = html;
    modal.show();
}
</script> 