<?php
// /crm/save_form_state.php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';

// Проверяем авторизацию пользователя
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Пользователь не авторизован']);
    exit;
}

// ID пользователя
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $_SESSION['user_id'];

// Проверяем, совпадает ли запрошенный ID с ID авторизованного пользователя
if ($user_id != $_SESSION['user_id']) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Доступ запрещен']);
    exit;
}

// Определяем действие
$action = $_SERVER['REQUEST_METHOD'] === 'GET' ? ($_GET['action'] ?? 'restore') : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем данные из JSON
    $post_data = json_decode(file_get_contents('php://input'), true);
    $action = $post_data['action'] ?? 'save';
}

// Создаем таблицу для сохранения состояния форм, если ее еще нет
createFormStateTableIfNotExists($conn);

// Обрабатываем запрос в зависимости от действия
switch ($action) {
    case 'restore':
        // Восстановление данных
        restoreFormState($conn, $user_id);
        break;
    
    case 'save':
    case 'sync':
        // Сохранение данных
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            saveFormState($conn, $user_id, $post_data);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Метод не поддерживается']);
        }
        break;
    
    case 'save_tabs':
        // Сохранение вкладок
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            saveTabsState($conn, $user_id, $post_data);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Метод не поддерживается']);
        }
        break;
    
    case 'clear':
        // Очистка сохраненных данных
        clearFormState($conn, $user_id);
        break;
    
    default:
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Неизвестное действие']);
        break;
}

// Функция для создания таблицы, если ее еще нет
function createFormStateTableIfNotExists($conn) {
    $sql = "
    CREATE TABLE IF NOT EXISTS PCRM_FormState (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        state_key VARCHAR(255) NOT NULL,
        state_data LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (user_id, state_key)
    )
    ";
    
    try {
        $conn->query($sql);
    } catch (Exception $e) {
        error_log("Ошибка при создании таблицы PCRM_FormState: " . $e->getMessage());
    }
}

// Функция для восстановления состояния форм
function restoreFormState($conn, $user_id) {
    header('Content-Type: application/json');
    
    try {
        // Получаем данные о формах
        $forms_sql = "SELECT state_data FROM PCRM_FormState WHERE user_id = ? AND state_key = 'forms'";
        $forms_stmt = $conn->prepare($forms_sql);
        $forms_stmt->bind_param("i", $user_id);
        $forms_stmt->execute();
        $forms_result = $forms_stmt->get_result();
        
        $forms_data = null;
        if ($forms_result->num_rows > 0) {
            $forms_row = $forms_result->fetch_assoc();
            $forms_data = json_decode($forms_row['state_data'], true);
        }
        
        // Получаем данные о вкладках
        $tabs_sql = "SELECT state_data FROM PCRM_FormState WHERE user_id = ? AND state_key = 'tabs'";
        $tabs_stmt = $conn->prepare($tabs_sql);
        $tabs_stmt->bind_param("i", $user_id);
        $tabs_stmt->execute();
        $tabs_result = $tabs_stmt->get_result();
        
        $tabs_data = null;
        if ($tabs_result->num_rows > 0) {
            $tabs_row = $tabs_result->fetch_assoc();
            $tabs_data = json_decode($tabs_row['state_data'], true);
        }
        
        // Формируем ответ
        $response = [
            'status' => 'ok',
            'data' => [
                'forms' => $forms_data,
                'tabs' => $tabs_data
            ]
        ];
        
        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Ошибка при восстановлении данных: ' . $e->getMessage()]);
    }
}

// Функция для сохранения состояния форм
function saveFormState($conn, $user_id, $data) {
    header('Content-Type: application/json');
    
    try {
        // Сохраняем данные о формах, если они есть
        if (isset($data['data']['forms'])) {
            $forms_data = json_encode($data['data']['forms']);
            
            $forms_sql = "INSERT INTO PCRM_FormState (user_id, state_key, state_data) 
                          VALUES (?, 'forms', ?) 
                          ON DUPLICATE KEY UPDATE state_data = ?";
            $forms_stmt = $conn->prepare($forms_sql);
            $forms_stmt->bind_param("iss", $user_id, $forms_data, $forms_data);
            $forms_stmt->execute();
        }
        
        // Сохраняем данные о вкладках, если они есть
        if (isset($data['data']['tabs'])) {
            $tabs_data = json_encode($data['data']['tabs']);
            
            $tabs_sql = "INSERT INTO PCRM_FormState (user_id, state_key, state_data) 
                         VALUES (?, 'tabs', ?) 
                         ON DUPLICATE KEY UPDATE state_data = ?";
            $tabs_stmt = $conn->prepare($tabs_sql);
            $tabs_stmt->bind_param("iss", $user_id, $tabs_data, $tabs_data);
            $tabs_stmt->execute();
        }
        
        echo json_encode(['status' => 'ok', 'message' => 'Данные успешно сохранены']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Ошибка при сохранении данных: ' . $e->getMessage()]);
    }
}

// Функция для сохранения состояния вкладок
function saveTabsState($conn, $user_id, $data) {
    header('Content-Type: application/json');
    
    try {
        // Проверяем наличие данных о вкладках
        if (!isset($data['tabs'])) {
            echo json_encode(['status' => 'error', 'message' => 'Данные о вкладках отсутствуют']);
            return;
        }
        
        $tabs_data = json_encode($data['tabs']);
        
        $tabs_sql = "INSERT INTO PCRM_FormState (user_id, state_key, state_data) 
                     VALUES (?, 'tabs', ?) 
                     ON DUPLICATE KEY UPDATE state_data = ?";
        $tabs_stmt = $conn->prepare($tabs_sql);
        $tabs_stmt->bind_param("iss", $user_id, $tabs_data, $tabs_data);
        $tabs_stmt->execute();
        
        echo json_encode(['status' => 'ok', 'message' => 'Вкладки успешно сохранены']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Ошибка при сохранении вкладок: ' . $e->getMessage()]);
    }
}

// Функция для очистки сохраненных данных
function clearFormState($conn, $user_id) {
    header('Content-Type: application/json');
    
    try {
        $sql = "DELETE FROM PCRM_FormState WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        echo json_encode(['status' => 'ok', 'message' => 'Данные успешно очищены']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Ошибка при очистке данных: ' . $e->getMessage()]);
    }
}