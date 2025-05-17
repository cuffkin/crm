<?php
// /crm/modules/finances/edit_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'finances')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

$id = (int)($_GET['id'] ?? 0);
$transaction_type = $_GET['type'] ?? 'income'; // По умолчанию - приход
// Проверка режима работы (в отдельной вкладке)
$tabMode = isset($_GET['tab']) && $_GET['tab'] == 1;

// Добавляем параметры для создания на основании
$based_on = $_GET['based_on'] ?? '';
$order_id = (int)($_GET['order_id'] ?? 0);
$shipment_id = (int)($_GET['shipment_id'] ?? 0);
$return_id = (int)($_GET['return_id'] ?? 0);
$amount = (float)($_GET['amount'] ?? 0);
$counterparty_id = (int)($_GET['counterparty_id'] ?? 0);
$description = $_GET['description'] ?? '';

// Значения по умолчанию
$transaction_number = '';
$transaction_date = date('Y-m-d H:i:s');
if (empty($amount)) $amount = '0.00';
if (empty($counterparty_id)) $counterparty_id = null;
$cash_register_id = null;
$payment_method = 'cash'; // По умолчанию - наличные
if (empty($description)) $description = '';
$conducted = 0;
$user_id = $_SESSION['user_id']; // Текущий пользователь
$expense_category = ''; // Новое поле для статьи расходов (по умолчанию пусто)

// Загружаем данные транзакции если ID > 0
if ($id > 0) {
    $st = $conn->prepare("SELECT * FROM PCRM_FinancialTransaction WHERE id=?");
    $st->bind_param("i", $id);
    $st->execute();
    $res = $st->get_result();
    $tr = $res->fetch_assoc();
    if ($tr) {
        $transaction_type = $tr['transaction_type'];
        $transaction_number = $tr['transaction_number'] ?? '';
        $transaction_date = $tr['transaction_date'];
        $amount = $tr['amount'];
        $counterparty_id = $tr['counterparty_id'];
        $cash_register_id = $tr['cash_register_id'];
        $payment_method = $tr['payment_method'];
        $description = $tr['description'] ?? '';
        $conducted = $tr['conducted'];
        $user_id = $tr['user_id'] ?? $_SESSION['user_id'];
        $expense_category = $tr['expense_category'] ?? ''; // Загружаем статью расходов
    } else {
        die("<div class='text-danger'>Финансовая операция не найдена</div>");
    }
}

// Загружаем список контрагентов
$ctrRes = $conn->query("SELECT id, name FROM PCRM_Counterparty ORDER BY name");
$allCounterparties = $ctrRes->fetch_all(MYSQLI_ASSOC);

// Загружаем список касс
$crRes = $conn->query("SELECT id, name FROM PCRM_CashRegister WHERE status='active' ORDER BY name");
$allCashRegisters = $crRes->fetch_all(MYSQLI_ASSOC);

// Загружаем список пользователей
$userRes = $conn->query("SELECT id, username, role FROM PCRM_User WHERE status='active' ORDER BY username");
$allUsers = $userRes->fetch_all(MYSQLI_ASSOC);

// Если это новый документ, генерируем номер
if (empty($transaction_number) && $id == 0) {
    $prefix = $transaction_type === 'income' ? 'IN-' : 'OUT-';
    $nextIdRes = $conn->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'PCRM_FinancialTransaction'");
    $nextId = $nextIdRes->fetch_row()[0] ?? 1;
    $transaction_number = $prefix . str_pad($nextId, 6, '0', STR_PAD_LEFT);
}

// Получаем детали гибридной оплаты
$hybridDetails = [];
if ($id > 0 && $payment_method === 'hybrid') {
    $detailsQuery = "SELECT * FROM PCRM_PaymentMethodDetails WHERE transaction_id=? ORDER BY id";
    $stmt = $conn->prepare($detailsQuery);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $detailsResult = $stmt->get_result();
    while ($detail = $detailsResult->fetch_assoc()) {
        $hybridDetails[] = $detail;
    }
}

// Заголовок операции
$header = $transaction_type === 'income' ? 'Приходная операция' : 'Расходная операция';
$header .= $id > 0 ? " #{$id}" : "";

// Добавляем информацию, на основании чего создана операция
if ($based_on === 'order' && $order_id > 0) {
    $header .= " (на основании заказа #{$order_id})";
} elseif ($based_on === 'shipment' && $shipment_id > 0) {
    $header .= " (на основании отгрузки #{$shipment_id})";
} elseif ($based_on === 'return' && $return_id > 0) {
    $header .= " (на основании возврата #{$return_id})";
}

// Стиль заголовка в зависимости от типа операции
$headerClass = $transaction_type === 'income' ? 'bg-success text-white' : 'bg-danger text-white';
?>
<div class="card">
  <div class="card-header <?= $headerClass ?>">
    <?= $header ?>
  </div>
  <div class="card-body">
    <div class="mb-3">
      <label>Тип операции</label>
      <select id="tr-type" class="form-select" <?= ($id > 0 ? 'disabled' : '') ?>>
        <option value="income" <?= ($transaction_type === 'income' ? 'selected' : '') ?>>Приход</option>
        <option value="expense" <?= ($transaction_type === 'expense' ? 'selected' : '') ?>>Расход</option>
      </select>
      <input type="hidden" id="transaction-id" value="<?= $id ?>">
    </div>
    <div class="mb-3">
      <label>Номер операции</label>
      <input type="text" id="tr-number" class="form-control" value="<?= htmlspecialchars($transaction_number) ?>">
    </div>
    <div class="mb-3">
      <label>Дата</label>
      <input type="datetime-local" id="tr-date" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($transaction_date)) ?>">
    </div>
    <div class="mb-3">
      <label>Контрагент <span class="text-danger">*</span></label>
      <div class="input-group">
        <select id="tr-counterparty" class="form-select required" required>
          <option value="">(не выбран)</option>
          <?php foreach ($allCounterparties as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($c['id'] == $counterparty_id ? 'selected' : '') ?>>
            <?= htmlspecialchars($c['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-secondary" type="button" onclick="openNewTab('counterparty/edit_partial')">Создать нового</button>
      </div>
      <div class="invalid-feedback">Выберите контрагента</div>
    </div>
    <div class="mb-3">
      <label>Касса <span class="text-danger">*</span></label>
      <select id="tr-cash-register" class="form-select required" required>
        <option value="">(не выбрана)</option>
        <?php foreach ($allCashRegisters as $cr): ?>
        <option value="<?= $cr['id'] ?>" <?= ($cr['id'] == $cash_register_id ? 'selected' : '') ?>>
          <?= htmlspecialchars($cr['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <div class="invalid-feedback">Выберите кассу</div>
    </div>
    <div class="mb-3">
      <label>Тип оплаты</label>
      <select id="tr-payment-method" class="form-select" onchange="toggleHybridPaymentDetails()">
        <option value="cash" <?= ($payment_method === 'cash' ? 'selected' : '') ?>>Наличные</option>
        <option value="card" <?= ($payment_method === 'card' ? 'selected' : '') ?>>Эквайринг</option>
        <option value="transfer_rncb" <?= ($payment_method === 'transfer_rncb' ? 'selected' : '') ?>>Перевод (РНКБ)</option>
        <option value="transfer_other" <?= ($payment_method === 'transfer_other' ? 'selected' : '') ?>>Перевод (Другой банк)</option>
        <option value="bank_account" <?= ($payment_method === 'bank_account' ? 'selected' : '') ?>>Банковский счёт</option>
        <option value="hybrid" <?= ($payment_method === 'hybrid' ? 'selected' : '') ?>>Гибрид</option>
      </select>
    </div>
    
    <!-- Статья расходов (только для расходных операций) -->
    <div id="expense-category-container" class="mb-3" <?= ($transaction_type === 'income' ? 'style="display:none;"' : '') ?>>
      <label>Статья расходов <span class="text-danger">*</span></label>
      <select id="tr-expense-category" class="form-select" onchange="checkExpenseCategoryOther()">
        <option value="" <?= ($expense_category === '' ? 'selected' : '') ?>>(не выбрана)</option>
        <option value="salary" <?= ($expense_category === 'salary' ? 'selected' : '') ?>>Зарплата</option>
        <option value="materials" <?= ($expense_category === 'materials' ? 'selected' : '') ?>>Закупка материалов</option>
        <option value="fuel" <?= ($expense_category === 'fuel' ? 'selected' : '') ?>>Топливо</option>
        <option value="parts" <?= ($expense_category === 'parts' ? 'selected' : '') ?>>Запчасти</option>
        <option value="delivery" <?= ($expense_category === 'delivery' ? 'selected' : '') ?>>Доставка</option>
        <option value="debt" <?= ($expense_category === 'debt' ? 'selected' : '') ?>>Долг</option>
        <option value="collection" <?= ($expense_category === 'collection' ? 'selected' : '') ?>>Инкассация</option>
        <option value="other" <?= ($expense_category === 'other' ? 'selected' : '') ?>>Другое (указать в описании)</option>
      </select>
      <div class="invalid-feedback">Выберите статью расходов</div>
    </div>
    
    <!-- Стандартное поле суммы (для не-гибридных методов) -->
    <div id="standard-amount-field" <?= ($payment_method === 'hybrid' ? 'style="display:none;"' : '') ?>>
      <div class="mb-3">
        <label>Сумма <span class="text-danger">*</span></label>
        <input type="number" step="0.01" id="tr-amount" class="form-control required" value="<?= $amount ?>" required>
        <div class="invalid-feedback">Введите сумму</div>
      </div>
    </div>
    
    <!-- Гибридный метод оплаты (несколько типов с суммами) -->
    <div id="hybrid-payment-details" <?= ($payment_method !== 'hybrid' ? 'style="display:none;"' : '') ?>>
      <h5 class="mt-3">Детали оплаты</h5>
      <table class="table table-sm table-bordered" id="payment-details-table">
        <thead>
          <tr>
            <th>Тип оплаты</th>
            <th>Сумма</th>
            <th>Описание</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($hybridDetails)): ?>
            <?php foreach ($hybridDetails as $detail): ?>
            <tr>
              <td>
                <select class="form-select pd-method">
                  <option value="cash" <?= ($detail['payment_method'] === 'cash' ? 'selected' : '') ?>>Наличные</option>
                  <option value="card" <?= ($detail['payment_method'] === 'card' ? 'selected' : '') ?>>Эквайринг</option>
                  <option value="transfer_rncb" <?= ($detail['payment_method'] === 'transfer_rncb' ? 'selected' : '') ?>>Перевод (РНКБ)</option>
                  <option value="transfer_other" <?= ($detail['payment_method'] === 'transfer_other' ? 'selected' : '') ?>>Перевод (Другой банк)</option>
                  <option value="bank_account" <?= ($detail['payment_method'] === 'bank_account' ? 'selected' : '') ?>>Банковский счёт</option>
                </select>
              </td>
              <td><input type="number" step="0.01" class="form-control pd-amount" value="<?= $detail['amount'] ?>"></td>
              <td><input type="text" class="form-control pd-description" value="<?= htmlspecialchars($detail['description'] ?? '') ?>"></td>
              <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();calcTotalHybrid();">×</button></td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td>
                <select class="form-select pd-method">
                  <option value="cash">Наличные</option>
                  <option value="card">Эквайринг</option>
                  <option value="transfer_rncb">Перевод (РНКБ)</option>
                  <option value="transfer_other">Перевод (Другой банк)</option>
                  <option value="bank_account">Банковский счёт</option>
                </select>
              </td>
              <td><input type="number" step="0.01" class="form-control pd-amount" value="<?= $amount > 0 ? $amount : '0.00' ?>"></td>
              <td><input type="text" class="form-control pd-description" value=""></td>
              <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();calcTotalHybrid();">×</button></td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
      <button class="btn btn-outline-primary btn-sm mb-3" onclick="addPaymentDetailRow()">+ Добавить метод оплаты</button>
      
      <div class="mb-3">
        <label>Итого (руб.)</label>
        <input type="text" id="tr-hybrid-total" class="form-control" readonly value="<?= $amount ?>">
      </div>
    </div>
    
    <div class="mb-3">
      <label>Описание <span id="description-required" class="text-danger" style="display:none;">*</span></label>
      <textarea id="tr-description" class="form-control" rows="2"><?= htmlspecialchars($description) ?></textarea>
      <div class="invalid-feedback">При выборе статьи расходов "Другое" необходимо заполнить описание</div>
    </div>
    
    <div class="mb-3">
      <label>Пользователь</label>
      <select id="tr-user" class="form-select">
        <?php foreach ($allUsers as $u): ?>
        <option value="<?= $u['id'] ?>" <?= ($u['id'] == $user_id ? 'selected' : '') ?>>
          <?= htmlspecialchars($u['username']) ?> (<?= $u['role'] ?>)
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    
    <!-- Добавляем скрытые поля -->
    <input type="hidden" id="tr-conducted" value="0"> <!-- Скрытое поле для проведения -->
    <input type="hidden" id="tr-order-id" value="<?= $order_id ?>">
    <input type="hidden" id="tr-shipment-id" value="<?= $shipment_id ?>">
    <input type="hidden" id="tr-return-id" value="<?= $return_id ?>">
    <input type="hidden" id="tr-based-on" value="<?= $based_on ?>">
    
    <div class="mt-3">
      <button class="btn btn-success" onclick="saveTransactionAndClose(<?= $id ?>, true)">Сохранить и провести</button>
      <button class="btn btn-success" onclick="saveTransaction(<?= $id ?>)">Сохранить</button>
      <button class="btn btn-secondary" onclick="cancelChanges()">Отмена</button>
    </div>
    
    <?php
    // Включаем связанные документы, если редактируем существующую финансовую операцию
    if ($id > 0) {
        require_once __DIR__ . '/../../includes/related_documents.php';
        showRelatedDocuments($conn, 'finance', $id);
    }
    ?>
  </div>
</div>

<script>
// Глобальные функции для кнопок
window.saveTransactionAndClose = function(tid, withConducted = false) {
  if (withConducted) {
    // Устанавливаем признак проведения
    $('#tr-conducted').val(1);
  }
  saveTransaction(tid, true);
};

window.saveTransaction = function(tid, closeAfterSave = false) {
  // Проверка обязательных полей
  let valid = true;
  
  // Проверка контрагента
  if (!$('#tr-counterparty').val()) {
    $('#tr-counterparty').addClass('is-invalid');
    valid = false;
  } else {
    $('#tr-counterparty').removeClass('is-invalid');
  }
  
  // Проверка кассы
  if (!$('#tr-cash-register').val()) {
    $('#tr-cash-register').addClass('is-invalid');
    valid = false;
  } else {
    $('#tr-cash-register').removeClass('is-invalid');
  }
  
  // Проверка статьи расходов (только для расходных операций)
  if ($('#tr-type').val() === 'expense') {
    if (!$('#tr-expense-category').val()) {
      $('#tr-expense-category').addClass('is-invalid');
      valid = false;
    } else {
      $('#tr-expense-category').removeClass('is-invalid');
      
      // Если выбрана статья "Другое", проверяем наличие описания
      if ($('#tr-expense-category').val() === 'other' && !$('#tr-description').val().trim()) {
        $('#tr-description').addClass('is-invalid');
        valid = false;
      } else {
        $('#tr-description').removeClass('is-invalid');
      }
    }
  }
  
  // Проверка суммы (зависит от метода оплаты)
  const paymentMethod = $('#tr-payment-method').val();
  
  if (paymentMethod === 'hybrid') {
    // Для гибридного метода проверяем, что есть хотя бы одна строка с суммой > 0
    let hasValidAmount = false;
    $('#payment-details-table tbody tr').each(function() {
      let amount = parseFloat($(this).find('.pd-amount').val()) || 0;
      if (amount > 0) {
        hasValidAmount = true;
      }
    });
    
    if (!hasValidAmount) {
      alert('Добавьте хотя бы один метод оплаты с суммой больше 0');
      valid = false;
    }
    
    // Пересчитываем итоговую сумму
    window.calcTotalHybrid();
  } else {
    // Для обычного метода проверяем поле суммы
    if (!$('#tr-amount').val() || parseFloat($('#tr-amount').val()) <= 0) {
      $('#tr-amount').addClass('is-invalid');
      valid = false;
    } else {
      $('#tr-amount').removeClass('is-invalid');
    }
  }
  
  if (!valid) {
    return;
  }
  
  // Собираем данные для отправки
  let data = {
    id: tid,
    transaction_type: $('#tr-type').val(),
    transaction_number: $('#tr-number').val(),
    transaction_date: $('#tr-date').val(),
    counterparty_id: $('#tr-counterparty').val(),
    cash_register_id: $('#tr-cash-register').val(),
    payment_method: $('#tr-payment-method').val(),
    description: $('#tr-description').val(),
    conducted: parseInt($('#tr-conducted').val()),
    user_id: $('#tr-user').val(),
    
    // Добавляем данные для статьи расходов (если это расход)
    expense_category: $('#tr-type').val() === 'expense' ? $('#tr-expense-category').val() : '',
    
    // Добавляем данные для связанных документов
    order_id: $('#tr-order-id').val() || 0,
    shipment_id: $('#tr-shipment-id').val() || 0,
    return_id: $('#tr-return-id').val() || 0,
    based_on: $('#tr-based-on').val() || ''
  };
  
  // Определяем сумму в зависимости от метода оплаты
  if (paymentMethod === 'hybrid') {
    data.amount = $('#tr-hybrid-total').val();
    
    // Собираем детали гибридного платежа
    let details = [];
    $('#payment-details-table tbody tr').each(function() {
      let method = $(this).find('.pd-method').val();
      let amount = parseFloat($(this).find('.pd-amount').val()) || 0;
      let description = $(this).find('.pd-description').val();
      
      if (amount > 0) {
        details.push({
          payment_method: method,
          amount: amount,
          description: description
        });
      }
    });
    data.payment_details = JSON.stringify(details);
  } else {
    data.amount = $('#tr-amount').val();
  }

  // Отправляем данные на сервер
  $.post('/crm/modules/finances/save.php', data, function(resp) {
    // Упрощаем обработку ответа: теперь ожидаем 'OK' вместо JSON
    if (resp === 'OK') {
      // Обновляем все списки финансовых операций
      updateFinanceList();
      
      // Вместо уведомления - показываем алерт
      // alert('Финансовая операция успешно сохранена');
      
      // Если это новая операция или нужно закрыть вкладку после сохранения
      if (closeAfterSave) {
        // Закрываем текущую вкладку
        window.cancelChanges();
      } else if (tid === 0) {
        // Получаем ID созданной операции
        $.get('/crm/modules/finances/get_last_transaction_id.php', function(newId) {
          if (newId > 0) {
            // Закрываем текущую вкладку
            window.cancelChanges();
            
            // Открываем новую вкладку с созданной операцией
            openFinanceEditTab(newId, $('#tr-type').val());
          }
        });
      }
    } else if (resp.includes('error')) {
      // Если возвращается ошибка в JSON формате, пробуем распарсить
      try {
        const response = JSON.parse(resp);
        alert('Ошибка: ' + (response.message || 'Неизвестная ошибка'));
      } catch (e) {
        // Если не удалось распарсить, показываем текст ошибки как есть
        alert('Ошибка при сохранении: ' + resp);
      }
    } else {
      // В противном случае показываем ответ как ошибку
      alert('Ошибка при сохранении: ' + resp);
    }
  }).fail(function(xhr, status, error) {
    alert('Ошибка при отправке запроса: ' + error);
  });
};

window.cancelChanges = function() {
  // Ищем ближайшую родительскую вкладку
  const tabContent = $('.tab-pane.active');
  if (tabContent.length) {
    const contentId = tabContent.attr('id');
    const tabId = $('a[href="#' + contentId + '"]').attr('id');
    if (contentId && tabId) {
      closeModuleTab(tabId, contentId);
    }
  }
};

// На уровне каждой вкладки создаем свою область видимости
(function() {
  // Переменные для текущей вкладки
  var tabId = '';
  var tabContentId = '';

  $(document).ready(function(){
    // Если выбран гибридный тип оплаты, рассчитываем итоговую сумму
    if ($('#tr-payment-method').val() === 'hybrid') {
      calcTotalHybrid();
    }
    
    // Получение ID текущей вкладки из URL параметра или поиск активной вкладки
    let urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('tab_id') && urlParams.has('content_id')) {
      tabId = urlParams.get('tab_id');
      tabContentId = urlParams.get('content_id');
    } else {
      // Ищем текущий элемент вкладки
      tabContentId = $('.tab-pane.active').attr('id');
      if (tabContentId) {
        tabId = $('a[href="#' + tabContentId + '"]').attr('id');
      }
    }
    
    // Обработчик изменения типа операции (приход/расход)
    $('#tr-type').change(function() {
      let type = $(this).val();
      // Изменение префикса номера операции
      let prefix = type === 'income' ? 'IN-' : 'OUT-';
      let currentNumber = $('#tr-number').val();
      
      // Если номер пустой или соответствует формату, меняем префикс
      if (currentNumber === '' || currentNumber.match(/^(IN|OUT)-\d+$/)) {
        let numPart = currentNumber.split('-')[1] || '000001';
        $('#tr-number').val(prefix + numPart);
      }
      
      // Изменение заголовка
      $('.card-header').removeClass('bg-success bg-danger').addClass(type === 'income' ? 'bg-success' : 'bg-danger');
      $('.card-header').text(type === 'income' ? 'Приходная операция' : 'Расходная операция');
      
      // Показываем/скрываем статью расходов
      if (type === 'expense') {
        $('#expense-category-container').show();
      } else {
        $('#expense-category-container').hide();
        $('#tr-expense-category').val(''); // Сбрасываем значение при переключении на приход
      }
      
      // Проверяем необходимость обязательного заполнения описания
      checkExpenseCategoryOther();
    });
    
    // Проверяем необходимость обязательного заполнения описания при загрузке
    checkExpenseCategoryOther();
  });

  // Переключение между стандартной и гибридной оплатой
  window.toggleHybridPaymentDetails = function() {
    let method = $('#tr-payment-method').val();
    if (method === 'hybrid') {
      $('#standard-amount-field').hide();
      $('#hybrid-payment-details').show();
      calcTotalHybrid();
    } else {
      $('#standard-amount-field').show();
      $('#hybrid-payment-details').hide();
    }
  };

  // Проверка, выбрана ли статья расходов "Другое"
  window.checkExpenseCategoryOther = function() {
    let type = $('#tr-type').val();
    if (type === 'expense') {
      let category = $('#tr-expense-category').val();
      if (category === 'other') {
        $('#description-required').show();
        $('#tr-description').addClass('required');
      } else {
        $('#description-required').hide();
        $('#tr-description').removeClass('required');
      }
    } else {
      $('#description-required').hide();
      $('#tr-description').removeClass('required');
    }
  };

  // Добавление строки детализации гибридного платежа
  window.addPaymentDetailRow = function() {
    let rowHtml = `
      <tr>
        <td>
          <select class="form-select pd-method">
            <option value="cash">Наличные</option>
            <option value="card">Эквайринг</option>
            <option value="transfer_rncb">Перевод (РНКБ)</option>
            <option value="transfer_other">Перевод (Другой банк)</option>
            <option value="bank_account">Банковский счёт</option>
          </select>
        </td>
        <td><input type="number" step="0.01" class="form-control pd-amount" value="0.00"></td>
        <td><input type="text" class="form-control pd-description" value=""></td>
        <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();calcTotalHybrid();">×</button></td>
      </tr>
    `;
    $('#payment-details-table tbody').append(rowHtml);
  };

  // Рассчет суммы для гибридного платежа
  window.calcTotalHybrid = function() {
    let total = 0;
    $('#payment-details-table tbody tr').each(function() {
      let amount = parseFloat($(this).find('.pd-amount').val()) || 0;
      total += amount;
    });
    $('#tr-hybrid-total').val(total.toFixed(2));
  };

  // Обработчик расчета суммы при изменении значений
  $('#payment-details-table').on('change', '.pd-amount', function() {
    calcTotalHybrid();
  });

  // Внутренняя вспомогательная функция, теперь не используется
  function saveTransactionInternal(tid, closeAfterSave = false) {
    // Проверка обязательных полей
    let valid = true;
    
    // Проверка контрагента
    if (!$('#tr-counterparty').val()) {
      $('#tr-counterparty').addClass('is-invalid');
      valid = false;
    } else {
      $('#tr-counterparty').removeClass('is-invalid');
    }
    
    // Проверка кассы
    if (!$('#tr-cash-register').val()) {
      $('#tr-cash-register').addClass('is-invalid');
      valid = false;
    } else {
      $('#tr-cash-register').removeClass('is-invalid');
    }
    
    // Проверка статьи расходов (только для расходных операций)
    if ($('#tr-type').val() === 'expense') {
      if (!$('#tr-expense-category').val()) {
        $('#tr-expense-category').addClass('is-invalid');
        valid = false;
      } else {
        $('#tr-expense-category').removeClass('is-invalid');
        
        // Если выбрана статья "Другое", проверяем наличие описания
        if ($('#tr-expense-category').val() === 'other' && !$('#tr-description').val().trim()) {
          $('#tr-description').addClass('is-invalid');
          valid = false;
        } else {
          $('#tr-description').removeClass('is-invalid');
        }
      }
    }
    
    // Проверка суммы (зависит от метода оплаты)
    const paymentMethod = $('#tr-payment-method').val();
    
    if (paymentMethod === 'hybrid') {
      // Для гибридного метода проверяем, что есть хотя бы одна строка с суммой > 0
      let hasValidAmount = false;
      $('#payment-details-table tbody tr').each(function() {
        let amount = parseFloat($(this).find('.pd-amount').val()) || 0;
        if (amount > 0) {
          hasValidAmount = true;
        }
      });
      
      if (!hasValidAmount) {
        alert('Добавьте хотя бы один метод оплаты с суммой больше 0');
        valid = false;
      }
      
      // Пересчитываем итоговую сумму
      calcTotalHybrid();
    } else {
      // Для обычного метода проверяем поле суммы
      if (!$('#tr-amount').val() || parseFloat($('#tr-amount').val()) <= 0) {
        $('#tr-amount').addClass('is-invalid');
        valid = false;
      } else {
        $('#tr-amount').removeClass('is-invalid');
      }
    }
    
    if (!valid) {
      return;
    }
    
    // Собираем данные для отправки
    let data = {
      id: tid,
      transaction_type: $('#tr-type').val(),
      transaction_number: $('#tr-number').val(),
      transaction_date: $('#tr-date').val(),
      counterparty_id: $('#tr-counterparty').val(),
      cash_register_id: $('#tr-cash-register').val(),
      payment_method: $('#tr-payment-method').val(),
      description: $('#tr-description').val(),
      conducted: parseInt($('#tr-conducted').val()),
      user_id: $('#tr-user').val(),
      
      // Добавляем данные для статьи расходов (если это расход)
      expense_category: $('#tr-type').val() === 'expense' ? $('#tr-expense-category').val() : '',
      
      // Добавляем данные для связанных документов
      order_id: $('#tr-order-id').val() || 0,
      shipment_id: $('#tr-shipment-id').val() || 0,
      return_id: $('#tr-return-id').val() || 0,
      based_on: $('#tr-based-on').val() || ''
    };
    
    // Определяем сумму в зависимости от метода оплаты
    if (paymentMethod === 'hybrid') {
      data.amount = $('#tr-hybrid-total').val();
      
      // Собираем детали гибридного платежа
      let details = [];
      $('#payment-details-table tbody tr').each(function() {
        let method = $(this).find('.pd-method').val();
        let amount = parseFloat($(this).find('.pd-amount').val()) || 0;
        let description = $(this).find('.pd-description').val();
        
        if (amount > 0) {
          details.push({
            payment_method: method,
            amount: amount,
            description: description
          });
        }
      });
      data.payment_details = JSON.stringify(details);
    } else {
      data.amount = $('#tr-amount').val();
    }

    // Отправляем данные на сервер
    $.post('/crm/modules/finances/save.php', data, function(resp) {
      // Упрощаем обработку ответа: теперь ожидаем 'OK' вместо JSON
      if (resp === 'OK') {
        // Обновляем все списки финансовых операций
        updateFinanceList();
        
        // Показываем уведомление
        showNotification('Финансовая операция успешно сохранена', 'success');
        
        // Если это новая операция или нужно закрыть вкладку после сохранения
        if (closeAfterSave) {
          // Закрываем текущую вкладку
          cancelChanges();
        } else if (tid === 0) {
          // Получаем ID созданной операции
          $.get('/crm/modules/finances/get_last_transaction_id.php', function(newId) {
            if (newId > 0) {
              // Закрываем текущую вкладку
              cancelChanges();
              
              // Открываем новую вкладку с созданной операцией
              openFinanceEditTab(newId, $('#tr-type').val());
            }
          });
        }
      } else if (resp.includes('error')) {
        // Если возвращается ошибка в JSON формате, пробуем распарсить
        try {
          const response = JSON.parse(resp);
          alert('Ошибка: ' + (response.message || 'Неизвестная ошибка'));
        } catch (e) {
          // Если не удалось распарсить, показываем текст ошибки как есть
          alert('Ошибка при сохранении: ' + resp);
        }
      } else {
        // В противном случае показываем ответ как ошибку
        alert('Ошибка при сохранении: ' + resp);
      }
    }).fail(function(xhr, status, error) {
      alert('Ошибка при отправке запроса: ' + error);
    });
  };

  // Внутренняя функция отмены изменений
  function cancelChangesInternal() {
    if (tabId && tabContentId) {
      closeModuleTab(tabId, tabContentId);
    } else {
      // Используем глобальную функцию
      window.cancelChanges();
    }
  };
})();

// Используем глобальную функцию openNewTab из common.js
</script>