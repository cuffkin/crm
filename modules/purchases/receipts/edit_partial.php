<?php
// /crm/modules/purchases/receipts/edit_partial.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'purchases_receipts')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

$id = (int)($_GET['id'] ?? 0);
// Проверка режима работы (в отдельной вкладке)
$tabMode = isset($_GET['tab']) && $_GET['tab'] == 1;

// Получение параметра purchase_order_id, если он передан (для создания на основании заказа поставщику)
$purchase_order_id = (int)($_GET['purchase_order_id'] ?? 0);
$based_on = $_GET['based_on'] ?? '';

// Значения по умолчанию
$receipt_number = '';
$receipt_date = date('Y-m-d H:i:s');
$warehouse_id = null;
$loader_id = null;
$status = 'new';
$conducted = 0;
$comment = '';

// Загружаем данные приёмки если ID > 0
if ($id > 0) {
    $st = $conn->prepare("SELECT * FROM PCRM_ReceiptHeader WHERE id=?");
    $st->bind_param("i", $id);
    $st->execute();
    $res = $st->get_result();
    $rh = $res->fetch_assoc();
    if ($rh) {
        $receipt_number = $rh['receipt_number'] ?? '';
        $receipt_date = $rh['receipt_date'];
        $purchase_order_id = $rh['purchase_order_id'];
        $warehouse_id = $rh['warehouse_id'];
        $loader_id = $rh['loader_id'];
        $status = $rh['status'];
        $conducted = $rh['conducted'];
        $comment = $rh['comment'] ?? '';
    } else {
        die("<div class='text-danger'>Приёмка не найдена</div>");
    }
}
// Если создаем на основании заказа поставщику
else if ($purchase_order_id > 0 && $based_on === 'purchase_order') {
    // Заполняем данные из заказа поставщику
    $orderSql = "SELECT warehouse_id, supplier_id FROM PCRM_PurchaseOrder WHERE id = ? AND deleted = 0";
    $orderStmt = $conn->prepare($orderSql);
    $orderStmt->bind_param("i", $purchase_order_id);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    
    if ($orderResult && $orderResult->num_rows > 0) {
        $orderData = $orderResult->fetch_assoc();
        $warehouse_id = $orderData['warehouse_id'];
    }
}

// Загружаем список заказов поставщикам
$ordRes = $conn->query("SELECT id, purchase_order_number FROM PCRM_PurchaseOrder WHERE deleted=0 ORDER BY id DESC");
$allOrders = $ordRes->fetch_all(MYSQLI_ASSOC);

// Загружаем список складов
$whRes = $conn->query("SELECT id, name FROM PCRM_Warehouse WHERE status='active' ORDER BY name");
$allWarehouses = $whRes->fetch_all(MYSQLI_ASSOC);

// Загружаем список грузчиков
$ldRes = $conn->query("SELECT id, name FROM PCRM_Loaders WHERE status='active' ORDER BY name");
$allLoaders = $ldRes->fetch_all(MYSQLI_ASSOC);

// Загружаем список товаров
$prodRes = $conn->query("SELECT id, name, cost_price, price FROM PCRM_Product WHERE status='active' ORDER BY name");
$allProducts = $prodRes->fetch_all(MYSQLI_ASSOC);

// Загружаем позиции приёмки, если редактируем существующую
$items = [];
if ($id > 0) {
    $sqlItems = "
        SELECT ri.*, p.name AS product_name, p.cost_price AS default_price
        FROM PCRM_ReceiptItem ri
        LEFT JOIN PCRM_Product p ON ri.product_id = p.id
        WHERE ri.receipt_header_id = ?
        ORDER BY ri.id ASC
    ";
    $st2 = $conn->prepare($sqlItems);
    $st2->bind_param("i", $id);
    $st2->execute();
    $r2 = $st2->get_result();
    $items = $r2->fetch_all(MYSQLI_ASSOC);
}
// Если создаем на основании заказа поставщику, автоматически заполняем товары из заказа
else if ($purchase_order_id > 0 && $based_on === 'purchase_order') {
    $sqlOrderItems = "
        SELECT poi.product_id, poi.quantity, poi.price, poi.discount, p.name AS product_name
        FROM PCRM_PurchaseOrderItem poi
        LEFT JOIN PCRM_Product p ON poi.product_id = p.id
        WHERE poi.purchase_order_id = ?
        ORDER BY poi.id ASC
    ";
    $stOrderItems = $conn->prepare($sqlOrderItems);
    $stOrderItems->bind_param("i", $purchase_order_id);
    $stOrderItems->execute();
    $orderItemsResult = $stOrderItems->get_result();
    $items = $orderItemsResult->fetch_all(MYSQLI_ASSOC);
}

// Если это новый документ, генерируем номер
if (empty($receipt_number) && $id == 0) {
    $nextIdRes = $conn->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'PCRM_ReceiptHeader'");
    $nextId = $nextIdRes->fetch_row()[0] ?? 1;
    $receipt_number = 'REC-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
}

// Рассчитываем общую сумму
$total_amount = 0;
foreach ($items as $item) {
    $total_amount += ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0);
}

// Уникальный идентификатор для объектов на этой странице
$uniquePrefix = 'rc_' . uniqid();
?>
<div class="card">
  <div class="card-header">
    <?= ($id > 0 ? "Редактирование приёмки #{$id}" : "Новая приёмка") ?>
    <?= ($based_on === 'purchase_order' && $purchase_order_id > 0 ? " (на основании заказа поставщику #{$purchase_order_id})" : "") ?>
  </div>
  <div class="card-body">
    <div class="mb-3">
      <label>Номер приёмки</label>
      <input type="text" id="rc-number" class="form-control" value="<?= htmlspecialchars($receipt_number) ?>">
    </div>
    <div class="mb-3">
      <label>Дата</label>
      <input type="datetime-local" id="rc-date" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($receipt_date)) ?>">
    </div>
    <div class="mb-3">
      <label>Заказ поставщику <span class="text-danger">*</span></label>
      <select id="rc-order" class="form-select required" required <?= ($purchase_order_id > 0 && $based_on === 'purchase_order' ? 'disabled' : '') ?>>
        <option value="">(не выбран)</option>
        <?php foreach ($allOrders as $o): ?>
        <option value="<?= $o['id'] ?>" <?= ($o['id'] == $purchase_order_id ? 'selected' : '') ?>>
          #<?= $o['id'] ?> (<?= htmlspecialchars($o['purchase_order_number']) ?>)
        </option>
        <?php endforeach; ?>
      </select>
      <?php if ($purchase_order_id > 0 && $based_on === 'purchase_order'): ?>
      <input type="hidden" id="rc-order-hidden" value="<?= $purchase_order_id ?>">
      <?php endif; ?>
      <div class="invalid-feedback">Выберите заказ поставщику</div>
    </div>
    <div class="mb-3">
      <label>Склад <span class="text-danger">*</span></label>
      <select id="rc-warehouse" class="form-select required" required>
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
      <select id="rc-loader" class="form-select">
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
      <select id="rc-status" class="form-select">
        <option value="new" <?= ($status == 'new' ? 'selected' : '') ?>>Новая</option>
        <option value="in_progress" <?= ($status == 'in_progress' ? 'selected' : '') ?>>В процессе</option>
        <option value="completed" <?= ($status == 'completed' ? 'selected' : '') ?>>Завершена</option>
        <option value="cancelled" <?= ($status == 'cancelled' ? 'selected' : '') ?>>Отменена</option>
      </select>
    </div>
    <div class="mb-3">
      <label>Комментарий</label>
      <textarea id="rc-comment" class="form-control" rows="2"><?= htmlspecialchars($comment) ?></textarea>
    </div>
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" id="rc-conducted" <?= ($conducted == 1 ? 'checked' : '') ?>>
      <label class="form-check-label" for="rc-conducted">Проведена</label>
    </div>
    
    <h5>Товары</h5>
    <table class="table table-sm table-bordered" id="ri-table">
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
            <select class="form-select ri-product">
              <option value="">(не выбран)</option>
              <?php foreach ($allProducts as $p): ?>
              <option value="<?= $p['id'] ?>" data-price="<?= $p['cost_price'] ?>" <?= ($p['id'] == $itm['product_id'] ? 'selected' : '') ?>>
                <?= htmlspecialchars($p['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input type="number" step="0.001" class="form-control ri-qty" value="<?= $itm['quantity'] ?>"></td>
          <td><input type="number" step="0.01" class="form-control ri-price" value="<?= $itm['price'] ?>"></td>
          <td><input type="number" step="0.01" class="form-control ri-discount" value="<?= $itm['discount'] ?? 0 ?>"></td>
          <td class="ri-sum"></td>
          <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();window['<?= $uniquePrefix ?>_calcTotal']();">×</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <button class="btn btn-outline-primary btn-sm" onclick="window['<?= $uniquePrefix ?>_addItemRow']()">+ Добавить строку</button>
    <div class="mt-3">
      <label>Итого (руб.)</label>
      <input type="text" id="rc-total" class="form-control" readonly value="<?= number_format($total_amount, 2, '.', '') ?>">
    </div>
    <input type="hidden" id="rc-based-on" value="<?= htmlspecialchars($based_on) ?>">
    <div class="mt-3">
      <button class="btn btn-success" onclick="window['<?= $uniquePrefix ?>_saveReceiptAndClose'](<?= $id ?>)">Сохранить и закрыть</button>
      <button class="btn btn-success" onclick="window['<?= $uniquePrefix ?>_saveReceipt'](<?= $id ?>)">Сохранить</button>
      
      <?php if ($id > 0): ?>
      <!-- Кнопка "Создать на основании" с выпадающим меню -->
      <div class="btn-group">
        <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          Создать на основании
        </button>
        <ul class="dropdown-menu">
          <li><a class="dropdown-item" href="#" onclick="createSupplierReturnFromReceipt(<?= $id ?>)">Возврат поставщику</a></li>
          <li><a class="dropdown-item" href="#" onclick="createFinanceFromReceipt(<?= $id ?>, 'expense')">Расходная кассовая операция</a></li>
        </ul>
      </div>
      <?php endif; ?>
      
      <button class="btn btn-secondary" onclick="window['<?= $uniquePrefix ?>_cancelChanges']()">Отмена</button>
    </div>
    
    <?php
    // Включаем связанные документы, если редактируем существующую приёмку
    if ($id > 0) {
        require_once __DIR__ . '/../../../includes/related_documents.php';
        showRelatedDocuments($conn, 'receipt', $id);
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
    window['<?= $uniquePrefix ?>_saveReceipt'] = saveReceipt;
    window['<?= $uniquePrefix ?>_saveReceiptAndClose'] = saveReceiptAndClose;
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
        
        // При выборе заказа поставщику автоматически подгружаем товары из него
        $('#rc-order').change(function() {
            const orderId = $(this).val();
            if (orderId) {
                // Загружаем данные заказа поставщику
                $.getJSON('/crm/modules/purchases/orders/order_api.php', { 
                    action: 'get_order_info',
                    id: orderId 
                }, function(response) {
                    if (response.status === 'ok') {
                        // Заполняем склад из заказа поставщику
                        $('#rc-warehouse').val(response.data.warehouse_id);
                    }
                });
                
                // Очищаем таблицу товаров
                $('#ri-table tbody').empty();
                
                // Загружаем товары из заказа поставщику
                $.getJSON('/crm/modules/purchases/orders/order_api.php', { 
                    action: 'get_order_items',
                    id: orderId 
                }, function(data) {
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
        $('#ri-table').on('change', '.ri-product, .ri-qty, .ri-price, .ri-discount', function(){
            if ($(this).hasClass('ri-product')) {
                let priceInput = $(this).closest('tr').find('.ri-price');
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
                    <select class="form-select ri-product">
                        <option value="">(не выбран)</option>
                        ${ALL_PRODUCTS.map(p => `<option value="${p.id}" data-price="${p.cost_price}">${p.name}</option>`).join('')}
                    </select>
                </td>
                <td><input type="number" step="0.001" class="form-control ri-qty" value="1"></td>
                <td><input type="number" step="0.01" class="form-control ri-price" value="0"></td>
                <td><input type="number" step="0.01" class="form-control ri-discount" value="0"></td>
                <td class="ri-sum"></td>
                <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();window['<?= $uniquePrefix ?>_calcTotal']();">×</button></td>
            </tr>
        `;
        $('#ri-table tbody').append(rowHtml);
        calcTotal();
    }

    // Добавление строки с данными
    function addItemRowWithData(item) {
        let rowHtml = `
            <tr>
                <td>
                    <select class="form-select ri-product">
                        <option value="">(не выбран)</option>
                        ${ALL_PRODUCTS.map(p => `<option value="${p.id}" data-price="${p.cost_price}" ${p.id == item.product_id ? 'selected' : ''}>${p.name}</option>`).join('')}
                    </select>
                </td>
                <td><input type="number" step="0.001" class="form-control ri-qty" value="${item.quantity}"></td>
                <td><input type="number" step="0.01" class="form-control ri-price" value="${item.price}"></td>
                <td><input type="number" step="0.01" class="form-control ri-discount" value="${item.discount || 0}"></td>
                <td class="ri-sum"></td>
                <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();window['<?= $uniquePrefix ?>_calcTotal']();">×</button></td>
            </tr>
        `;
        $('#ri-table tbody').append(rowHtml);
    }

    // Расчёт общей суммы
    function calcTotal() {
        let grand = 0;
        $('#ri-table tbody tr').each(function(){
            let qty = parseFloat($(this).find('.ri-qty').val()) || 0;
            let price = parseFloat($(this).find('.ri-price').val()) || 0;
            let discount = parseFloat($(this).find('.ri-discount').val()) || 0;
            let sum = (qty * price) - discount;
            $(this).find('.ri-sum').text(sum.toFixed(2));
            grand += sum;
        });
        $('#rc-total').val(grand.toFixed(2));
    }

    // Сохранить и закрыть
    function saveReceiptAndClose(rid) {
        saveReceipt(rid, true);
    }

    // Сохранение приёмки
    function saveReceipt(rid, closeAfterSave = false) {
        // Проверка обязательных полей
        let valid = true;
        
        // Проверка заказа поставщику
        let orderId = $('#rc-order').val() || $('#rc-order-hidden').val();
        if (!orderId) {
            $('#rc-order').addClass('is-invalid');
            valid = false;
        } else {
            $('#rc-order').removeClass('is-invalid');
        }
        
        // Проверка склада
        if (!$('#rc-warehouse').val()) {
            $('#rc-warehouse').addClass('is-invalid');
            valid = false;
        } else {
            $('#rc-warehouse').removeClass('is-invalid');
        }
        
        // Проверка наличия товаров
        const hasProducts = $('#ri-table tbody tr').length > 0 && 
                            $('#ri-table tbody tr').some(function() {
                                return $(this).find('.ri-product').val() !== '';
                            });
        
        if (!hasProducts) {
            alert('Добавьте хотя бы один товар в приёмку');
            valid = false;
        }
        
        if (!valid) {
            return;
        }
        
        calcTotal();
        
        // Собираем данные для отправки
        let data = {
            id: rid,
            receipt_number: $('#rc-number').val(),
            receipt_date: $('#rc-date').val(),
            purchase_order_id: orderId,
            warehouse_id: $('#rc-warehouse').val(),
            loader_id: $('#rc-loader').val() || null,
            status: $('#rc-status').val(),
            conducted: ($('#rc-conducted').is(':checked') ? 1 : 0),
            comment: $('#rc-comment').val(),
            based_on: $('#rc-based-on').val()
        };

        // Собираем товары
        let items = [];
        $('#ri-table tbody tr').each(function(){
            let pid = $(this).find('.ri-product').val();
            if (!pid) return;
            let qty = parseFloat($(this).find('.ri-qty').val()) || 0;
            let prc = parseFloat($(this).find('.ri-price').val()) || 0;
            let dsc = parseFloat($(this).find('.ri-discount').val()) || 0;
            
            items.push({
                product_id: pid, 
                quantity: qty, 
                price: prc, 
                discount: dsc
            });
        });
        data.items = JSON.stringify(items);

        // Отправляем данные на сервер
        $.post('/crm/modules/purchases/receipts/save.php', data, function(resp){
            try {
                const response = JSON.parse(resp);
                
                if (response.status === 'ok') {
                    // Обновляем все списки приёмок
                    updateReceiptList();
                    
                    // Показываем уведомление
                    showNotification('Приёмка успешно сохранена', 'success');
                    
                    // Если это новая приёмка или нужно закрыть вкладку после сохранения
                    if (closeAfterSave) {
                        // Закрываем текущую вкладку
                        cancelChanges();
                    } else if (rid === 0) {
                        // Получаем ID созданной приёмки
                        const newId = response.receipt_id;
                        if (newId > 0) {
                            // Закрываем текущую вкладку
                            cancelChanges();
                            
                            // Открываем новую вкладку с созданной приёмкой
                            openReceiptEditTab(newId);
                        }
                    }
                } else {
                    alert('Ошибка: ' + response.message);
                }
            } catch (e) {
                // Для обратной совместимости с текстовым ответом "OK"
                if (resp === 'OK') {
                    // Обновляем все списки приёмок
                    updateReceiptList();
                    
                    // Показываем уведомление
                    showNotification('Приёмка успешно сохранена', 'success');
                    
                    // Если это новая приёмка или нужно закрыть вкладку после сохранения
                    if (closeAfterSave) {
                        // Закрываем текущую вкладку
                        cancelChanges();
                    } else if (rid === 0) {
                        // Получаем ID созданной приёмки
                        $.get('/crm/modules/purchases/receipts/api_handler.php', { action: 'get_last_receipt_id' }, function(newId) {
                            if (newId > 0) {
                                // Закрываем текущую вкладку
                                cancelChanges();
                                
                                // Открываем новую вкладку с созданной приёмкой
                                openReceiptEditTab(newId);
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
</script>