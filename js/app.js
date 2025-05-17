// /crm/js/app.js

// Инициализация глобального объекта для хранения данных форм
window.globalFormsData = {
  forms: {},
  lastSaveTime: null
};

// Инициализация коллекции для хранения информации об открытых вкладках
window.openOrderTabs = new Map();

// Проверяем, загружен ли modal.js и его функции
(function() {
  console.log('Инициализация app.js, проверка доступности функций из modal.js');
  
  // Проверяем, что мы находимся в браузерной среде
  if (typeof window === 'undefined' || typeof document === 'undefined') {
    console.error('app.js загружен вне браузерной среды, это не поддерживается');
    return;
  }
  
  // Проверяем, доступны ли функции из modal.js
  const functionsFromModalJs = [
    'patchBootstrapModal',
    'patchBootstrapImmediately',
    'cleanupModals',
    'showConfirmationModal',
    'showUnsavedChangesConfirm',
    'hideUnsavedChangesModal'
  ];
  
  // Проверяем наличие всех необходимых функций
  const missingFunctions = functionsFromModalJs.filter(funcName => typeof window[funcName] !== 'function');
  
  if (missingFunctions.length > 0) {
    console.error('Не найдены необходимые функции из modal.js:', missingFunctions.join(', '));
    console.error('Убедитесь, что файл modal.js загружен перед app.js!');
  } else {
    console.log('Все функции из modal.js доступны, продолжаем инициализацию');
    
    // Вызываем патч Bootstrap если он доступен
    if (typeof window.patchBootstrapImmediately === 'function') {
      window.patchBootstrapImmediately();
    }
  }
})();

// Глобальная функция для открытия новой вкладки с модулем
window.openNewTab = function(module) {
  console.log('Редиректим на версию функции openNewTab из common.js');
  // Проверяем наличие функции в common.js
  if (typeof window.openNewTabFromCommon === 'function') {
    return window.openNewTabFromCommon(module);
  }
  
  // Запасной вариант, если функция из common.js недоступна
  console.log('Запасной вариант openNewTab вызван с параметром:', module);
  const event = new CustomEvent('openNewTab', {
    detail: { module: module }
  });
  document.dispatchEvent(event);
};

$(function() {
  "use strict";
  
  // Патчим еще раз после полной загрузки страницы для уверенности
  $(document).ready(window.patchBootstrapModal);
  
  // Добавляем обработчик события открытия новой вкладки
  document.addEventListener('openNewTab', function(event) {
    if (event.detail && event.detail.module) {
      console.log('Opening new tab with module:', event.detail.module);
      openModuleTab(event.detail.module);
    }
  });
  
  // НОВАЯ РЕАЛИЗАЦИЯ ВЫПАДАЮЩИХ МЕНЮ
  // Создаем свой менеджер меню, полностью отключая Bootstrap dropdown
  const menuManager = {
    // Текущее активное меню
    activeMenu: null,
    
    // Инициализация
    init: function() {
      // Отключаем стандартное поведение bootstrap dropdown
      $(document).on('show.bs.dropdown shown.bs.dropdown hide.bs.dropdown hidden.bs.dropdown', 
        function(e) {
          e.preventDefault();
          e.stopPropagation();
          return false;
        }
      );
      
      // Обработчик для кнопок открытия меню
      $(document).on('click', '[data-bs-toggle="dropdown"], .dropdown-toggle', 
        this.handleDropdownToggle.bind(this)
      );
      
      // Обработчик кликов по пунктам меню
      $(document).on('click', '.dropdown-menu .dropdown-item', 
        this.handleMenuItemClick.bind(this)
      );
      
      // Закрытие меню при клике вне
      $(document).on('mousedown', this.handleOutsideClick.bind(this));
      
      // Скрываем все меню при инициализации
      $('.dropdown-menu').hide();
      
      console.log('MenuManager initialized');
    },
    
    // Обработчик нажатия на кнопку открытия меню
    handleDropdownToggle: function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      const $button = $(e.currentTarget);
      const $menu = $button.closest('.dropdown, .btn-group').find('.dropdown-menu');
      
      // Если это то же меню, что уже открыто - закрываем его
      if (this.activeMenu && this.activeMenu[0] === $menu[0]) {
        this.closeMenu();
        return;
      }
      
      // Закрываем ранее открытое меню
      this.closeMenu();
      
      // Открываем новое меню
      if ($menu.length) {
        const buttonPos = $button[0].getBoundingClientRect();
        const isTable = $button.closest('.table').length > 0;
        
        // Фиксированное позиционирование относительно окна
        $menu.css({
          position: 'fixed',
          display: 'block',
          top: buttonPos.bottom + 'px',
          left: buttonPos.left + 'px',
          minWidth: Math.max(180, buttonPos.width) + 'px',
          zIndex: 99999
        });
        
        // Уникальное поведение для кнопок в таблице
        if (isTable) {
          $menu.css('minWidth', '180px');
        }
        
        // Исправляем выход за пределы экрана
        const menuPos = $menu[0].getBoundingClientRect();
        const windowWidth = window.innerWidth;
        
        if (menuPos.right > windowWidth) {
          $menu.css('left', Math.max(10, windowWidth - menuPos.width - 10) + 'px');
        }
        
        // Запоминаем активное меню
        this.activeMenu = $menu;
        
        // Отладочная информация
        console.log('Opened menu:', $menu);
        console.log('Button position:', buttonPos);
        console.log('Menu position:', menuPos);
      }
    },
    
    // Обработчик нажатия на пункт меню
    handleMenuItemClick: function(e) {
      console.log('Menu item clicked:', e.currentTarget);
      // Не закрываем меню сразу, чтобы успело сработать действие
      setTimeout(() => this.closeMenu(), 50);
    },
    
    // Обработчик клика вне меню
    handleOutsideClick: function(e) {
      if (!this.activeMenu) return;
      
      const $target = $(e.target);
      
      // Если клик не по меню и не по его кнопке
      if (!$target.closest('.dropdown-menu').length && 
          !$target.closest('[data-bs-toggle="dropdown"], .dropdown-toggle').length) {
        console.log('Outside click detected, closing menu');
        this.closeMenu();
      }
    },
    
    // Закрытие меню
    closeMenu: function() {
      if (this.activeMenu) {
        console.log('Closing menu:', this.activeMenu);
        this.activeMenu.hide();
        this.activeMenu = null;
      }
    }
  };
  
  // Инициализируем менеджер меню
  menuManager.init();

  // Остальные обработчики (не связанные с меню)
  $('[data-module]').on('click', function(e) {
    e.preventDefault();
    let modPath = $(this).data('module') || '';
    modPath = modPath.trim();
    openModuleTab(modPath);
  });

  // Запускаем автосохранение форм каждые 15 секунд
  setInterval(function() {
    autoSaveAllForms();
  }, 15000);
  
  // Запускаем синхронизацию с сервером каждые 45 секунд
  setInterval(function() {
    syncFormsWithServer();
  }, 45000);
  
  // Проверяем наличие сохраненной сессии после загрузки страницы
  $(document).ready(function() {
    // Очищаем все модальные окна при загрузке страницы
    if (typeof window.cleanupModals === 'function') {
      window.cleanupModals();
    }
    
    console.log('Документ загружен, ожидаем инициализацию Bootstrap...');
    
    // Проверка доступности Bootstrap выполняется в footer.php
    // Там же будет вызвана функция restoreUserSession после загрузки Bootstrap
    
    // Инициализируем индикаторы вкладок с небольшой задержкой
    setTimeout(function() {
      if (typeof initTabIndicators === 'function') {
        initTabIndicators();
      }
    }, 1500);
  });
  
  // Добавляем обработчик для события перед закрытием страницы
  $(window).on('beforeunload', function() {
    autoSaveAllForms();
    syncFormsWithServer(true);
    return undefined;
  });

  // Функция для отладки элементов
  function debugElements() {
    console.log('DEBUG: Проверка кликабельных элементов');
    console.log('- Favorite tabs container:', $('#favorite-tabs').length);
    console.log('- Favorite buttons:', $('#favorite-tabs button').length);
    console.log('- Nav items:', $('.navbar .nav-item .nav-link[data-module]').length);
    
    // Выводим данные о каждой кнопке
    $('#favorite-tabs button').each(function(i) {
      const $btn = $(this);
      const module = $btn.data('module');
      const visible = $btn.is(':visible');
      const width = $btn.width();
      const height = $btn.height();
      console.log(`Кнопка #${i+1}: модуль=${module}, видима=${visible}, размер=${width}x${height}px`);
    });
  }

  // Отладочный код - проверяем элементы
  console.log('Favorite tabs:', $('#favorite-tabs').length, 'found');
  console.log('Favorite buttons:', $('#favorite-tabs button').length, 'found');
  console.log('Nav items:', $('.navbar .nav-item .nav-link[data-module]').length, 'found');

  // Глобальный обработчик на документе для гарантированной работы
  $(document).on('click', '#favorite-tabs button', function(e) {
    e.preventDefault();
    e.stopPropagation();
    const module = $(this).data('module');
    if (module) {
      console.log('Global document click handler detected module:', module);
      openModuleTab(module);
    } else {
      console.error('Module not found in clicked button');
    }
    return false;
  });

  // Запускаем отладку через секунду после загрузки
  setTimeout(debugElements, 1000);

  // === НАСТРОЙКИ: обработчики ===
  // Открытие модального окна по клику на кнопку
  // УДАЛЕНО

  // Смена размера шрифта
  // УДАЛЕНО
  // Восстановление размера шрифта при загрузке
  // УДАЛЕНО

  // Смена темы
  // УДАЛЕНО
  // Восстановление темы при загрузке
  // УДАЛЕНО

  // Выйти из аккаунта
  // УДАЛЕНО

  // Удалить весь контент (кроме аккаунтов)
  // УДАЛЕНО
});

// Функция для открытия модуля в новой вкладке
function openModuleTab(modulePath) {
  // Отладочная информация
  console.log('Opening module:', modulePath);
  
  // Проверка на undefined или null
  if (!modulePath) {
    console.error('Ошибка: modulePath не определен');
    return;
  }
  
  let safePath = modulePath.replace(/\//g, '-');

  let tabId = 'tab-' + safePath;
  let tabContentId = 'content-' + safePath;

  if ($('#' + tabId).length > 0) {
    console.log('Tab already exists, showing it');
    $('#' + tabId).tab('show');
    return;
  }

  let title = getModuleTitle(modulePath);
  console.log('Tab title:', title);

  // Проверяем состояние контейнеров для вкладок
  console.log('Tab containers:', {
    'crm-tabs exists': $('#crm-tabs').length,
    'crm-tab-content exists': $('#crm-tab-content').length,
    'crm-tabs visible': $('#crm-tabs').is(':visible'),
    'crm-tab-content visible': $('#crm-tab-content').is(':visible'),
    'crm-tabs CSS display': $('#crm-tabs').css('display')
  });
  
  // Принудительно показываем контейнер вкладок, если он скрыт
  $('#crm-tabs').css('display', 'flex');
  $('#crm-tab-content').show();

  let navItem = $(
    `<li class="nav-item">
      <a class="nav-link" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" data-module="${modulePath}">
        ${title}
        <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>`
  );

  navItem.find('.btn-close').on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
    openOrderTabs.delete(tabContentId);
  });

  let tabPane = $(
    `<div class="tab-pane fade" id="${tabContentId}">
      <p>Загрузка содержимого...</p>
    </div>`
  );

  $('#crm-tabs').append(navItem);
  $('#crm-tab-content').append(tabPane);

  $('#' + tabId).tab('show');

  // Формируем правильный путь для списка
  let url;
  if (modulePath.endsWith('/list')) {
    // Например: purchases/orders/list -> purchases/orders/list_partial.php
    url = '/crm/modules/' + modulePath.replace(/\/list$/, '') + '/list_partial.php';
  } else if (modulePath.includes('edit_partial')) {
    // Прямой доступ к страницам редактирования без добавления list_partial.php
    url = '/crm/modules/' + modulePath + '.php';
    console.log('Прямой URL для редактирования:', url);
  } else {
    url = '/crm/modules/' + modulePath + '/list_partial.php';
  }
  console.log('AJAX URL:', url);

  // Загружаем только по одному пути
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
      initFormTracking(tabContentId);
      saveTabsState();
    },
    error: function(xhr) {
      tabPane.html('<div class="alert alert-danger">Ошибка загрузки ('+xhr.status+')</div>');
    }
  });

  return { tabId, tabContentId };
}

// Функция для закрытия вкладки по её идентификатору контента
function closeOrderTab(contentId) {
  if (openOrderTabs.has(contentId)) {
    const { tabId } = openOrderTabs.get(contentId);
    closeModuleTab(tabId, contentId);
    openOrderTabs.delete(contentId);
    return true;
  }
  return false;
}

// Функция для закрытия вкладки
function closeModuleTab(tabId, tabContentId) {
  console.log('Закрытие вкладки:', tabId, tabContentId);
  
  // Проверяем, существуют ли элементы
  if (!$('#' + tabId).length || !$('#' + tabContentId).length) {
    console.log('Элементы вкладки не найдены, отмена закрытия');
    return;
  }
  
  // Проверяем, является ли вкладка формой редактирования
  const contentElement = document.getElementById(tabContentId);
  const isEditForm = contentElement && 
                    (contentElement.getAttribute('data-is-edit-form') === 'true' ||
                     contentElement.getAttribute('data-has-form') === 'true' ||
                     contentElement.getAttribute('data-has-inputs') === 'true' ||
                     tabContentId.includes('edit') || // Включаем все вкладки с "edit" в ID
                     contentElement.querySelector('form, input, select, textarea')); // Есть форма или поля ввода
  
  // Проверяем, есть ли несохраненные изменения - разделяем проверки для лучшей отладки
  const hasDataUnsavedChanges = contentElement && contentElement.getAttribute('data-has-unsaved-changes') === 'true';
  const hasModifiedFields = contentElement && contentElement.getAttribute('data-has-modified-fields') === 'true';
  const hasDynamicChanges = contentElement && contentElement.getAttribute('data-has-dynamic-changes') === 'true';
  
  // Проверяем также globalFormsData
  const formData = globalFormsData.forms[tabContentId];
  const hasUserModifiedData = formData && formData.userModified === true;
  
  // Ищем измененные поля внутри вкладки
  const changedInputs = contentElement ? contentElement.querySelectorAll('input[data-user-modified="true"], select[data-user-modified="true"], textarea[data-user-modified="true"]') : [];
  const hasChangedInputs = changedInputs.length > 0;
  
  // Комбинируем все проверки
  const hasUnsavedChanges = hasDataUnsavedChanges || hasModifiedFields || hasDynamicChanges;
  
  // Проверяем элементы с jQuery для надежности
  const $jqUnsavedChanges = $('#' + tabContentId).find('input[data-user-modified="true"], select[data-user-modified="true"], textarea[data-user-modified="true"]').length > 0;
  
  // Детальная отладочная информация для поиска проблем с обнаружением несохраненных изменений
  console.log(`[Close Tab Debug] Tab: ${tabId}, ContentId: ${tabContentId}`);
  console.log(`[Close Tab Debug] isEditForm: ${isEditForm}, hasUnsavedChanges: ${hasUnsavedChanges}`);
  console.log(`[Close Tab Debug] Отдельные проверки: hasDataUnsavedChanges=${hasDataUnsavedChanges}, hasModifiedFields=${hasModifiedFields}, hasDynamicChanges=${hasDynamicChanges}`);
  console.log(`[Close Tab Debug] globalFormsData: hasUserModifiedData=${hasUserModifiedData}`);
  console.log(`[Close Tab Debug] Измененные поля: hasChangedInputs=${hasChangedInputs}, количество=${changedInputs.length}, $jqUnsavedChanges=${$jqUnsavedChanges}`);
  
  // Принудительно проверяем все формы внутри вкладки, чтобы иметь полную картину
  if (contentElement) {
    const forms = contentElement.querySelectorAll('form');
    console.log(`[Close Tab Debug] Найдено форм: ${forms.length}`);
    
    forms.forEach((form, idx) => {
      const formInputs = form.querySelectorAll('input, select, textarea');
      console.log(`[Close Tab Debug] Форма #${idx+1}: ${formInputs.length} полей ввода`);
    });
  }
  
  // Объединяем все проверки для решения о показе предупреждения
  const shouldShowWarning = isEditForm && (hasUnsavedChanges || hasUserModifiedData || hasChangedInputs || $jqUnsavedChanges);
  
  // Если это форма с несохраненными изменениями, показываем предупреждение
  if (shouldShowWarning) {
    // Показываем уведомление перед модальным окном
    if (typeof window.showNotification === 'function') {
      window.showNotification('Обнаружены несохраненные изменения в форме', 'warning', 5000);
    }
    console.log('[Close Tab Warning] Обнаружены несохраненные изменения!');
    
    // Используем улучшенную функцию работы с модальным окном из modal.js
    window.showUnsavedChangesConfirm(
      '<i class="fas fa-exclamation-triangle text-warning me-2"></i>Несохраненные изменения',
      'В форме есть несохраненные изменения. Вы уверены, что хотите закрыть её без сохранения?',
      'Закрыть без сохранения',
      'Отмена',
      function() {
        // Колбэк подтверждения - закрываем вкладку
        forceCloseModuleTab(tabId, tabContentId);
      },
      function() {
        // Колбэк отмены - ничего не делаем
        console.log('Отмена закрытия вкладки с несохраненными изменениями');
      }
    );
  } else {
    // Если нет несохраненных изменений, просто закрываем вкладку
    forceCloseModuleTab(tabId, tabContentId);
  }
}

// Функция принудительного закрытия вкладки (без проверки изменений)
function forceCloseModuleTab(tabId, tabContentId) {
  console.log('Принудительное закрытие вкладки:', tabId, tabContentId);
  
  // Удаляем сохраненное состояние формы для этой вкладки
  removeTabFormState(tabContentId);
  
  let isActive = $('#' + tabId).hasClass('active');
  $('#' + tabId).closest('li').remove();
  $('#' + tabContentId).remove();
  
  // Удаляем информацию о закрытой вкладке из карты
  openOrderTabs.delete(tabContentId);
  
  // Если это активная вкладка, переключаемся на другую
  if (isActive) {
    // Ищем предыдущую вкладку или первую доступную
    let $prevTab = $('#crm-tabs a:last');
    if ($prevTab.length) {
      $prevTab.tab('show');
    }
  }
  
  // Сохраняем текущие открытые вкладки
  saveTabsState();
  
  console.log(`Вкладка ${tabId} закрыта`);
}

// Функция для инициализации отслеживания изменений в форме
function initFormTracking(tabContentId) {
  console.log(`Инициализация отслеживания формы для: ${tabContentId}`);
  const contentElement = document.getElementById(tabContentId);
  
  if (!contentElement) {
    console.error(`Элемент #${tabContentId} не найден`);
    return;
  }
  
  // Отмечаем форму как форму редактирования с помощью атрибута
  // Проверяем не только по includes('edit'), но и по другим признакам форм редактирования
  if (tabContentId.includes('edit') || 
      tabContentId.includes('order-content-') || 
      tabContentId.includes('shipment-content-') || 
      tabContentId.includes('finance-content-') || 
      tabContentId.includes('return-content-') ||
      tabContentId.includes('recipe-content-') ||
      tabContentId.includes('operation-content-')) {
    contentElement.setAttribute('data-is-edit-form', 'true');
  }
  
  // Ищем форму внутри контентного элемента
  const formElements = contentElement.querySelectorAll('form');
  if (formElements.length > 0) {
    // Если найдена хотя бы одна форма, будем считать это редактируемым контентом
    contentElement.setAttribute('data-is-edit-form', 'true');
    contentElement.setAttribute('data-has-form', 'true');
  }
  
  const inputs = contentElement.querySelectorAll('input, select, textarea');
  
  // Проверяем, есть ли вообще элементы ввода
  if (inputs.length > 0) {
    // Если есть элементы ввода, считаем это потенциально редактируемым контентом
    contentElement.setAttribute('data-has-inputs', 'true');
  }
  
  // Сохраняем начальные значения полей для последующего сравнения
  inputs.forEach(input => {
    // Сбрасываем все флаги изменений
    input.removeAttribute('data-user-modified');
    
    // Устанавливаем атрибуты с исходными значениями для последующего отслеживания
    if (input.type === 'checkbox' || input.type === 'radio') {
      input.setAttribute('data-initial-checked', input.checked.toString());
    } else {
      input.setAttribute('data-initial-value', input.value);
    }
    
    // Общий обработчик изменений для любых полей ввода
    const markAsModified = function() {
      let valueChanged = false;
      
      if (input.type === 'checkbox' || input.type === 'radio') {
        valueChanged = input.checked.toString() !== input.getAttribute('data-initial-checked');
      } else {
        valueChanged = input.value !== input.getAttribute('data-initial-value');
      }
      
      if (valueChanged) {
        input.setAttribute('data-user-modified', 'true');
        console.log('Поле изменено пользователем:', input.id || input.name);
        // Устанавливаем флаг userModified=true, чтобы точно указать на ручное изменение
        saveFormState(tabContentId, true);
      }
    };
    
    // Добавляем обработчики для всех типов событий изменения данных
    input.addEventListener('change', markAsModified);
    
    // Для текстовых полей, используем также событие input с debounce
    if (input.tagName === 'TEXTAREA' || 
        (input.tagName === 'INPUT' && 
         ['text', 'number', 'email', 'tel', 'password', 'date', 'datetime-local'].includes(input.type))) {
      
      let debounceTimer;
      input.addEventListener('input', function() {
        // Очищаем предыдущий таймер
        clearTimeout(debounceTimer);
        
        // Устанавливаем новый таймер
        debounceTimer = setTimeout(() => {
          markAsModified();
        }, 300);
      });
    }
    
    // Для select также обрабатываем событие keyup (для клавиатурной навигации)
    if (input.tagName === 'SELECT') {
      input.addEventListener('keyup', markAsModified);
    }
  });
  
  // Добавляем универсальный обработчик для родительского элемента, который будет 
  // отлавливать все возможные изменения в форме (делегирование событий)
  contentElement.addEventListener('input', function(e) {
    if (e.target.matches('input, select, textarea')) {
      const input = e.target;
      
      // Если у элемента еще нет атрибута data-initial-value, устанавливаем его
      if (input.type !== 'checkbox' && input.type !== 'radio' && !input.hasAttribute('data-initial-value')) {
        input.setAttribute('data-initial-value', input.value);
      }
      
      // Если у элемента еще нет атрибута data-initial-checked, устанавливаем его
      if ((input.type === 'checkbox' || input.type === 'radio') && !input.hasAttribute('data-initial-checked')) {
        input.setAttribute('data-initial-checked', input.checked.toString());
      }
      
      // Проверяем изменение и устанавливаем флаг
      let valueChanged = false;
      if (input.type === 'checkbox' || input.type === 'radio') {
        valueChanged = input.checked.toString() !== input.getAttribute('data-initial-checked');
      } else {
        valueChanged = input.value !== input.getAttribute('data-initial-value');
      }
      
      if (valueChanged) {
        input.setAttribute('data-user-modified', 'true');
        // Используем setTimeout чтобы не вызывать saveFormState слишком часто
        setTimeout(() => {
          saveFormState(tabContentId, true);
        }, 300);
      }
    }
  });
  
  // Устанавливаем флаг модифицированных данных в globalFormsData
  // Инициализируем если еще не существует
  if (!globalFormsData.forms[tabContentId]) {
    globalFormsData.forms[tabContentId] = {
      userModified: false,
      tabContentId: tabContentId
    };
  }
}

// Удаление сохраненного состояния формы
function removeTabFormState(tabContentId) {
  const userId = getUserId();
  if (!userId) return;
  
  console.log(`Удаление состояния формы для ${tabContentId}`);
  
  // Очищаем все флаги изменений
  const tabContent = document.getElementById(tabContentId);
  if (tabContent) {
    const inputs = tabContent.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
      input.removeAttribute('data-user-modified');
      input.removeAttribute('data-initial-value');
      input.removeAttribute('data-initial-checked');
    });
    
    // Убираем атрибуты с флагами изменений
    tabContent.removeAttribute('data-has-unsaved-changes');
    tabContent.removeAttribute('data-has-modified-fields');
    tabContent.removeAttribute('data-has-dynamic-changes');
    
    // Удаляем индикатор несохраненных изменений
    updateTabModifiedStatus(tabContentId, false);
  }
  
  // Удаляем из глобального объекта
  if (globalFormsData.forms[tabContentId]) {
    delete globalFormsData.forms[tabContentId];
  }
  
  // Удаляем из localStorage
  try {
    const formKey = `form_state_${userId}_${tabContentId}`;
    localStorage.removeItem(formKey);
    
    // Удаляем ключ из списка сохраненных форм
    const formsListKey = `form_keys_${userId}`;
    const formsList = JSON.parse(localStorage.getItem(formsListKey) || '[]');
    const updatedList = formsList.filter(key => key !== formKey);
    localStorage.setItem(formsListKey, JSON.stringify(updatedList));
  } catch (e) {
    console.error('Ошибка при удалении формы из localStorage:', e);
  }
}

// Функция для отображения собственного модального окна с подтверждением
function showConfirmModal(title, message, confirmCallback, cancelCallback) {
  console.log(`Показываю модальное окно: "${title}"`);
  
  // Используем функцию из modal.js
  if (typeof window.showConfirmationModal === 'function') {
    return window.showConfirmationModal(title, message, confirmCallback, cancelCallback);
  }
  
  // Если функция из modal.js недоступна, выводим ошибку в консоль
  console.error('Функция showConfirmationModal не найдена! Проверьте, загружен ли файл modal.js');
  
  // В крайнем случае, просто вызываем колбэк подтверждения
  if (typeof confirmCallback === 'function') {
    setTimeout(confirmCallback, 0);
  }
}

// Глобальный обработчик на документе для гарантированной работы
$(document).on('click', '#favorite-tabs button', function(e) {
  e.preventDefault();
  e.stopPropagation();
  const module = $(this).data('module');
  if (module) {
    console.log('Global document click handler detected module:', module);
    openModuleTab(module);
  } else {
    console.error('Module not found in clicked button');
  }
  return false;
});

// Функция для открытия редактирования заказа в новой вкладке
function openOrderEditTab(orderId, orderNumber = null) {
  const uniqueSuffix = Date.now(); // добавляем уникальный суффикс для избежания конфликтов
  const tabId = 'tab-order-edit-' + orderId + '-' + uniqueSuffix;
  const tabContentId = 'content-order-edit-' + orderId + '-' + uniqueSuffix;
  
  // Сохраняем информацию об открытой вкладке
  openOrderTabs.set(tabContentId, { tabId, orderId, type: 'order' });
  
  // Определяем заголовок вкладки
  let title = 'Новый заказ';
  
  if (orderId > 0) {
    // Если передан номер заказа, используем его
    if (orderNumber) {
      title = `Заказ ${orderNumber}`;
    } else {
      // Ищем номер заказа в таблице
      const orderRow = $(`table.table tbody tr td:first-child:contains(${orderId})`).closest('tr');
      if (orderRow.length) {
        const orderNumber = orderRow.find('td:nth-child(3)').text().trim();
        title = `Заказ ${orderNumber}`;
      } else {
        title = `Заказ #${orderId}`;
      }
    }
  }

  // Создаем новую вкладку
  let navItem = $(`
    <li class="nav-item">
      <a class="nav-link" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" data-order-id="${orderId}" data-document-type="order">
        ${title}
        <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>
  `);

  navItem.find('.btn-close').on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
  });

  let tabPane = $(`
    <div class="tab-pane fade" id="${tabContentId}" data-document-type="order" data-id="${orderId}">
      <p>Загрузка формы редактирования...</p>
    </div>
  `);

  $('#crm-tabs').append(navItem);
  $('#crm-tab-content').append(tabPane);

  $('#' + tabId).tab('show');

  // Загружаем содержимое формы редактирования
  $.ajax({
    url: '/crm/modules/sales/orders/edit_partial.php',
    data: { 
      id: orderId, 
      tab: 1,
      tab_id: tabId,
      content_id: tabContentId
    },
    success: function(html) {
      tabPane.html(html).addClass('fade-in');
      
      // После загрузки содержимого запускаем отслеживание изменений формы
      initFormTracking(tabContentId);
      
      // Восстанавливаем сохраненное состояние формы, если есть
      restoreTabFormState(tabContentId, 'order', orderId);
      
      // Сохраняем состояние вкладок
      saveTabsState();
    },
    error: function(xhr) {
      tabPane.html('<div class="text-danger">Ошибка загрузки формы ('+xhr.status+')</div>');
    }
  });
  
  return { tabId, tabContentId };
}

// Функция для открытия редактирования рецепта производства в новой вкладке
function openRecipeTab(recipeId, viewMode = false) {
  console.log('Opening recipe tab:', recipeId, viewMode);
  const uniqueSuffix = Date.now(); // добавляем уникальный суффикс для избежания конфликтов
  const tabId = 'tab-recipe-' + recipeId + '-' + uniqueSuffix;
  const tabContentId = 'content-recipe-' + recipeId + '-' + uniqueSuffix;
  
  // Определяем заголовок вкладки
  let title = 'Новый рецепт';
  if (recipeId > 0) {
    title = 'Рецепт #' + recipeId;
  }
  if (viewMode) {
    title = 'Просмотр рецепта #' + recipeId;
  }

  // Создаем новую вкладку
  let navItem = $(`
    <li class="nav-item">
      <a class="nav-link" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" data-recipe-id="${recipeId}" data-document-type="production-recipe">
        ${title}
        <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>
  `);

  navItem.find('.btn-close').on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
  });

  let tabPane = $(`
    <div class="tab-pane fade" id="${tabContentId}" data-document-type="production-recipe" data-id="${recipeId}">
      <p>Загрузка формы редактирования...</p>
    </div>
  `);

  $('#crm-tabs').append(navItem);
  $('#crm-tab-content').append(tabPane);

  $('#' + tabId).tab('show');

  // Загружаем содержимое формы редактирования
  $.ajax({
    url: '/crm/modules/production/recipes/edit_partial.php',
    data: { 
      id: recipeId, 
      view: viewMode ? 'true' : 'false',
      tab: 1,
      tab_id: tabId,
      content_id: tabContentId
    },
    success: function(html) {
      tabPane.html(html).addClass('fade-in');
      
      // После загрузки содержимого запускаем отслеживание изменений формы
      initFormTracking(tabContentId);
      
      // Восстанавливаем сохраненное состояние формы, если есть
      restoreTabFormState(tabContentId, 'production-recipe', recipeId);
      
      // Сохраняем состояние вкладок
      saveTabsState();
    },
    error: function(xhr) {
      if (xhr.status === 404) {
        tabPane.html('<div class="text-danger">Файл не найден (404). Проверьте структуру папок.</div>');
      } else if (xhr.status === 500) {
        tabPane.html('<div class="text-danger">Ошибка 500 на сервере. Проверьте PHP-код.</div>');
      } else {
        tabPane.html('<div class="text-danger">Ошибка загрузки ('+xhr.status+')</div>');
      }
    }
  });
  
  return { tabId, tabContentId };
}

// Экспортируем функции в глобальное пространство имен
window.openProductionOperationTab = openProductionOperationTab;
window.openRecipeTab = openRecipeTab;

// Функция для открытия редактирования отгрузки в новой вкладке
function openShipmentEditTab(shipmentId, options = {}) {
  const uniqueSuffix = Date.now(); // добавляем уникальный суффикс для избежания конфликтов
  const tabId = 'tab-shipment-edit-' + shipmentId + '-' + uniqueSuffix;
  const tabContentId = 'content-shipment-edit-' + shipmentId + '-' + uniqueSuffix;
  
  // Сохраняем информацию об открытой вкладке
  openOrderTabs.set(tabContentId, { tabId, shipmentId, type: 'shipment' });
  
  // Определяем заголовок вкладки
  let title = shipmentId > 0 ? 'Отгрузка #' + shipmentId : 'Новая отгрузка';

  // Создаем новую вкладку
  let navItem = $(`
    <li class="nav-item">
      <a class="nav-link" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" data-shipment-id="${shipmentId}" data-document-type="shipment">
        ${title}
        <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>
  `);

  navItem.find('.btn-close').on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
  });

  let tabPane = $(`
    <div class="tab-pane fade" id="${tabContentId}" data-document-type="shipment" data-id="${shipmentId}">
      <p>Загрузка формы редактирования...</p>
    </div>
  `);

  $('#crm-tabs').append(navItem);
  $('#crm-tab-content').append(tabPane);

  $('#' + tabId).tab('show');

  // Формируем параметры для запроса
  const params = { 
    id: shipmentId, 
    tab: 1,
    tab_id: tabId,
    content_id: tabContentId
  };
  
  // Добавляем дополнительные параметры, если они есть
  if (options.order_id) params.order_id = options.order_id;
  if (options.based_on) params.based_on = options.based_on;

  // Загружаем содержимое формы редактирования
  $.ajax({
    url: '/crm/modules/shipments/edit_partial.php',
    data: params,
    success: function(html) {
      tabPane.html(html).addClass('fade-in');
      
      // После загрузки содержимого запускаем отслеживание изменений формы
      initFormTracking(tabContentId);
      
      // Восстанавливаем сохраненное состояние формы, если есть
      restoreTabFormState(tabContentId, 'shipment', shipmentId);
      
      // Сохраняем состояние вкладок
      saveTabsState();
    },
    error: function(xhr) {
      tabPane.html('<div class="text-danger">Ошибка загрузки формы ('+xhr.status+')</div>');
    }
  });
  
  return { tabId, tabContentId };
}

// Функция для открытия редактирования финансовой операции в новой вкладке
function openFinanceEditTab(transactionId, transactionType, options = {}) {
  const uniqueSuffix = Date.now(); // добавляем уникальный суффикс для избежания конфликтов
  const tabId = 'tab-finance-edit-' + transactionId + '-' + uniqueSuffix;
  const tabContentId = 'content-finance-edit-' + transactionId + '-' + uniqueSuffix;
  
  // Сохраняем информацию об открытой вкладке
  openOrderTabs.set(tabContentId, { tabId, transactionId, type: 'finance', transactionType });
  
  // Заголовок вкладки
  let typeText = transactionType === 'income' ? 'Приход' : 'Расход';
  let title = transactionId > 0 ? `${typeText} #${transactionId}` : `Новый ${typeText.toLowerCase()}`;
  
  // Создаем новую вкладку
  let navItem = $(`
    <li class="nav-item">
      <a class="nav-link" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" data-transaction-id="${transactionId}" data-transaction-type="${transactionType}" data-document-type="finance">
        ${title}
        <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>
  `);

  navItem.find('.btn-close').on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
  });

  let tabPane = $(`
    <div class="tab-pane fade" id="${tabContentId}" data-document-type="finance" data-id="${transactionId}" data-transaction-type="${transactionType}">
      <p>Загрузка формы редактирования...</p>
    </div>
  `);

  $('#crm-tabs').append(navItem);
  $('#crm-tab-content').append(tabPane);

  $('#' + tabId).tab('show');

  // Формируем параметры для запроса
  const params = { 
    id: transactionId,
    type: transactionType,
    tab: 1,
    tab_id: tabId,
    content_id: tabContentId
  };
  
  // Добавляем дополнительные параметры, если они есть
  if (options.order_id) params.order_id = options.order_id;
  if (options.shipment_id) params.shipment_id = options.shipment_id;
  if (options.return_id) params.return_id = options.return_id;
  if (options.amount) params.amount = options.amount;
  if (options.counterparty_id) params.counterparty_id = options.counterparty_id;
  if (options.based_on) params.based_on = options.based_on;
  if (options.description) params.description = options.description;

  // Загружаем содержимое формы редактирования
  $.ajax({
    url: '/crm/modules/finances/edit_partial.php',
    data: params,
    success: function(html) {
      tabPane.html(html).addClass('fade-in');
      
      // После загрузки содержимого запускаем отслеживание изменений формы
      initFormTracking(tabContentId);
      
      // Восстанавливаем сохраненное состояние формы, если есть
      restoreTabFormState(tabContentId, 'finance', transactionId, { type: transactionType });
      
      // Сохраняем состояние вкладок
      saveTabsState();
    },
    error: function(xhr) {
      tabPane.html('<div class="text-danger">Ошибка загрузки формы ('+xhr.status+')</div>');
    }
  });
  
  return { tabId, tabContentId };
}

// Функция для открытия редактирования возврата в новой вкладке
function openReturnEditTab(returnId, options = {}) {
  const uniqueSuffix = Date.now(); // добавляем уникальный суффикс для избежания конфликтов
  const tabId = 'tab-return-edit-' + returnId + '-' + uniqueSuffix;
  const tabContentId = 'content-return-edit-' + returnId + '-' + uniqueSuffix;
  
  // Сохраняем информацию об открытой вкладке
  openOrderTabs.set(tabContentId, { tabId, returnId, type: 'return' });
  
  // Определяем заголовок вкладки
  let title = returnId > 0 ? `Возврат #${returnId}` : 'Новый возврат';
  
  // Создаем новую вкладку
  let navItem = $(`
    <li class="nav-item">
      <a class="nav-link" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" data-return-id="${returnId}" data-document-type="return">
        ${title}
        <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>
  `);

  navItem.find('.btn-close').on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
  });

  let tabPane = $(`
    <div class="tab-pane fade" id="${tabContentId}" data-document-type="return" data-id="${returnId}">
      <p>Загрузка формы редактирования...</p>
    </div>
  `);

  $('#crm-tabs').append(navItem);
  $('#crm-tab-content').append(tabPane);

  $('#' + tabId).tab('show');

  // Формируем параметры для запроса
  const params = {
    id: returnId,
    tab: 1,
    tab_id: tabId,
    content_id: tabContentId
  };
  
  // Добавляем параметры, если они переданы
  if (options.order_id) params.order_id = options.order_id;
  if (options.based_on) params.based_on = options.based_on;
  
  // Загружаем содержимое редактирования
  $.ajax({
    url: '/crm/modules/sales/returns/edit_partial.php',
    data: params,
    success: function(html) {
      tabPane.html(html).addClass('fade-in');
      
      // После загрузки содержимого запускаем отслеживание изменений формы
      initFormTracking(tabContentId);
      
      // Восстанавливаем сохраненное состояние формы, если есть
      restoreTabFormState(tabContentId, 'return', returnId);
      
      // Сохраняем состояние вкладок
      saveTabsState();
    },
    error: function(xhr) {
      tabPane.html(`
        <div class="alert alert-danger">
          <h4>Ошибка загрузки формы возврата</h4>
          <p>Ответ сервера: ${xhr.responseText}</p>
        </div>
      `);
    }
  });
  
  return { tabId, tabContentId };
}

// Функция для обновления списка заказов во всех открытых вкладках
function updateOrderLists() {
  // Находим все вкладки со списком заказов
  $('div[id^="content-sales-orders-list"]').each(function() {
    let tabContent = $(this);
    
    $.ajax({
      url: '/crm/modules/sales/orders/list_partial.php',
      success: function(html) {
        // Заменяем только таблицу и кнопку, а не весь контент
        let tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        
        let newTable = $(tempDiv).find('table.table');
        let newButton = $(tempDiv).find('button.btn-primary.btn-sm.mb-2');
        
        tabContent.find('table.table').replaceWith(newTable);
        tabContent.find('button.btn-primary.btn-sm.mb-2').replaceWith(newButton);
        
        // Переподключаем обработчики событий к новым элементам
        initEventHandlers();
      }
    });
  });
}

// Функция для обновления списка отгрузок во всех открытых вкладках
function updateShipmentList() {
  // Находим все вкладки со списком отгрузок
  $('div[id^="content-shipments-list"]').each(function() {
    let tabContent = $(this);
    
    $.ajax({
      url: '/crm/modules/shipments/list_partial.php',
      success: function(html) {
        // Заменяем содержимое вкладки
        tabContent.html(html);
      }
    });
  });
}

// Функция для обновления списка финансовых операций
function updateFinanceList() {
  // Находим все вкладки со списком финансовых операций
  $('div[id^="content-finances-list"]').each(function() {
    let tabContent = $(this);
    
    $.ajax({
      url: '/crm/modules/finances/list_partial.php',
      success: function(html) {
        // Заменяем содержимое вкладки
        tabContent.html(html);
      }
    });
  });
}

// Функция для обновления списка возвратов
function updateReturnsList() {
  // Обновляем список возвратов в каждой вкладке, содержащей их
  $('div[id^="content-sales-returns-list"]').each(function() {
    let tabContent = $(this);
    
    $.ajax({
      url: '/crm/modules/sales/returns/list_partial.php',
      success: function(html) {
        // Заменяем содержимое вкладки
        tabContent.html(html);
      }
    });
  });
}

// Инициализация обработчиков событий для списка заказов
function initEventHandlers() {
  $('button.btn-danger.btn-sm').off('click').on('click', function() {
    const orderId = $(this).closest('tr').find('td:first').text();
    deleteOrder(orderId);
  });
}

// Функция для удаления отгрузки
function deleteShipment(shipmentId) {
  if (!confirm('Вы уверены, что хотите удалить эту отгрузку?')) return;
  
  $.get('/crm/modules/shipments/delete.php', { id: shipmentId }, function(response) {
    if (response === 'OK') {
      // Обновляем список отгрузок
      updateShipmentList();
      // alert('Отгрузка успешно удалена');
    } else {
      alert('Ошибка при удалении: ' + response);
    }
  });
}

// Функция для печати отгрузки
function printShipment(shipmentId) {
  // Открываем окно печати
  window.open('/crm/modules/shipments/print.php?id=' + shipmentId, '_blank');
}

// Функция для удаления финансовой операции
function deleteTransaction(transactionId) {
  if (!confirm('Вы уверены, что хотите удалить эту финансовую операцию?')) return;
  
  $.get('/crm/modules/finances/delete.php', { id: transactionId }, function(response) {
    if (response === 'OK') {
      // Обновляем список операций
      updateFinanceList();
      // alert('Финансовая операция успешно удалена');
    } else {
      alert('Ошибка при удалении: ' + response);
    }
  });
}

// Функция для печати финансовой операции
function printTransaction(transactionId) {
  // Открываем окно печати
  window.open('/crm/modules/finances/print.php?id=' + transactionId, '_blank');
}

// Функция для удаления возврата
function deleteReturn(returnId) {
  if (!confirm('Вы уверены, что хотите удалить этот возврат?')) return;
  
  $.get('/crm/modules/sales/returns/delete.php', { id: returnId }, function(response) {
    if (response === 'OK') {
      // Обновляем список возвратов
      updateReturnsList();
      // alert('Возврат успешно удален');
    } else {
      alert('Ошибка при удалении: ' + response);
    }
  });
}

// Функция для печати возврата
function printReturn(returnId) {
  // Открываем окно печати
  window.open('/crm/modules/sales/returns/print.php?id=' + returnId, '_blank');
}

// Получение заголовка модуля
function getModuleTitle(path) {
  // Если путь не определен, возвращаем безопасное значение
  if (!path) return 'Неизвестный модуль';
  
  console.log('Getting title for module path:', path);
  
  // Карта соответствия путей и заголовков
  const moduleTitles = {
    // Основные модули
    'users/list': 'Пользователи',
    'access/list': 'Управление доступом',
    'finances/list': 'Финансы',
    
    // Продажи
    'sales/orders/list': 'Заказы клиентов',
    'shipments/list': 'Отгрузки',
    'sales/returns/list': 'Возвраты покупателей',
    
    // Закупки
    'purchases/orders/list': 'Заказы поставщикам',
    'purchases/receipts/list': 'Приёмки',
    'purchases/returns/list': 'Возвраты поставщикам',
    
    // Товары
    'products/list': 'Товары',
    'categories/list': 'Категории',
    'measurements/list': 'Единицы измерения',
    'warehouse/list': 'Склады',
    'stock/list': 'Остатки',
    
    // Производство
    'production/recipes/list': 'Рецепты производства',
    'production/orders/list': 'Заказы на производство',
    'production/operations/list': 'Операции производства',
    
    // Справочники
    'counterparty/list': 'Контрагенты',
    'drivers/list': 'Водители',
    'loaders/list': 'Грузчики',
    
    // Специальные страницы
    'products/edit': 'Редактирование товара',
    'measurements/edit': 'Редактирование единицы измерения'
  };
  
  // Проверяем, есть ли путь в списке известных
  if (moduleTitles[path]) {
    return moduleTitles[path];
  }
  
  // Если путь не найден напрямую, пробуем разобрать его
  const pathParts = path.split('/');
  const lastPart = pathParts[pathParts.length - 1];
  const secondLastPart = pathParts.length > 1 ? pathParts[pathParts.length - 2] : '';
  
  // Формируем заголовок на основе пути
  if (path.includes('edit')) {
    // Для страниц редактирования
    if (secondLastPart === 'measurements') {
      return lastPart === 'edit_partial' ? 'Редактирование ЕИ' : 'Единица измерения';
    } else if (secondLastPart === 'products') {
      return lastPart === 'edit_partial' ? 'Редактирование товара' : 'Товар';
    } else {
      return 'Редактирование';
    }
  } else if (lastPart === 'list') {
    // Для списков модулей
    if (secondLastPart === 'measurements') {
      return 'Единицы измерения';
    } else if (secondLastPart === 'products') {
      return 'Товары';
    } else {
      // Формируем название из последней значимой части пути
      const baseModuleName = secondLastPart || pathParts[0] || 'Модуль';
      return baseModuleName.charAt(0).toUpperCase() + baseModuleName.slice(1);
    }
  }
  
  // Для неизвестных путей возвращаем путь как заголовок
  console.log('Unknown module path:', path);
  return path.split('/').pop() || 'Модуль';
}

// ======== ФУНКЦИИ ДЛЯ РАБОТЫ СО СВЯЗАННЫМИ ДОКУМЕНТАМИ ========

// Функция для создания отгрузки на основании заказа
function createShipmentFromOrder(orderId) {
  // Создаем новую вкладку для отгрузки
  const tabId = 'shipment-tab-' + Math.floor(Math.random() * 1000000);
  const tabContentId = 'shipment-content-' + Math.floor(Math.random() * 1000000);
  
  // Заголовок вкладки
  let tabTitle = 'Новая отгрузка';
  
  // Добавляем новую вкладку
  $('#crm-tabs').append(`
    <li class="nav-item">
      <a class="nav-link active" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" role="tab" data-order-id="${orderId}" data-document-type="shipment-from-order">
        ${tabTitle} <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>
  `);
  
  // Добавляем содержимое вкладки
  $('#crm-tab-content').append(`
    <div class="tab-pane fade show active" id="${tabContentId}" role="tabpanel" data-document-type="shipment-from-order" data-order-id="${orderId}">
      <div class="text-center p-5">
        <div class="spinner-border" role="status">
          <span class="visually-hidden">Загрузка...</span>
        </div>
      </div>
    </div>
  `);
  
  // Делаем новую вкладку активной
  $('.nav-link').removeClass('active');
  $('.tab-pane').removeClass('show active');
  $(`#${tabId}`).addClass('active');
  $(`#${tabContentId}`).addClass('show active');
  
  // Загружаем содержимое редактирования отгрузки
  $.ajax({
    url: '/crm/modules/shipments/edit_partial.php',
    data: { 
      id: 0, 
      order_id: orderId,
      based_on: 'order',
      tab: 1,
      tab_id: tabId,
      content_id: tabContentId
    },
    success: function(html) {
      $(`#${tabContentId}`).html(html);
      
      // После загрузки содержимого запускаем отслеживание изменений формы
      initFormTracking(tabContentId);
      
      // Сохраняем информацию об открытой вкладке
      openOrderTabs.set(tabContentId, { tabId, orderId, type: 'shipment-from-order' });
      
      // Сохраняем состояние вкладок
      saveTabsState();
    },
    error: function(xhr, status, error) {
      $(`#${tabContentId}`).html(`
        <div class="alert alert-danger">
          <h4>Ошибка загрузки формы отгрузки</h4>
          <p>Ответ сервера: ${xhr.responseText}</p>
        </div>
      `);
    }
  });
  
  // Обработчик закрытия вкладки
  $(`#${tabId} .btn-close`).on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
  });
}

// Функция для создания возврата на основании заказа
function createReturnFromOrder(orderId) {
  // Создаем новую вкладку для возврата
  const tabId = 'return-tab-' + Math.floor(Math.random() * 1000000);
  const tabContentId = 'return-content-' + Math.floor(Math.random() * 1000000);
  
  // Заголовок вкладки
  let tabTitle = 'Новый возврат';
  
  // Добавляем новую вкладку
  $('#crm-tabs').append(`
    <li class="nav-item">
      <a class="nav-link active" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" role="tab" data-order-id="${orderId}" data-document-type="return-from-order">
        ${tabTitle} <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>
  `);
  
  // Добавляем содержимое вкладки
  $('#crm-tab-content').append(`
    <div class="tab-pane fade show active" id="${tabContentId}" role="tabpanel" data-document-type="return-from-order" data-order-id="${orderId}">
      <div class="text-center p-5">
        <div class="spinner-border" role="status">
          <span class="visually-hidden">Загрузка...</span>
        </div>
      </div>
    </div>
  `);
  
  // Делаем новую вкладку активной
  $('.nav-link').removeClass('active');
  $('.tab-pane').removeClass('show active');
  $(`#${tabId}`).addClass('active');
  $(`#${tabContentId}`).addClass('show active');
  
  // Загружаем содержимое редактирования возврата
  $.ajax({
    url: '/crm/modules/sales/returns/edit_partial.php',
    data: { 
      id: 0, 
      order_id: orderId,
      based_on: 'order',
      tab: 1,
      tab_id: tabId,
      content_id: tabContentId
    },
    success: function(html) {
      $(`#${tabContentId}`).html(html);
      
      // После загрузки содержимого запускаем отслеживание изменений формы
      initFormTracking(tabContentId);
      
      // Сохраняем информацию об открытой вкладке
      openOrderTabs.set(tabContentId, { tabId, orderId, type: 'return-from-order' });
      
      // Сохраняем состояние вкладок
      saveTabsState();
    },
    error: function(xhr, status, error) {
      $(`#${tabContentId}`).html(`
        <div class="alert alert-danger">
          <h4>Ошибка загрузки формы возврата</h4>
          <p>Ответ сервера: ${xhr.responseText}</p>
        </div>
      `);
    }
  });
  
  // Обработчик закрытия вкладки
  $(`#${tabId} .btn-close`).on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
  });
}

// Функция для создания финансовой операции на основании заказа
function createFinanceFromOrder(orderId, type = 'income') {
  // Получаем информацию о заказе
  // ИСПРАВЛЕНИЕ: Изменен URL с несуществующего get_order_info.php на правильный order_api.php с параметром action
  $.getJSON('/crm/modules/sales/orders/order_api.php', { action: 'get_order_info', id: orderId }, function(response) {
    if (response.status === 'ok') {
      const data = response.data;
      
      // Создаем новую вкладку для финансовой операции
      const tabId = 'finance-tab-' + Math.floor(Math.random() * 1000000);
      const tabContentId = 'finance-content-' + Math.floor(Math.random() * 1000000);
      
      // Заголовок вкладки
      let tabTitle = (type === 'income') ? 'Новый приход' : 'Новый расход';
      
      // Добавляем новую вкладку
      $('#crm-tabs').append(`
        <li class="nav-item">
          <a class="nav-link active" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" role="tab" data-order-id="${orderId}" data-document-type="finance-from-order" data-finance-type="${type}">
            ${tabTitle} <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
          </a>
        </li>
      `);
      
      // Добавляем содержимое вкладки
      $('#crm-tab-content').append(`
        <div class="tab-pane fade show active" id="${tabContentId}" role="tabpanel" data-document-type="finance-from-order" data-order-id="${orderId}" data-finance-type="${type}">
          <div class="text-center p-5">
            <div class="spinner-border" role="status">
              <span class="visually-hidden">Загрузка...</span>
            </div>
          </div>
        </div>
      `);
      
      // Делаем новую вкладку активной
      $('.nav-link').removeClass('active');
      $('.tab-pane').removeClass('show active');
      $(`#${tabId}`).addClass('active');
      $(`#${tabContentId}`).addClass('show active');
      
      // Загружаем содержимое редактирования финансовой операции
      $.ajax({
        url: '/crm/modules/finances/edit_partial.php',
        data: { 
          id: 0,
          type: type,
          order_id: orderId,
          amount: data.total_amount || data.order_sum,
          counterparty_id: data.customer,
          tab: 1,
          tab_id: tabId,
          content_id: tabContentId,
          based_on: 'order',
          description: 'Оплата по заказу №' + data.order_number
        },
        success: function(html) {
          $(`#${tabContentId}`).html(html);
          
          // После загрузки содержимого запускаем отслеживание изменений формы
          initFormTracking(tabContentId);
          
          // Сохраняем информацию об открытой вкладке
          openOrderTabs.set(tabContentId, { tabId, orderId, type: 'finance-from-order', financeType: type });
          
          // Сохраняем состояние вкладок
          saveTabsState();
        },
        error: function(xhr, status, error) {
          $(`#${tabContentId}`).html(`
            <div class="alert alert-danger">
              <h4>Ошибка загрузки формы финансовой операции</h4>
              <p>Ответ сервера: ${xhr.responseText}</p>
            </div>
          `);
        }
      });
      
      // Обработчик закрытия вкладки
      $(`#${tabId} .btn-close`).on('click', function(e) {
        e.stopPropagation();
        closeModuleTab(tabId, tabContentId);
      });
    } else {
      alert('Ошибка при получении данных заказа: ' + (response.message || 'Неизвестная ошибка'));
    }
  });
}

// Функция для создания РКО на основании возврата
function createRkoFromReturn(returnId) {
  // Получаем информацию о возврате
  $.getJSON('/crm/modules/sales/returns/get_return_info.php', { id: returnId }, function(response) {
    if (response.status === 'ok') {
      const data = response.data;
      
      // Создаем новую вкладку для финансовой операции
      const tabId = 'finance-tab-' + Math.floor(Math.random() * 1000000);
      const tabContentId = 'finance-content-' + Math.floor(Math.random() * 1000000);
      
      // Заголовок вкладки
      let tabTitle = 'Новый расход';
      
      // Добавляем новую вкладку
      $('#crm-tabs').append(`
        <li class="nav-item">
          <a class="nav-link active" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" role="tab" data-return-id="${returnId}" data-document-type="finance-from-return">
            ${tabTitle} <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
          </a>
        </li>
      `);
      
      // Добавляем содержимое вкладки
      $('#crm-tab-content').append(`
        <div class="tab-pane fade show active" id="${tabContentId}" role="tabpanel" data-document-type="finance-from-return" data-return-id="${returnId}">
          <div class="text-center p-5">
            <div class="spinner-border" role="status">
              <span class="visually-hidden">Загрузка...</span>
            </div>
          </div>
        </div>
      `);
      
      // Делаем новую вкладку активной
      $('.nav-link').removeClass('active');
      $('.tab-pane').removeClass('show active');
      $(`#${tabId}`).addClass('active');
      $(`#${tabContentId}`).addClass('show active');
      
      // Получаем контрагента из связанного заказа, если есть
      const orderId = data.order_id;
      let counterpartyId = null;
      
      function loadFinanceForm(counterpId) {
        // Загружаем содержимое редактирования финансовой операции
        $.ajax({
          url: '/crm/modules/finances/edit_partial.php',
          data: { 
            id: 0,
            type: 'expense',
            return_id: returnId,
            order_id: orderId || 0,
            amount: data.total_amount || 0,
            counterparty_id: counterpId || 0,
            tab: 1,
            tab_id: tabId,
            content_id: tabContentId,
            based_on: 'return', // Важно! Указываем что РКО создаётся на основе возврата
            description: 'Возврат средств по возврату №' + data.return_number + (orderId ? ' (Заказ №' + orderId + ')' : '')
          },
          success: function(html) {
            $(`#${tabContentId}`).html(html);
            
            // После загрузки содержимого запускаем отслеживание изменений формы
            initFormTracking(tabContentId);
            
            // Сохраняем информацию об открытой вкладке
            openOrderTabs.set(tabContentId, { tabId, returnId, type: 'finance-from-return' });
            
            // Сохраняем состояние вкладок
            saveTabsState();
          },
          error: function(xhr, status, error) {
            $(`#${tabContentId}`).html(`
              <div class="alert alert-danger">
                <h4>Ошибка загрузки формы финансовой операции</h4>
                <p>Ответ сервера: ${xhr.responseText}</p>
              </div>
            `);
          }
        });
      }
      
      // Если есть ID заказа, получаем контрагента из заказа
      if (orderId) {
        // ИСПРАВЛЕНИЕ: Изменен URL с несуществующего get_order_info.php на правильный order_api.php с параметром action
        $.getJSON('/crm/modules/sales/orders/order_api.php', { action: 'get_order_info', id: orderId }, function(orderData) {
          if (orderData.status === 'ok') {
            counterpartyId = orderData.data.customer;
            loadFinanceForm(counterpartyId);
          } else {
            loadFinanceForm(null);
          }
        });
      } else {
        loadFinanceForm(null);
      }
      
      // Обработчик закрытия вкладки
      $(`#${tabId} .btn-close`).on('click', function(e) {
        e.stopPropagation();
        closeModuleTab(tabId, tabContentId);
      });
    } else {
      alert('Ошибка при получении данных возврата: ' + (response.message || 'Неизвестная ошибка'));
    }
  });
}

// Функция для удаления заказа
function deleteOrder(orderId) {
  if (!confirm('Вы уверены, что хотите удалить этот заказ?')) return;
  
  $.get('/crm/modules/sales/orders/delete.php', { id: orderId }, function(response) {
    if (response === 'OK') {
      updateOrderLists();
      // alert('Заказ успешно удален');
    } else {
      alert('Ошибка при удалении: ' + response);
    }
  });
}

// Функция для печати заказа
function printOrder(orderId) {
  // Открываем окно печати
  window.open('/crm/modules/sales/orders/print.php?id=' + orderId, '_blank');
}

// ======== ФУНКЦИИ ДЛЯ УПРАВЛЕНИЯ ВКЛАДКАМИ И СЕССИЕЙ ========

// Функция для сохранения состояния вкладок
function saveTabsState() {
  const tabs = [];
  
  // Собираем информацию о всех открытых вкладках
  $('#crm-tabs .nav-item').each(function() {
    const link = $(this).find('.nav-link');
    const contentId = link.attr('href').substring(1); // Убираем # из href
    const tabId = link.attr('id');
    const title = link.text().trim().replace('×', '').trim(); // Убираем символ крестика и лишние пробелы
    
    // Получаем все дата-атрибуты
    const dataAttrs = {};
    const link_data = link.data();
    
    for (const key in link_data) {
      dataAttrs[key] = link_data[key];
    }
    
    const tabInfo = {
      tabId: tabId,
      contentId: contentId,
      title: title,
      isActive: link.hasClass('active'),
      data: dataAttrs
    };
    
    tabs.push(tabInfo);
  });
  
  // Сохраняем в localStorage с привязкой к текущему пользователю
  if (tabs.length > 0) {
    const userId = getUserId();
    if (userId) {
      // Обновляем и сразу отправляем на сервер
      localStorage.setItem('user_tabs_' + userId, JSON.stringify(tabs));
      
      // Отправляем состояние вкладок на сервер для надежного хранения
      $.ajax({
        url: '/crm/save_form_state.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
          action: 'save_tabs',
          user_id: userId,
          tabs: tabs
        }),
        success: function(response) {
          console.log('Tabs state saved to server');
        },
        error: function(xhr, status, error) {
          console.error('Failed to save tabs state to server:', error);
        }
      });
    }
  }
}

// Функция для получения ID текущего пользователя
function getUserId() {
  // В этом примере просто извлекаем ID из метатега, который нужно добавить в header.php
  const userIdMeta = document.querySelector('meta[name="user-id"]');
  if (userIdMeta) {
    return userIdMeta.getAttribute('content');
  }
  // В случае отсутствия метатега, пытаемся получить ID пользователя из URL
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.has('user_id')) {
    return urlParams.get('user_id');
  }
  
  // Пытаемся извлечь из авторизованного куки
  const cookies = document.cookie.split(';');
  for (let i = 0; i < cookies.length; i++) {
    const cookie = cookies[i].trim();
    if (cookie.startsWith('user_id=')) {
      return cookie.substring('user_id='.length, cookie.length);
    }
  }
  
  return null;
}

// Глобальная переменная для отслеживания попыток восстановления сессии
window.sessionRestoreAttempts = 0;
window.maxSessionRestoreAttempts = 5;

// Функция для отображения диалога восстановления сессии
function showSessionRestoreDialog(tabs) {
  console.log('%c[SESSION_RESTORE] Попытка показать диалог восстановления', 'background: #ffa500; color: white; padding: 2px 5px;');
  console.log('[SESSION_RESTORE] Количество вкладок:', tabs.length);
  console.log('[SESSION_RESTORE] Bootstrap доступен:', typeof bootstrap !== 'undefined');
  console.log('[SESSION_RESTORE] Bootstrap.Modal доступен:', typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined');
  
  // Сначала очищаем все модальные окна
  if (typeof window.cleanupModals === 'function') {
    window.cleanupModals();
  }
  
  // Удаляем существующий диалог восстановления сессии, если он есть
  let oldDialog = document.getElementById('sessionRestoreModal');
  if (oldDialog && oldDialog.parentNode) {
    oldDialog.parentNode.removeChild(oldDialog);
  }
  
  try {
    // Создаем список вкладок для отображения
    let tabsList = '';
    tabs.forEach(function(tab) {
      tabsList += `<li>${tab.title || 'Неизвестная вкладка'}</li>`;
    });
    
    // Создаем модальное окно средствами Bootstrap
    const modalHTML = `
      <div class="modal fade" id="sessionRestoreModal" tabindex="-1" aria-labelledby="sessionRestoreModalLabel" role="dialog" aria-modal="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="sessionRestoreModalLabel">
                <i class="fas fa-sync-alt text-primary me-2"></i>Восстановление сессии
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
              <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Обнаружена предыдущая сессия с открытыми вкладками (${tabs.length}).
              </div>
              <p>Открытые вкладки:</p>
              <ul>${tabsList}</ul>
              <p>Хотите продолжить предыдущую сессию или начать новую?</p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" id="newSessionBtn">Начать новую</button>
              <button type="button" class="btn btn-primary" id="restoreSessionBtn">
                <i class="fas fa-sync me-2"></i>Продолжить предыдущую
              </button>
            </div>
          </div>
        </div>
      </div>
    `;
    
    console.log('[SESSION_RESTORE] HTML модального окна подготовлен, добавляем в DOM');
    
    // Добавляем модальное окно в DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Получаем ссылки на элементы
    const modalEl = document.getElementById('sessionRestoreModal');
    if (!modalEl) {
      console.error('[SESSION_RESTORE] Не удалось найти созданное модальное окно в DOM!');
      throw new Error('Модальное окно не найдено после добавления в DOM');
    }
    
    console.log('[SESSION_RESTORE] Модальное окно успешно добавлено в DOM');
    
    const newSessionBtn = document.getElementById('newSessionBtn');
    const restoreSessionBtn = document.getElementById('restoreSessionBtn');
    const closeBtn = modalEl.querySelector('.btn-close');
    
    // Определение функций-обработчиков
    // Функция для начала новой сессии
    const startNewAction = function() {
      console.log('[SESSION_RESTORE] Пользователь выбрал начать новую сессию');
      
      // Скрываем модальное окно
      hideSessionRestoreModal();
      
      // Очищаем сохраненные вкладки для текущего пользователя
      const userId = getUserId();
      if (userId) {
        // Очистка данных вкладок
        localStorage.removeItem('user_tabs_' + userId);
        
        // Очистка данных форм - ищем все ключи localStorage и удаляем их
        for (let i = 0; i < localStorage.length; i++) {
          const key = localStorage.key(i);
          if (key && key.startsWith(`form_state_${userId}_`)) {
            localStorage.removeItem(key);
          }
        }
        
        // Очищаем глобальные данные форм
        globalFormsData.forms = {};
        
        // Также отправляем запрос на сервер для очистки данных
        $.ajax({
          url: '/crm/save_form_state.php',
          type: 'POST',
          contentType: 'application/json',
          data: JSON.stringify({
            action: 'clear',
            user_id: userId
          })
        });
      }
    };
    
    // Функция для восстановления сессии
    const restoreAction = function() {
      console.log('[SESSION_RESTORE] Пользователь выбрал восстановить предыдущую сессию');
      
      // Скрываем модальное окно
      hideSessionRestoreModal();
      
      // Восстанавливаем сохраненные вкладки
      restoreSavedTabs(tabs);
    };
    
    // Функция для скрытия модального окна
    const hideSessionRestoreModal = function() {
      try {
        console.log('[SESSION_RESTORE] Попытка скрыть модальное окно');
        
        // Попытка скрыть модальное окно через Bootstrap API
        if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined' && modalEl) {
          const bsModal = bootstrap.Modal.getInstance(modalEl);
          if (bsModal) {
            console.log('[SESSION_RESTORE] Найден экземпляр модального окна, закрываем');
            bsModal.hide();
          } else {
            console.log('[SESSION_RESTORE] Экземпляр не найден, создаем новый и закрываем');
            const newModal = new bootstrap.Modal(modalEl);
            newModal.hide();
          }
        } else {
          console.log('[SESSION_RESTORE] Bootstrap недоступен, закрываем модальное окно вручную');
          // Резервный вариант: вручную скрываем и удаляем модальное окно
          if (modalEl) {
            modalEl.classList.remove('show');
            modalEl.style.display = 'none';
            
            // Удаляем модальное окно из DOM после закрытия
            setTimeout(function() {
              if (modalEl && modalEl.parentNode) {
                modalEl.parentNode.removeChild(modalEl);
              }
              
              // Удаляем backdrop
              const backdrops = document.querySelectorAll('.modal-backdrop');
              backdrops.forEach(backdrop => {
                if (backdrop && backdrop.parentNode) {
                  backdrop.parentNode.removeChild(backdrop);
                }
              });
              
              // Очищаем стили body
              document.body.classList.remove('modal-open');
              document.body.style.removeProperty('overflow');
              document.body.style.removeProperty('padding-right');
            }, 300);
          }
        }
      } catch (e) {
        console.error('[SESSION_RESTORE] Ошибка при закрытии модального окна:', e);
        
        // В случае ошибки просто удаляем элемент
        if (modalEl && modalEl.parentNode) {
          modalEl.parentNode.removeChild(modalEl);
        }
        
        // Вызываем очистку модальных окон
        if (typeof window.cleanupModals === 'function') {
          window.cleanupModals();
        }
      }
    };
    
    // Назначаем обработчики событий
    if (newSessionBtn) {
      console.log('[SESSION_RESTORE] Назначаем обработчик для кнопки новой сессии');
      newSessionBtn.addEventListener('click', startNewAction);
    } else {
      console.warn('[SESSION_RESTORE] Кнопка новой сессии не найдена!');
    }
    
    if (restoreSessionBtn) {
      console.log('[SESSION_RESTORE] Назначаем обработчик для кнопки восстановления сессии');
      restoreSessionBtn.addEventListener('click', restoreAction);
    } else {
      console.warn('[SESSION_RESTORE] Кнопка восстановления сессии не найдена!');
    }
    
    if (closeBtn) {
      console.log('[SESSION_RESTORE] Назначаем обработчик для кнопки закрытия');
      closeBtn.addEventListener('click', startNewAction); // По умолчанию закрытие = новая сессия
    } else {
      console.warn('[SESSION_RESTORE] Кнопка закрытия не найдена!');
    }
    
    // Обработчик для клика по backdrop (за пределами модального окна)
    modalEl.addEventListener('click', function(e) {
      if (e.target === modalEl) {
        startNewAction();
      }
    });
    
    // Показываем модальное окно
    console.log('[SESSION_RESTORE] Попытка показать модальное окно через Bootstrap');
    try {
      if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
        // Явно проверяем наличие метода show в Modal
        if (typeof bootstrap.Modal.prototype.show !== 'function') {
          console.error('[SESSION_RESTORE] Bootstrap.Modal.prototype.show не является функцией!');
          throw new Error('Метод show недоступен в bootstrap.Modal');
        }
        
        // Создаем новый экземпляр модального окна с параметрами
        const bsModal = new bootstrap.Modal(modalEl, {
          backdrop: 'static',  // Статический backdrop (не закрывается по клику)
          keyboard: false      // Отключаем закрытие по Esc
        });
        
        // Сохраняем экземпляр в DOM для дальнейшего доступа
        modalEl._bootstrapModal = bsModal;
        
        console.log('[SESSION_RESTORE] Вызываем метод show() для модального окна');
        bsModal.show();
        console.log('[SESSION_RESTORE] Метод show() успешно вызван');
      } else {
        console.warn('[SESSION_RESTORE] Bootstrap недоступен, показываем модальное окно вручную');
        // Резервный вариант: вручную показываем модальное окно
        modalEl.classList.add('fade');
        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        document.body.classList.add('modal-open');
        
        // Создаем backdrop вручную
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        document.body.appendChild(backdrop);
      }
      console.log('[SESSION_RESTORE] Модальное окно должно быть показано');
    } catch (e) {
      console.error('[SESSION_RESTORE] Критическая ошибка при показе модального окна:', e);
      // В случае ошибки, по умолчанию начинаем новую сессию
      startNewAction();
      
      // Попробуем запустить показ тестового модального окна
      setTimeout(function() {
        console.log('[SESSION_RESTORE] Попытка показать тестовое модальное окно...');
        if (typeof window.testModal === 'function') {
          window.testModal();
        }
      }, 1000);
    }
  } catch (globalError) {
    console.error('[SESSION_RESTORE] Глобальная ошибка в showSessionRestoreDialog:', globalError);
    
    // Прямой вызов тестовой функции для проверки работы Bootstrap Modal
    setTimeout(function() {
      if (typeof window.testModal === 'function') {
        console.log('[SESSION_RESTORE] Пытаемся показать тестовое окно после ошибки');
        window.testModal();
      }
    }, 1000);
  }
}

// Функция для восстановления сессии пользователя
function restoreUserSession() {
  // Увеличиваем счетчик попыток
  window.sessionRestoreAttempts++;
  
  console.log(`%c[SESSION_RESTORE] Попытка #${window.sessionRestoreAttempts} восстановления сессии`, 'background: #4CAF50; color: white; padding: 2px 5px;');
  
  const userId = getUserId();
  if (!userId) {
    console.error('[SESSION_RESTORE] Не удалось получить ID пользователя. Восстановление сессии невозможно.');
    return;
  }
  
  console.log('[SESSION_RESTORE] Восстановление сессии для пользователя:', userId);
  
  // Проверяем, загружен ли Bootstrap полностью
  if (typeof bootstrap === 'undefined' || typeof bootstrap.Modal === 'undefined') {
    console.log('[SESSION_RESTORE] Bootstrap еще не загружен, откладываем восстановление сессии...');
    
    // Проверяем, не превышено ли максимальное количество попыток
    if (window.sessionRestoreAttempts >= window.maxSessionRestoreAttempts) {
      console.error('[SESSION_RESTORE] Превышено максимальное количество попыток восстановления сессии');
      
      // Принудительно создаем глобальный объект Bootstrap, если его нет
      if (typeof bootstrap === 'undefined') {
        console.log('[SESSION_RESTORE] Принудительно создаем глобальный объект Bootstrap');
        window.bootstrap = {
          Modal: function(element, options) {
            this.element = element;
            this.options = options;
            this.show = function() {
              console.log('[MOCK_BOOTSTRAP] Вызов show() для Modal');
              element.style.display = 'block';
              element.classList.add('show');
              document.body.classList.add('modal-open');
            };
            this.hide = function() {
              console.log('[MOCK_BOOTSTRAP] Вызов hide() для Modal');
              element.style.display = 'none';
              element.classList.remove('show');
              document.body.classList.remove('modal-open');
            };
          }
        };
        
        // Пробуем найти вкладки напрямую
        tryRestoreFromLocalStorage(userId);
      }
      return;
    }
    
    // Увеличиваем задержку до 2 секунд для гарантированной загрузки всех скриптов
    setTimeout(restoreUserSession, 2000);
    return;
  }
  
  // Пытаемся загрузить состояние сессии с сервера
  console.log('[SESSION_RESTORE] Отправляем AJAX запрос для восстановления сессии');
  
  $.ajax({
    url: '/crm/save_form_state.php',
    type: 'GET',
    data: {
      action: 'restore',
      user_id: userId
    },
    success: function(response) {
      console.log('[SESSION_RESTORE] Получен ответ от сервера:', response);
      
      try {
        const serverData = typeof response === 'string' ? JSON.parse(response) : response;
        
        if (serverData.status === 'ok' && serverData.data) {
          console.log('[SESSION_RESTORE] Данные успешно получены с сервера');
          
          // Восстанавливаем состояние форм
          if (serverData.data.forms) {
            globalFormsData.forms = serverData.data.forms;
          }
          
          // Восстанавливаем вкладки
          if (serverData.data.tabs && serverData.data.tabs.length > 0) {
            console.log('[SESSION_RESTORE] Найдены вкладки для восстановления:', serverData.data.tabs.length);
            
            // Показываем диалог подтверждения с небольшой задержкой
            // для гарантированной инициализации Bootstrap
            setTimeout(function() {
              showSessionRestoreDialog(serverData.data.tabs);
            }, 500);
            return;
          } else {
            console.log('[SESSION_RESTORE] Вкладки не найдены в ответе сервера');
          }
        } else {
          console.log('[SESSION_RESTORE] Получен некорректный ответ от сервера:', serverData);
        }
      } catch (e) {
        console.error('[SESSION_RESTORE] Ошибка при обработке ответа сервера:', e);
      }
      
      // Если с сервера не получили ничего, пробуем из localStorage
      tryRestoreFromLocalStorage(userId);
    },
    error: function(xhr, status, error) {
      console.error('[SESSION_RESTORE] Ошибка при загрузке состояния с сервера:', error);
      
      // В случае ошибки пробуем восстановить из localStorage
      tryRestoreFromLocalStorage(userId);
    }
  });
}

// Функция для восстановления из localStorage
function tryRestoreFromLocalStorage(userId) {
  console.log('[SESSION_RESTORE] Попытка восстановления из localStorage для пользователя:', userId);
  
  const savedTabs = localStorage.getItem('user_tabs_' + userId);
  if (!savedTabs) {
    console.log('[SESSION_RESTORE] В localStorage нет сохраненных вкладок');
    return;
  }
  
  try {
    const tabs = JSON.parse(savedTabs);
    console.log('[SESSION_RESTORE] Данные из localStorage получены:', tabs);
    
    if (!tabs || !Array.isArray(tabs) || tabs.length === 0) {
      console.log('[SESSION_RESTORE] Найдено пустое состояние вкладок');
      return;
    }
    
    console.log('[SESSION_RESTORE] Найдено', tabs.length, 'вкладок для восстановления');
    
    // Показываем диалог подтверждения с небольшой задержкой
    // для гарантированной инициализации Bootstrap
    setTimeout(function() {
      showSessionRestoreDialog(tabs);
    }, 500);
  } catch (e) {
    console.error('[SESSION_RESTORE] Ошибка при разборе сохраненных вкладок:', e);
  }
}

// Добавляем глобальный обработчик ошибок для перехвата проблем с модальными окнами
window.addEventListener('error', function(e) {
  console.error('[GLOBAL_ERROR] Перехвачена ошибка:', e.message);
  console.error('[GLOBAL_ERROR] Стек вызовов:', e.error ? e.error.stack : 'Стек недоступен');
  
  // Если ошибка связана с модальными окнами, пытаемся восстановить состояние
  if (e.message.includes('modal') || e.message.includes('Modal') || e.message.includes('bootstrap')) {
    console.log('[GLOBAL_ERROR] Обнаружена ошибка, связанная с модальными окнами, пытаемся восстановить...');
    
    // Очищаем все модальные окна
    if (typeof window.cleanupModals === 'function') {
      window.cleanupModals();
    }
    
    // Пытаемся показать тестовое модальное окно
    setTimeout(function() {
      if (typeof window.testModal === 'function') {
        console.log('[GLOBAL_ERROR] Попытка показать тестовое модальное окно после ошибки');
        window.testModal();
      }
    }, 1000);
  }
});

// Функция для восстановления сохраненных вкладок
function restoreSavedTabs(tabs) {
  console.log('Начинаем восстановление вкладок:', tabs.length);
  
  // Если нет вкладок, выходим
  if (!tabs || tabs.length === 0) return;
  
  // Восстанавливаем каждую вкладку по данным
  tabs.forEach(function(tab) {
    console.log('Восстанавливаем вкладку:', tab);
    
    if (tab.data) {
      // Если есть данные о типе документа и его ID, восстанавливаем соответствующую вкладку
      if (tab.data.documentType) {
        const type = tab.data.documentType;
        
        // Обычные модули
        if (tab.data.module) {
          openModuleTab(tab.data.module);
          return;
        }
        
        // Заказы
        if (type === 'order' && tab.data.orderId) {
          openOrderEditTab(tab.data.orderId);
          return;
        }
        
        // Отгрузки
        if (type === 'shipment' && tab.data.shipmentId) {
          openShipmentEditTab(tab.data.shipmentId);
          return;
        }
        
        // Возвраты
        if (type === 'return' && tab.data.returnId) {
          openReturnEditTab(tab.data.returnId);
          return;
        }
        
        // Финансы
        if (type === 'finance' && tab.data.transactionId !== undefined) {
          openFinanceEditTab(tab.data.transactionId, tab.data.transactionType || 'income');
          return;
        }
        
        // Специальные типы документов на основании других
        if (type === 'shipment-from-order' && tab.data.orderId) {
          createShipmentFromOrder(tab.data.orderId);
          return;
        }
        
        if (type === 'return-from-order' && tab.data.orderId) {
          createReturnFromOrder(tab.data.orderId);
          return;
        }
        
        if (type === 'finance-from-order' && tab.data.orderId) {
          createFinanceFromOrder(tab.data.orderId, tab.data.financeType || 'income');
          return;
        }
        
        if (type === 'finance-from-return' && tab.data.returnId) {
          createRkoFromReturn(tab.data.returnId);
          return;
        }
      }
      // Стандартные модули
      else if (tab.data.module) {
        openModuleTab(tab.data.module);
        return;
      }
    }
    
    // Совместимость со старым форматом
    if (tab.modulePath) {
      openModuleTab(tab.modulePath);
      return;
    }
    
    // Восстанавливаем заказы
    if (tab.orderId !== undefined) {
      openOrderEditTab(tab.orderId);
      return;
    }
    
    // Восстанавливаем отгрузки
    if (tab.shipmentId !== undefined) {
      openShipmentEditTab(tab.shipmentId);
      return;
    }
    
    // Восстанавливаем возвраты
    if (tab.returnId !== undefined) {
      openReturnEditTab(tab.returnId);
      return;
    }
    
    // Восстанавливаем финансовые операции
    if (tab.transactionId !== undefined && tab.transactionType) {
      openFinanceEditTab(tab.transactionId, tab.transactionType);
      return;
    }
    
    // Восстанавливаем специальные типы документов
    if (tab.documentType) {
      switch(tab.documentType) {
        case 'shipment-from-order':
          if (tab.orderId) createShipmentFromOrder(tab.orderId);
          break;
        case 'return-from-order':
          if (tab.orderId) createReturnFromOrder(tab.orderId);
          break;
        case 'finance-from-order':
          if (tab.orderId) createFinanceFromOrder(tab.orderId, tab.financeType || 'income');
          break;
        case 'finance-from-return':
          if (tab.returnId) createRkoFromReturn(tab.returnId);
          break;
      }
    }
    
    // Добавляем возможность восстановления вкладок производственных операций
    if (tab.documentType === 'production-operation' && tab.data && tab.data.operationId) {
      openProductionOperationTab(tab.data.operationId);
      return;
    }
  });
  
  console.log('Восстановление вкладок завершено');
}

// ======== НОВЫЕ ФУНКЦИИ ДЛЯ УПРАВЛЕНИЯ ФОРМАМИ ========

// Сохранение состояния формы
function saveFormState(tabContentId, userModified = false) {
  const userId = getUserId();
  if (!userId) return;
  
  const contentElement = document.getElementById(tabContentId);
  if (!contentElement) return;
  
  // Получаем тип документа и его ID для сохранения
  const documentType = contentElement.getAttribute('data-document-type');
  const documentId = contentElement.getAttribute('data-id');
  
  // Собираем данные всех элементов ввода
  const formData = {
    tabContentId: tabContentId,
    documentType: documentType,
    documentId: documentId,
    timestamp: new Date().toISOString(),
    userModified: userModified, // Флаг, указывающий, что изменения внесены пользователем вручную
    values: {}
  };
  
  // Если было указано, что форма изменена пользователем, выводим лог
  if (userModified) {
    console.log('Сохранение формы с измененными пользователем данными:', tabContentId);
    
    // Отмечаем форму как имеющую несохраненные изменения
    contentElement.setAttribute('data-has-unsaved-changes', 'true');
  }
  
  // Сохраняем все значения полей ввода
  const inputs = contentElement.querySelectorAll('input, select, textarea');
  let modifiedFieldsCount = 0;
  
  inputs.forEach(input => {
    // Пропускаем кнопки и скрытые поля без имени
    if (input.type === 'button' || input.type === 'submit' || input.type === 'reset' || 
        (!input.id && !input.name)) {
      return;
    }
    
    // Используем id или name как ключ
    const key = input.id || input.name;
    if (!key) return;
    
    // Сохраняем значение в зависимости от типа поля
    if (input.type === 'checkbox' || input.type === 'radio') {
      formData.values[key] = input.checked;
    } else {
      formData.values[key] = input.value;
    }
    
    // Проверяем, было ли поле изменено по сравнению с начальным значением
    let valueChanged = false;
    if (input.type === 'checkbox' || input.type === 'radio') {
      const initialChecked = input.getAttribute('data-initial-checked');
      if (initialChecked !== null && input.checked.toString() !== initialChecked) {
        valueChanged = true;
      }
    } else {
      const initialValue = input.getAttribute('data-initial-value');
      if (initialValue !== null && input.value !== initialValue) {
        valueChanged = true;
      }
    }
    
    // Если значение изменилось или уже есть атрибут data-user-modified, записываем это
    if (valueChanged || input.dataset.userModified === 'true') {
      if (!formData.modifiedFields) formData.modifiedFields = {};
      formData.modifiedFields[key] = true;
      modifiedFieldsCount++;
      
      // Отмечаем поле как измененное пользователем
      input.setAttribute('data-user-modified', 'true');
    }
  });
  
  // Добавляем отладочную информацию о количестве измененных полей
  if (modifiedFieldsCount > 0) {
    console.log(`Обнаружено ${modifiedFieldsCount} измененных полей в форме ${tabContentId}`);
    // Принудительно устанавливаем флаг userModified, если есть модифицированные поля
    formData.userModified = true;
    
    // Также отмечаем сам элемент контента
    contentElement.setAttribute('data-has-modified-fields', 'true');
  }
  
  // Сохраняем для специфичных элементов интерфейса (например, таблицы с гибридными платежами)
  if (contentElement.querySelector('#payment-details-table')) {
    const paymentDetails = [];
    contentElement.querySelectorAll('#payment-details-table tbody tr').forEach(row => {
      const method = row.querySelector('.pd-method')?.value;
      const amount = row.querySelector('.pd-amount')?.value;
      const description = row.querySelector('.pd-description')?.value;
      
      if (method) {
        paymentDetails.push({ method, amount, description });
      }
    });
    formData.values['payment_details'] = paymentDetails;
    
    // Если есть платежи, считаем что форма была изменена пользователем
    if (paymentDetails.length > 0) {
      formData.userModified = true;
      contentElement.setAttribute('data-has-dynamic-changes', 'true');
    }
  }
  
  // Сохраняем в глобальный объект
  globalFormsData.forms[tabContentId] = formData;
  globalFormsData.lastSaveTime = new Date().toLocaleTimeString();
  
  // Добавляем явную установку флага в глобальный объект
  if (userModified || formData.userModified === true) {
    // Если параметр userModified=true или если обнаружены изменения, гарантируем, что флаг установлен
    globalFormsData.forms[tabContentId].userModified = true;
    
    // Обновляем индикатор состояния вкладки
    updateTabModifiedStatus(tabContentId, true);
  }
  
  // Сохраняем данные в localStorage
  saveFormDataToLocalStorage(userId, tabContentId, formData);
}

// Функция для сохранения данных формы в localStorage
function saveFormDataToLocalStorage(userId, tabContentId, formData) {
  try {
    const formKey = `form_state_${userId}_${tabContentId}`;
    localStorage.setItem(formKey, JSON.stringify(formData));
    
    // Добавляем ключ формы в список сохраненных форм
    const formsListKey = `form_keys_${userId}`;
    const formsList = JSON.parse(localStorage.getItem(formsListKey) || '[]');
    
    if (!formsList.includes(formKey)) {
      formsList.push(formKey);
      localStorage.setItem(formsListKey, JSON.stringify(formsList));
    }
  } catch (e) {
    console.error('Ошибка при сохранении формы в localStorage:', e);
  }
}

// Восстановление состояния формы
function restoreFormState(tabContentId) {
  const userId = getUserId();
  if (!userId) return;
  
  // Сначала проверяем глобальный объект
  let formData = globalFormsData.forms[tabContentId];
  
  // Если нет в глобальном объекте, пытаемся загрузить из localStorage
  if (!formData) {
    const formKey = `form_state_${userId}_${tabContentId}`;
    const savedData = localStorage.getItem(formKey);
    
    if (savedData) {
      try {
        formData = JSON.parse(savedData);
        // Добавляем в глобальный объект для дальнейшего использования
        globalFormsData.forms[tabContentId] = formData;
      } catch (e) {
        console.error('Ошибка при восстановлении состояния формы из localStorage:', e);
        return;
      }
    } else {
      return; // Если нет сохраненных данных, выходим
    }
  }
  
  const contentElement = document.getElementById(tabContentId);
  if (!contentElement || !formData.values) return;
  
  try {
    // Восстанавливаем значения элементов ввода
    for (const key in formData.values) {
      if (key === 'payment_details') continue; // Обработаем отдельно
      
      const input = contentElement.querySelector(`#${key}`) || contentElement.querySelector(`[name="${key}"]`);
      if (!input) continue;
      
      // Проверяем, было ли это поле изменено пользователем вручную
      const isUserModified = formData.modifiedFields && formData.modifiedFields[key];
      
      if (isUserModified) {
        // Если поле было изменено пользователем вручную, устанавливаем соответствующий флаг
        if (input.type === 'checkbox' || input.type === 'radio') {
          input.checked = formData.values[key];
        } else {
          input.value = formData.values[key];
        }
        input.dataset.userModified = 'true';
      } else {
        // Если поле не было изменено вручную, используем функцию для программного заполнения
        setAutoFilledValue(input, formData.values[key]);
      }
    }
    
    // Восстанавливаем специфичные элементы интерфейса
    if (formData.values.payment_details && contentElement.querySelector('#payment-details-table')) {
      // Очищаем существующие строки
      const tbody = contentElement.querySelector('#payment-details-table tbody');
      tbody.innerHTML = '';
      
      // Добавляем сохраненные строки
      formData.values.payment_details.forEach(detail => {
        const row = document.createElement('tr');
        row.innerHTML = `
          <td>
            <select class="form-select pd-method">
              <option value="cash" ${detail.method === 'cash' ? 'selected' : ''}>Наличные</option>
              <option value="card" ${detail.method === 'card' ? 'selected' : ''}>Эквайринг</option>
              <option value="transfer_rncb" ${detail.method === 'transfer_rncb' ? 'selected' : ''}>Перевод (РНКБ)</option>
              <option value="transfer_other" ${detail.method === 'transfer_other' ? 'selected' : ''}>Перевод (Другой банк)</option>
              <option value="bank_account" ${detail.method === 'bank_account' ? 'selected' : ''}>Банковский счёт</option>
            </select>
          </td>
          <td><input type="number" step="0.01" class="form-control pd-amount" value="${detail.amount || 0}"></td>
          <td><input type="text" class="form-control pd-description" value="${detail.description || ''}"></td>
          <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();calcTotalHybrid();">×</button></td>
        `;
        
        // Устанавливаем атрибут data-auto-filled для всех элементов, если они не были изменены вручную
        if (!(formData.modifiedFields && formData.modifiedFields['payment_details'])) {
          row.querySelectorAll('input, select').forEach(el => {
            el.dataset.autoFilled = 'true';
          });
        }
        
        tbody.appendChild(row);
      });
      
      // Вызываем перерасчет итоговой суммы, если функция доступна
      if (typeof window.calcTotalHybrid === 'function') {
        window.calcTotalHybrid();
      } else if (typeof calcTotalHybrid === 'function') {
        calcTotalHybrid();
      }
    }
    
    // Если есть функция для проверки категории расходов - вызываем её
    if (typeof window.checkExpenseCategoryOther === 'function') {
      window.checkExpenseCategoryOther();
    } else if (typeof checkExpenseCategoryOther === 'function') {
      checkExpenseCategoryOther();
    }
    
    // Вызываем событие change для запуска обработчиков
    contentElement.querySelectorAll('input, select, textarea').forEach(input => {
      const event = new Event('change', { bubbles: true });
      input.dispatchEvent(event);
    });
    
    console.log(`Форма ${tabContentId} успешно восстановлена`);
  } catch (e) {
    console.error('Ошибка при восстановлении состояния формы:', e);
  }
}

// Восстановление состояния формы для динамической вкладки по типу
function restoreTabFormState(tabContentId, documentType, documentId, options = {}) {
  // Пытаемся найти сохраненную форму по типу документа и его ID
  const userId = getUserId();
  if (!userId) return;
  
  console.log(`Поиск сохраненной формы для: ${documentType}/${documentId}`);
  
  // Ищем подходящие формы в глобальном объекте
  for (const key in globalFormsData.forms) {
    const formData = globalFormsData.forms[key];
    if (formData.documentType === documentType && formData.documentId == documentId) {
      globalFormsData.forms[tabContentId] = formData;
      delete globalFormsData.forms[key]; // Удаляем из старого ключа
      console.log(`Найдена форма в глобальном объекте: ${key} -> ${tabContentId}`);
      return;
    }
  }
  
  // Если в глобальном объекте не нашли, ищем в localStorage
  const formsListKey = `form_keys_${userId}`;
  const formsList = JSON.parse(localStorage.getItem(formsListKey) || '[]');
  
  for (const formKey of formsList) {
    try {
      const savedData = localStorage.getItem(formKey);
      if (!savedData) continue;
      
      const formData = JSON.parse(savedData);
      
      // Проверяем, соответствует ли эта форма нашему документу
      if (formData.documentType === documentType && formData.documentId == documentId) {
        // Копируем данные в новый ключ
        const newFormKey = `form_state_${userId}_${tabContentId}`;
        localStorage.setItem(newFormKey, savedData);
        
        // Добавляем новый ключ в список, если его там нет
        if (!formsList.includes(newFormKey)) {
          formsList.push(newFormKey);
          localStorage.setItem(formsListKey, JSON.stringify(formsList));
        }
        
        // Удаляем старый ключ из localStorage и списка
        localStorage.removeItem(formKey);
        const index = formsList.indexOf(formKey);
        if (index > -1) {
          formsList.splice(index, 1);
          localStorage.setItem(formsListKey, JSON.stringify(formsList));
        }
        
        console.log(`Найдена форма в localStorage: ${formKey} -> ${newFormKey}`);
        return;
      }
    } catch (e) {
      console.error('Ошибка при поиске формы в localStorage:', e);
    }
  }
  
  console.log(`Не найдено сохраненной формы для: ${documentType}/${documentId}`);
}

// Автосохранение всех форм на открытых вкладках
function autoSaveAllForms() {
  // Сохраняем состояние всех открытых вкладок
  $('.tab-pane').each(function() {
    const tabContentId = $(this).attr('id');
    if (tabContentId) {
      saveFormState(tabContentId);
    }
  });
  
  // Также обновляем состояние вкладок
  saveTabsState();
  
  console.log(`[${globalFormsData.lastSaveTime}] Автосохранение выполнено`);
}

// Синхронизация данных форм с сервером
function syncFormsWithServer(sync = false) {
  const userId = getUserId();
  if (!userId) return;
  
  // Копия данных для отправки
  const syncData = {
    forms: JSON.parse(JSON.stringify(globalFormsData.forms)),
    tabs: [],
    timestamp: new Date().toISOString()
  };
  
  // Добавляем информацию о текущих вкладках
  $('#crm-tabs .nav-item').each(function() {
    const link = $(this).find('.nav-link');
    const contentId = link.attr('href').substring(1);
    const tabId = link.attr('id');
    
    // Собираем все дата-атрибуты
    const dataAttrs = {};
    $.each(link.data(), function(key, value) {
      dataAttrs[key] = value;
    });
    
    syncData.tabs.push({
      tabId: tabId,
      contentId: contentId,
      title: link.text().trim().replace('×', '').trim(),
      data: dataAttrs,
      isActive: link.hasClass('active')
    });
  });
  
  // Отправляем на сервер
  $.ajax({
    url: '/crm/save_form_state.php',
    type: 'POST',
    contentType: 'application/json',
    data: JSON.stringify({
      action: 'sync',
      user_id: userId,
      data: syncData
    }),
    async: !sync, // Если sync=true, то запрос синхронный
    success: function(response) {
      console.log('Синхронизация с сервером выполнена успешно');
    },
    error: function(xhr, status, error) {
      console.error('Ошибка синхронизации с сервером:', error);
    }
  });
}

// Функция для открытия редактирования операции производства в новой вкладке
function openProductionOperationTab(operationId, operationNumber = null) {
  console.log('Opening production operation tab:', operationId, operationNumber);
  const uniqueSuffix = Date.now(); // добавляем уникальный суффикс для избежания конфликтов
  const tabId = 'tab-production-operation-' + operationId + '-' + uniqueSuffix;
  const tabContentId = 'content-production-operation-' + operationId + '-' + uniqueSuffix;
  
  // Определяем заголовок вкладки
  let title = 'Новая операция производства';
  
  if (operationId > 0) {
    // Если передан номер операции, используем его
    if (operationNumber) {
      title = `Операция ${operationNumber}`;
    } else {
      title = `Операция #${operationId}`;
    }
  }

  // Создаем новую вкладку
  let navItem = $(`
    <li class="nav-item">
      <a class="nav-link" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" data-operation-id="${operationId}" data-document-type="production-operation">
        ${title}
        <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>
  `);

  navItem.find('.btn-close').on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
  });

  let tabPane = $(`
    <div class="tab-pane fade" id="${tabContentId}" data-document-type="production-operation" data-id="${operationId}">
      <p>Загрузка формы редактирования...</p>
    </div>
  `);

  $('#crm-tabs').append(navItem);
  $('#crm-tab-content').append(tabPane);

  $('#' + tabId).tab('show');

  // Загружаем содержимое формы редактирования
  $.ajax({
    url: '/crm/modules/production/operations/edit_partial.php',
    data: { 
      id: operationId, 
      tab: 1,
      tab_id: tabId,
      content_id: tabContentId
    },
    success: function(html) {
      tabPane.html(html).addClass('fade-in');
      
      // После загрузки содержимого запускаем отслеживание изменений формы
      initFormTracking(tabContentId);
      
      // Восстанавливаем сохраненное состояние формы, если есть
      restoreTabFormState(tabContentId, 'production-operation', operationId);
      
      // Сохраняем состояние вкладок
      saveTabsState();
    },
    error: function(xhr) {
      if (xhr.status === 404) {
        tabPane.html('<div class="text-danger">Файл не найден (404). Проверьте структуру папок.</div>');
      } else if (xhr.status === 500) {
        tabPane.html('<div class="text-danger">Ошибка 500 на сервере. Проверьте PHP-код.</div>');
      } else {
        tabPane.html('<div class="text-danger">Ошибка загрузки ('+xhr.status+')</div>');
      }
    }
  });
  
  return { tabId, tabContentId };
}

// Инициализация сайдбара и избранного
$(function() {
  // УДАЛЯЮ ВТОРОЙ БЛОК КОДА ИНИЦИАЛИЗАЦИИ САЙДБАРА
  // Эта часть полностью заменена новой реализацией в sidebar.php

  // Обработка клика по элементам сайдбара
  $('.sidebar .nav-link:not(.sidebar-toggle)').on('click', function(e) {
    e.preventDefault();
    const path = $(this).data('module');
    if (path) {
      console.log('Клик по элементу сайдбара, открываю модуль:', path);
      openModuleTab(path);
    } else {
      console.error('Ошибка: data-module не определен для элемента', this);
    }
  });

  // Избранные вкладки в шапке
  let favorites = JSON.parse(localStorage.getItem('favorites')) || [];

  function saveFavorites() {
    localStorage.setItem('favorites', JSON.stringify(favorites));
  }

  function renderFavorites() {
    const $fav = $('#favorite-tabs');
    $fav.empty();
    favorites.slice(0, 6).forEach(function(path) {
      // Проверяем, что путь определен
      if (!path) {
        console.error('Ошибка: неопределенный путь в избранном');
        return;
      }
      
      const title = getModuleTitle(path) || path;
      const btn = $(`<button class="btn btn-link text-light d-flex align-items-center favorite-tab-btn" data-module="${path}" title="${title}">
                       <i class="fas fa-star me-1"></i><span>${title}</span>
                     </button>`);
      btn.on('click', function(e) {
        e.preventDefault();
        console.log('Clicked favorite tab:', path);
        openModuleTab(path);
      });
      $fav.append(btn);
    });
    // Проверяем количество созданных кнопок
    console.log('Rendered', $fav.find('button').length, 'favorite buttons');
    
    // Добавляем прямую обработку кликов после рендеринга
    setTimeout(function() {
      // Повторно привязываем события на всякий случай
      $('#favorite-tabs button').each(function() {
        const $btn = $(this);
        const path = $btn.data('module');
        if (!path) {
          console.error('Ошибка: data-module не определен для кнопки избранного', $btn);
          return;
        }
        
        $btn.off('click').on('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          console.log('Direct click handler:', path);
          openModuleTab(path);
          return false;
        });
      });
    }, 100);
  }

  function toggleFavorite(path) {
    const idx = favorites.indexOf(path);
    if (idx === -1) {
      if (favorites.length >= 6) {
        alert('Можно добавить не более 6 избранных');
        return;
      }
      favorites.push(path);
      // Добавляем класс favorite к ссылке для стилизации
      $(`.sidebar .nav-link[data-module="${path}"]`).addClass('favorite');
      // Также добавляем класс active к звездочке для визуального выделения
      $(`.sidebar .nav-link[data-module="${path}"] .star-icon`).addClass('active');
    } else {
      favorites.splice(idx, 1);
      // Удаляем классы для деактивации
      $(`.sidebar .nav-link[data-module="${path}"]`).removeClass('favorite');
      $(`.sidebar .nav-link[data-module="${path}"] .star-icon`).removeClass('active');
    }
    saveFavorites();
    renderFavorites();
  }

  // Делегированный обработчик клика по звездочке в сайдбаре
  $('.sidebar').on('click', '.star-icon', function(e) {
    e.stopPropagation();
    const path = $(this).closest('.nav-link').data('module');
    toggleFavorite(path);
  });

  // Доступ из глобальной области
  window.toggleFavorite = toggleFavorite;

  // Инициализируем классы избранного и отрисовываем
  favorites.forEach(function(path) {
    // Добавляем классы к ссылке и звездочке для правильного отображения
    $(`.sidebar .nav-link[data-module="${path}"]`).addClass('favorite');
    $(`.sidebar .nav-link[data-module="${path}"] .star-icon`).addClass('active');
  });
  renderFavorites();
  
  // Добавляем обработчик для nav-item в навбаре (если они есть)
  // Используем делегирование событий для большей надежности
  $(document).on('click', '.navbar .nav-item .nav-link[data-module], .favorite-tab-btn', function(e) {
    e.preventDefault();
    const path = $(this).data('module');
    if (path) {
      console.log('Clicked nav-item or favorite:', path);
      openModuleTab(path);
    } else {
      console.error('Ошибка: data-module не определен для элемента навигации', this);
    }
  });
  
  // Отладочный код - проверяем элементы
  console.log('Favorite tabs:', $('#favorite-tabs').length, 'found');
  console.log('Favorite buttons:', $('#favorite-tabs button').length, 'found');
  console.log('Nav items:', $('.navbar .nav-item .nav-link[data-module]').length, 'found');
});

// Вспомогательная функция для программного заполнения полей без установки флага ручного изменения
function setAutoFilledValue(element, value) {
  if (!element) return;
  
  // Устанавливаем атрибут, указывающий, что значение было заполнено программно
  element.dataset.autoFilled = 'true';
  
  // Удаляем флаг ручного изменения, если он был установлен ранее
  element.removeAttribute('data-user-modified');
  
  // Задаем значение в зависимости от типа поля
  if (element.type === 'checkbox' || element.type === 'radio') {
    element.checked = !!value;
  } else {
    element.value = value;
  }
  
  // Если необходимо - вызываем событие change для обработки зависимых полей
  // но делаем это в режиме программного изменения
  const event = new Event('change', { bubbles: true });
  element.dispatchEvent(event);
}

// Кастомная модальная система
$(document).on('click', '[data-bs-dismiss="modal"]', function() {
  // Находим ближайшее модальное окно и скрываем его
  const $modal = $(this).closest('.modal');
  if ($modal.length) {
    try {
      $modal.modal('hide');
    } catch (e) {
      console.error('Ошибка при закрытии модального окна через jQuery:', e);
      // В случае ошибки пытаемся закрыть через Bootstrap API
      try {
        const modalElement = $modal[0];
        const bsModal = bootstrap.Modal.getInstance(modalElement);
        if (bsModal) bsModal.hide();
      } catch (e2) {
        console.error('Ошибка при закрытии модального окна через Bootstrap API:', e2);
      }
    }
  }
  
  // Очищаем остатки модальных окон
  setTimeout(window.cleanupModals, 300);
});

// Переопределение стандартной функции confirm
(function() {
  // Сохраняем оригинальную функцию
  const originalConfirm = window.confirm;
  
  // Переопределяем глобальную функцию confirm
  window.confirm = function(message) {
    console.log('Перехваченное диалоговое окно confirm:', message);
    // Всегда возвращаем true - как будто пользователь всегда подтверждает
    return true;
  };
})();

// Функция для обновления статуса вкладки (отображение индикатора несохраненных изменений)
function updateTabModifiedStatus(tabContentId, hasModifiedData) {
  const tabLink = document.querySelector(`a[href="#${tabContentId}"]`);
  if (!tabLink) return;
  
  if (hasModifiedData) {
    // Добавляем индикатор, если его еще нет
    if (!tabLink.querySelector('.tab-modified-indicator')) {
      const indicator = document.createElement('span');
      indicator.className = 'tab-modified-indicator ms-1';
      indicator.innerHTML = '●';
      indicator.style.color = '#ffc107';
      indicator.style.fontSize = '12px';
      
      // Вставляем индикатор после заголовка, но перед кнопкой закрытия
      const closeButton = tabLink.querySelector('.btn-close');
      if (closeButton) {
        tabLink.insertBefore(indicator, closeButton);
      } else {
        tabLink.appendChild(indicator);
      }
    }
  } else {
    // Удаляем индикатор, если он есть
    const indicator = tabLink.querySelector('.tab-modified-indicator');
    if (indicator) {
      indicator.remove();
    }
  }
}

// Инициализация индикаторов вкладок
function initTabIndicators() {
  // Перебираем все формы в globalFormsData
  for (const tabContentId in globalFormsData.forms) {
    const formData = globalFormsData.forms[tabContentId];
    if (formData && formData.userModified) {
      // Если форма имеет несохраненные пользовательские изменения, обновляем индикатор
      updateTabModifiedStatus(tabContentId, true);
    }
  }
}

// Функция для очистки стилей модальных окон
function cleanupModals() {
  console.log('Очистка модальных окон...');
  
  // Очистка body от классов и стилей модальных окон
  if (document.body) {
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
  }
  
  // Удаляем backdrop элементы нативным способом
  const backdrops = document.querySelectorAll('.modal-backdrop');
  backdrops.forEach(backdrop => {
    if (backdrop && backdrop.parentNode) {
      backdrop.parentNode.removeChild(backdrop);
    }
  });
  
  // Сбрасываем все модальные окна
  const modals = document.querySelectorAll('.modal');
  modals.forEach(modal => {
    // Пропускаем обработку несуществующих элементов
    if (!modal) return;
    
    // Удаляем экземпляры Bootstrap Modal
    if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
      try {
        const modalInstance = bootstrap.Modal.getInstance(modal);
        if (modalInstance) {
          modalInstance.dispose();
        }
      } catch (e) {
        // Ничего не делаем при ошибке - просто продолжаем очистку
      }
    }
    
    // Сбрасываем классы и стили модального окна
    modal.classList.remove('show');
    if (modal.style) {
      modal.style.display = 'none';
      modal.style.paddingRight = '';
    }
    
    // Сбрасываем атрибуты
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('role', 'dialog');
    modal.removeAttribute('aria-hidden');
  });
  
  // Чистим очередь анимации, устанавливая все на начальное состояние
  // Это поможет избежать "зависших" анимаций, которые могут вызывать проблемы
  if (document.body) {
    document.body.offsetHeight; // Force reflow
  }
  
  console.log('Все модальные окна очищены');
}

// Проверяем наличие сохраненной сессии после загрузки страницы
$(document).ready(function() {
  // Очищаем все модальные окна при загрузке страницы
  if (typeof window.cleanupModals === 'function') {
    window.cleanupModals();
  }
  
  console.log('Документ загружен, ожидаем инициализацию Bootstrap...');
  
  // Проверка доступности Bootstrap выполняется в footer.php
  // Там же будет вызвана функция restoreUserSession после загрузки Bootstrap
  
  // Инициализируем индикаторы вкладок с небольшой задержкой
  setTimeout(function() {
    if (typeof initTabIndicators === 'function') {
      initTabIndicators();
    }
  }, 1500);
});

// Функция для патчинга Bootstrap Modal, чтобы он не вызывал ошибки при работе с null-элементами
function patchBootstrapModal() {
  // Проверяем, доступен ли Bootstrap и его компоненты
  if (typeof bootstrap === 'undefined' || typeof bootstrap.Modal === 'undefined') {
    console.warn('Bootstrap Modal не обнаружен, патч не применён');
    return;
  }
  
  // Проверяем, был ли уже применен патч
  if (bootstrap.Modal._patched) {
    console.log('Bootstrap Modal уже был пропатчен');
    return;
  }
  
  try {
    // Получаем прототип класса Modal
    const modalProto = bootstrap.Modal.prototype;
    
    // Сохраняем оригинальные методы
    const originalShowElement = modalProto._showElement;
    const originalHide = modalProto.hide;
    const originalDispose = modalProto.dispose;
    const originalResetAdjustments = modalProto._resetAdjustments;
    const originalSetEscapeEvent = modalProto._setEscapeEvent;
    const originalAdjustDialog = modalProto._adjustDialog;
    const originalEnforceFocus = modalProto._enforceFocus;
    const originalHideModal = modalProto._hideModal;
    
    // Общая функция для безопасной работы с элементами
    function safeElementAccess(callback, errorCallback) {
      try {
        return callback();
      } catch (e) {
        console.error('Bootstrap Modal патч поймал ошибку:', e);
        if (typeof errorCallback === 'function') {
          errorCallback(e);
        }
        return null;
      }
    }
    
    // Патчим метод _showElement для проверки существования элемента
    modalProto._showElement = function(relatedTarget) {
      if (!this._element || !document.body.contains(this._element)) {
        console.warn('Bootstrap Modal: элемент не найден или не добавлен в DOM');
        return;
      }
      
      return safeElementAccess(() => {
        return originalShowElement.call(this, relatedTarget);
      }, () => {
        // В случае ошибки пытаемся привести DOM в нормальное состояние
        if (this._element) {
          this._element.style.display = 'none';
        }
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
        setTimeout(window.cleanupModals, 0);
      });
    };
    
    // Патчим метод hide для безопасного скрытия
    modalProto.hide = function() {
      if (!this._element || !document.body.contains(this._element)) {
        console.warn('Bootstrap Modal: элемент не найден при попытке скрытия');
        // Принудительно очищаем, даже если элемент не найден
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());
        return;
      }
      
      // Полностью заменяем функцию hide вместо вызова оригинальной
      return safeElementAccess(() => {
        // Проверяем, не скрыто ли уже модальное окно
        if (!this._isShown || this._isTransitioning) {
          return;
        }
        
        // Вызываем обработчики событий перед скрытием, если они есть
        if (typeof this._triggerBackdropTransition === 'function') {
          try {
            // Если метод возвращает true, значит он сам обрабатывает анимацию закрытия
            if (this._triggerBackdropTransition()) {
              return;
            }
          } catch (e) {
            console.warn('Ошибка при вызове _triggerBackdropTransition:', e);
          }
        }
        
        // Устанавливаем флаги
        this._isShown = false;
        
        // Удаляем backdrop
        try {
          if (this._backdrop) {
            // Безопасно вызываем hide у backdrop
            if (typeof this._backdrop.hide === 'function') {
              this._backdrop.hide(() => {
                this._hideModal();
              });
            } else {
              // Если метод hide у backdrop не доступен, сразу вызываем _hideModal
              this._hideModal();
            }
          } else {
            this._hideModal();
          }
        } catch (e) {
          console.warn('Ошибка при удалении backdrop:', e);
          // В случае ошибки просто вызываем _hideModal напрямую
          this._hideModal();
        }
      }, () => {
        // В случае ошибки выполняем минимально необходимые действия
        try {
          // Устанавливаем основные флаги
          this._isShown = false;
          
          // Вызываем метод скрытия напрямую
          this._hideModal();
        } catch (e) {
          console.error('Критическая ошибка при скрытии модального окна:', e);
          // В случае ошибки принудительно очищаем DOM
          document.body.classList.remove('modal-open');
          document.body.style.overflow = '';
          document.body.style.paddingRight = '';
          
          // Скрываем элемент модального окна, если он доступен
          if (this._element) {
            this._element.style.display = 'none';
            this._element.classList.remove('show');
            this._element.setAttribute('aria-hidden', 'true');
            this._element.removeAttribute('aria-modal');
            this._element.removeAttribute('role');
          }
          
          // Удаляем backdrop вручную
          const backdrops = document.querySelectorAll('.modal-backdrop');
          backdrops.forEach(backdrop => {
            if (backdrop && backdrop.parentNode) {
              backdrop.parentNode.removeChild(backdrop);
            }
          });
          
          // Запускаем дополнительную очистку
          setTimeout(window.cleanupModals, 0);
        }
      });
    };
    
    // Патчим метод _resetAdjustments для безопасной работы с элементами
    modalProto._resetAdjustments = function() {
      if (!this._element) {
        console.warn('Bootstrap Modal: элемент не найден в _resetAdjustments');
        return;
      }
      
      if (!this._element.style) {
        console.warn('Bootstrap Modal: элемент не имеет свойства style');
        return;
      }
      
      return safeElementAccess(() => {
        return originalResetAdjustments.call(this);
      });
    };
    
    // Патчим метод _adjustDialog
    modalProto._adjustDialog = function() {
      if (!this._element) {
        console.warn('Bootstrap Modal: элемент не найден в _adjustDialog');
        return;
      }
      
      if (!this._element.style) {
        console.warn('Bootstrap Modal: элемент не имеет свойства style в _adjustDialog');
        return;
      }
      
      return safeElementAccess(() => {
        return originalAdjustDialog.call(this);
      });
    };
    
    // Патчим метод _enforceFocus
    modalProto._enforceFocus = function() {
      if (!this._element) {
        console.warn('Bootstrap Modal: элемент не найден в _enforceFocus');
        return;
      }
      
      return safeElementAccess(() => {
        return originalEnforceFocus.call(this);
      });
    };
    
    // Патчим метод _setEscapeEvent
    modalProto._setEscapeEvent = function() {
      if (!this._element) {
        console.warn('Bootstrap Modal: элемент не найден в _setEscapeEvent');
        return;
      }
      
      return safeElementAccess(() => {
        return originalSetEscapeEvent.call(this);
      });
    };
    
    // Патчим метод dispose для безопасного удаления
    modalProto.dispose = function() {
      return safeElementAccess(() => {
        const result = originalDispose.call(this);
        
        // Принудительно удаляем все обработчики и ссылки
        if (this._element) {
          this._element.removeAttribute('aria-modal');
          this._element.removeAttribute('role');
          this._element.removeAttribute('aria-hidden');
          
          // Удаляем все обработчики событий, связанные с Bootstrap
          ['show.bs.modal', 'shown.bs.modal', 'hide.bs.modal', 'hidden.bs.modal', 
           'hidePrevented.bs.modal', 'mousedown.dismiss.bs.modal'].forEach(event => {
            this._element.removeEventListener(event, () => {});
          });
        }
        
        return result;
      }, () => {
        // Принудительная очистка в случае ошибки
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());
      });
    };
    
    // Патчим Backdrop если он доступен
    if (typeof bootstrap.Backdrop !== 'undefined') {
      try {
        const backdropProto = bootstrap.Backdrop.prototype;
        const originalBackdropDispose = backdropProto.dispose;
        const originalBackdropHide = backdropProto.hide;
        
        // Проверка на существование элемента backdrop перед вызовом hide
        backdropProto.hide = function(callback) {
          if (!this._element || !this._isAppended) {
            if (typeof callback === 'function') {
              callback();
            }
            return;
          }
          
          return safeElementAccess(() => {
            return originalBackdropHide.call(this, callback);
          }, () => {
            // Ручное удаление backdrop при ошибке
            if (this._element && this._element.parentNode) {
              this._element.parentNode.removeChild(this._element);
            }
            if (typeof callback === 'function') {
              callback();
            }
          });
        };
        
        // Безопасный dispose для backdrop
        backdropProto.dispose = function() {
          if (!this._isAppended || !this._element) {
            return;
          }
          
          return safeElementAccess(() => {
            return originalBackdropDispose.call(this);
          }, () => {
            // Ручное удаление backdrop при ошибке
            if (this._element && this._element.parentNode) {
              this._element.parentNode.removeChild(this._element);
            }
          });
        };
      } catch (e) {
        console.error('Ошибка при патчинге Bootstrap Backdrop:', e);
      }
    } else {
      // Если Backdrop недоступен напрямую, патчим через Modal.js
      // Это нужно для интеграции с модальными окнами Bootstrap
      const originalModalInitBackdrop = modalProto._initializeBackDrop;
      
      modalProto._initializeBackDrop = function() {
        const backdrop = originalModalInitBackdrop.call(this);
        
        if (backdrop) {
          // Переопределяем метод dispose для backdrop
          const originalDispose = backdrop.dispose.bind(backdrop);
          backdrop.dispose = function() {
            try {
              originalDispose();
            } catch (e) {
              console.error('Error in backdrop dispose:', e);
              // Удаляем backdrop вручную при ошибке
              const backdrops = document.querySelectorAll('.modal-backdrop');
              backdrops.forEach(el => {
                if (el && el.parentNode) {
                  el.parentNode.removeChild(el);
                }
              });
            }
          };
          
          // Переопределяем метод hide для backdrop
          const originalHide = backdrop.hide.bind(backdrop);
          backdrop.hide = function(callback) {
            try {
              originalHide(callback);
            } catch (e) {
              console.error('Error in backdrop hide:', e);
              // Выполняем callback вручную при ошибке
              if (typeof callback === 'function') {
                callback();
              }
            }
          };
        }
        
        return backdrop;
      };
    }
    
    // Патчим метод _hideModal для предотвращения ошибок с null.style
    modalProto._hideModal = function() {
      if (!this._element) {
        console.warn('Bootstrap Modal: элемент не найден в _hideModal');
        return;
      }
      
      return safeElementAccess(() => {
        // Полностью заменяем реализацию _hideModal, а не вызываем оригинальный метод
        
        // Скрываем диалог
        if (this._dialog && this._dialog.style) {
          this._dialog.style.display = 'none';
        }
        
        // Скрываем элемент модального окна
        this._element.setAttribute('aria-hidden', 'true');
        this._element.removeAttribute('aria-modal');
        this._element.removeAttribute('role');
        
        if (this._element.style) {
          this._element.style.display = 'none';
        }
        
        // Удаляем класс show
        if (this._element.classList) {
          this._element.classList.remove('show');
        }
        
        // Устанавливаем флаг транзиции
        this._isTransitioning = false;
        
        // Очищаем состояние body
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
        
        // Удаляем backdrop вручную вместо вызова this._backdrop.hide()
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => {
          if (backdrop && backdrop.parentNode) {
            backdrop.parentNode.removeChild(backdrop);
          }
        });
        
        // Выполняем дополнительные действия, которые обычно выполняются в callback backdrop.hide
        if (typeof this._resetAdjustments === 'function') {
          try {
            this._resetAdjustments();
          } catch (e) {
            console.warn('Error in _resetAdjustments:', e);
          }
        }
        
        if (this._scrollBar && typeof this._scrollBar.reset === 'function') {
          try {
            this._scrollBar.reset();
          } catch (e) {
            console.warn('Error in scrollBar.reset:', e);
          }
        }
        
        // Генерируем событие HIDDEN
        if (typeof EventHandler !== 'undefined' && 
            typeof EventHandler.trigger === 'function' && 
            this._element) {
          try {
            EventHandler.trigger(this._element, 'hidden.bs.modal');
          } catch (e) {
            console.warn('Error triggering hidden.bs.modal:', e);
          }
        }
        
        // Для уверенности вызываем дополнительную очистку
        setTimeout(window.cleanupModals, 0);
      }, () => {
        // В случае ошибки принудительно очищаем состояние
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
        
        // Удаляем backdrop вручную при ошибке
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => {
          if (backdrop && backdrop.parentNode) {
            backdrop.parentNode.removeChild(backdrop);
          }
        });
        
        // Также очищаем модальные окна для уверенности
        setTimeout(window.cleanupModals, 0);
      });
    };
    
    // Отмечаем, что патч был применен
    bootstrap.Modal._patched = true;
    
    // Дополнительно патчим статические методы, которые могут использоваться внутри Bootstrap
    try {
      // Патчим Selector Engine, если он доступен
      if (bootstrap.Selector && bootstrap.Selector.findOne) {
        const originalFindOne = bootstrap.Selector.findOne;
        bootstrap.Selector.findOne = function(selector, element) {
          try {
            return originalFindOne(selector, element);
          } catch (e) {
            console.warn('Bootstrap Selector Error:', e);
            return null;
          }
        };
      }
      
      // Патчим DOM манипуляции, если они доступны
      if (bootstrap.Util) {
        // Патчим getElementFromSelector, если он существует
        if (bootstrap.Util.getElementFromSelector) {
          const originalGetElementFromSelector = bootstrap.Util.getElementFromSelector;
          bootstrap.Util.getElementFromSelector = function(element) {
            try {
              if (!element) return null;
              return originalGetElementFromSelector(element);
            } catch (e) {
              console.warn('Bootstrap Util.getElementFromSelector Error:', e);
              return null;
            }
          };
        }
        
        // Патчим getSelectorFromElement, если он существует
        if (bootstrap.Util.getSelectorFromElement) {
          const originalGetSelectorFromElement = bootstrap.Util.getSelectorFromElement;
          bootstrap.Util.getSelectorFromElement = function(element) {
            try {
              if (!element) return null;
              return originalGetSelectorFromElement(element);
            } catch (e) {
              console.warn('Bootstrap Util.getSelectorFromElement Error:', e);
              return null;
            }
          };
        }
      }
    } catch (e) {
      console.error('Ошибка при дополнительном патчинге Bootstrap:', e);
    }
    
    console.log('Bootstrap Modal успешно пропатчен для предотвращения ошибок');
  } catch (e) {
    console.error('Ошибка при патчинге Bootstrap Modal:', e);
  }
}
  
// ГЛОБАЛЬНЫЙ ДЕЛЕГИРОВАННЫЙ ОБРАБОТЧИК ДЛЯ ВСЕХ ФОРМ НА ВКЛАДКАХ
$(document).on('input change', 'input, select, textarea', function(e) {
  // Более универсальный поиск родительской вкладки - ищем любой ближайший элемент с ID, содержащим "content-"
  const $input = $(this);
  const $tabPane = $input.closest('.tab-pane, [id^="content-"], [class*="form-control"]').closest('[id]');
  
  if ($tabPane.length) {
    const tabId = $tabPane.attr('id');
    console.log('[CHANGED] Зарегистрировано изменение в элементе:', $input.attr('name') || $input.attr('id') || 'unnamed');
    
    // Устанавливаем несколько атрибутов, чтобы гарантировать регистрацию изменения
    $tabPane.attr('data-has-unsaved-changes', 'true');
    $tabPane.attr('data-has-modified-fields', 'true');
    $tabPane.attr('data-has-dynamic-changes', 'true');
    
    // Устанавливаем атрибут на сам элемент
    $input.attr('data-user-modified', 'true');
    
    // Обновляем данные в globalFormsData, создавая запись, если её нет
    if (window.globalFormsData) {
      if (!globalFormsData.forms[tabId]) {
        globalFormsData.forms[tabId] = { userModified: true };
      } else {
        globalFormsData.forms[tabId].userModified = true;
      }
    }
    
    console.log('[DELEGATE] Изменено поле, выставлены атрибуты изменений для', tabId);
    
    // УДАЛЯЕМ ТЕСТОВОЕ УВЕДОМЛЕНИЕ ОБ ИЗМЕНЕНИИ ПОЛЯ
    // Это было нужно только для тестирования, сейчас оно не требуется
    // ---
  } else {
    console.log('[WARNING] Не удалось найти родительскую вкладку для измененного поля', $input.attr('name') || $input.attr('id') || 'unnamed');
  }
});

// Добавляем таймаут для автоматического закрытия, чтобы избежать зависания UI
// Это запасной вариант, если модальное окно по какой-то причине не будет закрыто
setTimeout(function() {
  try {
    // Проверяем, существует ли еще элемент модального окна
    const modalId = 'confirmModal';
    const modalElement = document.getElementById(modalId);
    if (!modalElement) {
      console.log('Модальное окно уже закрыто или удалено');
      return;
    }
    
    // Безопасно проверяем класс
    let stillOpen = false;
    try {
      if (modalElement.classList) {
        stillOpen = modalElement.classList.contains('show');
      }
    } catch (err) {
      console.warn('Ошибка при проверке класса модального окна:', err);
    }
    
    if (stillOpen) {
      console.warn('Обнаружено зависшее модальное окно, закрываем принудительно');
      window.cleanupModals();
      
      // Если не была нажата кнопка подтверждения, считаем это отменой
      if (!confirmClicked) {
        safeExecuteCancelCallback();
      } else {
        safeExecuteConfirmCallback();
      }
    }
  } catch (e) {
    console.warn('Ошибка при автоматическом закрытии модального окна:', e);
  }
}, 10000); // 10 секунд

// Используем функции из modal.js для работы с модальными окнами:
// - patchBootstrapModal: патч для Bootstrap Modal
// - cleanupModals: очистка модальных окон
// - showConfirmationModal: показ окна подтверждения
// - showUnsavedChangesConfirm: показ предупреждения о несохраненных изменениях
// - hideUnsavedChangesModal: скрытие модального окна с предупреждением

// Тестовая функция для проверки работы модальных окон
window.testModal = function() {
  console.log('Тестирование модального окна');
  
  // Проверка доступности Bootstrap
  if (typeof bootstrap === 'undefined' || typeof bootstrap.Modal === 'undefined') {
    console.error('Bootstrap Modal недоступен!');
    return false;
  }
  
  // Проверяем доступность unsavedChangesModal
  const modalEl = document.getElementById('unsavedChangesModal');
  if (!modalEl) {
    console.error('Модальное окно unsavedChangesModal не найдено в DOM!');
    return false;
  }
  
  console.log('Модальное окно найдено, попытка показать');
  
  // Пробуем получить существующий экземпляр
  let modalInstance = bootstrap.Modal.getInstance(modalEl);
  
  // Если экземпляр не найден, создаем новый
  if (!modalInstance) {
    console.log('Создание нового экземпляра модального окна');
    modalInstance = new bootstrap.Modal(modalEl, {
      backdrop: true,
      keyboard: true,
      focus: true
    });
  }
  
  // Показываем модальное окно
  try {
    modalInstance.show();
    console.log('Модальное окно должно быть показано');
    return true;
  } catch (e) {
    console.error('Ошибка при показе модального окна:', e);
    return false;
  }
};

 