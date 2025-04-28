<?php
// /crm/includes/footer.php
?>

<script src="/crm/assets/jquery-3.3.1/jquery.min.js"></script>
<script src="/crm/assets/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script src="/crm/js/notification-handler.js"></script>
<script src="/crm/js/modal.js"></script>
<script src="/crm/js/app.js"></script>
<script src="/crm/js/session-status.js"></script>

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