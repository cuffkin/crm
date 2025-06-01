<?php
// /crm/modules/purchases/orders/save.php
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'purchases_orders')) {
    die("Нет доступа");
}

try {
    // Шапка
    $id          = (int)($_POST['id'] ?? 0);
    $org         = $_POST['organization'] ? (int)$_POST['organization'] : null;
    $num         = trim($_POST['purchase_order_number'] ?? '');
    $date        = $_POST['date'] ?? date('Y-m-d H:i:s');
    $supplier_id = $_POST['supplier_id'] ? (int)$_POST['supplier_id'] : null;
    $warehouse_id = $_POST['warehouse_id'] ? (int)$_POST['warehouse_id'] : null;
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $comment     = trim($_POST['comment'] ?? '');
    $status      = trim($_POST['status'] ?? 'draft');
    $total_amount = (float)($_POST['total_amount'] ?? 0);
    $conducted   = (int)($_POST['conducted'] ?? 0);

    // Позиции
    $itemsJson = $_POST['items'] ?? '[]';
    $itemsArr  = json_decode($itemsJson, true);
    if (!is_array($itemsArr)) {
        $itemsArr = [];
    }

    // Валидация обязательных полей
    if (!$org) {
        die("Выберите организацию");
    }
    if (!$supplier_id) {
        die("Выберите поставщика");
    }
    if (!$warehouse_id) {
        die("Выберите склад");
    }

    // Проверка наличия товаров
    $validItems = array_filter($itemsArr, function($item) {
        return isset($item['product_id']) && (int)$item['product_id'] > 0;
    });
    
    if (count($validItems) == 0) {
        die("Добавьте хотя бы один товар");
    }

    // Генерация номера если пустой
    if (empty($num)) {
        $r = $conn->query("SELECT id FROM PCRM_PurchaseOrder ORDER BY id DESC LIMIT 1");
        $last = $r->fetch_assoc();
        $newId = $last ? ($last['id'] + 1) : 1;
        $num = "PO-" . str_pad($newId, 6, '0', STR_PAD_LEFT);
    }

    // UPDATE или INSERT шапки
    if ($id > 0) {
        $sql = "
          UPDATE PCRM_PurchaseOrder
          SET organization=?, purchase_order_number=?, date=?, 
              supplier_id=?, warehouse_id=?, delivery_address=?, 
              comment=?, status=?, total_amount=?, conducted=?
          WHERE id=? AND deleted=0
        ";
        $st = $conn->prepare($sql);
        if (!$st) {
            die("Ошибка prepare UPDATE: " . $conn->error);
        }
        $st->bind_param("issiisssdii",
            $org, $num, $date,
            $supplier_id, $warehouse_id, $delivery_address,
            $comment, $status, $total_amount, $conducted,
            $id
        );
        $st->execute();
        if ($st->error) {
            die("Ошибка при UPDATE: " . $st->error);
        }
    } else {
        $created_by = $_SESSION['user_id'] ?? null;
        $sql = "
          INSERT INTO PCRM_PurchaseOrder
          (organization, purchase_order_number, date, 
           supplier_id, warehouse_id, delivery_address,
           comment, status, total_amount, conducted,
           created_by, deleted)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,0)
        ";
        $st = $conn->prepare($sql);
        if (!$st) {
            die("Ошибка prepare INSERT: " . $conn->error);
        }
        $st->bind_param("issiisssdii",
            $org, $num, $date,
            $supplier_id, $warehouse_id, $delivery_address,
            $comment, $status, $total_amount, $conducted,
            $created_by
        );
        $st->execute();
        if ($st->error) {
            die("Ошибка при INSERT: " . $st->error);
        }
        $id = $st->insert_id;
    }

    // Сохраняем позиции (PurchaseOrderItem)
    $del = $conn->prepare("DELETE FROM PCRM_PurchaseOrderItem WHERE purchase_order_id=?");
    if (!$del) {
        die("Ошибка prepare DELETE: " . $conn->error);
    }
    $del->bind_param("i", $id);
    $del->execute();
    if ($del->error) {
        die("Ошибка при DELETE позиций: " . $del->error);
    }

    $ins = $conn->prepare("
      INSERT INTO PCRM_PurchaseOrderItem (purchase_order_id, product_id, quantity, price, discount)
      VALUES (?,?,?,?,?)
    ");
    if (!$ins) {
        die("Ошибка prepare INSERT позиций: " . $conn->error);
    }
    
    foreach ($itemsArr as $itm) {
        $pid = (int)($itm['product_id'] ?? 0);
        $qty = (float)($itm['quantity'] ?? 0);
        $prc = (float)($itm['price'] ?? 0);
        $dsc = (float)($itm['discount'] ?? 0);
        
        if ($pid <= 0 || $qty <= 0) {
            continue;
        }
        
        $ins->bind_param("iiddd", $id, $pid, $qty, $prc, $dsc);
        $ins->execute();
        if ($ins->error) {
            die("Ошибка INSERT позиции: " . $ins->error);
        }
    }

    echo "OK";
    
} catch (Exception $e) {
    error_log("Ошибка в save.php заказов поставщиков: " . $e->getMessage());
    die("Ошибка при сохранении заказа: " . $e->getMessage());
} catch (Error $e) {
    error_log("Критическая ошибка в save.php заказов поставщиков: " . $e->getMessage());
    die("Критическая ошибка при сохранении заказа");
}