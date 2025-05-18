/**
 * Файл управления сайдбаром - выделен из sidebar.php для лучшей организации кода
 */

console.log('[SIDEBAR.JS] Начало загрузки скрипта');

// Глобальный обработчик ошибок для отлова проблем
window.addEventListener('error', function(e) {
  try {
    console.error('[SIDEBAR.JS ERROR]', e.message, e.error ? e.error.stack : '');
  } catch (logError) {
    console.error('[SIDEBAR.JS LOGGING ERROR] Не удалось залогировать оригинальную ошибку:', logError);
    console.error('[SIDEBAR.JS ORIGINAL ERROR MSG]', String(e.message)); 
  }
});

// Основные переменные
var sidebarToggleBtn;
var sidebar;
var sidebarOverlay;
var submenuToggles;
var moduleLinks;
var favoriteIcons;
var STORAGE_MENU_STATE = 'sidebarMenuState';
var STORAGE_FAVORITES = 'favorites';

// Этот код выполнится прямо при загрузке файла
console.log('==== SIDEBAR.JS ОСНОВНЫЕ ПЕРЕМЕННЫЕ ОБЪЯВЛЕНЫ ====');
console.trace('Трассировка загрузки sidebar.js');

// Немедленная самовызывающаяся функция для инициализации
(function() {
  console.log('==== SIDEBAR.JS IIFE ЗАПУЩЕНА ====');
  
  try {
    // Даем более длинную временную задержку для полной загрузки DOM
    setTimeout(function() {
      console.log('==== SIDEBAR.JS ЗАПУСК INIT ЧЕРЕЗ 500ms ====');
      try {
        initSidebar();
      } catch (e) {
        console.error('[FATAL] Ошибка в отложенном init:', e);
      }
    }, 500);
  } catch (e) {
    console.error('[FATAL] Ошибка в IIFE sidebar.js:', e);
  }
})();

// Функция инициализации сайдбара
function initSidebar() {
  try {
    console.log('[SIDEBAR.JS] Запуск инициализации сайдбара');
    
    // Предотвращаем повторную инициализацию
    if (window.sidebarInitialized) {
      console.log('[SIDEBAR.JS] Сайдбар уже инициализирован, пропускаем');
      return;
    }
    
    // Находим необходимые элементы
    sidebarToggleBtn = document.getElementById('sidebar-toggle');
    sidebar = document.querySelector('.sidebar');
    sidebarOverlay = document.querySelector('.sidebar-overlay');
    submenuToggles = document.querySelectorAll('.sidebar .sidebar-toggle');
    
    console.log('[SIDEBAR.JS] Найдены элементы:', {
      'toggle': sidebarToggleBtn,
      'sidebar': sidebar,
      'overlay': sidebarOverlay,
      'submenuToggles': submenuToggles.length
    });
    
    // Проверяем наличие элементов
    if (!sidebar) {
      console.error('[SIDEBAR.JS] Элемент .sidebar не найден! Инициализация невозможна');
      return;
    }
    
    if (!sidebarOverlay) {
      console.error('[SIDEBAR.JS] Элемент .sidebar-overlay не найден! Инициализация невозможна');
      return;
    }
    
    // Добавляем обработчик на кнопку сайдбара
    if (sidebarToggleBtn) {
      sidebarToggleBtn.addEventListener('click', function(e) {
        console.log('[SIDEBAR.JS] Клик по кнопке сайдбара в sidebar.js');
        e.preventDefault();
        e.stopPropagation();
        toggleSidebar();
      });
      console.log('[SIDEBAR.JS] Добавлен обработчик на кнопку сайдбара');
    } else {
      console.error('[SIDEBAR.JS] Кнопка #sidebar-toggle не найдена!');
    }
    
    // Добавляем обработчик на оверлей для закрытия сайдбара
    if (sidebarOverlay) {
      sidebarOverlay.addEventListener('click', function() {
        console.log('[SIDEBAR.JS] Клик по оверлею, закрываем сайдбар');
        if (sidebar.classList.contains('open')) {
          closeSidebar();
        }
      });
      console.log('[SIDEBAR.JS] Добавлен обработчик на оверлей');
    }
    
    // Добавляем обработчики для кнопок категорий (открытие/закрытие подменю)
    if (submenuToggles && submenuToggles.length > 0) {
      submenuToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          
          console.log('[SIDEBAR.JS] Клик по категории:', toggle.textContent.trim());
          
          // Получаем ID подменю из атрибута data-submenu
          const submenuId = toggle.getAttribute('data-submenu');
          if (!submenuId) {
            console.error('[SIDEBAR.JS] Не указан атрибут data-submenu у категории');
            return;
          }
          
          // Находим подменю по ID или рядом с текущим элементом
          const submenu = document.getElementById(submenuId + 'Submenu') || 
                          toggle.closest('.nav-item').querySelector('.submenu');
          
          if (submenu) {
            // Переключаем класс open для подменю
            const isOpen = submenu.classList.toggle('open');
            console.log('[SIDEBAR.JS] ' + (isOpen ? 'Открываем' : 'Закрываем') + ' подменю:', submenuId);
            
            // Также добавляем/удаляем aria-expanded для доступности
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            
            // Переключаем иконку стрелки (если используем иконку)
            const arrow = toggle.querySelector('.fa-chevron-down');
            if (arrow) {
              arrow.style.transform = isOpen ? 'rotate(180deg)' : '';
            }
          } else {
            console.error('[SIDEBAR.JS] Подменю не найдено для категории:', submenuId);
          }
        });
      });
      console.log('[SIDEBAR.JS] Добавлены обработчики на', submenuToggles.length, 'категорий');
    } else {
      console.warn('[SIDEBAR.JS] Не найдены элементы категорий (.sidebar-toggle)');
    }
    
    // Экспортируем функции для внешнего использования
    window.toggleSidebar = toggleSidebar;
    window.openSidebar = openSidebar;
    window.closeSidebar = closeSidebar;
    
    // Отмечаем, что инициализация прошла успешно
    window.sidebarInitialized = true;
    console.log('[SIDEBAR.JS] Инициализация завершена успешно');
    
  } catch (err) {
    console.error('[SIDEBAR.JS] Ошибка при инициализации:', err);
  }
}

// Функция переключения сайдбара
function toggleSidebar() {
  try {
    console.log('[SIDEBAR.JS] Вызвана функция toggleSidebar()');
    if (!sidebar || !sidebarOverlay) {
      console.error('[SIDEBAR.JS] Элементы сайдбара не найдены, невозможно переключить');
      return;
    }
    
    if (sidebar.classList.contains('open')) {
      closeSidebar();
    } else {
      openSidebar();
    }
  } catch (err) {
    console.error('[SIDEBAR.JS] Ошибка при переключении сайдбара:', err);
  }
}

// Функция открытия сайдбара
function openSidebar() {
  try {
    console.log('[SIDEBAR.JS] Открываем сайдбар');
    sidebar.classList.add('open');
    sidebarOverlay.classList.add('active');
    document.body.classList.add('sidebar-open');
  } catch (err) {
    console.error('[SIDEBAR.JS] Ошибка при открытии сайдбара:', err);
  }
}

// Функция закрытия сайдбара
function closeSidebar() {
  try {
    console.log('[SIDEBAR.JS] Закрываем сайдбар');
    sidebar.classList.remove('open');
    sidebarOverlay.classList.remove('active');
    document.body.classList.remove('sidebar-open');
  } catch (err) {
    console.error('[SIDEBAR.JS] Ошибка при закрытии сайдбара:', err);
  }
}

// Инициализируем сайдбар после загрузки DOM
if (document.readyState === 'loading') {
  console.log('[SIDEBAR.JS] DOM еще загружается, добавляем обработчик DOMContentLoaded');
  document.addEventListener('DOMContentLoaded', initSidebar);
} else {
  console.log('[SIDEBAR.JS] DOM уже загружен, запускаем инициализацию');
  initSidebar();
}

// Дополнительная инициализация при полной загрузке страницы
window.addEventListener('load', function() {
  console.log('[SIDEBAR.JS] Событие window.load, проверяем инициализацию сайдбара');
  if (!window.sidebarInitialized) {
    console.log('[SIDEBAR.JS] Сайдбар не был инициализирован, повторяем инициализацию');
    initSidebar();
  }
});

console.log('[SIDEBAR.JS] Загрузка скрипта завершена'); 