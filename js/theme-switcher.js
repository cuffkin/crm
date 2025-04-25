// /crm/js/theme-switcher.js
document.addEventListener('DOMContentLoaded', function() {
    // Проверяем сохраненную в localStorage тему
    const currentTheme = localStorage.getItem('theme') || 'light';
    
    // Устанавливаем состояние переключателя
    const themeSwitch = document.getElementById('theme-switcher');
    if (themeSwitch) {
        themeSwitch.checked = currentTheme === 'dark';
        setTheme(currentTheme);
        
        // Обработчик изменения темы
        themeSwitch.addEventListener('change', function() {
            const theme = this.checked ? 'dark' : 'light';
            setTheme(theme);
            localStorage.setItem('theme', theme);
        });
    }
    
    // Функция установки темы
    function setTheme(theme) {
        if (theme === 'dark') {
            document.body.classList.remove('light-theme');
            document.body.classList.add('dark-theme');
            
            // Обновляем переменные CSS для темной темы
            document.documentElement.style.setProperty('--bg-main', '#121728');
            document.documentElement.style.setProperty('--bg-card', '#1a2035');
            document.documentElement.style.setProperty('--bg-header', '#151c2c');
            document.documentElement.style.setProperty('--text-primary', '#e2e8f0');
            document.documentElement.style.setProperty('--text-secondary', '#a9b1c1');
            document.documentElement.style.setProperty('--border-color', '#232b3e');
        } else {
            document.body.classList.remove('dark-theme');
            document.body.classList.add('light-theme');
            
            // Обновляем переменные CSS для светлой темы
            document.documentElement.style.setProperty('--bg-main', '#f9fafc');
            document.documentElement.style.setProperty('--bg-card', '#ffffff');
            document.documentElement.style.setProperty('--bg-header', '#f8f9fa');
            document.documentElement.style.setProperty('--text-primary', '#303442');
            document.documentElement.style.setProperty('--text-secondary', '#555b6e');
            document.documentElement.style.setProperty('--border-color', '#eaedf3');
        }
        
        // Обновляем metas
        const metaColor = theme === 'dark' ? '#121728' : '#f9fafc';
        let metaThemeColor = document.querySelector('meta[name="theme-color"]');
        if (metaThemeColor) {
            metaThemeColor.content = metaColor;
        }
        
        // Вызываем событие изменения темы
        const event = new CustomEvent('themeChanged', { detail: { theme } });
        document.dispatchEvent(event);
    }
});