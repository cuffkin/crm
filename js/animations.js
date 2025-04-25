/**
 * /crm/js/animations.js
 * Скрипт для добавления различных анимаций и визуальных эффектов
 */

document.addEventListener('DOMContentLoaded', function() {
    // Инициализация всех подсказок и всплывающих подсказок Bootstrap
    initTooltips();
    
    // Добавление анимаций при прокрутке
    initScrollAnimations();
    
    // Добавление эффектов для кнопок
    initButtonEffects();
    
    // Инициализация эффектов для карточек
    initCardEffects();
    
    // Инициализация анимаций для списков
    initListAnimations();
    
    // Анимация для модальных окон
    initModalAnimations();
    
    // Инициализация эффектов Ripple для кнопок
    initRippleEffect();
});

/**
 * Инициализация всплывающих подсказок Bootstrap
 */
function initTooltips() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            delay: { show: 300, hide: 100 }
        });
    });
    
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

/**
 * Инициализация анимаций при прокрутке
 */
function initScrollAnimations() {
    const animateElements = document.querySelectorAll('.animate-on-scroll');
    
    function checkScroll() {
        const windowHeight = window.innerHeight;
        const windowTopPosition = window.scrollY;
        const windowBottomPosition = windowTopPosition + windowHeight;
        
        animateElements.forEach(function(element) {
            const elementHeight = element.offsetHeight;
            const elementTopPosition = element.getBoundingClientRect().top + windowTopPosition;
            const elementBottomPosition = elementTopPosition + elementHeight;
            
            // Проверяем, видим ли элемент
            if (elementBottomPosition >= windowTopPosition && elementTopPosition <= windowBottomPosition) {
                element.classList.add('animated');
            }
        });
    }
    
    // Запускаем проверку при прокрутке
    window.addEventListener('scroll', checkScroll);
    // Запускаем один раз при загрузке
    checkScroll();
}

/**
 * Инициализация эффектов для кнопок
 */
function initButtonEffects() {
    // Эффект нажатия для кнопок
    const buttons = document.querySelectorAll('.btn');
    
    buttons.forEach(function(button) {
        // Добавляем эффект при наведении
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.2)';
        });
        
        // Возвращаем в исходное состояние
        button.addEventListener('mouseleave', function() {
            this.style.transform = '';
            this.style.boxShadow = '';
        });
        
        // Эффект при клике
        button.addEventListener('mousedown', function() {
            this.style.transform = 'translateY(1px)';
            this.style.boxShadow = '0 2px 4px rgba(0, 0, 0, 0.2)';
        });
        
        // Возвращаем после клика
        button.addEventListener('mouseup', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.2)';
        });
    });
}

/**
 * Инициализация эффектов для карточек
 */
function initCardEffects() {
    const cards = document.querySelectorAll('.card');
    
    cards.forEach(function(card) {
        // Добавляем эффект при наведении
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = 'var(--shadow-lg)';
            this.style.transition = 'transform 0.3s ease, box-shadow 0.3s ease';
        });
        
        // Возвращаем в исходное состояние
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
            this.style.boxShadow = '';
        });
    });
}

/**
 * Инициализация анимаций для элементов списка
 */
function initListAnimations() {
    const listItems = document.querySelectorAll('.list-item-animated');
    
    listItems.forEach(function(item, index) {
        // Устанавливаем задержку для элементов
        item.style.opacity = '0';
        item.style.animation = 'fadeInUp 0.5s ease forwards';
        item.style.animationDelay = (0.1 * index) + 's';
    });
}

/**
 * Инициализация анимаций для модальных окон
 */
function initModalAnimations() {
    const modals = document.querySelectorAll('.modal');
    
    modals.forEach(function(modal) {
        modal.addEventListener('show.bs.modal', function() {
            const modalDialog = this.querySelector('.modal-dialog');
            modalDialog.classList.add('anim-zoom-in');
            
            setTimeout(function() {
                modalDialog.classList.remove('anim-zoom-in');
            }, 500);
        });
    });
}

/**
 * Эффект волны (Ripple) для кнопок и элементов
 */
function initRippleEffect() {
    const rippleElements = document.querySelectorAll('.ripple-effect');
    
    rippleElements.forEach(function(element) {
        element.addEventListener('click', function(event) {
            const ripple = document.createElement('span');
            ripple.classList.add('ripple');
            this.appendChild(ripple);
            
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            
            ripple.style.width = ripple.style.height = size * 2 + 'px';
            ripple.style.left = (event.clientX - rect.left - size) + 'px';
            ripple.style.top = (event.clientY - rect.top - size) + 'px';
            
            setTimeout(function() {
                ripple.remove();
            }, 600);
        });
    });
}