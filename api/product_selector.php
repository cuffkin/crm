<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Проверяем авторизацию
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Не авторизован']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Отладочная информация для разработки
error_log('Product Selector API: action=' . $action . ', user_id=' . ($_SESSION['user_id'] ?? 'not_set'));

try {
    switch ($action) {
        case 'search':
            searchProducts();
            break;
        case 'recent':
            getRecentProducts();
            break;
        case 'details':
            getProductDetails();
            break;
        case 'save_recent':
            saveRecentProduct();
            break;
        case 'categories':
            getCategories();
            break;
        case 'by_category':
        case 'category_products':
            getProductsByCategory();
            break;
        case 'remove_recent':
            removeRecentProduct();
            break;
        default:
            throw new Exception('Неизвестное действие: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Поиск товаров по названию/артикулу/коду
 */
function searchProducts() {
    global $conn;
    
    $query = trim($_GET['q'] ?? '');
    $limit = (int)($_GET['limit'] ?? 10);
    $context = $_GET['context'] ?? 'sale'; // sale, purchase, production
    
    // Если запрос пустой, возвращаем все товары (для "Показать всё")
    if (empty($query)) {
        getAllProducts($limit, $context);
        return;
    }
    
    // Определяем поля цены в зависимости от контекста
    if ($context === 'purchase' || $context === 'production') {
        $priceField = 'cost_price';
    } else {
        $priceField = 'price';
    }
    
    $sql = "
        SELECT 
            p.id,
            p.name,
            p.sku,
            p.unit_of_measure,
            p.{$priceField} as price,
            COALESCE(SUM(s.quantity), 0) as stock_quantity,
            p.description,
            p.status,
            c.name as category_name,
            c.id as category_id
        FROM PCRM_Product p
        LEFT JOIN PCRM_Categories c ON p.category = c.id
        LEFT JOIN PCRM_Stock s ON p.id = s.prod_id AND s.deleted = 0
        WHERE p.status = 'active' AND p.deleted = 0
        AND (
            p.name LIKE ? 
            OR p.sku LIKE ? 
            OR p.id LIKE ?
        )
        GROUP BY p.id, p.name, p.sku, p.unit_of_measure, p.{$priceField}, p.description, p.status, c.name, c.id
        ORDER BY 
            CASE 
                WHEN p.name LIKE ? THEN 1
                WHEN p.sku LIKE ? THEN 2  
                ELSE 3
            END,
            p.name ASC
        LIMIT ?
    ";
    
    $searchTerm = "%{$query}%";
    $exactTerm = "{$query}%";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Ошибка подготовки SQL запроса: ' . $conn->error);
    }
    
    $stmt->bind_param('sssssi', 
        $searchTerm, $searchTerm, $searchTerm,
        $exactTerm, $exactTerm,
        $limit
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Ошибка выполнения SQL запроса: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $products = [];
    
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'sku' => $row['sku'],
            'unit' => $row['unit_of_measure'],
            'price' => (float)$row['price'],
            'stock' => (int)$row['stock_quantity'],
            'description' => $row['description'],
            'category' => [
                'id' => (int)$row['category_id'],
                'name' => $row['category_name']
            ]
        ];
    }
    
    echo json_encode(['products' => $products]);
}

/**
 * Получение всех товаров (для "Показать всё")
 */
function getAllProducts($limit = 50, $context = 'sale') {
    global $conn;
    
    error_log('Product Selector API: getAllProducts called, limit=' . $limit . ', context=' . $context);
    
    // Определяем поля цены в зависимости от контекста
    if ($context === 'purchase' || $context === 'production') {
        $priceField = 'cost_price';
    } else {
        $priceField = 'price';
    }
    
    $sql = "
        SELECT 
            p.id,
            p.name,
            p.sku,
            p.unit_of_measure,
            p.{$priceField} as price,
            COALESCE(SUM(s.quantity), 0) as stock_quantity,
            p.description,
            p.status,
            c.name as category_name,
            c.id as category_id
        FROM PCRM_Product p
        LEFT JOIN PCRM_Categories c ON p.category = c.id
        LEFT JOIN PCRM_Stock s ON p.id = s.prod_id AND s.deleted = 0
        WHERE p.status = 'active' AND p.deleted = 0
        GROUP BY p.id, p.name, p.sku, p.unit_of_measure, p.{$priceField}, p.description, p.status, c.name, c.id
        ORDER BY p.name ASC
        LIMIT ?
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Ошибка подготовки SQL запроса: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $limit);
    
    if (!$stmt->execute()) {
        throw new Exception('Ошибка выполнения SQL запроса: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $products = [];
    
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'sku' => $row['sku'],
            'unit' => $row['unit_of_measure'],
            'price' => (float)$row['price'],
            'stock' => (int)$row['stock_quantity'],
            'description' => $row['description'],
            'category' => [
                'id' => (int)$row['category_id'],
                'name' => $row['category_name']
            ]
        ];
    }
    
    error_log('Product Selector API: getAllProducts returned ' . count($products) . ' products');
    echo json_encode(['products' => $products]);
}

/**
 * Получение недавних товаров для пользователя
 */
function getRecentProducts() {
    global $conn;
    
    $userId = $_SESSION['user_id'];
    $context = $_GET['context'] ?? 'sale';
    $limit = (int)($_GET['limit'] ?? 3); // Ограничиваем до 3-х товаров
    
    // Создаем таблицу если не существует
    $createTableSql = "
        CREATE TABLE IF NOT EXISTS PCRM_RecentProducts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            context VARCHAR(50) NOT NULL DEFAULT 'sale',
            last_used TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_product_context (user_id, product_id, context),
            INDEX idx_user_context_time (user_id, context, last_used)
        )
    ";
    $conn->query($createTableSql);
    
    if ($context === 'purchase' || $context === 'production') {
        $priceField = 'cost_price';
    } else {
        $priceField = 'price';
    }
    
    $sql = "
        SELECT 
            p.id,
            p.name,
            p.sku,
            p.unit_of_measure,
            p.{$priceField} as price,
            COALESCE(SUM(s.quantity), 0) as stock_quantity,
            c.name as category_name,
            rp.last_used
        FROM PCRM_RecentProducts rp
        JOIN PCRM_Product p ON rp.product_id = p.id
        LEFT JOIN PCRM_Categories c ON p.category = c.id
        LEFT JOIN PCRM_Stock s ON p.id = s.prod_id AND s.deleted = 0
        WHERE rp.user_id = ? 
        AND p.status = 'active' AND p.deleted = 0
        AND rp.context = ?
        GROUP BY p.id, p.name, p.sku, p.unit_of_measure, p.{$priceField}, c.name, rp.last_used
        ORDER BY rp.last_used DESC
        LIMIT ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isi', $userId, $context, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'sku' => $row['sku'],
            'unit' => $row['unit_of_measure'],
            'price' => (float)$row['price'],
            'stock' => (int)$row['stock_quantity'],
            'category_name' => $row['category_name'],
            'last_used' => $row['last_used']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'count' => count($products)
    ]);
}

/**
 * Получение детальной информации о товаре
 */
function getProductDetails() {
    global $conn;
    
    $productId = (int)($_GET['id'] ?? 0);
    $context = $_GET['context'] ?? 'sale';
    
    if (!$productId) {
        throw new Exception('ID товара не указан');
    }
    
    if ($context === 'purchase' || $context === 'production') {
        $priceField = 'cost_price';
    } else {
        $priceField = 'price';
    }
    
    $sql = "
        SELECT 
            p.id,
            p.name,
            p.sku,
            p.unit_of_measure,
            p.price,
            p.cost_price,
            p.{$priceField} as context_price,
            COALESCE(SUM(s.quantity), 0) as stock_quantity,
            p.description,
            p.status,
            p.weight,
            p.volume,
            c.name as category_name,
            c.id as category_id
        FROM PCRM_Product p
        LEFT JOIN PCRM_Categories c ON p.category = c.id
        LEFT JOIN PCRM_Stock s ON p.id = s.prod_id AND s.deleted = 0
        WHERE p.id = ? AND p.deleted = 0
        GROUP BY p.id, p.name, p.sku, p.unit_of_measure, p.price, p.cost_price, p.{$priceField}, p.description, p.status, p.weight, p.volume, c.name, c.id
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'product' => [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'sku' => $row['sku'],
                'unit' => $row['unit_of_measure'],
                'price' => (float)$row['price'],
                'cost_price' => (float)$row['cost_price'],
                'context_price' => (float)$row['context_price'],
                'stock' => (int)$row['stock_quantity'],
                'description' => $row['description'],
                'weight' => (float)$row['weight'],
                'volume' => (float)$row['volume'],
                'category' => [
                    'id' => (int)$row['category_id'],
                    'name' => $row['category_name']
                ]
            ]
        ]);
    } else {
        throw new Exception('Товар не найден');
    }
}

/**
 * Сохранение товара в недавние для пользователя
 */
function saveRecentProduct() {
    global $conn;
    
    $productId = (int)($_POST['product_id'] ?? 0);
    $context = $_POST['context'] ?? 'sale';
    $userId = $_SESSION['user_id'];
    
    if (!$productId) {
        throw new Exception('ID товара не указан');
    }
    
    // Создаем таблицу если не существует (без foreign keys для совместимости)
    $createTableSql = "
        CREATE TABLE IF NOT EXISTS PCRM_RecentProducts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            context VARCHAR(50) NOT NULL DEFAULT 'sale',
            last_used TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_product_context (user_id, product_id, context),
            INDEX idx_user_context_time (user_id, context, last_used)
        )
    ";
    $conn->query($createTableSql);
    
    // Используем INSERT ... ON DUPLICATE KEY UPDATE для обновления времени
    $sql = "
        INSERT INTO PCRM_RecentProducts (user_id, product_id, context, last_used)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE last_used = NOW()
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iis', $userId, $productId, $context);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Ошибка сохранения в недавние товары');
    }
}

/**
 * Получение иерархического дерева категорий для фильтрации
 */
function getCategories() {
    global $conn;
    
    error_log('Product Selector API: getCategories called');
    
    $sql = "
        SELECT 
            c.id,
            c.name,
            c.pc_id as parent_id,
            c.status,
            COUNT(p.id) as products_count
        FROM PCRM_Categories c
        LEFT JOIN PCRM_Product p ON c.id = p.category AND p.status = 'active' AND p.deleted = 0
        GROUP BY c.id, c.name, c.pc_id, c.status
        ORDER BY c.name ASC
    ";
    
    $result = $conn->query($sql);
    if (!$result) {
        error_log('Product Selector API: Categories query failed: ' . $conn->error);
        throw new Exception('Ошибка запроса категорий: ' . $conn->error);
    }
    
    $allCategories = [];
    
    while ($row = $result->fetch_assoc()) {
        $allCategories[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'parent_id' => $row['parent_id'] ? (int)$row['parent_id'] : null,
            'status' => $row['status'],
            'products_count' => (int)$row['products_count']
        ];
    }
    
    error_log('Product Selector API: Found ' . count($allCategories) . ' categories');
    
    // Строим дерево категорий
    function buildCategoryTree($categories, $parentId = null) {
        $tree = [];
        foreach ($categories as $category) {
            if ($category['parent_id'] === $parentId) {
                $children = buildCategoryTree($categories, $category['id']);
                if (!empty($children)) {
                    $category['children'] = $children;
                }
                $tree[] = $category;
            }
        }
        return $tree;
    }
    
    $categoryTree = buildCategoryTree($allCategories, null);
    
    error_log('Product Selector API: Built tree with ' . count($categoryTree) . ' root categories');
    
    echo json_encode([
        'categories' => $categoryTree,
        'total_categories' => count($allCategories)
    ]);
}

/**
 * Получение товаров по категории
 */
function getProductsByCategory() {
    global $conn;
    
    $categoryId = (int)($_GET['category_id'] ?? 0);
    $context = $_GET['context'] ?? 'sale';
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    
    error_log('Product Selector API: getProductsByCategory called, categoryId=' . $categoryId);
    
    if ($context === 'purchase' || $context === 'production') {
        $priceField = 'cost_price';
    } else {
        $priceField = 'price';
    }
    
    // Если categoryId = 0, показываем все товары
    if ($categoryId === 0) {
        $sql = "
            SELECT 
                p.id,
                p.name,
                p.sku,
                p.unit_of_measure,
                p.{$priceField} as price,
                COALESCE(SUM(s.quantity), 0) as stock_quantity,
                p.description
            FROM PCRM_Product p
            LEFT JOIN PCRM_Stock s ON p.id = s.prod_id AND s.deleted = 0
            WHERE p.status = 'active' AND p.deleted = 0
            GROUP BY p.id, p.name, p.sku, p.unit_of_measure, p.{$priceField}, p.description
            ORDER BY p.name ASC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Получаем общее количество всех товаров
        $countSql = "SELECT COUNT(*) as total FROM PCRM_Product WHERE status = 'active' AND deleted = 0";
        $countResult = $conn->query($countSql);
        $total = $countResult->fetch_assoc()['total'];
        
    } else {
        // Получаем все подкатегории (включая саму категорию)
        $allCategoryIds = getAllSubcategoryIds($categoryId);
        error_log('Product Selector API: Found subcategories: ' . implode(',', $allCategoryIds));
        
        // Создаем placeholder'ы для IN запроса
        $placeholders = str_repeat('?,', count($allCategoryIds) - 1) . '?';
        
        $sql = "
            SELECT 
                p.id,
                p.name,
                p.sku,
                p.unit_of_measure,
                p.{$priceField} as price,
                COALESCE(SUM(s.quantity), 0) as stock_quantity,
                p.description
            FROM PCRM_Product p
            LEFT JOIN PCRM_Stock s ON p.id = s.prod_id AND s.deleted = 0
            WHERE p.category IN ({$placeholders})
            AND p.status = 'active' AND p.deleted = 0
            GROUP BY p.id, p.name, p.sku, p.unit_of_measure, p.{$priceField}, p.description
            ORDER BY p.name ASC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $conn->prepare($sql);
        
        // Подготавливаем параметры для bind_param
        $types = str_repeat('i', count($allCategoryIds)) . 'ii';
        $params = array_merge($allCategoryIds, [$limit, $offset]);
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Получаем общее количество товаров в категории и подкатегориях
        $countSql = "SELECT COUNT(*) as total FROM PCRM_Product WHERE category IN ({$placeholders}) AND status = 'active' AND deleted = 0";
        $countStmt = $conn->prepare($countSql);
        $countTypes = str_repeat('i', count($allCategoryIds));
        $countStmt->bind_param($countTypes, ...$allCategoryIds);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $total = $countResult->fetch_assoc()['total'];
    }
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'sku' => $row['sku'],
            'unit' => $row['unit_of_measure'],
            'price' => (float)$row['price'],
            'stock' => (int)$row['stock_quantity'],
            'description' => $row['description']
        ];
    }
    
    error_log('Product Selector API: getProductsByCategory returned ' . count($products) . ' products, total=' . $total);
    
    echo json_encode([
        'products' => $products,
        'total' => (int)$total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

/**
 * Рекурсивное получение всех ID подкатегорий
 */
function getAllSubcategoryIds($categoryId) {
    global $conn;
    
    $allIds = [$categoryId]; // Включаем саму категорию
    
    // Получаем прямых потомков
    $sql = "SELECT id FROM PCRM_Categories WHERE pc_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $childIds = getAllSubcategoryIds($row['id']); // Рекурсивно получаем потомков
        $allIds = array_merge($allIds, $childIds);
    }
    
    return array_unique($allIds);
}

/**
 * Удаление товара из недавних для пользователя
 */
function removeRecentProduct() {
    global $conn;
    
    $productId = (int)($_POST['product_id'] ?? 0);
    $context = $_POST['context'] ?? 'sale';
    $userId = $_SESSION['user_id'];
    
    if (!$productId) {
        throw new Exception('ID товара не указан');
    }
    
    $sql = "DELETE FROM PCRM_RecentProducts WHERE user_id = ? AND product_id = ? AND context = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iis', $userId, $productId, $context);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Товар удален из недавних']);
    } else {
        throw new Exception('Ошибка удаления товара из недавних');
    }
}
?> 