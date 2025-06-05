<?php
// /crm/modules/purchases/returns/edit_partial.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'purchases_returns')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

$id = (int)($_GET['id'] ?? 0);
$based_on = $_GET['based_on'] ?? '';
$purchase_order_id = (int)($_GET['purchase_order_id'] ?? 0);
$receipt_id = (int)($_GET['receipt_id'] ?? 0);

// Проверка режима работы (в отдельной вкладке)
$tabMode = isset($_GET['tab']) && $_GET['tab'] == 1;

// Значения по умолчанию
$return_number = '';
$return_date = date('Y-m-d H:i:s');
$warehouse_id = null;
$loader_id = null;
$reason = '';
$notes = '';
$status = 'new';
$conducted = 0;

// Загружаем данные возврата если ID > 0
if ($id > 0) {
    $st = $conn->prepare("SELECT * FROM PCRM_SupplierReturnHeader WHERE id=?");
    $st->bind_param("i", $id);
    $st->execute();
    $res = $st->get_result();
    $r = $res->fetch_assoc();
    if ($r) {
        $return_number = $r['return_number'] ?? '';
        $return_date = $r['return_date'];
        $purchase_order_id = $r['purchase_order_id'];
        $warehouse_id = $r['warehouse_id'];
        $loader_id = $r['loader_id'];
        $reason = $r['reason'];
        $notes = $r['notes'] ?? '';
        $status = $r['status'];
        $conducted = $r['conducted'];
    } else {
        die("<div class='text-danger'>Возврат не найден</div>");
    }
}
// Если передан ID заказа поставщику и создаем новый возврат, заполняем данные из заказа
elseif ($purchase_order_id > 0 && $id == 0 && $based_on == 'purchase_order') {
    $o_st = $conn->prepare("SELECT warehouse_id FROM PCRM_PurchaseOrder WHERE id=? AND deleted=0");
    $o_st->bind_param("i", $purchase_order_id);
    $o_st->execute();
    $o_res = $o_st->get_result();
    $order = $o_res->fetch_assoc();
    
    if ($order) {
        $warehouse_id = $order['warehouse_id'];
    }
}
// Если передан ID приёмки и создаем новый возврат, заполняем данные из приёмки
elseif ($receipt_id > 0 && $id == 0 && $based_on == 'receipt') {
    $r_st = $conn->prepare("SELECT warehouse_id, purchase_order_id, loader_id FROM PCRM_ReceiptHeader WHERE id=?");
    $r_st->bind_param("i", $receipt_id);
    $r_st->execute();
    $r_res = $r_st->get_result();
    $receipt = $r_res->fetch_assoc();
    
    if ($receipt) {
        $warehouse_id = $receipt['warehouse_id'];
        $purchase_order_id = $receipt['purchase_order_id'];
        $loader_id = $receipt['loader_id'];
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
$prodRes = $conn->query("SELECT id, name, cost_price FROM PCRM_Product WHERE status='active' ORDER BY name");
$allProducts = $prodRes->fetch_all(MYSQLI_ASSOC);

// Загружаем позиции возврата, если редактируем существующий
$items = [];
if ($id > 0) {
    $sqlItems = "
        SELECT sri.*, p.name AS product_name, p.cost_price AS default_price
        FROM PCRM_SupplierReturnItem sri
        LEFT JOIN PCRM_Product p ON sri.product_id = p.id
        WHERE sri.return_id = ?
        ORDER BY sri.id ASC
    ";
    $st2 = $conn->prepare($sqlItems);
    $st2->bind_param("i", $id);
    $st2->execute();
    $r2 = $st2->get_result();
    $items = $r2->fetch_all(MYSQLI_ASSOC);
}
// Если создаем на основе заказа поставщику, предзаполняем товары из заказа
elseif ($purchase_order_id > 0 && $id == 0 && $based_on == 'purchase_order') {
    $sqlOrderItems = "
        SELECT poi.product_id, poi.quantity, poi.price, poi.discount, p.name AS product_name
        FROM PCRM_PurchaseOrderItem poi
        LEFT JOIN PCRM_Product p ON poi.product_id = p.id
        WHERE poi.purchase_order_id = ?
        ORDER BY poi.id ASC
    ";
    $st3 = $conn->prepare($sqlOrderItems);
    $st3->bind_param("i", $purchase_order_id);
    $st3->execute();
    $r3 = $st3->get_result();
    $items = $r3->fetch_all(MYSQLI_ASSOC);
}
// Если создаем на основе приёмки, предзаполняем товары из приёмки
elseif ($receipt_id > 0 && $id == 0 && $based_on == 'receipt') {
    $sqlReceiptItems = "
        SELECT ri.product_id, ri.quantity, ri.price, ri.discount, p.name AS product_name
        FROM PCRM_ReceiptItem ri
        LEFT JOIN PCRM_Product p ON ri.product_id = p.id
        WHERE ri.receipt_header_id = ?
        ORDER BY ri.id ASC
    ";
    $st4 = $conn->prepare($sqlReceiptItems);
    $st4->bind_param("i", $receipt_id);
    $st4->execute();
    $r4 = $st4->get_result();
    $items = $r4->fetch_all(MYSQLI_ASSOC);
}

// Если это новый документ, генерируем номер
if (empty($return_number) && $id == 0) {
    $nextIdRes = $conn->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'PCRM_SupplierReturnHeader'");
    $nextId = $nextIdRes->fetch_row()[0] ?? 1;
    $return_number = 'SRET-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
}

// Рассчитываем общую сумму
$total_amount = 0;
foreach ($items as $item) {
    $total_amount += ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0);
}

// Уникальный идентификатор для этого экземпляра
$uniquePrefix = 'sret_' . preg_replace('/[^a-zA-Z0-9]/', '', uniqid('a', true));
?>
<div class="card">
  <div class="card-header">
    <?= ($id > 0 ? "Редактирование возврата поставщику #{$id}" : "Новый возврат поставщику") ?>
    <?php if ($based_on == 'purchase_order' && $purchase_order_id > 0): ?>
    (на основании заказа поставщику #<?= $purchase_order_id ?>)
    <?php elseif ($based_on == 'receipt' && $receipt_id > 0): ?>
    (на основании приёмки #<?= $receipt_id ?>)
    <?php endif; ?>
  </div>
  <div class="card-body">
    <div class="mb-3">
      <label>Номер возврата</label>
      <input type="text" id="sr-number" class="form-control" value="<?= htmlspecialchars($return_number) ?>">
    </div>
    <div class="mb-3">
      <label>Дата</label>
      <input type="datetime-local" id="sr-date" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($return_date)) ?>">
    </div>
    <div class="mb-3">
      <label>Заказ поставщику</label>
      <select id="sr-order" class="form-select" <?= ($purchase_order_id > 0 ? 'disabled' : '') ?>>
        <option value="">(не выбран)</option>
        <?php foreach ($allOrders as $o): ?>
        <option value="<?= $o['id'] ?>" <?= ($o['id'] == $purchase_order_id ? 'selected' : '') ?>>
          #<?= $o['id'] ?> (<?= htmlspecialchars($o['purchase_order_number']) ?>)
        </option>
        <?php endforeach; ?>
      </select>
      <?php if ($purchase_order_id > 0): ?>
      <input type="hidden" id="sr-order-hidden" value="<?= $purchase_order_id ?>">
      <?php endif; ?>
    </div>
    <div class="mb-3">
      <label>Склад</label>
      <select id="sr-warehouse" class="form-select required" required>
        <option value="">(не выбран)</option>
        <?php foreach ($allWarehouses as $w): ?>
        <option value="<?= $w['id'] ?>" <?= ($w['id'] == $warehouse_id ? 'selected' : '') ?>>
          <?= htmlspecialchars($w['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label>Грузчик</label>
      <select id="sr-loader" class="form-select required" required>
        <option value="">(не выбран)</option>
        <?php foreach ($allLoaders as $l): ?>
        <option value="<?= $l['id'] ?>" <?= ($l['id'] == $loader_id ? 'selected' : '') ?>>
          <?= htmlspecialchars($l['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label>Причина возврата</label>
      <select id="sr-reason" class="form-select required" required onchange="window['<?= $uniquePrefix ?>_checkOtherReason']()">
        <option value="">(не выбрана)</option>
        <option value="Брак" <?= ($reason === 'Брак' ? 'selected' : '') ?>>Брак</option>
        <option value="Лишнее" <?= ($reason === 'Лишнее' ? 'selected' : '') ?>>Лишнее</option>
        <option value="Не соответствует ожиданиям" <?= ($reason === 'Не соответствует ожиданиям' ? 'selected' : '') ?>>Не соответствует ожиданиям</option>
        <option value="Перепутал" <?= ($reason === 'Перепутал' ? 'selected' : '') ?>>Перепутал</option>
        <option value="Другое" <?= ($reason === 'Другое' ? 'selected' : '') ?>>Другое</option>
      </select>
    </div>
    <div class="mb-3">
      <label>Примечания <?= ($reason === 'Другое' ? '<span class="text-danger">*</span>' : '') ?></label>
      <textarea id="sr-notes" class="form-control <?= ($reason === 'Другое' ? 'required' : '') ?>" rows="2"><?= htmlspecialchars($notes) ?></textarea>
    </div>
    <div class="mb-3">
      <label>Статус</label>
      <select id="sr-status" class="form-select">
        <option value="new" <?= ($status == 'new' ? 'selected' : '') ?>>Новый</option>
        <option value="confirmed" <?= ($status == 'confirmed' ? 'selected' : '') ?>>Подтверждён</option>
        <option value="completed" <?= ($status == 'completed' ? 'selected' : '') ?>>Завершён</option>
        <option value="cancelled" <?= ($status == 'cancelled' ? 'selected' : '') ?>>Отменён</option>
      </select>
    </div>
    <!-- Слайдер проведения возврата поставщику -->
    <div class="mb-3">
      <!-- Скрытый чекбокс для совместимости -->
      <input class="form-check-input" type="checkbox" id="sr-conducted" <?= ($conducted == 1 ? 'checked' : '') ?> style="display: none;">
      <!-- Слайдер проведения -->
      <div class="conduct-slider-wrapper <?= ($conducted == 1 ? 'active' : '') ?>">
        <div class="conduct-slider <?= ($conducted == 1 ? 'active' : '') ?>" 
             id="sr-conducted-slider"
             data-checked="<?= ($conducted == 1 ? 'true' : 'false') ?>"
             data-original-checkbox="sr-conducted"
             tabindex="0"
             role="switch"
             aria-checked="<?= ($conducted == 1 ? 'true' : 'false') ?>"
             aria-label="Проведён">
        </div>
        <label class="conduct-slider-label" for="sr-conducted-slider">Проведён</label>
      </div>
    </div>
    
    <h5>Товары</h5>
    <table class="table table-sm table-bordered" id="sri-table">
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
            <div class="product-selector-container"></div>
          </td>
          <td><input type="number" step="0.001" class="form-control sri-qty" value="<?= $itm['quantity'] ?>"></td>
          <td><input type="number" step="0.01" class="form-control sri-price" value="<?= $itm['price'] ?>"></td>
          <td><input type="number" step="0.01" class="form-control sri-discount" value="<?= $itm['discount'] ?? 0 ?>"></td>
          <td class="sri-sum"></td>
          <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();window['<?= $uniquePrefix ?>_calcTotal']();">×</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <button class="btn btn-outline-primary btn-sm" onclick="window['<?= $uniquePrefix ?>_addItemRow']()">+ Добавить строку</button>
    <div class="mt-3">
      <label>Итого (руб.)</label>
      <input type="text" id="sr-total" class="form-control" readonly value="<?= number_format($total_amount, 2, '.', '') ?>">
    </div>
    
    <input type="hidden" id="sr-based-on" value="<?= htmlspecialchars($based_on) ?>">
    <input type="hidden" id="sr-receipt-id" value="<?= htmlspecialchars($receipt_id) ?>">
    
    <div class="mt-3">
      <button class="btn btn-success" onclick="window['<?= $uniquePrefix ?>_saveReturnWithPKO'](<?= $id ?>)">Сохранить, провести и создать ПКО</button>
      <button class="btn btn-success" onclick="window['<?= $uniquePrefix ?>_saveReturn'](<?= $id ?>)">Сохранить</button>
      
      <?php if ($id > 0): ?>
      <!-- Кнопка "Создать на основании" с выпадающим меню для возврата -->
      <div class="btn-group">
        <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          Создать на основании
        </button>
        <ul class="dropdown-menu">
          <li><a class="dropdown-item" href="#" onclick="createFinanceFromSupplierReturn(<?= $id ?>, 'income')">Приходная кассовая операция</a></li>
        </ul>
      </div>
      <?php endif; ?>
      
      <button class="btn btn-secondary" onclick="window['<?= $uniquePrefix ?>_cancelChanges']()">Отмена</button>
    </div>
    
    <?php
    // Включаем связанные документы, если редактируем существующий возврат
    if ($id > 0) {
        require_once __DIR__ . '/../../../includes/related_documents.php';
        showRelatedDocuments($conn, 'supplier_return', $id);
    }
    ?>
  </div>
</div>

<!-- Подключение общих JavaScript функций -->
<script src="/crm/js/common.js"></script>

<script>
// Используем анонимную функцию для создания локальной области видимости
(function() {
    // Создаем локальные переменные, недоступные извне этой функции
    const ALL_PRODUCTS = <?= json_encode($allProducts, JSON_UNESCAPED_UNICODE) ?>;
    
    // ID текущей вкладки (для закрытия)
    let currentTabId = '';
    let currentTabContentId = '';

    // Регистрируем функции в глобальной области видимости с уникальными именами
    window['<?= $uniquePrefix ?>_checkOtherReason'] = checkOtherReason;
    window['<?= $uniquePrefix ?>_addItemRow'] = addItemRow;
    window['<?= $uniquePrefix ?>_addItemRowWithData'] = addItemRowWithData;
    window['<?= $uniquePrefix ?>_calcTotal'] = calcTotal;
    window['<?= $uniquePrefix ?>_saveReturnWithPKO'] = saveReturnWithPKO;
    window['<?= $uniquePrefix ?>_saveReturn'] = saveReturn;
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
      $('#sr-order').change(function() {
        const orderId = $(this).val();
        if (orderId) {
          // Загружаем данные заказа поставщику
          $.getJSON('/crm/modules/purchases/orders/order_api.php', { 
            action: 'get_order_info',
            id: orderId 
          }, function(response) {
            if (response.status === 'ok') {
              // Заполняем склад из заказа
              $('#sr-warehouse').val(response.data.warehouse_id);
            }
          });
          
          // Очищаем таблицу товаров
          $('#sri-table tbody').empty();
          
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
      
      // Проверяем текущую причину возврата
      checkOtherReason();
      
      // Обработчик изменения товаров в таблице
      $('#sri-table').on('change', '.sri-product, .sri-qty, .sri-price, .sri-discount', function(){
        if ($(this).hasClass('sri-product')) {
          let priceInput = $(this).closest('tr').find('.sri-price');
          let currentVal = parseFloat(priceInput.val()) || 0;
          if (currentVal === 0) {
            let sel = $(this).find(':selected');
            let autoPrice = parseFloat(sel.attr('data-price')) || 0;
            priceInput.val(autoPrice.toFixed(2));
          }
        }
        calcTotal();
      });
      
      // Инициализация слайдера проведения
      if (typeof window.initAllConductSliders === 'function') {
        window.initAllConductSliders();
      }
      
      // Синхронизация слайдера с чекбоксом
      $(document).on('click', '#sr-conducted-slider', function() {
        const isActive = $(this).hasClass('active');
        $('#sr-conducted').prop('checked', isActive).trigger('change');
        console.log('Слайдер проведения возврата поставщику:', isActive ? 'Включён' : 'Выключен');
      });
    });

    // Функция проверки причины возврата "Другое"
    function checkOtherReason() {
      const reason = $('#sr-reason').val();
      if (reason === 'Другое') {
        $('#sr-notes').addClass('required').attr('required', 'required');
      } else {
        $('#sr-notes').removeClass('required').removeAttr('required');
      }
    }

    // Добавление пустой строки товара
    function addItemRow() {
      let rowHtml = `
        <tr>
          <td>
            <div class="product-selector-container"></div>
          </td>
          <td><input type="number" step="0.001" class="form-control sri-qty" value="1"></td>
          <td><input type="number" step="0.01" class="form-control sri-price" value="0"></td>
          <td><input type="number" step="0.01" class="form-control sri-discount" value="0"></td>
          <td class="sri-sum"></td>
          <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();window['<?= $uniquePrefix ?>_calcTotal']();">×</button></td>
        </tr>
      `;
      $('#sri-table tbody').append(rowHtml);
      calcTotal();
    }

    // Добавление строки с данными
    function addItemRowWithData(item) {
      let rowHtml = `
        <tr>
          <td>
            <div class="product-selector-container"></div>
          </td>
          <td><input type="number" step="0.001" class="form-control sri-qty" value="${item.quantity}"></td>
          <td><input type="number" step="0.01" class="form-control sri-price" value="${item.price}"></td>
          <td><input type="number" step="0.01" class="form-control sri-discount" value="${item.discount || 0}"></td>
          <td class="sri-sum"></td>
          <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();window['<?= $uniquePrefix ?>_calcTotal']();">×</button></td>
        </tr>
      `;
      $('#sri-table tbody').append(rowHtml);
    }

    // Расчёт общей суммы
    function calcTotal() {
      let grand = 0;
      $('#sri-table tbody tr').each(function(){
        let qty = parseFloat($(this).find('.sri-qty').val()) || 0;
        let price = parseFloat($(this).find('.sri-price').val()) || 0;
        let discount = parseFloat($(this).find('.sri-discount').val()) || 0;
        let sum = (qty * price) - discount;
        $(this).find('.sri-sum').text(sum.toFixed(2));
        grand += sum;
      });
      $('#sr-total').val(grand.toFixed(2));
    }

    // Сохранить и создать ПКО
    function saveReturnWithPKO(rid) {
      // Устанавливаем флаг проведения
      $('#sr-conducted').prop('checked', true);
      // Сохраняем возврат с созданием ПКО
      saveReturn(rid, false, true);
    }

    // Сохранение возврата
    function saveReturn(rid, closeAfterSave = false, createPKO = false) {
      // Проверка обязательных полей
      let valid = true;
      
      // Проверка склада
      if (!$('#sr-warehouse').val()) {
        $('#sr-warehouse').addClass('is-invalid');
        valid = false;
      } else {
        $('#sr-warehouse').removeClass('is-invalid');
      }
      
      // Проверка грузчика
      if (!$('#sr-loader').val()) {
        $('#sr-loader').addClass('is-invalid');
        valid = false;
      } else {
        $('#sr-loader').removeClass('is-invalid');
      }
      
      // Проверка причины возврата
      if (!$('#sr-reason').val()) {
        $('#sr-reason').addClass('is-invalid');
        valid = false;
      } else {
        $('#sr-reason').removeClass('is-invalid');
      }
      
      // Если причина "Другое", проверяем заполнено ли примечание
      if ($('#sr-reason').val() === 'Другое' && !$('#sr-notes').val().trim()) {
        $('#sr-notes').addClass('is-invalid');
        valid = false;
      } else {
        $('#sr-notes').removeClass('is-invalid');
      }
      
      // Проверка наличия товаров
      const hasProducts = $('#sri-table tbody tr').length > 0 && 
                          $('#sri-table tbody tr').some(function() {
                            return $(this).find('.sri-product').val() !== '';
                          });
      
      if (!hasProducts) {
        alert('Добавьте хотя бы один товар в возврат');
        valid = false;
      }
      
      if (!valid) {
        return;
      }
      
      calcTotal();
      
      // Собираем данные для отправки
      let data = {
        id: rid,
        return_number: $('#sr-number').val(),
        return_date: $('#sr-date').val(),
        purchase_order_id: $('#sr-order-hidden').val() || $('#sr-order').val(),
        receipt_id: $('#sr-receipt-id').val(),
        warehouse_id: $('#sr-warehouse').val(),
        loader_id: $('#sr-loader').val(),
        reason: $('#sr-reason').val(),
        notes: $('#sr-notes').val(),
        status: $('#sr-status').val(),
        conducted: ($('#sr-conducted').is(':checked') ? 1 : 0),
        based_on: $('#sr-based-on').val()
      };

      // Собираем товары
      let items = [];
      $('#sri-table tbody tr').each(function(){
        let pid = $(this).find('.sri-product').val();
        if (!pid) return;
        let qty = parseFloat($(this).find('.sri-qty').val()) || 0;
        let prc = parseFloat($(this).find('.sri-price').val()) || 0;
        let dsc = parseFloat($(this).find('.sri-discount').val()) || 0;
        
        items.push({
          product_id: pid, 
          quantity: qty, 
          price: prc, 
          discount: dsc
        });
      });
      data.items = JSON.stringify(items);
      
      // Флаг для создания ПКО
      data.create_pko = createPKO ? 1 : 0;

      // Отправляем данные на сервер
      $.post('/crm/modules/purchases/returns/save.php', data, function(resp){
        try {
          const response = JSON.parse(resp);
          
          if (response.status === 'ok') {
            // Обновляем все списки возвратов
            updateSupplierReturnsList();
            
            // Показываем уведомление
            showNotification('Возврат поставщику успешно сохранен', 'success');
            
            // Если был создан ПКО, сообщаем об этом
            if (response.pko_created) {
              showNotification('ПКО успешно создан', 'success');
            }
            
            // Если это новый возврат или нужно закрыть вкладку после сохранения
            if (closeAfterSave) {
              // Закрываем текущую вкладку
              cancelChanges();
            } else if (rid === 0) {
              // Получаем ID созданного возврата
              const newId = response.return_id;
              if (newId > 0) {
                // Закрываем текущую вкладку
                cancelChanges();
                
                // Открываем новую вкладку с созданным возвратом
                openSupplierReturnEditTab(newId);
              }
            }
          } else {
            alert('Ошибка: ' + response.message);
          }
        } catch (e) {
          alert('Ошибка при сохранении: ' + resp);
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
    
    // Расширение для метода some в jQuery
    $.fn.some = function(callback) {
      for (let i = 0; i < this.length; i++) {
        if (callback.call(this[i], i, this[i])) {
          return true;
        }
      }
      return false;
    };
})();

// Функция инициализации выпадающих меню (глобальная)
function initDropdowns() {
  console.log('🔧 [PURCHASES/RETURNS] Инициализация dropdown кнопок...');
  
  // Проверяем наличие Bootstrap
  if (typeof bootstrap !== 'undefined') {
    console.log('✅ Bootstrap найден, используем стандартные dropdown');
    // Bootstrap 5 сам обрабатывает data-bs-toggle="dropdown"
    return;
  }
  
  console.log('⚠️ Bootstrap не найден, используем кастомные обработчики');
  
  // Кастомная обработка для кнопок с data-bs-toggle="dropdown"
  $('[data-bs-toggle="dropdown"], .dropdown-toggle').off('click.customDropdown').on('click.customDropdown', function(e) {
    console.log('👆 Клик по dropdown кнопке:', $(this).text().trim());
    
    const $button = $(this);
    const $menu = $button.next('.dropdown-menu').length > 0 
                  ? $button.next('.dropdown-menu') 
                  : $button.siblings('.dropdown-menu');
    const $container = $button.closest('.dropdown, .btn-group');
    
    console.log('📋 Найдено меню:', $menu.length > 0);
    console.log('📦 Найден контейнер:', $container.length > 0);
    
    // Закрываем все другие меню
    $('.dropdown, .btn-group').not($container).removeClass('show');
    $('.dropdown-menu').not($menu).removeClass('show').hide();
    
    // Переключаем текущее меню
    const isOpen = $container.hasClass('show');
    $container.toggleClass('show', !isOpen);
    $menu.toggleClass('show', !isOpen);
    
    if (!isOpen) {
      $menu.show();
      console.log('🟢 Меню открыто');
    } else {
      $menu.hide();
      console.log('🔴 Меню закрыто');
    }
    
    // Обновляем aria-expanded
    $button.attr('aria-expanded', !isOpen);
    
    // Предотвращаем всплытие
    e.preventDefault();
    e.stopPropagation();
    
    return false;
  });
  
  // Закрытие при клике вне меню
  $(document).off('click.customDropdown').on('click.customDropdown', function(e) {
    if (!$(e.target).closest('.dropdown, .btn-group').length) {
      $('.dropdown, .btn-group').removeClass('show');
      $('.dropdown-menu').removeClass('show').hide();
      $('[data-bs-toggle="dropdown"], .dropdown-toggle').attr('aria-expanded', 'false');
    }
  });
  
  // Предотвращаем закрытие при клике на элементы меню
  $('.dropdown-menu').off('click.customDropdown').on('click.customDropdown', function(e) {
    e.stopPropagation();
  });
  
  console.log('✅ Кастомные dropdown обработчики установлены');
}

// Вызываем инициализацию после загрузки
$(document).ready(function() {
  console.log('📄 [PURCHASES/RETURNS] Документ загружен, инициализируем dropdown...');
  setTimeout(function() {
    initDropdowns();
  }, 100);
});
</script>