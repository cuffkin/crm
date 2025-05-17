<?php
// /crm/modules/categories/save.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json'); // Устанавливаем заголовок для JSON ответа

$response = ['status' => 'error', 'message' => 'Произошла неизвестная ошибка.'];

if (!check_access($conn, $_SESSION['user_id'], 'categories')) {
    $response['message'] = 'Нет доступа';
    echo json_encode($response);
    exit;
}

// Получаем данные из POST
$id          = (int)($_POST['id'] ?? 0);
$name        = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$status      = trim($_POST['status'] ?? 'active');
$pc_id       = $_POST['pc_id'] ?? null; // Получаем как строку
$db_type     = trim($_POST['db_type'] ?? 'Товарная категория'); // Ожидаем "Товарная категория"

if ($name === '') {
    $response['message'] = 'Название не может быть пустым';
    echo json_encode($response);
    exit;
}

// Обработка pc_id: если передано "0" или пустая строка, устанавливаем NULL для БД
// В базе pc_id может быть NULL, что означает категорию верхнего уровня.
// Или 0, если так принято в проекте. Уточним: для новой структуры pc_id=NULL для категорий верхнего уровня.
if ($pc_id === '0' || $pc_id === '') {
    $pc_id_for_db = null;
} else {
    $pc_id_for_db = (int)$pc_id;
}

// Проверка, что db_type соответствует ожидаемому, хотя он и передается скрытым полем
if ($db_type !== 'Товарная категория') {
    // Это не должно произойти при нормальной работе формы,
    // но как дополнительная проверка или если значение по умолчанию не сработало.
    $db_type = 'Товарная категория'; 
}

try {
    if ($id > 0) {
        // UPDATE
        // Проверка, не пытаемся ли мы сделать категорию дочерней самой себе
        if ($pc_id_for_db !== null && $id === $pc_id_for_db) {
            $response['message'] = 'Категория не может быть дочерней самой себе.';
            echo json_encode($response);
            exit;
        }

        $sql = "UPDATE PCRM_Categories
                SET name=?, type=?, pc_id=?, description=?, status=?
                WHERE id=?";
        $stt = $conn->prepare($sql);
        if (!$stt) {
            throw new Exception("Ошибка подготовки запроса (UPDATE): " . $conn->error);
        }
        $stt->bind_param("ssissi", 
            $name, 
            $db_type,       // Используем $db_type
            $pc_id_for_db,  // Используем $pc_id_for_db
            $description, 
            $status, 
            $id
        );
        $stt->execute();
        if ($stt->error) {
            throw new Exception("Ошибка при обновлении: " . $stt->error);
        }
        if ($stt->affected_rows > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Категория (ID: ' . $id . ') успешно обновлена.';
        } else {
            // Если affected_rows == 0, это может означать, что данные не изменились, или запись не найдена.
            // Для простоты, если нет ошибки, будем считать это условно успешным.
            $response['status'] = 'success'; 
            $response['message'] = 'Данные категории (ID: ' . $id . ') не изменились или уже были актуальны.';
        }
    } else {
        // INSERT
        $sql = "INSERT INTO PCRM_Categories 
                (name, type, pc_id, description, status)
                VALUES (?,?,?,?,?)";
        $stt = $conn->prepare($sql);
        if (!$stt) {
            throw new Exception("Ошибка подготовки запроса (INSERT): " . $conn->error);
        }
        $stt->bind_param("ssiss", 
            $name, 
            $db_type,       // Используем $db_type
            $pc_id_for_db,  // Используем $pc_id_for_db
            $description, 
            $status
        );
        $stt->execute();
        if ($stt->error) {
            throw new Exception("Ошибка при добавлении: " . $stt->error);
        }
        $new_id = $conn->insert_id;
        $response['status'] = 'success';
        $response['message'] = 'Новая категория (ID: ' . $new_id . ') успешно добавлена.';
        $response['new_id'] = $new_id; 
    }
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);