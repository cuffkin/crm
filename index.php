<?php
// /crm/index.php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$username  = $_SESSION['username'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';

include __DIR__ . '/includes/header.php';
?>

<div class="container mt-3">
  <!-- Дашборд-плейсхолдер -->
  <div id="dashboard" class="dashboard-empty">
    <!-- Здесь будет контент дашборда -->
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- Модальное окно настроек -->
<div class="modal fade" id="adminSettingsModal" tabindex="-1" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Настройки администратора</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <div id="settingsConsole" style="background:#222; color:#b9f6ca; font-size:13px; min-height:80px; max-height:180px; overflow-y:auto; border-radius:6px; padding:8px 12px; margin-bottom:12px; font-family:monospace;">
          Готово к работе.
        </div>
        
        <!-- Секция подтверждения удаления -->
        <div id="deleteConfirmSection" style="display:none; margin-top: 15px; border-top: 1px solid #444; padding-top: 15px;">
          <p class="text-warning"><b>Внимание!</b> Будут безвозвратно очищены следующие таблицы PCRM_ (кроме системных):</p>
          <ul id="tablesToDeleteList" style="max-height: 100px; overflow-y: auto; background: #333; padding: 5px 15px; border-radius: 4px;"></ul>
          <p class="mt-2">Для подтверждения введите <strong class="text-danger">УДАЛИТЬ PCRM</strong> в поле ниже:</p>
          <input type="text" class="form-control mb-2" id="deleteConfirmPhraseInput" placeholder="УДАЛИТЬ PCRM">
          <button class="btn btn-danger w-100" id="confirmActualDeleteBtn">ПОДТВЕРДИТЬ И УДАЛИТЬ ДАННЫЕ</button>
        </div>
        
        <button class="btn btn-outline-danger w-100 mb-2 mt-3" id="deleteAllContentBtn">
          УДАЛИТЬ ВСЕ ДАННЫЕ PCRM_ (кроме аккаунтов и служебных)
        </button>
        <button class="btn btn-outline-secondary w-100" id="logoutFromSettingsBtn">
          Выйти из аккаунта
        </button>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
      </div>
    </div>
  </div>
</div>

<script>
// Кнопка удаления данных
document.addEventListener('DOMContentLoaded', function() {
  const deleteAllBtn = document.getElementById('deleteAllContentBtn');
  const settingsConsole = document.getElementById('settingsConsole');
  const deleteConfirmSection = document.getElementById('deleteConfirmSection');
  const tablesToDeleteList = document.getElementById('tablesToDeleteList');
  const deleteConfirmPhraseInput = document.getElementById('deleteConfirmPhraseInput');
  const confirmActualDeleteBtn = document.getElementById('confirmActualDeleteBtn');
  const logoutBtnSettings = document.getElementById('logoutFromSettingsBtn');

  function logToSettingsConsole(msg, isError = false) {
    if (settingsConsole) {
      const messageDiv = document.createElement('div');
      if (isError) {
        messageDiv.style.color = '#ff5252'; // Красный для ошибок
      }
      messageDiv.innerHTML = msg; // Используем innerHTML для поддержки тегов span из PHP
      settingsConsole.appendChild(messageDiv);
      settingsConsole.scrollTop = settingsConsole.scrollHeight;
    }
  }

  if (deleteAllBtn) {
    deleteAllBtn.addEventListener('click', function() {
      logToSettingsConsole('<b>Запрос на подтверждение удаления...</b>');
      deleteAllBtn.disabled = true; // Блокируем кнопку на время запроса
      deleteConfirmSection.style.display = 'none';
      tablesToDeleteList.innerHTML = '';

      fetch('admin_settings.php?action=confirm_delete')
        .then(response => {
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.json();
        })
        .then(data => {
          deleteAllBtn.disabled = false; // Разблокируем кнопку
          if (data.logs) {
            data.logs.forEach(log => logToSettingsConsole(log));
          }
          if (data.tables_to_delete && data.tables_to_delete.length > 0) {
            logToSettingsConsole('<b>Следующие таблицы PCRM_ будут очищены (кроме системных):</b>', true);
            data.tables_to_delete.forEach(table => {
              const li = document.createElement('li');
              li.textContent = table;
              tablesToDeleteList.appendChild(li);
              // logToSettingsConsole(` - ${table}`); // Уже не дублируем, т.к. есть список
            });
            deleteConfirmPhraseInput.value = '';
            deleteConfirmSection.style.display = 'block';
            logToSettingsConsole("Пожалуйста, введите <strong class=\\"text-danger\\">'УДАЛИТЬ PCRM'</strong> и нажмите 'ПОДТВЕРДИТЬ И УДАЛИТЬ ДАННЫЕ'.", true);
          } else if (data.tables_to_delete) { // tables_to_delete существует, но пустой
            logToSettingsConsole('Нет таблиц PCRM_ для удаления (или все подпадают под исключения).');
          } else {
            logToSettingsConsole('Ошибка: не удалось получить список таблиц для удаления.', true);
          }
        })
        .catch(error => {
          deleteAllBtn.disabled = false; // Разблокируем кнопку в случае ошибки
          logToSettingsConsole(`Сетевая ошибка или ошибка обработки JSON при запросе подтверждения: ${error}`, true);
          console.error("Error during confirm_delete:", error);
        });
    });
  }

  if (confirmActualDeleteBtn) {
    confirmActualDeleteBtn.addEventListener('click', function() {
      const phrase = deleteConfirmPhraseInput.value;
      if (phrase !== 'УДАЛИТЬ PCRM') {
        alert('Фраза подтверждения неверна!');
        logToSettingsConsole('Фраза подтверждения неверна. Удаление отменено.', true);
        return;
      }

      logToSettingsConsole('<b>Подтверждение получено. Запуск удаления...</b>');
      confirmActualDeleteBtn.disabled = true; // Блокируем кнопку
      deleteAllBtn.disabled = true; // И основную кнопку тоже
      deleteConfirmSection.style.display = 'none';

      const formData = new FormData();
      formData.append('action', 'delete_all');

      fetch('admin_settings.php', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then(data => {
        confirmActualDeleteBtn.disabled = false;
        deleteAllBtn.disabled = false;
        if (data.logs) {
          data.logs.forEach(log => logToSettingsConsole(log));
        } else {
          logToSettingsConsole('Ошибка: получен пустой ответ от сервера после удаления.', true);
        }
        logToSettingsConsole('<b>Операция завершена. Пожалуйста, обновите страницу, если это необходимо.</b>');
      })
      .catch(error => {
        confirmActualDeleteBtn.disabled = false;
        deleteAllBtn.disabled = false;
        logToSettingsConsole(`Сетевая ошибка или ошибка обработки JSON при удалении: ${error}`, true);
        console.error("Error during delete_all:", error);
      });
    });
  }

  if (logoutBtnSettings) {
    logoutBtnSettings.addEventListener('click', function() {
      window.location.href = 'logout.php';
    });
  }
});

// Фикс "скукоживания" контента при открытии модальных окон Bootstrap
// ЭТОТ КОД ПЕРЕНЕСЕН В /crm/js/modal.js
</script>