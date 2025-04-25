<?php
// /crm/includes/session-manager.php

// Функция для инициализации менеджера сессий
function initSessionManager() {
    // Увеличиваем время жизни сессии до 8 часов (28800 секунд)
    ini_set('session.gc_maxlifetime', 28800);
    
    // Устанавливаем время жизни cookie сессии
    if (!headers_sent()) {
        session_set_cookie_params(28800);
    }
    
    // Регистрируем функцию, которая будет вызвана при завершении сессии
    register_shutdown_function('saveSessionState');
    
    // Проверяем наличие пользовательского идентификатора в сессии
    if (isset($_SESSION['user_id'])) {
        // Устанавливаем куки с ID пользователя для возможности восстановления сессии
        // при перезагрузке страницы или при проблемах с соединением
        if (!headers_sent()) {
            setcookie('user_id', $_SESSION['user_id'], time() + 86400, '/'); // 24 часа
        }
        
        // Для XHR запросов установим заголовок
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('X-User-ID: ' . $_SESSION['user_id']);
        }
    }
    
    // Проверяем, нужно ли обработать запрос на сохранение или восстановление состояния формы
    if (isset($_SERVER['REQUEST_URI'])) {
        // Определяем тип запроса по URL
        $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Если это запрос к API сохранения состояния форм, обрабатываем его
        if ($requestPath === '/crm/save_form_state.php') {
            require_once __DIR__ . '/../save_form_state.php';
            exit; // Прерываем выполнение, так как запрос уже обработан
        }
    }
}

// Функция для сохранения состояния сессии
function saveSessionState() {
    // Обновляем информацию о текущей сессии пользователя
    if (isset($_SESSION['user_id'])) {
        // Получаем текущее значение счетчика посещений или устанавливаем 0, если его нет
        $visits = isset($_SESSION['visits']) ? $_SESSION['visits'] : 0;
        
        // Увеличиваем счетчик посещений
        $_SESSION['visits'] = $visits + 1;
        
        // Обновляем время последнего посещения
        $_SESSION['last_visit'] = time();
        
        // Сохраняем информацию о текущем пользователе
        $_SESSION['user_info'] = [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? '',
            'role' => $_SESSION['user_role'] ?? '',
            'last_active' => time()
        ];
        
        // Для предотвращения потери данных при неожиданном закрытии страницы,
        // можно сохранить текущее состояние сессии в базе данных
        saveSessionToDatabase();
    }
}

// Функция для сохранения состояния сессии в базе данных
function saveSessionToDatabase() {
    global $conn;
    
    // Проверяем соединение с БД
    if (!isset($conn) || $conn->connect_error) {
        error_log("Ошибка соединения с БД при сохранении сессии");
        return;
    }
    
    try {
        // ID пользователя
        $user_id = $_SESSION['user_id'];
        
        // Создаем таблицу, если её еще нет
        $create_table_sql = "
        CREATE TABLE IF NOT EXISTS PCRM_UserSession (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_id VARCHAR(255) NOT NULL,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY (user_id, session_id)
        )
        ";
        $conn->query($create_table_sql);
        
        // Текущий ID сессии
        $session_id = session_id();
        
        // Подготавливаем данные для сохранения
        $session_data = [
            'user_id' => $user_id,
            'username' => $_SESSION['username'] ?? '',
            'role' => $_SESSION['user_role'] ?? '',
            'visits' => $_SESSION['visits'] ?? 0,
            'last_visit' => $_SESSION['last_visit'] ?? time()
        ];
        
        // Сериализуем данные сессии в JSON
        $data_json = json_encode($session_data);
        
        // Сохраняем/обновляем данные в БД
        $sql = "INSERT INTO PCRM_UserSession (user_id, session_id, data, last_activity) 
                VALUES (?, ?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE data = ?, last_activity = NOW()";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $user_id, $session_id, $data_json, $data_json);
        $stmt->execute();
        
        if ($stmt->error) {
            error_log("Ошибка при сохранении сессии: " . $stmt->error);
        }
    } catch (Exception $e) {
        error_log("Исключение при сохранении сессии: " . $e->getMessage());
    }
}

// Функция для восстановления сессии пользователя
function restoreUserSession($user_id) {
    global $conn;
    
    // Проверяем соединение с БД
    if (!isset($conn) || $conn->connect_error) {
        error_log("Ошибка соединения с БД при восстановлении сессии");
        return false;
    }
    
    try {
        // Ищем последнюю активную сессию пользователя
        $sql = "SELECT * FROM PCRM_UserSession 
                WHERE user_id = ? 
                ORDER BY last_activity DESC 
                LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $session_data = json_decode($row['data'], true);
            
            // Восстанавливаем данные сессии
            if (is_array($session_data)) {
                foreach ($session_data as $key => $value) {
                    $_SESSION[$key] = $value;
                }
                
                // Обновляем ID сессии
                session_regenerate_id(true);
                
                // Обновляем информацию о сессии в БД
                $new_session_id = session_id();
                $update_sql = "UPDATE PCRM_UserSession 
                              SET session_id = ?, last_activity = NOW() 
                              WHERE id = ?";
                
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $new_session_id, $row['id']);
                $update_stmt->execute();
                
                return true;
            }
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Исключение при восстановлении сессии: " . $e->getMessage());
        return false;
    }
}

// Вызываем функцию инициализации
initSessionManager();
?>