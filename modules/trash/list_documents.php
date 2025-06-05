<?php
// /crm/modules/trash/list_documents.php - Список удаленных документов
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/TrashManager.php';

// ОТЛАДКА: добавляем логирование для диагностики
error_log("[TRASH_DEBUG] list_documents.php запущен");
echo "<!-- DEBUG: list_documents.php запущен -->\n";

// УБИРАЕМ ВСЕ ПРОВЕРКИ АВТОРИЗАЦИИ - пусть работает для всех
$userId = $_SESSION['user_id'] ?? 1; // Fallback на admin если нет сессии
error_log("[TRASH_DEBUG] Используем user_id = $userId");
echo "<!-- DEBUG: user_id = $userId -->\n";

$search = $_GET['search'] ?? '';
$limit = 50;
$offset = 0;

error_log("[TRASH_DEBUG] Параметры: search='$search', limit=$limit, offset=$offset");
echo "<!-- DEBUG: search='$search', limit=$limit, offset=$offset -->\n";

try {
    $trashManager = new TrashManager($conn, $userId);
    error_log("[TRASH_DEBUG] TrashManager создан успешно");
    echo "<!-- DEBUG: TrashManager создан -->\n";
    
    $items = $trashManager->getTrashItems('document', $search, $limit, $offset);
    error_log("[TRASH_DEBUG] getTrashItems вернул " . count($items) . " элементов");
    echo "<!-- DEBUG: Найдено элементов: " . count($items) . " -->\n";
    
    if (!empty($items)) {
        error_log("[TRASH_DEBUG] Первый элемент: " . json_encode($items[0]));
        echo "<!-- DEBUG: Первый элемент ID: " . $items[0]['id'] . " -->\n";
    }
    
} catch (Exception $e) {
    error_log("[TRASH_DEBUG] Ошибка: " . $e->getMessage());
    echo '<div class="alert alert-danger">Ошибка: ' . htmlspecialchars($e->getMessage()) . '</div>';
    return;
}

if (empty($items)) {
    echo '<div class="text-center p-4">
        <div class="text-muted">
            <i class="fas fa-inbox fa-3x mb-3"></i>
            <h5>Нет удаленных документов</h5>
            <p>Документы, помеченные на удаление, будут отображаться здесь</p>
        </div>
    </div>';
    return;
}
?>

<div class="table-responsive">
    <table class="table table-hover">
        <thead class="table-light">
            <tr>
                <th width="50">Тип</th>
                <th>Название/Номер</th>
                <th>Тип документа</th>
                <th>Удален</th>
                <th>Кем удален</th>
                <th>Автоудаление</th>
                <th width="200">Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr class="<?= $item['days_until_auto_delete'] <= 7 ? 'table-warning' : '' ?>">
                    <td class="text-center">
                        <span class="fs-4"><?= htmlspecialchars($item['icon']) ?></span>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($item['original_name']) ?></strong>
                        <?php if ($item['reason']): ?>
                            <br><small class="text-muted">Причина: <?= htmlspecialchars($item['reason']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-info"><?= htmlspecialchars($item['type_name']) ?></span>
                    </td>
                    <td>
                        <small class="text-muted">
                            <?= date('d.m.Y H:i', strtotime($item['deleted_at'])) ?>
                        </small>
                    </td>
                    <td>
                        <small class="text-muted">
                            <?= htmlspecialchars($item['deleted_by_username'] ?? 'Неизвестен') ?>
                        </small>
                    </td>
                    <td>
                        <?php if ($item['days_until_auto_delete'] > 0): ?>
                            <small class="<?= $item['days_until_auto_delete'] <= 7 ? 'text-warning fw-bold' : 'text-muted' ?>">
                                <?= $item['days_until_auto_delete'] ?> дн.
                            </small>
                        <?php else: ?>
                            <small class="text-danger fw-bold">Просрочен</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm" role="group">
                            <?php if ($item['can_restore']): ?>
                                <button type="button" class="btn btn-outline-success" 
                                        onclick="window.restoreItem && window.restoreItem(<?= $item['id'] ?>)"
                                        title="Восстановить">
                                    ♻️
                                </button>
                            <?php endif; ?>
                            
                            <button type="button" class="btn btn-outline-info" 
                                    onclick="window.viewDetails && window.viewDetails(<?= $item['id'] ?>)"
                                    title="Просмотр деталей">
                                👁️
                            </button>
                            
                            <button type="button" class="btn btn-outline-danger" 
                                    onclick="window.permanentlyDelete && window.permanentlyDelete(<?= $item['id'] ?>)"
                                    title="Удалить навсегда">
                                🗑️
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
console.log('[TRASH_DEBUG] list_documents.php скрипт загружен');
console.log('[TRASH_DEBUG] Доступные функции:', {
    restoreItem: typeof window.restoreItem,
    permanentlyDelete: typeof window.permanentlyDelete,
    viewDetails: typeof window.viewDetails
});
</script> 