<?php
// /crm/modules/measurements/edit_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Проверка прав доступа
if (!check_access($conn, $_SESSION['user_id'], 'measurements')) {
    echo '<div class="alert alert-danger">У вас нет доступа к этому разделу.</div>';
    exit;
}

// Определение режима (создание или редактирование)
$measurementId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$mode = $measurementId > 0 ? 'edit' : 'create';
$measurement = [
    'id' => 0,
    'name' => '',
    'short_name' => '',
    'description' => '',
    'is_default' => 0,
    'status' => 'active'
];

if ($mode === 'edit') {
    $sql = "SELECT * FROM PCRM_Measurement WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $measurementId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo '<div class="alert alert-danger">Единица измерения не найдена</div>';
        exit;
    }
    
    $measurement = $result->fetch_assoc();
    $stmt->close();
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><?= $mode === 'edit' ? 'Редактирование' : 'Создание' ?> единицы измерения</h4>
        <button type="button" class="btn btn-secondary btn-sm" onclick="openModuleTab('measurements/list')">
            Вернуться к списку
        </button>
    </div>

    <form id="measurementForm">
        <input type="hidden" name="id" value="<?= $measurement['id'] ?>">
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="name" class="form-label">Наименование <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" 
                       value="<?= htmlspecialchars($measurement['name']) ?>" required>
            </div>
            <div class="col-md-6">
                <label for="short_name" class="form-label">Сокращение <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="short_name" name="short_name" 
                       value="<?= htmlspecialchars($measurement['short_name']) ?>" required>
            </div>
        </div>
        
        <div class="mb-3">
            <label for="description" class="form-label">Описание</label>
            <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($measurement['description'] ?? '') ?></textarea>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_default" name="is_default" 
                           value="1" <?= $measurement['is_default'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_default">
                        Использовать по умолчанию
                    </label>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label d-block">Статус</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="status" id="status_active" 
                           value="active" <?= $measurement['status'] === 'active' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="status_active">Активна</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="status" id="status_inactive" 
                           value="inactive" <?= $measurement['status'] === 'inactive' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="status_inactive">Неактивна</label>
                </div>
            </div>
        </div>
        
        <div class="d-flex justify-content-end">
            <button type="button" class="btn btn-primary btn-sm" onclick="saveMeasurement()">
                <i class="fas fa-save"></i> Сохранить
            </button>
        </div>
    </form>
</div>

<script>
function saveMeasurement() {
    const form = $('#measurementForm');
    
    // Базовая валидация
    if (!form[0].checkValidity()) {
        form[0].reportValidity();
        return;
    }
    
    // Серализация формы
    const formData = form.serialize();
    
    // Добавляем is_default как 0, если не отмечен
    const isDefaultChecked = $('#is_default').is(':checked');
    const formDataAdjusted = isDefaultChecked ? formData : formData + '&is_default=0';
    
    $.ajax({
        url: '/crm/modules/measurements/edit_post.php',
        type: 'POST',
        data: formDataAdjusted,
        success: function(response) {
            try {
                const result = JSON.parse(response);
                if (result.success) {
                    // Показываем уведомление об успехе
                    alert('Единица измерения успешно сохранена');
                    
                    // Находим открытую вкладку со списком измерений и обновляем её
                    let measurementsListTab = $("a[data-module='measurements/list']");
                    if (measurementsListTab.length > 0) {
                        // Если вкладка со списком существует, обновляем её
                        const listTabContentId = measurementsListTab.attr('href').substring(1);
                        $.get('/crm/modules/measurements/list_partial.php', function(html) {
                            $('#' + listTabContentId).html(html);
                        });
                        
                        // Переключаемся на вкладку со списком
                        measurementsListTab.tab('show');
                    } else {
                        // Если вкладки нет, открываем новую
                        openModuleTab('measurements/list');
                    }
                    
                    // Закрываем текущую вкладку
                    const currentTabId = $('.nav-link.active').attr('id');
                    const currentContentId = $('.tab-pane.active').attr('id');
                    
                    if (currentTabId && currentContentId) {
                        setTimeout(function() {
                            closeModuleTab(currentTabId, currentContentId);
                        }, 100);
                    }
                } else {
                    alert('Ошибка: ' + (result.message || 'Неизвестная ошибка'));
                }
            } catch (e) {
                console.error('Ошибка при разборе JSON:', e);
                alert('Ошибка при обработке ответа сервера');
            }
        },
        error: function(xhr, status, error) {
            alert('Ошибка при сохранении: ' + error);
        }
    });
}
</script> 