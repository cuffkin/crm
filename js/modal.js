// Универсальный обработчик модальных окон для CRM

// Глобальная функция для создания и открытия модального окна
function openModal(modalHTML, onOpenCallback = null) {
    try {
        // Генерируем уникальный ID для модального окна
        const modalId = `dynamic-modal-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
        
        // Добавляем ID к HTML модального окна
        const processedHTML = modalHTML.replace(/class="modal/, `id="${modalId}" class="modal`);
        
        // Добавляем модальное окно в DOM
        document.body.insertAdjacentHTML('beforeend', processedHTML);
        
        // Находим созданный элемент
        const modalElement = document.getElementById(modalId);
        if (!modalElement) {
            console.error('Не удалось найти созданное модальное окно');
            return false;
        }
        
        // Инициализируем модальное окно через modalManager
        const modalInterface = modalManager.init(modalId);
        if (!modalInterface) {
            console.error('Не удалось инициализировать модальное окно');
            modalElement.remove();
            return false;
        }
        
        // Устанавливаем обработчик на закрытие для очистки
        modalInterface.on('hidden', function() {
            console.log(`Модальное окно ${modalId} закрыто, выполняю отложенную очистку`);
            setTimeout(() => {
                modalInterface.dispose();
                if (modalElement && modalElement.parentNode) {
                    modalElement.remove();
                }
            }, 100);
        });
        
        // Показываем модальное окно
        const showResult = modalInterface.show();
        if (!showResult) {
            console.error('Не удалось показать модальное окно');
            modalInterface.dispose();
            modalElement.remove();
            return false;
        }
        
        // Вызываем коллбэк с элементом модального окна
        if (onOpenCallback && typeof onOpenCallback === 'function') {
            try {
                onOpenCallback(modalElement);
            } catch (callbackError) {
                console.error('Ошибка при выполнении коллбэка onOpenCallback:', callbackError);
            }
        }
        
        return true;
    } catch (error) {
        console.error('Ошибка при создании модального окна:', error);
        return false;
    }
}

// Экспортируем функцию в глобальную область видимости
window.openModal = openModal;

const modalManager = {
  // Хранилище для экземпляров модальных окон
  instances: {},
  
  // Инициализация модального окна
  init: function(modalId) {
    try {
      // Проверяем существование элемента
      const modalElement = document.getElementById(modalId);
      
      if (!modalElement) {
        console.error(`Модальное окно с ID ${modalId} не найдено`);
        return null;
      }
      
      // Дополнительная проверка, находится ли элемент в DOM
      if (!document.body.contains(modalElement)) {
        console.error(`Модальное окно с ID ${modalId} не добавлено в DOM`);
        return null;
      }
      
      // Проверяем, что Bootstrap определен перед использованием
      if (typeof bootstrap === 'undefined' || typeof bootstrap.Modal === 'undefined') {
        console.error('Bootstrap Modal не определен, невозможно инициализировать модальное окно');
        return null;
      }
      
      // Создаем экземпляр Bootstrap Modal с параметрами
      let modalInstance;
      try {
        modalInstance = new bootstrap.Modal(modalElement, {
          backdrop: true, 
          keyboard: true,
          focus: true
        });
      } catch (initError) {
        console.error(`Не удалось создать экземпляр Bootstrap Modal для ${modalId}:`, initError);
        return null;
      }
      
      // Сохраняем экземпляр в хранилище
      this.instances[modalId] = {
        element: modalElement,
        instance: modalInstance,
        callbacks: {}
      };
      
      // Возвращаем интерфейс для работы с этим модальным окном
      return this.createModalInterface(modalId);
    } catch (e) {
      console.error(`Ошибка при инициализации модального окна ${modalId}:`, e);
      return null;
    }
  },
  
  // Создание интерфейса для работы с модальным окном
  createModalInterface: function(modalId) {
    const self = this;
    
    return {
      // Показ модального окна
      show: function() {
        try {
          if (self.instances[modalId] && self.instances[modalId].instance) {
            self.instances[modalId].instance.show();
            return true;
          }
          return false;
        } catch (e) {
          console.error(`Ошибка при показе модального окна ${modalId}:`, e);
          return false;
        }
      },
      
      // Скрытие модального окна
      hide: function() {
        try {
          if (self.instances[modalId] && self.instances[modalId].instance) {
            self.instances[modalId].instance.hide();
            return true;
          }
          return false;
        } catch (e) {
          console.error(`Ошибка при скрытии модального окна ${modalId}:`, e);
          // Принудительно очищаем модальное окно в случае ошибки
          cleanupModals();
          return false;
        }
      },
      
      // Установка обработчика события
      on: function(event, callback) {
        try {
          if (!self.instances[modalId]) return false;
          
          const modalElement = self.instances[modalId].element;
          if (!modalElement) return false;
          
          // Сохраняем обработчик в хранилище 
          if (!self.instances[modalId].callbacks[event]) {
            self.instances[modalId].callbacks[event] = [];
          }
          self.instances[modalId].callbacks[event].push(callback);
          
          // Безопасно добавляем обработчик события
          modalElement.addEventListener('bs.' + event, callback);
          return true;
        } catch (e) {
          console.error(`Ошибка при установке обработчика ${event} для ${modalId}:`, e);
          return false;
        }
      },
      
      // Удаление обработчика события
      off: function(event) {
        try {
          if (!self.instances[modalId]) return false;
          
          const modalElement = self.instances[modalId].element;
          if (!modalElement) return false;
          
          // Удаляем все обработчики этого события
          if (self.instances[modalId].callbacks[event]) {
            self.instances[modalId].callbacks[event].forEach(callback => {
              modalElement.removeEventListener('bs.' + event, callback);
            });
            delete self.instances[modalId].callbacks[event];
          }
          return true;
        } catch (e) {
          console.error(`Ошибка при удалении обработчиков ${event} для ${modalId}:`, e);
          return false;
        }
      },
      
      // Уничтожение модального окна и очистка ресурсов
      dispose: function() {
        try {
          if (!self.instances[modalId]) return false;
          
          // Удаляем все обработчики событий
          const modalElement = self.instances[modalId].element;
          if (modalElement) {
            Object.keys(self.instances[modalId].callbacks || {}).forEach(event => {
              self.instances[modalId].callbacks[event].forEach(callback => {
                try {
                  modalElement.removeEventListener('bs.' + event, callback);
                } catch (err) {
                  console.warn(`Ошибка при удалении обработчика ${event}:`, err);
                }
              });
            });
          }
          
          // Уничтожаем экземпляр Bootstrap Modal
          if (self.instances[modalId] && self.instances[modalId].instance) {
            try {
              self.instances[modalId].instance.dispose();
            } catch (err) {
              console.warn(`Ошибка при вызове dispose для модального окна ${modalId}:`, err);
            }
          }
          
          // Удаляем запись из хранилища
          delete self.instances[modalId];
          return true;
        } catch (e) {
          console.error(`Ошибка при уничтожении модального окна ${modalId}:`, e);
          return false;
        }
      },
      
      // Обновление содержимого модального окна
      setContent: function(content) {
        try {
          if (!self.instances[modalId]) return false;
          
          const modalElement = self.instances[modalId].element;
          if (!modalElement) return false;
          
          // Находим тело модального окна и обновляем его содержимое
          const modalBody = modalElement.querySelector('.modal-body');
          if (modalBody) {
            modalBody.innerHTML = content;
            return true;
          }
          return false;
        } catch (e) {
          console.error(`Ошибка при обновлении содержимого модального окна ${modalId}:`, e);
          return false;
        }
      },
      
      // Обновление заголовка модального окна
      setTitle: function(title) {
        try {
          if (!self.instances[modalId]) return false;
          
          const modalElement = self.instances[modalId].element;
          if (!modalElement) return false;
          
          // Находим заголовок модального окна и обновляем его
          const modalTitle = modalElement.querySelector('.modal-title');
          if (modalTitle) {
            modalTitle.textContent = title;
            return true;
          }
          return false;
        } catch (e) {
          console.error(`Ошибка при обновлении заголовка модального окна ${modalId}:`, e);
          return false;
        }
      }
    };
  },
  
  // Получение интерфейса для существующего модального окна
  get: function(modalId) {
    // Если экземпляр уже есть - возвращаем интерфейс для него
    if (this.instances[modalId]) {
      return this.createModalInterface(modalId);
    }
    
    // Иначе пробуем инициализировать
    return this.init(modalId);
  },
  
  // Очистка всех модальных окон
  cleanupAll: function() {
    console.log('Выполняется полная очистка всех модальных окон...');
    
    try {
      // Сохраняем список модальных окон, отмеченных как persistent
      const persistentModals = {};
      
      // Проходим по всем сохраненным экземплярам и уничтожаем их
      const modalIds = Object.keys(this.instances || {});
      modalIds.forEach(modalId => {
        try {
          const instance = this.instances[modalId];
          if (instance) {
            // Проверяем, является ли модальное окно persistent (не должно автоматически удаляться)
            const modalElement = instance.element;
            if (modalElement && modalElement.getAttribute('data-persistent') === 'true') {
              console.log(`Модальное окно ${modalId} отмечено как persistent, пропускаем очистку`);
              persistentModals[modalId] = instance;
              return; // Пропускаем это модальное окно
            }
            
            // Проверяем наличие элемента в DOM перед уничтожением
            if (modalElement && !document.body.contains(modalElement)) {
              console.warn(`Модальное окно ${modalId} не найдено в DOM, просто удаляем запись`);
              delete this.instances[modalId];
              return;
            }
            
            // Уничтожаем экземпляр, если он существует
            if (instance.instance && typeof instance.instance.dispose === 'function') {
              instance.instance.dispose();
            }
          }
        } catch (e) {
          console.warn(`Ошибка при уничтожении модального окна ${modalId}:`, e);
        } finally {
          // В любом случае удаляем запись из хранилища, если это не persistent-модальное окно
          if (!persistentModals[modalId]) {
            delete this.instances[modalId];
          }
        }
      });
      
      // Очищаем хранилище, но сохраняем persistent-модальные окна
      this.instances = persistentModals;
      
      // Бережно вызываем стандартную функцию очистки из app.js, если она доступна
      if (typeof cleanupModals === 'function') {
        setTimeout(function() {
          try {
            cleanupModals();
          } catch (e) {
            console.warn('Ошибка при вызове функции cleanupModals:', e);
          }
        }, 100); // Увеличиваем задержку
        return; // Выходим и не выполняем оставшийся код
      }
    } catch (e) {
      console.warn('Ошибка в процессе cleanupAll:', e);
    }
    
    // Резервный вариант очистки модальных окон
    try {
      // Очищаем стили body
      if (document && document.body) {
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
      }
      
      // Удаляем все backdrop элементы
      try {
        const backdrops = document.querySelectorAll('.modal-backdrop');
        if (backdrops && backdrops.length) {
          backdrops.forEach(backdrop => {
            if (backdrop && backdrop.parentNode) {
              backdrop.parentNode.removeChild(backdrop);
            }
          });
        }
      } catch (e) {
        console.warn('Ошибка при удалении backdrop элементов:', e);
      }
      
      // Находим все модальные окна и сбрасываем их состояние
      try {
        const modals = document.querySelectorAll('.modal');
        if (modals && modals.length) {
          modals.forEach(modal => {
            if (!modal) return;
            
            // Безопасная проверка перед использованием classList
            try {
              if (modal.classList) {
                modal.classList.remove('show');
                
                if (modal.style) {
                  modal.style.display = 'none';
                  modal.style.paddingRight = '';
                }
              }
            } catch (e) {
              console.warn('Ошибка при сбросе состояния модального окна:', e);
            }
          });
        }
      } catch (e) {
        console.warn('Ошибка при поиске и сбросе модальных окон:', e);
      }
    } catch (e) {
      console.error('Критическая ошибка при очистке модальных окон:', e);
    }
    
    console.log('Очистка модальных окон завершена');
  }
};

// Функция для обработки закрытия модальных окон по кнопке закрытия
document.addEventListener('click', function(e) {
  // Проверяем, что это клик по кнопке закрытия в модальном окне и что элемент существует
  if (!e || !e.target) return;
  
  // Проверка на существование методов перед их вызовом
  const isDismissButton = e.target.getAttribute && e.target.getAttribute('data-bs-dismiss') === 'modal';
  const isClosestDismiss = e.target.closest && e.target.closest('[data-bs-dismiss="modal"]');
  
  if (isDismissButton || isClosestDismiss) {
    // Находим родительское модальное окно
    const modalElement = e.target.closest ? e.target.closest('.modal') : null;
    
    // Безопасно проверяем наличие элемента перед использованием его свойств
    if (modalElement && modalElement.id) {
      // Получаем ID модального окна
      const modalId = modalElement.id;
      
      // Пытаемся скрыть модальное окно через наш менеджер
      if (modalManager.instances[modalId]) {
        const modal = modalManager.get(modalId);
        if (modal) {
          setTimeout(function() {
            try {
              modal.hide();
            } catch (err) {
              console.warn('Ошибка при закрытии модального окна:', err);
              modalManager.cleanupAll();
            }
          }, 10);
        }
      } else {
        // Если модальное окно не зарегистрировано в нашем менеджере,
        // пытаемся скрыть его через Bootstrap API
        try {
          if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
            setTimeout(function() {
              try {
                const bootstrapModal = bootstrap.Modal.getInstance(modalElement);
                if (bootstrapModal) {
                  bootstrapModal.hide();
                }
              } catch (err) {
                console.warn('Ошибка при закрытии модального окна через Bootstrap API:', err);
                modalManager.cleanupAll();
              }
            }, 10);
          }
        } catch (e) {
          console.warn('Ошибка при доступе к Bootstrap API:', e);
          // В случае ошибки - принудительно очищаем модальные окна
          setTimeout(function() {
            modalManager.cleanupAll();
          }, 10);
        }
      }
    } else {
      // Если не удалось найти модальное окно, попробуем очистить все модальные окна
      console.warn('Не удалось найти родительское модальное окно для кнопки закрытия');
      setTimeout(function() {
        modalManager.cleanupAll();
      }, 10);
    }
  }
});

// Функция для создания и показа модального окна с подтверждением
function showConfirmationModal(title, message, confirmCallback, cancelCallback, confirmButtonText = 'Подтвердить', cancelButtonText = 'Отмена') {
  try {
    console.log(`Открываю модальное окно подтверждения: "${title}"`);
    
    // Предварительно очищаем все существующие модальные окна перед открытием нового
    modalManager.cleanupAll();
    
    // ID для модального окна
    const modalId = 'confirmationModal';
    
    // Проверяем, существует ли уже такое модальное окно и удаляем его
    let existingModal = document.getElementById(modalId);
    if (existingModal && existingModal.parentNode) {
      existingModal.parentNode.removeChild(existingModal);
    }
    
    // Создаем новый элемент модального окна
    const modalElement = document.createElement('div');
    modalElement.id = modalId;
    modalElement.className = 'modal fade';
    modalElement.setAttribute('tabindex', '-1');
    modalElement.setAttribute('aria-labelledby', 'confirmationModalLabel');
    modalElement.setAttribute('aria-hidden', 'true');
    
    // Создаем структуру модального окна
    modalElement.innerHTML = `
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="confirmationModalLabel"></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
          </div>
          <div class="modal-body">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="confirmationModalCancel"></button>
            <button type="button" class="btn btn-primary" id="confirmationModalConfirm"></button>
          </div>
        </div>
      </div>
    `;
    
    // Добавляем модальное окно в body
    document.body.appendChild(modalElement);
    
    // Устанавливаем заголовок и сообщение
    const modalTitle = modalElement.querySelector('.modal-title');
    const modalBody = modalElement.querySelector('.modal-body');
    const confirmButton = modalElement.querySelector('#confirmationModalConfirm');
    const cancelButton = modalElement.querySelector('#confirmationModalCancel');
    
    // Безопасно проверяем наличие элементов перед использованием их свойств
    if (modalTitle) modalTitle.textContent = title || 'Подтверждение';
    if (modalBody) modalBody.innerHTML = message || 'Вы уверены?';
    if (confirmButton) confirmButton.textContent = confirmButtonText;
    if (cancelButton) cancelButton.textContent = cancelButtonText;
    
    // Инициализируем модальное окно через наш менеджер
    const modal = modalManager.init(modalId);
    
    if (!modal) {
      console.error('Не удалось инициализировать модальное окно подтверждения');
      
      // В случае ошибки, безопасно вызываем колбэк подтверждения
      if (typeof confirmCallback === 'function') {
        confirmCallback();
      }
      return false;
    }
    
    // Флаг для отслеживания, была ли нажата кнопка подтверждения
    let confirmed = false;
    
    // Флаг для отслеживания, был ли уже вызван колбэк
    let callbackExecuted = false;
    
    // Функция для безопасного выполнения колбэка
    const safeExecuteCallback = function(callback) {
      if (callbackExecuted) return;
      callbackExecuted = true;
      
      try {
        if (typeof callback === 'function') {
          callback();
        }
      } catch (e) {
        console.error('Ошибка при выполнении колбэка:', e);
      }
    };
    
    // Устанавливаем обработчик для кнопки подтверждения
    if (confirmButton) {
      confirmButton.addEventListener('click', function() {
        console.log('Нажата кнопка подтверждения');
        confirmed = true;
        safeExecuteCallback(confirmCallback);
        
        // Закрываем модальное окно
        setTimeout(function() {
          modal.hide();
        }, 100);
      });
    }
    
    // Устанавливаем обработчик для кнопки отмены
    if (cancelButton) {
      cancelButton.addEventListener('click', function() {
        console.log('Нажата кнопка отмены');
        confirmed = false;
        // Закрытие обрабатывается через стандартный механизм Bootstrap (data-bs-dismiss)
      });
    }
    
    // Обработчик события закрытия модального окна
    modal.on('hidden', function() {
      console.log('Модальное окно закрыто событием hidden');
      
      // Если колбэк еще не был вызван
      if (!callbackExecuted) {
        if (confirmed) {
          safeExecuteCallback(confirmCallback);
        } else {
          safeExecuteCallback(cancelCallback);
        }
      }
      
      // Удаляем обработчики событий
      modal.off('hidden');
      
      // Уничтожаем экземпляр модального окна
      setTimeout(function() {
        try {
          // Проверяем, существует ли ещё элемент
          if (modal && typeof modal.dispose === 'function') {
            modal.dispose();
          }
        } catch (err) {
          console.warn('Ошибка при уничтожении экземпляра модального окна:', err);
        }
      }, 300);
    });
    
    // Показываем модальное окно
    modal.show();
    
    // Устанавливаем таймер автоматического закрытия (на случай зависания)
    setTimeout(function() {
      try {
        // Проверяем, существует ли ещё экземпляр модального окна в менеджере
        if (modalManager && modalManager.instances && modalManager.instances[modalId]) {
          // Проверяем, не является ли модальное окно постоянным (не должно автоматически закрываться)
          const modalElement = modalManager.instances[modalId].element;
          if (modalElement && modalElement.getAttribute('data-persistent') === 'true') {
            console.log('Модальное окно отмечено как persistent, пропускаем автоматическое закрытие');
            return; // Выходим и не закрываем окно автоматически
          }
          
          console.warn('Модальное окно не было закрыто автоматически, принудительная очистка');
          
          // Если колбэк еще не был вызван
          if (!callbackExecuted) {
            if (confirmed) {
              safeExecuteCallback(confirmCallback);
            } else {
              safeExecuteCallback(cancelCallback);
            }
          }
          
          // Безопасно пытаемся скрыть модальное окно
          try {
            if (modal && typeof modal.hide === 'function') {
              modal.hide();
            }
          } catch (e) {
            console.warn('Ошибка при скрытии модального окна:', e);
          }
          
          // Очищаем все модальные окна с задержкой
          setTimeout(function() {
            if (modalManager && typeof modalManager.cleanupAll === 'function') {
              modalManager.cleanupAll();
            }
          }, 1000);
        }
      } catch (e) {
        console.warn('Ошибка при автоматической очистке модального окна:', e);
      }
    }, 15000); // Увеличиваем таймаут до 15 секунд
    
    return true;
  } catch (e) {
    console.error('Ошибка при создании модального окна подтверждения:', e);
    
    // В случае ошибки, напрямую вызываем колбэк подтверждения
    // (предполагая, что это более безопасное действие)
    if (typeof confirmCallback === 'function') {
      try {
        confirmCallback();
      } catch (e) {
        console.error('Ошибка при выполнении колбэка подтверждения:', e);
      }
    }
    
    return false;
  }
}

// Создаем глобальные функции для работы с модальными окнами
window.modalManager = modalManager;
window.showConfirmationModal = showConfirmationModal;
// Для обратной совместимости со старым кодом
window.showConfirmModal = showConfirmationModal;

// Функция для очистки устаревших модальных окон
function cleanupModals() {
  try {
    // Получаем все элементы модальных окон
    const modals = document.querySelectorAll('.modal[id^="confirm-modal-"]');
    
    // Перебираем все найденные модальные окна
    modals.forEach(modalElement => {
      try {
        // Пропускаем постоянные модальные окна
        if (modalElement.getAttribute('data-persistent') === 'true') {
          console.log(`Модальное окно ${modalElement.id} отмечено как persistent, пропускаем очистку`);
          return;
        }
        
        // Проверяем наличие Bootstrap
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
          // Пытаемся получить экземпляр Modal для элемента
          const instance = bootstrap.Modal.getInstance(modalElement);
          
          // Если экземпляр существует, закрываем и уничтожаем его
          if (instance) {
            instance.hide();
            instance.dispose();
          }
        }
        
        // Удаляем элемент из DOM
        if (modalElement.parentNode) {
          modalElement.parentNode.removeChild(modalElement);
        }
      } catch (e) {
        console.error('Ошибка при очистке модального окна:', e);
      }
    });
    
    // Дополнительно очищаем остаточные элементы модальных окон
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => {
      if (backdrop.parentNode) {
        backdrop.parentNode.removeChild(backdrop);
      }
    });
    
    // Удаляем класс modal-open с body, если нет активных модальных окон
    if (document.querySelectorAll('.modal.show').length === 0) {
      document.body.classList.remove('modal-open');
      document.body.style.removeProperty('overflow');
      document.body.style.removeProperty('padding-right');
    }
  } catch (e) {
    console.error('Ошибка при очистке модальных окон:', e);
  }
}

// Экспортируем функцию cleanupModals в глобальную область видимости
window.cleanupModals = cleanupModals;

// Устанавливаем обработчик на все модальные окна при закрытии
$(document).ready(function() {
  // Обработчик на все модальные окна при закрытии
  $(document).on('hidden.bs.modal', '.modal', function(e) {
    // Предотвращаем повторные вызовы для одного и того же элемента
    if (e.target !== this) return;
    
    const modalId = e.target.id;
    console.log(`Модальное окно ${modalId} закрыто, выполняю отложенную очистку`);
    
    // Проверяем, не является ли модальное окно постоянным (не должно автоматически очищаться)
    if (e.target.getAttribute('data-persistent') === 'true') {
      console.log(`Модальное окно ${modalId} отмечено как persistent, пропускаем очистку`);
      return;
    }
    
    // Откладываем очистку, чтобы дать Bootstrap завершить свои процессы
    setTimeout(function() {
      try {
        // Вызываем cleanupModals только для модальных окон, которые не помечены как persistent
        if (modalId !== 'unsavedChangesModal') {
          cleanupModals();
        } else {
          // Для unsavedChangesModal очищаем только backdrops и стили body
          const backdrops = document.querySelectorAll('.modal-backdrop');
          backdrops.forEach(backdrop => {
            if (backdrop && backdrop.parentNode) {
              backdrop.parentNode.removeChild(backdrop);
            }
          });
          
          // Удаляем класс modal-open с body, если нет активных модальных окон
          if (document.querySelectorAll('.modal.show').length === 0) {
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
          }
        }
      } catch (err) {
        console.warn('Ошибка при очистке модальных окон после закрытия:', err);
      }
    }, 300);
  });
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
      // Сначала убедимся, что элемент существует
      if (!this._element) {
        console.warn('Bootstrap Modal: элемент не найден в _showElement');
        return;
      }
      
      // Убедимся, что элемент находится в DOM
      try {
        if (!document.body.contains(this._element)) {
          console.warn('Bootstrap Modal: элемент не добавлен в DOM');
          return;
        }
      } catch (err) {
        console.warn('Ошибка при проверке наличия элемента в DOM:', err);
        return;
      }
      
      // Безопасно вызываем оригинальный метод
      return safeElementAccess(() => {
        return originalShowElement.call(this, relatedTarget);
      }, (e) => {
        // В случае ошибки пытаемся скрыть модальное окно
        try {
          if (this._element) {
            this._element.style.display = 'none';
          }
          // Очищаем стили body
          document.body.classList.remove('modal-open');
          document.body.style.removeProperty('overflow');
          document.body.style.removeProperty('padding-right');
        } catch (cleanupErr) {
          console.warn('Ошибка при очистке после ошибки _showElement:', cleanupErr);
        }
      });
    };
    
    // Патчим метод hide для безопасного скрытия
    modalProto.hide = function() {
      // Сначала убедимся, что элемент существует
      if (!this._element) {
        console.warn('Bootstrap Modal: элемент не найден в hide');
        return;
      }
      
      // Безопасно вызываем оригинальный метод
      return safeElementAccess(() => {
        return originalHide.call(this);
      }, (e) => {
        // В случае ошибки выполняем минимальную очистку
        try {
          // Скрываем модальное окно вручную
          if (this._element) {
            this._element.classList.remove('show');
            this._element.style.display = 'none';
            this._element.setAttribute('aria-hidden', 'true');
          }
          
          // Очищаем стили body
          document.body.classList.remove('modal-open');
          document.body.style.removeProperty('overflow');
          document.body.style.removeProperty('padding-right');
          
          // Удаляем backdrop элементы
          const backdrops = document.querySelectorAll('.modal-backdrop');
          backdrops.forEach(backdrop => {
            if (backdrop && backdrop.parentNode) {
              backdrop.parentNode.removeChild(backdrop);
            }
          });
        } catch (cleanupErr) {
          console.warn('Ошибка при очистке после ошибки hide:', cleanupErr);
        }
      });
    };
    
    // Патчим остальные методы аналогично
    modalProto._resetAdjustments = function() {
      if (!this._element || !this._element.style) return;
      
      return safeElementAccess(() => {
        return originalResetAdjustments.call(this);
      });
    };
    
    modalProto._adjustDialog = function() {
      if (!this._element || !this._element.style) return;
      
      return safeElementAccess(() => {
        return originalAdjustDialog.call(this);
      });
    };
    
    modalProto._enforceFocus = function() {
      if (!this._element) return;
      
      return safeElementAccess(() => {
        return originalEnforceFocus.call(this);
      });
    };
    
    modalProto._setEscapeEvent = function() {
      if (!this._element) return;
      
      return safeElementAccess(() => {
        return originalSetEscapeEvent.call(this);
      });
    };
    
    modalProto._hideModal = function() {
      // Проверяем наличие элемента
      if (!this._element) {
        console.warn('Bootstrap Modal: элемент не найден в _hideModal');
        return;
      }
      
      return safeElementAccess(() => {
        return originalHideModal.call(this);
      }, (e) => {
        // Выполняем минимальную очистку в случае ошибки
        try {
          // Скрываем модальное окно
          if (this._element) {
            this._element.classList.remove('show');
            this._element.style.display = 'none';
            this._element.setAttribute('aria-hidden', 'true');
          }
          
          // Очищаем стили body
          document.body.classList.remove('modal-open');
          document.body.style.removeProperty('overflow');
          document.body.style.removeProperty('padding-right');
          
          // Удаляем backdrop элементы
          const backdrops = document.querySelectorAll('.modal-backdrop');
          backdrops.forEach(backdrop => {
            if (backdrop && backdrop.parentNode) {
              backdrop.parentNode.removeChild(backdrop);
            }
          });
        } catch (cleanupErr) {
          console.warn('Ошибка при очистке после ошибки _hideModal:', cleanupErr);
        }
      });
    };
    
    modalProto.dispose = function() {
      return safeElementAccess(() => {
        return originalDispose.call(this);
      }, (e) => {
        // В случае ошибки удаляем атрибуты модального окна
        try {
          if (this._element) {
            this._element.removeAttribute('aria-modal');
            this._element.removeAttribute('role');
            this._element.removeAttribute('aria-hidden');
          }
          
          // Очищаем стили body
          document.body.classList.remove('modal-open');
          document.body.style.removeProperty('overflow');
          document.body.style.removeProperty('padding-right');
        } catch (cleanupErr) {
          console.warn('Ошибка при очистке после ошибки dispose:', cleanupErr);
        }
      });
    };
    
    // Отмечаем, что патч применен
    bootstrap.Modal._patched = true;
    console.log('Bootstrap Modal успешно пропатчен');
    
    return true;
  } catch (e) {
    console.error('Ошибка при патчинге Bootstrap Modal:', e);
    return false;
  }
}

// Экспортируем функцию для использования в других файлах
window.patchBootstrapModal = patchBootstrapModal;

// Функция для немедленного патчинга Bootstrap Modal
function patchBootstrapImmediately() {
  // Проверяем, доступен ли Bootstrap в текущем контексте
  console.log('Проверка наличия Bootstrap:', typeof bootstrap);
  
  // Если bootstrap еще не определен, попробуем отложить выполнение
  if (typeof bootstrap === 'undefined') {
    console.log('Bootstrap не найден, устанавливаем обработчик события DOMContentLoaded');
    
    // Добавляем обработчик для вызова после полной загрузки страницы
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function() {
        console.log('DOMContentLoaded сработал, пробуем еще раз');
        setTimeout(patchBootstrapModal, 500); // Даем еще время на загрузку Bootstrap
      });
    } else {
      // Если DOM уже загружен, пробуем через задержку
      console.log('DOM уже загружен, пробуем с задержкой');
      setTimeout(patchBootstrapModal, 500);
    }
    
    return;
  }
  
  // Если bootstrap доступен, вызываем сразу
  console.log('Bootstrap найден, применяем патч');
  patchBootstrapModal();
}

// Экспортируем функцию в глобальную область видимости
window.patchBootstrapImmediately = patchBootstrapImmediately;

// Вызываем патч немедленно при загрузке скрипта
(function() {
  patchBootstrapImmediately();
})();

// Экспортируем функцию в глобальную область видимости
window.patchBootstrapImmediately = patchBootstrapImmediately;

// Функция для безопасной работы с модальным окном несохраненных изменений
function showUnsavedChangesConfirm(title, message, confirmText, cancelText, confirmCallback, cancelCallback, saveAndCloseCallback = null) {
  try {
    console.log('Открываю модальное окно несохраненных изменений');
    
    // ID нашего модального окна
    const modalId = 'unsavedChangesModal';
    
    // Проверяем существование модального окна в DOM
    const modalEl = document.getElementById(modalId);
    
    // Если модальное окно не найдено, используем нативный confirm
    if (!modalEl) {
      console.error(`Модальное окно с ID ${modalId} не найдено в DOM. Используется стандартный confirm.`);
      
      if (confirm(message || 'В форме есть несохраненные изменения. Вы уверены, что хотите закрыть её без сохранения?')) {
        if (typeof confirmCallback === 'function') {
          confirmCallback();
        }
      } else {
        if (typeof cancelCallback === 'function') {
          cancelCallback();
        }
      }
      
      return false;
    }
    
    // Обновляем содержимое модального окна
    const titleEl = modalEl.querySelector('.modal-title');
    const bodyEl = modalEl.querySelector('.modal-body p');
    const confirmBtn = modalEl.querySelector('#closeTabConfirm');
    const cancelBtn = modalEl.querySelector('[data-bs-dismiss="modal"]');
    const saveAndCloseBtn = modalEl.querySelector('#saveAndCloseConfirm');
    
    if (titleEl) titleEl.innerHTML = title || '<i class="fas fa-exclamation-triangle text-warning me-2"></i>Внимание!';
    if (bodyEl) bodyEl.textContent = message || 'В форме есть несохраненные изменения. Вы уверены, что хотите закрыть её без сохранения?';
    if (confirmBtn) confirmBtn.textContent = confirmText || 'Закрыть без сохранения';
    if (cancelBtn) cancelBtn.textContent = cancelText || 'Отмена';
    
    // Показываем/скрываем кнопку "Сохранить и закрыть" в зависимости от наличия колбэка
    if (saveAndCloseBtn) {
      if (typeof saveAndCloseCallback === 'function') {
        saveAndCloseBtn.style.display = 'inline-block';
      } else {
        saveAndCloseBtn.style.display = 'none';
      }
    }
    
    // Удаляем существующие обработчики с кнопки подтверждения
    if (confirmBtn) {
      // Очищаем все существующие обработчики
      const confirmBtnNew = confirmBtn.cloneNode(true);
      confirmBtn.parentNode.replaceChild(confirmBtnNew, confirmBtn);
      
      // Добавляем новый обработчик
      confirmBtnNew.addEventListener('click', function() {
        // Скрываем модальное окно через Bootstrap API
        let bootstrapModal = null;
        
        if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
          try {
            bootstrapModal = bootstrap.Modal.getInstance(modalEl);
            
            if (bootstrapModal) {
              bootstrapModal.hide();
            }
          } catch (err) {
            console.warn('Ошибка при закрытии модального окна через Bootstrap API', err);
          }
        }
        
        // Вызываем колбэк подтверждения с небольшой задержкой
        setTimeout(function() {
          if (typeof confirmCallback === 'function') {
            confirmCallback();
          }
        }, 300);
      });
    }
    
    // Обработчик для кнопки "Сохранить и закрыть"
    if (saveAndCloseBtn && typeof saveAndCloseCallback === 'function') {
      // Очищаем все существующие обработчики
      const saveAndCloseBtnNew = saveAndCloseBtn.cloneNode(true);
      saveAndCloseBtn.parentNode.replaceChild(saveAndCloseBtnNew, saveAndCloseBtn);
      
      // Добавляем новый обработчик
      saveAndCloseBtnNew.addEventListener('click', function() {
        // Скрываем модальное окно через Bootstrap API
        let bootstrapModal = null;
        
        if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
          try {
            bootstrapModal = bootstrap.Modal.getInstance(modalEl);
            
            if (bootstrapModal) {
              bootstrapModal.hide();
            }
          } catch (err) {
            console.warn('Ошибка при закрытии модального окна через Bootstrap API', err);
          }
        }
        
        // Вызываем колбэк сохранения и закрытия с небольшой задержкой
        setTimeout(function() {
          if (typeof saveAndCloseCallback === 'function') {
            saveAndCloseCallback();
          }
        }, 300);
      });
    }
    
    // Создаем переменную для отслеживания вызова колбэка отмены
    let cancelCallbackExecuted = false;
    
    // Функция для безопасного выполнения колбэка отмены
    const executeCancelCallback = function() {
      if (!cancelCallbackExecuted) {
        cancelCallbackExecuted = true;
        
        setTimeout(function() {
          if (typeof cancelCallback === 'function') {
            cancelCallback();
          }
        }, 300);
      }
    };
    
    // Добавляем обработчик события hidden.bs.modal напрямую к модальному окну
    const handleHidden = function() {
      modalEl.removeEventListener('hidden.bs.modal', handleHidden);
      
      // Вызываем колбэк отмены, если колбэк подтверждения еще не был вызван
      executeCancelCallback();
    };
    
    // Удаляем существующие обработчики события hidden.bs.modal
    modalEl.removeEventListener('hidden.bs.modal', handleHidden);
    
    // Добавляем новый обработчик
    modalEl.addEventListener('hidden.bs.modal', handleHidden);
    
    // Показываем модальное окно, используя Bootstrap API
    try {
      // Пытаемся получить существующий экземпляр модального окна
      let bootstrapModal = null;
      
      if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
        // Проверяем, существует ли уже экземпляр для этого элемента
        try {
          bootstrapModal = bootstrap.Modal.getInstance(modalEl);
          
          // Если существует, используем его
          if (bootstrapModal) {
            console.log('Используем существующий экземпляр модального окна');
          } else {
            // Иначе создаем новый
            console.log('Создаем новый экземпляр модального окна');
            bootstrapModal = new bootstrap.Modal(modalEl, {
              backdrop: true,
              keyboard: true,
              focus: true
            });
          }
          
          // Показываем модальное окно
          bootstrapModal.show();
        } catch (err) {
          console.error('Ошибка при показе модального окна через Bootstrap API', err);
          executeCancelCallback();
        }
      } else {
        console.error('Bootstrap Modal не найден, невозможно показать модальное окно');
        executeCancelCallback();
      }
    } catch (e) {
      console.error('Критическая ошибка при работе с модальным окном:', e);
      executeCancelCallback();
    }
    
    return true;
  } catch (e) {
    console.error('Ошибка при работе с модальным окном несохраненных изменений:', e);
    
    // Если произошла ошибка, используем стандартный confirm
    if (confirm(message || 'В форме есть несохраненные изменения. Вы уверены, что хотите закрыть её без сохранения?')) {
      if (typeof confirmCallback === 'function') {
        confirmCallback();
      }
    } else {
      if (typeof cancelCallback === 'function') {
        cancelCallback();
      }
    }
    
    return false;
  }
}

// Функция для закрытия модального окна несохраненных изменений
function hideUnsavedChangesModal() {
  try {
    const modalId = 'unsavedChangesModal';
    const modalEl = document.getElementById(modalId);
    
    if (!modalEl) {
      console.warn(`Модальное окно с ID ${modalId} не найдено для закрытия`);
      return;
    }
    
    // Пытаемся закрыть через Bootstrap API
    if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
      try {
        const bootstrapModal = bootstrap.Modal.getInstance(modalEl);
        
        if (bootstrapModal) {
          bootstrapModal.hide();
          return;
        }
      } catch (err) {
        console.warn('Ошибка при получении экземпляра модального окна:', err);
      }
    }
    
    // Запасной вариант: вручную скрываем модальное окно
    try {
      modalEl.classList.remove('show');
      modalEl.style.display = 'none';
      modalEl.setAttribute('aria-hidden', 'true');
    } catch (err) {
      console.warn('Ошибка при ручном скрытии модального окна:', err);
    }
    
    // Удаляем .modal-backdrop и очищаем стили body
    try {
      // Очищаем стили body
      document.body.classList.remove('modal-open');
      document.body.style.removeProperty('overflow');
      document.body.style.removeProperty('padding-right');
      
      // Удаляем backdrop
      const backdrops = document.querySelectorAll('.modal-backdrop');
      backdrops.forEach(backdrop => {
        if (backdrop && backdrop.parentNode) {
          backdrop.parentNode.removeChild(backdrop);
        }
      });
    } catch (err) {
      console.warn('Ошибка при очистке DOM после закрытия модального окна:', err);
    }
  } catch (e) {
    console.error('Критическая ошибка при закрытии модального окна:', e);
  }
}

// Экспортируем функции в глобальную область видимости
window.showUnsavedChangesConfirm = showUnsavedChangesConfirm;
window.hideUnsavedChangesModal = hideUnsavedChangesModal;

// Вызываем патч немедленно при загрузке скрипта
(function() {
  // Проверяем, что мы находимся в браузерной среде
  if (typeof window === 'undefined' || typeof document === 'undefined') {
    return;
  }
  
  // Проверяем, что jQuery и Bootstrap доступны
  if (typeof $ !== 'undefined') {
    patchBootstrapImmediately();
  } else {
    console.error('jQuery не обнаружен даже после загрузки DOM, патч не применён');
  }
})();

// Экспортируем функции в глобальное пространство имен для доступа из других скриптов
window.showConfirmationModal = showConfirmationModal;
window.cleanupModals = cleanupModals;
window.patchBootstrapModal = patchBootstrapModal;
window.patchBootstrapImmediately = patchBootstrapImmediately;
window.showUnsavedChangesConfirm = showUnsavedChangesConfirm;
window.hideUnsavedChangesModal = hideUnsavedChangesModal;

// Выполняем патч Bootstrap Modal при загрузке скрипта
$(document).ready(function() {
  console.log('modal.js загружен, патчим Bootstrap Modal');
  if (typeof patchBootstrapImmediately === 'function') {
    patchBootstrapImmediately();
  }
});

// Функция для тестирования работоспособности модальных окон после загрузки страницы
function testBootstrapModalOnLoad() {
  console.log('[MODAL_TEST] Запуск тестирования модальных окон после загрузки');
  
  // Проверка доступности bootstrap
  const bootstrapAvailable = typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined';
  console.log('[MODAL_TEST] Bootstrap доступен:', bootstrapAvailable);
  
  if (!bootstrapAvailable) {
    console.error('[MODAL_TEST] Bootstrap недоступен, тестирование невозможно');
    return false;
  }
  
  // Создаем тестовое модальное окно
  const testModalId = 'testBootstrapModal_' + Date.now();
  
  // Создаем HTML для модального окна
  const modalHtml = `
    <div class="modal fade" id="${testModalId}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Тестовое модальное окно</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Это тест модального окна Bootstrap. Если вы видите это окно, значит Bootstrap Modal работает корректно.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">ОК</button>
          </div>
        </div>
      </div>
    </div>
  `;
  
  // Добавляем модальное окно в документ
  document.body.insertAdjacentHTML('beforeend', modalHtml);
  
  // Получаем DOM-элемент
  const modalEl = document.getElementById(testModalId);
  if (!modalEl) {
    console.error('[MODAL_TEST] Элемент модального окна не найден после добавления в DOM');
    return false;
  }
  
  // Создаем экземпляр модального окна
  try {
    const modal = new bootstrap.Modal(modalEl);
    
    // Показываем модальное окно
    modal.show();
    
    console.log('[MODAL_TEST] Тестовое модальное окно показано успешно');
    
    // Через 5 секунд автоматически закрываем окно
    setTimeout(function() {
      try {
        modal.hide();
        console.log('[MODAL_TEST] Тестовое модальное окно закрыто автоматически');
        
        // Удаляем элемент после закрытия
        setTimeout(function() {
          if (modalEl.parentNode) {
            modalEl.parentNode.removeChild(modalEl);
          }
        }, 500);
      } catch (e) {
        console.error('[MODAL_TEST] Ошибка при закрытии тестового окна:', e);
      }
    }, 5000);
    
    return true;
  } catch (e) {
    console.error('[MODAL_TEST] Ошибка при создании тестового модального окна:', e);
    return false;
  }
}

// Экспортируем функцию тестирования в глобальную область видимости
window.testBootstrapModalOnLoad = testBootstrapModalOnLoad;

// Запускаем тест через 3 секунды после загрузки страницы
document.addEventListener('DOMContentLoaded', function() {
  console.log('[MODAL_TEST] DOMContentLoaded событие получено');
  
  // Отложенный запуск теста для гарантированной загрузки Bootstrap
  setTimeout(function() {
    // Если функция восстановления сессии не была вызвана, запускаем тест
    // if (typeof window.sessionRestoreAttempts === 'undefined' || window.sessionRestoreAttempts === 0) {
    //   console.log('[MODAL_TEST] Восстановление сессии не запускалось, выполняем тест модальных окон');
    //   testBootstrapModalOnLoad();
    // } else {
    //   console.log('[MODAL_TEST] Восстановление сессии уже запускалось, пропускаем тест');
    // }
  }, 3000);
});


// Глобальный вызов патча Bootstrap Modal (если он еще не был вызван)
// Это гарантирует, что все модальные окна будут работать корректно
// patchBootstrapImmediately(); // Вызов может быть здесь или в app.js

// НОВЫЙ КОД: Фикс "скукоживания" контента при открытии модальных окон Bootstrap
document.addEventListener('DOMContentLoaded', function () {
  const mainNavbar = document.querySelector('.navbar'); // Селектор главного меню
  const mainContentContainer = document.querySelector('.container.mt-3'); // Селектор основного контейнера контента из index.php
  // Дополнительно, если есть общий content-wrapper, который используется на всех страницах
  const generalContentWrapper = document.querySelector('.content-wrapper'); 

  function getScrollbarWidth() {
    const outer = document.createElement('div');
    outer.style.visibility = 'hidden';
    outer.style.overflow = 'scroll';
    document.body.appendChild(outer);
    const inner = document.createElement('div');
    outer.appendChild(inner);
    const scrollbarWidth = outer.offsetWidth - inner.offsetWidth;
    outer.parentNode.removeChild(outer);
    return scrollbarWidth;
  }

  function applyPadding(scrollbarWidth) {
    if (mainNavbar) {
      mainNavbar.style.paddingRight = scrollbarWidth + 'px';
    }
    if (mainContentContainer) { // Для структуры из index.php
      mainContentContainer.style.paddingRight = scrollbarWidth + 'px';
    }
    if (generalContentWrapper) { // Для общей структуры, если есть
        generalContentWrapper.style.paddingRight = scrollbarWidth + 'px';
    }
    // Если есть другие фиксированные элементы, их селекторы нужно добавить сюда
  }

  function resetPadding() {
    if (mainNavbar) {
      mainNavbar.style.paddingRight = '';
    }
    if (mainContentContainer) {
      mainContentContainer.style.paddingRight = '';
    }
    if (generalContentWrapper) {
        generalContentWrapper.style.paddingRight = '';
    }
  }

  // Применяем ко всем модальным окнам Bootstrap на странице
  const allModals = document.querySelectorAll('.modal');
  
  allModals.forEach(modal => {
    modal.addEventListener('show.bs.modal', function (event) {
      // Важно: Не добавлять padding к самому модальному окну!
      if (event.target.classList.contains('modal')) {
        const scrollbarWidth = getScrollbarWidth();
        applyPadding(scrollbarWidth); // Эта функция должна применять padding к body/navbar, НЕ к event.target
      }
    });
    modal.addEventListener('hidden.bs.modal', function (event) {
      // Важно: Не изменять padding самого модального окна!
      if (event.target.classList.contains('modal')) {
        resetPadding(); // Эта функция должна сбрасывать padding на body/navbar, НЕ на event.target
      }
    });
  });
}); 