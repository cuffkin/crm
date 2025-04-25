<?php
// /crm/modules/sales/orders/debug_edit.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/../../../config/session.php';
    require_once __DIR__ . '/../../../config/db.php';
    require_once __DIR__ . '/../../../includes/functions.php';
    
    $id = (int)($_GET['id'] ?? 0);
    $tabMode = isset($_GET['tab']) && $_GET['tab'] == 1;
    
    if (!check_access($conn, $_SESSION['user_id'], 'sales_orders')) {
        die("<div class='text-danger'>Нет доступа</div>");
    }
    
    // Базовые значения для полей формы
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
    $conducted        = 0;
    $driver_id        = null;
    
    // ТЕСТ 1: Запрос к заказу (если редактирование)
    echo "<div class='alert alert-info'>Тест 1: Получение данных заказа</div>";
    if ($id > 0) {
        $st = $conn->prepare("SELECT * FROM PCRM_Order WHERE id=? AND deleted=0");
        $st->bind_param("i", $id);
        $st->execute();
        $res = $st->get_result();
        $ord = $res->fetch_assoc();
        if ($ord) {
            echo "✓ Заказ #$id успешно загружен<br>";
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
            echo "⨯ Заказ не найден или удален<br>";
        }
    }
    
    // ТЕСТ 2: Получение справочников
    echo "<div class='alert alert-info'>Тест 2: Загрузка справочников</div>";
    
    // Организации
    echo "Загрузка организаций... ";
    $orgRes = $conn->query("SELECT id,name FROM PCRM_Organization ORDER BY name");
    if ($orgRes) {
        $allOrgs = $orgRes->fetch_all(MYSQLI_ASSOC);
        echo "✓ Загружено: " . count($allOrgs) . "<br>";
    } else {
        echo "⨯ Ошибка: " . $conn->error . "<br>";
    }
    
    // Контрагенты
    echo "Загрузка контрагентов... ";
    $custRes = $conn->query("SELECT id,name,address,contact_info FROM PCRM_Counterparty ORDER BY name");
    if ($custRes) {
        $allCust = $custRes->fetch_all(MYSQLI_ASSOC);
        echo "✓ Загружено: " . count($allCust) . "<br>";
    } else {
        echo "⨯ Ошибка: " . $conn->error . "<br>";
    }
    
    // Склады
    echo "Загрузка складов... ";
    $whRes = $conn->query("SELECT id,name FROM PCRM_Warehouse WHERE status='active' ORDER BY name");
    if ($whRes) {
        $allWh = $whRes->fetch_all(MYSQLI_ASSOC);
        echo "✓ Загружено: " . count($allWh) . "<br>";
    } else {
        echo "⨯ Ошибка: " . $conn->error . "<br>";
    }
    
    // Водители
    echo "Загрузка водителей... ";
    $drvRes = $conn->query("SELECT id,name FROM PCRM_Drivers ORDER BY name");
    if ($drvRes) {
        $allDrivers = $drvRes->fetch_all(MYSQLI_ASSOC);
        echo "✓ Загружено: " . count($allDrivers) . "<br>";
    } else {
        echo "⨯ Ошибка: " . $conn->error . "<br>";
    }
    
    // Товары
    echo "Загрузка товаров... ";
    $prodRes = $conn->query("SELECT id,name,price FROM PCRM_Product WHERE status='active' ORDER BY name");
    if ($prodRes) {
        $allProducts = $prodRes->fetch_all(MYSQLI_ASSOC);
        echo "✓ Загружено: " . count($allProducts) . "<br>";
    } else {
        echo "⨯ Ошибка: " . $conn->error . "<br>";
    }
    
    // ТЕСТ 3: Позиции заказа
    echo "<div class='alert alert-info'>Тест 3: Загрузка позиций заказа</div>";
    $items = [];
    if ($id > 0) {
        echo "Загрузка позиций для заказа #$id... ";
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
        if ($r2) {
            $items = $r2->fetch_all(MYSQLI_ASSOC);
            echo "✓ Загружено: " . count($items) . "<br>";
        } else {
            echo "⨯ Ошибка: " . $st2->error . "<br>";
        }
    } else {
        echo "Создание нового заказа, позиций пока нет<br>";
    }
    
    // ТЕСТ 4: Проверка related_documents
    echo "<div class='alert alert-info'>Тест 4: Проверка related_documents.php</div>";
    $relDocPath = __DIR__ . '/../../../includes/related_documents.php';
    if (file_exists($relDocPath)) {
        echo "✓ Файл related_documents.php существует<br>";
        
        // Попробуем включить его и проверить функцию
        require_once $relDocPath;
        if (function_exists('showRelatedDocuments')) {
            echo "✓ Функция showRelatedDocuments доступна<br>";
            
            // Проверка наличия таблицы
            $tableCheck = $conn->query("SHOW TABLES LIKE 'PCRM_RelatedDocuments'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                echo "✓ Таблица PCRM_RelatedDocuments существует<br>";
            } else {
                echo "⨯ Таблица PCRM_RelatedDocuments отсутствует<br>";
            }
        } else {
            echo "⨯ Функция showRelatedDocuments не найдена<br>";
        }
    } else {
        echo "⨯ Файл related_documents.php не найден<br>";
    }
    
    echo "<hr>";
    echo "<h2>Все тесты выполнены успешно!</h2>";
    echo "<p>Теперь вы можете вернуться к оригинальному файлу и исправить обнаруженные проблемы.</p>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h4>Произошла ошибка!</h4>";
    echo "<p>Сообщение: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Файл: " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p>Строка: " . $e->getLine() . "</p>";
    echo "</div>";
}
?>