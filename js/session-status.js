// /crm/js/session-status.js

$(document).ready(function() {
    // Инициализация статуса сессии
    initSessionStatus();
    
    // Обновление каждые 5 секунд
    setInterval(updateSessionStatus, 5000);
});

// Переменные для хранения состояния
let lastSyncTime = null;
let syncInProgress = false;
let syncSuccess = true;
let sessionActive = true;

// Инициализация отображения статуса сессии
function initSessionStatus() {
    // Подготовка DOM-элементов
    const $sessionInfo = $('#session-info');
    const $syncStatus = $('#sync-status');
    
    if (!$sessionInfo.length || !$syncStatus.length) {
        console.warn('Элементы статуса сессии не найдены в DOM');
        return;
    }
    
    // Первое обновление
    updateSessionStatus();
    
    // Добавляем обработчик для ручной синхронизации при клике
    $syncStatus.on('click', function() {
        if (!syncInProgress) {
            forceSyncWithServer();
        }
    });
}

// Обновление статуса сессии
function updateSessionStatus() {
    const $sessionInfo = $('#session-info');
    const $syncStatus = $('#sync-status');
    
    if (!$sessionInfo.length || !$syncStatus.length) return;
    
    // Получаем время последнего сохранения из globalFormsData
    if (window.globalFormsData && window.globalFormsData.lastSaveTime) {
        lastSyncTime = window.globalFormsData.lastSaveTime;
    }
    
    // Обновляем отображение статуса
    if (syncInProgress) {
        $syncStatus.removeClass('bg-success bg-danger').addClass('bg-warning');
        $syncStatus.html('<i class="fas fa-sync-alt fa-spin"></i>');
        $sessionInfo.text('Синхронизация...');
    } else if (!syncSuccess) {
        $syncStatus.removeClass('bg-success bg-warning').addClass('bg-danger');
        $syncStatus.html('<i class="fas fa-exclamation-triangle"></i>');
        $sessionInfo.text('Ошибка синхронизации');
    } else if (lastSyncTime) {
        $syncStatus.removeClass('bg-danger bg-warning').addClass('bg-success');
        $syncStatus.html('<i class="fas fa-check"></i>');
        $sessionInfo.text(`Синхронизировано: ${lastSyncTime}`);
    } else {
        $syncStatus.removeClass('bg-danger bg-warning').addClass('bg-secondary');
        $syncStatus.html('<i class="fas fa-question"></i>');
        $sessionInfo.text('Нет данных о синхронизации');
    }
    
    // Проверка статуса сессии на сервере
    checkSessionStatus();
}

// Проверка статуса сессии на сервере
function checkSessionStatus() {
    // Простой ping запрос для проверки, что сессия активна
    $.ajax({
        url: '/crm/ping.php',
        type: 'GET',
        timeout: 3000, // 3 секунды таймаут
        success: function(response) {
            if (response === 'OK') {
                sessionActive = true;
            } else {
                sessionActive = false;
                handleSessionInactive();
            }
        },
        error: function() {
            sessionActive = false;
            handleSessionInactive();
        }
    });
}

// Обработка ситуации, когда сессия неактивна
function handleSessionInactive() {
    const $sessionInfo = $('#session-info');
    const $syncStatus = $('#sync-status');
    
    $syncStatus.removeClass('bg-success bg-warning').addClass('bg-danger');
    $syncStatus.html('<i class="fas fa-exclamation-circle"></i>');
    $sessionInfo.text('Сессия неактивна');
    
    // Если после 5 проверок сессия остается неактивной, предложить обновить страницу
    if (sessionInactiveCount >= 5) {
        if (confirm('Ваша сессия неактивна. Хотите перезагрузить страницу?')) {
            location.reload();
        }
        sessionInactiveCount = 0; // Сбрасываем счетчик
    } else {
        sessionInactiveCount++;
    }
}

// Счетчик проверок неактивной сессии
let sessionInactiveCount = 0;

// Принудительная синхронизация с сервером
function forceSyncWithServer() {
    if (syncInProgress) return;
    
    syncInProgress = true;
    updateSessionStatus();
    
    // Если доступна функция синхронизации в app.js, используем её
    if (typeof syncFormsWithServer === 'function') {
        try {
            syncFormsWithServer(true);
            
            // Успешное завершение
            setTimeout(function() {
                syncInProgress = false;
                syncSuccess = true;
                lastSyncTime = new Date().toLocaleTimeString();
                
                if (window.globalFormsData) {
                    window.globalFormsData.lastSaveTime = lastSyncTime;
                }
                
                updateSessionStatus();
                
                // Вместо уведомления - консоль
                console.log('Синхронизация успешно выполнена');
            }, 1000);
        } catch (e) {
            console.error('Ошибка при синхронизации:', e);
            
            // Ошибка синхронизации
            setTimeout(function() {
                syncInProgress = false;
                syncSuccess = false;
                updateSessionStatus();
                
                // Вместо уведомления - консоль
                console.log('Ошибка при синхронизации');
            }, 1000);
        }
    } else {
        // Если функция недоступна, выполняем базовую синхронизацию
        $.ajax({
            url: '/crm/save_form_state.php',
            type: 'GET',
            data: { action: 'ping' },
            success: function() {
                syncInProgress = false;
                syncSuccess = true;
                lastSyncTime = new Date().toLocaleTimeString();
                updateSessionStatus();
                
                // Вместо уведомления - консоль
                console.log('Проверка связи выполнена успешно');
            },
            error: function() {
                syncInProgress = false;
                syncSuccess = false;
                updateSessionStatus();
                
                // Вместо уведомления - консоль
                console.log('Ошибка при проверке связи');
            }
        });
    }
}

// Функция уведомления - безопасная версия без рекурсии
function showNotification(message, type = 'info', duration = 3000) {
    // Просто логируем в консоль и ничего больше не делаем
    console.log('Notification:', message, type);
    
    // Если в глобальном объекте есть функция для показа уведомлений, используем её
    if (window.appShowNotification && window.appShowNotification !== showNotification) {
        try {
            window.appShowNotification(message, type, duration);
        } catch (e) {
            console.error('Ошибка при показе уведомления через appShowNotification:', e);
        }
        return;
    }
    
    // Вариант с простым показом в DOM без рекурсии
    try {
        const existingNotifications = document.querySelectorAll('.toast-notification');
        // Если уже есть уведомления, просто добавляем новое в консоль
        if (existingNotifications.length > 0) {
            return;
        }
        
        // Создаем новый элемент уведомления
        const notification = document.createElement('div');
        notification.className = `toast-notification toast-${type}`;
        notification.innerHTML = `
            <div class="toast-content">
                <div class="toast-message">${message}</div>
            </div>
        `;
        
        // Добавляем на страницу
        document.body.appendChild(notification);
        
        // Удаляем через указанное время
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, duration);
    } catch (e) {
        console.error('Ошибка при показе уведомления:', e);
    }
}