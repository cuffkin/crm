<?php
// /crm/modules/purchases/orders/edit_partial.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'purchases_orders')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

$id = (int)($_GET['id'] ?? 0);
// Проверка режима работы (в отдельной вкладке)
$tabMode = isset($_GET['tab']) && $_GET['tab'] == 1;

$organization     = null;
$purchase_order_number = '';
$date             = date('Y-m-d H:i:s');
$supplier_id      = null;
$warehouse_id     = null;
$delivery_address = '';
$comment          = '';
$status           = 'draft';
$total_amount     = '0.00';
$conducted        = 0; // 0 = неактивен, 1 = активен но не проведён, 2 = проведён

if ($id > 0) {
    $st = $conn->prepare("SELECT * FROM PCRM_PurchaseOrder WHERE id=? AND deleted=0");
    $st->bind_param("i", $id);
    $st->execute();
    $res = $st->get_result();
    $order = $res->fetch_assoc();
    if ($order) {
        $organization     = $order['organization'];
        $purchase_order_number = $order['purchase_order_number'];
        $date             = $order['date'];
        $supplier_id      = $order['supplier_id'];
        $warehouse_id     = $order['warehouse_id'];
        $delivery_address = $order['delivery_address'];
        $comment          = $order['comment'];
        $status           = $order['status'];
        $total_amount     = $order['total_amount'];
        $conducted        = $order['conducted'];
    } else {
        die("<div class='text-danger'>Заказ не найден</div>");
    }
}

// Справочники
$orgRes = $conn->query("SELECT id,name FROM PCRM_Organization ORDER BY name");
$allOrgs = $orgRes->fetch_all(MYSQLI_ASSOC);

$supplierRes = $conn->query("SELECT id,name,type FROM PCRM_Counterparty ORDER BY name");
$allSuppliers = $supplierRes->fetch_all(MYSQLI_ASSOC);

$whRes = $conn->query("SELECT id,name FROM PCRM_Warehouse WHERE status='active' ORDER BY name");
$allWh = $whRes->fetch_all(MYSQLI_ASSOC);

$prodRes = $conn->query("SELECT id, name, price FROM PCRM_Product WHERE status='active' ORDER BY name");
$allProducts = $prodRes->fetch_all(MYSQLI_ASSOC);

$items = [];
if ($id > 0) {
    $sqlItems = "
      SELECT i.*, p.name AS product_name, p.cost_price AS default_price
      FROM PCRM_PurchaseOrderItem i
      LEFT JOIN PCRM_Product p ON i.product_id = p.id
      WHERE i.purchase_order_id = ?
      ORDER BY i.id ASC
    ";
    $st2 = $conn->prepare($sqlItems);
    $st2->bind_param("i", $id);
    $st2->execute();
    $r2 = $st2->get_result();
    $items = $r2->fetch_all(MYSQLI_ASSOC);
}

// Уникальный идентификатор для этого экземпляра редактирования заказа
$uniquePrefix = 'po_' . preg_replace('/[^a-zA-Z0-9]/', '', uniqid('a', true));
?>
<div class="card">
  <div class="card-header">
    <?= ($id > 0 ? "Редактирование заказа поставщику #{$id}" : "Новый заказ поставщику") ?>
  </div>
  <div class="card-body">
    <div class="mb-3">
      <label>Организация <span class="text-danger">*</span></label>
      <select id="po-org" class="form-select required" required>
        <option value="">(не выбрано)</option>
        <?php foreach ($allOrgs as $org): ?>
        <option value="<?= $org['id'] ?>" <?= ($org['id'] == $organization ? 'selected' : '') ?>>
          <?= htmlspecialchars($org['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label>Номер</label>
      <input type="text" id="po-num" class="form-control" value="<?= htmlspecialchars($purchase_order_number) ?>">
    </div>
    <div class="mb-3">
      <label>Дата</label>
      <input type="datetime-local" id="po-date" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($date)) ?>">
    </div>
    <div class="mb-3">
      <label>Поставщик <span class="text-danger">*</span></label>
      <div class="input-group">
        <select id="po-supplier" class="form-select required" required>
          <option value="">(не выбран)</option>
          <?php foreach ($allSuppliers as $supplier): ?>
          <option value="<?= $supplier['id'] ?>" <?= ($supplier['id'] == $supplier_id ? 'selected' : '') ?>>
            <?= htmlspecialchars($supplier['name']) ?> (<?= htmlspecialchars($supplier['type']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-secondary" type="button" onclick="openNewTab('counterparty/edit_partial')">Создать нового</button>
      </div>
    </div>
    <div class="mb-3">
      <label>Склад <span class="text-danger">*</span></label>
      <select id="po-wh" class="form-select required" required>
        <option value="">(не выбран)</option>
        <?php foreach ($allWh as $w): ?>
        <option value="<?= $w['id'] ?>" <?= ($w['id'] == $warehouse_id ? 'selected' : '') ?>>
          <?= htmlspecialchars($w['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label>Адрес доставки</label>
      <input type="text" id="po-delivery" class="form-control" value="<?= htmlspecialchars($delivery_address) ?>">
    </div>
    <div class="mb-3">
      <label>Комментарий</label>
      <textarea id="po-comment" class="form-control" rows="2"><?= htmlspecialchars($comment) ?></textarea>
    </div>
    <div class="mb-3">
      <label>Статус</label>
      <select id="po-status" class="form-select">
        <option value="draft" <?= ($status == 'draft' ? 'selected' : '') ?>>Черновик</option>
        <option value="new" <?= ($status == 'new' ? 'selected' : '') ?>>Новый</option>
        <option value="confirmed" <?= ($status == 'confirmed' ? 'selected' : '') ?>>Подтверждён</option>
        <option value="processing" <?= ($status == 'processing' ? 'selected' : '') ?>>В обработке</option>
        <option value="completed" <?= ($status == 'completed' ? 'selected' : '') ?>>Завершён</option>
        <option value="cancelled" <?= ($status == 'cancelled' ? 'selected' : '') ?>>Отменён</option>
      </select>
    </div>
    <!-- Слайдер проведения заказа поставщику -->
    <div class="mb-3">
      <!-- Скрытый чекбокс для совместимости -->
      <input class="form-check-input" type="checkbox" id="po-conducted" <?= ($conducted == 1 ? 'checked' : '') ?> style="display: none;">
      <!-- Слайдер проведения -->
      <div class="conduct-slider-wrapper <?= ($conducted == 1 ? 'active' : '') ?>">
        <div class="conduct-slider <?= ($conducted == 1 ? 'active' : '') ?>" 
             id="po-conducted-slider"
             data-checked="<?= ($conducted == 1 ? 'true' : 'false') ?>"
             data-original-checkbox="po-conducted"
             tabindex="0"
             role="switch"
             aria-checked="<?= ($conducted == 1 ? 'true' : 'false') ?>"
             aria-label="Проведён">
        </div>
        <label class="conduct-slider-label" for="po-conducted-slider">Проведён</label>
      </div>
    </div>
    <h5>Товары</h5>
    <table class="table table-sm table-bordered" id="poi-table">
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
          <td><input type="number" step="0.001" class="form-control poi-qty" value="<?= $itm['quantity'] ?>"></td>
          <td><input type="number" step="0.01" class="form-control poi-price" value="<?= $itm['price'] ?>"></td>
          <td><input type="number" step="0.01" class="form-control poi-discount" value="<?= $itm['discount'] ?>"></td>
          <td class="poi-sum"></td>
          <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();window['<?= $uniquePrefix ?>_calcTotal']();">×</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <button class="btn btn-outline-primary btn-sm" onclick="window['<?= $uniquePrefix ?>_addRow']()">+ Добавить строку</button>
    <div class="mt-3">
      <label>Итого (руб.)</label>
      <input type="text" id="po-total" class="form-control" readonly value="<?= $total_amount ?>">
    </div>
    <div class="mt-3">
      <button class="btn btn-success" onclick="window['<?= $uniquePrefix ?>_saveOrderAndClose'](<?= $id ?>)">Сохранить и закрыть</button>
      <button class="btn btn-success" onclick="window['<?= $uniquePrefix ?>_saveOrder'](<?= $id ?>)">Сохранить</button>
      
      <?php if ($id > 0): ?>
      <!-- Кнопка "Создать на основании" с выпадающим меню -->
      <div class="btn-group">
        <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          Создать на основании
        </button>
        <ul class="dropdown-menu">
          <li><a class="dropdown-item" href="#" onclick="createReceiptFromPurchaseOrder(<?= $id ?>)">Приёмка</a></li>
          <li><a class="dropdown-item" href="#" onclick="createFinanceFromPurchaseOrder(<?= $id ?>)">Расходная кассовая операция</a></li>
          <li><a class="dropdown-item" href="#" onclick="createSupplierReturnFromPurchaseOrder(<?= $id ?>)">Возврат поставщику</a></li>
        </ul>
      </div>
      <?php endif; ?>
      
      <button class="btn btn-secondary" onclick="window['<?= $uniquePrefix ?>_cancelChanges']()">Отмена</button>
    </div>
    
    <?php
    // Включаем связанные документы, если редактируем существующий заказ
    if ($id > 0) {
        require_once __DIR__ . '/../../../includes/related_documents.php';
        showRelatedDocuments($conn, 'purchase_order', $id);
    }
    ?>
  </div>
</div>

<!-- Подключение общих JavaScript функций -->
<script src="/crm/js/common.js"></script>

<script>
console.log('🟢 МОДУЛЬ ЗАКАЗОВ ПОСТАВЩИКАМ: Скрипт начал загружаться');
console.log('🔍 DIAGNOSTIC: uniquePrefix =', '<?= $uniquePrefix ?>');

// Используем анонимную функцию для создания локальной области видимости
(function() {
    // Создаем локальные переменные, недоступные извне этой функции
    const ALL_PRODUCTS = <?= json_encode($allProducts, JSON_UNESCAPED_UNICODE) ?>;
    
    // ID текущей вкладки (для закрытия)
    let currentTabId = '';
    let currentTabContentId = '';

    // Регистрируем функции в глобальной области видимости с уникальными именами
    window['<?= $uniquePrefix ?>_addRow'] = addRow;
    window['<?= $uniquePrefix ?>_calcTotal'] = calcTotal;
    window['<?= $uniquePrefix ?>_saveOrderAndClose'] = saveOrderAndClose;
    window['<?= $uniquePrefix ?>_saveOrder'] = saveOrder;
    window['<?= $uniquePrefix ?>_cancelChanges'] = cancelChanges;

    // Функция проверки наличия товаров в таблице
    $.fn.some = function(callback) {
      for (let i = 0; i < this.length; i++) {
        if (callback.call(this[i], i, this[i])) {
          return true;
        }
      }
      return false;
    };

    $(document).ready(function(){
      calcTotal();
      
      // Более надежный способ получения ID текущей вкладки
      // Получаем ID вкладки из URL параметра или ищем активную вкладку
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
      
      // Если это новый заказ, добавляем строку товара автоматически
      if (<?= $id ?> === 0 && $('#poi-table tbody tr').length === 0) {
        window['<?= $uniquePrefix ?>_addRow']();
      }
      
      // Если это новый заказ, генерируем номер автоматически
      if (<?= $id ?> === 0 && $('#po-num').val() === '') {
        $.getJSON('/crm/modules/purchases/orders/order_api.php', { action: 'generate' }, function(response) {
          if (response.status === 'ok') {
            $('#po-num').val(response.number);
          }
        });
      }
      
      // Если это новый заказ, автоматически заполняем организацию и склад
      if (<?= $id ?> === 0) {
        // Автоматически выбираем первую организацию, если ни одна не выбрана
        if ($('#po-org').val() === '') {
          const firstOrg = $('#po-org option:not(:first)').first();
          if (firstOrg.length) {
            $('#po-org').val(firstOrg.val());
          }
        }
        
        // Автоматически выбираем первый склад, если ни один не выбран
        if ($('#po-wh').val() === '') {
          const firstWh = $('#po-wh option:not(:first)').first();
          if (firstWh.length) {
            $('#po-wh').val(firstWh.val());
          }
        }
      }
      
      // Проверка уникальности номера заказа
      $('#po-num').on('change', function() {
        const number = $(this).val().trim();
        if (number === '') {
          alert('Номер заказа не может быть пустым');
          // Генерируем новый номер
          $.getJSON('/crm/modules/purchases/orders/order_api.php', { action: 'generate' }, function(response) {
            if (response.status === 'ok') {
              $('#po-num').val(response.number);
            }
          });
          return;
        }
        
        // Проверяем уникальность номера
        $.getJSON('/crm/modules/purchases/orders/order_api.php', {
          action: 'check',
          number: number,
          id: <?= $id ?>
        }, function(response) {
          if (response.status === 'error') {
            alert(response.message);
            // Генерируем новый номер
            $.getJSON('/crm/modules/purchases/orders/order_api.php', { action: 'generate' }, function(generatedResponse) {
              if (generatedResponse.status === 'ok') {
                $('#po-num').val(generatedResponse.number);
              }
            });
          }
        });
      });
      
      // Обработчики для очистки валидации при изменении полей
      $('#po-org').on('change', function() {
        $(this).removeClass('is-invalid');
      });
      
      $('#po-supplier').on('change', function() {
        $(this).removeClass('is-invalid');
      });
      
      $('#po-wh').on('change', function() {
        $(this).removeClass('is-invalid');
      });
      
      // Обработчик изменения товаров в таблице - ОБНОВЛЕН
      $('#poi-table').on('change', '.poi-qty, .poi-price, .poi-discount', function(){
        calcTotal();
      });
      
      // Инициализируем Product Selector для существующих строк
      $('#poi-table .product-selector-container').each(function() {
        const $container = $(this);
        const $row = $container.closest('tr');
        
        const productSelector = createProductSelector(this, {
          context: 'purchase',
          onSelect: function(product) {
            // Автозаполнение цены
            const $priceInput = $row.find('.poi-price');
            if (parseFloat($priceInput.val()) === 0) {
              $priceInput.val(parseFloat(product.cost_price || 0).toFixed(2));
            }
            
            calcTotal();
          },
          onClear: function() {
            calcTotal();
          }
        });
        
        // Устанавливаем выбранный товар если есть
        <?php foreach ($items as $itm): ?>
        if ($row.index() === <?= array_search($itm, $items) ?> && <?= $itm['product_id'] ?>) {
          // Находим товар по ID и устанавливаем его
          const productId = <?= $itm['product_id'] ?>;
          const product = ALL_PRODUCTS.find(p => p.id == productId);
          if (product) {
            productSelector.setProduct(product);
          }
        }
        <?php endforeach; ?>
      });
      
      // Инициализация слайдера проведения
      if (typeof window.initAllConductSliders === 'function') {
        window.initAllConductSliders();
      }
      
      // Синхронизация слайдера с чекбоксом
      $(document).on('click', '#po-conducted-slider', function() {
        const isActive = $(this).hasClass('active');
        $('#po-conducted').prop('checked', isActive).trigger('change');
        console.log('Слайдер проведения заказа поставщику:', isActive ? 'Включён' : 'Выключен');
      });
    });

    function addRow() {
      let rowHtml = `
        <tr>
          <td>
            <div class="product-selector-container"></div>
          </td>
          <td><input type="number" step="0.001" class="form-control poi-qty" value="1"></td>
          <td><input type="number" step="0.01" class="form-control poi-price" value="0"></td>
          <td><input type="number" step="0.01" class="form-control poi-discount" value="0"></td>
          <td class="poi-sum"></td>
          <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();window['<?= $uniquePrefix ?>_calcTotal']();">×</button></td>
        </tr>
      `;
      const $newRow = $(rowHtml);
      $('#poi-table tbody').append($newRow);
      
      // Инициализируем Product Selector для новой строки
      const $container = $newRow.find('.product-selector-container');
      const productSelector = createProductSelector($container[0], {
        context: 'purchase',
        onSelect: function(product) {
          const $row = $container.closest('tr');
          
          // Автозаполнение цены
          const $priceInput = $row.find('.poi-price');
          if (parseFloat($priceInput.val()) === 0) {
            $priceInput.val(parseFloat(product.cost_price || 0).toFixed(2));
          }
          
          calcTotal();
        },
        onClear: function() {
          calcTotal();
        }
      });
      
      calcTotal();
    }

    function calcTotal() {
      let grand = 0;
      $('#poi-table tbody tr').each(function(){
        let qty = parseFloat($(this).find('.poi-qty').val()) || 0;
        let price = parseFloat($(this).find('.poi-price').val()) || 0;
        let discount = parseFloat($(this).find('.poi-discount').val()) || 0;
        let sum = (qty * price) - discount;
        $(this).find('.poi-sum').text(sum.toFixed(2));
        grand += sum;
      });
      $('#po-total').val(grand.toFixed(2));
    }

    function saveOrderAndClose(oid) {
      console.log('🟢 [PURCHASE_ORDER] saveOrderAndClose вызвана, oid =', oid);
      try {
        // Сохранить и закрыть вкладку
        saveOrder(oid, true);
      } catch (e) {
        console.error('❌ [PURCHASE_ORDER] Ошибка в saveOrderAndClose:', e);
        alert('Ошибка при сохранении заказа: ' + e.message);
      }
    }

    function saveOrder(oid, closeAfterSave = false) {
      console.log('🟢 [PURCHASE_ORDER] saveOrder вызвана, oid =', oid, 'closeAfterSave =', closeAfterSave);
      
      try {
        // Проверка обязательных полей
        let valid = true;
        
        console.log('🔍 [PURCHASE_ORDER] Проверка валидации полей...');
        
        // Проверка организации
        if (!$('#po-org').val()) {
          console.log('❌ [PURCHASE_ORDER] Организация не выбрана');
          $('#po-org').addClass('is-invalid');
          valid = false;
        } else {
          $('#po-org').removeClass('is-invalid');
        }
        
        // Проверка поставщика
        if (!$('#po-supplier').val()) {
          console.log('❌ [PURCHASE_ORDER] Поставщик не выбран');
          $('#po-supplier').addClass('is-invalid');
          valid = false;
        } else {
          $('#po-supplier').removeClass('is-invalid');
        }
        
        // Проверка склада
        if (!$('#po-wh').val()) {
          console.log('❌ [PURCHASE_ORDER] Склад не выбран');
          $('#po-wh').addClass('is-invalid');
          valid = false;
        } else {
          $('#po-wh').removeClass('is-invalid');
        }
        
        // Проверка наличия товаров - ОБНОВЛЕНА
        const hasProducts = $('#poi-table tbody tr').length > 0 && 
                            $('#poi-table tbody tr').some(function() {
                              const selector = $(this).find('.product-selector-container')[0];
                              if (selector && selector.productSelector) {
                                const product = selector.productSelector.getSelectedProduct();
                                return product && product.id;
                              }
                              return false;
                            });
        
        if (!hasProducts) {
          console.log('❌ [PURCHASE_ORDER] Нет товаров в заказе');
          alert('Добавьте хотя бы один товар в заказ');
          valid = false;
        }
        
        if (!valid) {
          console.log('❌ [PURCHASE_ORDER] Валидация не пройдена, прерываем сохранение');
          return;
        }
        
        console.log('✅ [PURCHASE_ORDER] Валидация пройдена, собираем данные...');
        
        calcTotal();
        let data = {
          id: oid,
          organization:     $('#po-org').val(),
          purchase_order_number: $('#po-num').val(),
          date:             $('#po-date').val(),
          supplier_id:      $('#po-supplier').val(),
          warehouse_id:     $('#po-wh').val(),
          delivery_address: $('#po-delivery').val(),
          comment:          $('#po-comment').val(),
          status:           $('#po-status').val(),
          total_amount:     $('#po-total').val(),
          conducted:        ($('#po-conducted').is(':checked') ? 1 : 0)
        };

        let items = [];
        $('#poi-table tbody tr').each(function(){
          const $container = $(this).find('.product-selector-container');
          const selector = $container[0];
          let pid = null;
          
          if (selector && selector.productSelector) {
            const product = selector.productSelector.getSelectedProduct();
            pid = product ? product.id : null;
          }
          
          if (!pid) return;
          let qty = parseFloat($(this).find('.poi-qty').val()) || 0;
          let prc = parseFloat($(this).find('.poi-price').val()) || 0;
          let dsc = parseFloat($(this).find('.poi-discount').val()) || 0;
          items.push({product_id: pid, quantity: qty, price: prc, discount: dsc});
        });
        data.items = JSON.stringify(items);

        console.log('📋 [PURCHASE_ORDER] Отправляем данные на сервер:', data);

        $.post('/crm/modules/purchases/orders/save.php', data, function(resp){
          console.log('📥 [PURCHASE_ORDER] Ответ сервера:', resp);
          
          if (resp === 'OK') {
            console.log('✅ [PURCHASE_ORDER] Заказ успешно сохранен');
            
            // Сбрасываем флаги изменений после успешного сохранения
            if (typeof window.resetFormChangeFlags === 'function') {
              console.log('🔄 [PURCHASE_ORDER] Сбрасываем флаги изменений...');
              window.resetFormChangeFlags(currentTabContentId);
            } else {
              console.warn('⚠️ [PURCHASE_ORDER] Функция resetFormChangeFlags не найдена');
            }
            
            // Обновляем все списки заказов в других вкладках
            if (typeof updatePurchaseOrderLists === 'function') {
              console.log('🔄 [PURCHASE_ORDER] Обновляем списки заказов...');
              updatePurchaseOrderLists();
            } else {
              console.warn('⚠️ [PURCHASE_ORDER] Функция updatePurchaseOrderLists не найдена');
            }
            
            // Показываем уведомление
            if (typeof showNotification === 'function') {
              showNotification('Заказ поставщику успешно сохранен', 'success');
            } else {
              console.warn('⚠️ [PURCHASE_ORDER] Функция showNotification не найдена');
            }
            
            // Если это новый заказ или нужно закрыть вкладку после сохранения
            if (closeAfterSave) {
              console.log('🚪 [PURCHASE_ORDER] Закрываем вкладку после сохранения...');
              // Закрываем текущую вкладку
              cancelChanges();
            } else if (oid === 0) {
              console.log('🆕 [PURCHASE_ORDER] Новый заказ создан, обновляем вкладку...');
              // Получаем ID созданного заказа
              $.get('/crm/modules/purchases/orders/order_api.php', { action: 'get_last_id' }, function(newId) {
                console.log('📋 [PURCHASE_ORDER] Получен ID нового заказа:', newId);
                if (newId > 0) {
                  // Получаем номер заказа
                  const orderNumber = $('#po-num').val();
                  
                  // Закрываем текущую вкладку
                  cancelChanges();
                  
                  // Открываем новую вкладку с созданным заказом
                  if (typeof openPurchaseOrderEditTab === 'function') {
                    openPurchaseOrderEditTab(newId, orderNumber);
                  } else {
                    console.error('❌ [PURCHASE_ORDER] Функция openPurchaseOrderEditTab не найдена');
                  }
                }
              }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('❌ [PURCHASE_ORDER] Ошибка при получении ID нового заказа:', textStatus, errorThrown);
              });
            } else {
              console.log('🔄 [PURCHASE_ORDER] Обновляем заголовок существующего заказа...');
              // Обновляем заголовок вкладки для существующего заказа
              const orderNumber = $('#po-num').val();
              if (currentTabId) {
                $(`#${currentTabId}`).html(`Заказ поставщику ${orderNumber} <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>`);
                
                // Восстанавливаем обработчик закрытия
                $(`#${currentTabId} .btn-close`).on('click', function(e) {
                  e.stopPropagation();
                  if (typeof closeModuleTab === 'function') {
                    closeModuleTab(currentTabId, currentTabContentId);
                  } else {
                    console.error('❌ [PURCHASE_ORDER] Функция closeModuleTab не найдена');
                  }
                });
              }
            }
          } else {
            console.error('❌ [PURCHASE_ORDER] Ошибка сервера:', resp);
            alert(resp);
          }
        }).fail(function(jqXHR, textStatus, errorThrown) {
          console.error('❌ [PURCHASE_ORDER] Ошибка AJAX запроса:', textStatus, errorThrown);
          console.error('❌ [PURCHASE_ORDER] Детали ошибки:', jqXHR.responseText);
          alert('Ошибка при сохранении заказа: ' + textStatus);
        });
        
      } catch (e) {
        console.error('❌ [PURCHASE_ORDER] Критическая ошибка в saveOrder:', e);
        alert('Критическая ошибка при сохранении заказа: ' + e.message);
      }
    }

    function cancelChanges() {
      console.log('🚪 [PURCHASE_ORDER] cancelChanges вызвана');
      console.log('🔍 [PURCHASE_ORDER] currentTabId =', currentTabId);
      console.log('🔍 [PURCHASE_ORDER] currentTabContentId =', currentTabContentId);
      
      try {
        // Получаем информацию о текущей вкладке из хранимых переменных
        if (currentTabId && currentTabContentId) {
          console.log('✅ [PURCHASE_ORDER] Есть ID вкладок, используем closeModuleTab');
          // Используем глобальную функцию closeModuleTab
          if (typeof closeModuleTab === 'function') {
            console.log('🚪 [PURCHASE_ORDER] Вызываем closeModuleTab');
            closeModuleTab(currentTabId, currentTabContentId);
          } else if (typeof forceCloseModuleTab === 'function') {
            console.log('🚪 [PURCHASE_ORDER] Вызываем forceCloseModuleTab');
            forceCloseModuleTab(currentTabId, currentTabContentId);
          } else {
            console.error('❌ [PURCHASE_ORDER] Функции закрытия вкладок не найдены');
          }
        } else {
          console.log('⚠️ [PURCHASE_ORDER] Нет ID вкладок, ищем активную вкладку');
          // Запасной вариант - ищем ближайшую родительскую вкладку
          const tabContent = $('.tab-pane.active');
          if (tabContent.length) {
            const contentId = tabContent.attr('id');
            const tabId = $('a[href="#' + contentId + '"]').attr('id');
            console.log('🔍 [PURCHASE_ORDER] Найдена активная вкладка:', contentId, tabId);
            if (contentId && tabId) {
              if (typeof closeModuleTab === 'function') {
                console.log('🚪 [PURCHASE_ORDER] Вызываем closeModuleTab для найденной вкладки');
                closeModuleTab(tabId, contentId);
              } else if (typeof forceCloseModuleTab === 'function') {
                console.log('🚪 [PURCHASE_ORDER] Вызываем forceCloseModuleTab для найденной вкладки');
                forceCloseModuleTab(tabId, contentId);
              } else {
                console.error('❌ [PURCHASE_ORDER] Функции закрытия вкладок не найдены');
              }
            }
          } else {
            console.error('❌ [PURCHASE_ORDER] Активная вкладка не найдена');
          }
        }
      } catch (e) {
        console.error('❌ [PURCHASE_ORDER] Ошибка в cancelChanges:', e);
      }
    }

    // Используем глобальную функцию openNewTab из common.js
    
})();

// Функция инициализации выпадающих меню (глобальная)
function initDropdowns() {
    console.log('🔧 Инициализация dropdown кнопок...');
    
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
    console.log('📄 Документ загружен, инициализируем dropdown...');
    console.log('🔍 ПРОВЕРКА: typeof initDropdowns =', typeof initDropdowns);
    console.log('🔍 ПРОВЕРКА: найдено dropdown кнопок =', $('[data-bs-toggle="dropdown"], .dropdown-toggle').length);
    
    // Задержка для убеждения что всё загрузилось
    setTimeout(function() {
        initDropdowns();
        
        // Дополнительная диагностика
        const dropdownButtons = $('[data-bs-toggle="dropdown"], .dropdown-toggle');
        console.log(`🔍 Найдено dropdown кнопок: ${dropdownButtons.length}`);
        
        dropdownButtons.each(function(i) {
            console.log(`   ${i+1}. "${$(this).text().trim()}" (${$(this).prop('tagName')})`);
        });
    }, 100);
});

// 🔧 ФУНКЦИЯ ДЛЯ РУЧНОЙ ДИАГНОСТИКИ ИЗ КОНСОЛИ
window.testDropdownButtons = function() {
    console.log('🔧 РУЧНАЯ ДИАГНОСТИКА DROPDOWN КНОПОК:');
    console.log('1. Bootstrap доступен:', typeof bootstrap !== 'undefined');
    console.log('2. jQuery доступен:', typeof $ !== 'undefined');
    console.log('3. initDropdowns доступна:', typeof initDropdowns !== 'undefined');
    
    const buttons = $('[data-bs-toggle="dropdown"], .dropdown-toggle');
    console.log('4. Найдено кнопок:', buttons.length);
    
    buttons.each(function(i) {
        const $btn = $(this);
        const $menu = $btn.next('.dropdown-menu').length > 0 ? $btn.next('.dropdown-menu') : $btn.siblings('.dropdown-menu');
        console.log(`   Кнопка ${i+1}: "${$btn.text().trim()}" - Меню найдено: ${$menu.length > 0}`);
        
        // Попробуем кликнуть программно
        console.log(`   Добавляем тестовый обработчик клика...`);
        $btn.off('click.test').on('click.test', function() {
            console.log(`   ✅ КЛИК СРАБОТАЛ на кнопке "${$btn.text().trim()}"`);
        });
    });
    
    console.log('5. Можете теперь попробовать кликнуть на кнопки!');
};

console.log('🔧 Добавлена функция testDropdownButtons() для диагностики');

// Вызываем инициализацию после загрузки
$(document).ready(function() {
    console.log('📄 Документ загружен, инициализируем dropdown...');
    console.log('🔍 ПРОВЕРКА: typeof initDropdowns =', typeof initDropdowns);
    console.log('🔍 ПРОВЕРКА: найдено dropdown кнопок =', $('[data-bs-toggle="dropdown"], .dropdown-toggle').length);
    
    // Задержка для убеждения что всё загрузилось
    setTimeout(function() {
        initDropdowns();
        
        // Дополнительная диагностика
        const dropdownButtons = $('[data-bs-toggle="dropdown"], .dropdown-toggle');
        console.log(`🔍 Найдено dropdown кнопок: ${dropdownButtons.length}`);
        
        dropdownButtons.each(function(i) {
            console.log(`   ${i+1}. "${$(this).text().trim()}" (${$(this).prop('tagName')})`);
        });
    }, 100);
});
</script>