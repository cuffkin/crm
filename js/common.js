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
  if (!slider || slider.dataset.eventsInitialized === 'true') {
    return;
  }

  slider.addEventListener('click', handleSliderClick);
  slider.addEventListener('keydown', handleSliderKeydown);
  slider.dataset.eventsInitialized = 'true';
}

/**
 * Обработчик клика по слайдеру
 */
function handleSliderClick(event) {
  const slider = event.currentTarget;
  if (slider.classList.contains('disabled')) {
    return;
  }
  toggleSliderState(slider);
}

/**
 * Обработчик нажатия клавиш на слайдере (для доступности)
 * @param {KeyboardEvent} event 
 */
function handleSliderKeydown(event) {
  const slider = event.currentTarget;
  if (slider.classList.contains('disabled')) {
    return;
  }
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault();
    toggleSliderState(slider);
  }
}

/**
 * Переключает состояние слайдера и связанного чекбокса
 * @param {HTMLElement} slider 
 */
function toggleSliderState(slider) {
  const isActive = slider.classList.toggle('active');
  slider.setAttribute('aria-checked', isActive);
  slider.dataset.checked = isActive;

  const wrapper = slider.closest('.conduct-slider-wrapper');
  if (wrapper) {
    wrapper.classList.toggle('active', isActive);
  }

  const originalCheckboxId = slider.dataset.originalCheckbox;
  if (originalCheckboxId) {
    const originalCheckbox = document.getElementById(originalCheckboxId) || document.querySelector(`[name="${originalCheckboxId}"]`);
    if (originalCheckbox) {
      originalCheckbox.checked = isActive;
      // Триггерим событие change на оригинальном чекбоксе для совместимости
      const event = new Event('change', { bubbles: true });
      originalCheckbox.dispatchEvent(event);
    }
  }

  // Вызываем callback, если он был зарегистрирован
  const sliderId = slider.id;
  if (window.conductSliderCallbacks && window.conductSliderCallbacks[sliderId]) {
    window.conductSliderCallbacks[sliderId](isActive);
  }
  
  console.log('Slider', sliderId, 'state changed to', isActive);
}

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
  const sliders = document.querySelectorAll('.conduct-slider:not([data-events-initialized="true"])');
  sliders.forEach(slider => {
    initConductSliderEvents(slider);
  });
  console.log(`Инициализированы все слайдеры проведения на странице (${sliders.length} шт.)`);

  // Дополнительно: заменяем все чекбоксы с классом 'conduct-checkbox-to-replace'
  const checkboxesToReplace = document.querySelectorAll('input[type="checkbox"].conduct-checkbox-to-replace:not([data-replaced-by-slider])');
  checkboxesToReplace.forEach(checkbox => {
    // Для каждого чекбокса можно передать свои опции, если они есть в data-атрибутах
    const options = {
      id: checkbox.dataset.sliderId,
      size: checkbox.dataset.sliderSize,
      label: checkbox.dataset.sliderLabel
      // onChange callback можно задать через JS до вызова этой функции,
      // если привязать его к window.conductSliderCallbacks[checkbox.dataset.sliderId]
    };
    window.replaceConductCheckboxWithSlider(checkbox, options);
  });
  if (checkboxesToReplace.length > 0) {
    console.log(`Заменено чекбоксов на слайдеры: ${checkboxesToReplace.length} шт.`);
  }
};

// Вызываем инициализацию слайдеров при загрузке документа
document.addEventListener('DOMContentLoaded', function() {
  // Даем небольшую задержку, чтобы другие скрипты успели добавить свои элементы
  setTimeout(window.initAllConductSliders, 100); 
});

// Код для управления Bootstrap модальными окнами и предотвращения "прыжков" контента
// ... (этот код у вас уже есть, оставляем его)

// НОВЫЙ КОД ДЛЯ УПРАВЛЕНИЯ INVALID-FEEDBACK
$(document).ready(function() {
    // Универсальный обработчик для скрытия invalid-feedback при заполнении обязательных полей
    function handleRequiredFieldFeedback() {
        const $field = $(this);
        // Ищем .invalid-feedback как следующий элемент или внутри родителя .mb-3/.form-group/.input-group
        let $feedbackElement = $field.nextAll('.invalid-feedback').first(); // Изменено на nextAll().first()
        if (!$feedbackElement.length) {
            $feedbackElement = $field.siblings('.invalid-feedback').first(); // Ищем среди соседей
        }
        if (!$feedbackElement.length) {
            $feedbackElement = $field.parent().find('.invalid-feedback').first(); // Ищем в непосредственном родителе
        }
        if (!$feedbackElement.length) {
            $feedbackElement = $field.closest('.mb-3, .form-group, .input-group, .form-floating').find('.invalid-feedback').first();
        }

        if ($field.val() && String($field.val()).trim() !== '') {
            $field.removeClass('is-invalid');
            if ($feedbackElement.length) {
                $feedbackElement.hide(); // Явно скрываем
            }
        }
        // Если поле снова пустое, Bootstrap сам обработает показ is-invalid при валидации формы.
        // Нет необходимости добавлять $field.addClass('is-invalid'); здесь.
    }

    // Используем делегирование событий для охвата динамически добавляемых элементов
    // Добавляем keyup для более быстрой реакции на ввод текста
    $(document).on('input change keyup', 'input.required, textarea.required, select.required', handleRequiredFieldFeedback);
});
// КОНЕЦ НОВОГО КОДА 