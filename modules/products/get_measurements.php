<?php
// /crm/modules/products/get_measurements.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Проверяем доступ
if (!check_access($conn, $_SESSION['user_id'], 'products')) {
    die(json_encode(['error' => 'Нет доступа']));
}

// Добавляем отладочную информацию
$debug = [];

// Проверка существования таблицы единиц измерения (проверяем оба возможных написания)
$checkMeasurementTable1 = "SHOW TABLES LIKE 'PCRM_Measurement'";
$checkMeasurementTable2 = "SHOW TABLES LIKE 'PRCM_Measurement'";

$measurementTableExists1 = $conn->query($checkMeasurementTable1)->num_rows > 0;
$measurementTableExists2 = $conn->query($checkMeasurementTable2)->num_rows > 0;

$debug['table_check_1'] = $measurementTableExists1;
$debug['table_check_2'] = $measurementTableExists2;

$result = [];
$tableName = '';

// Определяем, какая таблица существует
if ($measurementTableExists1) {
    $tableName = 'PCRM_Measurement';
} elseif ($measurementTableExists2) {
    $tableName = 'PRCM_Measurement';
}

$debug['found_table'] = $tableName;

if (!empty($tableName)) {
    // Получаем все активные единицы измерения
    $measurementSql = "SELECT id, name, short_name FROM {$tableName} WHERE status = 'active' ORDER BY name";
    $debug['sql_query'] = $measurementSql;
    
    try {
        $measurementResult = $conn->query($measurementSql);
        
        if ($measurementResult) {
            while ($row = $measurementResult->fetch_assoc()) {
                $result[] = $row;
            }
            $debug['rows_found'] = count($result);
        } else {
            $debug['query_error'] = $conn->error;
        }
    } catch (Exception $e) {
        $debug['exception'] = $e->getMessage();
    }
}

// Если не нашли данных в таблице или таблица не существует, используем стандартный набор
if (empty($result)) {
    $debug['using_default_units'] = true;
    
    // Если таблицы нет, возвращаем стандартный набор единиц измерения
    $commonUnits = [
        ['id' => 1, 'name' => 'Штука', 'short_name' => 'шт'],
        ['id' => 2, 'name' => 'Килограмм', 'short_name' => 'кг'],
        ['id' => 3, 'name' => 'Грамм', 'short_name' => 'г'],
        ['id' => 4, 'name' => 'Литр', 'short_name' => 'л'],
        ['id' => 5, 'name' => 'Миллилитр', 'short_name' => 'мл'],
        ['id' => 6, 'name' => 'Метр', 'short_name' => 'м'],
        ['id' => 7, 'name' => 'Сантиметр', 'short_name' => 'см'],
        ['id' => 8, 'name' => 'Квадратный метр', 'short_name' => 'м²'],
        ['id' => 9, 'name' => 'Кубический метр', 'short_name' => 'м³'],
        ['id' => 10, 'name' => 'Тонна', 'short_name' => 'т'],
        ['id' => 11, 'name' => 'Мешок', 'short_name' => 'меш.'],
        ['id' => 12, 'name' => 'Упаковка', 'short_name' => 'уп.'],
        ['id' => 13, 'name' => 'Комплект', 'short_name' => 'компл.'],
    ];
    
    $result = $commonUnits;
    $debug['default_units_count'] = count($result);
}

// Возвращаем результат в формате JSON с отладочной информацией
$response = [
    'measurements' => $result,
    'debug' => $debug
];

header('Content-Type: application/json');
echo json_encode($response);
exit; 