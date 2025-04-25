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

$supplierRes = $conn->query("SELECT id,name FROM PCRM_Counterparty WHERE type='supplier' ORDER BY name");
$allSuppliers = $supplierRes->fetch_all(MYSQLI_ASSOC);

$whRes = $conn->query("SELECT id,name FROM PCRM_Warehouse WHERE status='active' ORDER BY name");
$allWh = $whRes->fetch_all(MYSQLI_ASSOC);

$prodRes = $conn->query("SELECT id,name,price,cost_price FROM PCRM_Product WHERE status='active' ORDER BY name");
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
$uniquePrefix = 'po_' . uniqid();
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
      <div class="invalid-feedback">Выберите организацию</div>
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
      <select id="po-supplier" class="form-select required" required>
        <option value="">(не выбран)</option>
        <?php foreach ($allSuppliers as $supplier): ?>
        <option value="<?= $supplier['id'] ?>" <?= ($supplier['id'] == $supplier_id ? 'selected' : '') ?>>
          <?= htmlspecialchars($supplier['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <div class="invalid-feedback">Выберите поставщика</div>
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
      <div class="invalid-feedback">Выберите склад</div>
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
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" id="po-conducted" <?= ($conducted == 1 ? 'checked' : '') ?>>
      <label class="form-check-label" for="po-conducted">Проведён</label>
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
            <select class="form-select poi-product">
              <option value="">(не выбран)</option>
              <?php foreach ($allProducts as $p): ?>
              <option value="<?= $p['id'] ?>" data-price="<?= $p['cost_price'] ?>" <?= ($p['id'] == $itm['product_id'] ? 'selected' : '') ?>>
                <?= htmlspecialchars($p['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
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

<script>
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
      
      // Обработчик изменения товаров в таблице
      $('#poi-table').on('change', '.poi-product, .poi-qty, .poi-price, .poi-discount', function(){
        if ($(this).hasClass('poi-product')) {
          let priceInput = $(this).closest('tr').find('.poi-price');
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

    function addRow() {
      let rowHtml = `
        <tr>
          <td>
            <select class="form-select poi-product">
              <option value="">(не выбран)</option>
              ${ALL_PRODUCTS.map(p => `<option value="${p.id}" data-price="${p.cost_price}">${p.name}</option>`).join('')}
            </select>
          </td>
          <td><input type="number" step="0.001" class="form-control poi-qty" value="1"></td>
          <td><input type="number" step="0.01" class="form-control poi-price" value="0"></td>
          <td><input type="number" step="0.01" class="form-control poi-discount" value="0"></td>
          <td class="poi-sum"></td>
          <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();window['<?= $uniquePrefix ?>_calcTotal']();">×</button></td>
        </tr>
      `;
      $('#poi-table tbody').append(rowHtml);
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
      // Сохранить и закрыть вкладку
      saveOrder(oid, true);
    }

    function saveOrder(oid, closeAfterSave = false) {
      // Проверка обязательных полей
      let valid = true;
      
      // Проверка организации
      if (!$('#po-org').val()) {
        $('#po-org').addClass('is-invalid');
        valid = false;
      } else {
        $('#po-org').removeClass('is-invalid');
      }
      
      // Проверка поставщика
      if (!$('#po-supplier').val()) {
        $('#po-supplier').addClass('is-invalid');
        valid = false;
      } else {
        $('#po-supplier').removeClass('is-invalid');
      }
      
      // Проверка склада
      if (!$('#po-wh').val()) {
        $('#po-wh').addClass('is-invalid');
        valid = false;
      } else {
        $('#po-wh').removeClass('is-invalid');
      }
      
      // Проверка наличия товаров
      const hasProducts = $('#poi-table tbody tr').length > 0 && 
                          $('#poi-table tbody tr').some(function() {
                            return $(this).find('.poi-product').val() !== '';
                          });
      
      if (!hasProducts) {
        alert('Добавьте хотя бы один товар в заказ');
        valid = false;
      }
      
      if (!valid) {
        return;
      }
      
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
        let pid = $(this).find('.poi-product').val();
        if (!pid) return;
        let qty = parseFloat($(this).find('.poi-qty').val()) || 0;
        let prc = parseFloat($(this).find('.poi-price').val()) || 0;
        let dsc = parseFloat($(this).find('.poi-discount').val()) || 0;
        items.push({product_id: pid, quantity: qty, price: prc, discount: dsc});
      });
      data.items = JSON.stringify(items);

      $.post('/crm/modules/purchases/orders/save.php', data, function(resp){
        if (resp === 'OK') {
          // Обновляем все списки заказов в других вкладках
          updatePurchaseOrderLists();
          
          // Показываем уведомление
          showNotification('Заказ поставщику успешно сохранен', 'success');
          
          // Если это новый заказ или нужно закрыть вкладку после сохранения
          if (closeAfterSave) {
            // Закрываем текущую вкладку
            cancelChanges();
          } else if (oid === 0) {
            // Получаем ID созданного заказа
            $.get('/crm/modules/purchases/orders/order_api.php', { action: 'get_last_id' }, function(newId) {
              if (newId > 0) {
                // Получаем номер заказа
                const orderNumber = $('#po-num').val();
                
                // Закрываем текущую вкладку
                cancelChanges();
                
                // Открываем новую вкладку с созданным заказом
                openPurchaseOrderEditTab(newId, orderNumber);
              }
            });
          } else {
            // Обновляем заголовок вкладки для существующего заказа
            const orderNumber = $('#po-num').val();
            if (currentTabId) {
              $(`#${currentTabId}`).html(`Заказ поставщику ${orderNumber} <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>`);
              
              // Восстанавливаем обработчик закрытия
              $(`#${currentTabId} .btn-close`).on('click', function(e) {
                e.stopPropagation();
                closeModuleTab(currentTabId, currentTabContentId);
              });
            }
          }
        } else {
          alert(resp);
        }
      });
    }

    function cancelChanges() {
      // Получаем информацию о текущей вкладке из хранимых переменных
      if (currentTabId && currentTabContentId) {
        // Используем глобальную функцию closeModuleTab
        closeModuleTab(currentTabId, currentTabContentId);
      } else {
        // Запасной вариант - ищем ближайшую родительскую вкладку
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
</script>