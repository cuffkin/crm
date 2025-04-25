<?php
// /crm/modules/finances/list_partial.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'finances')) {
    die("<div class='text-danger'>Нет доступа</div>");
}

$sql = "
SELECT ft.id, ft.transaction_number, ft.transaction_date, ft.transaction_type, ft.amount, 
       ft.description, ft.conducted, ft.counterparty_id, ft.cash_register_id, ft.payment_method,
       ft.expense_category,
       u.username AS user_name,
       c.name AS counterparty_name,
       cr.name AS cash_register_name
FROM PCRM_FinancialTransaction ft
LEFT JOIN PCRM_User u ON ft.user_id = u.id
LEFT JOIN PCRM_Counterparty c ON ft.counterparty_id = c.id
LEFT JOIN PCRM_CashRegister cr ON ft.cash_register_id = cr.id
ORDER BY ft.transaction_date DESC
";

$res = $conn->query($sql);
if (!$res) {
    die("<div class='text-danger'>Ошибка запроса: " . $conn->error . "</div>");
}
$transactions = $res->fetch_all(MYSQLI_ASSOC);

// Функция для перевода типа операции
function translateTransactionType($type) {
    switch ($type) {
        case 'income':  return '<span class="badge bg-success">Приход</span>';
        case 'expense': return '<span class="badge bg-danger">Расход</span>';
        default:        return $type;
    }
}

// Функция для перевода метода оплаты
function translatePaymentMethod($method) {
    switch ($method) {
        case 'cash':           return 'Наличные';
        case 'card':           return 'Эквайринг';
        case 'transfer_rncb':  return 'Перевод (РНКБ)';
        case 'transfer_other': return 'Перевод (Другой банк)';
        case 'bank_account':   return 'Банковский счёт';
        case 'hybrid':         return 'Гибрид';
        default:               return $method;
    }
}

// Функция для перевода статьи расходов
function translateExpenseCategory($category) {
    switch ($category) {
        case 'salary':     return 'Зарплата';
        case 'materials':  return 'Закупка материалов';
        case 'fuel':       return 'Топливо';
        case 'parts':      return 'Запчасти';
        case 'delivery':   return 'Доставка';
        case 'debt':       return 'Долг';
        case 'collection': return 'Инкассация';
        case 'other':      return 'Другое';
        default:           return '';
    }
}

// Функция для вывода "да"/"нет" для проведенных документов
function isConducted($val) {
    return ($val == 1) ? 'да' : 'нет';
}
?>
<h4>Финансовые операции</h4>
<div class="mb-3">
    <button class="btn btn-success btn-sm me-2" onclick="openFinanceEditTab(0, 'income')">Приходная операция</button>
    <button class="btn btn-danger btn-sm" onclick="openFinanceEditTab(0, 'expense')">Расходная операция</button>
</div>

<table class="table table-bordered table-sm">
  <thead>
    <tr>
      <th>ID</th>
      <th>Номер</th>
      <th>Дата</th>
      <th>Тип</th>
      <th>Сумма</th>
      <th>Контрагент</th>
      <th>Касса</th>
      <th>Тип оплаты</th>
      <th>Статья расходов</th>
      <th>Описание</th>
      <th>Проведена</th>
      <th>Пользователь</th>
      <th>Действия</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($transactions as $t): ?>
    <tr>
      <td><?= $t['id'] ?></td>
      <td><?= htmlspecialchars($t['transaction_number']) ?></td>
      <td><?= $t['transaction_date'] ?></td>
      <td><?= translateTransactionType($t['transaction_type']) ?></td>
      <td><?= number_format($t['amount'], 2, '.', ' ') ?> руб.</td>
      <td><?= htmlspecialchars($t['counterparty_name'] ?? '') ?></td>
      <td><?= htmlspecialchars($t['cash_register_name'] ?? '') ?></td>
      <td><?= translatePaymentMethod($t['payment_method']) ?></td>
      <td><?= $t['transaction_type'] === 'expense' ? translateExpenseCategory($t['expense_category']) : '' ?></td>
      <td><?= htmlspecialchars($t['description'] ?? '') ?></td>
      <td><?= isConducted($t['conducted']) ?></td>
      <td><?= htmlspecialchars($t['user_name'] ?? '') ?></td>
      <td>
        <button class="btn btn-warning btn-sm" onclick="openFinanceEditTab(<?= $t['id'] ?>, '<?= $t['transaction_type'] ?>')">Ред.</button>
        <button class="btn btn-danger btn-sm" onclick="deleteTransaction(<?= $t['id'] ?>)">Удал.</button>
        <button class="btn btn-info btn-sm" onclick="printTransaction(<?= $t['id'] ?>)">Печать</button>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<script>
function openFinanceEditTab(transactionId, transactionType) {
  // Создаем новую вкладку для редактирования
  const tabId = 'finance-tab-' + Math.floor(Math.random() * 1000000);
  const tabContentId = 'finance-content-' + Math.floor(Math.random() * 1000000);
  
  // Заголовок вкладки
  let typeText = transactionType === 'income' ? 'Приход' : 'Расход';
  let tabTitle = transactionId > 0 ? `${typeText} #${transactionId}` : `Новый ${typeText.toLowerCase()}`;
  
  // Добавляем новую вкладку
  $('#crm-tabs').append(`
    <li class="nav-item">
      <a class="nav-link active" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" role="tab" data-transaction-id="${transactionId}" data-transaction-type="${transactionType}">
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
  
  // Загружаем содержимое редактирования
  $.ajax({
    url: '/crm/modules/finances/edit_partial.php',
    data: { 
      id: transactionId,
      type: transactionType,
      tab: 1,
      tab_id: tabId,
      content_id: tabContentId
    },
    success: function(html) {
      $(`#${tabContentId}`).html(html);
      // После загрузки контента запускаем отслеживание изменений формы
      initFormTracking(tabContentId);
    },
    error: function(xhr, status, error) {
      console.error("Error loading transaction:", error);
      $(`#${tabContentId}`).html(`
        <div class="alert alert-danger">
          <h4>Ошибка загрузки операции</h4>
          <p>Статус: ${status}, Код: ${xhr.status}</p>
          <p>Сообщение: ${error}</p>
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
  
  // Сохраняем информацию об открытой вкладке 
  openOrderTabs.set(tabContentId, { tabId, transactionId, type: 'finance', transactionType });
  
  // Сохраняем состояние вкладок
  saveTabsState();
}

function deleteTransaction(transactionId) {
  if (!confirm('Вы уверены, что хотите удалить эту финансовую операцию?')) return;
  
  $.get('/crm/modules/finances/delete.php', { id: transactionId }, function(response) {
    if (response === 'OK') {
      // Обновляем список операций
      updateFinanceList();
      showNotification('Финансовая операция успешно удалена', 'success');
    } else {
      alert('Ошибка при удалении: ' + response);
    }
  });
}

function printTransaction(transactionId) {
  // Открываем окно печати
  window.open('/crm/modules/finances/print.php?id=' + transactionId, '_blank');
}

function updateFinanceList() {
  // Обновляем список финансовых операций
  $.get('/crm/modules/finances/list_partial.php', function(html) {
    // Находим вкладку со списком финансов и обновляем её содержимое
    $('.tab-pane').each(function() {
      if ($(this).find('h4:contains("Финансовые операции")').length > 0) {
        $(this).html(html);
      }
    });
  });
}
</script>