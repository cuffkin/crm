<?php
// /crm/modules/sales/returns/edit_partial.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'sales_returns')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

$id = (int)($_GET['id'] ?? 0);
$based_on = $_GET['based_on'] ?? '';
$order_id = (int)($_GET['order_id'] ?? 0);

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
    $st = $conn->prepare("SELECT * FROM PCRM_ReturnHeader WHERE id=?");
    $st->bind_param("i", $id);
    $st->execute();
    $res = $st->get_result();
    $r = $res->fetch_assoc();
    if ($r) {
        $return_number = $r['return_number'] ?? '';
        $return_date = $r['return_date'];
        $order_id = $r['order_id'];
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
// Если передан ID заказа и создаем новый возврат, заполняем данные из заказа
elseif ($order_id > 0 && $id == 0) {
    $o_st = $conn->prepare("SELECT warehouse, order_number FROM PCRM_Order WHERE id=? AND deleted=0");
    $o_st->bind_param("i", $order_id);
    $o_st->execute();
    $o_res = $o_st->get_result();
    $order = $o_res->fetch_assoc();
    
    if ($order) {
        $warehouse_id = $order['warehouse'];
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

// Загружаем позиции возврата, если редактируем существующий
$items = [];
if ($id > 0) {
    $sqlItems = "
        SELECT ri.*, p.name AS product_name, p.price AS default_price
        FROM PCRM_ReturnItem ri
        LEFT JOIN PCRM_Product p ON ri.product_id = p.id
        WHERE ri.return_id = ?
        ORDER BY ri.id ASC
    ";
    $st2 = $conn->prepare($sqlItems);
    $st2->bind_param("i", $id);
    $st2->execute();
    $r2 = $st2->get_result();
    $items = $r2->fetch_all(MYSQLI_ASSOC);
}
// Если создаем на основе заказа, предзаполняем товары из заказа
elseif ($order_id > 0 && $id == 0) {
    $sqlOrderItems = "
        SELECT oi.product_id, oi.quantity, oi.price, oi.discount, p.name AS product_name
        FROM PCRM_OrderItem oi
        LEFT JOIN PCRM_Product p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ";
    $st3 = $conn->prepare($sqlOrderItems);
    $st3->bind_param("i", $order_id);
    $st3->execute();
    $r3 = $st3->get_result();
    $items = $r3->fetch_all(MYSQLI_ASSOC);
}

// Если это новый документ, генерируем номер
if (empty($return_number) && $id == 0) {
    $nextIdRes = $conn->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'PCRM_ReturnHeader'");
    $nextId = $nextIdRes->fetch_row()[0] ?? 1;
    $return_number = 'RET-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
}

// Рассчитываем общую сумму
$total_amount = 0;
foreach ($items as $item) {
    $total_amount += ($item['quantity'] * $item['price']) - $item['discount'];
}

// Уникальный идентификатор для этого экземпляра
$uniquePrefix = 'ret_' . uniqid();
?>
<div class="card">
  <div class="card-header">
    <?= ($id > 0 ? "Редактирование возврата #{$id}" : "Новый возврат") ?>
    <?= ($based_on === 'order' && $order_id > 0 ? " (на основании заказа #{$order_id})" : "") ?>
  </div>
  <div class="card-body">
    <div class="mb-3">
      <label>Номер возврата</label>
      <input type="text" id="r-number" class="form-control" value="<?= htmlspecialchars($return_number) ?>">
    </div>
    <div class="mb-3">
      <label>Дата</label>
      <input type="datetime-local" id="r-date" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($return_date)) ?>">
    </div>
    <div class="mb-3">
      <label>Заказ</label>
      <select id="r-order" class="form-select" <?= ($order_id > 0 ? 'disabled' : '') ?>>
        <option value="">(не выбран)</option>
        <?php foreach ($allOrders as $o): ?>
        <option value="<?= $o['id'] ?>" <?= ($o['id'] == $order_id ? 'selected' : '') ?>>
          #<?= $o['id'] ?> (<?= htmlspecialchars($o['order_number']) ?>)
        </option>
        <?php endforeach; ?>
      </select>
      <?php if ($order_id > 0): ?>
      <input type="hidden" id="r-order-hidden" value="<?= $order_id ?>">
      <?php endif; ?>
    </div>
    <div class="mb-3">
      <label>Склад <span class="text-danger">*</span></label>
      <select id="r-warehouse" class="form-select required" required>
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
      <label>Грузчик <span class="text-danger">*</span></label>
      <select id="r-loader" class="form-select required" required>
        <option value="">(не выбран)</option>
        <?php foreach ($allLoaders as $l): ?>
        <option value="<?= $l['id'] ?>" <?= ($l['id'] == $loader_id ? 'selected' : '') ?>>
          <?= htmlspecialchars($l['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <div class="invalid-feedback">Выберите грузчика</div>
    </div>
    <div class="mb-3">
      <label>Причина возврата <span class="text-danger">*</span></label>
      <select id="r-reason" class="form-select required" required onchange="window['<?= $uniquePrefix ?>_checkOtherReason']()">
        <option value="">(не выбрана)</option>
        <option value="Брак" <?= ($reason === 'Брак' ? 'selected' : '') ?>>Брак</option>
        <option value="Лишнее" <?= ($reason === 'Лишнее' ? 'selected' : '') ?>>Лишнее</option>
        <option value="Не соответствует ожиданиям" <?= ($reason === 'Не соответствует ожиданиям' ? 'selected' : '') ?>>Не соответствует ожиданиям</option>
        <option value="Перепутал" <?= ($reason === 'Перепутал' ? 'selected' : '') ?>>Перепутал</option>
        <option value="Другое" <?= ($reason === 'Другое' ? 'selected' : '') ?>>Другое</option>
      </select>
      <div class="invalid-feedback">Выберите причину возврата</div>
    </div>
    <div class="mb-3">
      <label>Примечания <?= ($reason === 'Другое' ? '<span class="text-danger">*</span>' : '') ?></label>
      <textarea id="r-notes" class="form-control <?= ($reason === 'Другое' ? 'required' : '') ?>" rows="2"><?= htmlspecialchars($notes) ?></textarea>
      <div class="invalid-feedback">При выборе причины "Другое" необходимо заполнить примечания</div>
    </div>
    <div class="mb-3">
      <label>Статус</label>
      <select id="r-status" class="form-select">
        <option value="new" <?= ($status == 'new' ? 'selected' : '') ?>>Новый</option>
        <option value="confirmed" <?= ($status == 'confirmed' ? 'selected' : '') ?>>Подтверждён</option>
        <option value="completed" <?= ($status == 'completed' ? 'selected' : '') ?>>Завершён</option>
        <option value="cancelled" <?= ($status == 'cancelled' ? 'selected' : '') ?>>Отменён</option>
      </select>
    </div>
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" id="r-conducted" <?= ($conducted == 1 ? 'checked' : '') ?>>
      <label class="form-check-label" for="r-conducted">Проведён</label>
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
              <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>" <?= ($p['id'] == $itm['product_id'] ? 'selected' : '') ?>>
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
      <input type="text" id="r-total" class="form-control" readonly value="<?= number_format($total_amount, 2, '.', '') ?>">
    </div>
    
    <input type="hidden" id="r-based-on" value="<?= htmlspecialchars($based_on) ?>">
    
    <div class="mt-3">
      <button class="btn btn-success" onclick="window['<?= $uniquePrefix ?>_saveReturnWithRKO'](<?= $id ?>)">Сохранить, провести и создать РКО</button>
      <button class="btn btn-success" onclick="window['<?= $uniquePrefix ?>_saveReturn'](<?= $id ?>)">Сохранить</button>
      
      <?php if ($id > 0): ?>
      <!-- Кнопка "Создать на основании" с выпадающим меню для возврата -->
      <div class="btn-group">
        <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          Создать на основании
        </button>
        <ul class="dropdown-menu">
          <li><a class="dropdown-item" href="#" onclick="createRkoFromReturn(<?= $id ?>)">Расходная кассовая операция</a></li>
        </ul>
      </div>
      <?php endif; ?>
      
      <button class="btn btn-secondary" onclick="window['<?= $uniquePrefix ?>_cancelChanges']()">Отмена</button>
    </div>
    
    <?php
    // Включаем связанные документы, если редактируем существующий возврат
    if ($id > 0) {
        require_once __DIR__ . '/../../../includes/related_documents.php';
        showRelatedDocuments($conn, 'return', $id);
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
    window['<?= $uniquePrefix ?>_checkOtherReason'] = checkOtherReason;
    window['<?= $uniquePrefix ?>_addItemRow'] = addItemRow;
    window['<?= $uniquePrefix ?>_addItemRowWithData'] = addItemRowWithData;
    window['<?= $uniquePrefix ?>_calcTotal'] = calcTotal;
    window['<?= $uniquePrefix ?>_saveReturnWithRKO'] = saveReturnWithRKO;
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
      
      // При выборе заказа автоматически подгружаем товары из него
      $('#r-order').change(function() {
        const orderId = $(this).val();
        if (orderId) {
          // Загружаем данные заказа - ИСПРАВЛЕНО: используем новый URL с action parameter
          $.getJSON('/crm/modules/sales/orders/order_api.php', { 
            action: 'get_order_info',
            id: orderId 
          }, function(response) {
            if (response.status === 'ok') {
              // Заполняем склад из заказа
              $('#r-warehouse').val(response.data.warehouse);
            }
          });
          
          // Очищаем таблицу товаров
          $('#ri-table tbody').empty();
          
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
      
      // Проверяем текущую причину возврата
      checkOtherReason();
      
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

    // Функция проверки причины возврата "Другое"
    function checkOtherReason() {
      const reason = $('#r-reason').val();
      if (reason === 'Другое') {
        $('#r-notes').addClass('required').attr('required', 'required');
      } else {
        $('#r-notes').removeClass('required').removeAttr('required');
      }
    }

    // Добавление пустой строки товара
    function addItemRow() {
      let rowHtml = `
        <tr>
          <td>
            <select class="form-select ri-product">
              <option value="">(не выбран)</option>
              ${ALL_PRODUCTS.map(p => `<option value="${p.id}" data-price="${p.price}">${p.name}</option>`).join('')}
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
              ${ALL_PRODUCTS.map(p => `<option value="${p.id}" data-price="${p.price}" ${p.id == item.product_id ? 'selected' : ''}>${p.name}</option>`).join('')}
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
      $('#r-total').val(grand.toFixed(2));
    }

    // Сохранить и создать РКО
    function saveReturnWithRKO(rid) {
      // Устанавливаем флаг проведения
      $('#r-conducted').prop('checked', true);
      // Сохраняем возврат с созданием РКО
      saveReturn(rid, false, true);
    }

    // Сохранение возврата
    function saveReturn(rid, closeAfterSave = false, createRKO = false) {
      // Проверка обязательных полей
      let valid = true;
      
      // Проверка склада
      if (!$('#r-warehouse').val()) {
        $('#r-warehouse').addClass('is-invalid');
        valid = false;
      } else {
        $('#r-warehouse').removeClass('is-invalid');
      }
      
      // Проверка грузчика
      if (!$('#r-loader').val()) {
        $('#r-loader').addClass('is-invalid');
        valid = false;
      } else {
        $('#r-loader').removeClass('is-invalid');
      }
      
      // Проверка причины возврата
      if (!$('#r-reason').val()) {
        $('#r-reason').addClass('is-invalid');
        valid = false;
      } else {
        $('#r-reason').removeClass('is-invalid');
      }
      
      // Если причина "Другое", проверяем заполнено ли примечание
      if ($('#r-reason').val() === 'Другое' && !$('#r-notes').val().trim()) {
        $('#r-notes').addClass('is-invalid');
        valid = false;
      } else {
        $('#r-notes').removeClass('is-invalid');
      }
      
      // Проверка наличия товаров
      const hasProducts = $('#ri-table tbody tr').length > 0 && 
                          $('#ri-table tbody tr').some(function() {
                            return $(this).find('.ri-product').val() !== '';
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
        return_number: $('#r-number').val(),
        return_date: $('#r-date').val(),
        order_id: $('#r-order-hidden').val() || $('#r-order').val(),
        warehouse_id: $('#r-warehouse').val(),
        loader_id: $('#r-loader').val(),
        reason: $('#r-reason').val(),
        notes: $('#r-notes').val(),
        status: $('#r-status').val(),
        conducted: ($('#r-conducted').is(':checked') ? 1 : 0),
        based_on: $('#r-based-on').val()
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
      
      // Флаг для создания РКО
      data.create_rko = createRKO ? 1 : 0;

      // Отправляем данные на сервер
      $.post('/crm/modules/sales/returns/save.php', data, function(resp){
        try {
          const response = JSON.parse(resp);
          
          if (response.status === 'ok') {
            // Обновляем все списки возвратов
            updateReturnsList();
            
            // Показываем уведомление
            console.log('Возврат успешно сохранен');
            // Безопасный вызов через setTimeout
            setTimeout(function() {
              try {
                if (typeof appShowNotification === 'function') {
                  appShowNotification('Возврат успешно сохранен', 'success');
                }
              } catch (e) {
                console.error('Ошибка при показе уведомления:', e);
              }
            }, 0);
            
            // Если был создан РКО, сообщаем об этом
            if (response.rko_created) {
              // Показываем уведомление о создании РКО
              console.log('РКО успешно создан');
              // Безопасный вызов через setTimeout
              setTimeout(function() {
                try {
                  if (typeof appShowNotification === 'function') {
                    appShowNotification('РКО успешно создан', 'success');
                  }
                } catch (e) {
                  console.error('Ошибка при показе уведомления:', e);
                }
              }, 100);
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
                openReturnEditTab(newId);
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
</script>