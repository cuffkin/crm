<?php
// Файл /crm/modules/shipments/api_handler.php - расширенная версия
// Эмулирует get_last_shipment_id.php, get_order_items.php и get_shipment_info.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Определяем какой файл эмулировать
$requestFile = basename($_SERVER['SCRIPT_NAME']);

if ($requestFile === 'get_last_shipment_id.php') {
    // Эмулируем функциональность get_last_shipment_id.php
    if (!check_access($conn, $_SESSION['user_id'], 'shipments')) {
        die("0");
    }

    $res = $conn->query("SELECT id FROM PCRM_ShipmentHeader ORDER BY id DESC LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo $row['id'];
    } else {
        echo "0";
    }
} 
else if ($requestFile === 'get_order_items.php') {
    // Эмулируем функциональность get_order_items.php
    if (!check_access($conn, $_SESSION['user_id'], 'shipments')) {
        die(json_encode(["status" => "error", "message" => "Нет доступа"]));
    }

    $order_id = (int)($_GET['order_id'] ?? 0);
    if ($order_id <= 0) {
        die(json_encode(["status" => "error", "message" => "Некорректный ID заказа"]));
    }

    // Получаем товары из заказа
    $sqlItems = "
        SELECT i.product_id, i.quantity, i.price, i.discount, p.name AS product_name
        FROM PCRM_OrderItem i
        LEFT JOIN PCRM_Product p ON i.product_id = p.id
        WHERE i.order_id = ?
        ORDER BY i.id ASC
    ";
    $st = $conn->prepare($sqlItems);
    $st->bind_param("i", $order_id);
    $st->execute();
    $result = $st->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(["status" => "ok", "items" => $items]);
}
else if ($requestFile === 'get_shipment_info.php') {
    // Новый обработчик для получения информации об отгрузке
    if (!check_access($conn, $_SESSION['user_id'], 'shipments')) {
        die(json_encode(["status" => "error", "message" => "Нет доступа"]));
    }
    
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        die(json_encode(["status" => "error", "message" => "Некорректный ID отгрузки"]));
    }
    
    // Получаем информацию об отгрузке
    $sql = "
        SELECT sh.*, 
               o.order_number,
               w.name AS warehouse_name,
               l.name AS loader_name,
               (SELECT SUM((s.quantity * s.price) - s.discount) 
                FROM PCRM_Shipments s 
                WHERE s.shipment_header_id = sh.id) AS total_amount
        FROM PCRM_ShipmentHeader sh
        LEFT JOIN PCRM_Order o ON sh.order_id = o.id
        LEFT JOIN PCRM_Warehouse w ON sh.warehouse_id = w.id
        LEFT JOIN PCRM_Loaders l ON sh.loader_id = l.id
        WHERE sh.id = ?
    ";
    $st = $conn->prepare($sql);
    $st->bind_param("i", $id);
    $st->execute();
    $result = $st->get_result();
    
    if ($result->num_rows === 0) {
        die(json_encode(["status" => "error", "message" => "Отгрузка не найдена"]));
    }
    
    $shipmentInfo = $result->fetch_assoc();
    
    // Возвращаем данные отгрузки
    echo json_encode(["status" => "ok", "data" => $shipmentInfo]);
}
else {
    // Если вызван непосредственно api_handler.php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        // Обработчик POST запросов с action
        $action = $_POST['action'];
        
        if ($action === 'create_from_order') {
            // Создание отгрузки на основе заказа
            error_log("[SHIPMENT_API] Начало создания отгрузки из заказа");
            
            if (!check_access($conn, $_SESSION['user_id'], 'shipments')) {
                error_log("[SHIPMENT_API] Ошибка доступа для пользователя: " . $_SESSION['user_id']);
                die(json_encode(["status" => "error", "message" => "Нет доступа"]));
            }
            
            $order_id = (int)($_POST['order_id'] ?? 0);
            error_log("[SHIPMENT_API] ID заказа: " . $order_id);
            
            if ($order_id <= 0) {
                error_log("[SHIPMENT_API] Некорректный ID заказа: " . $order_id);
                die(json_encode(["status" => "error", "message" => "Некорректный ID заказа"]));
            }
            
            // Получаем информацию о заказе
            $sqlOrder = "
                SELECT o.*, cc.name AS customer_name, w.name AS warehouse_name
                FROM PCRM_Order o
                LEFT JOIN PCRM_Counterparty cc ON o.customer = cc.id
                LEFT JOIN PCRM_Warehouse w ON o.warehouse = w.id
                WHERE o.id = ? AND o.deleted = 0
            ";
            error_log("[SHIPMENT_API] Выполняем запрос информации о заказе");
            
            $st = $conn->prepare($sqlOrder);
            if (!$st) {
                error_log("[SHIPMENT_API] Ошибка подготовки запроса заказа: " . $conn->error);
                die(json_encode(["status" => "error", "message" => "Ошибка подготовки запроса: " . $conn->error]));
            }
            
            $st->bind_param("i", $order_id);
            $st->execute();
            $orderResult = $st->get_result();
            
            if ($orderResult->num_rows === 0) {
                error_log("[SHIPMENT_API] Заказ не найден: " . $order_id);
                die(json_encode(["status" => "error", "message" => "Заказ не найден"]));
            }
            
            $order = $orderResult->fetch_assoc();
            error_log("[SHIPMENT_API] Заказ найден: " . $order['order_number']);
            
            // Получаем товары заказа
            $sqlItems = "
                SELECT i.product_id, i.quantity, i.price, i.discount, p.name AS product_name
                FROM PCRM_OrderItem i
                LEFT JOIN PCRM_Product p ON i.product_id = p.id
                WHERE i.order_id = ?
                ORDER BY i.id ASC
            ";
            error_log("[SHIPMENT_API] Получаем товары заказа");
            
            $st = $conn->prepare($sqlItems);
            if (!$st) {
                error_log("[SHIPMENT_API] Ошибка подготовки запроса товаров: " . $conn->error);
                die(json_encode(["status" => "error", "message" => "Ошибка подготовки запроса товаров: " . $conn->error]));
            }
            
            $st->bind_param("i", $order_id);
            $st->execute();
            $itemsResult = $st->get_result();
            $items = $itemsResult->fetch_all(MYSQLI_ASSOC);
            
            if (empty($items)) {
                error_log("[SHIPMENT_API] В заказе нет товаров");
                die(json_encode(["status" => "error", "message" => "В заказе нет товаров"]));
            }
            
            error_log("[SHIPMENT_API] Найдено товаров: " . count($items));
            
            // Получаем последний номер отгрузки
            $res = $conn->query("SELECT id FROM PCRM_ShipmentHeader ORDER BY id DESC LIMIT 1");
            $lastId = ($res && $res->num_rows > 0) ? $res->fetch_assoc()['id'] : 0;
            $newNumber = 'ОТГ-' . str_pad($lastId + 1, 6, '0', STR_PAD_LEFT);
            error_log("[SHIPMENT_API] Новый номер отгрузки: " . $newNumber);
            
            // Проверяем обязательные поля
            if (empty($order['warehouse'])) {
                error_log("[SHIPMENT_API] Отсутствует склад в заказе");
                die(json_encode(["status" => "error", "message" => "Отсутствует склад в заказе"]));
            }
            
            // Создаем заголовок отгрузки
            $sqlHeader = "
                INSERT INTO PCRM_ShipmentHeader 
                (shipment_number, shipment_date, order_id, warehouse_id, loader_id, comment, conducted, created_by)
                VALUES (?, NOW(), ?, ?, ?, ?, 1, ?)
            ";
            error_log("[SHIPMENT_API] Создаем заголовок отгрузки");
            
            $st = $conn->prepare($sqlHeader);
            if (!$st) {
                error_log("[SHIPMENT_API] Ошибка подготовки запроса заголовка: " . $conn->error);
                die(json_encode(["status" => "error", "message" => "Ошибка подготовки запроса заголовка: " . $conn->error]));
            }
            
            $st->bind_param("siiiisi", 
                $newNumber,
                $order_id,
                $order['warehouse'],
                $order['driver_id'], // используем водителя как погрузчика
                "Создано автоматически из заказа №" . $order['order_number'],
                $_SESSION['user_id']
            );
            
            if (!$st->execute()) {
                error_log("[SHIPMENT_API] Ошибка создания заголовка отгрузки: " . $st->error);
                die(json_encode(["status" => "error", "message" => "Ошибка создания отгрузки: " . $st->error]));
            }
            
            $shipment_id = $conn->insert_id;
            error_log("[SHIPMENT_API] Создан заголовок отгрузки с ID: " . $shipment_id);
            
            // Добавляем товары в отгрузку
            $sqlItem = "
                INSERT INTO PCRM_Shipments 
                (shipment_header_id, product_id, quantity, price, discount, conducted)
                VALUES (?, ?, ?, ?, ?, 1)
            ";
            error_log("[SHIPMENT_API] Добавляем товары в отгрузку");
            
            $st = $conn->prepare($sqlItem);
            if (!$st) {
                error_log("[SHIPMENT_API] Ошибка подготовки запроса товаров: " . $conn->error);
                die(json_encode(["status" => "error", "message" => "Ошибка подготовки запроса товаров: " . $conn->error]));
            }
            
            foreach ($items as $index => $item) {
                error_log("[SHIPMENT_API] Добавляем товар #{$index}: " . $item['product_name']);
                
                $st->bind_param("iiddd", 
                    $shipment_id,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price'],
                    $item['discount']
                );
                
                if (!$st->execute()) {
                    error_log("[SHIPMENT_API] Ошибка добавления товара #{$index}: " . $st->error);
                    die(json_encode(["status" => "error", "message" => "Ошибка добавления товара: " . $st->error]));
                }
            }
            
            error_log("[SHIPMENT_API] Отгрузка создана успешно с ID: " . $shipment_id);
            echo json_encode(["status" => "ok", "shipment_id" => $shipment_id, "message" => "Отгрузка создана успешно"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Неизвестное действие: " . $action]);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => "Прямой доступ к этому файлу запрещен"]);
    }
}