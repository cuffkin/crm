<?php
// /crm/modules/sales/orders/edit_partial.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'sales_orders')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

$id = (int)($_GET['id'] ?? 0);
// Проверка режима работы (в отдельной вкладке)
$tabMode = isset($_GET['tab']) && $_GET['tab'] == 1;

$organization     = null;
$order_number     = '';
$order_date       = date('Y-m-d H:i:s');
$customer         = null;
$warehouse        = null;
$delivery_address = '';
$contacts         = '';
$comment          = '';
$status           = 'new';
$total_amount     = '0.00';
$conducted        = 0; // 0 = неактивен, 1 = активен но не проведён, 2 = проведён
$driver_id        = null;

if ($id > 0) {
    $st = $conn->prepare("SELECT * FROM PCRM_Order WHERE id=? AND deleted=0");
    $st->bind_param("i", $id);
    $st->execute();
    $res = $st->get_result();
    $ord = $res->fetch_assoc();
    if ($ord) {
        $organization     = $ord['organization'];
        $order_number     = $ord['order_number'];
        $order_date       = $ord['order_date'];
        $customer         = $ord['customer'];
        $warehouse        = $ord['warehouse'];
        $delivery_address = $ord['delivery_address'] ?? '';
        $contacts         = isset($ord['contacts']) ? $ord['contacts'] : '';
        $comment          = $ord['comment'] ?? '';
        $status           = $ord['status'];
        $total_amount     = $ord['total_amount'];
        $conducted        = $ord['conducted'];
        $driver_id        = $ord['driver_id'];
    } else {
        die("<div class='text-danger'>Заказ не найден</div>");
    }
}

// Справочники
$orgRes = $conn->query("SELECT id, name FROM PCRM_Organization ORDER BY name");
$allOrgs = $orgRes->fetch_all(MYSQLI_ASSOC);

// Исправлено - используем поле phone вместо contact_info
$custRes = $conn->query("SELECT id, name, address, phone FROM PCRM_Counterparty ORDER BY name");
$allCust = $custRes->fetch_all(MYSQLI_ASSOC);

$whRes = $conn->query("SELECT id, name FROM PCRM_Warehouse ORDER BY name");
$allWh = $whRes->fetch_all(MYSQLI_ASSOC);

$drvRes = $conn->query("SELECT id, name FROM PCRM_Drivers ORDER BY name");
$allDrivers = $drvRes->fetch_all(MYSQLI_ASSOC);

$prodRes = $conn->query("SELECT id, name, price FROM PCRM_Product ORDER BY name");
$allProducts = $prodRes->fetch_all(MYSQLI_ASSOC);

$items = [];
if ($id > 0) {
    $sqlItems = "
      SELECT i.*, p.name AS product_name, p.price AS default_price
      FROM PCRM_OrderItem i
      LEFT JOIN PCRM_Product p ON i.product_id = p.id
      WHERE i.order_id = ?
      ORDER BY i.id ASC
    ";
    $st2 = $conn->prepare($sqlItems);
    $st2->bind_param("i", $id);
    $st2->execute();
    $r2 = $st2->get_result();
    $items = $r2->fetch_all(MYSQLI_ASSOC);
}

// Определяем, выбрана ли доставка (true) или самовывоз (false)
$isDelivery = !empty($driver_id);

// Уникальный идентификатор для этого экземпляра редактирования заказа
$uniquePrefix = 'ord_' . uniqid();
?>
<div class="card">
  <div class="card-header">
    <?= ($id > 0 ? "Редактирование заказа #{$id}" : "Новый заказ") ?>
  </div>
  <div class="card-body">
    <div class="mb-3">
      <label>Организация <span class="text-danger">*</span></label>
      <select id="o-org" class="form-select required" required>
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
      <input type="text" id="o-num" class="form-control" value="<?= htmlspecialchars($order_number) ?>">
    </div>
    <div class="mb-3">
      <label>Дата</label>
      <input type="datetime-local" id="o-date" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($order_date)) ?>">
    </div>
    <div class="mb-3">
      <label>Контрагент <span class="text-danger">*</span></label>
      <div class="input-group">
        <select id="o-cust" class="form-select required" required>
          <option value="">(не выбран)</option>
          <?php foreach ($allCust as $c): ?>
          <option value="<?= $c['id'] ?>" 
                  data-address="<?= htmlspecialchars($c['address'] ?? '') ?>"
                  data-contacts="<?= htmlspecialchars($c['phone'] ?? '') ?>"
                  <?= ($c['id'] == $customer ? 'selected' : '') ?>>
            <?= htmlspecialchars($c['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-secondary" type="button" onclick="openNewTab('counterparty/edit_partial')">Создать нового</button>
      </div>
      <div class="invalid-feedback">Выберите контрагента</div>
    </div>
    <div class="mb-3">
      <label>Склад <span class="text-danger">*</span></label>
      <select id="o-wh" class="form-select required" required>
        <option value="">(не выбран)</option>
        <?php foreach ($allWh as $w): ?>
        <option value="<?= $w['id'] ?>" <?= ($w['id'] == $warehouse ? 'selected' : '') ?>>
          <?= htmlspecialchars($w['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <div class="invalid-feedback">Выберите склад</div>
    </div>
    <div class="mb-3">
      <label>Тип доставки</label>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="o-delivery-type" <?= $isDelivery ? 'checked' : '' ?>>
        <label class="form-check-label" for="o-delivery-type">
          <span id="delivery-type-text"><?= $isDelivery ? 'Доставка' : 'Самовывоз' ?></span>
        </label>
      </div>
    </div>
    
    <!-- Блок с водителем (показывается только при выборе доставки) -->
    <div class="mb-3" id="driver-container" <?= $isDelivery ? '' : 'style="display:none;"' ?>>
      <label>Водитель</label>
      <select id="o-driver" class="form-select">
        <option value="">(не выбран)</option>
        <?php foreach ($allDrivers as $dr): ?>
        <option value="<?= $dr['id'] ?>" <?= ($dr['id'] == $driver_id ? 'selected' : '') ?>>
          <?= htmlspecialchars($dr['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    
    <div class="mb-3">
      <label>Контакты <span class="text-danger">*</span></label>
      <input type="text" id="o-contacts" class="form-control required" value="<?= htmlspecialchars($contacts) ?>" required>
      <div class="invalid-feedback">Введите контактную информацию</div>
    </div>
    
    <div class="mb-3">
      <label>Адрес доставки</label>
      <input type="text" id="o-delivery" class="form-control <?= $isDelivery ? 'required' : '' ?>" 
             value="<?= htmlspecialchars($delivery_address) ?>"
             <?= $isDelivery ? 'required' : '' ?>>
      <div class="invalid-feedback">Адрес доставки обязателен для заполнения при выборе типа "Доставка"</div>
    </div>
    
    <div class="mb-3">
      <label>Комментарий</label>
      <textarea id="o-comment" class="form-control" rows="2"><?= htmlspecialchars($comment) ?></textarea>
    </div>
    <div class="mb-3">
      <label>Статус</label>
      <select id="o-status" class="form-select">
        <option value="new" <?= ($status == 'new' || $status == 'draft' ? 'selected' : '') ?>>Новый</option>
        <option value="confirmed" <?= ($status == 'confirmed' ? 'selected' : '') ?>>Подтверждён</option>
        <option value="in_transit" <?= ($status == 'in_transit' || $status == 'shipped' ? 'selected' : '') ?>>В пути</option>
        <option value="completed" <?= ($status == 'completed' ? 'selected' : '') ?>>Завершён</option>
        <option value="cancelled" <?= ($status == 'cancelled' ? 'selected' : '') ?>>Отменён</option>
      </select>
    </div>
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" id="o-conducted" <?= ($conducted == 2 ? 'checked' : '') ?>>
      <label class="form-check-label" for="o-conducted">Проведён</label>
    </div>
    <h5>Товары</h5>
    <table class="table table-sm table-bordered" id="oi-table">
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
              <select class="form-select oi-product">
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
          <td><input type="number" step="0.001" class="form-control oi-qty" value="<?= $itm['quantity'] ?>"></td>
          <td><input type="number" step="0.01" class="form-control oi-price" value="<?= $itm['price'] ?>"></td>
          <td><input type="number" step="0.01" class="form-control oi-discount" value="<?= $itm['discount'] ?>"></td>
          <td class="oi-sum"></td>
          <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();window['<?= $uniquePrefix ?>_calcTotal']();">×</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <button class="btn btn-outline-primary btn-sm" onclick="window['<?= $uniquePrefix ?>_addRow']()">+ Добавить строку</button>
    <div class="mt-3">
      <label>Итого (руб.)</label>
      <input type="text" id="o-total" class="form-control" readonly value="<?= $total_amount ?>">
    </div>
    <div class="mt-3">
      <div class="d-flex justify-content-between">
        <div>
          <button class="btn btn-success" onclick="window['<?= $uniquePrefix ?>_saveOrderAndClose'](<?= $id ?>)">Сохранить и закрыть</button>
          <button class="btn btn-success" onclick="window['<?= $uniquePrefix ?>_saveOrder'](<?= $id ?>)">Сохранить</button>
          
          <?php if ($id > 0): ?>
          <!-- Кнопка "Создать на основании" с выпадающим меню -->
          <div class="btn-group dropend">
            <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
              Создать на основании
            </button>
            <ul class="dropdown-menu position-static">
              <li><a class="dropdown-item" href="#" onclick="window['<?= $uniquePrefix ?>_createShipmentFromOrder'](<?= $id ?>)">Создать отгрузку</a></li>
              <li><a class="dropdown-item" href="#" onclick="window['<?= $uniquePrefix ?>_createFinanceFromOrder'](<?= $id ?>)">Создать ПКО</a></li>
              <li><a class="dropdown-item" href="#" onclick="window['<?= $uniquePrefix ?>_createReturnFromOrder'](<?= $id ?>)">Создать возврат</a></li>
            </ul>
          </div>
          <?php endif; ?>
        </div>
        
        <!-- Кнопка-бургер с дополнительными действиями для заказа -->
        <div class="dropdown">
          <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
            <i class="fas fa-bars"></i> Действия
          </button>
          <ul class="dropdown-menu position-static">
            <li><a class="dropdown-item" href="#" onclick="saveCreateShipmentAndPrint(<?= $id ?>)">
              <i class="fas fa-print"></i> Сохранить, создать отгрузку на печать
            </a></li>
            <li><a class="dropdown-item" href="#" onclick="saveCreateShipmentAndPKO(<?= $id ?>)">
              <i class="fas fa-money-bill"></i> Сохранить, создать отгрузку и ПКО на печать
            </a></li>
          </ul>
        </div>
      </div>
    </div>
    
    <?php
    // Включаем связанные документы, если редактируем существующий заказ
    if ($id > 0) {
        require_once __DIR__ . '/../../../includes/related_documents.php';
        showRelatedDocuments($conn, 'order', $id);
    }
    ?>
  </div>
</div>

<style>
/* Стили для правильного отображения выпадающих меню */
.dropdown-menu.position-static {
  position: absolute !important;
  transform: translate(0, 40px) !important;
  top: 0 !important;
  left: 0 !important;
  margin: 0 !important;
  display: none;
}

.dropdown.show .dropdown-menu.position-static {
  display: block;
}

.btn-group.dropend .dropdown-menu.position-static {
  left: 0 !important;
  right: auto !important;
}
</style>

<script>
// Используем анонимную функцию для создания локальной области видимости
(function() {
    // Создаем локальные переменные, недоступные извне этой функции
    const ALL_PRODUCTS = <?= json_encode($allProducts, JSON_UNESCAPED_UNICODE) ?>;
    
    // ID текущей вкладки (для закрытия)
    let currentTabId = '';
    let currentTabContentId = '';

    // Регистрируем функции в глобальной области видимости с уникальными именами
    window['<?= $uniquePrefix ?>_addRow'] = function() {
      const newRow = `
        <tr>
          <td>
            <div class="input-group">
              <select class="form-select oi-product">
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
          <td><input type="number" step="0.001" class="form-control oi-qty" value="1"></td>
          <td><input type="number" step="0.01" class="form-control oi-price" value="0"></td>
          <td><input type="number" step="0.01" class="form-control oi-discount" value="0"></td>
          <td class="oi-sum"></td>
          <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();window['<?= $uniquePrefix ?>_calcTotal']();">×</button></td>
        </tr>
      `;
      $('#oi-table tbody').append(newRow);
      initializeRowHandlers();
      window['<?= $uniquePrefix ?>_calcTotal']();
    };
    window['<?= $uniquePrefix ?>_calcTotal'] = calcTotal;
    window['<?= $uniquePrefix ?>_saveOrderAndClose'] = saveOrderAndClose;
    window['<?= $uniquePrefix ?>_saveOrder'] = saveOrder;
    window['<?= $uniquePrefix ?>_cancelChanges'] = cancelChanges;
    window['<?= $uniquePrefix ?>_createShipmentFromOrder'] = createShipmentFromOrder;
    window['<?= $uniquePrefix ?>_createFinanceFromOrder'] = createFinanceFromOrder;
    window['<?= $uniquePrefix ?>_createReturnFromOrder'] = createReturnFromOrder;

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
      if (<?= $id ?> === 0 && $('#oi-table tbody tr').length === 0) {
        window['<?= $uniquePrefix ?>_addRow']();
      }
      
      // Если это новый заказ, генерируем номер автоматически
      if (<?= $id ?> === 0 && $('#o-num').val() === '') {
        $.getJSON('/crm/modules/sales/orders/order_api.php', { action: 'generate' }, function(response) {
          if (response.status === 'ok') {
            $('#o-num').val(response.number);
          }
        });
      }
      
      // Если это новый заказ, автоматически заполняем организацию и склад
      if (<?= $id ?> === 0) {
        // Автоматически выбираем первую организацию, если ни одна не выбрана
        if ($('#o-org').val() === '') {
          const firstOrg = $('#o-org option:not(:first)').first();
          if (firstOrg.length) {
            $('#o-org').val(firstOrg.val());
          }
        }
        
        // Автоматически выбираем первый склад, если ни один не выбран
        if ($('#o-wh').val() === '') {
          const firstWh = $('#o-wh option:not(:first)').first();
          if (firstWh.length) {
            $('#o-wh').val(firstWh.val());
          }
        }
      }
      
      // Обработчик выбора контрагента - автозаполнение адреса и контактов
      $('#o-cust').change(function() {
        const selectedOption = $(this).find('option:selected');
        
        try {
          // Всегда перезаполняем адрес доставки и контакты при смене контрагента
          const address = selectedOption.data('address') || '';
          $('#o-delivery').val(address);
          
          const contacts = selectedOption.data('contacts') || '';
          $('#o-contacts').val(contacts);
        } catch(e) {
          console.error('Ошибка при автозаполнении полей:', e);
        }
      });
      
      // Проверка уникальности номера заказа
      $('#o-num').on('change', function() {
        const number = $(this).val().trim();
        if (number === '') {
          alert('Номер заказа не может быть пустым');
          // Генерируем новый номер
          $.getJSON('/crm/modules/sales/orders/order_api.php', { action: 'generate' }, function(response) {
            if (response.status === 'ok') {
              $('#o-num').val(response.number);
            }
          });
          return;
        }
        
        // Проверяем уникальность номера
        $.getJSON('/crm/modules/sales/orders/order_api.php', {
          action: 'check',
          number: number,
          id: <?= $id ?>
        }, function(response) {
          if (response.status === 'error') {
            alert(response.message);
            // Генерируем новый номер
            $.getJSON('/crm/modules/sales/orders/order_api.php', { action: 'generate' }, function(generatedResponse) {
              if (generatedResponse.status === 'ok') {
                $('#o-num').val(generatedResponse.number);
              }
            });
          }
        });
      });
      
      // Обработчик переключателя типа доставки
      $('#o-delivery-type').change(function() {
        const isDelivery = $(this).is(':checked');
        
        // Обновляем текст
        $('#delivery-type-text').text(isDelivery ? 'Доставка' : 'Самовывоз');
        
        // Показываем/скрываем блок с выбором водителя
        $('#driver-container').toggle(isDelivery);
        
        // Сбрасываем значение водителя при переключении на самовывоз
        if (!isDelivery) {
          $('#o-driver').val('');
        }
        
        // Обновляем валидацию адреса доставки
        if (isDelivery) {
          $('#o-delivery').addClass('required').attr('required', 'required');
        } else {
          $('#o-delivery').removeClass('required').removeAttr('required');
        }
      });
      
      // Обработчик изменения товаров в таблице
      $('#oi-table').on('change', '.oi-product, .oi-qty, .oi-price, .oi-discount', function(){
        if ($(this).hasClass('oi-product')) {
          let priceInput = $(this).closest('tr').find('.oi-price');
          let currentVal = parseFloat(priceInput.val()) || 0;
          if (currentVal === 0) {
            let sel = $(this).find(':selected');
            let autoPrice = parseFloat(sel.attr('data-price')) || 0;
            priceInput.val(autoPrice.toFixed(2));
          }
        }
        calcTotal();
      });
      
      // Инициализируем выпадающие меню
      initializeDropdownMenus();
      
      // Обработчик для переключения статуса проведения
      $('#o-conducted').on('change', function() {
        let isChecked = $(this).is(':checked');
        $('#conduct-status-text').text(isChecked ? 'Проведён' : 'Не проведён');
      });
    });

    function calcTotal() {
      let grand = 0;
      $('#oi-table tbody tr').each(function(){
        let qty = parseFloat($(this).find('.oi-qty').val()) || 0;
        let price = parseFloat($(this).find('.oi-price').val()) || 0;
        let discount = parseFloat($(this).find('.oi-discount').val()) || 0;
        let sum = (qty * price) - discount;
        $(this).find('.oi-sum').text(sum.toFixed(2));
        grand += sum;
      });
      $('#o-total').val(grand.toFixed(2));
    }

    function saveOrderAndClose(oid) {
      // Сохранить и закрыть вкладку
      saveOrder(oid, true);
    }

    function saveOrder(oid, closeAfterSave = false, successCallback = null, errorCallback = null) {
      // Проверка обязательных полей
      let valid = true;
      
      // Проверка организации
      if (!$('#o-org').val()) {
        $('#o-org').addClass('is-invalid');
        valid = false;
      } else {
        $('#o-org').removeClass('is-invalid');
      }
      
      // Проверка контрагента
      if (!$('#o-cust').val()) {
        $('#o-cust').addClass('is-invalid');
        valid = false;
      } else {
        $('#o-cust').removeClass('is-invalid');
      }
      
      // Проверка контактов
      if (!$('#o-contacts').val().trim()) {
        $('#o-contacts').addClass('is-invalid');
        valid = false;
      } else {
        $('#o-contacts').removeClass('is-invalid');
      }
      
      // Проверка склада
      if (!$('#o-wh').val()) {
        $('#o-wh').addClass('is-invalid');
        valid = false;
      } else {
        $('#o-wh').removeClass('is-invalid');
      }
      
      // Проверка наличия товаров
      const hasProducts = $('#oi-table tbody tr').length > 0 && 
                          $('#oi-table tbody tr').some(function() {
                            return $(this).find('.oi-product').val() !== '';
                          });
      
      if (!hasProducts) {
        alert('Добавьте хотя бы один товар в заказ');
        valid = false;
      }
      
      if (!valid) {
        if (typeof errorCallback === 'function') {
          errorCallback();
        }
        return;
      }
      
      // Проверка валидации адреса доставки
      const isDelivery = $('#o-delivery-type').is(':checked');
      const deliveryAddress = $('#o-delivery').val().trim();
      
      if (isDelivery && deliveryAddress === '') {
        $('#o-delivery').addClass('is-invalid');
        alert('При выборе типа "Доставка" необходимо указать адрес доставки');
        if (typeof errorCallback === 'function') {
          errorCallback();
        }
        return;
      } else {
        $('#o-delivery').removeClass('is-invalid');
      }
      
      calcTotal();
      let data = {
        id: oid,
        organization:   $('#o-org').val(),
        order_number:   $('#o-num').val(),
        order_date:     $('#o-date').val(),
        customer:       $('#o-cust').val(),
        contacts:       $('#o-contacts').val(),
        warehouse:      $('#o-wh').val(),
        driver_id:      isDelivery ? $('#o-driver').val() : '',  // Устанавливаем driver_id только при доставке
        delivery_addr:  deliveryAddress,
        comment:        $('#o-comment').val(),
        status:         $('#o-status').val(),
        total_amount:   $('#o-total').val(),
        conducted:      ($('#o-conducted').is(':checked') ? 2 : 0)
      };

      let items = [];
      $('#oi-table tbody tr').each(function(){
        let pid = $(this).find('.oi-product').val();
        if (!pid) return;
        let qty = parseFloat($(this).find('.oi-qty').val()) || 0;
        let prc = parseFloat($(this).find('.oi-price').val()) || 0;
        let dsc = parseFloat($(this).find('.oi-discount').val()) || 0;
        items.push({product_id: pid, quantity: qty, price: prc, discount: dsc});
      });
      data.items = JSON.stringify(items);

      $.post('/crm/modules/sales/orders/save.php', data, function(resp){
        if (resp === 'OK') {
          // Обновляем все списки заказов в других вкладках
          updateOrderLists();
          
          // Показываем уведомление
          console.log('Заказ успешно сохранен');
          // Безопасный вызов уведомления через setTimeout с нулевой задержкой,
          // чтобы избежать рекурсии, если функция всё еще неисправна
          setTimeout(function() {
            try {
              if (typeof appShowNotification === 'function') {
                appShowNotification('Заказ успешно сохранен', 'success');
              }
            } catch (e) {
              console.error('Ошибка при показе уведомления:', e);
            }
          }, 0);
          
          // Если это новый заказ или нужно закрыть вкладку после сохранения
          if (closeAfterSave) {
            // Закрываем текущую вкладку
            cancelChanges();
          } else if (oid === 0) {
            // Получаем ID созданного заказа
            $.get('/crm/modules/sales/orders/order_api.php', { action: 'get_last_id' }, function(newId) {
              if (newId > 0) {
                // Вызываем колбэк успеха, если он есть
                if (typeof successCallback === 'function') {
                  successCallback(newId);
                }
                
                // Получаем номер заказа
                const orderNumber = $('#o-num').val();
                
                // Обновляем заголовок вкладки
                if (currentTabId) {
                  $(`#${currentTabId}`).html(`Заказ ${orderNumber} <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>`);
                  
                  // Восстанавливаем обработчик закрытия
                  $(`#${currentTabId} .btn-close`).on('click', function(e) {
                    e.stopPropagation();
                    closeModuleTab(currentTabId, currentTabContentId);
                  });
                }
              }
            });
          } else {
            // Обновляем заголовок вкладки для существующего заказа
            const orderNumber = $('#o-num').val();
            if (currentTabId) {
              $(`#${currentTabId}`).html(`Заказ ${orderNumber} <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>`);
              
              // Восстанавливаем обработчик закрытия
              $(`#${currentTabId} .btn-close`).on('click', function(e) {
                e.stopPropagation();
                closeModuleTab(currentTabId, currentTabContentId);
              });
            }
            
            // Вызываем колбэк успеха, если он есть
            if (typeof successCallback === 'function') {
              successCallback(oid);
            }
          }
        } else {
          alert(resp);
          if (typeof errorCallback === 'function') {
            errorCallback();
          }
        }
      }).fail(function(xhr, status, error) {
        alert("Ошибка при сохранении заказа: " + error);
        if (typeof errorCallback === 'function') {
          errorCallback();
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

    // Модифицируем функцию createShipmentFromOrder для поддержки колбэков
    window['<?= $uniquePrefix ?>_createShipmentFromOrder'] = function(orderId, callback) {
      // Если это вызов для просто печати, создаем отгрузку через API
      if (typeof callback === 'function') {
        $.ajax({
          url: '/crm/modules/shipments/api_handler.php',
          type: 'POST',
          data: {
            action: 'create_from_order',
            order_id: orderId
          },
          success: function(response) {
            try {
              const result = typeof response === 'string' ? JSON.parse(response) : response;
              if (result.status === 'ok') {
                callback(result.shipment_id);
              } else {
                alert(result.message || 'Ошибка при создании отгрузки');
                callback(null);
              }
            } catch (e) {
              alert('Неверный формат ответа');
              callback(null);
            }
          },
          error: function(xhr) {
            alert('Ошибка сервера: ' + xhr.statusText);
            callback(null);
          }
        });
        return;
      }

      // Создаем новую вкладку для отгрузки
      const tabId = 'shipment-tab-' + Math.floor(Math.random() * 1000000);
      const tabContentId = 'shipment-content-' + Math.floor(Math.random() * 1000000);
      
      // Заголовок вкладки
      let tabTitle = 'Новая отгрузка';
      
      // Добавляем новую вкладку
      $('#crm-tabs').append(`
        <li class="nav-item">
          <a class="nav-link active" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" role="tab">
            ${tabTitle} <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
          </a>
        </li>
      `);
      
      // Добавляем содержимое вкладки
      $('#crm-tab-content').append(`
        <div class="tab-pane fade show active" id="${tabContentId}" role="tabpanel">
          <div class="text-center p-5">
            <div class="spinner-border" role="status">
              <span class="visually-hidden">Загрузка...</span>
            </div>
          </div>
        </div>
      `);
      
      // Делаем новую вкладку активной
      $('.nav-link').removeClass('active');
      $('.tab-pane').removeClass('show active');
      $(`#${tabId}`).addClass('active');
      $(`#${tabContentId}`).addClass('show active');
      
      // Загружаем содержимое редактирования отгрузки
      $.ajax({
        url: '/crm/modules/shipments/edit_partial.php',
        data: { 
          id: 0,
          order_id: orderId,
          tab: 1,
          tab_id: tabId,
          content_id: tabContentId,
          based_on: 'order'
        },
        success: function(html) {
          $(`#${tabContentId}`).html(html);
        },
        error: function(xhr, status, error) {
          $(`#${tabContentId}`).html(`
            <div class="alert alert-danger">
              <h4>Ошибка загрузки формы отгрузки</h4>
              <p>Ответ сервера: ${xhr.responseText}</p>
            </div>
          `);
        }
      });
      
      // Обработчик закрытия вкладки
      $(`#${tabId} .btn-close`).on('click', function(e) {
        e.stopPropagation();
        closeModuleTab(tabId, tabContentId);
      });
    };

    // Модифицируем функцию createFinanceFromOrder для поддержки колбэков
    window['<?= $uniquePrefix ?>_createFinanceFromOrder'] = function(orderId, callback) {
      // Получаем информацию о заказе
      $.getJSON('/crm/modules/sales/orders/order_api.php', { action: 'get_order_info', id: orderId }, function(orderData) {
        // Если это вызов для просто печати, создаем ПКО через API
        if (typeof callback === 'function') {
          $.ajax({
            url: '/crm/modules/finances/get_last_transaction_id.php',
            type: 'GET',
            success: function(lastIdResponse) {
              const lastId = parseInt(lastIdResponse) || 0;
              const newNumber = 'ПКО-' + String(lastId + 1).padStart(6, '0');
              
              // Создаем ПКО
              $.ajax({
                url: '/crm/modules/finances/save.php',
                type: 'POST',
                data: {
                  transaction_type: 'income',
                  transaction_number: newNumber,
                  transaction_date: new Date().toISOString().slice(0, 19).replace('T', ' '),
                  amount: orderData.data.total_amount,
                  counterparty_id: orderData.data.customer,
                  cash_register_id: 1, // Предполагаем, что касса с ID=1 существует
                  payment_method: 'cash',
                  description: 'Оплата по заказу №' + orderId,
                  conducted: 1,
                  based_on: 'order',
                  order_id: orderId
                },
                success: function(pkoResponse) {
                  if (pkoResponse === 'OK') {
                    // Получаем ID созданного ПКО
                    $.ajax({
                      url: '/crm/modules/finances/get_last_transaction_id.php',
                      type: 'GET',
                      success: function(newPkoId) {
                        callback(parseInt(newPkoId));
                      },
                      error: function() {
                        alert('Ошибка при получении ID ПКО');
                        callback(null);
                      }
                    });
                  } else {
                    alert('Ошибка при создании ПКО');
                    callback(null);
                  }
                },
                error: function() {
                  alert('Ошибка сервера при создании ПКО');
                  callback(null);
                }
              });
            },
            error: function() {
              alert('Ошибка при получении последнего ID транзакции');
              callback(null);
            }
          });
          return;
        }
        
        // В обычном режиме
        const tabId = 'finance-tab-' + Math.floor(Math.random() * 1000000);
        const tabContentId = 'finance-content-' + Math.floor(Math.random() * 1000000);
        
        // Заголовок вкладки
        let tabTitle = 'Новый приход';
        
        // Добавляем новую вкладку
        $('#crm-tabs').append(`
          <li class="nav-item">
            <a class="nav-link active" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" role="tab">
              ${tabTitle} <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
            </a>
          </li>
        `);
        
        // Добавляем содержимое вкладки
        $('#crm-tab-content').append(`
          <div class="tab-pane fade show active" id="${tabContentId}" role="tabpanel">
            <div class="text-center p-5">
              <div class="spinner-border" role="status">
                <span class="visually-hidden">Загрузка...</span>
              </div>
            </div>
          </div>
        `);
        
        // Делаем новую вкладку активной
        $('.nav-link').removeClass('active');
        $('.tab-pane').removeClass('show active');
        $(`#${tabId}`).addClass('active');
        $(`#${tabContentId}`).addClass('show active');
        
        // Загружаем содержимое редактирования финансовой операции
        $.ajax({
          url: '/crm/modules/finances/edit_partial.php',
          data: { 
            id: 0,
            type: 'income',
            order_id: orderId,
            amount: orderData.data.total_amount,
            counterparty_id: orderData.data.customer,
            tab: 1,
            tab_id: tabId,
            content_id: tabContentId,
            based_on: 'order'
          },
          success: function(html) {
            $(`#${tabContentId}`).html(html);
          },
          error: function(xhr, status, error) {
            $(`#${tabContentId}`).html(`
              <div class="alert alert-danger">
                <h4>Ошибка загрузки формы финансовой операции</h4>
                <p>Ответ сервера: ${xhr.responseText}</p>
              </div>
            `);
          }
        });
        
        // Обработчик закрытия вкладки
        $(`#${tabId} .btn-close`).on('click', function(e) {
          e.stopPropagation();
          closeModuleTab(tabId, tabContentId);
        });
      });
    };

    // Функция для создания возврата на основании заказа
    function createReturnFromOrder(orderId) {
      // Создаем новую вкладку для возврата
      const tabId = 'return-tab-' + Math.floor(Math.random() * 1000000);
      const tabContentId = 'return-content-' + Math.floor(Math.random() * 1000000);
      
      // Заголовок вкладки
      let tabTitle = 'Новый возврат';
      
      // Добавляем новую вкладку
      $('#crm-tabs').append(`
        <li class="nav-item">
          <a class="nav-link active" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" role="tab">
            ${tabTitle} <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
          </a>
        </li>
      `);
      
      // Добавляем содержимое вкладки
      $('#crm-tab-content').append(`
        <div class="tab-pane fade show active" id="${tabContentId}" role="tabpanel">
          <div class="text-center p-5">
            <div class="spinner-border" role="status">
              <span class="visually-hidden">Загрузка...</span>
            </div>
          </div>
        </div>
      `);
      
      // Делаем новую вкладку активной
      $('.nav-link').removeClass('active');
      $('.tab-pane').removeClass('show active');
      $(`#${tabId}`).addClass('active');
      $(`#${tabContentId}`).addClass('show active');
      
      // Загружаем содержимое редактирования возврата
      $.ajax({
        url: '/crm/modules/sales/returns/edit_partial.php',
        data: { 
          id: 0,
          order_id: orderId,
          tab: 1,
          tab_id: tabId,
          content_id: tabContentId,
          based_on: 'order'
        },
        success: function(html) {
          $(`#${tabContentId}`).html(html);
        },
        error: function(xhr, status, error) {
          $(`#${tabContentId}`).html(`
            <div class="alert alert-danger">
              <h4>Ошибка загрузки формы возврата</h4>
              <p>Ответ сервера: ${xhr.responseText}</p>
            </div>
          `);
        }
      });
      
      // Обработчик закрытия вкладки
      $(`#${tabId} .btn-close`).on('click', function(e) {
        e.stopPropagation();
        closeModuleTab(tabId, tabContentId);
      });
    }

    // Для обратной совместимости
    function addRow() {
      window['<?= $uniquePrefix ?>_addRow']();
    }

    // Добавляем функцию для корректного позиционирования меню
    function initializeDropdownMenus() {
      // Находим все выпадающие меню на странице
      $('.dropdown-toggle').on('click', function(e) {
        const $button = $(this);
        const $menu = $button.next('.dropdown-menu');
        
        // Устанавливаем позицию меню относительно кнопки
        const buttonPos = $button[0].getBoundingClientRect();
        const isRightAligned = $button.closest('.dropdown').hasClass('dropend') || 
                              $menu.hasClass('dropdown-menu-end');
        
        if (isRightAligned) {
          // Для меню, выровненных по правому краю
          $menu.css({
            'left': 'auto',
            'right': '0'
          });
        } else {
          // Для стандартных меню
          $menu.css('left', '0');
        }
        
        // Правильное вертикальное расположение
        $menu.css('top', buttonPos.height + 'px');
        
        // Предотвращаем закрытие меню при клике на его элементы
        $menu.find('.dropdown-item').on('click', function(e) {
          e.stopPropagation();
        });
      });
    }

    // Функция для сохранения заказа, создания отгрузки и вывода на печать
    function saveCreateShipmentAndPrint(id) {
      // Сначала сохраняем заказ с проведением
      let currentConducted = $('#o-conducted').is(':checked');
      $('#o-conducted').prop('checked', true);
      
      // Выполняем сохранение
      saveOrder(id, false, function(savedId) {
        // После сохранения создаем отгрузку
        const actualId = savedId || id;
        
        // Используем существующую функцию создания отгрузки
        window[`${uniquePrefix}_createShipmentFromOrder`](actualId, function(shipmentId) {
          if (shipmentId) {
            // Открываем печатные формы в новых вкладках
            window.open(`/crm/modules/sales/orders/print.php?id=${actualId}`, '_blank');
            window.open(`/crm/modules/shipments/print.php?id=${shipmentId}`, '_blank');
          }
        });
      }, function() {
        // В случае ошибки восстанавливаем состояние проведения
        $('#o-conducted').prop('checked', currentConducted);
      });
    }

    // Функция для сохранения заказа, создания отгрузки и ПКО
    function saveCreateShipmentAndPKO(id) {
      // Сначала сохраняем заказ с проведением
      let currentConducted = $('#o-conducted').is(':checked');
      $('#o-conducted').prop('checked', true);
      
      // Выполняем сохранение
      saveOrder(id, false, function(savedId) {
        const actualId = savedId || id;
        
        // Используем существующую функцию создания отгрузки
        window[`${uniquePrefix}_createShipmentFromOrder`](actualId, function(shipmentId) {
          if (shipmentId) {
            // Затем создаем ПКО
            window[`${uniquePrefix}_createFinanceFromOrder`](actualId, function(financeId) {
              if (financeId) {
                // Открываем печатные формы в новых вкладках
                window.open(`/crm/modules/sales/orders/print.php?id=${actualId}`, '_blank');
                window.open(`/crm/modules/shipments/print.php?id=${shipmentId}`, '_blank');
                window.open(`/crm/modules/finances/print.php?id=${financeId}`, '_blank');
              }
            });
          }
        });
      }, function() {
        // В случае ошибки восстанавливаем состояние проведения
        $('#o-conducted').prop('checked', currentConducted);
      });
    }

    // Глобальные функции для кнопок в меню "Действия"
    window.saveCreateShipmentAndPrint = saveCreateShipmentAndPrint;
    window.saveCreateShipmentAndPKO = saveCreateShipmentAndPKO;
})();
</script>