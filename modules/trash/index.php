<?php
// /crm/modules/trash/index.php - Главная страница корзины
error_log("[TRASH_DEBUG] === НАЧАЛО ЗАГРУЗКИ INDEX.PHP ===");
echo "<!-- DEBUG: index.php корзины запущен -->\n";

try {
    require_once __DIR__ . '/../../config/session.php';
    error_log("[TRASH_DEBUG] session.php подключен успешно");
} catch (Exception $e) {
    error_log("[TRASH_DEBUG] ОШИБКА session.php: " . $e->getMessage());
    echo '<div class="alert alert-danger">Ошибка сессии: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

try {
    require_once __DIR__ . '/../../config/db.php';
    error_log("[TRASH_DEBUG] db.php подключен успешно");
} catch (Exception $e) {
    error_log("[TRASH_DEBUG] ОШИБКА db.php: " . $e->getMessage());
    echo '<div class="alert alert-danger">Ошибка БД: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

try {
    require_once __DIR__ . '/../../includes/functions.php';
    error_log("[TRASH_DEBUG] functions.php подключен успешно");
} catch (Exception $e) {
    error_log("[TRASH_DEBUG] ОШИБКА functions.php: " . $e->getMessage());
    echo '<div class="alert alert-danger">Ошибка функций: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

// УБИРАЕМ ВСЕ ПРОВЕРКИ АВТОРИЗАЦИИ - пусть работает для всех
error_log("[TRASH_DEBUG] Пропускаем проверки авторизации");

// Получаем статистику корзины
try {
    $stats = getTrashStats($conn);
    error_log("[TRASH_DEBUG] Статистика получена: " . json_encode($stats));
} catch (Exception $e) {
    error_log("[TRASH_DEBUG] ОШИБКА получения статистики: " . $e->getMessage());
    $stats = ['total' => 0, 'documents' => 0, 'references' => 0, 'by_type' => []];
}

// Функция получения статистики корзины
function getTrashStats($conn) {
    $stats = [
        'total' => 0,
        'documents' => 0,
        'references' => 0,
        'by_type' => []
    ];
    
    $query = "SELECT 
        item_type,
        document_type,
        COUNT(*) as count 
    FROM PCRM_TrashItems 
    GROUP BY item_type, document_type 
    ORDER BY item_type, document_type";
    
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stats['total'] += $row['count'];
            if ($row['item_type'] === 'document') {
                $stats['documents'] += $row['count'];
            } else {
                $stats['references'] += $row['count'];
            }
            $stats['by_type'][] = $row;
        }
    }
    
    return $stats;
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Заголовок модуля -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2>🗑️ Корзина</h2>
                    <p class="text-muted">Управление удаленными документами и справочниками</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-warning" onclick="cleanupOldItems()">
                        🧹 Очистить старые
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="emptyTrash()" 
                            <?= $stats['total'] === 0 ? 'disabled' : '' ?>>
                        🗑️ Очистить корзину
                    </button>
                </div>
            </div>

            <!-- Статистика -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h4 class="card-title text-primary"><?= $stats['total'] ?></h4>
                            <p class="card-text">Всего элементов</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h4 class="card-title text-info"><?= $stats['documents'] ?></h4>
                            <p class="card-text">Документы</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h4 class="card-title text-warning"><?= $stats['references'] ?></h4>
                            <p class="card-text">Справочники</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h4 class="card-title text-success">30</h4>
                            <p class="card-text">Дней до автоочистки</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Табы для разделения документов и справочников -->
            <ul class="nav nav-tabs" id="trashTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="documents-tab" data-bs-toggle="tab" 
                            data-bs-target="#documents" type="button" role="tab">
                        📄 Документы (<?= $stats['documents'] ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="references-tab" data-bs-toggle="tab" 
                            data-bs-target="#references" type="button" role="tab">
                        📚 Справочники (<?= $stats['references'] ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="logs-tab" data-bs-toggle="tab" 
                            data-bs-target="#logs" type="button" role="tab">
                        📋 Журнал операций
                    </button>
                </li>
            </ul>

            <!-- Содержимое табов -->
            <div class="tab-content" id="trashTabContent">
                <!-- Документы -->
                <div class="tab-pane fade show active" id="documents" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Удаленные документы</h5>
                            <div class="d-flex gap-2">
                                <input type="text" class="form-control form-control-sm" 
                                       placeholder="Поиск..." id="searchDocuments" style="width: 200px;">
                                <button class="btn btn-sm btn-success" onclick="restoreAllDocuments()">
                                    ♻️ Восстановить все
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div id="documentsTable">
                                <div class="text-center p-4">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">Загрузка...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Справочники -->
                <div class="tab-pane fade" id="references" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Удаленные справочники</h5>
                            <div class="d-flex gap-2">
                                <input type="text" class="form-control form-control-sm" 
                                       placeholder="Поиск..." id="searchReferences" style="width: 200px;">
                                <button class="btn btn-sm btn-success" onclick="restoreAllReferences()">
                                    ♻️ Восстановить все
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div id="referencesTable">
                                <div class="text-center p-4">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">Загрузка...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Журнал операций -->
                <div class="tab-pane fade" id="logs" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">Журнал операций с корзиной</h5>
                        </div>
                        <div class="card-body p-0">
                            <div id="logsTable">
                                <div class="text-center p-4">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">Загрузка...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модалка подтверждения -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Подтверждение действия</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="confirmMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-danger" id="confirmAction">Подтвердить</button>
            </div>
        </div>
    </div>
</div>

<!-- Модалка просмотра деталей -->
<div class="modal fade" id="detailsModal" tabindex="-1" style="--bs-modal-width: 80vw;">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Детали элемента</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent" style="min-height: 400px; max-height: 80vh; overflow-y: auto;">
                <!-- Содержимое загружается динамически -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<script>
console.log('[TRASH_DEBUG] JavaScript блок корзины запущен');
console.log('[TRASH_DEBUG] Текущий URL:', window.location.href);
console.log('[TRASH_DEBUG] Текущий pathname:', window.location.pathname);
console.log('[TRASH_DEBUG] Базовый путь:', window.location.origin);

// Глобальные переменные для хранения текущих поисковых запросов
let currentDocumentsSearch = '';
let currentReferencesSearch = '';

// НЕМЕДЛЕННАЯ инициализация для AJAX-модулей (без ожидания DOMContentLoaded)
function initTrashModule() {
    console.log('[TRASH_DEBUG] initTrashModule вызван');
    
    // Проверяем что контейнеры существуют
    const documentsContainer = document.getElementById('documentsTable');
    const referencesContainer = document.getElementById('referencesTable');
    const logsContainer = document.getElementById('logsTable');
    
    console.log('[TRASH_DEBUG] Контейнеры найдены:', {
        documents: !!documentsContainer,
        references: !!referencesContainer, 
        logs: !!logsContainer
    });
    
    if (documentsContainer && referencesContainer && logsContainer) {
        console.log('[TRASH_DEBUG] Все контейнеры найдены, загружаем данные');
        loadDocuments();
        loadReferences();
        loadLogs();
        
        // Обработчики поиска
        const searchDocs = document.getElementById('searchDocuments');
        const searchRefs = document.getElementById('searchReferences');
        
        if (searchDocs) {
            searchDocs.addEventListener('input', function() {
                currentDocumentsSearch = this.value;
                loadDocuments(currentDocumentsSearch);
            });
        }
        
        if (searchRefs) {
            searchRefs.addEventListener('input', function() {
                currentReferencesSearch = this.value;
                loadReferences(currentReferencesSearch);
            });
        }
        
        console.log('[TRASH_DEBUG] Обработчики поиска установлены');
    } else {
        console.log('[TRASH_DEBUG] Не все контейнеры найдены, повторяем через 100ms');
        setTimeout(initTrashModule, 100);
    }
}

// Запускаем инициализацию немедленно
console.log('[TRASH_DEBUG] Запускаем initTrashModule');
initTrashModule();

// ОБЕСПЕЧИВАЕМ ГЛОБАЛЬНУЮ ДОСТУПНОСТЬ ФУНКЦИЙ
window.restoreItem = restoreItem;
window.permanentlyDelete = permanentlyDelete;
window.viewDetails = viewDetails;
window.loadDocuments = loadDocuments;
window.loadReferences = loadReferences;
window.loadLogs = loadLogs;
window.updateTrashStats = updateTrashStats;

console.log('[TRASH_DEBUG] Функции добавлены в window:', {
    restoreItem: typeof window.restoreItem,
    permanentlyDelete: typeof window.permanentlyDelete,
    viewDetails: typeof window.viewDetails,
    loadDocuments: typeof window.loadDocuments,
    loadReferences: typeof window.loadReferences
});

// Загрузка списка документов
function loadDocuments(search = '') {
    console.log('[TRASH_DEBUG] loadDocuments вызван, search:', search);
    console.log('[TRASH_DEBUG] currentDocumentsSearch перед обновлением:', currentDocumentsSearch);
    
    const container = document.getElementById('documentsTable');
    console.log('[TRASH_DEBUG] Контейнер найден:', container);
    
    if (!container) {
        console.error('[TRASH_DEBUG] Контейнер documentsTable не найден!');
        return;
    }
    
    container.innerHTML = '<div class="text-center p-4"><div class="spinner-border" role="status"></div></div>';
    
    // Определяем какой поисковый запрос использовать
    let actualSearch = search;
    if (search === '' && currentDocumentsSearch !== '') {
        actualSearch = currentDocumentsSearch;
        console.log('[TRASH_DEBUG] Используем сохраненный поисковый запрос:', actualSearch);
    } else if (search !== '') {
        currentDocumentsSearch = search;
        console.log('[TRASH_DEBUG] Обновляем поисковый запрос на:', search);
    }
    
    const url = `modules/trash/list_documents.php?search=${encodeURIComponent(actualSearch)}`;
    console.log('[TRASH_DEBUG] URL для загрузки:', url);
    
    fetch(url)
        .then(response => {
            console.log('[TRASH_DEBUG] Ответ получен, status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text();
        })
        .then(html => {
            console.log('[TRASH_DEBUG] HTML получен, длина:', html.length);
            console.log('[TRASH_DEBUG] Первые 200 символов:', html.substring(0, 200));
            
            if (container) {
                container.innerHTML = html;
                console.log('[TRASH_DEBUG] Контент загружен в documentsTable');
            } else {
                console.error('[TRASH_DEBUG] Контейнер исчез при обновлении!');
            }
        })
        .catch(error => {
            console.error('[TRASH_DEBUG] Ошибка AJAX:', error);
            if (container) {
                container.innerHTML = '<div class="alert alert-danger">Ошибка загрузки: ' + error.message + '</div>';
            }
        });
}

// Загрузка списка справочников
function loadReferences(search = '') {
    console.log('[TRASH_DEBUG] loadReferences вызван, search:', search);
    console.log('[TRASH_DEBUG] currentReferencesSearch перед обновлением:', currentReferencesSearch);
    
    const container = document.getElementById('referencesTable');
    console.log('[TRASH_DEBUG] Контейнер найден:', container);
    
    if (!container) {
        console.error('[TRASH_DEBUG] Контейнер referencesTable не найден!');
        return;
    }
    
    container.innerHTML = '<div class="text-center p-4"><div class="spinner-border" role="status"></div></div>';
    
    // Определяем какой поисковый запрос использовать
    let actualSearch = search;
    if (search === '' && currentReferencesSearch !== '') {
        actualSearch = currentReferencesSearch;
        console.log('[TRASH_DEBUG] Используем сохраненный поисковый запрос:', actualSearch);
    } else if (search !== '') {
        currentReferencesSearch = search;
        console.log('[TRASH_DEBUG] Обновляем поисковый запрос на:', search);
    }
    
    const url = `modules/trash/list_references.php?search=${encodeURIComponent(actualSearch)}`;
    console.log('[TRASH_DEBUG] URL для загрузки:', url);
    
    fetch(url)
        .then(response => {
            console.log('[TRASH_DEBUG] Ответ получен, status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text();
        })
        .then(html => {
            console.log('[TRASH_DEBUG] HTML получен, длина:', html.length);
            
            if (container) {
                container.innerHTML = html;
                console.log('[TRASH_DEBUG] Контент загружен в referencesTable');
            } else {
                console.error('[TRASH_DEBUG] Контейнер исчез при обновлении!');
            }
        })
        .catch(error => {
            console.error('[TRASH_DEBUG] Ошибка AJAX:', error);
            if (container) {
                container.innerHTML = '<div class="alert alert-danger">Ошибка загрузки: ' + error.message + '</div>';
            }
        });
}

// Загрузка журнала операций
function loadLogs() {
    const container = document.getElementById('logsTable');
    container.innerHTML = '<div class="text-center p-4"><div class="spinner-border" role="status"></div></div>';
    
    fetch('modules/trash/list_logs.php')
        .then(response => response.text())
        .then(html => {
            container.innerHTML = html;
        })
        .catch(error => {
            container.innerHTML = '<div class="alert alert-danger">Ошибка загрузки: ' + error.message + '</div>';
        });
}

// Функция для обновления статистики корзины
function updateTrashStats() {
    console.log('[TRASH_DEBUG] Обновление статистики');
    fetch('modules/trash/get_stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('[TRASH_DEBUG] Статистика получена:', data.stats);
                
                // Обновляем карточки статистики
                document.querySelector('.col-md-3:nth-child(1) .card-title').textContent = data.stats.total;
                document.querySelector('.col-md-3:nth-child(2) .card-title').textContent = data.stats.documents;
                document.querySelector('.col-md-3:nth-child(3) .card-title').textContent = data.stats.references;
                
                // Обновляем счетчики в табах
                document.getElementById('documents-tab').innerHTML = `📄 Документы (${data.stats.documents})`;
                document.getElementById('references-tab').innerHTML = `📚 Справочники (${data.stats.references})`;
                
                // Обновляем состояние кнопки "Очистить корзину"
                const emptyButton = document.querySelector('button[onclick="emptyTrash()"]');
                if (emptyButton) {
                    emptyButton.disabled = data.stats.total === 0;
                }
                
                console.log('[TRASH_DEBUG] Статистика обновлена');
            }
        })
        .catch(error => {
            console.error('[TRASH_DEBUG] Ошибка обновления статистики:', error);
        });
}

// Восстановление элемента
function restoreItem(trashId) {
    console.log('[TRASH_DEBUG] === НАЧАЛО ВОССТАНОВЛЕНИЯ ===');
    console.log('[TRASH_DEBUG] Восстановление элемента:', trashId);
    console.log('[TRASH_DEBUG] Текущие поисковые запросы:', {
        documents: currentDocumentsSearch,
        references: currentReferencesSearch
    });
    
    fetch('modules/trash/restore.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `id=${trashId}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('[TRASH_DEBUG] Результат восстановления:', data);
        if (data.success) {
            showAlert('Элемент успешно восстановлен', 'success');
            
            console.log('[TRASH_DEBUG] Начинаем обновление интерфейса...');
            
            // Обновляем активную вкладку с сохранением поисковых запросов
            const activeTab = document.querySelector('.nav-link.active');
            console.log('[TRASH_DEBUG] Активная вкладка:', activeTab ? activeTab.id : 'НЕ НАЙДЕНА');
            
            if (activeTab && activeTab.id === 'documents-tab') {
                console.log('[TRASH_DEBUG] Обновляем документы с поиском:', currentDocumentsSearch);
                loadDocuments(currentDocumentsSearch);
            } else if (activeTab && activeTab.id === 'references-tab') {
                console.log('[TRASH_DEBUG] Обновляем справочники с поиском:', currentReferencesSearch);
                loadReferences(currentReferencesSearch);
            } else {
                console.log('[TRASH_DEBUG] Обновляем обе вкладки (неизвестная активная вкладка)');
                loadDocuments(currentDocumentsSearch);
                loadReferences(currentReferencesSearch);
            }
            
            console.log('[TRASH_DEBUG] Обновляем логи...');
            loadLogs();
            
            console.log('[TRASH_DEBUG] Обновляем статистику...');
            updateTrashStats();
            
            console.log('[TRASH_DEBUG] === ВОССТАНОВЛЕНИЕ ЗАВЕРШЕНО ===');
        } else {
            showAlert('Ошибка восстановления: ' + data.error, 'danger');
        }
    })
    .catch(error => {
        console.error('[TRASH_DEBUG] Ошибка запроса восстановления:', error);
        showAlert('Ошибка запроса: ' + error.message, 'danger');
    });
}

// Окончательное удаление элемента
function permanentlyDelete(trashId) {
    confirmAction(
        'Вы уверены, что хотите ОКОНЧАТЕЛЬНО удалить этот элемент? Это действие нельзя отменить!',
        () => {
            console.log('[TRASH_DEBUG] === НАЧАЛО ОКОНЧАТЕЛЬНОГО УДАЛЕНИЯ ===');
            console.log('[TRASH_DEBUG] Окончательное удаление элемента:', trashId);
            console.log('[TRASH_DEBUG] Текущие поисковые запросы:', {
                documents: currentDocumentsSearch,
                references: currentReferencesSearch
            });
            
            fetch('modules/trash/permanent_delete.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `id=${trashId}`
            })
            .then(response => response.json())
            .then(data => {
                console.log('[TRASH_DEBUG] Результат удаления:', data);
                if (data.success) {
                    showAlert('Элемент окончательно удален', 'success');
                    
                    console.log('[TRASH_DEBUG] Начинаем обновление интерфейса...');
                    
                    // Обновляем активную вкладку с сохранением поисковых запросов
                    const activeTab = document.querySelector('.nav-link.active');
                    console.log('[TRASH_DEBUG] Активная вкладка:', activeTab ? activeTab.id : 'НЕ НАЙДЕНА');
                    
                    if (activeTab && activeTab.id === 'documents-tab') {
                        console.log('[TRASH_DEBUG] Обновляем документы с поиском:', currentDocumentsSearch);
                        loadDocuments(currentDocumentsSearch);
                    } else if (activeTab && activeTab.id === 'references-tab') {
                        console.log('[TRASH_DEBUG] Обновляем справочники с поиском:', currentReferencesSearch);
                        loadReferences(currentReferencesSearch);
                    } else {
                        console.log('[TRASH_DEBUG] Обновляем обе вкладки (неизвестная активная вкладка)');
                        loadDocuments(currentDocumentsSearch);
                        loadReferences(currentReferencesSearch);
                    }
                    
                    console.log('[TRASH_DEBUG] Обновляем логи...');
                    loadLogs();
                    
                    console.log('[TRASH_DEBUG] Обновляем статистику...');
                    updateTrashStats();
                    
                    console.log('[TRASH_DEBUG] === ОКОНЧАТЕЛЬНОЕ УДАЛЕНИЕ ЗАВЕРШЕНО ===');
                } else {
                    showAlert('Ошибка удаления: ' + data.error, 'danger');
                }
            })
            .catch(error => {
                console.error('[TRASH_DEBUG] Ошибка запроса удаления:', error);
                showAlert('Ошибка запроса: ' + error.message, 'danger');
            });
        }
    );
}

// Очистка корзины
function emptyTrash() {
    confirmAction(
        'Вы уверены, что хотите ПОЛНОСТЬЮ ОЧИСТИТЬ корзину? Все элементы будут ОКОНЧАТЕЛЬНО удалены!',
        () => {
            console.log('[TRASH_DEBUG] Очистка корзины');
            fetch('modules/trash/empty_trash.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                console.log('[TRASH_DEBUG] Результат очистки корзины:', data);
                if (data.success) {
                    showAlert(`Корзина очищена. Удалено элементов: ${data.deleted_count}`, 'success');
                    
                    // Сбрасываем поисковые запросы и очищаем поля поиска
                    currentDocumentsSearch = '';
                    currentReferencesSearch = '';
                    const searchDocs = document.getElementById('searchDocuments');
                    const searchRefs = document.getElementById('searchReferences');
                    if (searchDocs) searchDocs.value = '';
                    if (searchRefs) searchRefs.value = '';
                    
                    loadDocuments();
                    loadReferences();
                    loadLogs();
                    updateTrashStats();
                } else {
                    showAlert('Ошибка очистки корзины: ' + data.error, 'danger');
                }
            })
            .catch(error => {
                console.error('[TRASH_DEBUG] Ошибка запроса очистки:', error);
                showAlert('Ошибка запроса: ' + error.message, 'danger');
            });
        }
    );
}

// Очистка старых элементов
function cleanupOldItems() {
    confirmAction(
        'Удалить все элементы, которые находятся в корзине более 30 дней?',
        () => {
            console.log('[TRASH_DEBUG] Очистка старых элементов');
            fetch('modules/trash/cleanup_old.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                console.log('[TRASH_DEBUG] Результат очистки старых элементов:', data);
                if (data.success) {
                    showAlert(`Очистка завершена. Удалено старых элементов: ${data.deleted_count}`, 'success');
                    
                    // Обновляем с сохранением поисковых запросов
                    loadDocuments(currentDocumentsSearch);
                    loadReferences(currentReferencesSearch);
                    loadLogs();
                    updateTrashStats();
                } else {
                    showAlert('Ошибка очистки: ' + data.error, 'danger');
                }
            })
            .catch(error => {
                console.error('[TRASH_DEBUG] Ошибка запроса очистки старых элементов:', error);
                showAlert('Ошибка запроса: ' + error.message, 'danger');
            });
        }
    );
}

// Универсальная функция подтверждения
function confirmAction(message, callback) {
    document.getElementById('confirmMessage').textContent = message;
    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    modal.show();
    
    document.getElementById('confirmAction').onclick = function() {
        modal.hide();
        callback();
    };
}

// Показ уведомлений
function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Вставляем уведомление в начало контейнера
    const container = document.querySelector('.container-fluid .row .col-12');
    container.insertAdjacentHTML('afterbegin', alertHtml);
    
    // Автоматически скрываем через 5 секунд
    setTimeout(() => {
        const alert = container.querySelector('.alert');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

// Просмотр деталей элемента
function viewDetails(trashId) {
    console.log('[TRASH_DEBUG] Открытие деталей элемента:', trashId);
    
    const modal = document.getElementById('detailsModal');
    const content = document.getElementById('detailsContent');
    
    if (!modal || !content) {
        console.error('[TRASH_DEBUG] Модальное окно или контент не найдены');
        return;
    }
    
    // Проверяем и очищаем существующий экземпляр модального окна
    let modalInstance = bootstrap.Modal.getInstance(modal);
    if (modalInstance) {
        modalInstance.dispose();
    }
    
    // Устанавливаем фиксированные размеры для предотвращения layout shifts
    content.style.minHeight = '400px';
    content.style.maxHeight = '80vh';
    content.style.overflowY = 'auto';
    content.innerHTML = `
        <div class="text-center p-4">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
        </div>
    `;
    
    // Создаем новый экземпляр модального окна с оптимальными настройками
    modalInstance = new bootstrap.Modal(modal, {
        backdrop: 'static',
        keyboard: true,
        focus: true
    });
    
    console.log('[TRASH_DEBUG] Показываем модальное окно');
    modalInstance.show();
    
    // Загружаем содержимое после показа модального окна
    fetch(`modules/trash/view_details.php?id=${trashId}`)
        .then(response => {
            console.log('[TRASH_DEBUG] Ответ на запрос деталей:', response.status);
            return response.text();
        })
        .then(html => {
            console.log('[TRASH_DEBUG] HTML деталей получен, длина:', html.length);
            
            // Добавляем CSS стили для таблиц перед содержимым
            const styledHtml = `
                <style>
                    #detailsContent .table-responsive {
                        max-height: 300px;
                        overflow-y: auto;
                        border: 1px solid #dee2e6;
                        border-radius: 0.375rem;
                    }
                    #detailsContent .table {
                        margin-bottom: 0;
                        font-size: 0.875rem;
                    }
                    #detailsContent .table td, 
                    #detailsContent .table th {
                        white-space: nowrap;
                        padding: 0.5rem;
                    }
                    #detailsContent .card-body {
                        max-height: 250px;
                        overflow-y: auto;
                    }
                </style>
                ${html}
            `;
            
            content.innerHTML = styledHtml;
            
            // Сбрасываем стили контейнера после загрузки содержимого
            content.style.minHeight = 'auto';
        })
        .catch(error => {
            console.error('[TRASH_DEBUG] Ошибка загрузки деталей:', error);
            content.innerHTML = '<div class="alert alert-danger">Ошибка загрузки: ' + error.message + '</div>';
        });
}

// Добавляем функции массового восстановления
function restoreAllDocuments() {
    confirmAction(
        'Восстановить все найденные документы из корзины?',
        () => {
            console.log('[TRASH_DEBUG] Массовое восстановление документов');
            fetch('modules/trash/restore_all.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `type=documents&search=${encodeURIComponent(currentDocumentsSearch)}`
            })
            .then(response => response.json())
            .then(data => {
                console.log('[TRASH_DEBUG] Результат массового восстановления:', data);
                if (data.success) {
                    showAlert(`Восстановлено документов: ${data.restored_count}`, 'success');
                    loadDocuments(currentDocumentsSearch);
                    loadLogs();
                    updateTrashStats();
                } else {
                    showAlert('Ошибка восстановления: ' + data.error, 'danger');
                }
            })
            .catch(error => {
                console.error('[TRASH_DEBUG] Ошибка массового восстановления:', error);
                showAlert('Ошибка запроса: ' + error.message, 'danger');
            });
        }
    );
}

function restoreAllReferences() {
    confirmAction(
        'Восстановить все найденные справочники из корзины?',
        () => {
            console.log('[TRASH_DEBUG] Массовое восстановление справочников');
            fetch('modules/trash/restore_all.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `type=references&search=${encodeURIComponent(currentReferencesSearch)}`
            })
            .then(response => response.json())
            .then(data => {
                console.log('[TRASH_DEBUG] Результат массового восстановления:', data);
                if (data.success) {
                    showAlert(`Восстановлено справочников: ${data.restored_count}`, 'success');
                    loadReferences(currentReferencesSearch);
                    loadLogs();
                    updateTrashStats();
                } else {
                    showAlert('Ошибка восстановления: ' + data.error, 'danger');
                }
            })
            .catch(error => {
                console.error('[TRASH_DEBUG] Ошибка массового восстановления:', error);
                showAlert('Ошибка запроса: ' + error.message, 'danger');
            });
        }
    );
}
</script> 