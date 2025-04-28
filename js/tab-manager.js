// /crm/js/tab-manager.js

// Этот файл содержит функции для управления вкладками и их сортировкой

document.addEventListener('DOMContentLoaded', function() {
    // Добавляем стили для наведения мыши на крестик
    addHoverStyles();
});

// Функция для добавления стилей при наведении на крестик
function addHoverStyles() {
    const style = document.createElement('style');
    style.textContent = `
        .nav-tabs .nav-link .btn-close {
            transition: opacity 0.2s, transform 0.2s, background-color 0.2s;
        }
        
        .nav-tabs .nav-link:hover .btn-close {
            opacity: 1;
            transform: scale(1.1);
        }
        
        .nav-tabs .nav-link .btn-close:hover {
            opacity: 1;
            transform: scale(1.2);
            background-color: var(--danger-color);
        }
        
        /* Курсор на вкладках для перетаскивания */
        .nav-tabs .nav-item {
            cursor: move;
        }
    `;
    document.head.appendChild(style);
}

// Функция для получения метаданных о вкладке
function getTabMetadata(tabElement) {
    const linkElement = tabElement.querySelector('.nav-link');
    if (!linkElement) return null;
    
    const contentId = linkElement.getAttribute('href').substring(1);
    const title = linkElement.textContent.trim().replace('×', '').trim();
    const modulePath = linkElement.getAttribute('data-module') || '';
    
    return {
        tabId: linkElement.id,
        contentId: contentId,
        title: title,
        modulePath: modulePath,
        isActive: linkElement.classList.contains('active')
    };
}

// Обработчик для обнаружения изменений в формах УДАЛЕН
// Эта функциональность уже реализована в app.js с использованием атрибута data-has-unsaved-changes
// и глобального делегированного обработчика $(document).on('input change', '.tab-pane input, .tab-pane select, .tab-pane textarea', ...)

// Экспортируем функции
window.tabManager = {
    getTabMetadata: getTabMetadata
};