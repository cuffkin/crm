<?php
// /crm/modules/shipments/edit_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'shipments')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

$id = (int)($_GET['id'] ?? 0);
// Проверка режима работы (в отдельной вкладке)
$tabMode = isset($_GET['tab']) && $_GET['tab'] == 1;

// Получение параметра order_id, если он передан (для создания на основании заказа)
$order_id = (int)($_GET['order_id'] ?? 0);
$based_on = $_GET['based_on'] ?? '';

// Значения по умолчанию
$shipment_number = '';
$shipment_date = date('Y-m-d H:i:s');
$warehouse_id = null;
$loader_id = null;
$status = 'new';
$conducted = 0;
$comment = '';

// Загружаем данные отгрузки если ID > 0
if ($id > 0) {
    $st = $conn->prepare("SELECT * FROM PCRM_ShipmentHeader WHERE id=?");
    $st->bind_param("i", $id);
    $st->execute();
    $res = $st->get_result();
    $sh = $res->fetch_assoc();
    if ($sh) {
        $shipment_number = $sh['shipment_number'] ?? '';
        $shipment_date = $sh['shipment_date'];
        $order_id = $sh['order_id'];
        $warehouse_id = $sh['warehouse_id'];
        $loader_id = $sh['loader_id'];
        $status = $sh['status'];
        $conducted = $sh['conducted'];
        $comment = $sh['comment'] ?? '';
    } else {
        die("<div class='text-danger'>Отгрузка не найдена</div>");
    }
}
// Если создаем на основании заказа
else if ($order_id > 0 && $based_on === 'order') {
    // Заполняем данные из заказа
    $orderSql = "SELECT warehouse, customer FROM PCRM_Order WHERE id = ? AND deleted = 0";
    $orderStmt = $conn->prepare($orderSql);
    $orderStmt->bind_param("i", $order_id);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    
    if ($orderResult && $orderResult->num_rows > 0) {
        $orderData = $orderResult->fetch_assoc();
        $warehouse_id = $orderData['warehouse'];
    }
}

// Загружаем список заказов
$ordRes = $conn->query("SELECT id, order_number FROM PCRM_Order WHERE deleted=0 ORDER BY id DESC");
$allOrders = $ordRes->fetch_all(MYSQLI_ASSOC);

// Загружаем список складов
$whRes = $conn->query("SELECT id, name FROM PCRM_Warehouse WHERE status='active' ORDER BY name");
$allWarehouses = $whRes->fetch_all(MYSQLI_ASSOC);

// Загружаем список грузчиков
$ldRes = $conn->query("SELECT id, name FROM PCRM_Loaders WHERE status='active' ORDER BY name");
$allLoaders = $ldRes->fetch_all(MYSQLI_ASSOC);

// Загружаем список товаров
$prodRes = $conn->query("SELECT id, name, price FROM PCRM_Product WHERE status='active' ORDER BY name");
$allProducts = $prodRes->fetch_all(MYSQLI_ASSOC);

// Загружаем позиции отгрузки, если редактируем существующую
$items = [];
if ($id > 0) {
    $sqlItems = "
        SELECT s.*, p.name AS product_name, p.price AS default_price
        FROM PCRM_Shipments s
        LEFT JOIN PCRM_Product p ON s.product_id = p.id
        WHERE s.shipment_header_id = ?
        ORDER BY s.id ASC
    ";
    $st2 = $conn->prepare($sqlItems);
    $st2->bind_param("i", $id);
    $st2->execute();
    $r2 = $st2->get_result();
    $items = $r2->fetch_all(MYSQLI_ASSOC);
}
// Если создаем на основании заказа, автоматически заполняем товары из заказа
else if ($order_id > 0 && $based_on === 'order') {
    $sqlOrderItems = "
        SELECT oi.product_id, oi.quantity, oi.price, oi.discount, p.name AS product_name
        FROM PCRM_OrderItem oi
        LEFT JOIN PCRM_Product p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ";
    $stOrderItems = $conn->prepare($sqlOrderItems);
    $stOrderItems->bind_param("i", $order_id);
    $stOrderItems->execute();
    $orderItemsResult = $stOrderItems->get_result();
    $items = $orderItemsResult->fetch_all(MYSQLI_ASSOC);
}

// Если это новый документ, генерируем номер
if (empty($shipment_number) && $id == 0) {
    $nextIdRes = $conn->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'PCRM_ShipmentHeader'");
    $nextId = $nextIdRes->fetch_row()[0] ?? 1;
    $shipment_number = 'SH-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
}

// Рассчитываем общую сумму
$total_amount = 0;
foreach ($items as $item) {
    $total_amount += ($item['quantity'] * $item['price']) - $item['discount'];
}

// Уникальный идентификатор для объектов на этой странице
$uniquePrefix = 'sh_' . uniqid();
?>
<div class="card">
  <div class="card-header">
    <?= ($id > 0 ? "Редактирование отгрузки #{$id}" : "Новая отгрузка") ?>
    <?= ($based_on === 'order' && $order_id > 0 ? " (на основании заказа #{$order_id})" : "") ?>
  </div>
  <div class="card-body">
    <div class="mb-3">
      <label>Номер отгрузки</label>
      <input type="text" id="sh-number" class="form-control" value="<?= htmlspecialchars($shipment_number) ?>">
    </div>
    <div class="mb-3">
      <label>Дата</label>
      <input type="datetime-local" id="sh-date" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($shipment_date)) ?>">
    </div>
    <div class="mb-3">
      <label>Заказ <span class="text-danger">*</span></label>
      <select id="sh-order" class="form-select required" required <?= ($order_id > 0 && $based_on === 'order' ? 'disabled' : '') ?>>
        <option value="">(не выбран)</option>
        <?php foreach ($allOrders as $o): ?>
        <option value="<?= $o['id'] ?>" <?= ($o['id'] == $order_id ? 'selected' : '') ?>>
          #<?= $o['id'] ?> (<?= htmlspecialchars($o['order_number']) ?>)
        </option>
        <?php endforeach; ?>
      </select>
      <?php if ($order_id > 0 && $based_on === 'order'): ?>
      <input type="hidden" id="sh-order-hidden" value="<?= $order_id ?>">
      <?php endif; ?>
      <div class="invalid-feedback">Выберите заказ</div>
    </div>
    <div class="mb-3">
      <label>Склад <span class="text-danger">*</span></label>
      <select id="sh-warehouse" class="form-select required" required>
        <option value="">(не выбран)</option>
        <?php foreach ($allWarehouses as $w): ?>
        <option value="<?= $w['id'] ?>" <?= ($w['id'] == $warehouse_id ? 'selected' : '') ?>>
          <?= htmlspecialchars($w['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <div class="invalid-feedback">Выберите склад</div>
    </div>
    <div class="mb-3">
      <label>Грузчик</label>
      <select id="sh-loader" class="form-select">
        <option value="">(не выбран)</option>
        <?php foreach ($allLoaders as $l): ?>
        <option value="<?= $l['id'] ?>" <?= ($l['id'] == $loader_id ? 'selected' : '') ?>>
          <?= htmlspecialchars($l['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label>Статус</label>
      <select id="sh-status" class="form-select">
        <option value="new" <?= ($status == 'new' ? 'selected' : '') ?>>Новая</option>
        <option value="in_progress" <?= ($status == 'in_progress' ? 'selected' : '') ?>>В процессе</option>
        <option value="completed" <?= ($status == 'completed' ? 'selected' : '') ?>>Завершена</option>
        <option value="cancelled" <?= ($status == 'cancelled' ? 'selected' : '') ?>>Отменена</option>
      </select>
    </div>
    <div class="mb-3">
      <label>Комментарий</label>
      <textarea id="sh-comment" class="form-control" rows="2"><?= htmlspecialchars($comment) ?></textarea>
    </div>
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" id="sh-conducted" <?= ($conducted == 1 ? 'checked' : '') ?>>
      <label class="form-check-label" for="sh-conducted">Проведена</label>
    </div>
    
    <h5>Товары</h5>
    <table class="table table-sm table-bordered" id="si-table">
      <thead>
        <tr>
          <th>Товар</th>
          <th>Кол-во</th>
          <th>Цена</th>
          <th>Скидка</th>
          <th>Сумма</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $itm): ?>
        <tr>
          <td>
            <div class="input-group">
              <select class="form-select si-product">
                <option value="">(не выбран)</option>
                <?php foreach ($allProducts as $p): ?>
                <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>" <?= ($p['id'] == $itm['product_id'] ? 'selected' : '') ?>>
                  <?= htmlspecialchars($p['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-outline-secondary btn-sm" type="button" onclick="openNewTab('products/edit_partial')">+</button>
            </div>
          </td>
          <td><input type="number" step="0.001" class="form-control si-qty" value="<?= $itm['quantity'] ?>"></td>
          <td><input type="number" step="0.01" class="form-control si-price" value="<?= $itm['price'] ?>"></td>
          <td><input type="number" step="0.01" class="form-control si-discount" value="<?= $itm['discount'] ?? 0 ?>"></td>
          <td class="si-sum"></td>
          <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();window['<?= $uniquePrefix ?>_calcTotal']();">×</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <button class="btn btn-outline-primary btn-sm" onclick="window['<?= $uniquePrefix ?>_addItemRow']()">+ Добавить строку</button>
    <div class="mt-3">
      <label>Итого (руб.)</label>
      <input type="text" id="sh-total" class="form-control" readonly value="<?= number_format($total_amount, 2, '.', '') ?>">
    </div>
    <input type="hidden" id="sh-based-on" value="<?= htmlspecialchars($based_on) ?>">
    <div class="mt-3">
      <button class="btn btn-success" onclick="window['<?= $uniquePrefix ?>_saveShipmentAndClose'](<?= $id ?>)">Сохранить и закрыть</button>
      <button class="btn btn-success" onclick="window['<?= $uniquePrefix ?>_saveShipment'](<?= $id ?>)">Сохранить</button>
      <button class="btn btn-secondary" onclick="window['<?= $uniquePrefix ?>_cancelChanges']()">Отмена</button>
    </div>
    
    <?php
    // Включаем связанные документы, если редактируем существующую отгрузку
    if ($id > 0) {
        require_once __DIR__ . '/../../includes/related_documents.php';
        showRelatedDocuments($conn, 'shipment', $id);
    }
    ?>
  </div>
</div>

<script>
// Внимание! Переменные теперь в локальной области видимости
(function() {
    // Создаем переменные в локальном scope этой анонимной функции
    const ALL_PRODUCTS = <?= json_encode($allProducts, JSON_UNESCAPED_UNICODE) ?>;
    
    // ID текущей вкладки (для закрытия)
    let currentTabId = '';
    let currentTabContentId = '';

    // Глобальная регистрация функций под уникальными именами
    window['<?= $uniquePrefix ?>_calcTotal'] = calcTotal;
    window['<?= $uniquePrefix ?>_addItemRow'] = addItemRow;
    window['<?= $uniquePrefix ?>_addItemRowWithData'] = addItemRowWithData;
    window['<?= $uniquePrefix ?>_saveShipment'] = saveShipment;
    window['<?= $uniquePrefix ?>_saveShipmentAndClose'] = saveShipmentAndClose;
    window['<?= $uniquePrefix ?>_cancelChanges'] = cancelChanges;

    $(document).ready(function(){
        calcTotal();
        
        // Получение ID текущей вкладки из URL параметра или поиск активной вкладки
        let urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('tab_id') && urlParams.has('content_id')) {
            currentTabId = urlParams.get('tab_id');
            currentTabContentId = urlParams.get('content_id');
        } else {
            // Ищем текущий элемент вкладки
            currentTabContentId = $('.tab-pane.active').attr('id');
            if (currentTabContentId) {
                currentTabId = $('a[href="#' + currentTabContentId + '"]').attr('id');
            }
        }
        
        // При выборе заказа автоматически подгружаем товары из него
        $('#sh-order').change(function() {
            const orderId = $(this).val();
            if (orderId) {
                // Очищаем таблицу товаров
                $('#si-table tbody').empty();
                
                // Загружаем товары из заказа
                $.getJSON('/crm/modules/shipments/api_handler.php', { order_id: orderId }, function(data) {
                    if (data.status === 'ok' && data.items.length > 0) {
                        // Добавляем товары из заказа
                        data.items.forEach(function(item) {
                            addItemRowWithData(item);
                        });
                        calcTotal();
                    }
                });
            }
        });

        // Обработчик изменения товаров в таблице
        $('#si-table').on('change', '.si-product, .si-qty, .si-price, .si-discount', function(){
            if ($(this).hasClass('si-product')) {
                let priceInput = $(this).closest('tr').find('.si-price');
                let currentVal = parseFloat(priceInput.val()) || 0;
                if (currentVal === 0) {
                    let sel = $(this).find(':selected');
                    let autoPrice = parseFloat(sel.attr('data-price')) || 0;
                    priceInput.val(autoPrice.toFixed(2));
                }
            }
            calcTotal();
        });
    });

    // Добавление пустой строки товара
    function addItemRow() {
        let rowHtml = `
            <tr>
                <td>
                    <div class="input-group">
                        <select class="form-select si-product">
                            <option value="">(не выбран)</option>
                            ${ALL_PRODUCTS.map(p => `<option value="${p.id}" data-price="${p.price}">${p.name}</option>`).join('')}
                        </select>
                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="openNewTab('products/edit_partial')">+</button>
                    </div>
                </td>
                <td><input type="number" step="0.001" class="form-control si-qty" value="1"></td>
                <td><input type="number" step="0.01" class="form-control si-price" value="0"></td>
                <td><input type="number" step="0.01" class="form-control si-discount" value="0"></td>
                <td class="si-sum"></td>
                <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();window['<?= $uniquePrefix ?>_calcTotal']();">×</button></td>
            </tr>
        `;
        $('#si-table tbody').append(rowHtml);
        calcTotal();
    }

    // Добавление строки с данными
    function addItemRowWithData(item) {
        let rowHtml = `
            <tr data-id="${item.id || ''}">
                <td>
                    <div class="input-group">
                        <select class="form-select si-product">
                            <option value="">(не выбран)</option>
                            ${ALL_PRODUCTS.map(p => `<option value="${p.id}" data-price="${p.price}" ${item.product_id == p.id ? 'selected' : ''}>${p.name}</option>`).join('')}
                        </select>
                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="openNewTab('products/edit_partial')">+</button>
                    </div>
                </td>
                <td><input type="number" step="0.001" class="form-control si-qty" value="${item.quantity}"></td>
                <td><input type="number" step="0.01" class="form-control si-price" value="${item.price}"></td>
                <td><input type="number" step="0.01" class="form-control si-discount" value="${item.discount || 0}"></td>
                <td class="si-sum"></td>
                <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();window['<?= $uniquePrefix ?>_calcTotal']();">×</button></td>
            </tr>
        `;
        $('#si-table tbody').append(rowHtml);
    }

    // Расчёт общей суммы
    function calcTotal() {
        let grand = 0;
        $('#si-table tbody tr').each(function(){
            let qty = parseFloat($(this).find('.si-qty').val()) || 0;
            let price = parseFloat($(this).find('.si-price').val()) || 0;
            let discount = parseFloat($(this).find('.si-discount').val()) || 0;
            let sum = (qty * price) - discount;
            $(this).find('.si-sum').text(sum.toFixed(2));
            grand += sum;
        });
        $('#sh-total').val(grand.toFixed(2));
    }

    // Сохранить и закрыть
    function saveShipmentAndClose(sid) {
        saveShipment(sid, true);
    }

    // Сохранение отгрузки
    function saveShipment(sid, closeAfterSave = false) {
        // Проверка обязательных полей
        let valid = true;
        
        // Проверка заказа
        let orderId = $('#sh-order').val() || $('#sh-order-hidden').val();
        if (!orderId) {
            $('#sh-order').addClass('is-invalid');
            valid = false;
        } else {
            $('#sh-order').removeClass('is-invalid');
        }
        
        // Проверка склада
        if (!$('#sh-warehouse').val()) {
            $('#sh-warehouse').addClass('is-invalid');
            valid = false;
        } else {
            $('#sh-warehouse').removeClass('is-invalid');
        }
        
        // Проверка наличия товаров
        const hasProducts = $('#si-table tbody tr').length > 0 && 
                            $('#si-table tbody tr').some(function() {
                                return $(this).find('.si-product').val() !== '';
                            });
        
        if (!hasProducts) {
            alert('Добавьте хотя бы один товар в отгрузку');
            valid = false;
        }
        
        if (!valid) {
            return;
        }
        
        calcTotal();
        
        // Собираем данные для отправки
        let data = {
            id: sid,
            shipment_number: $('#sh-number').val(),
            shipment_date: $('#sh-date').val(),
            order_id: orderId,
            warehouse_id: $('#sh-warehouse').val(),
            loader_id: $('#sh-loader').val(),
            status: $('#sh-status').val(),
            conducted: ($('#sh-conducted').is(':checked') ? 1 : 0),
            comment: $('#sh-comment').val(),
            based_on: $('#sh-based-on').val()
        };

        // Собираем товары
        let items = [];
        $('#si-table tbody tr').each(function(){
            let pid = $(this).find('.si-product').val();
            if (!pid) return;
            let qty = parseFloat($(this).find('.si-qty').val()) || 0;
            let prc = parseFloat($(this).find('.si-price').val()) || 0;
            let dsc = parseFloat($(this).find('.si-discount').val()) || 0;
            
            items.push({
                product_id: pid, 
                quantity: qty, 
                price: prc, 
                discount: dsc
            });
        });
        data.items = JSON.stringify(items);

        // Отправляем данные на сервер
        $.post('/crm/modules/shipments/save.php', data, function(resp){
            try {
                const response = JSON.parse(resp);
                
                if (response.status === 'ok') {
                    // Обновляем все списки отгрузок
                    updateShipmentList();
                    
                    // Показываем уведомление
                    console.log('Отгрузка успешно сохранена');
                    // Безопасный вызов через setTimeout
                    setTimeout(function() {
                        try {
                            if (typeof appShowNotification === 'function') {
                                appShowNotification('Отгрузка успешно сохранена', 'success');
                            }
                        } catch (e) {
                            console.error('Ошибка при показе уведомления:', e);
                        }
                    }, 0);
                    
                    // Если это новая отгрузка или нужно закрыть вкладку после сохранения
                    if (closeAfterSave) {
                        // Закрываем текущую вкладку
                        cancelChanges();
                    } else if (sid === 0) {
                        // Получаем ID созданной отгрузки
                        const newId = response.shipment_id;
                        if (newId > 0) {
                            // Закрываем текущую вкладку
                            cancelChanges();
                            
                            // Открываем новую вкладку с созданной отгрузкой
                            openShipmentEditTab(newId);
                        }
                    }
                } else {
                    alert('Ошибка: ' + response.message);
                }
            } catch (e) {
                // Для обратной совместимости с текстовым ответом "OK"
                if (resp === 'OK') {
                    // Обновляем все списки отгрузок
                    updateShipmentList();
                    
                    // Показываем уведомление
                    console.log('Отгрузка успешно сохранена');
                    // Безопасный вызов через setTimeout
                    setTimeout(function() {
                        try {
                            if (typeof appShowNotification === 'function') {
                                appShowNotification('Отгрузка успешно сохранена', 'success');
                            }
                        } catch (e) {
                            console.error('Ошибка при показе уведомления:', e);
                        }
                    }, 0);
                    
                    // Если это новая отгрузка или нужно закрыть вкладку после сохранения
                    if (closeAfterSave) {
                        // Закрываем текущую вкладку
                        cancelChanges();
                    } else if (sid === 0) {
                        // Получаем ID созданной отгрузки
                        $.get('/crm/modules/shipments/api_handler.php', function(newId) {
                            if (newId > 0) {
                                // Закрываем текущую вкладку
                                cancelChanges();
                                
                                // Открываем новую вкладку с созданной отгрузкой
                                openShipmentEditTab(newId);
                            }
                        });
                    }
                } else {
                    alert(resp);
                }
            }
        });
    }

    // Отмена изменений/закрытие вкладки
    function cancelChanges() {
        if (currentTabId && currentTabContentId) {
            closeModuleTab(currentTabId, currentTabContentId);
        } else {
            // Ищем ближайшую родительскую вкладку
            const tabContent = $('.tab-pane.active');
            if (tabContent.length) {
                const contentId = tabContent.attr('id');
                const tabId = $('a[href="#' + contentId + '"]').attr('id');
                if (contentId && tabId) {
                    closeModuleTab(tabId, contentId);
                }
            }
        }
    }
})();

// Расширение для метода some в jQuery
$.fn.some = function(callback) {
    for (let i = 0; i < this.length; i++) {
        if (callback.call(this[i], i, this[i])) {
            return true;
        }
    }
    return false;
};

// Используем глобальную функцию openNewTab из common.js

// Функция для добавления новой строки товара
window['<?= $uniquePrefix ?>_addItemRow'] = function() {
    const newRow = `
        <tr>
            <td>
                <div class="input-group">
                    <select class="form-select si-product">
                        <option value="">(не выбран)</option>
                        <?php foreach ($allProducts as $p): ?>
                        <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>">
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="openNewTab('products/edit_partial')">+</button>
                </div>
            </td>
            <td><input type="number" step="0.001" class="form-control si-qty" value="1"></td>
            <td><input type="number" step="0.01" class="form-control si-price" value="0"></td>
            <td><input type="number" step="0.01" class="form-control si-discount" value="0"></td>
            <td class="si-sum"></td>
            <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();window['<?= $uniquePrefix ?>_calcTotal']();">×</button></td>
        </tr>
    `;
    $('#si-table tbody').append(newRow);
    initRowHandlers();
    window['<?= $uniquePrefix ?>_calcTotal']();
};
</script>