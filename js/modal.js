// Универсальный обработчик модальных окон для CRM
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
    
    // Откладываем очистку, чтобы дать Bootstrap завершить свои процессы
    setTimeout(function() {
      try {
        cleanupModals();
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
        setTimeout(cleanupModals, 0);
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
          setTimeout(cleanupModals, 0);
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
           'hidePrevented.bs.modal'].forEach(event => {
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
        
        // Вызываем событие hidden.bs.modal
        try {
          const hiddenEvent = new Event('hidden.bs.modal');
          this._element.dispatchEvent(hiddenEvent);
        } catch (e) {
          console.warn('Error triggering hidden.bs.modal:', e);
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
  // Проверяем, что мы находимся в браузерной среде
  if (typeof window === 'undefined' || typeof document === 'undefined') {
    return;
  }
  
  // Проверяем, что jQuery и Bootstrap доступны
  if (typeof $ === 'undefined') {
    console.warn('jQuery не обнаружен, отложенный запуск патча Bootstrap Modal');
    // Ждем загрузку DOM и пробуем снова
    document.addEventListener('DOMContentLoaded', function() {
      if (typeof $ !== 'undefined') {
        patchBootstrapImmediately();
      } else {
        console.error('jQuery не обнаружен даже после загрузки DOM, патч не применён');
      }
    });
    return;
  }
  
  // Применяем патч с небольшой задержкой, чтобы Bootstrap успел загрузиться
  setTimeout(patchBootstrapImmediately, 100);
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
function showUnsavedChangesConfirm(title, message, confirmText, cancelText, confirmCallback, cancelCallback) {
  try {
    // Сначала очищаем возможные существующие модальные окна для избежания конфликтов
    cleanupModals();
    
    // ID нашего модального окна
    const modalId = 'unsavedChangesModal';
    
    // Проверяем существование модального окна в DOM
    let modalEl = document.getElementById(modalId);
    
    // Если модальное окно не найдено, создаем его
    if (!modalEl) {
      console.log('Создаём новое модальное окно для несохраненных изменений');
      
      // Создаем новый элемент модального окна
      modalEl = document.createElement('div');
      modalEl.id = modalId;
      modalEl.className = 'modal fade';
      modalEl.setAttribute('tabindex', '-1');
      modalEl.setAttribute('aria-labelledby', `${modalId}Label`);
      modalEl.setAttribute('aria-hidden', 'true');
      
      // Создаем структуру модального окна
      modalEl.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="${modalId}Label">
                <i class="fas fa-exclamation-triangle text-warning me-2"></i>Внимание!
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
              <p>В форме есть несохраненные изменения. Вы уверены, что хотите закрыть её без сохранения?</p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
              <button type="button" class="btn btn-danger" id="closeTabConfirm">Закрыть без сохранения</button>
            </div>
          </div>
        </div>
      `;
      
      // Добавляем модальное окно в body
      document.body.appendChild(modalEl);
    }
    
    // Обновляем содержимое модального окна
    const titleEl = modalEl.querySelector('.modal-title');
    const bodyEl = modalEl.querySelector('.modal-body p');
    const confirmBtn = modalEl.querySelector('#closeTabConfirm');
    const cancelBtn = modalEl.querySelector('[data-bs-dismiss="modal"]');
    
    if (titleEl) titleEl.innerHTML = title || '<i class="fas fa-exclamation-triangle text-warning me-2"></i>Внимание!';
    if (bodyEl) bodyEl.textContent = message || 'В форме есть несохраненные изменения. Вы уверены, что хотите закрыть её без сохранения?';
    if (confirmBtn) confirmBtn.textContent = confirmText || 'Закрыть без сохранения';
    if (cancelBtn) cancelBtn.textContent = cancelText || 'Отмена';
    
    // Удаляем все существующие обработчики событий кнопки подтверждения
    if (confirmBtn) {
      const newConfirmBtn = confirmBtn.cloneNode(true);
      if (confirmBtn.parentNode) {
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
      }
      
      // Добавляем обработчик для новой кнопки через прямое назначение свойства onclick
      newConfirmBtn.onclick = function() {
        // Закрываем модальное окно
        hideUnsavedChangesModal();
        
        // Вызываем функцию подтверждения
        if (typeof confirmCallback === 'function') {
          setTimeout(confirmCallback, 100);
        }
      };
    }
    
    // Переменная для отслеживания, был ли вызван колбэк отмены
    let cancelHandled = false;
    
    // Функция для безопасного вызова колбэка отмены
    const handleCancel = function() {
      if (cancelHandled) return;
      cancelHandled = true;
      
      // Вызываем функцию отмены
      if (typeof cancelCallback === 'function') {
        setTimeout(cancelCallback, 100);
      }
    };
    
    // Устанавливаем обработчик на событие скрытия модального окна через прямое назначение свойства
    modalEl.addEventListener('hidden.bs.modal', function modalHiddenHandler() {
      modalEl.removeEventListener('hidden.bs.modal', modalHiddenHandler);
      handleCancel();
    });
    
    // Инициализируем и показываем модальное окно
    try {
      let modal = null;
      
      // Проверяем доступность Bootstrap
      if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
        // Пытаемся получить существующий экземпляр
        try {
          modal = bootstrap.Modal.getInstance(modalEl);
        } catch (err) {
          console.log('Экземпляр модального окна не найден, создаём новый');
        }
        
        // Если экземпляр не найден, создаем новый
        if (!modal) {
          modal = new bootstrap.Modal(modalEl);
        }
        
        // Показываем модальное окно через Bootstrap API
        modal.show();
      } else {
        // Ручное отображение модального окна, если Bootstrap недоступен
        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        document.body.classList.add('modal-open');
      }
    } catch (e) {
      console.error('Ошибка при инициализации модального окна:', e);
      // В случае ошибки используем нативный confirm
      if (confirm(message || 'В форме есть несохраненные изменения. Вы уверены, что хотите закрыть её без сохранения?')) {
        if (typeof confirmCallback === 'function') {
          confirmCallback();
        }
      } else {
        if (typeof cancelCallback === 'function') {
          cancelCallback();
        }
      }
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
      console.log('Модальное окно несохраненных изменений не найдено для закрытия');
      return;
    }
    
    // Проверяем, отображается ли модальное окно
    const isVisible = modalEl.classList.contains('show') || modalEl.style.display === 'block';
    if (!isVisible) {
      console.log('Модальное окно уже закрыто или удалено');
      return;
    }
    
    // Пытаемся закрыть через Bootstrap API если доступно
    if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
      try {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) {
          modal.hide();
          return;
        }
      } catch (err) {
        console.log('Не удалось получить экземпляр модального окна, используем ручное закрытие');
      }
    }
    
    // Ручное закрытие модального окна, если Bootstrap API недоступно или не сработало
    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    modalEl.setAttribute('aria-hidden', 'true');
    
    // Очищаем стили body
    document.body.classList.remove('modal-open');
    document.body.style.paddingRight = '';
    document.body.style.overflow = '';
    
    // Удаляем backdrop элементы
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => {
      if (backdrop && backdrop.parentNode) {
        backdrop.parentNode.removeChild(backdrop);
      }
    });
    
    // Генерируем событие hidden.bs.modal для запуска обработчиков
    try {
      const event = new Event('hidden.bs.modal');
      modalEl.dispatchEvent(event);
    } catch (err) {
      console.log('Не удалось сгенерировать событие hidden.bs.modal');
    }
    
    console.log('Модальное окно несохраненных изменений закрыто');
  } catch (e) {
    console.error('Ошибка при закрытии модального окна несохраненных изменений:', e);
    
    // В случае ошибки используем общую очистку модальных окон
    try {
      cleanupModals();
    } catch (err) {
      console.error('Не удалось выполнить cleanupModals:', err);
    }
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