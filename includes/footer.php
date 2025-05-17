<?php
// /crm/includes/footer.php
?>

<!-- jQuery загружается в header.php -->
<script src="/crm/assets/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script src="/crm/js/notification-handler.js"></script>
<script src="/crm/js/modal.js"></script>
<script src="/crm/js/common.js"></script>
<script src="/crm/js/app.js"></script>
<script src="/crm/js/session-status.js"></script>

<!-- Дополнительная инициализация модальных окон -->
<script>
  // Флаг инициализации Bootstrap
  window.bootstrapInitialized = false;
  
  // Убеждаемся, что Bootstrap и Modal правильно загружены
  (function checkBootstrapAvailability() {
    console.log('[BOOTSTRAP_INIT] Проверка доступности Bootstrap...');
    
    // Если Bootstrap еще не загружен, повторяем проверку через небольшую задержку
    if (typeof bootstrap === 'undefined' || typeof bootstrap.Modal === 'undefined') {
      console.log('[BOOTSTRAP_INIT] Bootstrap пока не загружен, повторная проверка через 200мс');
      setTimeout(checkBootstrapAvailability, 200);
      return;
    }
    
    console.log('[BOOTSTRAP_INIT] Bootstrap успешно загружен!');
    window.bootstrapInitialized = true;
    
    // Патчим Bootstrap Modal, если функция доступна
    if (typeof window.patchBootstrapModal === 'function') {
      window.patchBootstrapModal();
    }
    
    // Инициализируем все модальные окна на странице
    document.querySelectorAll('.modal').forEach(function(modalEl) {
      try {
        // Попытка инициализировать модальное окно
        console.log('[BOOTSTRAP_INIT] Инициализация модального окна:', modalEl.id);
        const instance = new bootstrap.Modal(modalEl, {
          backdrop: true,
          keyboard: true,
          focus: true
        });
        
        // Сохраняем экземпляр в элементе для будущего доступа
        modalEl._bootstrapModal = instance;
      } catch (e) {
        console.error('[BOOTSTRAP_INIT] Ошибка при инициализации модального окна:', e);
      }
    });
    
    // Пытаемся восстановить сессию пользователя
    if (typeof restoreUserSession === 'function') {
      console.log('[BOOTSTRAP_INIT] Запускаем восстановление сессии');
      // Даем немного времени для завершения инициализации
      setTimeout(restoreUserSession, 500);
    } else {
      console.error('[BOOTSTRAP_INIT] Функция restoreUserSession не найдена!');
    }
  })();
  
  // Принудительно запускаем восстановление сессии через 5 секунд, если оно не было запущено
  setTimeout(function() {
    if (!window.bootstrapInitialized) {
      console.warn('[EMERGENCY] Bootstrap не был инициализирован за 5 секунд!');
      console.warn('[EMERGENCY] Пытаемся запустить восстановление сессии принудительно...');
      
      if (typeof restoreUserSession === 'function') {
        restoreUserSession();
      } else {
        console.error('[EMERGENCY] Функция restoreUserSession не найдена!');
      }
    }
  }, 5000);
  
  // Пробуем создать тестовое модальное окно после полной загрузки страницы
  document.addEventListener('DOMContentLoaded', function() {
    console.log('[BOOTSTRAP_INIT] DOMContentLoaded событие сработало');
    
    setTimeout(function() {
      try {
        console.log('[BOOTSTRAP_INIT] Проверка работы модальных окон...');
        
        // Создаем тестовое модальное окно
        const testModalHTML = `
          <div class="modal fade" id="testStartupModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Тестовое модальное окно</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <p>Это тестовое модальное окно для проверки работы Bootstrap.</p>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                </div>
              </div>
            </div>
          </div>
        `;
        
        // Добавляем тестовое модальное окно в DOM
        document.body.insertAdjacentHTML('beforeend', testModalHTML);
        
        // Экспортируем тестовую функцию
        window.testStartupModal = function() {
          const testModal = document.getElementById('testStartupModal');
          if (!testModal) {
            console.error('[TEST_MODAL] Тестовое модальное окно не найдено в DOM!');
            return false;
          }
          
          try {
            if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
              const bsModal = new bootstrap.Modal(testModal);
              bsModal.show();
              return true;
            } else {
              console.error('[TEST_MODAL] Bootstrap Modal недоступен!');
              return false;
            }
          } catch (e) {
            console.error('[TEST_MODAL] Ошибка при показе тестового модального окна:', e);
            return false;
          }
        };
      } catch (e) {
        console.error('[BOOTSTRAP_INIT] Ошибка при создании тестового модального окна:', e);
      }
    }, 2000);
  });
</script>

<!-- Скрипт для создания переключателя темы -->
<script>
  // Проверяем существование переключателя темы
  document.addEventListener('DOMContentLoaded', function() {
    // Если переключатель уже существует, ничего не делаем
    if (document.getElementById('theme-switcher')) return;
    
    // Ищем навбар
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;
    
    // Создаем переключатель
    const switcherContainer = document.createElement('div');
    switcherContainer.className = 'ms-auto me-3 d-flex align-items-center';
    switcherContainer.innerHTML = `
      <label class="theme-switcher mb-0">
        <input type="checkbox" id="theme-switcher">
        <span class="theme-slider">
          <i class="fas fa-sun theme-icon theme-icon-light"></i>
          <i class="fas fa-moon theme-icon theme-icon-dark"></i>
        </span>
      </label>
    `;
    
    // Добавляем в навбар
    const navbarContent = navbar.querySelector('.container-fluid');
    if (navbarContent) {
      navbarContent.appendChild(switcherContainer);
      
      // Инициализируем переключатель
      const themeSwitch = document.getElementById('theme-switcher');
      const currentTheme = localStorage.getItem('theme') || 'light';
      themeSwitch.checked = currentTheme === 'dark';
      
      // Обработчик изменения
      themeSwitch.addEventListener('change', function() {
        const theme = this.checked ? 'dark' : 'light';
        localStorage.setItem('theme', theme);
        
        // Обновляем страницу для применения темы
        window.location.reload();
      });
    }
  });
</script>

<!-- Модальное окно предупреждения о несохраненных изменениях -->
<div class="modal fade" id="unsavedChangesModal" tabindex="-1" aria-labelledby="unsavedChangesModalLabel" role="dialog" aria-modal="true" data-persistent="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="unsavedChangesModalLabel">
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
</div>

</body>
</html>