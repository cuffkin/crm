<?php
// /crm/modules/finances/print.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);

$sql = "
SELECT ft.*, 
       u.username AS user_name,
       cp.name AS counterparty_name,
       cr.name AS cash_register_name
FROM PCRM_FinancialTransaction ft
LEFT JOIN PCRM_User u ON ft.user_id = u.id
LEFT JOIN PCRM_Counterparty cp ON ft.counterparty_id = cp.id
LEFT JOIN PCRM_CashRegister cr ON ft.cash_register_id = cr.id
WHERE ft.id=?
";
$st = $conn->prepare($sql);
$st->bind_param("i", $id);
$st->execute();
$res = $st->get_result();
if (!$res || $res->num_rows === 0) {
    die("Финансовая операция не найдена");
}
$transaction = $res->fetch_assoc();

// Получаем детали гибридного платежа (если есть)
$details = [];
if ($transaction['payment_method'] === 'hybrid') {
    $sqlDetails = "
        SELECT * FROM PCRM_PaymentMethodDetails 
        WHERE transaction_id = ? 
        ORDER BY id ASC
    ";
    $st2 = $conn->prepare($sqlDetails);
    $st2->bind_param("i", $id);
    $st2->execute();
    $res2 = $st2->get_result();
    while ($detail = $res2->fetch_assoc()) {
        $details[] = $detail;
    }
}

// Функция для перевода типа операции
function translateTransactionType($type) {
    return $type === 'income' ? 'Приход' : 'Расход';
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

// Определяем тип и заголовок операции
$typeText = translateTransactionType($transaction['transaction_type']);
$title = $typeText . ' №' . $transaction['transaction_number'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title><?= $title ?></title>
  <style>
    body { font-family: sans-serif; margin: 20px; }
    .title { font-weight: bold; font-size: 1.2em; margin-bottom: 1em; text-align: center; }
    .label { font-weight: bold; }
    .block { margin-bottom: 1em; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 1em; }
    th, td { border: 1px solid #ddd; padding: 5px; }
    th { background-color: #f5f5f5; }
    .text-right { text-align: right; }
    .income { color: green; }
    .expense { color: red; }
    .footer { margin-top: 2em; }
    .sign-block { margin-top: 3em; }
  </style>
</head>
<body onload="window.print()">

<div class="title <?= $transaction['transaction_type'] ?>">
  <?= $title ?>
</div>

<div class="block">
  <span class="label">Дата:</span> <?= $transaction['transaction_date'] ?>
</div>

<div class="block">
  <span class="label">Контрагент:</span> <?= htmlspecialchars($transaction['counterparty_name'] ?? '') ?><br>
  <span class="label">Касса:</span> <?= htmlspecialchars($transaction['cash_register_name'] ?? '') ?><br>
  <span class="label">Тип оплаты:</span> <?= translatePaymentMethod($transaction['payment_method']) ?><br>
  <?php if ($transaction['transaction_type'] === 'expense' && !empty($transaction['expense_category'])): ?>
  <span class="label">Статья расходов:</span> <?= translateExpenseCategory($transaction['expense_category']) ?><br>
  <?php endif; ?>
  <span class="label">Проведена:</span> <?= $transaction['conducted'] == 1 ? 'Да' : 'Нет' ?>
</div>

<?php if ($transaction['payment_method'] === 'hybrid' && !empty($details)): ?>
<div class="block">
  <span class="label">Детали оплаты:</span>
  <table>
    <thead>
      <tr>
        <th>Метод оплаты</th>
        <th>Сумма</th>
        <th>Описание</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($details as $detail): ?>
      <tr>
        <td><?= translatePaymentMethod($detail['payment_method']) ?></td>
        <td class="text-right"><?= number_format($detail['amount'], 2, '.', ' ') ?> руб.</td>
        <td><?= htmlspecialchars($detail['description'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="1" class="text-right"><strong>Итого:</strong></td>
        <td class="text-right"><strong><?= number_format($transaction['amount'], 2, '.', ' ') ?> руб.</strong></td>
        <td></td>
      </tr>
    </tfoot>
  </table>
</div>
<?php else: ?>
<div class="block">
  <span class="label">Сумма:</span> <?= number_format($transaction['amount'], 2, '.', ' ') ?> руб.
</div>
<?php endif; ?>

<?php if (!empty($transaction['description'])): ?>
<div class="block">
  <span class="label">Описание:</span>
  <?= nl2br(htmlspecialchars($transaction['description'])) ?>
</div>
<?php endif; ?>

<div class="block">
  <span class="label">Пользователь:</span> <?= htmlspecialchars($transaction['user_name'] ?? '') ?>
</div>

<div class="sign-block">
  <table border="0">
    <tr>
      <td width="50%">Руководитель _______________</td>
      <td width="50%">Кассир _______________</td>
    </tr>
  </table>
</div>

<div class="footer">
  <p>Документ сформирован: <?= date('d.m.Y H:i:s') ?></p>
  <p>PRORABCRM &copy; <?= date('Y') ?></p>
</div>

</body>
</html>