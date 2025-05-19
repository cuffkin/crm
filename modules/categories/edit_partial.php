<?php
// /crm/modules/categories/edit_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'categories')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

// Идентификатор категории
$id = (int)($_GET['id'] ?? 0);

// Поля по умолчанию
$name        = '';
// $type        = 'category'; // Тип теперь определяется наличием pc_id, поле type в БД всегда "Товарная категория"
$pc_id       = null;
$status      = 'active';
$description = '';
$db_type     = 'Товарная категория'; // Фиксированное значение для поля type в БД

// Если редактирование
if ($id > 0) {
    $st = $conn->prepare("SELECT * FROM PCRM_Categories WHERE id=?");
    $st->bind_param("i", $id);
    $st->execute();
    $res = $st->get_result();
    $c   = $res->fetch_assoc();
    if ($c) {
        $name        = $c['name'];
        // $type        = $c['type']; // Не используется для определения логики формы
        $pc_id       = $c['pc_id'];
        $status      = $c['status'];
        $description = $c['description'];
        $db_type     = $c['type']; // Берем из БД, если есть, но ожидаем "Товарная категория"
    }
}

// Список потенциальных родительских категорий (только те, у кого pc_id IS NULL OR pc_id = 0)
$catRes = $conn->query("
    SELECT id, name
    FROM PCRM_Categories
    WHERE (pc_id IS NULL OR pc_id = 0 OR pc_id = '')
      AND status='active'
      AND id != ". ($id > 0 ? $id : 0) ." /* Нельзя выбрать саму себя в родители */
    ORDER BY name
");
$allParents = $catRes->fetch_all(MYSQLI_ASSOC);
?>

<form id="categoryEditForm">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="db_type" value="<?= htmlspecialchars($db_type) ?>"> <!-- Передаем фиксированный тип -->

    <div class="mb-3">
      <label for="cat-name-modal" class="form-label">Название</label>
      <input type="text" id="cat-name-modal" name="name" class="form-control"
             value="<?= htmlspecialchars($name) ?>" required>
    </div>
    
    <div class="mb-3">
      <label for="cat-pc-modal" class="form-label">Родительская категория</label>
      <select id="cat-pc-modal" name="pc_id" class="form-select">
        <option value="">(Верхний уровень - Категория)</option>
        <?php foreach ($allParents as $p): ?>
          <option value="<?= $p['id'] ?>" <?= ($pc_id == $p['id'] ? 'selected' : '') ?>>
            <?= htmlspecialchars($p['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <small class="form-text text-muted">Если не выбрано, будет создана категория верхнего уровня. Иначе - подкатегория.</small>
    </div>
    
    <div class="mb-3">
      <label for="cat-desc-modal" class="form-label">Описание</label>
      <textarea id="cat-desc-modal" name="description" class="form-control" rows="3"><?= htmlspecialchars($description) ?></textarea>
    </div>
    
    <div class="mb-3">
      <label for="cat-status-modal" class="form-label">Статус</label><br>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="cat-status-switch" name="status_switch" <?= ($status === 'active' ? 'checked' : '') ?>>
        <label class="form-check-label fw-bold ms-2" for="cat-status-switch">
          <span class="<?= $status === 'active' ? 'text-success' : 'text-danger' ?>"><?= $status === 'active' ? 'Активна' : 'Неактивна' ?></span>
        </label>
      </div>
      <input type="hidden" name="status" id="cat-status-hidden" value="<?= $status ?>">
    </div>

    <div class="d-flex justify-content-end">
        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Отмена</button>
        <button type="submit" class="btn btn-success">Сохранить</button>
    </div>
</form>

<script>
$(document).ready(function() {
    // Инициализация select2 для лучшего UI, если он подключен
    // if ($.fn.select2) {
    //     $('#cat-pc-modal').select2({
    //         dropdownParent: $('#categoryEditModal') // Важно для select2 в модалке Bootstrap
    //         // placeholder: "(Верхний уровень - Категория)",
    //         // allowClear: true
    //     });
    // }

    $('#categoryEditForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serializeArray(); // Собираем данные формы
        var dataToSend = {};
        $.each(formData, function(i, field){
            dataToSend[field.name] = field.value;
        });
        
        // Добавляем pc_id = 0 если выбрана опция "(Верхний уровень - Категория)"
        if (dataToSend.pc_id === "") {
            dataToSend.pc_id = "0"; 
        }


        // Визуальная обратная связь о загрузке (можно добавить спиннер на кнопку)
        const $submitButton = $(this).find('button[type="submit"]');
        const originalButtonText = $submitButton.html();
        $submitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Сохранение...');

        $.ajax({
            url: '/crm/modules/categories/save.php',
            type: 'POST',
            data: dataToSend,
            dataType: 'json', // Ожидаем JSON ответ
            success: function(response) {
                if (response.status === 'success') {
                    modalManager.hide('categoryEditModal');
                    loadCategoriesList(); // Обновляем основной список
                    // Можно добавить уведомление об успехе (например, toastr)
                    // if(typeof toastr !== 'undefined') {
                    //    toastr.success(response.message || 'Категория успешно сохранена!');
                    // }
                } else {
                    // alert('Ошибка: ' + (response.message || 'Не удалось сохранить категорию.'));
                     // Улучшенное отображение ошибки
                    let errorHtml = '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
                    errorHtml += (response.message || 'Не удалось сохранить категорию.');
                    errorHtml += '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                    // Показываем ошибку над формой или в специальном месте в модалке
                    $('#categoryEditForm').prepend(errorHtml);

                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // alert('Произошла ошибка AJAX: ' + textStatus + ', ' + errorThrown);
                let errorHtml = '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
                errorHtml += 'Произошла ошибка AJAX: ' + textStatus + ', ' + errorThrown;
                errorHtml += '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                $('#categoryEditForm').prepend(errorHtml);
            },
            complete: function() {
                // Возвращаем кнопку в исходное состояние
                $submitButton.prop('disabled', false).html(originalButtonText);
            }
        });
    });

    $('#cat-status-switch').on('change', function() {
        var label = $(this).closest('.form-check').find('span');
        var hidden = $('#cat-status-hidden');
        if ($(this).is(':checked')) {
            label.text('Активна').removeClass('text-danger').addClass('text-success');
            hidden.val('active');
        } else {
            label.text('Неактивна').removeClass('text-success').addClass('text-danger');
            hidden.val('inactive');
        }
    });
});
</script>