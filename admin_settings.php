<?php
// admin_settings.php — модуль настроек администратора CRM
// Содержит только серверную часть для удаления данных

require_once 'config/db.php'; // Подключаем конфигурацию БД в самом начале

// --- Хелпер функция для логирования и отправки JSON ответа ---
function send_json_response($logs_array) {
  header('Content-Type: application/json');
  echo json_encode(['logs' => $logs_array]);
  exit;
}

// --- Определение списка таблиц, которые НЕ ТРОГАЕМ ---
$skip_tables = [
  'PCRM_User',             // Пользователи
  'PCRM_Measurement',      // Единицы измерения
  'PCRM_TrainingMaterial', // Обучающие материалы
  'PCRM_FormState'         // Состояния форм и вкладок пользователя (важно для UI)
  // Добавьте сюда другие таблицы PCRM_, если их тоже нельзя удалять
];

// --- Получение списка таблиц для удаления (для подтверждения) ---
if (isset($_GET['action']) && $_GET['action'] === 'confirm_delete') {
  $logs = [];
  $tables_to_delete = [];

  $mysqli = @new mysqli($servername, $username, $password, $dbname);
  if ($mysqli->connect_errno) {
    send_json_response(['Ошибка подключения к БД: '.$mysqli->connect_error]);
  }
  $logs[] = "Подключение к БД `{$dbname}` для подтверждения успешно.";

  $res = $mysqli->query("SHOW TABLES LIKE 'PCRM_%'");
  if ($res) {
    while ($row = $res->fetch_array()) {
      $table_name = $row[0];
      if (!in_array($table_name, $skip_tables, true)) {
        if (strpos($table_name, 'PCRM_') === 0) { // Дополнительная проверка префикса
          $tables_to_delete[] = $table_name;
        }
      }
    }
    $logs[] = "Список таблиц для предполагаемого удаления подготовлен.";
  } else {
    $logs[] = "<span style='color:#ff5252'>Ошибка получения списка таблиц с префиксом 'PCRM_': ".$mysqli->error.'</span>';
  }
  $mysqli->close();
  
  // Отправляем список таблиц и логи
  header('Content-Type: application/json');
  echo json_encode(['tables_to_delete' => $tables_to_delete, 'logs' => $logs]);
  exit;
}

// --- Непосредственное удаление данных (после подтверждения) ---
if (isset($_POST['action']) && $_POST['action'] === 'delete_all') { // Изменено на POST для большей безопасности
  $logs = [];
  
  $mysqli = @new mysqli($servername, $username, $password, $dbname);
  if ($mysqli->connect_errno) {
    send_json_response(['Ошибка подключения к БД: '.$mysqli->connect_error]);
  }
  $logs[] = "Подключение к БД `{$dbname}` для удаления успешно.";

  if (!$mysqli->query("SET FOREIGN_KEY_CHECKS = 0;")) {
    $logs[] = "<span style='color:#ff5252'>Ошибка отключения FOREIGN_KEY_CHECKS: " . $mysqli->error . '</span>';
  } else {
    $logs[] = "Проверка внешних ключей временно отключена.";
  }

  $tables_processed_count = 0;
  $tables_deleted_count = 0;

  $res = $mysqli->query("SHOW TABLES LIKE 'PCRM_%'");
  if ($res) {
    $logs[] = "Обработка таблиц с префиксом 'PCRM_':";
    while ($row = $res->fetch_array()) {
      $table = $row[0];
      $tables_processed_count++;
      if (in_array($table, $skip_tables, true)) {
        $logs[] = "Пропущена таблица (в списке исключений): $table";
        continue;
      }
      
      if (strpos($table, 'PCRM_') === 0) {
        if ($mysqli->query("TRUNCATE TABLE `$table`")) {
          $logs[] = "Очищена таблица: $table";
          $tables_deleted_count++;
        } else {
          $logs[] = "<span style='color:#ff5252'>Ошибка очистки $table: ".$mysqli->error.'</span>';
        }
      } else {
        // Эта ситуация не должна возникать при SHOW TABLES LIKE 'PCRM_%'
        $logs[] = "<span style='color:orange'>Таблица $table не начинается с 'PCRM_' и была пропущена.</span>";
      }
    }
    $logs[] = "Завершено: обработано $tables_processed_count таблиц, очищено $tables_deleted_count таблиц.";
  } else {
    $logs[] = "<span style='color:#ff5252'>Ошибка получения списка таблиц с префиксом 'PCRM_': ".$mysqli->error.'</span>';
  }

  if (!$mysqli->query("SET FOREIGN_KEY_CHECKS = 1;")) {
    $logs[] = "<span style='color:#ff5252'>Ошибка включения FOREIGN_KEY_CHECKS: " . $mysqli->error . '</span>';
  } else {
    $logs[] = "Проверка внешних ключей восстановлена.";
  }
  
  $logs[] = 'Операция удаления данных завершена.';
  $mysqli->close();
  send_json_response($logs);
}

// Если никакой action не подошел
// send_json_response(['Ошибка: Не указано корректное действие (action).']);
// Лучше ничего не выводить, если action не задан, чтобы избежать случайного доступа
?> 