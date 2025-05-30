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
  
  // Проверка входного параметра
  if (!module) {
    console.error('Ошибка: не указан параметр module');
    return;
  }
  
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
    // Пытаемся преобразовать format=measurements/edit_partial&id=1 в правильный формат
    if (module.includes('&')) {
      console.warn('Получен параметр с амперсандом, преобразую его:', module);
      // Пример преобразования measurements/edit_partial&id=1 в measurements/edit_partial
      module = module.split('&')[0];
      console.log('Преобразованный параметр:', module);
    }
    
    // НИКОГДА не вызываем openModuleTab напрямую для edit_partial - это вызывает проблемы с URL
    if (module.includes('edit_partial')) {
      console.error('Некорректный модуль для openNewTab:', module);
      // Убираем вывод ошибки пользователю, чтобы не пугать его
      console.warn('Попытка открыть вкладку с модулем:', module);
      
      // Если это измерения, открываем список измерений
      if (module.includes('measurements')) {
        console.log('Перенаправление на список измерений');
        openModuleTab('measurements/list');
      }
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

/**
 * ==============================
 * СЛАЙДЕР ПРОВЕДЕНИЯ ДОКУМЕНТОВ
 * ==============================
 */

/**
 * Создаёт HTML-разметку для слайдера проведения
 * @param {Object} options - Опции для создания слайдера
 * @param {string} options.id - ID слайдера
 * @param {boolean} options.checked - Начальное состояние (true/false)
 * @param {string} options.size - Размер слайдера ('sm', 'md', 'lg')
 * @param {boolean} options.disabled - Отключён ли слайдер
 * @param {string} options.label - Текст лейбла
 * @param {function} options.onChange - Callback при изменении состояния
 * @returns {string} HTML-разметка слайдера
 */
window.createConductSlider = function(options = {}) {
  const {
    id = 'conduct-slider',
    checked = false,
    size = 'md',
    disabled = false,
    label = 'Проведён',
    onChange = null
  } = options;

  const sizeClass = size !== 'md' ? ` ${size}` : '';
  const activeClass = checked ? ' active' : '';
  const disabledClass = disabled ? ' disabled' : '';
  const wrapperActiveClass = checked ? ' active' : '';

  const sliderHtml = `
    <div class="conduct-slider-wrapper${wrapperActiveClass}" data-slider-id="${id}">
      <div class="conduct-slider${sizeClass}${activeClass}${disabledClass}" 
           id="${id}" 
           data-checked="${checked}"
           data-original-checkbox=""
           tabindex="0"
           role="switch"
           aria-checked="${checked}"
           aria-label="${label}">
      </div>
      <label class="conduct-slider-label" for="${id}">${label}</label>
    </div>
  `;

  // Если передан callback, сохраняем его для этого слайдера
  if (onChange && typeof onChange === 'function') {
    window.conductSliderCallbacks = window.conductSliderCallbacks || {};
    window.conductSliderCallbacks[id] = onChange;
  }

  return sliderHtml;
};

/**
 * Заменяет чекбокс на слайдер
 * @param {string|HTMLElement} checkboxSelector - Селектор или элемент чекбокса
 * @param {Object} options - Опции для слайдера
 */
window.replaceConductCheckboxWithSlider = function(checkboxSelector, options = {}) {
  const checkbox = typeof checkboxSelector === 'string' 
    ? document.querySelector(checkboxSelector) 
    : checkboxSelector;

  if (!checkbox) {
    console.warn('Чекбокс не найден:', checkboxSelector);
    return;
  }

  // Получаем текущие данные чекбокса
  const checked = checkbox.checked;
  const disabled = checkbox.disabled;
  const label = options.label || checkbox.closest('label')?.textContent?.trim() || 'Проведён';
  const id = options.id || checkbox.id || 'conduct-slider-' + Date.now();

  // Создаём слайдер
  const sliderOptions = {
    id: id,
    checked: checked,
    disabled: disabled,
    label: label,
    size: options.size || 'md',
    onChange: options.onChange
  };

  const sliderHtml = window.createConductSlider(sliderOptions);

  // Заменяем чекбокс на слайдер
  const checkboxContainer = checkbox.closest('.form-check') || checkbox.parentElement;
  const tempDiv = document.createElement('div');
  tempDiv.innerHTML = sliderHtml;
  const sliderWrapper = tempDiv.firstElementChild;

  // Сохраняем ссылку на оригинальный чекбокс в слайдере
  const slider = sliderWrapper.querySelector('.conduct-slider');
  slider.dataset.originalCheckbox = checkbox.id || checkbox.name || 'unknown';

  // Скрываем оригинальный чекбокс вместо удаления
  checkbox.style.display = 'none';
  checkbox.setAttribute('data-replaced-by-slider', id);

  // Вставляем слайдер
  checkboxContainer.parentNode.insertBefore(sliderWrapper, checkboxContainer);
  
  // Инициализируем обработчики событий для нового слайдера
  initConductSliderEvents(slider);

  console.log(`Чекбокс ${checkboxSelector} заменён на слайдер ${id}`);
};

/**
 * Инициализирует обработчики событий для слайдера
 * @param {HTMLElement} slider - Элемент слайдера
 */
function initConductSliderEvents(slider) {
  if (!slider || slider.dataset.eventsInitialized) return;

  // Обработчик клика
  slider.addEventListener('click', function(e) {
    e.preventDefault();
    if (slider.classList.contains('disabled')) return;
    
    toggleConductSlider(slider);
  });

  // Обработчик клика на лейбл
  const wrapper = slider.closest('.conduct-slider-wrapper');
  const label = wrapper?.querySelector('.conduct-slider-label');
  if (label) {
    label.addEventListener('click', function(e) {
      e.preventDefault();
      if (slider.classList.contains('disabled')) return;
      
      toggleConductSlider(slider);
    });
  }

  // Обработчик клавиатуры
  slider.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      if (slider.classList.contains('disabled')) return;
      
      toggleConductSlider(slider);
    }
  });

  slider.dataset.eventsInitialized = 'true';
}

/**
 * Переключает состояние слайдера
 * @param {HTMLElement} slider - Элемент слайдера
 * @param {boolean} silent - Не вызывать callback (по умолчанию false)
 */
window.toggleConductSlider = function(slider, silent = false) {
  if (!slider || slider.classList.contains('disabled')) return;

  const wrapper = slider.closest('.conduct-slider-wrapper');
  const currentState = slider.dataset.checked === 'true';
  const newState = !currentState;

  // Добавляем анимацию изменения
  slider.classList.add('changing');
  setTimeout(() => slider.classList.remove('changing'), 600);

  // Обновляем состояние
  setConductSliderState(slider, newState, silent);
};

/**
 * Устанавливает состояние слайдера
 * @param {HTMLElement} slider - Элемент слайдера
 * @param {boolean} checked - Новое состояние
 * @param {boolean} silent - Не вызывать callback (по умолчанию false)
 */
window.setConductSliderState = function(slider, checked, silent = false) {
  if (!slider) return;

  const wrapper = slider.closest('.conduct-slider-wrapper');
  
  // Обновляем классы и атрибуты
  slider.dataset.checked = checked.toString();
  slider.setAttribute('aria-checked', checked);
  
  if (checked) {
    slider.classList.add('active');
    wrapper?.classList.add('active');
  } else {
    slider.classList.remove('active');
    wrapper?.classList.remove('active');
  }

  // Синхронизируем с оригинальным чекбоксом, если он есть
  const originalCheckboxId = slider.dataset.originalCheckbox;
  if (originalCheckboxId) {
    const originalCheckbox = document.getElementById(originalCheckboxId) || 
                           document.querySelector(`[data-replaced-by-slider="${slider.id}"]`);
    if (originalCheckbox) {
      originalCheckbox.checked = checked;
      // Вызываем событие change на оригинальном чекбоксе для совместимости
      if (!silent) {
        originalCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }
  }

  // Вызываем callback, если он есть
  if (!silent && window.conductSliderCallbacks && window.conductSliderCallbacks[slider.id]) {
    window.conductSliderCallbacks[slider.id](checked, slider);
  }

  console.log(`Слайдер ${slider.id} установлен в состояние: ${checked}`);
};

/**
 * Получает состояние слайдера
 * @param {string|HTMLElement} sliderSelector - Селектор или элемент слайдера
 * @returns {boolean} Текущее состояние слайдера
 */
window.getConductSliderState = function(sliderSelector) {
  const slider = typeof sliderSelector === 'string' 
    ? document.querySelector(sliderSelector) 
    : sliderSelector;

  if (!slider) {
    console.warn('Слайдер не найден:', sliderSelector);
    return false;
  }

  return slider.dataset.checked === 'true';
};

/**
 * Включает/отключает слайдер
 * @param {string|HTMLElement} sliderSelector - Селектор или элемент слайдера
 * @param {boolean} disabled - Отключить слайдер
 */
window.setConductSliderDisabled = function(sliderSelector, disabled = true) {
  const slider = typeof sliderSelector === 'string' 
    ? document.querySelector(sliderSelector) 
    : sliderSelector;

  if (!slider) {
    console.warn('Слайдер не найден:', sliderSelector);
    return;
  }

  if (disabled) {
    slider.classList.add('disabled');
    slider.removeAttribute('tabindex');
  } else {
    slider.classList.remove('disabled');
    slider.setAttribute('tabindex', '0');
  }
};

/**
 * Инициализирует все слайдеры на странице
 */
window.initAllConductSliders = function() {
  document.querySelectorAll('.conduct-slider').forEach(slider => {
    if (!slider.dataset.eventsInitialized) {
      initConductSliderEvents(slider);
    }
  });
  
  console.log('Инициализированы все слайдеры проведения на странице');
};

// Автоматическая инициализация при загрузке DOM
document.addEventListener('DOMContentLoaded', function() {
  window.initAllConductSliders();
});

// Инициализация при динамическом добавлении контента
document.addEventListener('tabContentLoaded', function() {
  window.initAllConductSliders();
}); 