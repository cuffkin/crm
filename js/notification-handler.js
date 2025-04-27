/**
 * Простая реализация системы уведомлений как альтернатива Bootstrap Toast
 */
class NotificationHandler {
  constructor() {
    this.containerId = 'toast-container';
    this.init();
  }

  /**
   * Инициализация контейнера для уведомлений
   */
  init() {
    let container = document.getElementById(this.containerId);
    if (!container) {
      container = document.createElement('div');
      container.id = this.containerId;
      container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
      container.style.zIndex = '10000'; // Убедимся, что z-index больше, чем у модальных окон
      container.style.pointerEvents = 'none'; // Чтобы не блокировать взаимодействие с модальными окнами
      document.body.appendChild(container);
      console.log('Создан контейнер для уведомлений с z-index 10000');
    } else {
      // Обновляем стили существующего контейнера
      container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
      container.style.zIndex = '10000';
      container.style.pointerEvents = 'none';
    }
  }

  /**
   * Показать уведомление
   * @param {string} message - Текст сообщения
   * @param {string} type - Тип уведомления: success, warning, danger, primary
   * @param {number} autoHide - Время в мс, через которое скрыть уведомление
   * @returns {HTMLElement} - Элемент уведомления
   */
  show(message, type = 'primary', autoHide = 5000) {
    const id = 'toast-' + Date.now();
    
    // Создаем элемент уведомления
    const toast = document.createElement('div');
    toast.id = id;
    toast.className = `toast bg-${type} show`;
    toast.style.pointerEvents = 'auto'; // Разрешаем взаимодействие с самим уведомлением
    toast.style.opacity = '1'; // Принудительно устанавливаем прозрачность
    toast.style.zIndex = '10001'; // Убедимся, что z-index больше, чем у контейнера
    
    // Заголовок уведомления
    const header = document.createElement('div');
    header.className = `toast-header bg-${type}`;
    
    // Иконка
    const icon = document.createElement('span');
    icon.className = 'me-2';
    switch(type) {
      case 'success':
        icon.innerHTML = '✓';
        break;
      case 'warning':
        icon.innerHTML = '⚠';
        break;
      case 'danger':
        icon.innerHTML = '✗';
        break;
      default:
        icon.innerHTML = 'ℹ';
    }
    
    // Заголовок
    const title = document.createElement('strong');
    title.className = 'me-auto';
    title.textContent = this.getTitle(type);
    
    // Кнопка закрытия
    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'btn-close btn-close-white';
    closeBtn.setAttribute('aria-label', 'Close');
    closeBtn.addEventListener('click', () => this.hide(id));
    
    header.appendChild(icon);
    header.appendChild(title);
    header.appendChild(closeBtn);
    
    // Тело уведомления
    const body = document.createElement('div');
    body.className = 'toast-body';
    body.textContent = message;
    
    toast.appendChild(header);
    toast.appendChild(body);
    
    // Добавляем в контейнер
    const container = document.getElementById(this.containerId);
    container.appendChild(toast);
    
    // Автоматическое скрытие
    if (autoHide) {
      setTimeout(() => this.hide(id), autoHide);
    }
    
    return toast;
  }
  
  /**
   * Скрыть уведомление
   * @param {string} id - ID уведомления
   */
  hide(id) {
    const toast = document.getElementById(id);
    if (toast) {
      toast.classList.remove('show');
      toast.classList.add('hide');
      
      // Удаляем элемент из DOM после завершения анимации
      setTimeout(() => {
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast);
        }
      }, 300);
    }
  }
  
  /**
   * Получить заголовок уведомления по типу
   * @param {string} type - Тип уведомления
   * @returns {string} - Заголовок
   */
  getTitle(type) {
    switch(type) {
      case 'success':
        return 'Успешно';
      case 'warning':
        return 'Внимание';
      case 'danger':
        return 'Ошибка';
      default:
        return 'Информация';
    }
  }
}

// Экспортируем обработчик уведомлений
window.NotificationHandler = NotificationHandler;

// Универсальная функция уведомлений для всего проекта
window.appShowNotification = function(message, type = 'info', duration = 5000) {
  // Логируем в консоль
  console.log('Notification:', message, type);

  try {
    // Преобразуем тип info в primary для соответствия нашей системе
    if (type === 'info') {
      type = 'primary';
    }
    
    // УБИРАЕМ ПРИНУДИТЕЛЬНОЕ СОЗДАНИЕ ДУБЛИРУЮЩЕГО УВЕДОМЛЕНИЯ
    // Оставляем только в случае критической ошибки
    // (не создаем дополнительное уведомление для сообщений о несохраненных изменениях)
    if ((type === 'danger') && 
        !message.includes('Обнаружены несохраненные изменения') &&
        !message.includes('несохраненные изменения')) {
      createSimpleToast(message, type, duration);
    }

    // Проверяем, доступен ли наш NotificationHandler
    if (window.NotificationHandler) {
      // Если нет экземпляра, создаем его
      if (!window._notificationHandler) {
        window._notificationHandler = new NotificationHandler();
      }
      // Показываем уведомление через наш обработчик
      window._notificationHandler.show(message, type, duration);
      
      // Дополнительно выводим в консоль для отладки
      console.log(`%c${message}`, `background: ${type === 'primary' ? '#007bff' : type === 'success' ? '#28a745' : type === 'warning' ? '#ffc107' : '#dc3545'}; color: white; padding: 2px 5px; border-radius: 3px;`);
      
      return;
    }

    // Проверяем, загружен ли Bootstrap как запасной вариант
    if (typeof bootstrap !== 'undefined') {
      // Проверяем, есть ли уже контейнер для уведомлений
      let toastContainer = document.getElementById('toast-container');
      if (!toastContainer) {
        // Создаем контейнер, если его нет
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        toastContainer.style.zIndex = '10000'; // Высокий z-index для отображения поверх модальных окон
        toastContainer.style.pointerEvents = 'none'; // Чтобы не блокировать взаимодействие с элементами под ним
        document.body.appendChild(toastContainer);
        console.log('Создан контейнер для уведомлений:', toastContainer);
      }
      
      // Обновляем стили контейнера, чтобы быть уверенными, что они правильные
      toastContainer.style.zIndex = '10000';
      toastContainer.style.position = 'fixed';
      toastContainer.style.bottom = '0';
      toastContainer.style.right = '0';
      toastContainer.style.padding = '1rem';
      
      // Создаем класс для типа уведомления
      let bgClass = 'bg-primary';
      let iconClass = 'info-circle';
      switch(type) {
        case 'success':
          bgClass = 'bg-success';
          iconClass = 'check-circle';
          break;
        case 'warning':
          bgClass = 'bg-warning';
          iconClass = 'exclamation-triangle';
          break;
        case 'error':
        case 'danger':
          bgClass = 'bg-danger';
          iconClass = 'exclamation-circle';
          break;
        case 'primary':
        case 'info':
        default:
          bgClass = 'bg-primary';
          iconClass = 'info-circle';
          break;
      }
      // Создаем ID для этого уведомления
      const toastId = 'toast-' + Date.now();
      // Создаем элемент для уведомления (не используем innerHTML)
      const toastElement = document.createElement('div');
      toastElement.id = toastId;
      toastElement.className = `toast ${bgClass} text-white`;
      toastElement.setAttribute('role', 'alert');
      toastElement.setAttribute('aria-live', 'assertive');
      toastElement.setAttribute('aria-atomic', 'true');
      
      // Добавляем стили для гарантированного отображения
      toastElement.style.pointerEvents = 'auto';
      toastElement.style.opacity = '1';
      toastElement.style.zIndex = '10001';
      toastElement.style.minWidth = '250px';
      toastElement.style.boxShadow = '0 0.5rem 1rem rgba(0, 0, 0, 0.15)';
      
      // Создаем заголовок уведомления
      const toastHeader = document.createElement('div');
      toastHeader.className = `toast-header ${bgClass} text-white`;
      const icon = document.createElement('i');
      icon.className = `fas fa-${iconClass} me-2`;
      const strong = document.createElement('strong');
      strong.className = 'me-auto';
      strong.textContent = type.charAt(0).toUpperCase() + type.slice(1);
      const closeButton = document.createElement('button');
      closeButton.type = 'button';
      closeButton.className = 'btn-close btn-close-white';
      closeButton.setAttribute('data-bs-dismiss', 'toast');
      closeButton.setAttribute('aria-label', 'Закрыть');
      toastHeader.appendChild(icon);
      toastHeader.appendChild(strong);
      toastHeader.appendChild(closeButton);
      // Создаем тело уведомления
      const toastBody = document.createElement('div');
      toastBody.className = 'toast-body';
      toastBody.textContent = message;
      // Собираем уведомление
      toastElement.appendChild(toastHeader);
      toastElement.appendChild(toastBody);
      // Добавляем уведомление в контейнер
      toastContainer.appendChild(toastElement);
      // Показываем уведомление через Bootstrap API
      try {
        const toast = new bootstrap.Toast(toastElement, {
          autohide: true,
          delay: duration
        });
        
        console.log('Созданный объект toast:', toast);
        toast.show();
        
        // Удаляем элемент после скрытия
        toastElement.addEventListener('hidden.bs.toast', function() {
          if (toastElement.parentNode) {
            toastElement.parentNode.removeChild(toastElement);
          }
        });
        
        // Если toast не показался автоматически, принудительно добавим класс show
        setTimeout(() => {
          // Проверяем, не скрыто ли уведомление и существует ли оно еще
          if (toastElement && toastElement.parentNode && !toastElement.classList.contains('show')) {
            console.log('Принудительное добавление класса show для:', toastId);
            toastElement.classList.add('show');
            
            // Устанавливаем таймер для автоматического скрытия
            setTimeout(() => {
              if (toastElement && toastElement.parentNode) {
                toastElement.classList.remove('show');
                setTimeout(() => {
                  if (toastElement && toastElement.parentNode) {
                    toastElement.parentNode.removeChild(toastElement);
                  }
                }, 300);
              }
            }, duration);
          }
        }, 100);
        
      } catch (e) {
        console.error('Ошибка при инициализации Toast:', e);
        // Альтернативный способ показа, если Bootstrap Toast не работает
        toastElement.classList.add('show');
        
        // Анимация появления
        toastElement.style.opacity = '0';
        toastElement.style.transform = 'translateY(20px)';
        toastElement.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        
        // Запускаем анимацию
        setTimeout(() => {
          toastElement.style.opacity = '1';
          toastElement.style.transform = 'translateY(0)';
        }, 10);
        
        // Устанавливаем таймер скрытия
        setTimeout(() => {
          // Анимация исчезновения
          toastElement.style.opacity = '0';
          toastElement.style.transform = 'translateY(20px)';
          
          // Удаляем после анимации
          setTimeout(() => {
            if (toastElement.parentNode) {
              toastElement.parentNode.removeChild(toastElement);
            }
          }, 300);
        }, duration);
      }
    } else {
      // Если ни Bootstrap, ни NotificationHandler не доступны, показываем простое уведомление
      const simpleToast = document.createElement('div');
      simpleToast.className = 'simple-toast';
      simpleToast.textContent = message;
      document.body.appendChild(simpleToast);
      // Показываем и скрываем через время
      setTimeout(() => simpleToast.classList.add('show'), 10);
      setTimeout(() => {
        simpleToast.classList.remove('show');
        setTimeout(() => {
          if (simpleToast.parentNode) {
            simpleToast.parentNode.removeChild(simpleToast);
          }
        }, 300);
      }, duration);
    }
  } catch (e) {
    console.error('Общая ошибка при показе уведомления:', e);
    // В случае ошибки, пытаемся показать простое DOM-уведомление
    createSimpleToast(message, type, duration);
  }
};

// Вспомогательная функция для создания простого уведомления без зависимостей от других библиотек
function createSimpleToast(message, type, duration) {
  try {
    // Создаем простое уведомление с более приятными стилями
    const toast = document.createElement('div');
    toast.style.position = 'fixed';
    toast.style.bottom = '20px';
    toast.style.right = '20px';
    toast.style.minWidth = '300px';
    toast.style.maxWidth = '400px';
    // Более мягкие цвета для уведомлений
    toast.style.backgroundColor = type === 'success' ? 'rgba(40, 167, 69, 0.9)' : 
                                  type === 'warning' ? 'rgba(255, 193, 7, 0.9)' : 
                                  type === 'danger' ? 'rgba(220, 53, 69, 0.9)' : 'rgba(0, 123, 255, 0.9)';
    toast.style.color = type === 'warning' ? '#333' : '#fff';
    toast.style.padding = '1rem';
    toast.style.borderRadius = '6px';
    toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
    toast.style.zIndex = '100000'; // Очень высокий z-index
    toast.style.fontSize = '14px';
    toast.style.lineHeight = '1.5';
    toast.style.opacity = '0';
    toast.style.transition = 'opacity 0.3s ease-in-out, transform 0.3s ease-in-out';
    toast.style.transform = 'translateY(20px)';
    toast.textContent = message;
    
    // Добавляем в DOM
    document.body.appendChild(toast);
    
    // Показываем уведомление с анимацией
    setTimeout(() => {
      toast.style.opacity = '1';
      toast.style.transform = 'translateY(0)';
    }, 50);
    
    // Скрываем и удаляем через указанное время
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(20px)';
      setTimeout(() => {
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast);
        }
      }, 300);
    }, duration);
  } catch (e) {
    console.error('Критическая ошибка при создании простого уведомления:', e);
  }
}

// Для обратной совместимости с существующим кодом
function showNotification(message, type = 'info', duration = 5000) {
  // Используем глобальную функцию
  window.appShowNotification(message, type, duration);
}

// Делаем глобально доступным
window.showNotification = showNotification; 