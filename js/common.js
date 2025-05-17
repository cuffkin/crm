// Глобальная переменная для хранения информации о формах
window.globalFormsData = {
  // Форматированная дата последнего сохранения
  lastSaveTime: '',
  // Объект с данными по формам: {tabContentId: {formData: {...}}}
  forms: {}
};

// Глобальная функция для открытия новой вкладки с модулем
window.openNewTab = function(module) {
  console.log('Глобальная функция openNewTab вызвана с параметром:', module);
  
  // Обрабатываем пути к файлам редактирования
  if (module === 'products/edit_partial' || module === 'counterparty/edit_partial') {
    // Создаем URL для прямого обращения к файлу
    const url = '/crm/modules/' + module + '.php';
    console.log('Прямой URL:', url);
    
    // Создаем уникальные идентификаторы для вкладки
    const moduleName = module.split('/')[0]; // products или counterparty
    const timestamp = Date.now();
    const tabId = 'tab-' + moduleName + '-edit-' + timestamp;
    const tabContentId = 'content-' + moduleName + '-edit-' + timestamp;
    
    // Определяем заголовок вкладки
    let title = module.includes('counterparty') ? "Новый контрагент" : "Новый товар";
    
    // Создаем вкладку напрямую
    let navItem = $(
      `<li class="nav-item">
        <a class="nav-link" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" data-module="${module}">
          ${title}
          <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
        </a>
      </li>`
    );

    navItem.find('.btn-close').on('click', function(e) {
      e.stopPropagation();
      if (typeof closeModuleTab === 'function') {
        closeModuleTab(tabId, tabContentId);
      } else {
        $('#' + tabId).closest('li').remove();
        $('#' + tabContentId).remove();
      }
    });

    let tabPane = $(
      `<div class="tab-pane fade" id="${tabContentId}">
        <p>Загрузка содержимого...</p>
      </div>`
    );

    $('#crm-tabs').append(navItem);
    $('#crm-tab-content').append(tabPane);

    $('#' + tabId).tab('show');
    
    // Загружаем содержимое напрямую
    $.ajax({
      url: url,
      success: function(html) {
        if (html.trim() === '') {
          tabPane.html('<div class="alert alert-warning">Пустой ответ от сервера.</div>');
          return;
        }
        if (html.includes('404 Not Found') || html.includes('500 Internal Server Error')) {
          tabPane.html('<div class="alert alert-danger">Ошибка загрузки: ' + html + '</div>');
          return;
        }
        tabPane.html(html).addClass('fade-in');
        if (typeof initFormTracking === 'function') {
          initFormTracking(tabContentId);
        }
        if (typeof saveTabsState === 'function') {
          saveTabsState();
        }
      },
      error: function(xhr) {
        tabPane.html('<div class="alert alert-danger">Ошибка загрузки ('+xhr.status+'): ' + xhr.statusText + '<br>URL: ' + url + '</div>');
        console.error('Ошибка загрузки:', xhr);
      }
    });
  } else {
    // НИКОГДА не вызываем openModuleTab напрямую для edit_partial - это вызывает проблемы с URL
    if (module.includes('edit_partial')) {
      console.error('Некорректный модуль для openNewTab:', module);
      alert('Ошибка: попытка открыть некорректный модуль ' + module);
      return;
    }
    
    // Стандартный вариант - вызываем функцию openModuleTab
    console.log('Вызов стандартной функции openModuleTab для', module);
    if (typeof openModuleTab === 'function') {
      openModuleTab(module);
    } else {
      console.error('Функция openModuleTab не найдена');
      
      // Запасной вариант через событие
      const event = new CustomEvent('openNewTab', {
        detail: { module: module }
      });
      document.dispatchEvent(event);
    }
  }
};

// Делаем функцию доступной для других скриптов
window.openNewTabFromCommon = window.openNewTab;

// Инициализация глобальных объектов
window.openOrderTabs = new Map();

// Регистрация обработчика события открытия новой вкладки
document.addEventListener('DOMContentLoaded', function() {
  document.addEventListener('openNewTab', function(event) {
    if (event.detail && event.detail.module) {
      console.log('Opening new tab with module:', event.detail.module);
      if (typeof openModuleTab === 'function') {
        openModuleTab(event.detail.module);
      } else {
        console.error('Функция openModuleTab не найдена');
      }
    }
  });
}); 