<?php
// /crm/modules/finances/save.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'finances')) {
    die(json_encode(["status" => "error", "message" => "Нет доступа"]));
}

// Получаем данные транзакции
$id = (int)($_POST['id'] ?? 0);
$transaction_type = $_POST['transaction_type'] ?? 'income';
$transaction_number = trim($_POST['transaction_number'] ?? '');
$transaction_date = $_POST['transaction_date'] ?? date('Y-m-d H:i:s');
$amount = (float)($_POST['amount'] ?? 0);
$counterparty_id = (int)($_POST['counterparty_id'] ?? 0);
$cash_register_id = (int)($_POST['cash_register_id'] ?? 0);
$payment_method = $_POST['payment_method'] ?? 'cash';
$description = trim($_POST['description'] ?? '');
$conducted = (int)($_POST['conducted'] ?? 0);
$user_id = (int)($_POST['user_id'] ?? $_SESSION['user_id']);

// Добавляем статью расходов
$expense_category = '';
if ($transaction_type === 'expense') {
    $expense_category = trim($_POST['expense_category'] ?? '');
}

// Данные для связанных документов
$order_id = (int)($_POST['order_id'] ?? 0);
$shipment_id = (int)($_POST['shipment_id'] ?? 0);
$return_id = (int)($_POST['return_id'] ?? 0);
$based_on = $_POST['based_on'] ?? '';

// Проверка наличия гибридных платежей
$payment_details = [];
if (isset($_POST['payment_details']) && $payment_method === 'hybrid') {
    $payment_details = json_decode($_POST['payment_details'], true) ?? [];
}

// Валидация
if ($counterparty_id <= 0) {
    die(json_encode(["status" => "error", "message" => "Не выбран контрагент"]));
}
if ($cash_register_id <= 0) {
    die(json_encode(["status" => "error", "message" => "Не выбрана касса"]));
}
if ($amount <= 0) {
    die(json_encode(["status" => "error", "message" => "Сумма должна быть больше 0"]));
}
if ($payment_method === 'hybrid' && empty($payment_details)) {
    die(json_encode(["status" => "error", "message" => "Для гибридного метода оплаты необходимо указать хотя бы один метод"]));
}

// Проверка наличия статьи расходов для РКО
if ($transaction_type === 'expense' && empty($expense_category)) {
    die(json_encode(["status" => "error", "message" => "Для расходной операции необходимо указать статью расходов"]));
}

// Проверка наличия описания, если статья расходов - "Другое"
if ($transaction_type === 'expense' && $expense_category === 'other' && empty($description)) {
    die(json_encode(["status" => "error", "message" => "При выборе статьи расходов 'Другое' необходимо заполнить описание"]));
}

// Начинаем транзакцию
$conn->begin_transaction();

try {
    // UPDATE или INSERT заголовка транзакции
    if ($id > 0) {
        $sql = "
            UPDATE PCRM_FinancialTransaction
            SET transaction_number=?, transaction_date=?, transaction_type=?,
                amount=?, counterparty_id=?, cash_register_id=?, 
                payment_method=?, expense_category=?, description=?, conducted=?, user_id=?
            WHERE id=?
        ";
        $st = $conn->prepare($sql);
        $st->bind_param("sssdiisissii",
            $transaction_number, $transaction_date, $transaction_type,
            $amount, $counterparty_id, $cash_register_id,
            $payment_method, $expense_category, $description, $conducted, $user_id,
            $id
        );
        $st->execute();
        if ($st->error) {
            throw new Exception("Ошибка при UPDATE транзакции: " . $st->error);
        }
    } else {
        $sql = "
            INSERT INTO PCRM_FinancialTransaction
            (transaction_number, transaction_date, transaction_type,
             amount, counterparty_id, cash_register_id, 
             payment_method, expense_category, description, conducted, user_id, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())
        ";
        $st = $conn->prepare($sql);
        $st->bind_param("sssdiisissii",
            $transaction_number, $transaction_date, $transaction_type,
            $amount, $counterparty_id, $cash_register_id,
            $payment_method, $expense_category, $description, $conducted, $user_id
        );
        $st->execute();
        if ($st->error) {
            throw new Exception("Ошибка при INSERT транзакции: " . $st->error);
        }
        $id = $st->insert_id;
    }

    // Если метод оплаты гибридный, обрабатываем дополнительные детали
    if ($payment_method === 'hybrid') {
        // Удаляем старые записи о платежах
        $delStmt = $conn->prepare("DELETE FROM PCRM_PaymentMethodDetails WHERE transaction_id=?");
        $delStmt->bind_param("i", $id);
        $delStmt->execute();
        if ($delStmt->error) {
            throw new Exception("Ошибка при удалении деталей платежа: " . $delStmt->error);
        }
        
        // Добавляем новые записи
        $insStmt = $conn->prepare("
            INSERT INTO PCRM_PaymentMethodDetails
            (transaction_id, payment_method, amount, description, created_at)
            VALUES (?,?,?,?,NOW())
        ");
        
        foreach ($payment_details as $detail) {
            $method = $detail['payment_method'];
            $detAmount = (float)$detail['amount'];
            $detDesc = $detail['description'] ?? '';
            
            if ($detAmount <= 0) continue;
            
            $insStmt->bind_param("isds", $id, $method, $detAmount, $detDesc);
            $insStmt->execute();
            if ($insStmt->error) {
                throw new Exception("Ошибка при добавлении деталей платежа: " . $insStmt->error);
            }
        }
    }
    
    // Создаем связи с другими документами, если указаны
if ($based_on) {
    $sourceType = ''; 
    $sourceId = 0;
    
    switch ($based_on) {
        case 'order':
            if ($order_id > 0) {
                $sourceType = 'order';
                $sourceId = $order_id;
            }
            break;
        case 'shipment':
            if ($shipment_id > 0) {
                $sourceType = 'shipment';
                $sourceId = $shipment_id;
            }
            break;
        case 'return':
            if ($return_id > 0) {
                $sourceType = 'return';
                $sourceId = $return_id;
            }
            break;
    }
    
    if ($sourceType && $sourceId > 0) {
        // Проверяем, существует ли уже связь
        $checkRelSql = "SELECT id FROM PCRM_RelatedDocuments WHERE 
                        (source_type=? AND source_id=? AND related_type='finance' AND related_id=?) OR
                        (source_type='finance' AND source_id=? AND related_type=? AND related_id=?)";
        $checkRelStmt = $conn->prepare($checkRelSql);
        $checkRelStmt->bind_param("siisis", $sourceType, $sourceId, $id, $id, $sourceType, $sourceId);
        $checkRelStmt->execute();
        $checkRelResult = $checkRelStmt->get_result();
        
        if ($checkRelResult->num_rows == 0) {
            // Создаем связь между документами (двустороннюю)
            $relSql = "INSERT INTO PCRM_RelatedDocuments 
                      (source_type, source_id, related_type, related_id, relation_type, created_at) 
                      VALUES (?, ?, 'finance', ?, 'created_to', NOW())";
            $relStmt = $conn->prepare($relSql);
            $relStmt->bind_param("sii", $sourceType, $sourceId, $id);
            $relStmt->execute();
            if ($relStmt->error) {
                throw new Exception("Ошибка при создании связи с документом: " . $relStmt->error);
            }
            
            // Создаем обратную связь (от финансов к документу-источнику)
            $relSql2 = "INSERT INTO PCRM_RelatedDocuments 
                       (source_type, source_id, related_type, related_id, relation_type, created_at) 
                       VALUES ('finance', ?, ?, ?, 'created_from', NOW())";
            $relStmt2 = $conn->prepare($relSql2);
            $relStmt2->bind_param("isi", $id, $sourceType, $sourceId);
            $relStmt2->execute();
            if ($relStmt2->error) {
                throw new Exception("Ошибка при создании обратной связи: " . $relStmt2->error);
            }
        }
    }
}

    // Завершаем транзакцию
    $conn->commit();
    // Сначала сохраняем ID в переменную
    $saved_id = $id;
    // Возвращаем стандартизированный ответ OK для совместимости с интерфейсом
    echo "OK";
} catch (Exception $e) {
    // Откатываем транзакцию в случае ошибки
    $conn->rollback();
    die(json_encode(["status" => "error", "message" => $e->getMessage()]));
}