/**
 * Обработчик уведомлений для CRM-системы
 * Позволяет создавать и управлять уведомлениями различных типов
 */

class NotificationHandler {
  constructor() {
    this.container = null;
    this.notifications = [];
    this.counter = 0;
    this.init();
  }

  /**
   * Инициализация системы уведомлений
   */
  init() {
    // Создаем контейнер, если он еще не существует
    if (!this.container) {
      this.container = document.createElement('div');
      this.container.className = 'notification-container';
      document.body.appendChild(this.container);
    }

    // Добавляем стили, если они еще не добавлены
    if (!document.getElementById('notification-styles')) {
      const link = document.createElement('link');
      link.id = 'notification-styles';
      link.rel = 'stylesheet';
      link.href = 'js/notification-handler.css';
      document.head.appendChild(link);
    }
  }

  /**
   * Показать уведомление
   * @param {string} message - текст уведомления
   * @param {string} type - тип уведомления (success, error, warning, info)
   * @param {object} options - дополнительные параметры
   * @returns {number} ID уведомления
   */
  show(message, type = 'info', options = {}) {
    const id = ++this.counter;
    
    // Настройки по умолчанию
    const defaults = {
      title: this.getDefaultTitle(type),
      duration: 5000, // 5 секунд
      closable: true,
      onClose: null
    };

    // Объединяем настройки по умолчанию с переданными опциями
    const settings = { ...defaults, ...options };

    // Создаем элемент уведомления
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.dataset.id = id;

    // Создаем содержимое уведомления
    let content = '';
    
    if (settings.title) {
      content += `<h4 class="notification-title">${settings.title}</h4>`;
    }
    
    content += `<p class="notification-message">${message}</p>`;
    
    // Кнопка закрытия
    if (settings.closable) {
      content += `<button class="notification-close">&times;</button>`;
    }
    
    // Прогресс-бар для автоматического закрытия
    if (settings.duration > 0) {
      content += `
        <div class="notification-progress">
          <div class="notification-progress-bar"></div>
        </div>
      `;
    }
    
    notification.innerHTML = content;

    // Добавляем уведомление в контейнер
    this.container.appendChild(notification);

    // Анимируем появление уведомления
    setTimeout(() => {
      notification.classList.add('show');
      
      // Анимируем прогресс-бар
      if (settings.duration > 0) {
        const progressBar = notification.querySelector('.notification-progress-bar');
        progressBar.style.animation = `progress ${settings.duration / 1000}s linear`;
      }
    }, 10);

    // Добавляем обработчики событий
    if (settings.closable) {
      const closeButton = notification.querySelector('.notification-close');
      closeButton.addEventListener('click', (e) => {
        e.stopPropagation();
        this.close(id);
      });
    }

    // Закрытие по клику на уведомление
    notification.addEventListener('click', () => {
      this.close(id);
    });

    // Автоматическое закрытие
    let timeout = null;
    if (settings.duration > 0) {
      timeout = setTimeout(() => {
        this.close(id);
      }, settings.duration);
    }

    // Сохраняем данные уведомления
    this.notifications.push({
      id,
      element: notification,
      timeout,
      onClose: settings.onClose
    });

    return id;
  }

  /**
   * Закрыть уведомление по ID
   * @param {number} id - ID уведомления
   */
  close(id) {
    const index = this.notifications.findIndex(item => item.id === id);
    
    if (index !== -1) {
      const notification = this.notifications[index];
      
      // Останавливаем таймер автоматического закрытия
      if (notification.timeout) {
        clearTimeout(notification.timeout);
      }

      // Анимация закрытия
      notification.element.style.animation = 'fadeOut 0.3s forwards';
      
      // Удаляем элемент после завершения анимации
      setTimeout(() => {
        notification.element.remove();
        
        // Вызываем callback, если он указан
        if (typeof notification.onClose === 'function') {
          notification.onClose(id);
        }
        
        // Удаляем из массива
        this.notifications.splice(index, 1);
      }, 300);
    }
  }

  /**
   * Закрыть все уведомления
   */
  closeAll() {
    // Создаем копию массива, чтобы избежать проблем при удалении элементов
    const notifications = [...this.notifications];
    notifications.forEach(notification => {
      this.close(notification.id);
    });
  }

  /**
   * Показать уведомление об успехе
   * @param {string} message - текст уведомления
   * @param {object} options - дополнительные параметры
   * @returns {number} ID уведомления
   */
  success(message, options = {}) {
    return this.show(message, 'success', options);
  }

  /**
   * Показать уведомление об ошибке
   * @param {string} message - текст уведомления
   * @param {object} options - дополнительные параметры
   * @returns {number} ID уведомления
   */
  error(message, options = {}) {
    return this.show(message, 'error', options);
  }

  /**
   * Показать предупреждающее уведомление
   * @param {string} message - текст уведомления
   * @param {object} options - дополнительные параметры
   * @returns {number} ID уведомления
   */
  warning(message, options = {}) {
    return this.show(message, 'warning', options);
  }

  /**
   * Показать информационное уведомление
   * @param {string} message - текст уведомления
   * @param {object} options - дополнительные параметры
   * @returns {number} ID уведомления
   */
  info(message, options = {}) {
    return this.show(message, 'info', options);
  }

  /**
   * Получить заголовок по умолчанию в зависимости от типа уведомления
   * @param {string} type - тип уведомления
   * @returns {string} заголовок
   */
  getDefaultTitle(type) {
    switch (type) {
      case 'success':
        return 'Выполнено';
      case 'error':
        return 'Ошибка';
      case 'warning':
        return 'Предупреждение';
      case 'info':
        return 'Информация';
      default:
        return '';
    }
  }
}

// Создаем и экспортируем единственный экземпляр обработчика уведомлений
const notificationHandler = new NotificationHandler();

// Используем тип экспорта, совместимый со старыми браузерами
if (typeof module !== 'undefined' && module.exports) {
  module.exports = notificationHandler;
} else {
  window.notificationHandler = notificationHandler;
} 