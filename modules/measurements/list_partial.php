<?php
// /crm/modules/measurements/list_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Проверка прав доступа
if (!check_access($conn, $_SESSION['user_id'], 'measurements')) {
    echo '<div class="alert alert-danger">У вас нет доступа к этому разделу.</div>';
    exit;
}

// Получение списка единиц измерения
$sql = "SELECT * FROM PCRM_Measurement ORDER BY name";
$result = $conn->query($sql);

// Проверка существования таблицы
if ($result === false && strpos($conn->error, "doesn't exist") !== false) {
    // Создаем таблицу, если она не существует
    $createTableSQL = "
    CREATE TABLE `PCRM_Measurement` (
      `id` int NOT NULL AUTO_INCREMENT,
      `name` varchar(100) NOT NULL,
      `short_name` varchar(20) NOT NULL,
      `description` text,
      `is_default` tinyint(1) NOT NULL DEFAULT '0',
      `status` enum('active','inactive') NOT NULL DEFAULT 'active',
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `short_name` (`short_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
    
    -- Добавление начальных данных
    INSERT INTO `PCRM_Measurement` (`name`, `short_name`, `description`, `is_default`, `status`) VALUES
    ('Штука', 'шт.', 'Штучный товар', 1, 'active'),
    ('Упаковка', 'уп.', 'Товар, продаваемый упаковками', 0, 'active'),
    ('Рулон', 'рул.', 'Рулонный материал', 0, 'active'),
    ('Лист', 'л.', 'Листовой материал', 0, 'active'),
    ('Тонна', 'т.', 'Весовой товар (тонны)', 0, 'active'),
    ('Мешок', 'меш.', 'Товар в мешках', 0, 'active'),
    ('Килограмм', 'кг', 'Весовой товар (килограммы)', 0, 'active'),
    ('Метр погонный', 'м.пог.', 'Погонный метр', 0, 'active');";
    
    if ($conn->multi_query($createTableSQL)) {
        // Необходимо очистить все результаты
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
        
        // Заново запрашиваем данные
        $result = $conn->query("SELECT * FROM PCRM_Measurement ORDER BY name");
    } else {
        echo '<div class="alert alert-danger">Ошибка создания таблицы единиц измерения: ' . $conn->error . '</div>';
    }
}

// Проверяем, существует ли таблица связи между товарами и единицами
$checkProductMeasurementTable = "SHOW TABLES LIKE 'PCRM_Product_Measurement'";
$productMeasurementExists = $conn->query($checkProductMeasurementTable)->num_rows > 0;

if (!$productMeasurementExists) {
    // Создаем таблицу связей
    $createLinkTableSQL = "
    CREATE TABLE `PCRM_Product_Measurement` (
      `id` int NOT NULL AUTO_INCREMENT,
      `product_id` int NOT NULL,
      `measurement_id` int NOT NULL,
      `is_primary` tinyint(1) NOT NULL DEFAULT '0',
      `conversion_factor` decimal(10,4) DEFAULT '1.0000',
      PRIMARY KEY (`id`),
      UNIQUE KEY `product_measurement` (`product_id`, `measurement_id`),
      KEY `measurement_id` (`measurement_id`),
      CONSTRAINT `fk_product_measurement_product` FOREIGN KEY (`product_id`) REFERENCES `PCRM_Product` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_product_measurement_measurement` FOREIGN KEY (`measurement_id`) REFERENCES `PCRM_Measurement` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;";
    
    if (!$conn->query($createLinkTableSQL)) {
        echo '<div class="alert alert-warning">Не удалось создать таблицу связей товаров и единиц измерения: ' . $conn->error . '</div>';
    }
}

// Проверяем, есть ли колонка default_measurement_id в таблице PCRM_Product
$checkColumnSQL = "SHOW COLUMNS FROM PCRM_Product LIKE 'default_measurement_id'";
$columnExists = $conn->query($checkColumnSQL)->num_rows > 0;

if (!$columnExists) {
    // Добавляем колонку
    $addColumnSQL = "
    ALTER TABLE `PCRM_Product` 
    ADD COLUMN `default_measurement_id` int DEFAULT NULL AFTER `name`,
    ADD CONSTRAINT `fk_product_default_measurement` FOREIGN KEY (`default_measurement_id`) REFERENCES `PCRM_Measurement` (`id`) ON DELETE SET NULL;";
    
    if (!$conn->query($addColumnSQL)) {
        echo '<div class="alert alert-warning">Не удалось добавить колонку default_measurement_id в таблицу PCRM_Product: ' . $conn->error . '</div>';
    } else {
        // Обновляем существующие товары
        $updateProductsSQL = "UPDATE `PCRM_Product` SET `default_measurement_id` = 1 WHERE `default_measurement_id` IS NULL;";
        if (!$conn->query($updateProductsSQL)) {
            echo '<div class="alert alert-warning">Не удалось обновить единицы измерения для существующих товаров: ' . $conn->error . '</div>';
        }
    }
}

// Получаем список единиц измерения снова, если была ошибка
if ($result === false) {
    $result = $conn->query("SELECT * FROM PCRM_Measurement ORDER BY name");
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Единицы измерения</h4>
        <button type="button" class="btn btn-primary btn-sm" onclick="openMeasurementEditTab(0)">
            <i class="fas fa-plus"></i> Добавить единицу измерения
        </button>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Наименование</th>
                    <th>Сокращение</th>
                    <th>По умолчанию</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['short_name']) ?></td>
                            <td>
                                <?php if ($row['is_default']): ?>
                                    <span class="badge bg-success">Да</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Нет</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['status'] == 'active'): ?>
                                    <span class="badge bg-success">Активна</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Неактивна</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-warning btn-sm" 
                                    onclick="openMeasurementEditTab(<?= $row['id'] ?>)">
                                    Редакт.
                                </button>
                                <button type="button" class="btn btn-danger btn-sm" 
                                    onclick="deleteMeasurement(<?= $row['id'] ?>)">
                                    Удалить
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">Единицы измерения не найдены</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function openMeasurementEditTab(measurementId) {
  // Создаем уникальный ID для вкладки
  const timestamp = Date.now();
  const tabId = 'tab-measurements-edit-' + timestamp;
  const tabContentId = 'content-measurements-edit-' + timestamp;
  
  // Создаем новую вкладку напрямую
  let title = measurementId > 0 ? 'Редактирование ЕИ' : 'Новая ЕИ';
  
  let navItem = $(`
    <li class="nav-item">
      <a class="nav-link" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" data-measurement-id="${measurementId}">
        ${title}
        <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>
  `);

  navItem.find('.btn-close').on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
  });

  let tabPane = $(`
    <div class="tab-pane fade" id="${tabContentId}">
      <p>Загрузка формы редактирования...</p>
    </div>
  `);

  $('#crm-tabs').append(navItem);
  $('#crm-tab-content').append(tabPane);

  $('#' + tabId).tab('show');
  
  // Загружаем форму редактирования
  $.get('/crm/modules/measurements/edit_partial.php', { id: measurementId }, function(html) {
    tabPane.html(html).addClass('fade-in');
    if (typeof initFormTracking === 'function') {
      initFormTracking(tabContentId);
    }
    if (typeof saveTabsState === 'function') {
      saveTabsState();
    }
  });
}

function deleteMeasurement(measurementId) {
    if (!confirm('Вы уверены, что хотите удалить эту единицу измерения?')) return;
    
    $.ajax({
        url: '/crm/modules/measurements/delete.php',
        type: 'POST',
        data: {
            id: measurementId
        },
        success: function(response) {
            try {
                const result = JSON.parse(response);
                if (result.success) {
                    // Перезагружаем список единиц измерения на текущей вкладке
                    const activeTabContentId = $('.tab-pane.active').attr('id');
                    $.get('/crm/modules/measurements/list_partial.php', function(html) {
                        $('#' + activeTabContentId).html(html);
                    });
                    
                    // Показываем уведомление
                    alert('Единица измерения успешно удалена');
                } else {
                    alert('Ошибка при удалении: ' + result.message);
                }
            } catch (e) {
                console.error('Ошибка при обработке ответа:', e);
                console.error('Ответ сервера:', response);
                alert('Произошла ошибка при обработке ответа от сервера');
            }
        },
        error: function(xhr, status, error) {
            console.error('Ошибка запроса:', error);
            alert('Произошла ошибка при выполнении запроса: ' + error);
        }
    });
}
</script> 