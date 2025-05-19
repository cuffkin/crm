<?php
// /crm/modules/products/save.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!check_access($conn, $_SESSION['user_id'], 'products')) {
    die("Нет доступа");
}

// Обработка только смены статуса (AJAX)
if (isset($_POST['status_only']) && $_POST['status_only'] == 1 && isset($_POST['id'], $_POST['status'])) {
  $id = (int)$_POST['id'];
  $status = $_POST['status'] === 'active' ? 'active' : 'inactive';
  $st = $conn->prepare("UPDATE PCRM_Product SET status=? WHERE id=?");
  $st->bind_param("si", $status, $id);
  if ($st->execute()) {
    echo 'OK';
  } else {
    echo 'Ошибка: ' . $conn->error;
  }
  exit;
}

// Получаем данные из POST
$id             = (int)($_POST['id'] ?? 0);
$name           = trim($_POST['name'] ?? '');
$sku            = trim($_POST['sku'] ?? '');
$category       = $_POST['category'] ? (int)$_POST['category'] : null;
$subcategory    = $_POST['subcategory'] ? (int)$_POST['subcategory'] : null;
$price          = $_POST['price'] ?? '0.00';
$cost_price     = $_POST['cost_price'] ?? '0.00';
$description    = trim($_POST['description'] ?? '');
$weight         = $_POST['weight'] ?? '0.000';
$volume         = $_POST['volume'] ?? '0.000';
$status         = trim($_POST['status'] ?? 'active');

// Работа с единицами измерения
$useOldUnitFormat = isset($_POST['unit_of_measure']);
$default_measurement_id = null;
$unit_of_measure = 'шт';

if ($useOldUnitFormat) {
    // Используем старое поле
    $unit_of_measure = trim($_POST['unit_of_measure'] ?? 'шт');
} else {
    // Используем новое поле
    $default_measurement_id = isset($_POST['default_measurement_id']) && !empty($_POST['default_measurement_id']) 
        ? (int)$_POST['default_measurement_id'] 
        : null;
    
    // Получаем дополнительные единицы измерения
    $additionalMeasurements = isset($_POST['additional_measurements']) 
        ? json_decode($_POST['additional_measurements'], true) 
        : [];
}

// Проверка обязательных полей
if ($name === '') {
    die("Название товара не может быть пустым");
}

// Начинаем транзакцию
$conn->begin_transaction();

try {
    // UPDATE или INSERT
    if ($id > 0) {
        // Проверяем, существует ли поле default_measurement_id в таблице PCRM_Product
        $checkColumnSQL = "SHOW COLUMNS FROM PCRM_Product LIKE 'default_measurement_id'";
        $columnExists = $conn->query($checkColumnSQL)->num_rows > 0;
        
        if (!$columnExists || $useOldUnitFormat) {
            // Используем старый формат
            $sql = "UPDATE PCRM_Product
                    SET name=?, sku=?, category=?, subcategory=?, price=?, cost_price=?, 
                        description=?, unit_of_measure=?, weight=?, volume=?, status=?
                    WHERE id=?";
            $stt = $conn->prepare($sql);
            $stt->bind_param("ssiiddssddsi",
                $name, $sku, $category, $subcategory, $price, $cost_price, 
                $description, $unit_of_measure, $weight, $volume, $status, $id
            );
        } else {
            // Используем новый формат с default_measurement_id
            $sql = "UPDATE PCRM_Product
                    SET name=?, sku=?, category=?, subcategory=?, price=?, cost_price=?, 
                        description=?, default_measurement_id=?, weight=?, volume=?, status=?
                    WHERE id=?";
            $stt = $conn->prepare($sql);
            $stt->bind_param("ssiiddsiddsi",
                $name, $sku, $category, $subcategory, $price, $cost_price, 
                $description, $default_measurement_id, $weight, $volume, $status, $id
            );
        }
        
        $stt->execute();
        if ($stt->error) {
            throw new Exception("Ошибка обновления товара: " . $stt->error);
        }
        $stt->close();
        
        // Добавляем обработку дополнительных единиц измерения
        if (!$useOldUnitFormat && isset($additionalMeasurements) && is_array($additionalMeasurements)) {
            // Удаляем все текущие связи для товара
            $deleteSql = "DELETE FROM PCRM_Product_Measurement WHERE product_id = ?";
            $deleteStmt = $conn->prepare($deleteSql);
            $deleteStmt->bind_param("i", $id);
            $deleteStmt->execute();
            if ($deleteStmt->error) {
                throw new Exception("Ошибка при удалении текущих единиц измерения: " . $deleteStmt->error);
            }
            $deleteStmt->close();
            
            // Добавляем новые связи
            if (!empty($additionalMeasurements)) {
                // SQL для добавления связи
                $insertLinkSql = "INSERT INTO PCRM_Product_Measurement 
                                  (product_id, measurement_id, is_primary, conversion_factor) 
                                  VALUES (?, ?, ?, ?)";
                $insertLinkStmt = $conn->prepare($insertLinkSql);
                
                foreach ($additionalMeasurements as $am) {
                    $measurementId = (int)$am['measurement_id'];
                    $isPrimary = $measurementId === $default_measurement_id ? 1 : 0;
                    $conversionFactor = $am['conversion_factor'] ?? 1.0;
                    
                    $insertLinkStmt->bind_param("iiid", $id, $measurementId, $isPrimary, $conversionFactor);
                    $insertLinkStmt->execute();
                    
                    if ($insertLinkStmt->error) {
                        throw new Exception("Ошибка при добавлении связи с единицей измерения: " . $insertLinkStmt->error);
                    }
                }
                
                $insertLinkStmt->close();
            }
            
            // Добавляем основную единицу измерения в таблицу связей, если её там нет
            if ($default_measurement_id) {
                $checkDefaultSql = "SELECT id FROM PCRM_Product_Measurement 
                                  WHERE product_id = ? AND measurement_id = ?";
                $checkDefaultStmt = $conn->prepare($checkDefaultSql);
                $checkDefaultStmt->bind_param("ii", $id, $default_measurement_id);
                $checkDefaultStmt->execute();
                $checkDefaultResult = $checkDefaultStmt->get_result();
                
                if ($checkDefaultResult->num_rows === 0) {
                    // Добавляем основную единицу со значением is_primary = 1
                    $insertDefaultSql = "INSERT INTO PCRM_Product_Measurement 
                                      (product_id, measurement_id, is_primary, conversion_factor) 
                                      VALUES (?, ?, 1, 1.0)";
                    $insertDefaultStmt = $conn->prepare($insertDefaultSql);
                    $insertDefaultStmt->bind_param("ii", $id, $default_measurement_id);
                    $insertDefaultStmt->execute();
                    
                    if ($insertDefaultStmt->error) {
                        throw new Exception("Ошибка при добавлении основной единицы измерения: " . $insertDefaultStmt->error);
                    }
                    
                    $insertDefaultStmt->close();
                }
                
                $checkDefaultStmt->close();
            }
        }
    } else {
        // Проверяем, существует ли поле default_measurement_id в таблице PCRM_Product
        $checkColumnSQL = "SHOW COLUMNS FROM PCRM_Product LIKE 'default_measurement_id'";
        $columnExists = $conn->query($checkColumnSQL)->num_rows > 0;
        
        if (!$columnExists || $useOldUnitFormat) {
            // Используем старый формат
            $sql = "INSERT INTO PCRM_Product
                    (name, sku, category, subcategory, price, cost_price, 
                     description, unit_of_measure, weight, volume, status)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)";
            $stt = $conn->prepare($sql);
            $stt->bind_param("ssiiddssdds",
                $name, $sku, $category, $subcategory, $price, $cost_price, 
                $description, $unit_of_measure, $weight, $volume, $status
            );
        } else {
            // Используем новый формат с default_measurement_id
            $sql = "INSERT INTO PCRM_Product
                    (name, sku, category, subcategory, price, cost_price, 
                     description, default_measurement_id, weight, volume, status)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)";
            $stt = $conn->prepare($sql);
            $stt->bind_param("ssiiddsidds",
                $name, $sku, $category, $subcategory, $price, $cost_price, 
                $description, $default_measurement_id, $weight, $volume, $status
            );
        }
        
        $stt->execute();
        if ($stt->error) {
            throw new Exception("Ошибка добавления товара: " . $stt->error);
        }
        
        $id = $conn->insert_id;
        $stt->close();
        
        // Добавляем связи с единицами измерения
        if (!$useOldUnitFormat && isset($additionalMeasurements) && is_array($additionalMeasurements) && !empty($additionalMeasurements)) {
            // SQL для добавления связи
            $insertLinkSql = "INSERT INTO PCRM_Product_Measurement 
                              (product_id, measurement_id, is_primary, conversion_factor) 
                              VALUES (?, ?, ?, ?)";
            $insertLinkStmt = $conn->prepare($insertLinkSql);
            
            foreach ($additionalMeasurements as $am) {
                $measurementId = (int)$am['measurement_id'];
                $isPrimary = $measurementId === $default_measurement_id ? 1 : 0;
                $conversionFactor = $am['conversion_factor'] ?? 1.0;
                
                $insertLinkStmt->bind_param("iiid", $id, $measurementId, $isPrimary, $conversionFactor);
                $insertLinkStmt->execute();
                
                if ($insertLinkStmt->error) {
                    throw new Exception("Ошибка при добавлении связи с единицей измерения: " . $insertLinkStmt->error);
                }
            }
            
            $insertLinkStmt->close();
        }
        
        // Добавляем основную единицу измерения в таблицу связей, если её там нет и она указана
        if (!$useOldUnitFormat && $default_measurement_id) {
            // Проверяем, есть ли уже запись для этой единицы
            $checkDefaultSql = "SELECT id FROM PCRM_Product_Measurement 
                              WHERE product_id = ? AND measurement_id = ?";
            $checkDefaultStmt = $conn->prepare($checkDefaultSql);
            $checkDefaultStmt->bind_param("ii", $id, $default_measurement_id);
            $checkDefaultStmt->execute();
            $checkDefaultResult = $checkDefaultStmt->get_result();
            
            if ($checkDefaultResult->num_rows === 0) {
                // Добавляем основную единицу со значением is_primary = 1
                $insertDefaultSql = "INSERT INTO PCRM_Product_Measurement 
                                  (product_id, measurement_id, is_primary, conversion_factor) 
                                  VALUES (?, ?, 1, 1.0)";
                $insertDefaultStmt = $conn->prepare($insertDefaultSql);
                $insertDefaultStmt->bind_param("ii", $id, $default_measurement_id);
                $insertDefaultStmt->execute();
                
                if ($insertDefaultStmt->error) {
                    throw new Exception("Ошибка при добавлении основной единицы измерения: " . $insertDefaultStmt->error);
                }
                
                $insertDefaultStmt->close();
            }
            
            $checkDefaultStmt->close();
        }
    }
    
    // Завершаем транзакцию
    $conn->commit();
    echo "OK";
} catch (Exception $e) {
    // Отменяем транзакцию в случае ошибки
    $conn->rollback();
    die("Ошибка: " . $e->getMessage());
}